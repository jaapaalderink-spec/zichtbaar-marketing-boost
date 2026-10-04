<main id="main" class="container booking-page">
<section class="panel hero-panel"><p class="eyebrow">Kennismaken met Zichtbaar Marketing</p><h1>Kies een moment dat jou uitkomt.</h1><p class="lead">Reserveer een beschikbaar tijdstip. We bevestigen de afspraak daarna definitief en sturen je een Google Meet-link, zodat je eenvoudig online kunt aansluiten.</p><p>Alle tijdstippen worden weergegeven in Nederlandse tijd (Europe/Amsterdam).</p></section>
<?php $notice=$_SESSION['flash'] ?? null; unset($_SESSION['flash']); if($notice): ?><p class="notice <?= e($notice[0]) ?>" role="status"><?= e($notice[1]) ?></p><?php endif ?>
<?php if($error): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif ?>
<section class="section">
<?php if(!$slots): ?><div class="panel card"><h2>Er staan nog geen vrije tijdstippen klaar.</h2><p>Neem contact op om een kennismaking af te spreken.</p><div class="actions"><a class="button" href="mailto:info@zichtbaar-marketing.nl">Mail Zichtbaar Marketing</a><a class="text-link" href="tel:0857605135">Bel 085-7605135</a></div></div>
<?php else: ?>
<form method="post" action="/afspraak-plannen" class="panel contact-form booking-form">
<h2>Reserveer je kennismaking</h2><p>Je tijdstip wordt vastgehouden totdat we de aanvraag hebben beoordeeld. De afspraak is definitief na onze bevestiging.</p>
<input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="request_id" value="<?= e($requestId) ?>">
<div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
<label>Beschikbaar tijdstip *<select name="slot" required><option value="">Kies een dag en tijd</option>
<?php $group=null; foreach($slots as $slot): $day=meeting_time($slot['starts'],'d-m-Y'); if($day!==$group): if($group!==null) echo '</optgroup>'; $group=$day; ?><optgroup label="<?= e($day) ?>"><?php endif ?><option value="<?= $slot['id'] ?>" <?= (string)($_POST['slot'] ?? '')===(string)$slot['id']?'selected':'' ?>><?= e(meeting_time($slot['starts'],'H:i').' – '.meeting_time($slot['ends'],'H:i')) ?></option><?php endforeach ?> </optgroup></select></label>
<div class="form-grid">
<?php foreach([['name','Naam *',100,'name',true],['company','Bedrijfsnaam',150,'organization',false],['email','E-mailadres *',254,'email',true],['phone','Telefoonnummer',40,'tel',false]] as [$key,$label,$max,$autocomplete,$required]): ?>
<label><?= e($label) ?><input name="<?= $key ?>" type="<?= $key==='email'?'email':($key==='phone'?'tel':'text') ?>" maxlength="<?= $max ?>" autocomplete="<?= $autocomplete ?>" <?= $required?'required':'' ?> value="<?= e(is_string($_POST[$key] ?? null)?$_POST[$key]:'') ?>"></label>
<?php endforeach ?>
<label class="wide">Wat wil je bespreken?<textarea name="note" rows="4" maxlength="2000" placeholder="Vertel kort over je bedrijf en je websitewensen."><?= e(is_string($_POST['note'] ?? null)?$_POST['note']:'') ?></textarea></label></div>
<button class="button full" type="submit">Reserveer dit tijdstip</button><p class="fine">We gebruiken je gegevens om deze afspraak te plannen en je de bevestiging en Meet-link te sturen.</p>
</form><?php endif ?></section></main>
