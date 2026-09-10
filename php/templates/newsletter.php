<?php require_once dirname(__DIR__).'/app/newsletter.php'; $newsletterNotice=$_SESSION['newsletter_flash'] ?? null; unset($_SESSION['newsletter_flash']); ?>
<section id="nieuwsbrief" class="container section"><div class="panel contact-intro">
<p class="eyebrow">Blijf op de hoogte</p><h2>Nieuwe ideeën voor jouw zichtbaarheid.</h2><p>Ontvang de nieuwsbrief met tips over SEO, advertenties en websites. Je schrijft je pas definitief in nadat je je e-mailadres hebt bevestigd.</p>
<?php if($newsletterNotice): ?><p class="notice <?= e($newsletterNotice[0]) ?>" role="status"><?= e($newsletterNotice[1]) ?></p><?php endif ?>
<form action="/nieuwsbrief/aanmelden" method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
<div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
<div class="form-grid">
<label>Voornaam *<input name="first_name" required maxlength="100" autocomplete="given-name" placeholder="Je voornaam"></label>
<label>Achternaam *<input name="last_name" required maxlength="100" autocomplete="family-name" placeholder="Je achternaam"></label>
<label class="wide">Bedrijfsnaam (optioneel)<input name="company" maxlength="150" autocomplete="organization" placeholder="Je bedrijfsnaam"></label>
<label class="wide">E-mailadres *<input name="email" type="email" required maxlength="254" autocomplete="email" placeholder="naam@bedrijf.nl"></label>
</div>
<label class="newsletter-consent"><input name="consent" type="checkbox" value="yes" required><span><?= e(NEWSLETTER_CONSENT) ?></span></label>
<button class="button" type="submit">Stuur mij de bevestigingsmail</button></form>
</div></section>
