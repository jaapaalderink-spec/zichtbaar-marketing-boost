<?php
declare(strict_types=1);

function seo_schema(string $path, string $title, string $description, ?array $post, string $canonicalPath): array {
    $base = rtrim(config()['base_url'], '/');
    $url = $base.$canonicalPath;
    $organization = ['@type'=>'Organization', '@id'=>$base.'/#organization', 'name'=>'Zichtbaar Marketing', 'url'=>$base.'/', 'logo'=>$base.'/assets/logo.png', 'email'=>'info@zichtbaar-marketing.nl', 'telephone'=>'+31857605135'];
    $website = ['@type'=>'WebSite', '@id'=>$base.'/#website', 'url'=>$base.'/', 'name'=>'Zichtbaar Marketing', 'inLanguage'=>'nl-NL', 'publisher'=>['@id'=>$organization['@id']]];
    $page = ['@type'=>$post ? 'BlogPosting' : 'WebPage', '@id'=>$url.'#page', 'url'=>$url, 'name'=>$title, 'description'=>$description, 'inLanguage'=>'nl-NL', 'isPartOf'=>['@id'=>$website['@id']]];
    if ($post) {
        $page += ['headline'=>$post['title'], 'image'=>$base.blog_image_url($post), 'datePublished'=>gmdate('c',(int)$post['published_at']), 'dateModified'=>gmdate('c',(int)$post['updated_at']), 'publisher'=>['@id'=>$organization['@id']], 'mainEntityOfPage'=>$url];
    }
    $graph = [$organization, $website, $page];
    if ($path !== '/') {
        $items = [['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>$base.'/']];
        if ($post) $items[] = ['@type'=>'ListItem','position'=>2,'name'=>'Blog','item'=>$base.'/blog'];
        $items[] = ['@type'=>'ListItem','position'=>count($items)+1,'name'=>$post['title'] ?? $title,'item'=>$url];
        $graph[] = ['@type'=>'BreadcrumbList','@id'=>$url.'#breadcrumbs','itemListElement'=>$items];
        $graph[2]['breadcrumb'] = ['@id'=>$url.'#breadcrumbs'];
    }
    return ['@context'=>'https://schema.org','@graph'=>$graph];
}

function seo_endpoint(string $path): never {
    $base = rtrim(config()['base_url'], '/');
    if ($path === '/robots.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /auth\nDisallow: /blog/voorbeeld/\n\nSitemap: ".$base."/sitemap.xml\n";
        exit;
    }
    require_once __DIR__.'/blog.php';
    $entries = [['url'=>$base.'/'], ['url'=>$base.'/blog'], ['url'=>$base.'/website-laten-maken']];
    foreach (defaults() as $id=>$content) {
        $url = $id === 'home' ? $base.'/' : $base.'/diensten/'.$id;
        $query = db()->prepare('SELECT updated_at FROM pages WHERE id=?');
        $query->execute([$id]);
        $modified = $query->fetchColumn();
        if ($id === 'home') { if ($modified) $entries[0]['modified'] = (int)$modified; }
        else $entries[] = ['url'=>$url, 'modified'=>$modified ? (int)$modified : null];
    }
    $query = blog_db()->prepare("SELECT slug,updated_at FROM blog_posts WHERE status='published' AND published_at<=? ORDER BY slug");
    $query->execute([time()]);
    while ($post = $query->fetch()) $entries[] = ['url'=>$base.'/blog/'.$post['slug'], 'modified'=>(int)$post['updated_at']];
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($entries as $entry) {
        echo '<url><loc>'.htmlspecialchars($entry['url'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';
        if (!empty($entry['modified'])) echo '<lastmod>'.gmdate('c', $entry['modified']).'</lastmod>';
        echo '</url>';
    }
    echo '</urlset>';
    exit;
}
