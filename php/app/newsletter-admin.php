<?php
declare(strict_types=1);
require_once __DIR__.'/newsletter.php';
function newsletter_export(): never {
    require_admin();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nieuwsbrief-bevestigd.csv"');
    $out=fopen('php://output','w');
    fputcsv($out,['voornaam','achternaam','bedrijfsnaam','email','bevestigd_op_utc','toestemming','afmeldlink'],',','"','');
    $rows=newsletter_db()->query("SELECT * FROM newsletter_subscribers WHERE status='confirmed' ORDER BY confirmed_at DESC");
    while($row=$rows->fetch()) {
        $values=[$row['first_name'],$row['last_name'],$row['company'],$row['email'],gmdate('c',$row['confirmed_at']),$row['consent_text'],rtrim(config()['base_url'],'/').'/nieuwsbrief/afmelden?id='.$row['id'].'&token='.unsubscribe_token($row['id'])];
        $values=array_map(fn($value)=>preg_match('/^[=+@\-\t\r\n]/',$value)?"'".$value:$value,$values);
        fputcsv($out,$values,',','"','');
    }
    fclose($out); exit;
}
function render_newsletter_admin(): void {
    $page=max(1,min(100000,(int)($_GET['page'] ?? 1)));
    $counts=newsletter_db()->query('SELECT status,count(*) AS total FROM newsletter_subscribers GROUP BY status')->fetchAll();
    $labels=['pending'=>'Wacht op bevestiging','confirmed'=>'Bevestigd','unsubscribed'=>'Afgemeld'];
    echo '<section class="panel admin-panel"><h2>Nieuwsbriefinschrijvingen</h2><p>Alleen bevestigde inschrijvingen worden geëxporteerd. Gebruik vóór iedere verzending een nieuwe export en neem de persoonlijke afmeldlink op in elke nieuwsbrief.</p><p>Dit onderdeel beheert inschrijvingen; het versturen van complete nieuwsbrieven gebeurt in je mailprogramma of nieuwsbriefdienst.</p><div class="actions">';
    foreach($counts as $count) echo '<span>'.e($labels[$count['status']]).': '.(int)$count['total'].'</span>';
    echo '</div><p><a class="button" href="/admin/newsletter/export">Exporteer bevestigde adressen</a></p>';
    $stmt=newsletter_db()->prepare('SELECT first_name,last_name,company,email,status,requested_at,confirmed_at,mail_status FROM newsletter_subscribers ORDER BY requested_at DESC LIMIT 25 OFFSET ?');
    $stmt->bindValue(1,($page-1)*25,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();
    if(!$rows) echo '<p>Er zijn nog geen inschrijvingen op deze pagina.</p>';
    foreach($rows as $row) echo '<article class="panel message"><h3>'.e($row['email']).'</h3><p>'.e(trim($row['first_name'].' '.$row['last_name'])).($row['company']!==''?' · '.e($row['company']):'').'</p><p>'.e($labels[$row['status']]).' · Aangevraagd: '.e(gmdate('d-m-Y H:i',$row['requested_at'])).' UTC'.($row['confirmed_at']?' · Bevestigd: '.e(gmdate('d-m-Y H:i',$row['confirmed_at'])).' UTC':'').'</p>'.($row['mail_status']==='failed'?'<p class="notice error">Bevestigingsmail niet verstuurd; de bezoeker kan opnieuw aanmelden.</p>':'').'</article>';
    echo '<nav class="actions" aria-label="Inschrijvingen bladeren">';
    if($page>1) echo '<a class="text-link" href="/admin/newsletter?page='.($page-1).'">Vorige</a>';
    if(count($rows)===25) echo '<a class="text-link" href="/admin/newsletter?page='.($page+1).'">Volgende</a>';
    echo '</nav></section>';
}
