<?php
declare(strict_types=1);
function blog_db(): PDO {
    static $ready=false; $db=db(); if($ready) return $db;
    $db->exec("CREATE TABLE IF NOT EXISTS blog_posts (
      id TEXT PRIMARY KEY,title TEXT NOT NULL,slug TEXT NOT NULL UNIQUE,excerpt TEXT NOT NULL DEFAULT '',body TEXT NOT NULL DEFAULT '',
      image TEXT NOT NULL DEFAULT '',image_alt TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'draft',
      publish_at INTEGER,published_at INTEGER,created_at INTEGER NOT NULL,updated_at INTEGER NOT NULL,version INTEGER NOT NULL DEFAULT 1,
      import_key TEXT UNIQUE,last_error TEXT NOT NULL DEFAULT ''
    ); CREATE TABLE IF NOT EXISTS blog_schedule (
      id INTEGER PRIMARY KEY CHECK(id=1),enabled INTEGER NOT NULL DEFAULT 0,weekday INTEGER NOT NULL DEFAULT 2,
      time_of_day TEXT NOT NULL DEFAULT '10:00',next_run INTEGER,mode TEXT NOT NULL DEFAULT 'generate',last_run INTEGER,last_message TEXT NOT NULL DEFAULT ''
    ); INSERT OR IGNORE INTO blog_schedule(id) VALUES(1);
    CREATE INDEX IF NOT EXISTS blog_public_idx ON blog_posts(status,published_at);");
    $ready=true; return $db;
}
function blog_text(mixed $value,int $max,bool $required=true): string {
    if(!is_string($value) || !preg_match('//u',$value) || strlen($value)>$max || ($required && trim($value)==='')) throw new InvalidArgumentException('Controleer de ingevulde tekstvelden en hun lengte.');
    return trim(str_replace(["\r\n","\r"],"\n",$value));
}
function blog_slug(string $title): string {
    $text=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$title);
    $slug=trim(preg_replace('/[^a-z0-9]+/','-',strtolower($text===false?$title:$text)),'-');
    return substr($slug ?: 'artikel',0,100).'-'.bin2hex(random_bytes(3));
}
function blog_post(string $id): array|false {
    $stmt=blog_db()->prepare('SELECT * FROM blog_posts WHERE id=?'); $stmt->execute([$id]); return $stmt->fetch();
}
function blog_image(array $file): string {
    if(($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return '';
    if(($file['error'] ?? -1)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0)>8*1024*1024) throw new InvalidArgumentException('Upload een JPG, PNG of WebP van maximaal 8 MB.');
    $info=@getimagesize($file['tmp_name']);
    $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!$info || !isset($extensions[$info['mime']]) || $info[0]*$info[1]>25000000) throw new InvalidArgumentException('Deze afbeelding wordt niet ondersteund. Gebruik JPG, PNG of WebP, maximaal 25 megapixels.');
    $dir=config()['data_dir'].'/blog-images'; if(!is_dir($dir)) mkdir($dir,0700,true);
    $name=bin2hex(random_bytes(16)).'.'.$extensions[$info['mime']];
    if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('Afbeelding opslaan mislukt.');
    chmod($dir.'/'.$name,0600); return $name;
}
function blog_image_url(array $post): string { return $post['image']!==''?'/blog/media/'.$post['image']:'/assets/blog-default.svg'; }
function blog_serve_image(string $name): never {
    if(!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/',$name)) { http_response_code(404); exit; }
    $stmt=blog_db()->prepare("SELECT id FROM blog_posts WHERE image=? AND (status='published' AND published_at<=?) LIMIT 1"); $stmt->execute([$name,time()]);
    if(!$stmt->fetchColumn() && !is_admin()) { http_response_code(404); exit; }
    $file=config()['data_dir'].'/blog-images/'.$name;
    if(!is_file($file)) { http_response_code(404); exit; }
    $types=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
    header('Content-Type: '.$types[pathinfo($name,PATHINFO_EXTENSION)]); header('Content-Length: '.filesize($file));
    readfile($file); exit;
}
function blog_save(array $input,array $file): string {
    $id=blog_text($input['id'] ?? '',32,false); $old=$id!==''?blog_post($id):false;
    if($id!=='' && !$old) throw new InvalidArgumentException('Dit artikel bestaat niet.');
    $title=blog_text($input['title'] ?? '',250); $excerpt=blog_text($input['excerpt'] ?? '',600,false);
    $body=blog_text($input['body'] ?? '',100000,false); $alt=blog_text($input['image_alt'] ?? '',250,false);
    $status=$input['status'] ?? 'draft'; if(!in_array($status,['draft','published','scheduled','queued'],true)) throw new InvalidArgumentException('Ongeldige publicatiestatus.');
    if(in_array($status,['published','scheduled'],true) && $body==='') throw new InvalidArgumentException('Vul eerst de blogtekst in voordat je publiceert of een publicatiedatum kiest.');
    $publishAt=null;
    if($status==='scheduled') {
        $date=$input['publish_at'] ?? ''; if(!is_string($date)) throw new InvalidArgumentException('Kies een publicatiedatum.');
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date,new DateTimeZone('Europe/Amsterdam'));
        if(!$parsed || $parsed->format('Y-m-d\TH:i')!==$date || $parsed->getTimestamp()<=time()) throw new InvalidArgumentException('Kies een geldige toekomstige datum en tijd (Nederlandse tijd).');
        $publishAt=$parsed->getTimestamp();
    }
    $version=filter_var($input['version'] ?? 0,FILTER_VALIDATE_INT);
    if($version===false || $version<0) throw new InvalidArgumentException('Ongeldige artikelversie.');
    $newImage=blog_image($file); $image=$newImage ?: ($old['image'] ?? '');
    if(($input['remove_image'] ?? '')==='yes' && $newImage==='') $image='';
    $id=$id ?: bin2hex(random_bytes(16)); $now=time();
    $db=blog_db(); $db->exec('BEGIN IMMEDIATE');
    try {
        $current=blog_post($id);
        if(($current?(int)$current['version']:0)!==$version) throw new RuntimeException('Dit artikel is ondertussen gewijzigd. Bewaar je tekst en laad het artikel opnieuw.');
        $stmt=$db->prepare("INSERT INTO blog_posts(id,title,slug,excerpt,body,image,image_alt,status,publish_at,published_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT(id) DO UPDATE SET title=excluded.title,excerpt=excluded.excerpt,body=excluded.body,image=excluded.image,image_alt=excluded.image_alt,status=excluded.status,publish_at=excluded.publish_at,published_at=excluded.published_at,updated_at=excluded.updated_at,version=blog_posts.version+1,last_error=''");
        $stmt->execute([$id,$title,$old['slug'] ?? blog_slug($title),$excerpt,$body,$image,$alt,$status,$publishAt,$status==='published'?($old['published_at'] ?? $now):null,$old['created_at'] ?? $now,$now]);
        $db->exec('COMMIT');
    } catch(Throwable $error) {
        $db->exec('ROLLBACK');
        if($newImage!=='') @unlink(config()['data_dir'].'/blog-images/'.$newImage);
        throw $error;
    }
    return $id;
}
function blog_import(array $file): array {
    if(($file['error'] ?? -1)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0)>512000) throw new InvalidArgumentException('Upload een TXT- of CSV-bestand van maximaal 500 KB.');
    $extension=strtolower(pathinfo($file['name'] ?? '',PATHINFO_EXTENSION));
    if(!in_array($extension,['txt','csv'],true)) throw new InvalidArgumentException('Gebruik een .txt- of .csv-bestand.');
    $text=preg_replace('/^\xEF\xBB\xBF/','',file_get_contents($file['tmp_name']));
    if(!preg_match('//u',$text)) throw new InvalidArgumentException('Sla het bestand op met UTF-8-tekstcodering.');
    $rows=[];
    if($extension==='txt') {
        foreach(preg_split('/\R/u',$text) as $line) if(trim($line)!=='') $rows[]=['onderwerp'=>trim($line),'tekst'=>'','samenvatting'=>''];
    } else {
        $stream=fopen('php://temp','r+'); fwrite($stream,$text); rewind($stream);
        $line=strtok($text,"\r\n") ?: ''; $delimiter=substr_count($line,';')>substr_count($line,',')?';':',';
        $header=fgetcsv($stream,0,$delimiter,'"','');
        if(!$header) throw new InvalidArgumentException('Het bestand bevat geen kopregel.');
        $header=array_map(fn($v)=>strtolower(trim((string)$v)),$header);
        if(!in_array('onderwerp',$header,true) || count($header)!==count(array_unique($header)) || array_diff($header,['onderwerp','tekst','samenvatting'])) throw new InvalidArgumentException('Gebruik de CSV-kolommen onderwerp en eventueel tekst en samenvatting.');
        while(($row=fgetcsv($stream,0,$delimiter,'"',''))!==false) {
            if($row===[null]) continue;
            if(count($row)!==count($header)) throw new InvalidArgumentException('Een CSV-regel heeft niet het juiste aantal kolommen.');
            $rows[]=array_combine($header,$row);
        }
        fclose($stream);
    }
    if(count($rows)<1 || count($rows)>100) throw new InvalidArgumentException('Upload tussen 1 en 100 onderwerpen per bestand.');
    $valid=[];
    foreach($rows as $row) $valid[]=[blog_text($row['onderwerp'] ?? '',250),blog_text($row['tekst'] ?? '',100000,false),blog_text($row['samenvatting'] ?? '',600,false)];
    $db=blog_db(); $db->exec('BEGIN IMMEDIATE'); $added=0;
    try {
        $stmt=$db->prepare("INSERT OR IGNORE INTO blog_posts(id,title,slug,body,excerpt,status,created_at,updated_at,import_key) VALUES(?,?,?,?,?,'queued',?,?,?)");
        foreach($valid as [$title,$body,$excerpt]) {
            $stmt->execute([bin2hex(random_bytes(16)),$title,blog_slug($title),$body,$excerpt,time(),time(),hash('sha256',strtolower(trim($title)))]); $added+=$stmt->rowCount();
        }
        $db->exec('COMMIT');
    } catch(Throwable $error) { $db->exec('ROLLBACK'); throw $error; }
    return ['added'=>$added,'skipped'=>count($valid)-$added];
}
function blog_next_run(int $weekday,string $time,int $after): int {
    $zone=new DateTimeZone('Europe/Amsterdam'); $date=(new DateTimeImmutable('@'.$after))->setTimezone($zone);
    [$hour,$minute]=array_map('intval',explode(':',$time)); $next=$date->setTime($hour,$minute);
    $days=($weekday-(int)$date->format('N')+7)%7; $next=$next->modify('+'.$days.' days');
    if($next->getTimestamp()<=$after) $next=$next->modify('+1 week');
    return $next->getTimestamp();
}
function blog_checkpoint(array $post,array $values): array {
    $columns=[]; $params=[];
    foreach($values as $key=>$value) {
        if(!in_array($key,['body','excerpt','image','image_alt'],true)) throw new InvalidArgumentException('Ongeldig artikelveld.');
        $columns[]=$key.'=?'; $params[]=$value;
    }
    $params[]=time(); $params[]=$post['id']; $params[]=$post['version'];
    $stmt=blog_db()->prepare("UPDATE blog_posts SET ".implode(',',$columns).",updated_at=?,version=version+1 WHERE id=? AND status='queued' AND version=?");
    $stmt->execute($params);
    if($stmt->rowCount()!==1) throw new RuntimeException('Het artikel is ondertussen gewijzigd. Er is niets gepubliceerd.');
    return array_replace($post,$values,['version'=>(int)$post['version']+1]);
}
function blog_run_schedule(?int $now=null,?callable $textGenerator=null,?callable $imageGenerator=null,?string $manualId=null): array {
    $now ??= time(); $db=blog_db(); $lock=fopen(config()['data_dir'].'/blog-cron.lock','c');
    if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) return ['published'=>0,'message'=>'Een andere publicatietaak draait al.'];
    $transaction=false;
    try {
        $count=0;
        if($manualId===null) { $stmt=$db->prepare("UPDATE blog_posts SET status='published',published_at=publish_at,updated_at=?,version=version+1 WHERE status='scheduled' AND publish_at<=? AND body<>''"); $stmt->execute([$now,$now]); $count=$stmt->rowCount(); }
        $settings=$db->query('SELECT * FROM blog_schedule WHERE id=1')->fetch(); $message='Publicatieplanning gecontroleerd.';
        if($manualId!==null || ($settings['enabled'] && $settings['next_run'] && (int)$settings['next_run']<=$now)) {
            $post=$db->query("SELECT * FROM blog_posts WHERE status='queued' ORDER BY created_at,rowid LIMIT 1")->fetch();
            if($manualId!==null && (!$post || !hash_equals($post['id'],$manualId))) throw new RuntimeException('De wachtrij is al verwerkt of gewijzigd. Vernieuw het overzicht voordat je nog een artikel maakt.');
            if($manualId!==null) $settings['mode']='generate';
            if(!$post) $message='Geen artikelen in de wachtrij.';
            else {
                if(trim($post['body'])==='') {
                    if($settings['mode']!=='generate') throw new RuntimeException('Het eerste onderwerp heeft nog geen tekst. Schrijf het artikel of stel automatisch schrijven in.');
                    require_once __DIR__.'/blog-ai.php'; $generated=$textGenerator?$textGenerator($post['title']):blog_generate($post['title']);
                    $post=blog_checkpoint($post,['body'=>$generated['body'],'excerpt'=>$generated['excerpt']]);
                }
                if($settings['mode']==='generate' && (config()['blog_ai_images'] ?? true) && $post['image']==='') {
                    require_once __DIR__.'/blog-ai.php';
                    $image=$imageGenerator?$imageGenerator($post['title'],$post['excerpt']):blog_generate_image($post['title'],$post['excerpt']);
                    try { $post=blog_checkpoint($post,['image'=>$image,'image_alt'=>'Illustratie bij '.$post['title']]); }
                    catch(Throwable $error) { @unlink(config()['data_dir'].'/blog-images/'.$image); throw $error; }
                }
                $db->exec('BEGIN IMMEDIATE'); $transaction=true;
                $current=$db->query('SELECT * FROM blog_schedule WHERE id=1')->fetch();
                if($manualId===null && (!$current['enabled'] || $current['next_run']!==$settings['next_run'] || $current['mode']!==$settings['mode'])) throw new RuntimeException('De planning is ondertussen gewijzigd. Het artikel is niet gepubliceerd.');
                $update=$db->prepare("UPDATE blog_posts SET status='published',body=?,excerpt=?,published_at=?,updated_at=?,version=version+1,last_error='' WHERE id=? AND status='queued' AND version=?");
                $update->execute([$post['body'],$post['excerpt'],$now,$now,$post['id'],$post['version']]);
                if($update->rowCount()!==1) throw new RuntimeException('Het artikel is ondertussen gewijzigd. De volgende controle probeert opnieuw.');
                $message='Eén artikel uit de wekelijkse wachtrij gepubliceerd.';
            }
            if($manualId===null || ($settings['next_run'] && (int)$settings['next_run']<=$now)) $db->prepare('UPDATE blog_schedule SET next_run=? WHERE id=1')->execute([blog_next_run((int)$settings['weekday'],$settings['time_of_day'],$now)]);
            if($transaction) { $db->exec('COMMIT'); $transaction=false; $count++; }
        }
        $db->prepare('UPDATE blog_schedule SET last_run=?,last_message=? WHERE id=1')->execute([$now,$message]);
        return ['published'=>$count,'message'=>$message];
    } catch(Throwable $error) {
        if($transaction) $db->exec('ROLLBACK');
        $message=$error instanceof RuntimeException?$error->getMessage():'Publiceren is mislukt. Controleer de hostinginstellingen.';
        $db->prepare('UPDATE blog_schedule SET last_run=?,last_message=? WHERE id=1')->execute([$now,$message]);
        // Retry a failed slot at most once per hour, not on every cron tick.
        if($manualId===null) {
            if($error instanceof BlogActionRequired) $db->exec('UPDATE blog_schedule SET enabled=0 WHERE id=1');
            else $db->prepare('UPDATE blog_schedule SET next_run=? WHERE id=1 AND next_run<=?')->execute([$now+3600,$now]);
        }
        return ['published'=>$count ?? 0,'message'=>$message,'error'=>true];
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
function blog_body(string $text): string {
    $out='';
    foreach(preg_split('/\n\s*\n/',trim($text)) as $block) {
        if(preg_match('/^## (.+)$/u',$block,$match)) $out.='<h2>'.e($match[1]).'</h2>';
        elseif(preg_match('/^### (.+)$/u',$block,$match)) $out.='<h3>'.e($match[1]).'</h3>';
        else $out.='<p>'.nl2br(e($block)).'</p>';
    }
    return $out;
}
