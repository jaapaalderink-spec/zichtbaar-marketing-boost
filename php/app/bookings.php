<?php
declare(strict_types=1);

function booking_db(): PDO {
    static $ready=false;
    $db = db();
    if($ready) return $db;
    $db->exec("CREATE TABLE IF NOT EXISTS meeting_slots(id INTEGER PRIMARY KEY, starts INTEGER NOT NULL UNIQUE, ends INTEGER NOT NULL, enabled INTEGER NOT NULL DEFAULT 1);
      CREATE TABLE IF NOT EXISTS meetings(id TEXT PRIMARY KEY, slot_id INTEGER NOT NULL, name TEXT NOT NULL, company TEXT NOT NULL, email TEXT NOT NULL, phone TEXT NOT NULL, note TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending', meet_url TEXT NOT NULL DEFAULT '', version INTEGER NOT NULL DEFAULT 1, created_at INTEGER NOT NULL);
      CREATE UNIQUE INDEX IF NOT EXISTS meeting_one_active_slot ON meetings(slot_id) WHERE status IN ('pending','confirmed');
      CREATE TABLE IF NOT EXISTS meeting_mail(id TEXT PRIMARY KEY, meeting_id TEXT NOT NULL, kind TEXT NOT NULL, recipient TEXT NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL, calendar TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'pending', created_at INTEGER NOT NULL, sent_at INTEGER);");
    $ready=true; return $db;
}
function meeting_time(int $stamp, string $format = 'd-m-Y H:i'): string {
    return (new DateTimeImmutable('@'.$stamp))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format($format);
}
function meeting_text(mixed $value, int $max, bool $required = true): string {
    if (!is_string($value) || !preg_match('//u', $value) || strlen($value)>$max || ($required && trim($value)==='')) throw new InvalidArgumentException('Controleer de ingevulde velden en hun lengte.');
    return trim($value);
}
function meeting_local(string $value): int {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('Europe/Amsterdam'));
    if (!$date || $date->format('Y-m-d\TH:i') !== $value) throw new InvalidArgumentException('Kies een geldige datum en tijd (Nederlandse tijd).');
    // Refuse the repeated clock hour at the autumn DST change.
    foreach ((new DateTimeZone('Europe/Amsterdam'))->getTransitions($date->getTimestamp()-86400,$date->getTimestamp()+86400) as $i=>$transition) {
        if ($i && $transition['offset'] < $previous && $date->getTimestamp() >= $transition['ts']-($previous-$transition['offset']) && $date->getTimestamp() < $transition['ts']+($previous-$transition['offset'])) throw new InvalidArgumentException('Dit tijdstip valt in de dubbele wintertijd-uurwisseling. Kies een ander tijdstip.');
        $previous = $transition['offset'];
    }
    return $date->getTimestamp();
}
function meeting_transaction(callable $work): mixed {
    $db = booking_db(); $db->exec('BEGIN IMMEDIATE');
    try { $result=$work($db); $db->exec('COMMIT'); return $result; }
    catch (Throwable $error) { $db->exec('ROLLBACK'); throw $error; }
}
function meeting_add_slots(array $input): int {
    $day=meeting_text($input['day'] ?? '',10); $from=meeting_text($input['from'] ?? '',5); $until=meeting_text($input['until'] ?? '',5);
    $start=meeting_local($day.'T'.$from); $end=meeting_local($day.'T'.$until);
    $minutes=filter_var($input['duration'] ?? '',FILTER_VALIDATE_INT);
    if (!in_array($minutes,[15,30,45,60,90,120],true) || $start<=time() || $end<=$start || $end-$start>43200 || $start>time()+366*86400 || ($end-$start)%($minutes*60)!==0) throw new InvalidArgumentException('Kies een toekomstige periode binnen één jaar, van maximaal 12 uur, die precies past bij de gekozen gespreksduur.');
    return meeting_transaction(function(PDO $db) use($start,$end,$minutes) {
        $conflict=$db->prepare('SELECT id FROM meeting_slots WHERE enabled=1 AND starts < ? AND ends > ?'); $conflict->execute([$end,$start]);
        if ($conflict->fetch()) throw new InvalidArgumentException('Deze periode overlapt met bestaande beschikbare tijdstippen.');
        $add=$db->prepare('INSERT INTO meeting_slots(starts,ends) VALUES(?,?) ON CONFLICT(starts) DO UPDATE SET ends=excluded.ends,enabled=1');
        $count=0; for($t=$start;$t<$end;$t+=$minutes*60) { $add->execute([$t,$t+$minutes*60]); $count++; } return $count;
    });
}
function meeting_get(string $id): array {
    $stmt=booking_db()->prepare('SELECT m.*,s.starts,s.ends FROM meetings m JOIN meeting_slots s ON s.id=m.slot_id WHERE m.id=?'); $stmt->execute([$id]);
    $row=$stmt->fetch(); if(!$row) throw new InvalidArgumentException('Deze afspraak bestaat niet.'); return $row;
}
function meeting_calendar(array $m): string {
    $escape=fn(string $s)=>str_replace(["\\","\r","\n",',',';'],["\\\\",'',"\\n","\\,","\\;"],$s);
    $method=$m['status']==='cancelled'?'CANCEL':'REQUEST'; $status=$m['status']==='cancelled'?'CANCELLED':'CONFIRMED';
    $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Zichtbaar Marketing//Meetings//NL','METHOD:'.$method,'BEGIN:VEVENT','UID:'.$m['id'].'@zichtbaar-marketing.nl','DTSTAMP:'.gmdate('Ymd\THis\Z'),'DTSTART:'.gmdate('Ymd\THis\Z',$m['starts']),'DTEND:'.gmdate('Ymd\THis\Z',$m['ends']),'SEQUENCE:'.$m['version'],'STATUS:'.$status,'SUMMARY:Kennismaking met Zichtbaar Marketing','ORGANIZER:mailto:'.config()['smtp_from'],'ATTENDEE;RSVP=TRUE:mailto:'.$m['email'],'DESCRIPTION:'.$escape('Kennismaking met Zichtbaar Marketing'.($m['meet_url']!==''?' — '.$m['meet_url']:''))];
    if($m['meet_url']!=='') $lines[]='LOCATION:'.$m['meet_url'];
    $lines[]='END:VEVENT'; $lines[]='END:VCALENDAR';
    $folded=[];
    foreach($lines as $line) {
        while(strlen($line)>75) {
            $length=75; while(!preg_match('//u',substr($line,0,$length))) $length--;
            $folded[]=substr($line,0,$length); $line=' '.substr($line,$length);
        }
        $folded[]=$line;
    }
    return implode("\r\n",$folded)."\r\n";
}
function meeting_queue(PDO $db,array $m,string $kind): void {
    $admin=$kind==='admin'; $cancel=$kind==='cancel'; $requested=in_array($kind,['request','admin'],true);
    $subject=$admin?'Nieuwe meetingaanvraag':($requested?'Je meetingaanvraag is ontvangen':($cancel?'Je afspraak is geannuleerd':($kind==='link'?'Google Meet-link voor je afspraak':'Je afspraak is definitief bevestigd')));
    $body=$admin?"Nieuwe aanvraag van ".$m['name']." (".$m['company'].")\nE-mail: ".$m['email']."\nTelefoon: ".$m['phone']."\nBeheer: ".rtrim(config()['base_url'],'/').'/admin/meetings':"Hoi ".$m['name'].",\n\n";
    $body.="\n".$subject.".\nTijdstip: ".meeting_time($m['starts']).' – '.meeting_time($m['ends'],'H:i')." (Europe/Amsterdam).\n";
    if($requested) $body.="Dit is een voorlopige reservering. Je ontvangt nog een definitieve bevestiging.\n";
    if(!$requested && !$cancel) $body.=$m['meet_url']!==''?"Google Meet: ".$m['meet_url']."\n":"De Google Meet-link volgt in een aparte e-mail.\n";
    if($admin) $body.="\nToelichting: ".$m['note']."\n";
    $body.="\nVragen? Reageer op deze mail of bel 085-7605135.\n\nZichtbaar Marketing\ninfo@zichtbaar-marketing.nl";
    $id=$m['id'].'-'.$kind.'-'.$m['version'];
    $db->prepare('INSERT OR IGNORE INTO meeting_mail(id,meeting_id,kind,recipient,subject,body,calendar,created_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,$m['id'],$kind,$admin?CONTACT_RECIPIENT:$m['email'],$subject.' — Zichtbaar Marketing',$body,!$requested?meeting_calendar($m):'',time()]);
}
function meeting_reserve(array $input,string $id): string {
    $name=meeting_text($input['name'] ?? '',100); $company=meeting_text($input['company'] ?? '',150,false);
    $email=meeting_text($input['email'] ?? '',254); $phone=meeting_text($input['phone'] ?? '',40,false); $note=meeting_text($input['note'] ?? '',2000,false);
    $slot=filter_var($input['slot'] ?? '',FILTER_VALIDATE_INT);
    if(!$slot || !filter_var($email,FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$name.$email)) throw new InvalidArgumentException('Vul een geldig e-mailadres in en kies een beschikbaar tijdstip.');
    return meeting_transaction(function(PDO $db) use($id,$slot,$name,$company,$email,$phone,$note) {
        $old=$db->prepare('SELECT id FROM meetings WHERE id=?'); $old->execute([$id]); if($old->fetch()) return $id;
        $stmt=$db->prepare("SELECT s.* FROM meeting_slots s WHERE s.id=? AND s.enabled=1 AND s.starts>? AND NOT EXISTS(SELECT 1 FROM meetings m WHERE m.slot_id=s.id AND m.status IN ('pending','confirmed'))"); $stmt->execute([$slot,time()]); $s=$stmt->fetch();
        if(!$s) throw new InvalidArgumentException('Dit tijdstip is niet meer beschikbaar. Kies een ander moment.');
        $db->prepare('INSERT INTO meetings(id,slot_id,name,company,email,phone,note,created_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,$slot,$name,$company,$email,$phone,$note,time()]);
        $m=meeting_get($id); meeting_queue($db,$m,'request'); meeting_queue($db,$m,'admin'); return $id;
    });
}
function meeting_change(array $input): void {
    $id=meeting_text($input['id'] ?? '',32); $action=meeting_text($input['action'] ?? '',20);
    $version=filter_var($input['version'] ?? '',FILTER_VALIDATE_INT);
    $url=meeting_text($input['meet_url'] ?? '',200,false);
    if($url!=='' && !preg_match('~^https://meet\.google\.com/[a-z]{3}-[a-z]{4}-[a-z]{3}$~D',$url)) throw new InvalidArgumentException('Gebruik een Google Meet-link zoals https://meet.google.com/abc-defg-hij.');
    meeting_transaction(function(PDO $db) use($id,$action,$version,$url) {
        $m=meeting_get($id);
        if((int)$m['version']!==$version) throw new InvalidArgumentException('Deze afspraak is ondertussen gewijzigd. Vernieuw de agenda.');
        if($m['starts']<=time() || $m['status']==='cancelled') throw new InvalidArgumentException('Deze afspraak is verlopen of geannuleerd.');
        if($action==='confirm' && $m['status']==='pending') { $status='confirmed'; $kind='confirm'; }
        elseif($action==='link' && $m['status']==='confirmed' && $url!=='') { $status='confirmed'; $kind='link'; }
        elseif($action==='cancel') { $status='cancelled'; $kind='cancel'; }
        else throw new InvalidArgumentException('Bevestig de afspraak voordat je een Google Meet-link deelt.');
        $db->prepare('UPDATE meetings SET status=?,meet_url=?,version=version+1 WHERE id=?')->execute([$status,$url!==''?$url:$m['meet_url'],$id]);
        $db->prepare("UPDATE meeting_mail SET status='superseded' WHERE meeting_id=? AND status IN ('pending','failed')")->execute([$id]);
        // A cancelled appointment's slot stays closed until the admin explicitly opens it again.
        if($status==='cancelled') $db->prepare('UPDATE meeting_slots SET enabled=0 WHERE id=?')->execute([$m['slot_id']]);
        meeting_queue($db,meeting_get($id),$kind);
    });
}
function meeting_send_mail(string $id,bool $retry=false): bool {
    require_once __DIR__.'/mail.php'; $db=booking_db();
    if(!smtp_configured()) return false;
    $stmt=$db->prepare("UPDATE meeting_mail SET status='sending' WHERE id=? AND status=?"); $stmt->execute([$id,$retry?'failed':'pending']); if($stmt->rowCount()!==1) return false;
    $stmt=$db->prepare('SELECT * FROM meeting_mail WHERE id=?'); $stmt->execute([$id]); $row=$stmt->fetch();
    try {
        $mail=site_mailer(); $mail->addAddress($row['recipient']); $mail->addReplyTo(CONTACT_RECIPIENT,'Zichtbaar Marketing');
        $mail->Subject=$row['subject']; $mail->Body=$row['body']; $mail->MessageID='<meeting-'.$id.'@zichtbaar-marketing.nl>';
        if($row['calendar']!=='') $mail->addStringAttachment($row['calendar'],'afspraak.ics','base64','text/calendar; method='.($row['kind']==='cancel'?'CANCEL':'REQUEST'));
        $mail->send(); $db->prepare("UPDATE meeting_mail SET status='sent',sent_at=? WHERE id=?")->execute([time(),$id]); return true;
    } catch(Throwable $error) {
        $db->prepare("UPDATE meeting_mail SET status='failed' WHERE id=?")->execute([$id]); error_log('Meeting mail not confirmed: '.$id); return false;
    }
}
function meeting_send_pending(string $meetingId): bool {
    $stmt=booking_db()->prepare("SELECT id FROM meeting_mail WHERE meeting_id=? AND status='pending' ORDER BY created_at,id"); $stmt->execute([$meetingId]); $ids=$stmt->fetchAll(PDO::FETCH_COLUMN); $stmt->closeCursor();
    $ok=true; foreach($ids as $id) if(!meeting_send_mail($id)) $ok=false; return $ok;
}
function meeting_month(mixed $value): DateTimeImmutable {
    $zone=new DateTimeZone('Europe/Amsterdam'); $fallback=(new DateTimeImmutable('now',$zone))->modify('first day of this month')->setTime(0,0);
    if(!is_string($value)) return $fallback;
    $date=DateTimeImmutable::createFromFormat('!Y-m', $value,$zone);
    return $date && $date->format('Y-m')===$value && abs($date->getTimestamp()-time())<5*366*86400?$date:$fallback;
}
function booking_public(): never {
    booking_db(); $error=null;
    if($_SERVER['REQUEST_METHOD']==='POST') {
        check_csrf();
        try {
            if(!empty($_POST['website'])) throw new InvalidArgumentException('Dit formulier kon niet worden verwerkt.');
            $id=meeting_text($_POST['request_id'] ?? '',32);
            if(!preg_match('/^[a-f0-9]{32}$/D',$id) || !isset($_SESSION['meeting_requests'][$id]) || $_SESSION['meeting_requests'][$id]<time()-7200) throw new InvalidArgumentException('Dit formulier is verlopen. Vernieuw de pagina.');
            if(!rate_limit('meeting:'.($_SERVER['REMOTE_ADDR'] ?? 'unknown'),8,3600) || !rate_limit('meeting-global',100,3600)) throw new InvalidArgumentException('Er zijn meerdere aanvragen gedaan. Probeer later opnieuw of bel ons.');
            $email=meeting_text($_POST['email'] ?? '',254);
            if(!filter_var($email,FILTER_VALIDATE_EMAIL) || !rate_limit('meeting-email:'.strtolower($email),3,3600)) throw new InvalidArgumentException('Controleer je e-mailadres of probeer later opnieuw.');
            $id=meeting_reserve($_POST,$id); $sent=meeting_send_pending($id);
            flash('success','Je tijdstip is voorlopig gereserveerd. Zichtbaar Marketing bevestigt de afspraak nog definitief.'.($sent?' Controleer je e-mail.':' De e-mail is nog niet bevestigd; de reservering staat wel in onze agenda.')); redirect('/afspraak-plannen');
        } catch(InvalidArgumentException $exception) { $error=$exception->getMessage(); }
    } elseif($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); header('Allow: GET, POST'); exit; }
    $stmt=booking_db()->prepare("SELECT s.id,s.starts,s.ends FROM meeting_slots s WHERE s.enabled=1 AND s.starts>? AND NOT EXISTS(SELECT 1 FROM meetings m WHERE m.slot_id=s.id AND m.status IN ('pending','confirmed')) ORDER BY s.starts LIMIT 500"); $stmt->execute([time()]); $slots=$stmt->fetchAll();
    $_SESSION['meeting_requests']=array_filter($_SESSION['meeting_requests'] ?? [],fn($t)=>$t>time()-7200); if(count($_SESSION['meeting_requests'])>20) array_shift($_SESSION['meeting_requests']);
    $requestId=bin2hex(random_bytes(16)); $_SESSION['meeting_requests'][$requestId]=time();
    $path='/afspraak-plannen'; $title='Plan een kennismaking — Zichtbaar Marketing'; $description='Kies een vrij tijdstip voor een kennismaking. Na onze definitieve bevestiging ontvang je een Google Meet-link.';
    require dirname(__DIR__).'/templates/head.php'; require dirname(__DIR__).'/templates/header.php'; require dirname(__DIR__).'/templates/booking.php'; require dirname(__DIR__).'/templates/footer.php'; exit;
}
