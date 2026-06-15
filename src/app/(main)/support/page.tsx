import Link from "next/link";
import {
  ArrowRight,
  ChevronDown,
  Clock,
  CreditCard,
  LifeBuoy,
  Mail,
  MessageCircle,
  PackageCheck,
  RotateCcw,
  ShieldCheck,
  Sparkles,
  Zap,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Reveal } from "@/components/reveal";

export const metadata = { title: "Support · Pilote Shopping" };

const faqs = [
  {
    icon: Zap,
    q: "Quel est le délai de livraison ?",
    a: "La livraison est automatique et instantanée. Dès la confirmation du paiement, vos clés et accès apparaissent dans votre espace client, et un récapitulatif vous est envoyé par e-mail. Dans de rares cas, une vérification manuelle peut prendre quelques minutes.",
  },
  {
    icon: CreditCard,
    q: "Quels moyens de paiement acceptez-vous ?",
    a: "Vous pouvez régler par carte bancaire, portefeuille électronique ou directement avec le solde de votre compte Pilote Shopping. Toutes les transactions sont chiffrées et sécurisées de bout en bout.",
  },
  {
    icon: RotateCcw,
    q: "Puis-je être remboursé ?",
    a: "Les produits numériques sont remboursables uniquement dans les cas éligibles : clé défectueuse, invalide ou produit non conforme à sa description. Une clé déjà activée ne peut pas être remboursée. Consultez notre politique de remboursement pour le détail.",
  },
  {
    icon: ShieldCheck,
    q: "Mes achats sont-ils garantis ?",
    a: "Oui. Chaque produit est vérifié avant mise en vente et bénéficie de notre garantie de conformité. En cas de problème à l'activation, notre support remplace la clé ou procède au remboursement selon le cas.",
  },
  {
    icon: PackageCheck,
    q: "Où retrouver mes clés et accès ?",
    a: "Tous vos produits sont disponibles à tout moment depuis votre tableau de bord, dans la rubrique « Mes commandes ». Vous pouvez les consulter, les copier et les réutiliser autant que nécessaire.",
  },
  {
    icon: Clock,
    q: "Sous quel délai le support répond-il ?",
    a: "Notre équipe est disponible 7j/7. Sur Discord, la plupart des demandes trouvent une réponse en moins d'une heure. Par e-mail, nous répondons généralement sous 24 heures.",
  },
];

export default function SupportPage() {
  return (
    <div>
      {/* Hero */}
      <section className="relative overflow-hidden">
        <div className="card-grid-bg absolute inset-0 opacity-40" />
        <div className="mx-auto max-w-7xl px-4 py-20 lg:px-6 lg:py-24">
          <Reveal className="mx-auto max-w-3xl text-center">
            <span className="inline-flex items-center gap-2 rounded-full border border-border bg-card/50 px-4 py-1.5 text-sm text-muted-foreground backdrop-blur">
              <LifeBuoy className="h-4 w-4 text-primary" /> Centre d&apos;aide
            </span>
            <h1 className="mt-6 text-balance text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
              Besoin d&apos;<span className="text-gradient">aide</span> ?
            </h1>
            <p className="mx-auto mt-5 max-w-2xl text-balance text-lg text-muted-foreground">
              Notre équipe est là pour vous, 7j/7. Rejoignez notre communauté Discord pour une réponse rapide, ou
              écrivez-nous par e-mail. On adore vous aider.
            </p>
          </Reveal>
        </div>
      </section>

      {/* Contact channels */}
      <section className="mx-auto max-w-7xl px-4 lg:px-6">
        <div className="grid gap-5 lg:grid-cols-2">
          <Reveal>
            <div className="glow-primary group flex h-full flex-col rounded-2xl border border-border bg-gradient-to-br from-primary/15 via-card to-chart-4/10 p-8">
              <span className="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-primary to-chart-4 text-primary-foreground">
                <MessageCircle className="h-7 w-7" />
              </span>
              <h2 className="mt-5 text-xl font-bold">Rejoindre le Discord</h2>
              <p className="mt-2 flex-1 text-muted-foreground">
                Le moyen le plus rapide d&apos;obtenir de l&apos;aide. Posez vos questions, suivez les nouveautés et
                échangez avec la communauté et notre équipe support en direct.
              </p>
              <Button size="lg" className="mt-6 w-full sm:w-fit" asChild>
                <a href="https://discord.gg" target="_blank" rel="noreferrer">
                  <MessageCircle /> Rejoindre le Discord
                </a>
              </Button>
            </div>
          </Reveal>

          <Reveal delay={0.1}>
            <div className="group flex h-full flex-col rounded-2xl border border-border bg-card p-8 transition-colors hover:border-primary/40">
              <span className="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-primary/20 to-chart-4/20 text-primary">
                <Mail className="h-7 w-7" />
              </span>
              <h2 className="mt-5 text-xl font-bold">Support par e-mail</h2>
              <p className="mt-2 flex-1 text-muted-foreground">
                Une question détaillée ou un souci sur une commande ? Écrivez-nous : nous répondons généralement sous
                24 heures, du lundi au dimanche.
              </p>
              <Button size="lg" variant="outline" className="mt-6 w-full sm:w-fit" asChild>
                <a href="mailto:support@piloteshopping.com">
                  <Mail /> support@piloteshopping.com
                </a>
              </Button>
            </div>
          </Reveal>
        </div>
      </section>

      {/* FAQ */}
      <section className="mx-auto max-w-3xl px-4 py-20 lg:px-6">
        <Reveal className="mb-10 text-center">
          <span className="inline-flex items-center gap-2 rounded-full border border-border bg-card/50 px-4 py-1.5 text-sm text-muted-foreground backdrop-blur">
            <Sparkles className="h-4 w-4 text-primary" /> FAQ
          </span>
          <h2 className="mt-5 text-balance text-3xl font-bold sm:text-4xl">Questions fréquentes</h2>
          <p className="mt-3 text-muted-foreground">Les réponses aux questions que l&apos;on nous pose le plus souvent.</p>
        </Reveal>

        <Reveal className="space-y-3">
          {faqs.map((f) => (
            <details
              key={f.q}
              className="group rounded-2xl border border-border bg-card transition-colors hover:border-primary/40 open:border-primary/40"
            >
              <summary className="flex cursor-pointer list-none items-center gap-4 p-5 font-medium [&::-webkit-details-marker]:hidden">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-primary/15 to-chart-4/15 text-primary">
                  <f.icon className="h-5 w-5" />
                </span>
                <span className="flex-1">{f.q}</span>
                <ChevronDown className="h-5 w-5 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
              </summary>
              <p className="px-5 pb-5 pl-14 text-muted-foreground">{f.a}</p>
            </details>
          ))}
        </Reveal>
      </section>

      {/* CTA */}
      <section className="mx-auto max-w-7xl px-4 pb-16 lg:px-6">
        <Reveal className="rounded-3xl border border-border bg-card/40 p-10 text-center lg:p-14">
          <h2 className="text-balance text-2xl font-bold sm:text-3xl">Vous n&apos;avez pas trouvé votre réponse ?</h2>
          <p className="mx-auto mt-3 max-w-xl text-muted-foreground">
            Notre équipe se fera un plaisir de vous accompagner. N&apos;hésitez pas à nous solliciter.
          </p>
          <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            <Button size="lg" asChild>
              <a href="https://discord.gg" target="_blank" rel="noreferrer">
                <MessageCircle /> Rejoindre le Discord
              </a>
            </Button>
            <Button size="lg" variant="outline" asChild>
              <Link href="/products">
                Voir les produits <ArrowRight />
              </Link>
            </Button>
          </div>
        </Reveal>
      </section>
    </div>
  );
}
