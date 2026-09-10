<?php
if(!getenv('ZM_CONFIG_FILE')) throw new RuntimeException('Run through integration.cjs with isolated storage.');
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/blog.php';
require dirname(__DIR__).'/app/blog-ai.php';
function verify(bool $ok,string $message):void { if(!$ok) throw new RuntimeException($message); }
$zone=new DateTimeZone('Europe/Amsterdam');
foreach(['2026-03-27 12:00'=>'2026-03-31 10:00 +02:00','2026-10-23 12:00'=>'2026-10-27 10:00 +01:00','2026-09-15 10:00'=>'2026-09-22 10:00 +02:00'] as $input=>$expected) {
    $next=blog_next_run(2,'10:00',(new DateTimeImmutable($input,$zone))->getTimestamp());
    verify((new DateTimeImmutable('@'.$next))->setTimezone($zone)->format('Y-m-d H:i P')===$expected,'DST/weekly calculation');
}
$db=blog_db(); $now=time();
$db->exec("UPDATE blog_posts SET status='draft' WHERE status='queued'");
$id=blog_save(['title'=>'Scheduler test','body'=>'Volledig artikel','status'=>'queued'],[]);
$db->prepare("UPDATE blog_schedule SET enabled=1,mode='ready',next_run=? WHERE id=1")->execute([$now]);
verify(blog_run_schedule($now)['published']===1,'Scheduled queue publication');
verify(blog_run_schedule($now)['published']===0,'No duplicate publication');
verify(blog_post($id)['status']==='published','Public status');
$empty=blog_save(['title'=>'Missing AI configuration','status'=>'queued'],[]);
$db->prepare("UPDATE blog_schedule SET mode='generate',next_run=? WHERE id=1")->execute([$now]);
$result=blog_run_schedule($now); verify(!empty($result['error']),'Missing API key reported');
verify(blog_post($empty)['status']==='queued','Failed generation stays private');
verify(blog_run_schedule($now+30)['published']===0,'Failure backoff');
$db->exec('UPDATE blog_schedule SET enabled=0 WHERE id=1');
verify(blog_run_schedule($now+7200)['published']===0,'Paused queue');
$id=blog_save(['title'=>'Explicit date','body'=>'Scheduled text','status'=>'draft'],[]);
$db->prepare("UPDATE blog_posts SET status='scheduled',publish_at=? WHERE id=?")->execute([$now,$id]);
verify(blog_run_schedule($now)['published']===1,'Explicit date works independently');
// The injected transport never uses a network or an API key.
$body=str_repeat('Praktische uitleg voor ondernemers. ',60);
$result=blog_generate('Een onderwerp',function($request) use($body) {
    verify($request['store']===false && $request['text']['format']['strict']===true,'Structured response request');
    return ['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['body'=>$body,'excerpt'=>'Praktische tips.'])]]]]];
});
verify($result['body']===trim($body),'Generated content parsed');
foreach([['status'=>'incomplete'],['status'=>'completed','output'=>[['content'=>[['type'=>'refusal']]]]]] as $response) {
    $failed=false; try { blog_generate('Onderwerp',fn()=> $response); } catch(RuntimeException) { $failed=true; } verify($failed,'Incomplete/refused response rejected');
}
verify(!str_contains(blog_body('<script>alert(1)</script>'),'<script>'),'Article HTML escaped');
$db->exec("UPDATE blog_posts SET status='draft' WHERE status='queued'");
$auto=blog_save(['title'=>'Automatic illustrated article','status'=>'queued'],[]);
$db->prepare("UPDATE blog_schedule SET enabled=1,mode='generate',next_run=? WHERE id=1")->execute([$now]);
$textCalls=0;
$writer=function() use(&$textCalls,$body) { $textCalls++; return ['body'=>$body,'excerpt'=>'Praktische tips']; };
$failedImage=function() { throw new RuntimeException('Simulated image failure'); };
verify(!empty(blog_run_schedule($now,$writer,$failedImage)['error']),'Image failure is reported');
verify(blog_post($auto)['body']===$body && blog_post($auto)['status']==='queued','Text saved before image retry');
$imageMaker=function($title,$excerpt) {
    return blog_generate_image($title,$excerpt,function($request) {
        verify($request['size']==='1536x1024' && $request['output_format']==='jpeg','Image request format');
        return ['data'=>[['b64_json'=>base64_encode(file_get_contents(__DIR__.'/fixtures/ai-test.jpg'))]]];
    });
};
verify(blog_run_schedule($now+3600,$writer,$imageMaker)['published']===1,'Article with generated image published');
verify($textCalls===1,'Saved text reused without a second AI call');
$saved=blog_post($auto); verify(is_file(config()['data_dir'].'/blog-images/'.$saved['image']),'Generated image stored locally');
verify(blog_run_schedule($now+3600,$writer,$imageMaker)['published']===0,'No duplicate illustrated article');
$failed=false; try { blog_generate_image('Test','',fn()=>['data'=>[['b64_json'=>base64_encode('<svg></svg>')]]]); } catch(RuntimeException) { $failed=true; } verify($failed,'Invalid generated image rejected');
// An admin edit during generation wins and the generated attachment is discarded.
$race=blog_save(['title'=>'Concurrent edit','body'=>'Ready text','status'=>'queued'],[]);
$db->prepare('UPDATE blog_schedule SET next_run=? WHERE id=1')->execute([$now+7200]);
$result=blog_run_schedule($now+7200,$writer,function($title,$excerpt) use($race,$imageMaker) { $post=blog_post($race); blog_save(array_replace($post,['body'=>'Admin text','status'=>'draft']),[]); return $imageMaker($title,$excerpt); });
verify(!empty($result['error']) && blog_post($race)['body']==='Admin text' && blog_post($race)['status']==='draft','Concurrent user edit preserved');
$db->exec("UPDATE blog_posts SET status='draft' WHERE status='queued'");
$first=blog_save(['title'=>'Publish early','status'=>'queued'],[]);
$second=blog_save(['title'=>'Keep queued','status'=>'queued'],[]);
$nextSlot=$now+604800; $db->prepare('UPDATE blog_schedule SET enabled=0,next_run=? WHERE id=1')->execute([$nextSlot]);
verify(blog_run_schedule($now,$writer,$imageMaker,$first)['published']===1,'Manual button works before scheduled time while weekly is paused');
verify((int)$db->query('SELECT next_run FROM blog_schedule')->fetchColumn()===$nextSlot,'Early post keeps next weekly slot');
verify(!empty(blog_run_schedule($now,$writer,$imageMaker,$first)['error']),'Double submission rejected');
verify(blog_post($second)['status']==='queued','Double click does not publish next item');
echo "PASS: blog scheduling, DST, idempotence, pause, failure backoff and mocked AI responses.\n";
