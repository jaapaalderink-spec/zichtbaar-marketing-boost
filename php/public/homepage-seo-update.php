<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require_admin();
$changes = [
    'badge'=>'Online marketingbureau in Groningen',
    'titleBefore'=>'SEO, Google Ads en',
    'titleAccent'=>'websites',
    'titleAfter'=>'voor ZZP en MKB',
    'intro'=>'Zichtbaar Marketing helpt ZZP’ers en MKB-bedrijven groeien met betere vindbaarheid, gerichte Google Ads en professionele websites. Vanuit Groningen werk ik aan meer relevante bezoekers en aanvragen voor jouw bedrijf.',
    'seoTitle'=>'Online marketingbureau Groningen | Zichtbaar Marketing',
    'seoDescription'=>'Zichtbaar Marketing helpt ZZP en MKB met SEO, Google Ads en professionele websites. Vanuit Groningen werken we aan meer bezoekers en aanvragen.',
];
$db=db();
$db->exec('CREATE TABLE IF NOT EXISTS seo_home_updates (id TEXT PRIMARY KEY, backup TEXT NOT NULL, applied_version INTEGER NOT NULL, restored INTEGER NOT NULL DEFAULT 0)');
$id='homepage-seo-20261009';
$message=''; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $query=$db->prepare('SELECT * FROM seo_home_updates WHERE id=?'); $query->execute([$id]); $record=$query->fetch();
        $current=page('home');
        if (($_POST['action'] ?? '')==='apply') {
            if ($record) throw new RuntimeException('Deze update is al uitgevoerd. Eventuele verdere aanpassingen kun je via het gewone beheer doen.');
            if ((string)$current['version'] !== ($_POST['version'] ?? '')) throw new RuntimeException('De homepage is ondertussen aangepast. Vernieuw deze pagina en controleer de voorgestelde tekst opnieuw.');
            $content=array_replace($current['content'],$changes);
            $version=$current['version']+1;
            $query=$db->prepare('INSERT INTO seo_home_updates(id,backup,applied_version) VALUES(?,?,?)');
            $query->execute([$id,json_encode($current['content'],JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),$version]);
            $query=$db->prepare('INSERT INTO pages(id,content,version,updated_at) VALUES(?,?,?,?) ON CONFLICT(id) DO UPDATE SET content=excluded.content,version=excluded.version,updated_at=excluded.updated_at');
            $query->execute(['home',json_encode($content,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),$version,time()]);
            $message='De homepage is bijgewerkt. De vorige teksten zijn opgeslagen als reservekopie.';
        } elseif (($_POST['action'] ?? '')==='restore') {
            if (!$record || $record['restored']) throw new RuntimeException('Er is geen actieve update om terug te draaien.');
            if ($current['version'] !== (int)$record['applied_version']) throw new RuntimeException('De homepage is na deze update gewijzigd. Automatisch herstellen is daarom geblokkeerd om je nieuwe wijzigingen te behouden.');
            $query=$db->prepare('UPDATE pages SET content=?,version=version+1,updated_at=? WHERE id=?');
            $query->execute([$record['backup'],time(),'home']);
            $query=$db->prepare('UPDATE seo_home_updates SET restored=1 WHERE id=?'); $query->execute([$id]);
            $message='De vorige homepage-teksten zijn hersteld.';
        } else throw new RuntimeException('Onbekende actie.');
        $db->exec('COMMIT');
    } catch (Throwable $exception) {
        $db->exec('ROLLBACK');
        $error=$exception instanceof RuntimeException ? $exception->getMessage() : 'De update kon niet worden opgeslagen.';
    }
}
$query=$db->prepare('SELECT * FROM seo_home_updates WHERE id=?'); $query->execute([$id]); $record=$query->fetch();
$current=page('home');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Homepage SEO-update — Zichtbaar Marketing</title><link rel="stylesheet" href="/assets/site.css"></head><body><main class="container section"><h1>Homepage SEO-update</h1>
<p>Deze update vervangt alleen de bovenkop, hoofdkop, introductie, SEO-title en meta description. De overige homepage-inhoud blijft behouden.</p>
<?php if ($message): ?><p role="status"><?= e($message) ?></p><?php endif ?>
<?php if ($error): ?><p role="alert"><?= e($error) ?></p><?php endif ?>
<?php if (!$record): ?><section class="panel card"><h2>Nieuwe teksten</h2><dl><?php foreach ($changes as $name=>$value): ?><dt><strong><?= e($name) ?></strong></dt><dd><?= e($value) ?></dd><?php endforeach ?></dl>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="version" value="<?= $current['version'] ?>"><input type="hidden" name="action" value="apply"><button class="button" type="submit">Homepage bijwerken</button></form></section>
<?php elseif (!$record['restored']): ?><p>Deze update is toegepast. Bekijk je homepage om de nieuwe tekst te controleren.</p><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="restore"><button class="button secondary" type="submit">Vorige teksten herstellen</button></form>
<?php else: ?><p>De update is teruggedraaid. Je kunt teksten verder wijzigen via het gewone beheer.</p><?php endif ?>
<p><a href="/">Bekijk homepage</a> · <a href="/admin">Websitebeheer</a></p><p>Verwijder na controle het bestand public/homepage-seo-update.php van je hosting.</p></main></body></html>
