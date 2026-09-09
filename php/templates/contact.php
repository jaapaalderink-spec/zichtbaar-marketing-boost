<?php
$_SESSION['contact_ids'] ??= [];
$_SESSION['contact_ids'] = array_filter($_SESSION['contact_ids'], fn($created)=>$created > time()-7200);
if (count($_SESSION['contact_ids']) > 20) array_shift($_SESSION['contact_ids']);
$requestId = bin2hex(random_bytes(16)); $_SESSION['contact_ids'][$requestId] = time();
$old = $_SESSION['contact_old'] ?? [];
$notice = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<section id="contact" class="section contact-section <?= $service ? '' : 'container' ?>">
<?php if ($notice): ?><p class="notice <?= e($notice[0]) ?>" role="status"><?= e($notice[1]) ?></p><?php endif ?>
<div class="grid two"><div class="panel contact-intro"><p class="eyebrow">Jouw volgende stap</p><h2><?= e($service['closing'] ?? $home['contactTitle']) ?></h2><p><?= e($service['closingText'] ?? $home['contactIntro']) ?></p><dl><div><dt>E-mail</dt><dd><a href="mailto:info@zichtbaar-marketing.nl">info@zichtbaar-marketing.nl</a></dd></div><div><dt>Telefoon</dt><dd><a href="tel:0857605135">085-7605135</a></dd></div></dl></div>
<form action="/contact" method="post" class="panel contact-form">
<input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="request_id" value="<?= e($requestId) ?>"><input type="hidden" name="source" value="<?= e($path) ?>">
<div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
<div class="form-grid"><label class="wide">Bedrijfsnaam<input name="bedrijfsnaam" maxlength="150" autocomplete="organization" placeholder="Bijv. De Vries Installaties" value="<?= e(is_string($old['bedrijfsnaam'] ?? null) ? $old['bedrijfsnaam'] : '') ?>"></label>
<label>Naam *<input name="naam" required maxlength="100" autocomplete="name" placeholder="Je naam" value="<?= e(is_string($old['naam'] ?? null) ? $old['naam'] : '') ?>"></label>
<label>Telefoon *<input name="telefoon" type="tel" required maxlength="40" autocomplete="tel" placeholder="06 12 34 56 78" value="<?= e(is_string($old['telefoon'] ?? null) ? $old['telefoon'] : '') ?>"></label>
<label class="wide">E-mail *<input name="email" type="email" required maxlength="254" autocomplete="email" placeholder="naam@bedrijf.nl" value="<?= e(is_string($old['email'] ?? null) ? $old['email'] : '') ?>"></label>
<label class="wide">Waar wil je mee starten? *<textarea name="bericht" required rows="4" maxlength="2000" placeholder="Kort je doel of vraag..."><?= e(is_string($old['bericht'] ?? null) ? $old['bericht'] : '') ?></textarea></label></div>
<button class="button full" type="submit"><?= e($service['cta'] ?? 'Verstuur en plan een gesprek') ?></button><p class="fine">Je aanvraag gaat naar Zichtbaar Marketing. Geen verplichtingen.</p>
</form></div></section>
