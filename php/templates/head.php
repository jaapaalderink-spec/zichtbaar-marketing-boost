<!doctype html>
<html lang="nl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description ?? '') ?>">
<?php if (str_starts_with($path, '/admin')): ?><meta name="robots" content="noindex,nofollow"><?php else: ?>
<link rel="canonical" href="<?= e(rtrim(config()['base_url'],'/').$path) ?>">
<meta property="og:title" content="<?= e($title) ?>"><meta property="og:description" content="<?= e($description ?? '') ?>">
<meta property="og:type" content="website"><meta property="og:url" content="<?= e(rtrim(config()['base_url'],'/').$path) ?>">
<?php endif ?>
<link rel="icon" href="/assets/favicon.png"><link rel="stylesheet" href="/assets/site.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head><body><a class="skip" href="#main">Naar de inhoud</a>
