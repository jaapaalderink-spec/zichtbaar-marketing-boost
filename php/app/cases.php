<?php
declare(strict_types=1);
function cases_public(string $path): never {
    $cases = json_decode(<<<'JSON'
{"gl-link": {"name": "GL Link", "title": "GL Link: social media en eventpromotie", "intro": "GL Link wilde meer exposure op social media en meer aandacht voor evenementen. Zichtbaar Marketing ondersteunde dit met LinkedIn-advertenties, socialmediaposts en blogs op de website.", "goal": "Meer zichtbaarheid op social media en evenementen actief onder de aandacht brengen.", "work": ["LinkedIn-advertenties maken voor de promotie.", "Socialmediaposts maken die de bekendheid van GL Link ondersteunen.", "Blogs schrijven voor de website."], "delivery": "Advertenties, posts en blogcontent als middelen om GL Link en de evenementen onder de aandacht te brengen.", "service": "sea-ai-advertising", "service_name": "Bekijk onze advertentie-aanpak"}, "kruiden-van-haar": {"name": "Kruiden van Haar", "title": "Kruiden van Haar: een webshop met handleiding en training", "intro": "Kruiden van Haar wilde een webshop starten. Zichtbaar Marketing bouwde de complete webshop en zorgde daarnaast voor een handleiding en training over het gebruik.", "goal": "Een webshop opzetten en leren hoe die werkt, zodat de opdrachtgever ermee aan de slag kon.", "work": ["Een complete webshop bouwen.", "Een handleiding maken over de werking van de webshop.", "Een training geven over het gebruik."], "delivery": "Een complete webshop, een bijbehorende handleiding en een gegeven training. De opdracht omvatte daarmee zowel het bouwen als het overdragen van kennis aan de opdrachtgever.", "service": "webshops", "service_name": "Bekijk onze webshop-aanpak"}, "ruijter-bouw": {"name": "Ruijter Bouw", "title": "Ruijter Bouw: een website en advertenties voor online zichtbaarheid", "intro": "Ruijter Bouw wilde een website om online beter vindbaar te zijn. Zichtbaar Marketing zette de website op en leidde met advertenties verkeer naar de website.", "goal": "Een website opzetten voor online vindbaarheid en bezoekers naar die website brengen.", "work": ["Een website opzetten voor Ruijter Bouw.", "Advertenties inzetten om verkeer naar de website te leiden."], "delivery": "Een website met een advertentieaanpak om bezoekers naar die website te brengen. De werkzaamheden verbonden het bouwen van de website met het aantrekken van verkeer.", "service": "ai-ready-websites", "service_name": "Bekijk onze website-aanpak"}}
JSON, true, 512, JSON_THROW_ON_ERROR);
    $slug = $path === '/cases' ? null : substr($path, 7);
    $case = $slug !== null ? ($cases[$slug] ?? null) : null;
    $notFound = $slug !== null && !$case;
    if ($notFound) http_response_code(404);
    $title = $notFound ? 'Case niet gevonden' : ($case ? $case['title'].' | Zichtbaar Marketing' : 'Cases: websites, webshops en marketing | Zichtbaar Marketing');
    $description = $case['intro'] ?? 'Bekijk werkzaamheden van Zichtbaar Marketing voor GL Link, Kruiden van Haar en Ruijter Bouw: advertenties, content, websites en webshops.';
    require dirname(__DIR__).'/templates/head.php';
    require dirname(__DIR__).'/templates/header.php';
    echo '<main id="main" class="container section">';
    if ($notFound) echo '<h1>Case niet gevonden</h1><a href="/cases">Bekijk alle cases</a>';
    elseif (!$case) {
        echo '<p class="eyebrow">Werk uit de praktijk</p><h1>Cases van Zichtbaar Marketing</h1><p class="lead">Van social media en eventpromotie tot een complete webshop: hieronder zie je welke vragen onze klanten hadden en wat we voor hen hebben uitgevoerd.</p><div class="grid three">';
        foreach ($cases as $id=>$item) echo '<article class="panel card"><h2>'.e($item['name']).'</h2><p>'.e($item['intro']).'</p><a class="text-link" href="/cases/'.e($id).'">Bekijk de case</a></article>';
        echo '</div>';
    } else {
        echo '<nav class="breadcrumb" aria-label="Broodkruimel"><a href="/">Home</a><span>/</span><a href="/cases">Cases</a><span>/</span><span aria-current="page">'.e($case['name']).'</span></nav><article><p class="eyebrow">Klantcase</p><h1>'.e($case['title']).'</h1><p class="lead">'.e($case['intro']).'</p><section class="section"><h2>De vraag van '.e($case['name']).'</h2><p>'.e($case['goal']).'</p></section><section class="section"><h2>Wat we hebben gedaan</h2><ul class="bullets">';
        foreach ($case['work'] as $work) echo '<li>'.e($work).'</li>';
        echo '</ul></section><section class="section"><h2>Wat is opgeleverd</h2><p>'.e($case['delivery']).'</p></section><p><a class="text-link" href="/diensten/'.e($case['service']).'">'.e($case['service_name']).'</a></p></article>';
    }
    if (!$notFound) echo '<section class="section panel card"><h2>Een vergelijkbare vraag voor jouw bedrijf?</h2><p>Vertel wat je wilt bereiken. We bespreken welke werkzaamheden en expertise daarbij passen.</p><a class="button" href="/afspraak-plannen">Plan een kennismaking</a></section>';
    echo '</main>';
    require dirname(__DIR__).'/templates/footer.php';
    exit;
}
