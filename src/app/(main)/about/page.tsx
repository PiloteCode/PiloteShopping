import Link from "next/link";
import {
  ArrowRight,
  Award,
  Clock,
  HeartHandshake,
  Heart,
  Headphones,
  Rocket,
  ShieldCheck,
  Sparkles,
  Target,
  TrendingDown,
  Users,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Reveal } from "@/components/reveal";

export const metadata = { title: "À propos · Pilote Shopping" };

const stats = [
  { icon: Award, value: "3+ ans", label: "d'expertise e-commerce" },
  { icon: TrendingDown, value: "jusqu'à -70%", label: "d'économies sur vos licences" },
  { icon: Users, value: "10k+", label: "clients satisfaits" },
];

const values = [
  {
    icon: HeartHandshake,
    title: "Confiance",
    text: "Des produits vérifiés, des prix transparents et aucun frais caché. Ce que vous voyez est ce que vous payez.",
    color: "text-primary",
  },
  {
    icon: Rocket,
    title: "Rapidité",
    text: "Livraison automatique de vos clés et accès en quelques secondes, à toute heure du jour comme de la nuit.",
    color: "text-chart-4",
  },
  {
    icon: ShieldCheck,
    title: "Sécurité",
    text: "Paiements chiffrés de bout en bout et protection systématique de vos données personnelles.",
    color: "text-success",
  },
  {
    icon: Headphones,
    title: "Support",
    text: "Une équipe réactive 7j/7 sur Discord et par e-mail pour vous accompagner avant et après l'achat.",
    color: "text-chart-3",
  },
];

export default function AboutPage() {
  return (
    <div>
      {/* Hero */}
      <section className="relative overflow-hidden">
        <div className="card-grid-bg absolute inset-0 opacity-40" />
        <div className="mx-auto max-w-7xl px-4 py-20 lg:px-6 lg:py-28">
          <Reveal className="mx-auto max-w-3xl text-center">
            <span className="inline-flex items-center gap-2 rounded-full border border-border bg-card/50 px-4 py-1.5 text-sm text-muted-foreground backdrop-blur">
              <Sparkles className="h-4 w-4 text-primary" /> Notre histoire
            </span>
            <h1 className="mt-6 text-balance text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
              Le numérique premium, <span className="text-gradient">accessible à tous</span>
            </h1>
            <p className="mx-auto mt-5 max-w-2xl text-balance text-lg text-muted-foreground">
              Pilote Shopping est née d&apos;une conviction simple : licences, comptes et abonnements premium ne
              devraient pas coûter une fortune. Nous rendons le meilleur du numérique accessible, instantané et
              sécurisé.
            </p>
            <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
              <Button size="lg" asChild>
                <Link href="/products">
                  Découvrir la boutique <ArrowRight />
                </Link>
              </Button>
              <Button size="lg" variant="glass" asChild>
                <Link href="/support">
                  <Headphones /> Nous contacter
                </Link>
              </Button>
            </div>
          </Reveal>
        </div>
      </section>

      {/* Stats */}
      <section className="mx-auto max-w-7xl px-4 lg:px-6">
        <Reveal className="grid gap-5 sm:grid-cols-3">
          {stats.map((s) => (
            <div
              key={s.label}
              className="flex flex-col items-center rounded-2xl border border-border bg-card p-8 text-center"
            >
              <span className="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-primary/20 to-chart-4/20 text-primary">
                <s.icon className="h-7 w-7" />
              </span>
              <span className="mt-4 text-3xl font-bold text-gradient">{s.value}</span>
              <span className="mt-1 text-sm text-muted-foreground">{s.label}</span>
            </div>
          ))}
        </Reveal>
      </section>

      {/* Mission */}
      <section className="mx-auto max-w-7xl px-4 py-20 lg:px-6">
        <div className="grid items-center gap-12 lg:grid-cols-2">
          <Reveal>
            <span className="inline-flex items-center gap-2 text-sm font-medium text-primary">
              <Target className="h-4 w-4" /> Notre mission
            </span>
            <h2 className="mt-4 text-balance text-3xl font-bold sm:text-4xl">
              Démocratiser l&apos;accès aux produits numériques premium
            </h2>
            <div className="mt-5 space-y-4 text-muted-foreground">
              <p className="leading-relaxed">
                Trop souvent, les meilleures licences et abonnements restent hors de portée à cause de tarifs élevés
                ou d&apos;un parcours d&apos;achat complexe. Nous avons bâti Pilote Shopping pour casser ces
                barrières.
              </p>
              <p className="leading-relaxed">
                Notre marketplace réunit une sélection rigoureuse de produits numériques, négociés au meilleur prix et
                livrés instantanément. Pas d&apos;intermédiaire inutile, pas de mauvaise surprise : juste le bon
                produit, au bon prix, immédiatement disponible.
              </p>
            </div>
          </Reveal>

          <Reveal delay={0.1}>
            <div className="glow-primary relative overflow-hidden rounded-3xl border border-border bg-gradient-to-br from-primary/15 via-card to-chart-4/10 p-8 lg:p-10">
              <div className="flex items-center gap-4">
                <span className="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-primary to-chart-4 text-2xl font-bold text-primary-foreground">
                  EB
                </span>
                <div>
                  <p className="text-lg font-semibold">Ewan Beraud</p>
                  <p className="text-sm text-muted-foreground">Fondateur · Expert e-commerce</p>
                </div>
              </div>
              <p className="mt-6 text-balance leading-relaxed text-muted-foreground">
                « J&apos;ai créé Pilote Shopping pour offrir une expérience d&apos;achat numérique honnête,
                rapide et abordable. Chaque produit est sélectionné comme si je l&apos;achetais moi-même. »
              </p>
              <div className="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-muted-foreground">
                <span className="flex items-center gap-2">
                  <Heart className="h-4 w-4 text-chart-5" /> Passionné de tech
                </span>
                <span className="flex items-center gap-2">
                  <Clock className="h-4 w-4 text-primary" /> Au service des clients
                </span>
              </div>
            </div>
          </Reveal>
        </div>
      </section>

      {/* Values */}
      <section className="mx-auto max-w-7xl px-4 pb-8 lg:px-6">
        <Reveal className="mb-10 text-center">
          <h2 className="text-balance text-3xl font-bold sm:text-4xl">Nos valeurs</h2>
          <p className="mx-auto mt-3 max-w-xl text-muted-foreground">
            Quatre principes qui guident chacune de nos décisions et chacune de vos commandes.
          </p>
        </Reveal>
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {values.map((v, i) => (
            <Reveal key={v.title} delay={i * 0.08}>
              <div className="group h-full rounded-2xl border border-border bg-card p-6 transition-all hover:border-primary/40 hover:bg-accent">
                <span className="grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-primary/15 to-chart-4/15 transition-transform group-hover:scale-110">
                  <v.icon className={`h-6 w-6 ${v.color}`} />
                </span>
                <h3 className="mt-4 font-semibold">{v.title}</h3>
                <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">{v.text}</p>
              </div>
            </Reveal>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="mx-auto max-w-7xl px-4 py-16 lg:px-6">
        <Reveal className="glow-primary relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary/15 via-card to-chart-4/10 p-10 text-center lg:p-16">
          <h2 className="text-balance text-3xl font-bold sm:text-4xl">Rejoignez l&apos;aventure</h2>
          <p className="mx-auto mt-3 max-w-xl text-muted-foreground">
            Des milliers de clients nous font déjà confiance. Découvrez nos produits et profitez du numérique premium
            au meilleur prix.
          </p>
          <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            <Button size="lg" asChild>
              <Link href="/products">
                Explorer la boutique <ArrowRight />
              </Link>
            </Button>
            <Button size="lg" variant="outline" asChild>
              <Link href="/register">Créer un compte</Link>
            </Button>
          </div>
        </Reveal>
      </section>
    </div>
  );
}
