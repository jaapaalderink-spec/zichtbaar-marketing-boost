<?php
declare(strict_types=1);
require_once __DIR__.'/blog.php';
function linkedin_db(): PDO {
    $db=blog_db();
    $db->exec("CREATE TABLE IF NOT EXISTS linkedin_jobs(post_id TEXT PRIMARY KEY,author TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',image_urn TEXT NOT NULL DEFAULT '',remote_id TEXT NOT NULL DEFAULT '',message TEXT NOT NULL DEFAULT '',created_at INTEGER NOT NULL,updated_at INTEGER NOT NULL)");
    return $db;
}
function linkedin_site_url(): string {
    $base=rtrim(config()['base_url'],'/'); $parts=parse_url($base); $host=strtolower($parts['host'] ?? '');
    if(($parts['scheme'] ?? '')!=='https' || !$host || $host==='localhost' || !str_contains($host,'.') || filter_var($host,FILTER_VALIDATE_IP) || preg_match('/\.(?:localhost|local|test|invalid)$/',$host) || !empty($parts['user']) || !empty($parts['pass']) || !empty($parts['query']) || !empty($parts['fragment']) || !empty($parts['path'])) throw new RuntimeException('Stel eerst je openbare HTTPS-domein in bij E-mailinstellingen. Een lokale website kan niet naar LinkedIn worden doorgeplaatst.');
    return $base;
}
function linkedin_http(string $method,string $url,string $body='',bool $auth=true,string $type='application/json',?callable $transport=null): array {
    $host=strtolower(parse_url($url,PHP_URL_HOST) ?: '');
    if(parse_url($url,PHP_URL_SCHEME)!=='https' || (parse_url($url,PHP_URL_PORT) ?? 443)!==443 || parse_url($url,PHP_URL_USER) || parse_url($url,PHP_URL_PASS) || !($host==='linkedin.com' || str_ends_with($host,'.linkedin.com') || str_ends_with($host,'.licdn.com'))) throw new RuntimeException('LinkedIn gaf een ongeldige uploadlocatie.');
    if($transport) return $transport($method,$url,$body);
    $headers='Content-Type: '.$type."\r\n";
    if($auth && ($host==='linkedin.com' || str_ends_with($host,'.linkedin.com'))) $headers.='Authorization: Bearer '.config()['linkedin_token']."\r\n";
    if($host==='api.linkedin.com') $headers.='Linkedin-Version: '.config()['linkedin_version']."\r\nX-Restli-Protocol-Version: 2.0.0\r\n";
    $context=stream_context_create(['http'=>['method'=>$method,'header'=>$headers,'content'=>$body,'timeout'=>45,'ignore_errors'=>true,'follow_location'=>0],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $raw=@file_get_contents($url,false,$context,0,2*1024*1024); $headers=$http_response_header ?? []; $status=0; $remote='';
    foreach($headers as $header) { if(preg_match('~^HTTP/\S+ (\d+)~',$header,$m)) $status=(int)$m[1]; if(stripos($header,'x-restli-id:')===0) $remote=trim(substr($header,12)); }
    return ['status'=>$status,'json'=>$raw!==false?(json_decode($raw,true) ?? []):[],'remote_id'=>$remote];
}
function linkedin_enqueue(): void {
    $c=config(); if(empty($c['linkedin_enabled'])) return;
    $db=linkedin_db();
    $db->prepare("INSERT OR IGNORE INTO linkedin_jobs(post_id,author,created_at,updated_at) SELECT id,?,?,? FROM blog_posts WHERE status='published' AND published_at>=? AND published_at<=?")->execute([$c['linkedin_author'],time(),time(),$c['linkedin_enabled_since'],time()]);
}
function linkedin_payload(array $post,string $author,string $imageUrn): array {
    $url=linkedin_site_url().'/blog/'.$post['slug'];
    return ['author'=>$author,'commentary'=>$post['title']."\n\n".$post['excerpt']."\n\nLees het volledige artikel: ".$url,'visibility'=>'PUBLIC','distribution'=>['feedDistribution'=>'MAIN_FEED','targetEntities'=>[],'thirdPartyDistributionChannels'=>[]],'content'=>['article'=>['source'=>$url,'thumbnail'=>$imageUrn,'title'=>$post['title'],'description'=>$post['excerpt']]],'lifecycleState'=>'PUBLISHED','isReshareDisabledByAuthor'=>false];
}
function linkedin_run_jobs(?callable $transport=null): array {
    $db=linkedin_db(); $c=config(); if(empty($c['linkedin_enabled'])) return ['message'=>'LinkedIn doorplaatsen staat uit.'];
    $lock=fopen($c['data_dir'].'/linkedin.lock','c');
    if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) return ['message'=>'LinkedIn-taak draait al.'];
    $job=null; $sending=false;
    try {
        linkedin_site_url(); linkedin_enqueue();
        // A process may have stopped after LinkedIn accepted a post. Never blindly send it again.
        $db->exec("UPDATE linkedin_jobs SET status='uncertain',message='Verzending onderbroken. Controleer LinkedIn voordat je opnieuw probeert.' WHERE status='sending'");
        if(empty($c['linkedin_token']) || (int)($c['linkedin_expires'] ?? 0)<=time()+60) throw new RuntimeException('LinkedIn is niet verbonden of de verbinding is verlopen. Verbind opnieuw vanuit het beheer.');
        $job=$db->query("SELECT * FROM linkedin_jobs WHERE status='pending' ORDER BY created_at,rowid LIMIT 1")->fetch();
        if(!$job) return ['message'=>'Geen LinkedIn-berichten klaar voor verzending.'];
        $post=blog_post($job['post_id']);
        if(!$post || $post['status']!=='published' || $post['published_at']>time()) { $db->prepare("UPDATE linkedin_jobs SET status='cancelled',message='Artikel is niet meer openbaar.' WHERE post_id=?")->execute([$job['post_id']]); return ['message'=>'Niet-openbaar artikel overgeslagen.']; }
        if($job['author']!==$c['linkedin_author']) throw new RuntimeException('De ingestelde bedrijfspagina is gewijzigd. Controleer dit bericht voordat je het opnieuw inplant.');
        if($job['image_urn']==='') {
            $file=$post['image']!==''?$c['data_dir'].'/blog-images/'.$post['image']:dirname(__DIR__).'/public/assets/logo.png';
            $info=@getimagesize($file); if(!$info || !in_array($info['mime'],['image/jpeg','image/png'],true)) throw new RuntimeException('Gebruik voor LinkedIn een JPG- of PNG-afbeelding bij dit artikel.');
            $result=linkedin_http('POST','https://api.linkedin.com/rest/images?action=initializeUpload',json_encode(['initializeUploadRequest'=>['owner'=>$job['author']]],JSON_THROW_ON_ERROR),true,'application/json',$transport);
            $value=$result['json']['value'] ?? [];
            if($result['status']!==200 || !preg_match('/^urn:li:image:[a-zA-Z0-9_-]+$/',$value['image'] ?? '') || !is_string($value['uploadUrl'] ?? null)) throw new RuntimeException('LinkedIn kon de afbeelding niet voorbereiden. Controleer de verbinding en rechten van je bedrijfspagina.');
            $upload=linkedin_http('PUT',$value['uploadUrl'],file_get_contents($file),true,$info['mime'],$transport);
            if(!in_array($upload['status'],[200,201],true)) throw new RuntimeException('De afbeelding kon niet naar LinkedIn worden geüpload.');
            $job['image_urn']=$value['image'];
            $db->prepare('UPDATE linkedin_jobs SET image_urn=?,updated_at=? WHERE post_id=?')->execute([$job['image_urn'],time(),$job['post_id']]);
        }
        $image=linkedin_http('GET','https://api.linkedin.com/rest/images/'.rawurlencode($job['image_urn']),'',true,'application/json',$transport);
        if($image['status']!==200) throw new RuntimeException('De status van de LinkedIn-afbeelding kon niet worden gecontroleerd. Controleer de API-rechten.');
        $imageStatus=$image['json']['status'] ?? '';
        if(in_array($imageStatus,['WAITING_UPLOAD','PROCESSING'],true)) { $db->prepare("UPDATE linkedin_jobs SET message='LinkedIn verwerkt de afbeelding; de volgende taak gaat verder.' WHERE post_id=?")->execute([$job['post_id']]); return ['message'=>'LinkedIn verwerkt de afbeelding.']; }
        if($imageStatus!=='AVAILABLE') throw new RuntimeException('LinkedIn heeft de afbeelding niet geaccepteerd. Controleer of vervang de afbeelding.');
        $latest=blog_post($post['id']);
        if(!$latest || $latest['version']!==$post['version'] || $latest['status']!=='published') throw new RuntimeException('Het artikel is tijdens de voorbereiding gewijzigd. Controleer de preview en plan het bericht opnieuw in.');
        $configPath=getenv('ZM_CONFIG_FILE') ?: dirname(__DIR__).'/config/config.local.php';
        $fresh=is_file($configPath)?require $configPath:[];
        if(empty($fresh['linkedin_enabled']) || ($fresh['linkedin_author'] ?? '')!==$job['author'] || ($fresh['linkedin_token'] ?? '')!==$c['linkedin_token']) throw new RuntimeException('De LinkedIn-instellingen zijn tijdens de voorbereiding gewijzigd. Er is niets verzonden.');
        $payload=linkedin_payload($post,$job['author'],$job['image_urn']);
        $claim=$db->prepare("UPDATE linkedin_jobs SET status='sending',updated_at=? WHERE post_id=? AND status='pending'"); $claim->execute([time(),$job['post_id']]);
        if($claim->rowCount()!==1) return ['message'=>'Dit bericht is ondertussen afgehandeld.'];
        $sending=true;
        $result=linkedin_http('POST','https://api.linkedin.com/rest/posts',json_encode($payload,JSON_THROW_ON_ERROR),true,'application/json',$transport);
        if($result['status']===201 && preg_match('/^urn:li:(?:share|ugcPost):\d+$/',$result['remote_id'] ?? '')) {
            $db->prepare("UPDATE linkedin_jobs SET status='sent',remote_id=?,message='Geplaatst op LinkedIn.',updated_at=? WHERE post_id=?")->execute([$result['remote_id'],time(),$job['post_id']]);
            return ['message'=>'Artikel doorgeplaatst naar LinkedIn.','sent'=>true];
        }
        if($result['status']>=400 && $result['status']<500) $sending=false;
        throw new RuntimeException($sending?'LinkedIn bevestigde de verzending niet. Controleer eerst de bedrijfspagina om een dubbel bericht te voorkomen.':'LinkedIn heeft het bericht geweigerd. Controleer je verbinding, API-rechten en artikel.');
    } catch(Throwable $error) {
        $message=$error instanceof RuntimeException && !($error instanceof PDOException)?$error->getMessage():'Doorplaatsen naar LinkedIn is mislukt.';
        if($job) $db->prepare("UPDATE linkedin_jobs SET status=?,message=?,updated_at=? WHERE post_id=? AND status NOT IN ('sent','cancelled')")->execute([$sending?'uncertain':'failed',$message,time(),$job['post_id']]);
        return ['message'=>$message,'error'=>true];
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
