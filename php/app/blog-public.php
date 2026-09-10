<?php
declare(strict_types=1);
require_once __DIR__.'/blog.php';
function blog_public(string $path): never {
    if(str_starts_with($path,'/blog/media/')) { blog_serve_image(substr($path,12)); exit; }
    $db=blog_db(); $post=null; $notFound=false; $noindex=false;
    if(str_starts_with($path,'/blog/voorbeeld/')) {
        $noindex=true;
        if(is_admin()) $post=blog_post(substr($path,16));
        $notFound=!$post;
    } elseif($path!=='/blog') {
        $query=$db->prepare("SELECT * FROM blog_posts WHERE slug=? AND status='published' AND published_at<=?");
        $query->execute([substr($path,6),time()]); $post=$query->fetch(); $notFound=!$post;
    }
    if($notFound) http_response_code(404);
    $title=$notFound?'Blog niet gevonden':($post?$post['title'].' — Zichtbaar Marketing':'Blog — Zichtbaar Marketing');
    $description=$post['excerpt'] ?? 'Praktische inzichten over marketing, websites en de groei van jouw bedrijf.';
    require dirname(__DIR__).'/templates/head.php'; require dirname(__DIR__).'/templates/header.php';
    echo '<main id="main" class="container section blog-shell">';
    if($notFound) echo '<h1>Blog niet gevonden</h1><p>Dit artikel is niet beschikbaar.</p><a class="button" href="/blog">Bekijk alle blogs</a>';
    elseif($post) {
        if($noindex) echo '<p class="notice">Privévoorbeeld — alleen zichtbaar voor de beheerder.</p>';
        echo '<article class="blog-article"><a class="text-link" href="/blog">← Alle blogs</a><p class="eyebrow">Inzichten & inspiratie</p><h1>'.e($post['title']).'</h1>';
        if($post['published_at']) echo '<p class="blog-date">'.e((new DateTimeImmutable('@'.$post['published_at']))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('d-m-Y')).'</p>';
        echo '<p class="lead">'.e($post['excerpt']).'</p><img class="blog-cover" src="'.e(blog_image_url($post)).'" alt="'.e($post['image_alt']).'"><div class="blog-prose">'.blog_body($post['body']).'</div></article>';
    } else {
        $page=max(1,min(100000,(int)($_GET['pagina'] ?? 1))); $offset=($page-1)*9;
        $query=$db->prepare("SELECT * FROM blog_posts WHERE status='published' AND published_at<=? ORDER BY published_at DESC,id LIMIT 10 OFFSET ?");
        $query->bindValue(1,time(),PDO::PARAM_INT); $query->bindValue(2,$offset,PDO::PARAM_INT); $query->execute(); $posts=$query->fetchAll();
        echo '<p class="eyebrow">Inzichten & inspiratie</p><h1>Maak jouw bedrijf<br><span class="accent">zichtbaar.</span></h1><p class="lead">Ideeën en praktische tips voor marketing die jouw bedrijf verder helpt.</p><div class="blog-grid">';
        foreach(array_slice($posts,0,9) as $item) echo '<article class="panel blog-card"><a href="/blog/'.e($item['slug']).'"><img loading="lazy" src="'.e(blog_image_url($item)).'" alt="'.e($item['image_alt']).'"><div class="blog-card-body"><h2>'.e($item['title']).'</h2><p>'.e($item['excerpt']).'</p><span class="text-link">Lees het artikel →</span></div></a></article>';
        echo '</div>';
        if(!$posts) echo '<div class="panel card"><h2>Binnenkort meer inspiratie</h2><p>Hier delen we binnenkort onze inzichten. Schrijf je hieronder in voor de nieuwsbrief.</p></div>';
        echo '<nav class="blog-pagination" aria-label="Blogpagina’s">';
        if($page>1) echo '<a class="button secondary" href="/blog?pagina='.($page-1).'">Vorige</a>';
        if(count($posts)>9) echo '<a class="button" href="/blog?pagina='.($page+1).'">Volgende</a>';
        echo '</nav>';
    }
    if(!$notFound) echo '<section class="panel card blog-cta"><p class="eyebrow">Van inzicht naar actie</p><h2>Klaar om meer uit jouw marketing te halen?</h2><p>We denken graag met je mee over de volgende stap voor jouw bedrijf.</p><a class="button" href="/#contact">Plan een kennismaking →</a></section>';
    echo '</main>'; require dirname(__DIR__).'/templates/footer.php'; exit;
}
