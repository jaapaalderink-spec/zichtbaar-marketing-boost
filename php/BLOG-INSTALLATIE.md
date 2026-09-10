# Blogs op je eigen PHP-hosting

## Een blog eerder maken

Op het blogoverzicht en bij Onderwerpen uploaden staat **Maak nu de eerstvolgende blog**. De knop maakt en publiceert één onderwerp uit de wachtrij, inclusief de ingeschakelde AI-afbeelding. Ook wanneer de wekelijkse planning uitstaat kun je deze knop gebruiken. Een openstaande toekomstige dinsdagbeurt blijft staan; als de beurt al verstreken was, wordt deze naar de volgende week verplaatst. Dubbelklikken of hetzelfde formulier opnieuw verzenden publiceert geen tweede onderwerp. De aanvraag kan enkele minuten duren; stel op je hosting de PHP/FastCGI/proxy-time-out hiervoor ruim genoeg in (minimaal vijf minuten). Een onderbroken aanvraag kan al opgeslagen inhoud hebben: vernieuw eerst het overzicht voordat je opnieuw probeert.

## Automatisch doorplaatsen naar LinkedIn

Ga naar `/admin/blog/linkedin`. De koppeling is ingericht voor de **bedrijfspagina**, niet een persoonlijk profiel. Nieuwe artikelen krijgen een LinkedIn-post met titel, korte samenvatting en een artikelkaart met klikbare bloglink en de geüploade blogafbeelding. Automatische AI-afbeeldingen zijn JPG en kunnen direct worden gebruikt. Voor een handmatig artikel met WebP: upload een JPG of PNG om door te plaatsen. Zonder eigen afbeelding wordt het logo gebruikt.

1. Zet de website op je openbare HTTPS-domein en stel de juiste basis-URL in via E-mailinstellingen.
2. Maak een [LinkedIn Developer-app](https://www.linkedin.com/developers/apps) en vraag de voor bedrijfspagina’s benodigde Community Management-toegang aan. Je app moet `w_organization_social` mogen aanvragen. Het LinkedIn-account waarmee je verbindt moet publicatierechten voor de bedrijfspagina hebben.
3. Registreer `https://jouw-domein.nl/admin/blog/linkedin/callback` als OAuth redirect-URL. Gebruik exact de URL die het beheer toont.
4. Vul Client ID, Client Secret en het numerieke bedrijfspagina-ID in het beheer in. Sla eerst op met automatisch doorplaatsen uit. Klik daarna **Verbinden met LinkedIn** en geef toestemming op LinkedIn zelf.
5. Zet **Nieuwe blogpublicaties automatisch op deze bedrijfspagina delen** aan en sla op. Deze expliciete instelling activeert automatisch delen. Bestaande artikelen worden niet ineens achteraf gepubliceerd.
6. Gebruik dezelfde cronjob als voor blogpublicaties. Iedere run verwerkt maximaal één LinkedIn-bericht. Als LinkedIn de afbeelding nog verwerkt, gaat de volgende run verder. De lokale website op 127.0.0.1 is niet geschikt om de openbare koppeling te activeren.

De [LinkedIn Posts API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api?view=li-lms-2026-06) vereist een vooraf geüploade thumbnail voor artikelkaarten; de implementatie gebruikt hiervoor de [Images API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/images-api?view=li-lms-2026-06). De versie staat instelbaar op 202606. Controleer bij toekomstig API-onderhoud welke versies LinkedIn ondersteunt.

De verbindingsdatum wordt gebaseerd op de `expires_in` uit de [OAuth-autorisatie](https://learn.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow). Het beheer toont wanneer je opnieuw moet verbinden. Tokens en appgeheim blijven in `config.local.php`, buiten de openbare map; geef ze niet door via chat of GitHub. Deze implementatie vraagt bij verloop opnieuw toestemming en gebruikt geen automatische tokenverversing.

Bij een fout blijft je blog gewoon op de website staan. Onder LinkedIn-berichten kun je de status bekijken en een mislukt bericht opnieuw inplannen. Wanneer verzending mogelijk al geslaagd is maar een bevestiging ontbreekt, wordt niet automatisch opnieuw verzonden: controleer eerst je bedrijfspagina en bevestig dat het bericht ontbreekt. Met **Afhandelen zonder verzenden** voorkom je een nieuwe poging. Verwijderen van de verbinding zet delen uit; het verwijdert geen bestaande LinkedIn-posts.

De tests simuleren LinkedIn en plaatsen geen echte berichten. Je LinkedIn-app moet door LinkedIn worden toegelaten en op je hosting worden verbonden voordat de koppeling live werkt.

Ga naar `/admin/blog`. Je kunt artikelen schrijven, een JPG/PNG/WebP uploaden, een privévoorbeeld bekijken en direct publiceren of als concept bewaren. Afbeeldingen mogen maximaal 8 MB en 25 megapixels zijn. Zonder upload wordt de meegeleverde merkafbeelding gebruikt. Het openbare overzicht staat op `/blog`; elk gepubliceerd artikel heeft een eigen URL en contactknop.

## Onderwerpen en automatisch schrijven

Upload onder **Onderwerpen uploaden** een UTF-8 TXT-bestand met één onderwerp per regel, of CSV met `onderwerp` en optioneel `tekst,samenvatting`. Voorbeelden staan in `examples/`. Maximaal 100 onderwerpen per bestand. Herhaalde onderwerpen uit uploads worden overgeslagen. De wachtrij volgt de uploadvolgorde. Bewerk een onderwerp en kies Concept om het uit de wachtrij te halen.

Onder **Blogplanning** staat dinsdag om 10:00 uur Nederlandse tijd ingesteld. Zomer- en wintertijd worden automatisch verwerkt. Vul je eigen OpenAI API-sleutel in, kies automatisch uitwerken en vink wekelijks publiceren aan. De sleutel blijft in het privéconfiguratiebestand. Het standaardmodel is `gpt-4.1-mini`; het veld is aanpasbaar. Gebruik een API-project met een passend budget. Bij automatisch schrijven wordt standaard ook een AI-illustratie gemaakt met hetzelfde API-project. Voor de tekst wordt het onderwerp meegestuurd; voor de afbeelding ook de samenvatting. In Blogplanning kun je afbeeldingsgeneratie uitzetten of het afbeeldingsmodel aanpassen. Het standaardmodel is gpt-image-2.5-sunburst, met een liggende JPG van 1536 × 1024 pixels en medium kwaliteit. Een eigen geüploade afbeelding blijft behouden. Afbeeldingen gebruiken extra API-tegoed.

De koppeling gebruikt de [OpenAI Responses API met Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs), met opslag uitgeschakeld. API-gebruik wordt apart door OpenAI in rekening gebracht. Gegenereerde artikelen verschijnen direct op het ingestelde moment; controleer de inhoud en toon. Bij tijdelijke fouten blijft het onderwerp privé en volgt hoogstens eenmaal per uur een nieuwe poging. De blogtekst en afbeelding worden tussentijds opgeslagen, zodat een volgende poging ze hergebruikt. Bij afbeeldingsfouten die een aanpassing aan onderwerp, sleutel of tegoed vereisen, pauzeert de wekelijkse planning. De planning is aanvankelijk uitgeschakeld.

## Geplande taak op de hosting

Stel in het hostingpaneel een cronjob in die iedere vijf minuten draait. Vervang de voorbeeldpaden door de echte paden van jouw hosting:

```cron
*/5 * * * * /usr/bin/php /home/account/website/php/bin/publish-blogs.php >> /home/account/private/blog-cron.log 2>&1
```

Het script gebruikt Nederlandse tijd, ongeacht de servertijdzone. Het publiceert maximaal één wachtrijartikel per wekelijkse beurt. Na gemiste beurten publiceert het één artikel en plant de volgende week, zodat niet de hele achterstand tegelijk verschijnt. Individueel ingeplande artikelen worden ook verwerkt wanneer de wekelijkse wachtrij is gepauzeerd. Bij langere AI-verwerking verschijnt het artikel kort na 10:00 uur. De laatste controle en de volgende beurt staan in het beheer.

Een open browser is niet nodig. De cronjob moet op de hosting worden ingesteld; het uploaden van deze bestanden activeert hem niet. De taak is alleen via PHP CLI uitvoerbaar, niet als openbare web-URL.

## Hostingvereisten en updates

Gebruik PHP 8.2 of hoger met PDO SQLite, OpenSSL en iconv. Voor AI moet `allow_url_fopen` aanstaan en moet uitgaand HTTPS naar `api.openai.com` mogelijk zijn met geldige CA-certificaten. Stel `upload_max_filesize=8M` en `post_max_size=10M` of hoger in. Webserver én CLI moeten dezelfde PHP-extensies en schrijfrechten op de privéopslag hebben.

De documentroot blijft `php/public`. Houd `php/config` en `php/storage` buiten de openbare map. Maak voor updates een back-up van configuratie, database en afbeeldingen; vervang je bestaande `config.local.php` en opslag niet. Nieuwe tabellen worden automatisch toegevoegd. Het distributiepakket bevat geen wachtwoorden, API-sleutels of inschrijvingen.

De geautomatiseerde tests gebruiken tijdelijke opslag, lokale SMTP en gesimuleerde AI-antwoorden. Een echte AI-aanroep en de hostingcron moeten na het instellen van je sleutel en hosting worden gecontroleerd.

Afbeeldingskoppeling: [OpenAI Image API](https://developers.openai.com/api/docs/guides/image-generation). Geef de hostingtaak minimaal vijf minuten uitvoertijd voor tekst en afbeelding samen.
