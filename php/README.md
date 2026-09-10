# Zichtbaar Marketing — PHP-versie

Zelfstandige PHP-website met dezelfde vijf publieke pagina’s, beheer voor de pagin teksten, contactopslag en SMTP-verzending. Geen Node.js, Supabase of externe database nodig op de hosting. De bestaande React/Lovable-bron blijft apart beschikbaar in de repository; wijzigingen in het PHP-beheer wijzigen die bronbestanden niet.

## Hosting

- PHP 8.2 of hoger met PDO SQLite, session en OpenSSL. De lokale test gebruikt PHP 8.4.
- Upload de hele map `php`. Stel de document root in op `php/public`. De mappen `app`, `config`, `storage`, `lib` en `bin` mogen niet publiek bereikbaar zijn.
- Apache: de meegeleverde `.htaccess` in `public` verzorgt de routes. Voor Nginx: `try_files $uri /index.php?$query_string`, PHP via PHP-FPM. Laat alleen `index.php` uitvoeren.
- Maak `php/storage` schrijfbaar voor PHP. Geef `php/config` schrijfrechten wanneer je de beheerder via de installatielink activeert of de mailinstellingen vanuit het beheer wilt opslaan. Geen wereldwijde schrijfrechten (777).
- Gebruik HTTPS. Bij TLS-terminatie op een proxy moet de webserver de betrouwbare HTTPS-status aan PHP doorgeven; de app vertrouwt geen willekeurige forwarded headers.
- Maak automatische back-ups van `storage/website.sqlite` en `config/config.local.php`, buiten de openbare map. Gebruik SQLite-back-upfunctionaliteit of stop schrijven tijdens een bestandskopie inclusief WAL. Bewaar wachtwoorden en klantgegevens privé.

## Activeren

1. Voer `php bin/setup.php https://www.zichtbaar-marketing.nl` uit. Dit genereert een eenmalige installatielink, geen standaardwachtwoord.
2. Open de link en kies een wachtwoord van minimaal 14 tekens voor `info@zichtbaar-marketing.nl`.
3. Ga naar `/admin/settings` en vul de SMTP-gegevens van de mailprovider in. Gebruik STARTTLS op poort 587 of SSL op poort 465. Certificaatcontrole is verplicht en wordt niet uitgeschakeld.
4. Doe een contactaanvraag en controleer **zowel** `/admin/messages` als de echte inbox van `info@zichtbaar-marketing.nl`. Een SMTP-acceptatie bewijst geen bezorging in de inbox; controleer ook spam en de SPF/DKIM/DMARC-instellingen bij de provider.

Zonder SSH kun je `bin/setup.php` lokaal uitvoeren met de uiteindelijke website-URL en het gegenereerde `config/config.local.php` via de veilige bestandsbeheerder van je hosting buiten de document root plaatsen. Verwijder de lokale kopie met productiegeheimen zodra deze niet meer nodig is.

## Lokaal starten

`php -S 127.0.0.1:8080 -t public router.php`

De PHP-ontwikkelserver is uitsluitend voor lokaal gebruik. De PHP-site is bereikbaar op poort 8080; de oude React-preview op 5173 bevat niet de PHP-beheerfuncties.

## Beheer en contact

- Op alle publieke pagina’s staat een aparte nieuwsbriefinschrijving met een niet vooraf aangevinkte toestemmingskeuze. Er wordt niets toegevoegd aan de actieve lijst totdat de bezoeker de bevestigingsmail volgt en de bevestigingsknop indrukt (binnen 48 uur).
- `/admin/newsletter`: bekijk de status en exporteer alleen bevestigde inschrijvingen. De export bevat de persoonlijke afmeldlink. Neem deze op in elke nieuwsbrief en gebruik vóór elke verzending een verse export, zodat afmeldingen worden gerespecteerd. Er is geen programma voor het opstellen of massaal versturen van nieuwsbrieven inbegrepen.
- Nieuwsbriefadressen, toestemmingsverklaring en tijdstippen worden in dezelfde privé-SQLite-database opgeslagen. Bevestigingstokens worden gehasht opgeslagen. Afmeldingen worden direct uitgesloten van de volgende export; eerder gedownloade bestanden veranderen niet automatisch.
- De contactformulieren en bevestigingsmails gebruiken dezelfde SMTP-configuratie. Ontbreekt het mailboxwachtwoord, dan vraagt het beheer dat nu expliciet. Met **Stuur een testmail naar mijn inbox** test je de opgeslagen configuratie.
- Stel in `/admin/settings` ook de echte HTTPS-website-URL in. Deze wordt gebruikt voor bevestigings- en afmeldlinks. Een URL met `127.0.0.1` is alleen voor lokale tests.

## Zelf hosten: van lokaal naar online

1. Kies je eigen hosting met PHP 8.2+, PDO SQLite en OpenSSL. Een draaiende PHP-versie lokaal betekent niet dat deze al online staat. Een Lovable/React-preview voert deze PHP-bestanden niet uit.
2. Upload het PHP-installatiepakket en stel de document root in op de map `public` **binnen dat pakket**. Upload de privéconfiguratie en SQLite-data nooit in de publieke map. Behoud bij updates de bestaande map `storage` en `config/config.local.php`.
3. Activeer HTTPS en het beheeraccount, vul het mailboxwachtwoord en de door jouw provider opgegeven SMTP-server in. De voorbeeldhost is geen bevestiging dat jouw provider die host gebruikt.
4. Sla de publieke website-URL op en gebruik de testmailknop. Test daarna één contactformulier én een volledige nieuwsbriefinschrijving, bevestiging en afmelding vanaf het echte domein.
5. Controleer echte aflevering in de inbox. Lokale tests gebruiken een afgeschermde testmailserver en bewijzen geen bezorging via jouw hostingprovider.

Automatische controles: `node tests/integration.cjs /pad/naar/php /pad/naar/openssl`. Deze gebruiken een aparte tijdelijke database en een lokale SMTP-server met gecontroleerd TLS-certificaat. Er worden geen echte e-mails verstuurd.

- `/admin`: publiceer tekstwijzigingen voor de homepage en vier dienstenpagina’s. Wijzigingen zijn blijvend opgeslagen in SQLite; gelijktijdige wijzigingen geven een versieconflict.
- `/admin/messages`: bekijk aanvragen en probeer mislukte verzending handmatig opnieuw. Bij een onzekere SMTP-uitslag eerst de inbox controleren om dubbele e-mail te voorkomen. Er is geen automatische verzendtaak ingericht.
- `/admin/settings`: SMTP instellen. Wachtwoorden staan uitsluitend in de niet-publieke configuratie en worden niet teruggetoond.
- `/admin/password`: wachtwoord veranderen; bestaande sessies worden ongeldig.
- Formulieren op alle vijf pagina’s posten naar dezelfde serveractie. Aanvragen worden eerst opgeslagen. Zonder SMTP of bij een verzendfout krijgt de bezoeker een eerlijke melding dat de aanvraag is opgeslagen maar de e-mail nog niet verzonden is.
- Beveiliging: gehashte wachtwoorden, CSRF-controle, sessierotatie en -verloop, servercontrole op elke beheeractie, begrensde login/contactpogingen, honeypot, geparametriseerde SQL en HTML-escaping.
- Bewaar contactgegevens niet langer dan nodig. Bespreek een passende bewaartermijn en back-upverwijdering voordat de website publiek wordt ingezet.

PHPMailer is meegeleverd uit de officiële v7.1.1-release; zie `lib/PHPMailer/LICENSE`. Update deze bibliotheek periodiek via de officiële bron.

## Blogs en LinkedIn

Via /admin/blog kun je artikelen maken, onderwerpen uploaden en de eerstvolgende blog met één knop direct laten maken en publiceren. Automatisch publiceren staat standaard op dinsdag 10:00 Nederlandse tijd, met optionele AI-afbeeldingen. Via /admin/blog/linkedin kun je doorplaatsen naar de bedrijfspagina instellen. Zie BLOG-INSTALLATIE.md voor API-instellingen, LinkedIn-autorisatie en de cronjob op je eigen hosting.
