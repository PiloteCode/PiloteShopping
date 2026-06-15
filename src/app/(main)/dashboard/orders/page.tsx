"use client";

import * as React from "react";
import Link from "next/link";
import { motion } from "framer-motion";
import { ArrowRight, ChevronRight, Package, Search, ShoppingBag } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { OrderStatusBadge } from "@/components/dashboard/status-badge";
import { apiGet } from "@/lib/client";
import { formatPrice } from "@/lib/utils";

interface OrderRow {
  orderNumber: string;
  status: string;
  paymentStatus: string;
  paymentMethod: string;
  total: number;
  itemCount: number;
  firstProduct: string;
  createdAt: string;
}
interface OrdersResponse {
  orders: OrderRow[];
  count: number;
}

export default function OrdersPage() {
  const [orders, setOrders] = React.useState<OrderRow[] | null>(null);
  const [query, setQuery] = React.useState("");

  React.useEffect(() => {
    apiGet<OrdersResponse>("/api/v1/orders")
      .then((d) => setOrders(d.orders))
      .catch(() => setOrders([]));
  }, []);

  const filtered = React.useMemo(() => {
    if (!orders) return [];
    const q = query.trim().toLowerCase();
    if (!q) return orders;
    return orders.filter(
      (o) => o.orderNumber.toLowerCase().includes(q) || o.firstProduct.toLowerCase().includes(q),
    );
  }, [orders, query]);

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold sm:text-3xl">Mes commandes</h1>
        <p className="mt-1 text-muted-foreground">Suivez et consultez l&apos;historique de vos commandes.</p>
      </header>

      {orders === null ? (
        <OrdersSkeleton />
      ) : orders.length === 0 ? (
        <EmptyState />
      ) : (
        <>
          <div className="relative">
            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Rechercher par numéro ou produit…"
              className="pl-10"
              aria-label="Rechercher une commande"
            />
          </div>

          {filtered.length === 0 ? (
            <div className="rounded-2xl border border-border bg-card p-10 text-center text-muted-foreground">
              Aucune commande ne correspond à « {query} ».
            </div>
          ) : (
            <ul className="space-y-3">
              {filtered.map((o, i) => (
                <motion.li
                  key={o.orderNumber}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.25, delay: Math.min(i * 0.03, 0.25) }}
                >
                  <Link
                    href={`/dashboard/orders/${o.orderNumber}`}
                    className="group flex items-center gap-4 rounded-2xl border border-border bg-card p-4 transition-all hover:border-primary/40 hover:bg-accent"
                  >
                    <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                      <Package className="h-5 w-5" />
                    </span>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-sm font-semibold">{o.orderNumber}</span>
                        <OrderStatusBadge status={o.status} />
                      </div>
                      <p className="mt-0.5 truncate text-sm text-muted-foreground">
                        {o.firstProduct}
                        {o.itemCount > 1 ? ` + ${o.itemCount - 1} autre${o.itemCount - 1 > 1 ? "s" : ""}` : ""}
                        {" · "}
                        {new Date(o.createdAt).toLocaleDateString("fr-FR")}
                      </p>
                    </div>
                    <span className="shrink-0 font-semibold">{formatPrice(o.total)}</span>
                    <ChevronRight className="hidden h-5 w-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 sm:block" />
                  </Link>
                </motion.li>
              ))}
            </ul>
          )}
        </>
      )}
    </div>
  );
}

function EmptyState() {
  return (
    <div className="flex flex-col items-center rounded-2xl border border-border bg-card px-4 py-16 text-center">
      <span className="grid h-16 w-16 place-items-center rounded-2xl bg-muted">
        <ShoppingBag className="h-7 w-7 text-muted-foreground" />
      </span>
      <h2 className="mt-5 text-lg font-semibold">Aucune commande</h2>
      <p className="mt-1 text-muted-foreground">Vous n&apos;avez pas encore passé de commande.</p>
      <Button className="mt-6" asChild>
        <Link href="/products">Découvrir la boutique <ArrowRight /></Link>
      </Button>
    </div>
  );
}

function OrdersSkeleton() {
  return (
    <div className="space-y-3">
      <Skeleton className="h-10 w-full rounded-lg" />
      {[0, 1, 2, 3].map((i) => (
        <Skeleton key={i} className="h-[72px] w-full rounded-2xl" />
      ))}
    </div>
  );
}
