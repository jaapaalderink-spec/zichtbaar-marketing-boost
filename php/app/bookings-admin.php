<?php
declare(strict_types=1);
require_once __DIR__.'/bookings.php';

function meeting_admin(string $path): never {
    require_admin(); booking_db(); $error=null;
    if(!in_array($path,['/admin/meetings','/admin/meetings/slots','/admin/meetings/change','/admin/meetings/mail'],true)) { http_response_code(404); exit; }
    if($_SERVER['REQUEST_METHOD']==='POST') {
        check_csrf();
        try {
            if($path==='/admin/meetings/slots') {
                if(($_POST['action'] ?? '')==='close') {
                    $id=filter_var($_POST['slot_id'] ?? '',FILTER_VALIDATE_INT);
                    $stmt=booking_db()->prepare("UPDATE meeting_slots SET enabled=0 WHERE id=? AND NOT EXISTS(SELECT 1 FROM meetings WHERE slot_id=meeting_slots.id AND status IN ('pending','confirmed'))"); $stmt->execute([$id]);
                    if($stmt->rowCount()!==1) throw new InvalidArgumentException('Dit tijdstip is gereserveerd of bestaat niet. Annuleer eerst de afspraak als dat nodig is.');
                    flash('success','Beschikbaar tijdstip gesloten.');
                } else { $count=meeting_add_slots($_POST); flash('success',$count.' beschikbare tijdstippen toegevoegd.'); }
            } elseif($path==='/admin/meetings/change') {
                meeting_change($_POST); $sent=meeting_send_pending(meeting_text($_POST['id'] ?? '',32));
                flash($sent?'success':'error',$sent?'Afspraak bijgewerkt. De mailserver heeft de e-mail geaccepteerd.':'Afspraak bijgewerkt, maar verzending is niet bevestigd. Controleer de mailstatus en je inbox voordat je opnieuw verstuurt.');
            } elseif($path==='/admin/meetings/mail') {
                $id=meeting_text($_POST['mail_id'] ?? '',100);
                $stmt=booking_db()->prepare('SELECT * FROM meeting_mail WHERE id=?'); $stmt->execute([$id]); $mail=$stmt->fetch();
                if(!$mail || !in_array($mail['status'],['pending','failed'],true)) throw new InvalidArgumentException('Deze e-mail is al verzonden of de verzending loopt.');
                $m=meeting_get($mail['meeting_id']);
                if($mail['kind']!=='cancel' && $m['status']==='cancelled') throw new InvalidArgumentException('Deze afspraak is geannuleerd. Verstuur geen oude bevestiging.');
                if(str_ends_with($mail['id'],'-'.$m['version'])===false) throw new InvalidArgumentException('Deze e-mail hoort bij een oudere versie van de afspraak. Gebruik de nieuwste e-mail.');
                if(!rate_limit('meeting-mail-admin',30,3600)) throw new InvalidArgumentException('Er zijn meerdere e-mails verzonden. Probeer later opnieuw.');
                $sent=meeting_send_mail($id,$mail['status']==='failed'); flash($sent?'success':'error',$sent?'De mailserver heeft de e-mail geaccepteerd.':'Verzending is niet bevestigd. Controleer je SMTP-instellingen en de inbox.');
            } else throw new InvalidArgumentException('Onbekende beheeractie.');
            $chosenMonth=($path==='/admin/meetings/slots' && ($_POST['action'] ?? '')!=='close')?substr(meeting_text($_POST['day'] ?? '',10),0,7):($_POST['month'] ?? null);
            $month=meeting_month($chosenMonth)->format('Y-m'); redirect('/admin/meetings?month='.$month);
        } catch(InvalidArgumentException $exception) { $error=$exception->getMessage(); }
    } elseif($_SERVER['REQUEST_METHOD']!=='GET' || $path!=='/admin/meetings') { http_response_code(405); header('Allow: POST'); exit; }
    $month=meeting_month($_GET['month'] ?? ($_POST['month'] ?? null)); $until=$month->modify('+1 month');
    $stmt=booking_db()->prepare('SELECT s.*,m.id AS meeting_id,m.status,m.name,m.company FROM meeting_slots s LEFT JOIN meetings m ON m.slot_id=s.id AND m.status IN (\'pending\',\'confirmed\') WHERE s.starts>=? AND s.starts<? AND (s.enabled=1 OR m.id IS NOT NULL) ORDER BY s.starts'); $stmt->execute([$month->getTimestamp(),$until->getTimestamp()]); $calendar=$stmt->fetchAll();
    $days=[]; foreach($calendar as $slot) $days[meeting_time($slot['starts'],'j')][]=$slot;
    $stmt=booking_db()->prepare('SELECT m.*,s.starts,s.ends FROM meetings m JOIN meeting_slots s ON s.id=m.slot_id WHERE s.starts>=? AND s.starts<? ORDER BY s.starts,m.created_at'); $stmt->execute([$month->getTimestamp(),$until->getTimestamp()]); $meetings=$stmt->fetchAll();
    $title='Meetings en beschikbaarheid — Zichtbaar Marketing'; $description=''; $noindex=true;
    require dirname(__DIR__).'/templates/head.php'; require dirname(__DIR__).'/templates/meetings-admin.php'; echo '</body></html>'; exit;
}
