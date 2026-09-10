<?php
declare(strict_types=1);
function write_config(array $values): void {
    $file = getenv('ZM_CONFIG_FILE') ?: dirname(__DIR__).'/config/config.local.php';
    $lock = fopen($file.'.lock','c');
    if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('De instellingen kunnen niet worden opgeslagen.');
    try {
        $current = is_file($file) ? require $file : [];
        $temp = tempnam(dirname($file),'config-');
        if ($temp === false || file_put_contents($temp, "<?php\nreturn ".var_export(array_replace($current,$values),true).";\n") === false) throw new RuntimeException('Instellingen opslaan mislukt.');
        chmod($temp,0600);
        if (!rename($temp,$file)) throw new RuntimeException('Instellingen opslaan mislukt.');
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
$error = null; $success = null;
if ($path === '/auth') redirect('/admin/login');
if ($path === '/admin/setup') {
    if (configured()) redirect('/admin/login');
    if (isset($_GET['token']) && is_string($_GET['token']) && isset(config()['setup_token_hash']) && hash_equals(config()['setup_token_hash'],hash('sha256',$_GET['token']))) {
        $_SESSION['setup_until'] = time()+900; redirect('/admin/setup');
    }
    if (($_SESSION['setup_until'] ?? 0) < time()) { http_response_code(403); $error = 'Open de eenmalige installatielink om je beheerderswachtwoord te kiezen.'; }
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $password = $_POST['password'] ?? ''; $confirmation = $_POST['confirmation'] ?? '';
        if (!is_string($password) || strlen($password) < 14 || strlen($password) > 200 || $password !== $confirmation) $error = 'Gebruik minimaal 14 tekens en vul twee keer hetzelfde wachtwoord in.';
        else {
            write_config(['admin_password_hash'=>password_hash($password,PASSWORD_DEFAULT),'setup_token_hash'=>'']);
            unset($_SESSION['setup_until']); session_regenerate_id(true); flash('success','Je wachtwoord is ingesteld. Je kunt nu inloggen.'); redirect('/admin/login');
        }
    }
} elseif ($path === '/admin/login') {
    if (is_admin()) redirect('/admin');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $password = $_POST['password'] ?? ''; $email = $_POST['email'] ?? '';
        $allowed = rate_limit('login:'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'),10,900) && rate_limit('login-global',100,900);
        if (!$allowed) { http_response_code(429); $error = 'Te veel pogingen. Wacht 15 minuten en probeer opnieuw.'; }
        elseif (configured() && is_string($email) && is_string($password) && strlen($password)<=200 && strtolower(trim($email)) === strtolower(config()['admin_email']) && password_verify($password,config()['admin_password_hash'])) {
            session_regenerate_id(true); $_SESSION['admin']=config()['admin_email']; $_SESSION['last_active']=time(); $_SESSION['auth_version']=hash('sha256',config()['admin_password_hash']); $_SESSION['csrf']=bin2hex(random_bytes(32)); redirect('/admin');
        } else $error = 'Inloggen is niet gelukt. Controleer je e-mailadres en wachtwoord.';
    }
} else {
    require_admin();
    if ($path === '/admin/blog' || str_starts_with($path,'/admin/blog/')) { require __DIR__.'/blog-admin.php'; handle_blog_admin($path); }
    if ($path === '/admin/newsletter/export') { require_once __DIR__.'/newsletter-admin.php'; newsletter_export(); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        if ($path === '/admin/logout') { $_SESSION=[]; session_destroy(); redirect('/admin/login'); }
        if ($path === '/admin/test-mail') {
            require_once __DIR__.'/mail.php';
            if (!rate_limit('admin-test-mail',5,3600)) { flash('error','Er zijn al meerdere testmails aangevraagd. Probeer later opnieuw.'); redirect('/admin/settings'); }
            try { $mail=site_mailer(); $mail->addAddress(CONTACT_RECIPIENT); $mail->Subject='Testmail — Zichtbaar Marketing'; $mail->Body='Dit is een testmail vanuit je eigen website. De SMTP-verbinding werkt. Controleer of deze e-mail in de juiste inbox is ontvangen.'; $mail->send(); flash('success','De mailserver heeft de testmail geaccepteerd. Controleer de inbox en spammap van '.CONTACT_RECIPIENT.'.'); }
            catch (Throwable $exception) { flash('error',smtp_configured()?mail_error_message($exception):'Er ontbreekt nog een SMTP-server, gebruikersnaam of mailboxwachtwoord. Vul deze eerst in en sla op.'); }
            redirect('/admin/settings');
        } elseif ($path === '/admin/settings') {
            try {
                $host = $_POST['smtp_host'] ?? ''; $port = filter_var($_POST['smtp_port'] ?? null,FILTER_VALIDATE_INT);
                $security = $_POST['smtp_security'] ?? ''; $username=$_POST['smtp_username'] ?? ''; $password=$_POST['smtp_password'] ?? ''; $from=$_POST['smtp_from'] ?? '';
                if (!is_string($host) || !preg_match('/^[a-zA-Z0-9.-]{1,253}$/',$host) || !in_array($port,[465,587],true) || !in_array($security,['tls','ssl'],true) || ($port === 465 && $security !== 'ssl') || ($port === 587 && $security !== 'tls') || !is_string($username) || $username === '' || strlen($username)>254 || !is_string($password) || strlen($password)>1000 || !is_string($from) || !filter_var($from,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Vul een geldige SMTP-server, gebruikersnaam en afzender in. Gebruik TLS met poort 587 of SSL met poort 465.');
                $base=$_POST['base_url'] ?? '';
                if (!is_string($base) || !filter_var($base,FILTER_VALIDATE_URL) || !in_array(parse_url($base,PHP_URL_SCHEME),['http','https'],true) || parse_url($base,PHP_URL_USER) || parse_url($base,PHP_URL_PASS) || parse_url($base,PHP_URL_QUERY) || parse_url($base,PHP_URL_FRAGMENT) || !in_array(parse_url($base,PHP_URL_PATH),[null,'','/'],true)) throw new InvalidArgumentException('Vul de basis-URL van de website in, bijvoorbeeld https://www.zichtbaar-marketing.nl.');
                if ($password === '' && config()['smtp_password'] === '') throw new InvalidArgumentException('Er is nog geen SMTP-wachtwoord opgeslagen. Vul het wachtwoord van de mailbox in.');
                $values=['base_url'=>rtrim($base,'/'),'smtp_host'=>$host,'smtp_port'=>$port,'smtp_security'=>$security,'smtp_username'=>$username,'smtp_from'=>$from];
                if ($password !== '') $values['smtp_password']=$password;
                write_config($values); flash('success','Mailinstellingen opgeslagen. Controleer de aflevering met een contactaanvraag.'); redirect('/admin/settings');
            } catch (InvalidArgumentException $exception) { $error=$exception->getMessage(); }
        } elseif ($path === '/admin/password') {
            $current=$_POST['current'] ?? ''; $password=$_POST['password'] ?? '';
            if (!is_string($current) || !password_verify($current,config()['admin_password_hash']) || !is_string($password) || strlen($password)<14 || strlen($password)>200 || $password !== ($_POST['confirmation'] ?? null)) $error='Controleer je huidige wachtwoord. Gebruik minimaal 14 tekens voor het nieuwe wachtwoord en bevestig het.';
            else { write_config(['admin_password_hash'=>password_hash($password,PASSWORD_DEFAULT)]); $_SESSION=[]; session_regenerate_id(true); flash('success','Je wachtwoord is gewijzigd. Log opnieuw in.'); redirect('/admin/login'); }
        } elseif ($path === '/admin/retry') {
            $id=$_POST['id'] ?? '';
            if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/',$id)) throw new InvalidArgumentException('Ongeldige aanvraag.');
            require_once __DIR__.'/mail.php';
            $sent=deliver_message($id); flash($sent?'success':'error',$sent?'De aanvraag is doorgestuurd.':'Verzending is niet bevestigd. Controleer de mailinstellingen en je inbox voordat je opnieuw probeert.'); redirect('/admin/messages');
        } elseif ($path === '/admin') {
            $id=$_POST['page'] ?? '';
            if (!is_string($id) || !isset(defaults()[$id])) throw new InvalidArgumentException('Onbekende pagina.');
            try {
                $version=filter_var($_POST['version'] ?? null,FILTER_VALIDATE_INT);
                if ($version === false || $version < 0) throw new InvalidArgumentException('Ongeldige paginaversie.');
                $draft=validated_fields($id,is_array($_POST['fields'] ?? null)?$_POST['fields']:[]);
                save_page($id,$version,$draft); flash('success','Je wijzigingen zijn opgeslagen en zichtbaar op de website.'); redirect('/admin?page='.rawurlencode($id));
            } catch (RuntimeException|InvalidArgumentException $exception) { $error=$exception->getMessage(); }
        } else { http_response_code(404); $error='Deze beheeractie bestaat niet.'; }
    }
}
$title = 'Website beheren — Zichtbaar Marketing'; $description='';
require dirname(__DIR__).'/templates/head.php';
$notice=$_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<main id="main" class="container admin-shell">
<div class="admin-toolbar"><a class="logo" href="/"><img src="/assets/logo.png" alt="Zichtbaar Marketing"></a><a class="text-link" href="/">Bekijk website ↗</a>
<?php if (is_admin()): ?><form method="post" action="/admin/logout"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button class="button secondary small">Uitloggen</button></form><?php endif ?></div>
<?php if ($notice): ?><p class="notice <?= e($notice[0]) ?>" role="status"><?= e($notice[1]) ?></p><?php endif ?>
<?php if ($error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif ?>
<?php if ($path === '/admin/login' || $path === '/admin/setup'): ?>
<section class="panel admin-panel login"><p class="eyebrow">Alleen voor de beheerder</p><h1><?= $path === '/admin/setup' ? 'Kies je wachtwoord' : 'Website beheren' ?></h1><p>Log in als <?= e(config()['admin_email']) ?>.</p>
<?php if ($path === '/admin/login' || ($_SESSION['setup_until'] ?? 0) >= time()): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
<?php if ($path === '/admin/login'): ?><label>E-mailadres<input type="email" name="email" required autocomplete="username" value="<?= e(config()['admin_email']) ?>"></label><?php endif ?>
<label>Wachtwoord<input type="password" name="password" required maxlength="200" <?= $path === '/admin/setup' ? 'minlength="14" autocomplete="new-password"' : 'autocomplete="current-password"' ?>></label>
<?php if ($path === '/admin/setup'): ?><label>Herhaal wachtwoord<input type="password" name="confirmation" required minlength="14" maxlength="200" autocomplete="new-password"></label><?php endif ?>
<button class="button full"><?= $path === '/admin/setup' ? 'Beheeraccount activeren' : 'Inloggen' ?></button></form>
<?php endif ?></section>
<?php else: ?>
<h1>Website beheren</h1><nav class="admin-tabs" aria-label="Beheer"><a href="/admin" <?= $path === '/admin' ? 'aria-current="page"' : '' ?>>Pagina’s</a><a href="/admin/messages" <?= $path === '/admin/messages' ? 'aria-current="page"' : '' ?>>Contactaanvragen</a><a href="/admin/newsletter">Nieuwsbrief</a><a href="/admin/blog">Blogs</a><a href="/admin/settings" <?= $path === '/admin/settings' ? 'aria-current="page"' : '' ?>>E-mailinstellingen</a><a href="/admin/password" <?= $path === '/admin/password' ? 'aria-current="page"' : '' ?>>Wachtwoord</a></nav>
<?php if ($path === '/admin/newsletter'): require_once __DIR__.'/newsletter-admin.php'; render_newsletter_admin(); ?>
<?php elseif ($path === '/admin/settings'): $c=config(); ?>
<section class="panel admin-panel"><h2>E-mail versturen</h2><p>Alle contactaanvragen worden doorgestuurd naar <strong><?= e(CONTACT_RECIPIENT) ?></strong>. Gebruik de SMTP-gegevens van je mailprovider. Je wachtwoord wordt nooit in dit formulier teruggetoond.</p><p class="notice <?= smtp_configured()?'success':'error' ?>"><?= smtp_configured()?'SMTP-gegevens zijn ingevuld. Aflevering moet nog met een echte aanvraag worden gecontroleerd.':'SMTP is nog niet volledig ingesteld. Controleer de server, gebruikersnaam en het mailboxwachtwoord. Contactaanvragen worden wel opgeslagen.' ?></p>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><div class="editor-fields"><label>Website-URL voor bevestigings- en afmeldlinks<input name="base_url" type="url" required value="<?= e($c['base_url']) ?>"></label><p class="fine">Gebruik op je eigen hosting de echte website-URL met HTTPS. Een localhost-link werkt alleen op deze computer.</p><label>SMTP-server<input name="smtp_host" required value="<?= e($c['smtp_host']) ?>" placeholder="Bijvoorbeeld smtp.jouwprovider.nl"></label><label>Beveiliging<select name="smtp_security"><option value="tls" <?= $c['smtp_security']==='tls'?'selected':'' ?>>STARTTLS (poort 587)</option><option value="ssl" <?= $c['smtp_security']==='ssl'?'selected':'' ?>>SSL/TLS (poort 465)</option></select></label><label>Poort<input name="smtp_port" type="number" required value="<?= e($c['smtp_port']) ?>"></label><label>Gebruikersnaam<input name="smtp_username" required autocomplete="off" value="<?= e($c['smtp_username']) ?>"></label><label>SMTP-wachtwoord<input name="smtp_password" type="password" autocomplete="new-password" <?= $c['smtp_password']===''?'required':'' ?> placeholder="<?= $c['smtp_password']===''?'Nog geen wachtwoord opgeslagen: vul je mailboxwachtwoord in':'Wachtwoord opgeslagen; laat leeg om het te behouden' ?>"></label><label>Afzenderadres<input name="smtp_from" type="email" required value="<?= e($c['smtp_from']) ?>"></label></div><button class="button">Mailinstellingen opslaan</button></form><form method="post" action="/admin/test-mail" class="actions"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button class="button secondary">Stuur een testmail naar mijn inbox</button></form><p class="fine">Sla gewijzigde instellingen eerst op. De test gebruikt de opgeslagen gegevens.</p></section>
<?php elseif ($path === '/admin/password'): ?>
<form method="post" class="panel admin-panel"><h2>Wachtwoord wijzigen</h2><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><div class="editor-fields"><label>Huidig wachtwoord<input name="current" type="password" required autocomplete="current-password"></label><label>Nieuw wachtwoord (minimaal 14 tekens)<input name="password" type="password" minlength="14" maxlength="200" required autocomplete="new-password"></label><label>Herhaal nieuw wachtwoord<input name="confirmation" type="password" minlength="14" maxlength="200" required autocomplete="new-password"></label></div><button class="button">Wachtwoord wijzigen</button></form>
<?php elseif ($path === '/admin/messages'):
$number=max(1,(int)($_GET['page'] ?? 1)); $offset=($number-1)*25;
$stmt=db()->prepare('SELECT * FROM messages ORDER BY created_at DESC LIMIT 25 OFFSET ?'); $stmt->bindValue(1,$offset,PDO::PARAM_INT); $stmt->execute(); $messages=$stmt->fetchAll();
$statusNames=['pending'=>'Opgeslagen, nog niet gemaild','sending'=>'Verzending gestart; ontvangst nog niet bevestigd','sent'=>'Overgedragen aan de mailserver','failed'=>'Verzending niet bevestigd'];
?>
<p>Hier staan de contactaanvragen van alle pagina’s. Controleer bij een onzekere verzending eerst je inbox; opnieuw versturen kan een dubbele e-mail opleveren.</p>
<?php if (!$messages): ?><div class="panel admin-panel"><p>Er zijn nog geen contactaanvragen op deze pagina.</p></div><?php endif ?>
<?php foreach($messages as $message): $data=json_decode($message['payload'],true,512,JSON_THROW_ON_ERROR); ?><article class="panel message"><span class="status"><?= e($statusNames[$message['status']] ?? $message['status']) ?></span><h2><?= e($data['naam']) ?></h2><dl><dt>Ontvangen</dt><dd><?= e(gmdate('d-m-Y H:i',$message['created_at'])) ?> UTC</dd><dt>Bedrijf</dt><dd><?= e($data['bedrijfsnaam']) ?></dd><dt>E-mail</dt><dd><a class="text-link" href="mailto:<?= e($data['email']) ?>"><?= e($data['email']) ?></a></dd><dt>Telefoon</dt><dd><?= e($data['telefoon']) ?></dd><dt>Pagina</dt><dd><?= e($data['source']) ?></dd></dl><p class="message-text"><?= e($data['bericht']) ?></p>
<?php if(in_array($message['status'],['pending','failed'],true)): ?><form action="/admin/retry" method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($message['id']) ?>"><button class="button secondary">Opnieuw doorsturen naar mijn e-mail</button></form><?php endif ?></article><?php endforeach ?>
<nav class="actions" aria-label="Aanvragen bladeren"><?php if($number>1): ?><a class="text-link" href="/admin/messages?page=<?= $number-1 ?>">Vorige</a><?php endif ?><?php if(count($messages)===25): ?><a class="text-link" href="/admin/messages?page=<?= $number+1 ?>">Volgende</a><?php endif ?></nav>
<?php elseif ($path === '/admin'):
$id=$id ?? (is_string($_GET['page'] ?? null)?$_GET['page']:'home'); if(!isset(defaults()[$id])) $id='home';
$entry=page($id); $content=$draft ?? $entry['content'];
?>
<nav class="admin-tabs" aria-label="Pagina kiezen"><?php foreach(defaults() as $key=>$value): ?><a href="/admin?page=<?= e($key) ?>" <?= $id===$key?'aria-current="page"':'' ?>><?= e($key==='home'?'Homepage':$value['title']) ?></a><?php endforeach ?></nav>
<form method="post" class="panel admin-panel"><h2><?= e($id==='home'?'Homepage aanpassen':$content['title'].' aanpassen') ?></h2><p>Pas de teksten aan en klik op opslaan. De wijzigingen zijn daarna direct zichtbaar voor bezoekers. Links en vormgeving blijven behouden.</p><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="page" value="<?= e($id) ?>"><input type="hidden" name="version" value="<?= e($version ?? $entry['version']) ?>"><div class="editor-fields">
<?php foreach(editable_fields($content) as $field=>$value): ?><label><?= e(field_label($field)) ?><textarea name="fields[<?= e(str_replace('.','__',$field)) ?>]" required maxlength="2500" rows="<?= strlen($value)>150?4:2 ?>"><?= e($value) ?></textarea></label><?php endforeach ?>
</div><div class="admin-actions"><button class="button">Wijzigingen opslaan</button><a class="text-link" href="<?= $id==='home'?'/':'/diensten/'.e($id) ?>" target="_blank" rel="noopener">Bekijk deze pagina ↗</a><span class="fine">Niet opgeslagen wijzigingen gaan verloren als je weggaat.</span></div></form>
<?php endif ?>
<?php endif ?></main></body></html>
