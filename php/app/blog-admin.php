<?php
declare(strict_types=1);
require_once __DIR__.'/blog.php';
function handle_blog_admin(string $path): never {
    require_admin(); blog_db(); $error=null; $draft=null;
    if(str_starts_with($path,'/admin/blog/linkedin')) { require __DIR__.'/linkedin-admin.php'; handle_linkedin_admin($path); }
    if($path==='/admin/blog/make-now') {
        if($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Allow: POST'); exit; }
        check_csrf(); $id=$_POST['next_id'] ?? '';
        if(!is_string($id) || !preg_match('/^[a-f0-9]{32}$/',$id)) { http_response_code(400); exit; }
        if(!rate_limit('blog-make-now',10,3600)) { flash('error','Maximaal tien aanvragen per uur. Probeer het later opnieuw.'); redirect('/admin/blog'); }
        set_time_limit(360); session_write_close();
        $result=blog_run_schedule(null,null,null,$id);
        session_start(); flash(!empty($result['error'])?'error':'success',$result['published']?'Het eerstvolgende artikel is gemaakt en gepubliceerd.':$result['message']); redirect('/admin/blog');
    }
    if($_SERVER['REQUEST_METHOD']==='POST') {
        check_csrf();
        try {
            if($path==='/admin/blog/edit') {
                $id=blog_save($_POST,$_FILES['image'] ?? []); flash('success','Artikel opgeslagen.'); redirect('/admin/blog/edit?id='.$id);
            } elseif($path==='/admin/blog/import') {
                $result=blog_import($_FILES['topics'] ?? []); flash('success',$result['added'].' onderwerpen toegevoegd; '.$result['skipped'].' bestaande onderwerpen overgeslagen.'); redirect('/admin/blog');
            } elseif($path==='/admin/blog/settings') {
                $weekday=filter_var($_POST['weekday'] ?? '',FILTER_VALIDATE_INT); $time=$_POST['time_of_day'] ?? ''; $mode=$_POST['mode'] ?? '';
                if(!$weekday || $weekday>7 || !is_string($time) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$time) || !in_array($mode,['ready','generate'],true)) throw new InvalidArgumentException('Kies een geldige dag, tijd en werkwijze.');
                $key=blog_text($_POST['api_key'] ?? '',1000,false); $model=blog_text($_POST['model'] ?? 'gpt-4.1-mini',100);
                if(preg_match('/\s/',$key) || !preg_match('/^[a-zA-Z0-9._-]+$/',$model)) throw new InvalidArgumentException('Controleer de API-sleutel en modelnaam.');
                $imageModel=blog_text($_POST['image_model'] ?? 'gpt-image-2.5-sunburst',100);
                if(!preg_match('/^[a-zA-Z0-9._-]+$/',$imageModel)) throw new InvalidArgumentException('Controleer de naam van het afbeeldingsmodel.');
                $values=['blog_ai_model'=>$model,'blog_ai_images'=>($_POST['ai_images'] ?? '')==='yes','blog_image_model'=>$imageModel]; if($key!=='') $values['blog_ai_key']=$key;
                $enabled=($_POST['enabled'] ?? '')==='yes'?1:0;
                if($enabled && $mode==='generate' && $key==='' && (config()['blog_ai_key'] ?? '')==='') throw new InvalidArgumentException('Vul eerst een API-sleutel in om automatisch schrijven aan te zetten.');
                write_config($values);
                blog_db()->prepare('UPDATE blog_schedule SET enabled=?,weekday=?,time_of_day=?,mode=?,next_run=?,last_message=? WHERE id=1')->execute([$enabled,$weekday,$time,$mode,blog_next_run($weekday,$time,time()),'Planning opgeslagen. Zorg dat de publicatietaak op je hosting draait.']);
                flash('success','Blogplanning opgeslagen (Nederlandse tijd).'); redirect('/admin/blog/settings');
            } else { http_response_code(404); throw new InvalidArgumentException('Deze beheeractie bestaat niet.'); }
        } catch(InvalidArgumentException|RuntimeException $exception) { $error=$exception->getMessage(); $draft=$_POST; }
    }
    $title='Blogs beheren — Zichtbaar Marketing'; $description=''; require dirname(__DIR__).'/templates/head.php';
    echo '<main id="main" class="container admin-shell"><div class="admin-toolbar"><a class="logo" href="/"><img src="/assets/logo.png" alt="Zichtbaar Marketing"></a><a class="text-link" href="/admin">Terug naar beheer</a><a class="text-link" href="/blog">Bekijk blog ↗</a></div><h1>Blogs beheren</h1><nav class="admin-tabs"><a href="/admin/blog">Alle artikelen</a><a href="/admin/blog/edit">Nieuw artikel</a><a href="/admin/blog/import">Onderwerpen uploaden</a><a href="/admin/blog/settings">Blogplanning</a></nav>';
    $notice=$_SESSION['flash'] ?? null; unset($_SESSION['flash']);
    if($notice) echo '<p class="notice '.e($notice[0]).'" role="status">'.e($notice[1]).'</p>';
    if($error) echo '<p class="notice error" role="alert">'.e($error).'</p>';
    echo '<p><a class="text-link" href="/admin/blog/linkedin">LinkedIn doorplaatsen instellen →</a></p>';
    if(in_array($path,['/admin/blog','/admin/blog/import'],true)) {
        $next=blog_db()->query("SELECT id,title FROM blog_posts WHERE status='queued' ORDER BY created_at,rowid LIMIT 1")->fetch();
        echo '<section class="panel admin-panel"><h2>Eerder publiceren</h2>';
        if($next) echo '<p>Eerstvolgend: <strong>'.e($next['title']).'</strong></p><p>Maakt de tekst en afbeelding en publiceert direct. Dit kan enkele minuten duren en gebruikt je API-tegoed. De volgende wekelijkse beurt blijft staan.</p><form method="post" action="/admin/blog/make-now"><input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="next_id" value="'.e($next['id']).'"><button class="button">Maak nu de eerstvolgende blog</button></form>';
        else echo '<p>De wachtrij is leeg. Upload eerst onderwerpen of zet een concept in de wekelijkse wachtrij.</p>';
        echo '</section>';
    }
    if($path==='/admin/blog/edit') {
        $id=is_string($_GET['id'] ?? null)?$_GET['id']:''; $post=$id!==''?blog_post($id):false;
        if($id!=='' && !$post) { http_response_code(404); echo '<p>Dit artikel bestaat niet.</p>'; }
        else {
            $post=$post ?: ['id'=>'','title'=>'','excerpt'=>'','body'=>'','image'=>'','image_alt'=>'','status'=>'draft','publish_at'=>null,'version'=>0];
            if($draft) foreach(['id','title','excerpt','body','image_alt','status','version'] as $field) if(isset($draft[$field]) && is_string($draft[$field])) $post[$field]=$draft[$field];
            $date=$post['publish_at']?(new DateTimeImmutable('@'.$post['publish_at']))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('Y-m-d\TH:i'):'';
            if(is_string($draft['publish_at'] ?? null)) $date=$draft['publish_at'];
            require dirname(__DIR__).'/templates/blog-editor.php';
        }
    } elseif($path==='/admin/blog/import') {
        echo '<section class="panel admin-panel"><h2>Onderwerpenlijst uploaden</h2><p>Gebruik een TXT-bestand met één onderwerp per regel, of CSV met de kolom <strong>onderwerp</strong>. Optioneel kun je <strong>tekst</strong> en <strong>samenvatting</strong> meesturen. Maximaal 100 onderwerpen, 500 KB. Bestaande onderwerpen worden overgeslagen.</p><p>De lijst komt in een wachtrij. Met automatisch schrijven maakt de website wekelijks één volledig artikel. Met AI-afbeeldingen ingeschakeld krijgt elk artikel ook een passende illustratie. Je kunt zelf een afbeelding uploaden; anders is de Zichtbaar Marketing-afbeelding beschikbaar als standaard.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="'.e(csrf()).'"><label>Onderwerpenbestand<input type="file" name="topics" accept=".txt,.csv" required></label><div class="actions"><button class="button">Onderwerpen toevoegen</button></div></form></section>';
    } elseif($path==='/admin/blog/settings') {
        $settings=blog_db()->query('SELECT * FROM blog_schedule WHERE id=1')->fetch(); require dirname(__DIR__).'/templates/blog-settings.php';
    } elseif($path==='/admin/blog') {
        $page=max(1,min(100000,(int)($_GET['page'] ?? 1))); $stmt=blog_db()->prepare('SELECT * FROM blog_posts ORDER BY created_at DESC,rowid DESC LIMIT 25 OFFSET ?'); $stmt->bindValue(1,($page-1)*25,PDO::PARAM_INT); $stmt->execute(); $posts=$stmt->fetchAll();
        $labels=['draft'=>'Concept','published'=>'Gepubliceerd','scheduled'=>'Gepland','queued'=>'Wekelijkse wachtrij'];
        if(!$posts) echo '<div class="panel admin-panel"><h2>Je eerste artikel begint hier.</h2><p>Schrijf zelf een blog of upload je onderwerpenlijst.</p><a class="button" href="/admin/blog/edit">Nieuw artikel maken</a></div>';
        foreach($posts as $post) echo '<article class="panel message"><span class="status">'.e($labels[$post['status']]).($post['status']==='queued' && $post['body']===''?' · Nog uit te werken':'').'</span><h2>'.e($post['title']).'</h2><p>'.e($post['excerpt']).'</p><div class="actions"><a class="button secondary" href="/admin/blog/edit?id='.e($post['id']).'">Bewerken</a>'.($post['status']==='published'?'<a class="text-link" href="/blog/'.e($post['slug']).'">Bekijk artikel ↗</a>':'<a class="text-link" href="/blog/voorbeeld/'.e($post['id']).'">Privévoorbeeld ↗</a>').'</div></article>';
        echo '<nav class="actions">'; if($page>1) echo '<a class="text-link" href="/admin/blog?page='.($page-1).'">Vorige</a>'; if(count($posts)===25) echo '<a class="text-link" href="/admin/blog?page='.($page+1).'">Volgende</a>'; echo '</nav>';
    } else { http_response_code(404); echo '<p>Pagina niet gevonden.</p>'; }
    echo '</main></body></html>'; exit;
}
