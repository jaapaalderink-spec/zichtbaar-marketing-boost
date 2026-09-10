<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/content.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
try {
    if (in_array($path,['/nieuwsbrief/aanmelden','/nieuwsbrief/bevestigen','/nieuwsbrief/afmelden'],true)) {
        require dirname(__DIR__).'/app/newsletter.php';
        if($path==='/nieuwsbrief/aanmelden' && $_SERVER['REQUEST_METHOD']==='POST') subscribe_newsletter();
        if($path!=='/nieuwsbrief/aanmelden' && in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)) newsletter_link_page($path);
        http_response_code(405); exit;
    }
    if (str_starts_with($path,'/admin') || $path === '/auth') {
        require dirname(__DIR__).'/app/admin.php'; exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === '/contact') {
        require dirname(__DIR__).'/app/contact.php'; handle_contact();
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
    if ($path === '/blog' || str_starts_with($path,'/blog/')) { require dirname(__DIR__).'/app/blog-public.php'; blog_public($path); }
    $serviceId = str_starts_with($path,'/diensten/') ? substr($path,10) : null;
    $notFound = $path !== '/' && (!$serviceId || $serviceId === 'home' || !isset(defaults()[$serviceId]));
    if ($notFound) http_response_code(404);
    $home = page('home')['content'];
    $services = [];
    foreach (array_keys(defaults()) as $id) if ($id !== 'home') $services[] = page($id)['content'];
    $service = !$notFound && $serviceId ? page($serviceId)['content'] : null;
    $title = $notFound ? 'Pagina niet gevonden' : ($service ? $service['title'].' — Zichtbaar Marketing' : $home['seoTitle']);
    $description = $service['intro'] ?? $home['seoDescription'];
    require dirname(__DIR__).'/templates/head.php';
    require dirname(__DIR__).'/templates/header.php';
    if ($notFound) echo '<main class="container section"><h1>Pagina niet gevonden</h1><p>Deze pagina bestaat niet.</p><a class="button" href="/">Naar de homepage</a></main>';
    else require dirname(__DIR__).'/templates/'.($service ? 'service' : 'home').'.php';
    require dirname(__DIR__).'/templates/footer.php';
} catch (Throwable $error) {
    error_log('Website request failed: '.get_class($error));
    if (http_response_code() < 400) http_response_code(503);
    echo '<!doctype html><html lang="nl"><meta charset="utf-8"><title>Tijdelijk niet beschikbaar</title><p>Dit verzoek kon niet worden verwerkt. Vernieuw de pagina of neem contact op via <a href="mailto:info@zichtbaar-marketing.nl">info@zichtbaar-marketing.nl</a>.</p></html>';
}
