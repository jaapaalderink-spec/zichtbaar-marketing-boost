import { createFileRoute } from "@tanstack/react-router";
import { useState, type FormEvent } from "react";
import logo from "@/assets/logo.png";
import { services } from "@/lib/services";

export const Route = createFileRoute("/")({
  component: Index,
  head: () => ({
    meta: [
      { title: "Zichtbaar Marketing — SEO, SEA & AI-ready websites" },
      {
        name: "description",
        content:
          "Zichtbaar Marketing helpt MKB-bedrijven groeien met SEO & topical authority, SEA & AI advertising, AI-ready websites en schaalbare webshops. Plan een gesprek.",
      },
      { property: "og:title", content: "Zichtbaar Marketing — SEO, SEA & AI-ready websites" },
      {
        property: "og:description",
        content:
          "Niet alleen zichtbaar in Google, maar gebouwd voor de volgende generatie online marketing.",
      },
      { property: "og:type", content: "website" },
      { property: "og:url", content: "/" },
      { name: "twitter:card", content: "summary" },
    ],
    links: [{ rel: "canonical", href: "/" }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "ProfessionalService",
          name: "Zichtbaar Marketing",
          url: "https://www.zichtbaar-marketing.nl",
          email: "info@zichtbaar-marketing.nl",
          telephone: "085-7605135",
          description:
            "SEO & topical authority, SEA & AI advertising, AI-ready websites en webshops die kunnen doorgroeien.",
        }),
      },
    ],
  }),
});

const diensten = [
  {
    num: "01",
    title: "SEO & Topical Authority",
    intro: "Rangschikking die blijft staan.",
    items: ["Kansanalyse per sector", "Content die autoriteit bouwt", "Meting zonder poespas"],
  },
  {
    num: "02",
    title: "SEA & AI Advertising",
    intro: "Winst per euro, niet per indruk.",
    items: [
      "Campagnes met een budgetplan",
      "Automatische optimalisatie",
      "Weekrapport in je inbox",
    ],
  },
  {
    num: "03",
    title: "AI-ready websites",
    intro: "Gebouwd om begrepen te worden.",
    items: ["Snel en technisch netjes", "Structuur die AI leest", "Klaar voor groei & updates"],
  },
  {
    num: "04",
    title: "Webshops die doorgroeien",
    intro: "Van eerste klus naar vaste omzet.",
    items: ["Winkel die verkoopt", "Logistiek & betalingen", "Schaalbaar op maat"],
  },
];

const stappen = [
  {
    num: "1",
    title: "Fundament",
    text: "We leggen de basis: analyse, doelen en een plan dat past bij jouw kernklant en je budget.",
  },
  {
    num: "2",
    title: "Autoriteit",
    text: "Content en campagnes die je positioneren als dé naam in je vakgebied, op zoek én in sociale kanalen.",
  },
  {
    num: "3",
    title: "Opschalen",
    text: "Wat werkt, schalen we op. Met cijfers inzichtelijk, zodat elke euro verdedigbaar is.",
  },
];

function Logo({ className }: { className?: string }) {
  return (
    <span
      className={`inline-flex items-center rounded-xl bg-white px-3 py-1.5 ring-1 ring-black/5 ${className ?? ""}`}
    >
      <img src={logo} alt="Zichtbaar Marketing" className="h-7 w-auto" />
    </span>
  );
}

function Index() {
  const [verstuurd, setVerstuurd] = useState(false);

  function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const data = new FormData(e.currentTarget);
    const subject = `Aanvraag via website — ${data.get("bedrijfsnaam") || data.get("naam")}`;
    const body = [
      `Bedrijfsnaam: ${data.get("bedrijfsnaam")}`,
      `Naam: ${data.get("naam")}`,
      `Telefoon: ${data.get("telefoon")}`,
      `E-mail: ${data.get("email")}`,
      "",
      `Bericht: ${data.get("bericht")}`,
    ].join("\n");
    window.location.href = `mailto:info@zichtbaar-marketing.nl?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    setVerstuurd(true);
  }

  return (
    <div className="min-h-screen font-body text-ink antialiased">
      {/* Header */}
      <header className="relative z-20">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
          <a href="#" aria-label="Zichtbaar Marketing — naar boven">
            <Logo />
          </a>
          <nav className="hidden items-center gap-8 text-sm font-medium text-brand/80 md:flex">
            <a href="#diensten" className="transition-colors hover:text-brand">
              Diensten
            </a>
            <a href="#werkwijze" className="transition-colors hover:text-brand">
              Werkwijze
            </a>
            <a href="#contact" className="transition-colors hover:text-brand">
              Contact
            </a>
          </nav>
          <a
            href="#contact"
            className="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-accent-foreground ring-1 ring-accent/40 transition-colors hover:bg-ink"
          >
            Plan een afspraak
          </a>
        </div>
      </header>

      {/* Hero */}
      <section className="relative z-10 overflow-hidden">
        <div className="mx-auto max-w-6xl px-6 pt-12 pb-20 lg:pt-20 lg:pb-28">
          <div className="relative overflow-hidden rounded-[28px] bg-white/55 p-8 ring-1 ring-black/5 backdrop-blur-xl sm:p-12 lg:p-16">
            <div className="spotlight pointer-events-none absolute inset-y-0 -left-1/3 w-1/3" />
            <div className="relative">
              <p className="rise rise-1 mb-6 inline-flex items-center gap-2 rounded-full bg-white/70 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-[0.14em] text-brand ring-1 ring-black/5">
                <span className="size-1.5 rounded-full bg-accent" />
                AI-ready marketing voor het MKB
              </p>
              <h1 className="rise rise-2 max-w-[20ch] font-display text-4xl font-medium leading-[1.02] tracking-tight text-balance text-ink sm:text-5xl lg:text-6xl">
                Word gevonden.{" "}
                <span className="text-accent underline decoration-accent/30 decoration-4 underline-offset-8">
                  Blijf zichtbaar
                </span>{" "}
                in een wereld die steeds sneller zoekt.
              </h1>
              <p className="rise rise-2 mt-6 max-w-[46ch] text-base leading-relaxed text-pretty text-brand/75 sm:text-lg">
                Zichtbaar Marketing bouwt digitaal bereik dat blijft werken: content die rankt,
                advertenties die conversie opleveren en websites die zoekmachines én mensen
                begrijpen.
              </p>
              <div className="rise rise-3 mt-9 flex flex-wrap items-center gap-4">
                <a
                  href="#contact"
                  className="rounded-[10px] bg-accent px-6 py-3 text-sm font-semibold text-accent-foreground ring-1 ring-accent/50 transition-colors hover:bg-ink"
                >
                  Start een gesprek
                </a>
                <a
                  href="#werkwijze"
                  className="rounded-[10px] bg-white/60 px-6 py-3 text-sm font-semibold text-brand ring-1 ring-black/5 transition-colors hover:bg-white/90"
                >
                  Zo werken wij
                </a>
              </div>
              <div className="rise rise-3 mt-12 grid grid-cols-2 gap-px overflow-hidden rounded-[14px] bg-black/5 ring-1 ring-black/5 sm:grid-cols-3">
                <div className="bg-white/60 px-5 py-4">
                  <div className="font-display text-2xl font-semibold text-brand">3</div>
                  <div className="mt-1 text-xs text-brand/60">stappen, geen zandkastelen</div>
                </div>
                <div className="bg-white/60 px-5 py-4">
                  <div className="font-display text-2xl font-semibold text-brand">100%</div>
                  <div className="mt-1 text-xs text-brand/60">Nederlands, recht naar het doel</div>
                </div>
                <div className="col-span-2 bg-white/60 px-5 py-4 sm:col-span-1">
                  <div className="font-display text-2xl font-semibold text-brand">AI-ready</div>
                  <div className="mt-1 text-xs text-brand/60">vanaf de eerste dag</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Diensten */}
      <section id="diensten" className="relative z-10 scroll-mt-8">
        <div className="mx-auto max-w-6xl px-6 py-16 lg:py-24">
          <div className="mb-12 max-w-[44ch]">
            <p className="text-xs font-semibold uppercase tracking-[0.14em] text-accent">
              Wat we doen
            </p>
            <h2 className="mt-3 font-display text-3xl font-medium leading-tight tracking-tight text-balance text-ink lg:text-4xl">
              Vier pijlers, één doel: jouw zaak vooruit.
            </h2>
          </div>
          <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {diensten.map((d) => (
              <article
                key={d.num}
                className="group rounded-[20px] bg-white/50 p-6 ring-1 ring-black/5 backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:bg-white/70 hover:ring-accent/30"
              >
                <span className="font-display text-4xl font-semibold text-accent/25 transition-colors group-hover:text-accent">
                  {d.num}
                </span>
                <h3 className="mt-3 font-display text-lg font-medium text-ink">{d.title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-brand/70">{d.intro}</p>
                <ul className="mt-4 space-y-2 text-sm text-brand/80">
                  {d.items.map((item) => (
                    <li key={item} className="flex gap-2">
                      <span className="mt-2 size-1 shrink-0 rounded-full bg-accent" />
                      {item}
                    </li>
                  ))}
                </ul>
                <a
                  href={`/diensten/${services.find((service) => service.num === d.num)!.slug}`}
                  className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-brand underline underline-offset-4 hover:text-ink"
                  aria-label={`Bekijk ${d.title}`}
                >
                  Bekijk de dienst <span aria-hidden="true">↗</span>
                </a>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* Werkwijze */}
      <section id="werkwijze" className="relative z-10 scroll-mt-8">
        <div className="mx-auto max-w-6xl px-6 py-16 lg:py-24">
          <div className="rounded-[28px] bg-brand-deep/95 p-8 ring-1 ring-white/10 backdrop-blur-xl sm:p-12 lg:p-16">
            <div className="grid gap-10 lg:grid-cols-[1fr_1.4fr] lg:gap-16">
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.14em] text-accent">
                  Werkwijze
                </p>
                <h2 className="mt-3 font-display text-3xl font-medium leading-tight tracking-tight text-balance text-white lg:text-4xl">
                  Eén methodiek, geen verrassingen.
                </h2>
                <p className="mt-4 max-w-[38ch] text-sm leading-relaxed text-pretty text-white/70 lg:text-base">
                  Drie stappen die je van onzichtbaar naar vooraan brengen. Elke fase heeft een
                  helder resultaat en een moment om te meten.
                </p>
              </div>
              <ol className="space-y-4">
                {stappen.map((s) => (
                  <li
                    key={s.num}
                    className="flex gap-5 rounded-[18px] bg-white/5 p-5 ring-1 ring-white/10 transition-colors hover:bg-white/10"
                  >
                    <span className="font-display text-3xl font-semibold leading-none text-accent">
                      {s.num}
                    </span>
                    <div>
                      <h3 className="font-display text-lg font-medium text-white">{s.title}</h3>
                      <p className="mt-1 text-sm leading-relaxed text-white/70">{s.text}</p>
                    </div>
                  </li>
                ))}
              </ol>
            </div>
          </div>
        </div>
      </section>

      {/* Contact */}
      <section id="contact" className="relative z-10 scroll-mt-8">
        <div className="mx-auto max-w-6xl px-6 pb-16 lg:pb-24">
          <div className="grid gap-6 lg:grid-cols-2">
            <div className="rounded-[24px] bg-white/55 p-8 ring-1 ring-black/5 backdrop-blur-xl sm:p-10">
              <h2 className="font-display text-2xl font-medium tracking-tight text-balance text-ink lg:text-3xl">
                Laten we over jouw zichtbaarheid praten.
              </h2>
              <p className="mt-3 max-w-[40ch] text-sm leading-relaxed text-pretty text-brand/70 lg:text-base">
                Vertel kort waar je staat. We komen terug met een concreet eerste stappenplan — geen
                standaard pitch.
              </p>
              <dl className="mt-8 space-y-4 text-sm">
                <div className="flex items-baseline gap-3">
                  <dt className="w-24 shrink-0 font-medium text-brand/50">E-mail</dt>
                  <dd>
                    <a
                      href="mailto:info@zichtbaar-marketing.nl"
                      className="font-medium text-brand transition-colors hover:text-accent"
                    >
                      info@zichtbaar-marketing.nl
                    </a>
                  </dd>
                </div>
                <div className="flex items-baseline gap-3">
                  <dt className="w-24 shrink-0 font-medium text-brand/50">Telefoon</dt>
                  <dd>
                    <a
                      href="tel:0857605135"
                      className="font-medium text-brand transition-colors hover:text-accent"
                    >
                      085-7605135
                    </a>
                  </dd>
                </div>
              </dl>
            </div>
            <form
              onSubmit={handleSubmit}
              className="rounded-[24px] bg-white/60 p-8 ring-1 ring-black/5 backdrop-blur-xl sm:p-10"
            >
              <div className="grid gap-4 sm:grid-cols-2">
                <label className="block sm:col-span-2">
                  <span className="text-xs font-semibold uppercase tracking-[0.1em] text-brand/60">
                    Bedrijfsnaam
                  </span>
                  <input
                    name="bedrijfsnaam"
                    type="text"
                    placeholder="Bijv. De Vries Installaties"
                    className="mt-1.5 w-full rounded-[10px] bg-white/70 px-4 py-3 text-sm text-ink ring-1 ring-black/5 outline-none transition-shadow placeholder:text-brand/40 focus:ring-2 focus:ring-accent/60"
                  />
                </label>
                <label className="block">
                  <span className="text-xs font-semibold uppercase tracking-[0.1em] text-brand/60">
                    Naam *
                  </span>
                  <input
                    name="naam"
                    type="text"
                    required
                    placeholder="Je naam"
                    className="mt-1.5 w-full rounded-[10px] bg-white/70 px-4 py-3 text-sm text-ink ring-1 ring-black/5 outline-none transition-shadow placeholder:text-brand/40 focus:ring-2 focus:ring-accent/60"
                  />
                </label>
                <label className="block">
                  <span className="text-xs font-semibold uppercase tracking-[0.1em] text-brand/60">
                    Telefoon *
                  </span>
                  <input
                    name="telefoon"
                    type="tel"
                    required
                    placeholder="06 12 34 56 78"
                    className="mt-1.5 w-full rounded-[10px] bg-white/70 px-4 py-3 text-sm text-ink ring-1 ring-black/5 outline-none transition-shadow placeholder:text-brand/40 focus:ring-2 focus:ring-accent/60"
                  />
                </label>
                <label className="block sm:col-span-2">
                  <span className="text-xs font-semibold uppercase tracking-[0.1em] text-brand/60">
                    E-mail *
                  </span>
                  <input
                    name="email"
                    type="email"
                    required
                    placeholder="naam@bedrijf.nl"
                    className="mt-1.5 w-full rounded-[10px] bg-white/70 px-4 py-3 text-sm text-ink ring-1 ring-black/5 outline-none transition-shadow placeholder:text-brand/40 focus:ring-2 focus:ring-accent/60"
                  />
                </label>
                <label className="block sm:col-span-2">
                  <span className="text-xs font-semibold uppercase tracking-[0.1em] text-brand/60">
                    Waar wil je mee starten?
                  </span>
                  <textarea
                    name="bericht"
                    rows={3}
                    placeholder="Kort je doel of vraag..."
                    className="mt-1.5 w-full resize-none rounded-[10px] bg-white/70 px-4 py-3 text-sm text-ink ring-1 ring-black/5 outline-none transition-shadow placeholder:text-brand/40 focus:ring-2 focus:ring-accent/60"
                  />
                </label>
              </div>
              <button
                type="submit"
                className="mt-6 w-full rounded-[10px] bg-accent px-6 py-3.5 text-sm font-semibold text-accent-foreground ring-1 ring-accent/40 transition-colors hover:bg-ink"
              >
                Verstuur en plan een gesprek
              </button>
              <p className="mt-3 text-xs text-brand/50">
                {verstuurd
                  ? "Je e-mailprogramma opent met je bericht — versturen en we nemen contact op."
                  : "Je bericht opent in je eigen e-mailprogramma. Geen spam, geen verplichtingen."}
              </p>
            </form>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="relative z-10">
        <div className="mx-auto max-w-6xl px-6 pb-10">
          <div className="flex flex-col items-start justify-between gap-4 rounded-[20px] bg-brand-deep/95 px-6 py-6 ring-1 ring-white/10 sm:flex-row sm:items-center">
            <Logo />
            <p className="text-xs text-white/50">
              © {new Date().getFullYear()} Zichtbaar Marketing ·{" "}
              <a
                href="mailto:info@zichtbaar-marketing.nl"
                className="transition-colors hover:text-accent"
              >
                info@zichtbaar-marketing.nl
              </a>{" "}
              ·{" "}
              <a href="tel:0857605135" className="transition-colors hover:text-accent">
                085-7605135
              </a>
            </p>
          </div>
        </div>
      </footer>
    </div>
  );
}
