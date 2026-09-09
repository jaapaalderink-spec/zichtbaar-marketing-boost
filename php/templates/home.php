<main id="main">
<section class="container hero"><div class="panel hero-panel"><div class="spotlight" aria-hidden="true"></div><div class="hero-content">
<p class="badge"><?= e($home['badge']) ?></p>
<h1><?= e($home['titleBefore']) ?> <span class="accent underline"><?= e($home['titleAccent']) ?></span> <?= e($home['titleAfter']) ?></h1>
<p class="lead"><?= e($home['intro']) ?></p>
<div class="actions"><a class="button" href="#contact"><?= e($home['cta']) ?></a><a class="button secondary" href="#werkwijze">Zo werken wij</a></div>
<div class="stats"><div><strong>3</strong><span>stappen, geen zandkastelen</span></div><div><strong>100%</strong><span>Nederlands, recht naar het doel</span></div><div><strong>AI-ready</strong><span>vanaf de eerste dag</span></div></div>
</div></div></section>
<section id="diensten" class="container section"><p class="eyebrow">Wat we doen</p><h2 class="section-title"><?= e($home['servicesTitle']) ?></h2>
<div class="grid four"><?php foreach ($services as $s): ?><article class="panel card"><span class="number"><?= e($s['num']) ?></span><h3><?= e($s['title']) ?></h3><p><?= e($s['label']) ?></p><ul class="bullets"><?php foreach ($s['benefits'] as $benefit): ?><li><?= e($benefit[0]) ?></li><?php endforeach ?></ul><a class="text-link" href="/diensten/<?= e($s['slug']) ?>">Bekijk de dienst <span aria-hidden="true">↗</span></a></article><?php endforeach ?></div>
</section>
<section id="werkwijze" class="container section"><div class="dark approach"><div><p class="eyebrow">Werkwijze</p><h2><?= e($home['approachTitle']) ?></h2><p><?= e($home['approachIntro']) ?></p></div><ol class="steps"><?php foreach ($home['steps'] as $i=>$step): ?><li><span><?= $i+1 ?></span><div><h3><?= e($step['title']) ?></h3><p><?= e($step['text']) ?></p></div></li><?php endforeach ?></ol></div></section>
<?php require __DIR__.'/contact.php' ?>
</main>
