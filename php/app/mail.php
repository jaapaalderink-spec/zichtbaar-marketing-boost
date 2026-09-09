<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/PHPMailer/Exception.php';
require_once dirname(__DIR__) . '/lib/PHPMailer/PHPMailer.php';
require_once dirname(__DIR__) . '/lib/PHPMailer/SMTP.php';

function deliver_message(string $id): bool {
    if (!smtp_configured()) return false;
    // A single worker may claim a message. Never automatically retry an ambiguous SMTP result.
    $claim = db()->prepare("UPDATE messages SET status='sending', attempts=attempts+1 WHERE id=? AND status IN ('pending','failed')");
    $claim->execute([$id]);
    if ($claim->rowCount() !== 1) return false;
    $stmt = db()->prepare('SELECT payload FROM messages WHERE id=?'); $stmt->execute([$id]);
    $data = json_decode($stmt->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
    try {
        $c = config(); $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP(); $mail->Host = $c['smtp_host']; $mail->Port = (int)$c['smtp_port'];
        $mail->SMTPAuth = true; $mail->Username = $c['smtp_username']; $mail->Password = $c['smtp_password'];
        $mail->SMTPSecure = $c['smtp_security']; $mail->Timeout = 15; $mail->Timelimit = 20;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($c['smtp_from'], 'Zichtbaar Marketing');
        $mail->addAddress(CONTACT_RECIPIENT);
        $mail->addReplyTo($data['email'], $data['naam']);
        $mail->Subject = 'Nieuwe contactaanvraag — Zichtbaar Marketing';
        $mail->MessageID = '<contact-' . $id . '@zichtbaar-marketing.nl>';
        $mail->Body = "Nieuwe aanvraag via de website\n\n" . implode("\n", [
            'Naam: '.$data['naam'], 'Bedrijf: '.$data['bedrijfsnaam'], 'E-mail: '.$data['email'],
            'Telefoon: '.$data['telefoon'], 'Pagina: '.$data['source'], '', $data['bericht'], '', 'Referentie: '.$id,
        ]);
        $mail->send();
        db()->prepare("UPDATE messages SET status='sent',sent_at=? WHERE id=?")->execute([time(),$id]);
        return true;
    } catch (Throwable $error) {
        db()->prepare("UPDATE messages SET status='failed' WHERE id=?")->execute([$id]);
        error_log('Contact SMTP delivery failed, message '.$id); // Never log credentials or message contents.
        return false;
    }
}
