import Link from "next/link";
import {
  ArrowRight,
  BookOpen,
  CreditCard,
  Headphones,
  PackageCheck,
  ShieldCheck,
  Sparkles,
  Zap,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Reveal } from "@/components/reveal";
import { ProductCard } from "@/components/product-card";
import { DynamicIcon } from "@/components/icon";
import { getCategories, getFeaturedProducts } from "@/server/products";

export const dynamic = "force-dynamic";

export default async function HomePage() {
  const [featured, categories] = await Promise.all([getFeaturedProducts(8), getCategories()]);

  return (
    <div>
      {/* Hero */}
      <section className="relative overflow-hidden">
        <div className="card-grid-bg absolute inset-0 opacity-40" />
        <div className="mx-auto max-w-7xl px-4 py-20 lg:px-6 lg:py-28">
          <Reveal className="mx-auto max-w-3xl text-center">
            <span className="inline-flex items-center gap-2 rounded-full border border-border bg-card/50 px-4 py-1.5 text-sm text-muted-foreground backdrop-blur">
              <Sparkles className="h-4 w-4 text-primary" /> Produits numériques premium, livrés en quelques secondes
            </span>
            <h1 className="mt-6 text-balance text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
              La marketplace des <span className="text-gradient">licences & abonnements</span> au meilleur prix
            </h1>
            <p className="mx-auto mt-5 max-w-2xl text-balance text-lg text-muted-foreground">
              Clés de licence, comptes premium, accès API et abonnements. Paiement sécurisé,
              livraison automatique, et un support réactif. Sans engagement.
            </p>
            <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
              <Button size="lg" asChild>
                <Link href="/products">Explorer la boutique <ArrowRight /></Link>
              </Button>
              <Button size="lg" variant="glass" asChild>
                <Link href="/api-docs"><BookOpen /> Documentation API</Link>
              </Button>
            </div>
            <div className="mt-10 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-muted-foreground">
              <span className="flex items-center gap-2"><Zap className="h-4 w-4 text-primary" /> Livraison instantanée</span>
              <span className="flex items-center gap-2"><ShieldCheck className="h-4 w-4 text-success" /> Paiement sécurisé</span>
              <span className="flex items-center gap-2"><Headphones className="h-4 w-4 text-chart-4" /> Support 7j/7</span>
            </div>
          </Reveal>
        </div>
      </section>

      {/* Categories */}
      <section className="mx-auto max-w-7xl px-4 lg:px-6">
        <Reveal className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          {categories.map((c) => (
            <Link
              key={c.slug}
              href={`/products?category=${c.slug}`}
              className="group flex flex-col items-center gap-2 rounded-2xl border border-border bg-card p-5 text-center transition-all hover:border-primary/40 hover:bg-accent"
            >
              <span
                className="grid h-12 w-12 place-items-center rounded-xl transition-transform group-hover:scale-110"
                style={{ backgroundColor: (c.color ?? "#0ea5e9") + "22", color: c.color ?? "#0ea5e9" }}
              >
                <DynamicIcon name={c.icon} className="h-6 w-6" />
              </span>
              <span className="text-sm font-medium">{c.name}</span>
            </Link>
          ))}
        </Reveal>
      </section>

      {/* Featured */}
      <section className="mx-auto max-w-7xl px-4 py-16 lg:px-6">
        <div className="mb-8 flex items-end justify-between">
          <div>
            <h2 className="text-2xl font-bold sm:text-3xl">Produits populaires</h2>
            <p className="mt-1 text-muted-foreground">Les plus appréciés par notre communauté</p>
          </div>
          <Button variant="ghost" asChild className="hidden sm:inline-flex">
            <Link href="/products">Tout voir <ArrowRight /></Link>
          </Button>
        </div>
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {featured.map((p, i) => (
            <ProductCard key={p.id} product={p} index={i} />
          ))}
        </div>
      </section>

      {/* How it works */}
      <section className="mx-auto max-w-7xl px-4 py-12 lg:px-6">
        <Reveal className="rounded-3xl border border-border bg-card/40 p-8 lg:p-12">
          <h2 className="text-center text-2xl font-bold sm:text-3xl">Comment ça marche</h2>
          <div className="mt-10 grid gap-8 md:grid-cols-3">
            {[
              { icon: PackageCheck, title: "1. Choisissez", text: "Parcourez le catalogue et sélectionnez l'offre adaptée à vos besoins." },
              { icon: CreditCard, title: "2. Payez en sécurité", text: "Réglez par carte, portefeuille ou virement. Transactions chiffrées." },
              { icon: Zap, title: "3. Recevez instantanément", text: "Vos clés et accès sont livrés automatiquement dans votre espace." },
            ].map((s) => (
              <div key={s.title} className="flex flex-col items-center text-center">
                <span className="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-primary/20 to-chart-4/20 text-primary">
                  <s.icon className="h-7 w-7" />
                </span>
                <h3 className="mt-4 font-semibold">{s.title}</h3>
                <p className="mt-1.5 text-sm text-muted-foreground">{s.text}</p>
              </div>
            ))}
          </div>
        </Reveal>
      </section>

      {/* Final CTA */}
      <section className="mx-auto max-w-7xl px-4 py-12 lg:px-6">
        <Reveal className="glow-primary relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary/15 via-card to-chart-4/10 p-10 text-center lg:p-16">
          <h2 className="text-balance text-3xl font-bold sm:text-4xl">Prêt à commencer ?</h2>
          <p className="mx-auto mt-3 max-w-xl text-muted-foreground">
            Créez un compte gratuitement et profitez de -15% sur votre première commande avec le code <span className="font-mono font-semibold text-primary">BIENVENUE15</span>.
          </p>
          <div className="mt-7 flex justify-center gap-3">
            <Button size="lg" asChild><Link href="/register">Créer un compte</Link></Button>
            <Button size="lg" variant="outline" asChild><Link href="/products">Voir les produits</Link></Button>
          </div>
        </Reveal>
      </section>
    </div>
  );
}
