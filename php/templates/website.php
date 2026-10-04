<main id="main" class="container website-landing">
<section class="panel hero-panel" style="margin-top:32px">
<p class="eyebrow">Websites door Zichtbaar Marketing</p>
<h1>Een website die past bij jouw bedrijf.</h1>
<p class="lead">Een heldere presentatie van je diensten, een herkenbare uitstraling en een eenvoudige route naar contact. Samen werken we jouw wensen uit tot een professionele website.</p>
<div class="actions"><a class="button" href="#contact">Vraag een vrijblijvende kennismaking aan</a><a class="text-link" href="#prijzen">Bekijk de abonnementen</a></div>
<p>Vanaf € 115 per maand exclusief btw · Op basis van 24 maanden</p>
</section>
<section class="section" aria-labelledby="jouw-website"><h2 id="jouw-website">Van eerste idee naar jouw eigen website.</h2>
<div class="grid three">
<article class="panel card"><h3>Jouw uitstraling</h3><p>We stemmen kleuren, beelden en teksten af op je bedrijf en de klanten die je wilt bereiken.</p></article>
<article class="panel card"><h3>Duidelijk op elk scherm</h3><p>Je diensten staan overzichtelijk op de website, met een ontwerp voor mobiel, tablet en desktop.</p></article>
<article class="panel card"><h3>Contact binnen handbereik</h3><p>Maak het bezoekers eenvoudig om een vraag te stellen. Met Plus kunnen ze ook een afspraak boeken.</p></article>
</div></section>
<section id="prijzen" aria-labelledby="abonnementen"><p class="eyebrow">Heldere maandtarieven</p><h2 id="abonnementen">Kies de ruimte die jouw bedrijf nodig heeft.</h2>
<div class="grid two">
<?php foreach ([['Basis',115,'Een compacte website die jouw bedrijf helder presenteert.',['Maximaal 3 pagina’s','Contactformulier','3 aanpassingen per jaar']],['Plus',145,'Meer ruimte voor je aanbod en de mogelijkheid om afspraken te boeken.',['Maximaal 7 pagina’s','Contactformulier','Boekingsmodule','5 aanpassingen per jaar']]] as [$name,$price,$intro,$items]): ?>
<article class="panel card price-card"><h3><?= e($name) ?></h3><p><?= e($intro) ?></p><p class="price"><strong>€ <?= $price ?></strong> per maand</p><p>Exclusief btw<br><strong>Op basis van een abonnementsduur van 24 maanden.</strong></p><ul class="bullets"><?php foreach($items as $item): ?><li><?= e($item) ?></li><?php endforeach ?></ul><a class="button" href="#contact">Bespreek <?= e($name) ?></a></article>
<?php endforeach ?>
</div><p>Tijdens de kennismaking bespreken we de precieze scope, wat onder een aanpassing valt en de overige voorwaarden.</p></section>
<section class="dark approach website-approach" aria-labelledby="website-werkwijze"><div><p class="eyebrow">Zo werken we samen</p><h2 id="website-werkwijze">Zo maken we jouw website.</h2></div><ol class="steps">
<li><span>1</span><div><h3>Bespreek je wensen</h3><p>Heb je een concept ontvangen? Dan nemen we dat samen door. We bespreken je bedrijf, inhoud en gewenste functies.</p></div></li>
<li><span>2</span><div><h3>Kies je abonnement</h3><p>We bepalen welk pakket past en leggen de inhoud, voorwaarden en planning vast.</p></div></li>
<li><span>3</span><div><h3>Werk samen naar oplevering</h3><p>We werken het ontwerp uit en stemmen de website met je af voordat deze online gaat.</p></div></li>
</ol></section>
<section class="section website-faq" aria-labelledby="vragen"><h2 id="vragen">Veelgestelde vragen</h2>
<?php foreach ([
['Heb je al een concept ontvangen?','Het concept uit onze mail is een eerste voorstel. We bekijken samen hoe de uitstraling, teksten en functies kunnen aansluiten op jouw bedrijf.'],
['Welk abonnement past bij mij?','Basis past bij een compacte website met een contactformulier. Plus biedt meer pagina’s en een boekingsmodule.'],
['Hoe lang loopt het abonnement?','Beide maandtarieven zijn gebaseerd op een abonnementsduur van 24 maanden. Alle prijzen zijn exclusief btw.'],
['Wat houdt een aanpassing in?','Voor de start spreken we af welke wijzigingen binnen een aanpassing vallen. Ook de overige voorwaarden leggen we vooraf vast.'],
['Wanneer kan de website klaar zijn?','Dat hangt af van je wensen en de beschikbare teksten en beelden. Tijdens de kennismaking stemmen we de planning af.'],
] as [$question,$answer]): ?><details class="panel card"><summary><?= e($question) ?></summary><p><?= e($answer) ?></p></details><?php endforeach ?>
</section>
<?php
$service = ['closing'=>'Benieuwd hoe jouw website eruit kan zien?','closingText'=>'Vraag een vrijblijvende kennismaking aan. Vertel kort over je bedrijf en, als je al een concept hebt ontvangen, wat je daarvan vindt. We spreken samen een geschikt moment af.','cta'=>'Vraag een kennismaking aan'];
require __DIR__.'/contact.php';
?>
</main>
