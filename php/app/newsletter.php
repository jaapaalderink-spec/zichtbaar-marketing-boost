<?php
declare(strict_types=1);
const NEWSLETTER_CONSENT = 'Ik ontvang graag per e-mail de nieuwsbrief van Zichtbaar Marketing met tips over SEO, advertenties en websites. Ik kan mij op ieder moment afmelden.';
function newsletter_db(): PDO {
    static $ready=false;
    $db=db();
    if($ready) return $db;
    $db->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id TEXT PRIMARY KEY,email TEXT NOT NULL UNIQUE,status TEXT NOT NULL DEFAULT 'pending',
        token_hash TEXT,token_expires INTEGER,consent_text TEXT NOT NULL,
        requested_at INTEGER NOT NULL,confirmed_at INTEGER,unsubscribed_at INTEGER,mail_status TEXT NOT NULL DEFAULT 'pending'
    ); CREATE TABLE IF NOT EXISTS newsletter_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,subscriber_id TEXT NOT NULL,event TEXT NOT NULL,occurred_at INTEGER NOT NULL,consent_text TEXT
    );");
    // Add fields to existing installations without changing subscriptions or consent.
    $db->exec('BEGIN IMMEDIATE');
    try {
        $columns=array_column($db->query('PRAGMA table_info(newsletter_subscribers)')->fetchAll(),'name');
        foreach(['first_name','last_name','company'] as $column) {
            if(!in_array($column,$columns,true)) $db->exec("ALTER TABLE newsletter_subscribers ADD COLUMN $column TEXT NOT NULL DEFAULT ''");
        }
        $db->exec('COMMIT'); $ready=true;
    } catch(Throwable $error) { $db->exec('ROLLBACK'); throw $error; }
    return $db;
}
function newsletter_event(string $id,string $event): void {
    newsletter_db()->prepare('INSERT INTO newsletter_events(subscriber_id,event,occurred_at,consent_text) VALUES(?,?,?,?)')->execute([$id,$event,time(),NEWSLETTER_CONSENT]);
}
function unsubscribe_token(string $id): string { return hash_hmac('sha256','unsubscribe:'.$id,config()['app_key']); }
function newsletter_notice(string $type,string $text): void { $_SESSION['newsletter_flash']=[$type,$text]; }
function subscribe_newsletter(): never {
    check_csrf();
    try {
        $email=$_POST['email'] ?? '';
        if (!is_string($email) || strlen($email)>254 || !filter_var(trim($email),FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$email) || ($_POST['consent'] ?? '') !== 'yes' || !empty($_POST['website'])) throw new InvalidArgumentException('Vul een geldig e-mailadres in en geef toestemming voor de nieuwsbrief.');
        $details=[];
        foreach(['first_name'=>100,'last_name'=>100,'company'=>150] as $field=>$limit) {
            $value=$_POST[$field] ?? '';
            if(!is_string($value) || !preg_match('//u',$value) || preg_match('/[\x00-\x1F\x7F]/',$value) || preg_match_all('/./us',$value)>$limit || ($field!=='company' && trim($value)==='')) throw new InvalidArgumentException('Vul je voornaam en achternaam in (maximaal 100 tekens). De bedrijfsnaam is optioneel (maximaal 150 tekens).');
            $details[$field]=trim($value);
        }
        if (!configured()) throw new InvalidArgumentException('Inschrijven is tijdelijk niet beschikbaar. Probeer het later opnieuw.');
        $email=strtolower(trim($email));
        if (!rate_limit('newsletter-ip:'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'),5,3600) || !rate_limit('newsletter-email:'.$email,3,3600) || !rate_limit('newsletter-global',50,3600)) throw new InvalidArgumentException('Er zijn te veel aanvragen gedaan. Probeer het later opnieuw.');
        if (!smtp_configured()) throw new InvalidArgumentException('De bevestigingsmail kan momenteel niet worden verstuurd. Je bent nog niet ingeschreven; probeer het later opnieuw.');
        $db=newsletter_db(); $token=bin2hex(random_bytes(32));
        $db->exec('BEGIN IMMEDIATE');
        try {
            $stmt=$db->prepare('SELECT * FROM newsletter_subscribers WHERE email=?'); $stmt->execute([$email]); $existing=$stmt->fetch(); $stmt->closeCursor();
            if ($existing && $existing['status']==='confirmed') { $db->exec('COMMIT'); newsletter_notice('success','Als bevestiging nodig is, ontvang je een e-mail met de volgende stap.'); redirect('/#nieuwsbrief'); }
            $id=$existing['id'] ?? bin2hex(random_bytes(16));
            $db->prepare("INSERT INTO newsletter_subscribers(id,email,token_hash,token_expires,consent_text,requested_at,first_name,last_name,company) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(email) DO UPDATE SET status='pending',token_hash=excluded.token_hash,token_expires=excluded.token_expires,consent_text=excluded.consent_text,requested_at=excluded.requested_at,first_name=excluded.first_name,last_name=excluded.last_name,company=excluded.company,confirmed_at=NULL,unsubscribed_at=NULL,mail_status='pending'")->execute([$id,$email,hash('sha256',$token),time()+172800,NEWSLETTER_CONSENT,time(),$details['first_name'],$details['last_name'],$details['company']]);
            newsletter_event($id,'requested'); $db->exec('COMMIT');
        } catch(Throwable $error) { if($db->inTransaction()) $db->exec('ROLLBACK'); throw $error; }
        require_once __DIR__.'/mail.php';
        try {
            $mail=site_mailer(); $mail->addAddress($email); $mail->Subject='Bevestig je nieuwsbriefinschrijving — Zichtbaar Marketing';
            $base=rtrim(config()['base_url'],'/');
            $mail->Body="Je hebt je aangemeld voor de nieuwsbrief van Zichtbaar Marketing.\n\nBevestig je inschrijving binnen 48 uur via:\n".$base.'/nieuwsbrief/bevestigen?token='.$token."\n\n".NEWSLETTER_CONSENT."\n\nHeb je dit niet aangevraagd? Negeer deze e-mail; je ontvangt geen nieuwsbrief zonder bevestiging.\nAfmelden: ".$base.'/nieuwsbrief/afmelden?id='.$id.'&token='.unsubscribe_token($id);
            $mail->send();
            $db->prepare("UPDATE newsletter_subscribers SET mail_status='sent' WHERE id=? AND token_hash=?")->execute([$id,hash('sha256',$token)]);
            newsletter_notice('success','Controleer je inbox en eventueel je spammap. Bevestig je inschrijving via de link in de e-mail.');
        } catch(Throwable $error) {
            $db->prepare("UPDATE newsletter_subscribers SET mail_status='failed' WHERE id=? AND token_hash=?")->execute([$id,hash('sha256',$token)]);
            newsletter_notice('error','De bevestigingsmail kon niet worden verstuurd. Je bent nog niet ingeschreven. Probeer het later opnieuw.');
        }
    } catch(InvalidArgumentException $error) { newsletter_notice('error',$error->getMessage()); }
    redirect('/#nieuwsbrief');
}
function newsletter_link_page(string $path): never {
    header('Referrer-Policy: no-referrer');
    $db=newsletter_db(); $token=$_GET['token'] ?? ''; $row=false; $unsubscribe=$path==='/nieuwsbrief/afmelden';
    if(is_string($token) && preg_match('/^[a-f0-9]{64}$/',$token)) {
        if($unsubscribe) {
            $id=$_GET['id'] ?? '';
            if(is_string($id) && preg_match('/^[a-f0-9]{32}$/',$id) && hash_equals(unsubscribe_token($id),$token)) { $stmt=$db->prepare('SELECT * FROM newsletter_subscribers WHERE id=?'); $stmt->execute([$id]); $row=$stmt->fetch(); }
        } else { $stmt=$db->prepare("SELECT * FROM newsletter_subscribers WHERE token_hash=? AND token_expires>=? AND status='pending'"); $stmt->execute([hash('sha256',$token),time()]); $row=$stmt->fetch(); }
    }
    $done=false;
    if($_SERVER['REQUEST_METHOD']==='POST') {
        check_csrf();
        if($row) {
            if($unsubscribe) $db->prepare("UPDATE newsletter_subscribers SET status='unsubscribed',unsubscribed_at=?,token_hash=NULL WHERE id=?")->execute([time(),$row['id']]);
            else {
                $change=$db->prepare("UPDATE newsletter_subscribers SET status='confirmed',confirmed_at=?,token_hash=NULL WHERE id=? AND token_hash=? AND status='pending'");
                $change->execute([time(),$row['id'],hash('sha256',$token)]);
                if($change->rowCount()!==1) $row=false;
            }
            if($row) { newsletter_event($row['id'],$unsubscribe?'unsubscribed':'confirmed'); $done=true; }
        }
    }
    if(!$row) http_response_code(400);
    $title=$unsubscribe?'Afmelden nieuwsbrief':'Bevestig je inschrijving'; $description='';
    require dirname(__DIR__).'/templates/head.php';
    echo '<main id="main" class="container section"><div class="panel admin-panel login"><h1>'.e($title).'</h1>';
    if($done) echo '<p role="status">'.($unsubscribe?'Je bent afgemeld voor de nieuwsbrief.':'Je inschrijving is bevestigd. Bedankt voor je interesse!').'</p>';
    elseif(!$row) echo '<p>Deze link is ongeldig of verlopen. Een bevestigingslink werkt één keer. Je kunt je opnieuw aanmelden op de website.</p>';
    else echo '<p>'.($unsubscribe?'Wil je geen nieuwsbrief meer ontvangen?':'Klik hieronder om je inschrijving voor de nieuwsbrief van Zichtbaar Marketing te bevestigen.').'</p><form method="post"><input type="hidden" name="csrf" value="'.e(csrf()).'"><button class="button">'.($unsubscribe?'Afmelden':'Inschrijving bevestigen').'</button></form>';
    echo '<p><a class="text-link" href="/">Terug naar de website</a></p></div></main></body></html>'; exit;
}
