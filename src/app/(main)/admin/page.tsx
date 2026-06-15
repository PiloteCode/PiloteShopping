"use client";

import * as React from "react";
import Link from "next/link";
import {
  Box,
  CheckCircle2,
  Euro,
  Package,
  Repeat,
  ShoppingCart,
  TrendingUp,
  Users,
} from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";
import { StatCard } from "@/components/admin/stat-card";
import { OrderStatusBadge } from "@/components/admin/status-badge";
import { apiGet } from "@/lib/client";
import { formatPrice, timeAgo } from "@/lib/utils";

interface RecentOrder {
  orderNumber: string;
  total: number;
  status: string;
  paymentStatus: string;
  email: string;
  createdAt: string;
}
interface TopProduct {
  name: string;
  slug: string;
  saleCount: number;
}
interface Stats {
  users: { total: number; verified: number };
  orders: { total: number; paid: number };
  revenue: { total: number; last7Days: number };
  products: { total: number; active: number };
  subscriptions: { active: number };
  recentOrders: RecentOrder[];
  topProducts: TopProduct[];
}

export default function AdminDashboardPage() {
  const [stats, setStats] = React.useState<Stats | null>(null);

  React.useEffect(() => {
    apiGet<Stats>("/api/v1/admin/stats")
      .then(setStats)
      .catch(() => setStats(null));
  }, []);

  if (!stats) return <DashboardSkeleton />;

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-bold">Tableau de bord</h1>
        <p className="mt-1 text-muted-foreground">Vue d&apos;ensemble de votre boutique</p>
      </div>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Revenu total" value={formatPrice(stats.revenue.total)} icon={Euro} hint={`${stats.orders.paid} commande${stats.orders.paid > 1 ? "s" : ""} payée${stats.orders.paid > 1 ? "s" : ""}`} />
        <StatCard label="Revenu 7 jours" value={formatPrice(stats.revenue.last7Days)} icon={TrendingUp} hint="7 derniers jours" iconClassName="bg-success/15 text-success" />
        <StatCard label="Commandes" value={String(stats.orders.total)} icon={ShoppingCart} hint={`${stats.orders.paid} payées`} iconClassName="bg-chart-4/15 text-chart-4" />
        <StatCard label="Utilisateurs" value={String(stats.users.total)} icon={Users} hint={`${stats.users.verified} vérifiés`} iconClassName="bg-chart-2/15 text-chart-2" />
        <StatCard label="Produits actifs" value={String(stats.products.active)} icon={Package} hint={`${stats.products.total} au total`} />
        <StatCard label="Abonnements actifs" value={String(stats.subscriptions.active)} icon={Repeat} iconClassName="bg-chart-5/15 text-chart-5" />
        <StatCard label="Comptes vérifiés" value={String(stats.users.verified)} icon={CheckCircle2} hint={`sur ${stats.users.total}`} iconClassName="bg-success/15 text-success" />
        <StatCard label="Catalogue" value={String(stats.products.total)} icon={Box} hint="produits référencés" iconClassName="bg-chart-1/15 text-chart-1" />
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="rounded-2xl border border-border bg-card lg:col-span-2">
          <div className="border-b border-border px-6 py-4">
            <h2 className="font-semibold">Commandes récentes</h2>
          </div>
          {stats.recentOrders.length === 0 ? (
            <p className="px-6 py-10 text-center text-sm text-muted-foreground">Aucune commande</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <th className="px-6 py-3 font-medium">Commande</th>
                    <th className="px-6 py-3 font-medium">Client</th>
                    <th className="px-6 py-3 font-medium">Statut</th>
                    <th className="px-6 py-3 text-right font-medium">Total</th>
                    <th className="px-6 py-3 text-right font-medium">Date</th>
                  </tr>
                </thead>
                <tbody>
                  {stats.recentOrders.map((o) => (
                    <tr key={o.orderNumber} className="border-b border-border/60 last:border-0 hover:bg-accent/40">
                      <td className="px-6 py-3 font-mono text-xs font-medium">{o.orderNumber}</td>
                      <td className="max-w-[12rem] truncate px-6 py-3 text-muted-foreground">{o.email}</td>
                      <td className="px-6 py-3"><OrderStatusBadge status={o.status} /></td>
                      <td className="px-6 py-3 text-right font-semibold">{formatPrice(o.total)}</td>
                      <td className="px-6 py-3 text-right text-xs text-muted-foreground">{timeAgo(o.createdAt)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <div className="rounded-2xl border border-border bg-card">
          <div className="border-b border-border px-6 py-4">
            <h2 className="font-semibold">Top produits</h2>
          </div>
          {stats.topProducts.length === 0 ? (
            <p className="px-6 py-10 text-center text-sm text-muted-foreground">Aucun produit</p>
          ) : (
            <ul className="divide-y divide-border/60">
              {stats.topProducts.map((p, i) => (
                <li key={p.slug} className="flex items-center gap-3 px-6 py-3">
                  <span className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-muted text-xs font-semibold text-muted-foreground">{i + 1}</span>
                  <Link href={`/products/${p.slug}`} className="min-w-0 flex-1 truncate text-sm font-medium hover:text-primary">{p.name}</Link>
                  <span className="shrink-0 text-xs text-muted-foreground">{p.saleCount} vente{p.saleCount > 1 ? "s" : ""}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  );
}

function DashboardSkeleton() {
  return (
    <div className="space-y-8">
      <div>
        <Skeleton className="h-9 w-56" />
        <Skeleton className="mt-2 h-4 w-72" />
      </div>
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {Array.from({ length: 8 }).map((_, i) => (
          <Skeleton key={i} className="h-28 w-full rounded-2xl" />
        ))}
      </div>
      <div className="grid gap-6 lg:grid-cols-3">
        <Skeleton className="h-80 w-full rounded-2xl lg:col-span-2" />
        <Skeleton className="h-80 w-full rounded-2xl" />
      </div>
    </div>
  );
}
