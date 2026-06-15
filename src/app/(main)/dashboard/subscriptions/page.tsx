"use client";

import * as React from "react";
import Link from "next/link";
import { motion } from "framer-motion";
import { ArrowRight, CalendarClock, Loader2, Pause, Play, RefreshCw, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { apiGet, apiPost } from "@/lib/client";
import { formatPrice } from "@/lib/utils";
import { toast } from "sonner";

interface Subscription {
  id: number;
  subscriptionNumber: string;
  productName: string;
  status: string;
  billingCycle: string;
  billingLabel: string;
  amount: number;
  currency: string;
  nextBillingDate: string | null;
  lastBillingDate: string | null;
  autoRenew: boolean;
  createdAt: string;
}
interface SubscriptionsResponse {
  subscriptions: Subscription[];
  count: number;
}

type Variant = NonNullable<BadgeProps["variant"]>;
const STATUS: Record<string, { label: string; variant: Variant }> = {
  active: { label: "Actif", variant: "success" },
  paused: { label: "Suspendu", variant: "warning" },
  cancelled: { label: "Annulé", variant: "destructive" },
  pending: { label: "En attente", variant: "secondary" },
  expired: { label: "Expiré", variant: "secondary" },
};

export default function SubscriptionsPage() {
  const [subs, setSubs] = React.useState<Subscription[] | null>(null);
  const [busy, setBusy] = React.useState<number | null>(null);

  const load = React.useCallback(async () => {
    const d = await apiGet<SubscriptionsResponse>("/api/v1/subscriptions");
    setSubs(d.subscriptions);
  }, []);

  React.useEffect(() => {
    load().catch(() => setSubs([]));
  }, [load]);

  async function act(id: number, action: "pause" | "resume" | "cancel") {
    setBusy(id);
    try {
      await apiPost(`/api/v1/subscriptions/${id}`, { action });
      toast.success(
        action === "pause" ? "Abonnement suspendu" : action === "resume" ? "Abonnement repris" : "Abonnement annulé",
      );
      await load();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(null);
    }
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold sm:text-3xl">Mes abonnements</h1>
        <p className="mt-1 text-muted-foreground">Gérez vos abonnements récurrents.</p>
      </header>

      {subs === null ? (
        <div className="grid gap-4 sm:grid-cols-2">
          {[0, 1, 2, 3].map((i) => <Skeleton key={i} className="h-48 w-full rounded-2xl" />)}
        </div>
      ) : subs.length === 0 ? (
        <div className="flex flex-col items-center rounded-2xl border border-border bg-card px-4 py-16 text-center">
          <span className="grid h-16 w-16 place-items-center rounded-2xl bg-muted">
            <RefreshCw className="h-7 w-7 text-muted-foreground" />
          </span>
          <h2 className="mt-5 text-lg font-semibold">Aucun abonnement</h2>
          <p className="mt-1 text-muted-foreground">Souscrivez à une offre pour la retrouver ici.</p>
          <Button className="mt-6" asChild>
            <Link href="/products">Voir les produits <ArrowRight /></Link>
          </Button>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {subs.map((sub, i) => {
            const st = STATUS[sub.status] ?? { label: sub.status, variant: "secondary" as Variant };
            const isBusy = busy === sub.id;
            return (
              <motion.div
                key={sub.id}
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.25, delay: Math.min(i * 0.04, 0.3) }}
                className="flex flex-col rounded-2xl border border-border bg-card p-5"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <h3 className="truncate font-semibold">{sub.productName}</h3>
                    <p className="font-mono text-xs text-muted-foreground">{sub.subscriptionNumber}</p>
                  </div>
                  <Badge variant={st.variant}>{st.label}</Badge>
                </div>

                <div className="mt-4 flex items-baseline gap-1">
                  <span className="text-2xl font-bold">{formatPrice(sub.amount, sub.currency)}</span>
                  <span className="text-sm text-muted-foreground">/ {sub.billingLabel.toLowerCase()}</span>
                </div>

                <div className="mt-3 space-y-1.5 text-sm text-muted-foreground">
                  {sub.nextBillingDate && sub.status !== "cancelled" && (
                    <p className="flex items-center gap-2">
                      <CalendarClock className="h-4 w-4" />
                      Prochain prélèvement : {new Date(sub.nextBillingDate).toLocaleDateString("fr-FR")}
                    </p>
                  )}
                  <p className="flex items-center gap-2">
                    <RefreshCw className="h-4 w-4" />
                    Renouvellement automatique : {sub.autoRenew ? "activé" : "désactivé"}
                  </p>
                </div>

                <div className="mt-auto flex flex-wrap gap-2 pt-5">
                  {sub.status === "active" && (
                    <Button variant="secondary" size="sm" onClick={() => act(sub.id, "pause")} disabled={isBusy}>
                      {isBusy ? <Loader2 className="animate-spin" /> : <Pause />} Suspendre
                    </Button>
                  )}
                  {sub.status === "paused" && (
                    <Button variant="secondary" size="sm" onClick={() => act(sub.id, "resume")} disabled={isBusy}>
                      {isBusy ? <Loader2 className="animate-spin" /> : <Play />} Reprendre
                    </Button>
                  )}
                  {sub.status !== "cancelled" && (
                    <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive" onClick={() => act(sub.id, "cancel")} disabled={isBusy}>
                      <X /> Annuler
                    </Button>
                  )}
                </div>
              </motion.div>
            );
          })}
        </div>
      )}
    </div>
  );
}
