import Link from "next/link";
import { Sparkles, Github, MessageCircle, ShieldCheck, Zap } from "lucide-react";

const columns = [
  {
    title: "Boutique",
    links: [
      { label: "Tous les produits", href: "/products" },
      { label: "Gaming", href: "/products?category=gaming" },
      { label: "Streaming", href: "/products?category=streaming" },
      { label: "Productivité", href: "/products?category=productivite" },
    ],
  },
  {
    title: "Compte",
    links: [
      { label: "Tableau de bord", href: "/dashboard" },
      { label: "Mes commandes", href: "/dashboard/orders" },
      { label: "Portefeuille", href: "/dashboard/wallet" },
      { label: "Connexion", href: "/login" },
    ],
  },
  {
    title: "Ressources",
    links: [
      { label: "Documentation API", href: "/api-docs" },
      { label: "Support", href: "/support" },
      { label: "À propos", href: "/about" },
    ],
  },
  {
    title: "Légal",
    links: [
      { label: "CGU", href: "/terms" },
      { label: "Confidentialité", href: "/privacy" },
      { label: "Remboursements", href: "/refunds" },
      { label: "Mentions légales", href: "/legal" },
    ],
  },
];

export function Footer() {
  return (
    <footer className="mt-24 border-t border-border/60">
      <div className="mx-auto max-w-7xl px-4 py-14 lg:px-6">
        <div className="grid gap-10 md:grid-cols-2 lg:grid-cols-6">
          <div className="lg:col-span-2">
            <Link href="/" className="flex items-center gap-2 font-semibold">
              <span className="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-primary to-chart-4 text-primary-foreground">
                <Sparkles className="h-5 w-5" />
              </span>
              <span className="text-lg">Pilote<span className="text-primary">Shopping</span></span>
            </Link>
            <p className="mt-4 max-w-xs text-sm text-muted-foreground">
              La marketplace de produits numériques premium : licences, comptes et abonnements livrés instantanément.
            </p>
            <div className="mt-5 flex items-center gap-3">
              <a href="https://discord.gg" target="_blank" rel="noreferrer" className="grid h-9 w-9 place-items-center rounded-lg border border-border text-muted-foreground transition-colors hover:border-primary hover:text-primary">
                <MessageCircle className="h-4 w-4" />
              </a>
              <a href="https://github.com/PiloteCode" target="_blank" rel="noreferrer" className="grid h-9 w-9 place-items-center rounded-lg border border-border text-muted-foreground transition-colors hover:border-primary hover:text-primary">
                <Github className="h-4 w-4" />
              </a>
            </div>
          </div>

          {columns.map((col) => (
            <div key={col.title}>
              <h4 className="text-sm font-semibold">{col.title}</h4>
              <ul className="mt-4 space-y-2.5">
                {col.links.map((l) => (
                  <li key={l.href}>
                    <Link href={l.href} className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                      {l.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-12 flex flex-col items-center justify-between gap-4 border-t border-border/60 pt-6 sm:flex-row">
          <p className="text-sm text-muted-foreground">© {new Date().getFullYear()} Pilote Shopping. Tous droits réservés.</p>
          <div className="flex items-center gap-5 text-xs text-muted-foreground">
            <span className="flex items-center gap-1.5"><ShieldCheck className="h-4 w-4 text-success" /> Paiement sécurisé</span>
            <span className="flex items-center gap-1.5"><Zap className="h-4 w-4 text-primary" /> Livraison instantanée</span>
          </div>
        </div>
      </div>
    </footer>
  );
}
