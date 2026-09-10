<?php
// Isolated fixtures only. Every network operation uses an injected mock transport.
$original=getenv('ZM_CONFIG_FILE'); if(!$original) throw new RuntimeException('Run through integration.cjs.');
$fixture=dirname($original).'/linkedin-config.php'; $values=require $original;
$values=array_replace($values,['base_url'=>'https://example.com','data_dir'=>dirname($original).'/linkedin-data','linkedin_enabled'=>true,'linkedin_enabled_since'=>time()-5,'linkedin_author'=>'urn:li:organization:12345','linkedin_token'=>'fixture-token-not-real','linkedin_expires'=>time()+86400]);
file_put_contents($fixture,'<?php return '.var_export($values,true).';'); putenv('ZM_CONFIG_FILE='.$fixture);
require dirname(__DIR__).'/app/bootstrap.php'; require dirname(__DIR__).'/app/linkedin.php';
function check_li(bool $ok,string $message):void { if(!$ok) throw new RuntimeException($message); }
$calls=[]; $postResponse=['status'=>201,'json'=>[],'remote_id'=>'urn:li:share:123456789'];
$transport=function($method,$url,$body) use(&$calls,&$postResponse) {
    $calls[]=[$method,$url,$body];
    if(str_contains($url,'initializeUpload')) return ['status'=>200,'json'=>['value'=>['image'=>'urn:li:image:fixture','uploadUrl'=>'https://www.linkedin.com/dms-uploads/fixture']]];
    if($method==='PUT') return ['status'=>201,'json'=>[]];
    if($method==='GET') return ['status'=>200,'json'=>['status'=>'AVAILABLE']];
    if($url==='https://api.linkedin.com/rest/posts') return $postResponse;
    throw new RuntimeException('Unexpected network operation');
};
$id=blog_save(['title'=>'LinkedIn preview','body'=>'A complete article','excerpt'=>'A useful preview','status'=>'published'],[]);
check_li(!empty(linkedin_run_jobs($transport)['sent']),'Article sent');
$payload=json_decode($calls[3][2],true);
check_li($payload['author']==='urn:li:organization:12345','Company target');
check_li($payload['content']['article']['thumbnail']==='urn:li:image:fixture','Uploaded image attached');
check_li(str_contains($payload['commentary'],'A useful preview') && str_starts_with($payload['content']['article']['source'],'https://example.com/blog/'),'Preview and article link');
linkedin_run_jobs($transport); check_li(count($calls)===4,'No duplicate send');
$second=blog_save(['title'=>'Uncertain response','body'=>'A complete article','status'=>'published'],[]);
$postResponse=['status'=>0,'json'=>[]]; linkedin_run_jobs($transport);
$db=linkedin_db(); $query=$db->prepare('SELECT status FROM linkedin_jobs WHERE post_id=?'); $query->execute([$second]); check_li($query->fetchColumn()==='uncertain','Unknown delivery held for review');
$count=count($calls); linkedin_run_jobs($transport); check_li(count($calls)===$count,'Uncertain post not retried');
$third=blog_save(['title'=>'Unpublished','body'=>'A complete article','status'=>'published'],[]); linkedin_enqueue(); $post=blog_post($third); blog_save(array_replace($post,['status'=>'draft']),[]); linkedin_run_jobs($transport);
check_li(count($calls)===$count,'Draft never shared');
$failed=false; try { linkedin_http('PUT','https://attacker.example/steal','private image',true,'image/jpeg',$transport); } catch(RuntimeException) { $failed=true; } check_li($failed,'Untrusted upload host blocked');
$fourth=blog_save(['title'=>'Revoked during upload','body'=>'A complete article','status'=>'published'],[]);
$revoke=function($method,$url,$body) use($transport,$fixture,$values) { if($method==='GET') file_put_contents($fixture,'<?php return '.var_export(array_replace($values,['linkedin_enabled'=>false]),true).';'); return $transport($method,$url,$body); };
$before=count(array_filter($calls,fn($call)=>$call[1]==='https://api.linkedin.com/rest/posts'));
linkedin_run_jobs($revoke);
check_li(count(array_filter($calls,fn($call)=>$call[1]==='https://api.linkedin.com/rest/posts'))===$before,'Disconnect prevents final send');
echo "PASS: LinkedIn image upload, article preview, company author, duplicate protection, uncertain delivery, private drafts and disconnect. No LinkedIn requests sent.\n";
