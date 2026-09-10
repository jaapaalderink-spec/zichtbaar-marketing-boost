import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useEffect, useState, type FormEvent } from "react";
import { supabase } from "@/integrations/supabase/client";
import { lovable } from "@/integrations/lovable/index";
import logo from "@/assets/logo.png";

export const Route = createFileRoute("/auth")({
  component: AuthPage,
  head: () => ({
    meta: [
      { title: "Inloggen — Zichtbaar Marketing" },
      {
        name: "description",
        content: "Log in om de berichten van potentiële klanten van Zichtbaar Marketing te bekijken.",
      },
      { property: "og:title", content: "Inloggen — Zichtbaar Marketing" },
      { property: "og:description", content: "Beheeromgeving van Zichtbaar Marketing." },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary" },
      { name: "robots", content: "noindex" },
    ],
  }),
});

function AuthPage() {
  const navigate = useNavigate();
  const [modus, setModus] = useState<"login" | "registreren">("login");
  const [email, setEmail] = useState("");
  const [wachtwoord, setWachtwoord] = useState("");
  const [melding, setMelding] = useState<string | null>(null);
  const [bezig, setBezig] = useState(false);

  useEffect(() => {
    supabase.auth.getSession().then(({ data }) => {
      if (data.session) navigate({ to: "/berichten", replace: true });
    });
  }, [navigate]);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setBezig(true);
    setMelding(null);
    if (modus === "login") {
      const { error } = await supabase.auth.signInWithPassword({ email, password: wachtwoord });
      setBezig(false);
      if (error) return setMelding("Inloggen mislukt. Controleer je e-mailadres en wachtwoord.");
      navigate({ to: "/berichten", replace: true });
    } else {
      const { error } = await supabase.auth.signUp({
        email,
        password: wachtwoord,
        options: { emailRedirectTo: `${window.location.origin}/berichten` },
      });
      setBezig(false);
      if (error) return setMelding(error.message);
      setMelding("Account aangemaakt. Controleer je e-mail om te bevestigen en log daarna in.");
      setModus("login");
    }
  }

  async function googleLogin() {
    setMelding(null);
    const result = await lovable.auth.signInWithOAuth("google", {
      redirect_uri: window.location.origin,
    });
    if (result.error) {
      setMelding("Inloggen met Google lukte niet. Probeer het opnieuw.");
      return;
    }
    if (result.redirected) return;
    navigate({ to: "/berichten", replace: true });
  }

  return (
    <div className="flex min-h-screen items-center justify-center px-6 py-16 font-body text-ink">
      <div className="w-full max-w-md rounded-[24px] bg-white/60 p-8 ring-1 ring-black/5 backdrop-blur-xl sm:p-10">
        <span className="inline-flex items-center px-3 py-1.5">
          <img src={logo} alt="Zichtbaar Marketing" className="h-7 w-auto" />
        </span>
        <h1 className="mt-6 font-display text-3xl tracking-tight text-brand">
          {modus === "login" ? "Inloggen" : "Account aanmaken"}
        </h1>
        <p className="mt-2 text-sm text-brand/60">Beheeromgeving voor je binnengekomen berichten.</p>

        <form onSubmit={handleSubmit} className="mt-6 grid gap-4">
          <label className="block">
            <span className="text-xs font-semibold uppercase tracking-[0.1em] text-brand/60">
              E-mail
            </span>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="mt-1.5 w-full rounded-[10px] bg-white/70 px-4 py-3 text-sm ring-1 ring-black/5 outline-none focus:ring-2 focus:ring-accent/60"
            />
          </label>
          <label className="block">
            <span className="text-xs font-semibold uppercase tracking-[0.1em] text-brand/60">
              Wachtwoord
            </span>
            <input
              type="password"
              required
              minLength={6}
              value={wachtwoord}
              onChange={(e) => setWachtwoord(e.target.value)}
              className="mt-1.5 w-full rounded-[10px] bg-white/70 px-4 py-3 text-sm ring-1 ring-black/5 outline-none focus:ring-2 focus:ring-accent/60"
            />
          </label>
          <button
            type="submit"
            disabled={bezig}
            className="rounded-[10px] bg-accent px-6 py-3.5 text-sm font-semibold text-accent-foreground transition-colors hover:bg-ink disabled:opacity-60"
          >
            {bezig ? "Even geduld..." : modus === "login" ? "Inloggen" : "Account aanmaken"}
          </button>
        </form>

        <button
          onClick={googleLogin}
          className="mt-3 w-full rounded-[10px] bg-white px-6 py-3.5 text-sm font-semibold text-brand ring-1 ring-black/10 transition-colors hover:bg-white/70"
        >
          Doorgaan met Google
        </button>

        {melding ? <p className="mt-4 text-sm text-accent">{melding}</p> : null}

        <button
          onClick={() => setModus(modus === "login" ? "registreren" : "login")}
          className="mt-6 text-sm text-brand/60 underline underline-offset-4 hover:text-brand"
        >
          {modus === "login" ? "Nog geen account? Maak er een aan" : "Ik heb al een account"}
        </button>
      </div>
    </div>
  );
}
