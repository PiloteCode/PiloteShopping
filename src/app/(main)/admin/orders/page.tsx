"use client";

import * as React from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { AdminSelect } from "@/components/admin/admin-select";
import {
  OrderStatusBadge,
  PaymentStatusBadge,
  ORDER_STATUS_OPTIONS,
  PAYMENT_STATUS_OPTIONS,
} from "@/components/admin/status-badge";
import { apiGet, apiPatch } from "@/lib/client";
import { cn, formatPrice, timeAgo } from "@/lib/utils";
import { toast } from "sonner";

interface Order {
  id: number;
  orderNumber: string;
  status: string;
  paymentStatus: string;
  paymentMethod: string | null;
  total: number;
  createdAt: string;
  email: string;
}

const FILTERS = [{ value: "all", label: "Toutes" }, ...ORDER_STATUS_OPTIONS];

export default function AdminOrdersPage() {
  const [orders, setOrders] = React.useState<Order[] | null>(null);
  const [filter, setFilter] = React.useState("all");
  const [busy, setBusy] = React.useState<number | null>(null);

  const load = React.useCallback(async () => {
    const data = await apiGet<{ orders: Order[] }>("/api/v1/admin/orders");
    setOrders(data.orders);
  }, []);

  React.useEffect(() => {
    load().catch((e) => {
      setOrders([]);
      toast.error((e as Error).message);
    });
  }, [load]);

  async function patch(o: Order, body: { status?: string; paymentStatus?: string }) {
    setBusy(o.id);
    try {
      await apiPatch(`/api/v1/admin/orders/${o.id}`, body);
      await load();
      toast.success("Commande mise à jour");
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(null);
    }
  }

  const filtered = orders?.filter((o) => filter === "all" || o.status === filter) ?? [];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Commandes</h1>
        <p className="mt-1 text-muted-foreground">
          {orders ? `${orders.length} commande${orders.length > 1 ? "s" : ""}` : "Suivi des commandes"}
        </p>
      </div>

      <div className="flex flex-wrap gap-2">
        {FILTERS.map((f) => (
          <button
            key={f.value}
            onClick={() => setFilter(f.value)}
            className={cn(
              "rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors",
              filter === f.value
                ? "border-primary/40 bg-primary/10 text-primary"
                : "border-border text-muted-foreground hover:bg-accent hover:text-foreground",
            )}
          >
            {f.label}
          </button>
        ))}
      </div>

      {!orders ? (
        <Skeleton className="h-96 w-full rounded-2xl" />
      ) : filtered.length === 0 ? (
        <div className="rounded-2xl border border-border bg-card px-6 py-16 text-center text-muted-foreground">
          Aucune commande {filter !== "all" ? "pour ce statut" : ""}.
        </div>
      ) : (
        <div className="overflow-x-auto rounded-2xl border border-border bg-card">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                <th className="px-5 py-3 font-medium">Commande</th>
                <th className="px-5 py-3 font-medium">Client</th>
                <th className="px-5 py-3 text-right font-medium">Total</th>
                <th className="px-5 py-3 font-medium">Statut</th>
                <th className="px-5 py-3 font-medium">Paiement</th>
                <th className="px-5 py-3 text-right font-medium">Date</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((o) => (
                <tr key={o.id} className={cn("border-b border-border/60 last:border-0 hover:bg-accent/40", busy === o.id && "opacity-60")}>
                  <td className="px-5 py-3 font-mono text-xs font-medium">{o.orderNumber}</td>
                  <td className="max-w-[12rem] truncate px-5 py-3 text-muted-foreground">{o.email}</td>
                  <td className="px-5 py-3 text-right font-semibold">{formatPrice(o.total)}</td>
                  <td className="px-5 py-3">
                    <div className="flex flex-col gap-1.5">
                      <OrderStatusBadge status={o.status} />
                      <AdminSelect
                        value={o.status}
                        disabled={busy === o.id}
                        onChange={(e) => patch(o, { status: e.target.value })}
                        className="h-8 w-36 text-xs"
                        aria-label="Modifier le statut"
                      >
                        {ORDER_STATUS_OPTIONS.map((s) => (
                          <option key={s.value} value={s.value}>
                            {s.label}
                          </option>
                        ))}
                      </AdminSelect>
                    </div>
                  </td>
                  <td className="px-5 py-3">
                    <div className="flex flex-col gap-1.5">
                      <PaymentStatusBadge status={o.paymentStatus} />
                      <AdminSelect
                        value={o.paymentStatus}
                        disabled={busy === o.id}
                        onChange={(e) => patch(o, { paymentStatus: e.target.value })}
                        className="h-8 w-36 text-xs"
                        aria-label="Modifier le paiement"
                      >
                        {PAYMENT_STATUS_OPTIONS.map((s) => (
                          <option key={s.value} value={s.value}>
                            {s.label}
                          </option>
                        ))}
                      </AdminSelect>
                    </div>
                  </td>
                  <td className="px-5 py-3 text-right text-xs text-muted-foreground">{timeAgo(o.createdAt)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
