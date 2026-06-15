import Link from "next/link";
import { redirect } from "next/navigation";
import {
  ArrowRight,
  Bell,
  Package,
  RefreshCw,
  ShoppingBag,
  Sparkles,
  Wallet,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { StatCard } from "@/components/dashboard/stat-card";
import { getCurrentUser } from "@/lib/auth";
import { getUserOrders } from "@/server/orders";
import { getSubscriptions, getWallet } from "@/server/account";
import { formatPrice } from "@/lib/utils";

export const dynamic = "force-dynamic";

const ORDER_STATUS: Record<string, { label: string; variant: "success" | "warning" | "secondary" | "destructive" | "default" }> = {
  completed: { label: "Terminée", variant: "success" },
  processing: { label: "En traitement", variant: "default" },
  pending: { label: "En attente", variant: "warning" },
  cancelled: { label: "Annulée", variant: "destructive" },
  refunded: { label: "Remboursée", variant: "secondary" },
};

export default async function DashboardOverviewPage() {
  const user = await getCurrentUser();
  if (!user) redirect("/login");

  const [orders, subscriptions, wallet] = await Promise.all([
    getUserOrders(user.id),
    getSubscriptions(user.id),
    getWallet(user.id),
  ]);

  const activeSubs = subscriptions.filter((s) => s.status === "active").length;
  const recentOrders = orders.slice(0, 5);

  return (
    <div className="space-y-8">
      <header>
        <h1 className="text-2xl font-bold sm:text-3xl">Bonjour {user.firstName} 👋</h1>
        <p className="mt-1 text-muted-foreground">Voici un aperçu de votre activité.</p>
      </header>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Commandes" value={orders.length} icon={Package} accent="primary" />
        <StatCard label="Abonnements actifs" value={activeSubs} icon={RefreshCw} accent="chart-4" />
        <StatCard label="Solde portefeuille" value={formatPrice(wallet.balance)} icon={Wallet} accent="success" />
        <StatCard label="Points fidélité" value={user.loyaltyPoints} icon={Sparkles} accent="warning" />
      </div>

      <section className="rounded-2xl border border-border bg-card">
        <div className="flex items-center justify-between border-b border-border p-5">
          <h2 className="font-semibold">Commandes récentes</h2>
          <Button variant="ghost" size="sm" asChild>
            <Link href="/dashboard/orders">Tout voir <ArrowRight /></Link>
          </Button>
        </div>

        {recentOrders.length === 0 ? (
          <div className="flex flex-col items-center px-5 py-12 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-muted">
              <ShoppingBag className="h-6 w-6 text-muted-foreground" />
            </span>
            <p className="mt-4 font-medium">Aucune commande pour le moment</p>
            <p className="mt-1 text-sm text-muted-foreground">Parcourez le catalogue pour passer votre première commande.</p>
            <Button className="mt-5" asChild><Link href="/products">Découvrir la boutique <ArrowRight /></Link></Button>
          </div>
        ) : (
          <ul className="divide-y divide-border">
            {recentOrders.map((o) => {
              const status = ORDER_STATUS[o.status] ?? { label: o.status, variant: "secondary" as const };
              return (
                <li key={o.orderNumber}>
                  <Link
                    href={`/dashboard/orders/${o.orderNumber}`}
                    className="flex items-center gap-4 p-4 transition-colors hover:bg-accent"
                  >
                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                      <Package className="h-5 w-5" />
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="truncate font-medium">{o.firstProduct}</p>
                      <p className="font-mono text-xs text-muted-foreground">{o.orderNumber}</p>
                    </div>
                    <div className="hidden sm:block">
                      <Badge variant={status.variant}>{status.label}</Badge>
                    </div>
                    <span className="shrink-0 font-semibold">{formatPrice(o.total)}</span>
                  </Link>
                </li>
              );
            })}
          </ul>
        )}
      </section>

      <section>
        <h2 className="mb-4 font-semibold">Actions rapides</h2>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <QuickAction href="/products" icon={ShoppingBag} label="Boutique" desc="Parcourir les produits" />
          <QuickAction href="/dashboard/wallet" icon={Wallet} label="Recharger" desc="Créditer mon solde" />
          <QuickAction href="/dashboard/subscriptions" icon={RefreshCw} label="Abonnements" desc="Gérer mes abonnements" />
          <QuickAction href="/dashboard/notifications" icon={Bell} label="Notifications" desc="Voir mes alertes" />
        </div>
      </section>
    </div>
  );
}

function QuickAction({
  href,
  icon: Icon,
  label,
  desc,
}: {
  href: string;
  icon: typeof Package;
  label: string;
  desc: string;
}) {
  return (
    <Link
      href={href}
      className="group flex items-center gap-3 rounded-2xl border border-border bg-card p-4 transition-all hover:border-primary/40 hover:bg-accent"
    >
      <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-primary/20 to-chart-4/20 text-primary transition-transform group-hover:scale-110">
        <Icon className="h-5 w-5" />
      </span>
      <div className="min-w-0">
        <p className="font-medium">{label}</p>
        <p className="truncate text-xs text-muted-foreground">{desc}</p>
      </div>
    </Link>
  );
}
