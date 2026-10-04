import { createFileRoute } from "@tanstack/react-router";
import logo from "@/assets/logo.png";

export const Route = createFileRoute("/website-laten-maken")({
  component: WebsitePage,
  head: () => ({
    meta: [
      { title: "Website laten maken vanaf € 115 per maand | Zichtbaar Marketing" },
      {
        name: "description",
        content:
          "Een website voor jouw bedrijf vanaf € 115 per maand exclusief btw, op basis van 24 maanden. Bekijk Basis en Plus en vraag een kennismaking aan.",
      },
    ],
    links: [{ rel: "canonical", href: "https://www.zichtbaar-marketing.nl/website-laten-maken" }],
  }),
});

const button =
  "inline-flex items-center justify-center rounded-[10px] bg-brand px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-ink focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand";
const panel = "rounded-[24px] bg-white/60 p-7 ring-1 ring-black/5 sm:p-10";
const packages = [
  {
    name: "Basis",
    price: 115,
    intro: "Een compacte website die jouw bedrijf helder presenteert.",
    items: ["Maximaal 3 pagina’s", "Contactformulier", "3 aanpassingen per jaar"],
  },
  {
    name: "Plus",
    price: 145,
    intro: "Meer ruimte voor je aanbod en de mogelijkheid om afspraken te boeken.",
    items: ["Maximaal 7 pagina’s", "Contactformulier", "Boekingsmodule", "5 aanpassingen per jaar"],
  },
];
const faq = [
  [
    "Heb je al een concept ontvangen?",
    "Het concept uit onze mail is een eerste voorstel. We bekijken samen hoe de uitstraling, teksten en functies kunnen aansluiten op jouw bedrijf.",
  ],
  [
    "Welk abonnement past bij mij?",
    "Basis past bij een compacte website met een contactformulier. Plus biedt meer pagina’s en een boekingsmodule.",
  ],
  [
    "Hoe lang loopt het abonnement?",
    "Beide maandtarieven zijn gebaseerd op een abonnementsduur van 24 maanden. Alle prijzen zijn exclusief btw.",
  ],
  [
    "Wat houdt een aanpassing in?",
    "Voor de start spreken we af welke wijzigingen binnen een aanpassing vallen. Ook de overige voorwaarden leggen we vooraf vast.",
  ],
  [
    "Wanneer kan de website klaar zijn?",
    "Dat hangt af van je wensen en de beschikbare teksten en beelden. Tijdens de kennismaking stemmen we de planning af.",
  ],
];

function WebsitePage() {
  return (
    <div className="min-h-screen font-body text-ink antialiased">
      <header className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-5">
        <a href="/" aria-label="Zichtbaar Marketing — home">
          <img src={logo} alt="Zichtbaar Marketing" className="h-7 w-auto" />
        </a>
        <nav
          aria-label="Hoofdnavigatie"
          className="flex flex-wrap items-center gap-5 text-sm font-medium text-brand"
        >
          <a href="/#diensten">Alle diensten</a>
          <a href="#prijzen">Abonnementen</a>
          <a href="https://www.zichtbaar-marketing.nl/afspraak-plannen" className={button}>
            Plan een kennismaking
          </a>
        </nav>
      </header>
      <main id="main" className="mx-auto max-w-6xl px-6 pb-16">
        <section className={`${panel} mt-8 py-12 lg:p-16`}>
          <p className="text-sm font-semibold uppercase tracking-widest text-brand">
            Websites door Zichtbaar Marketing
          </p>
          <h1 className="mt-5 max-w-[22ch] font-display text-4xl font-medium leading-tight tracking-tight sm:text-5xl lg:text-6xl">
            Een website die past bij jouw bedrijf.
          </h1>
          <p className="mt-6 max-w-[58ch] text-lg leading-relaxed text-ink/80">
            Een heldere presentatie van je diensten, een herkenbare uitstraling en een eenvoudige
            route naar contact. Samen werken we jouw wensen uit tot een professionele website.
          </p>
          <div className="mt-8 flex flex-wrap items-center gap-5">
            <a className={button} href="https://www.zichtbaar-marketing.nl/afspraak-plannen">
              Plan een vrijblijvende kennismaking
            </a>
            <a className="font-semibold text-brand underline underline-offset-4" href="#prijzen">
              Bekijk de abonnementen
            </a>
          </div>
          <p className="mt-6 text-sm text-ink/75">
            Vanaf € 115 per maand exclusief btw · Op basis van 24 maanden
          </p>
        </section>
        <section className="py-16" aria-labelledby="jouw-website">
          <h2 id="jouw-website" className="font-display text-3xl sm:text-4xl">
            Van eerste idee naar jouw eigen website.
          </h2>
          <div className="mt-8 grid gap-5 md:grid-cols-3">
            {[
              [
                "Jouw uitstraling",
                "We stemmen kleuren, beelden en teksten af op je bedrijf en de klanten die je wilt bereiken.",
              ],
              [
                "Duidelijk op elk scherm",
                "Je diensten staan overzichtelijk op de website, met een ontwerp voor mobiel, tablet en desktop.",
              ],
              [
                "Contact binnen handbereik",
                "Maak het bezoekers eenvoudig om een vraag te stellen. Met Plus kunnen ze ook een afspraak boeken.",
              ],
            ].map(([title, text]) => (
              <article key={title} className={panel}>
                <h3 className="font-display text-xl">{title}</h3>
                <p className="mt-3 leading-relaxed text-ink/80">{text}</p>
              </article>
            ))}
          </div>
        </section>
        <section id="prijzen" className="scroll-mt-8" aria-labelledby="abonnementen">
          <p className="text-sm font-semibold uppercase tracking-widest text-brand">
            Heldere maandtarieven
          </p>
          <h2 id="abonnementen" className="mt-3 font-display text-3xl sm:text-4xl">
            Kies de ruimte die jouw bedrijf nodig heeft.
          </h2>
          <div className="mt-9 grid gap-6 md:grid-cols-2">
            {packages.map((p) => (
              <article key={p.name} className={`${panel} flex flex-col`}>
                <h3 className="font-display text-2xl">{p.name}</h3>
                <p className="mt-3 text-ink/80">{p.intro}</p>
                <p className="mt-7">
                  <strong className="font-display text-5xl font-medium">€ {p.price}</strong>
                  <span className="ml-2">per maand</span>
                </p>
                <p className="mt-2 text-sm text-ink/80">Exclusief btw</p>
                <p className="mt-2 font-semibold text-brand">
                  Op basis van een abonnementsduur van 24 maanden.
                </p>
                <ul className="my-7 space-y-3">
                  {p.items.map((item) => (
                    <li key={item} className="flex gap-3">
                      <span aria-hidden="true" className="text-brand">
                        ✓
                      </span>
                      {item}
                    </li>
                  ))}
                </ul>
                <a
                  href="https://www.zichtbaar-marketing.nl/afspraak-plannen"
                  className={`${button} mt-auto`}
                >
                  Bespreek {p.name}
                </a>
              </article>
            ))}
          </div>
          <p className="mt-5 text-sm leading-relaxed text-ink/80">
            Tijdens de kennismaking bespreken we de precieze scope, wat onder een aanpassing valt en
            de overige voorwaarden.
          </p>
        </section>
        <section
          className="my-16 rounded-[28px] bg-brand-deep p-7 text-white sm:p-12"
          aria-labelledby="werkwijze"
        >
          <h2 id="werkwijze" className="font-display text-3xl">
            Zo maken we jouw website.
          </h2>
          <ol className="mt-8 grid gap-8 md:grid-cols-3">
            {[
              [
                "Bespreek je wensen",
                "Heb je een concept ontvangen? Dan nemen we dat samen door. We bespreken je bedrijf, inhoud en gewenste functies.",
              ],
              [
                "Kies je abonnement",
                "We bepalen welk pakket past en leggen de inhoud, voorwaarden en planning vast.",
              ],
              [
                "Werk samen naar oplevering",
                "We werken het ontwerp uit en stemmen de website met je af voordat deze online gaat.",
              ],
            ].map(([title, text], i) => (
              <li key={title}>
                <span className="font-display text-3xl text-orange-200">0{i + 1}</span>
                <h3 className="mt-3 font-display text-xl">{title}</h3>
                <p className="mt-3 leading-relaxed text-white/80">{text}</p>
              </li>
            ))}
          </ol>
        </section>
        <section aria-labelledby="vragen">
          <h2 id="vragen" className="font-display text-3xl">
            Veelgestelde vragen
          </h2>
          <div className="mt-7 space-y-3">
            {faq.map(([question, answer]) => (
              <details key={question} className="rounded-xl bg-white/60 p-5 ring-1 ring-black/5">
                <summary className="cursor-pointer font-semibold">{question}</summary>
                <p className="mt-3 max-w-[75ch] leading-relaxed text-ink/80">{answer}</p>
              </details>
            ))}
          </div>
        </section>
        <section id="kennismaking" className={`${panel} mt-16 scroll-mt-8`}>
          <h2 className="font-display text-3xl">Benieuwd hoe jouw website eruit kan zien?</h2>
          <p className="mt-4 max-w-[60ch] leading-relaxed text-ink/80">
            Kies een vrij tijdstip voor een kennismaking. We bevestigen de afspraak definitief en
            sturen je daarna een Google Meet-link.
          </p>
          <div className="mt-7 flex flex-wrap gap-5">
            <a className={button} href="https://www.zichtbaar-marketing.nl/afspraak-plannen">
              Plan een kennismaking
            </a>
            <a
              className="self-center font-semibold text-brand underline underline-offset-4"
              href="tel:0857605135"
            >
              Bel 085-7605135
            </a>
          </div>
        </section>
      </main>
      <footer className="mx-auto max-w-6xl px-6 pb-10">
        <div className="flex flex-wrap items-center justify-between gap-5 rounded-[20px] bg-brand-deep p-6 text-sm text-white">
          <a href="/">Zichtbaar Marketing</a>
          <a href="mailto:info@zichtbaar-marketing.nl">info@zichtbaar-marketing.nl</a>
          <a href="tel:0857605135">085-7605135</a>
        </div>
      </footer>
    </div>
  );
}
