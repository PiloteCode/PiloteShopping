"use client";

import * as React from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { motion } from "framer-motion";
import { ArrowRight, CheckCircle2, KeyRound, Package } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { apiGet } from "@/lib/client";
import { formatPrice } from "@/lib/utils";

interface OrderItem { id: number; productName: string; offerName: string | null; quantity: number; totalPrice: number; deliveryStatus: string; hasDelivery: boolean }
interface Order { orderNumber: string; total: number; paymentStatus: string; items: OrderItem[] }

function SuccessInner() {
  const params = useSearchParams();
  const orderNumber = params.get("order") ?? "";
  const [order, setOrder] = React.useState<Order | null>(null);

  React.useEffect(() => {
    if (orderNumber) apiGet<Order>(`/api/v1/orders/${orderNumber}`).then(setOrder).catch(() => setOrder(null));
  }, [orderNumber]);

  return (
    <div className="mx-auto max-w-2xl px-4 py-16 text-center lg:px-6">
      <motion.div initial={{ scale: 0, rotate: -20 }} animate={{ scale: 1, rotate: 0 }} transition={{ type: "spring", stiffness: 200, damping: 14 }} className="mx-auto grid h-20 w-20 place-items-center rounded-full bg-success/15">
        <CheckCircle2 className="h-11 w-11 text-success" />
      </motion.div>
      <motion.h1 initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.15 }} className="mt-6 text-3xl font-bold">
        Merci pour votre commande !
      </motion.h1>
      <p className="mt-2 text-muted-foreground">
        Votre commande <span className="font-mono font-semibold text-foreground">{orderNumber}</span> a bien été enregistrée.
      </p>

      {order && (
        <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.25 }} className="mt-8 rounded-2xl border border-border bg-card p-6 text-left">
          <div className="flex items-center justify-between">
            <span className="font-semibold">Récapitulatif</span>
            <Badge variant={order.paymentStatus === "paid" ? "success" : "warning"}>
              {order.paymentStatus === "paid" ? "Payée" : "En attente"}
            </Badge>
          </div>
          <div className="mt-4 space-y-3">
            {order.items.map((it) => (
              <div key={it.id} className="flex items-center justify-between gap-3 rounded-xl border border-border bg-background/40 p-3">
                <div className="flex items-center gap-3">
                  <span className="grid h-9 w-9 place-items-center rounded-lg bg-primary/10 text-primary">
                    {it.hasDelivery ? <KeyRound className="h-4 w-4" /> : <Package className="h-4 w-4" />}
                  </span>
                  <div>
                    <p className="text-sm font-medium">{it.productName}</p>
                    <p className="text-xs text-muted-foreground">{it.offerName} · ×{it.quantity}</p>
                  </div>
                </div>
                <Badge variant={it.deliveryStatus === "delivered" ? "success" : "secondary"}>
                  {it.deliveryStatus === "delivered" ? "Livré" : "En préparation"}
                </Badge>
              </div>
            ))}
          </div>
          <div className="mt-4 flex justify-between border-t border-border pt-4 font-bold">
            <span>Total</span><span>{formatPrice(order.total)}</span>
          </div>
        </motion.div>
      )}

      <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
        <Button asChild size="lg"><Link href={`/dashboard/orders/${orderNumber}`}>Voir ma commande <ArrowRight /></Link></Button>
        <Button asChild size="lg" variant="outline"><Link href="/products">Continuer mes achats</Link></Button>
      </div>
    </div>
  );
}

export default function SuccessPage() {
  return (
    <React.Suspense fallback={<div className="py-24 text-center text-muted-foreground">Chargement…</div>}>
      <SuccessInner />
    </React.Suspense>
  );
}
