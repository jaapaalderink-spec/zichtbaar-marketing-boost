<!doctype html>
<html lang="nl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description ?? '') ?>">
<?php if (str_starts_with($path, '/admin') || $path === '/auth' || ($noindex ?? false) || ($notFound ?? false)): ?><meta name="robots" content="noindex,nofollow"><?php else: ?>
<link rel="canonical" href="<?= e(rtrim(config()['base_url'],'/').($canonicalPath ?? $path)) ?>">
<meta property="og:title" content="<?= e($title) ?>"><meta property="og:description" content="<?= e($description ?? '') ?>">
<meta property="og:type" content="<?= !empty($post) ? 'article' : 'website' ?>"><meta property="og:url" content="<?= e(rtrim(config()['base_url'],'/').($canonicalPath ?? $path)) ?>">
<meta property="og:site_name" content="Zichtbaar Marketing"><meta property="og:locale" content="nl_NL">
<?php if (!empty($post)): ?>
<meta property="og:image" content="<?= e(rtrim(config()['base_url'],'/').blog_image_url($post)) ?>">
<meta property="og:image:alt" content="<?= e($post['image_alt']) ?>">
<meta property="article:published_time" content="<?= e(gmdate('c', (int)$post['published_at'])) ?>">
<meta property="article:modified_time" content="<?= e(gmdate('c', (int)$post['updated_at'])) ?>">
<?php endif ?>
<?php require_once dirname(__DIR__).'/app/seo.php'; ?>
<script type="application/ld+json"><?= json_encode(seo_schema($path, $title, $description ?? '', $post ?? null, $canonicalPath ?? $path), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<?php endif ?>
<link rel="icon" href="/assets/favicon.png"><link rel="stylesheet" href="/assets/site.css">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head><body><a class="skip" href="#main">Naar de inhoud</a>
