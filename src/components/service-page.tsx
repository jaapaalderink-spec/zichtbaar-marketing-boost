import logo from "@/assets/logo.png";
import { services, type Service } from "@/lib/services";

const button =
  "inline-flex items-center justify-center rounded-[10px] bg-accent px-6 py-3.5 text-sm font-semibold text-accent-foreground transition-colors hover:bg-ink focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-accent";

export function ServicePage({ service }: { service: Service }) {
  return (
    <div className="min-h-screen font-body text-ink antialiased">
      <header className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-5">
        <a
          href="/"
          aria-label="Zichtbaar Marketing — home"
          className="rounded-xl bg-white px-3 py-1.5 ring-1 ring-black/5"
        >
          <img src={logo} alt="Zichtbaar Marketing" className="h-7 w-auto" />
        </a>
        <nav
          aria-label="Hoofdnavigatie"
          className="flex flex-wrap items-center gap-5 text-sm font-medium text-brand"
        >
          <a href="/#diensten" className="hover:text-ink">
            Alle diensten
          </a>
          <a href="/#contact" className={button}>
            Plan een afspraak
          </a>
        </nav>
      </header>
      <main className="mx-auto max-w-6xl px-6 pb-16">
        <nav aria-label="Broodkruimel" className="flex flex-wrap gap-2 pt-7 text-sm text-brand">
          <a href="/" className="underline underline-offset-4">
            Home
          </a>
          <span aria-hidden="true">/</span>
          <a href="/#diensten" className="underline underline-offset-4">
            Diensten
          </a>
          <span aria-hidden="true">/</span>
          <span aria-current="page" className="text-ink">
            {service.title}
          </span>
        </nav>
        <section className="relative mt-8 overflow-hidden rounded-[28px] bg-white/55 p-7 ring-1 ring-black/5 backdrop-blur-xl sm:p-12 lg:p-16">
          <div
            className="spotlight pointer-events-none absolute inset-y-0 -left-1/3 w-1/3"
            aria-hidden="true"
          />
          <div className="relative grid items-start gap-10 lg:grid-cols-[1fr_220px]">
            <div>
              <p className="mb-5 text-sm font-semibold uppercase tracking-widest text-brand">
                {service.title}
              </p>
              <h1 className="max-w-[20ch] font-display text-4xl font-medium leading-[1.08] tracking-tight text-balance sm:text-5xl lg:text-6xl">
                {service.headline}
              </h1>
              <p className="mt-6 max-w-[58ch] text-base leading-relaxed text-ink/75 sm:text-lg">
                {service.intro}
              </p>
              <div className="mt-8 flex flex-wrap items-center gap-5">
                <a href="/#contact" className={button}>
                  {service.cta}{" "}
                  <span aria-hidden="true" className="ml-2">
                    ↗
                  </span>
                </a>
                <a
                  href="#aanpak"
                  className="text-sm font-semibold text-brand underline underline-offset-4"
                >
                  Bekijk onze aanpak
                </a>
              </div>
            </div>
            <div className="border-t border-brand/15 pt-5 lg:border-t-0 lg:border-l lg:pl-8">
              <span
                aria-hidden="true"
                className="font-display text-6xl font-medium text-accent/70 lg:text-8xl"
              >
                {service.num}
              </span>
              <p className="mt-4 font-display text-xl text-brand">{service.label}</p>
              <p className="mt-3 text-sm leading-relaxed text-ink/65">
                Een helder plan.
                <br />
                Een concrete volgende stap.
              </p>
            </div>
          </div>
        </section>
        <section className="py-16 lg:py-24" aria-labelledby="inhoud">
          <p className="text-sm font-semibold uppercase tracking-widest text-brand">
            Wat we voor je doen
          </p>
          <h2
            id="inhoud"
            className="mt-3 font-display text-3xl font-medium tracking-tight sm:text-4xl"
          >
            Van ambitie naar een werkbare aanpak.
          </h2>
          <div className="mt-9 grid gap-5 md:grid-cols-3">
            {service.benefits.map(([title, text], i) => (
              <article key={title} className="rounded-[20px] bg-white/50 p-7 ring-1 ring-black/5">
                <span className="font-display text-2xl text-brand">0{i + 1}</span>
                <h3 className="mt-4 font-display text-xl font-medium">{title}</h3>
                <p className="mt-3 text-base leading-relaxed text-ink/75">{text}</p>
              </article>
            ))}
          </div>
        </section>
        <section
          id="aanpak"
          className="scroll-mt-8 rounded-[28px] bg-brand-deep/95 p-7 text-white sm:p-12 lg:p-16"
        >
          <div className="grid gap-10 lg:grid-cols-[1fr_1.4fr]">
            <div>
              <p className="text-sm font-semibold uppercase tracking-widest text-white/70">
                Zo werken we samen
              </p>
              <h2 className="mt-3 font-display text-3xl font-medium tracking-tight sm:text-4xl">
                Drie stappen.
                <br />
                Duidelijke keuzes.
              </h2>
            </div>
            <ol className="space-y-4">
              {service.steps.map(([title, text], i) => (
                <li
                  key={title}
                  className="flex gap-5 rounded-[18px] bg-white/5 p-5 ring-1 ring-white/10"
                >
                  <span className="font-display text-3xl text-accent">{i + 1}</span>
                  <div>
                    <h3 className="font-display text-xl">{title}</h3>
                    <p className="mt-2 text-base leading-relaxed text-white/75">{text}</p>
                  </div>
                </li>
              ))}
            </ol>
          </div>
        </section>
        <section
          className="my-16 rounded-[28px] bg-white/60 p-7 ring-1 ring-black/5 sm:p-12 lg:my-24"
          aria-labelledby="volgende-stap"
        >
          <p className="text-sm font-semibold uppercase tracking-widest text-brand">
            Jouw volgende stap
          </p>
          <h2
            id="volgende-stap"
            className="mt-4 max-w-[28ch] font-display text-3xl font-medium tracking-tight text-balance sm:text-4xl"
          >
            {service.closing}
          </h2>
          <p className="mt-4 max-w-[60ch] text-base leading-relaxed text-ink/75">
            {service.closingText}
          </p>
          <div className="mt-7 flex flex-wrap items-center gap-5">
            <a href="/#contact" className={button}>
              {service.cta}
            </a>
            <a
              href="tel:0857605135"
              className="text-sm font-semibold text-brand underline underline-offset-4"
            >
              Of bel 085-7605135
            </a>
          </div>
        </section>
        <section aria-labelledby="andere-diensten">
          <h2 id="andere-diensten" className="font-display text-2xl font-medium">
            Ook verder bouwen aan je zichtbaarheid?
          </h2>
          <div className="mt-6 grid gap-4 md:grid-cols-3">
            {services
              .filter((s) => s.slug !== service.slug)
              .map((s) => (
                <a
                  key={s.slug}
                  href={`/diensten/${s.slug}`}
                  className="flex items-center justify-between gap-4 rounded-[18px] bg-white/50 p-6 font-medium text-brand ring-1 ring-black/5 transition-colors hover:bg-white/90"
                >
                  <span>{s.title}</span>
                  <span aria-hidden="true">↗</span>
                </a>
              ))}
          </div>
        </section>
      </main>
      <footer className="mx-auto max-w-6xl px-6 pb-10">
        <div className="flex flex-wrap items-center justify-between gap-5 rounded-[20px] bg-brand-deep/95 p-6 text-sm text-white/80">
          <a href="/" className="font-semibold text-white">
            Zichtbaar Marketing
          </a>
          <a href="mailto:info@zichtbaar-marketing.nl" className="break-all hover:text-white">
            info@zichtbaar-marketing.nl
          </a>
          <a href="/#contact" className="font-semibold text-white underline underline-offset-4">
            Plan een afspraak ↗
          </a>
        </div>
      </footer>
    </div>
  );
}
