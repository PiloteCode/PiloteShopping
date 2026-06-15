"use client";

import * as React from "react";
import Link from "next/link";
import { motion, AnimatePresence } from "framer-motion";
import { ArrowRight, Loader2, Minus, Plus, ShoppingBag, Tag, Trash2, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { useCart } from "@/components/providers";
import { apiDelete, apiGet, apiPatch, apiPost } from "@/lib/client";
import { formatPrice } from "@/lib/utils";
import { toast } from "sonner";

interface Item {
  id: number; name: string; slug: string; image: string | null; offerName: string; accessTypeName: string;
  categoryName: string | null; unitPrice: number; setupFee: number; quantity: number; lineTotal: number;
  isSubscription: boolean; inStock: boolean; billing: { label: string; suffix: string };
}
interface Summary { itemCount: number; subtotal: number; setupFees: number; discount: number; taxAmount: number; total: number; canCheckout: boolean }
interface Coupon { code: string; name: string; discountAmount: number }
interface Cart { items: Item[]; summary: Summary; coupon: Coupon | null }

export default function CartPage() {
  const { setCount } = useCart();
  const [cart, setCart] = React.useState<Cart | null>(null);
  const [code, setCode] = React.useState("");
  const [busy, setBusy] = React.useState(false);

  const load = React.useCallback(async () => {
    const data = await apiGet<Cart>("/api/v1/cart");
    setCart(data);
    setCount(data.summary.itemCount);
  }, [setCount]);

  React.useEffect(() => { load().catch(() => setCart({ items: [], summary: { itemCount: 0, subtotal: 0, setupFees: 0, discount: 0, taxAmount: 0, total: 0, canCheckout: false }, coupon: null })); }, [load]);

  async function update(id: number, quantity: number) {
    try { await apiPatch(`/api/v1/cart/${id}`, { quantity }); await load(); }
    catch (e) { toast.error((e as Error).message); }
  }
  async function remove(id: number) {
    try { await apiDelete(`/api/v1/cart/${id}`); toast.success("Article retiré"); await load(); }
    catch (e) { toast.error((e as Error).message); }
  }
  async function applyCoupon() {
    if (!code.trim()) return;
    setBusy(true);
    try { await apiPost("/api/v1/cart/coupon", { code }); toast.success("Code promo appliqué"); setCode(""); await load(); }
    catch (e) { toast.error((e as Error).message); }
    finally { setBusy(false); }
  }
  async function removeCoupon() {
    try { await apiDelete("/api/v1/cart/coupon"); await load(); } catch { /* */ }
  }

  if (!cart) return <CartSkeleton />;

  if (cart.items.length === 0) {
    return (
      <div className="mx-auto flex max-w-2xl flex-col items-center px-4 py-24 text-center">
        <span className="grid h-20 w-20 place-items-center rounded-2xl bg-muted"><ShoppingBag className="h-9 w-9 text-muted-foreground" /></span>
        <h1 className="mt-6 text-2xl font-bold">Votre panier est vide</h1>
        <p className="mt-2 text-muted-foreground">Parcourez notre catalogue et trouvez votre prochain produit numérique.</p>
        <Button className="mt-6" size="lg" asChild><Link href="/products">Découvrir la boutique <ArrowRight /></Link></Button>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-7xl px-4 py-10 lg:px-6">
      <h1 className="text-3xl font-bold">Mon panier</h1>
      <p className="mt-1 text-muted-foreground">{cart.summary.itemCount} article{cart.summary.itemCount > 1 ? "s" : ""}</p>

      <div className="mt-8 grid gap-8 lg:grid-cols-3">
        <div className="space-y-4 lg:col-span-2">
          <AnimatePresence initial={false}>
            {cart.items.map((it) => (
              <motion.div
                key={it.id}
                layout
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, x: -20 }}
                className="flex gap-4 rounded-2xl border border-border bg-card p-4"
              >
                <Link href={`/products/${it.slug}`} className="h-24 w-24 shrink-0 overflow-hidden rounded-xl bg-muted">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={it.image ?? "/placeholder.svg"} alt={it.name} className="h-full w-full object-cover" />
                </Link>
                <div className="flex flex-1 flex-col">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <Link href={`/products/${it.slug}`} className="font-semibold hover:text-primary">{it.name}</Link>
                      <p className="text-xs text-muted-foreground">{it.accessTypeName} · {it.offerName}</p>
                      <div className="mt-1 flex flex-wrap gap-1.5">
                        {it.isSubscription && <Badge variant="secondary">{it.billing.label}</Badge>}
                        {!it.inStock && <Badge variant="destructive">Rupture</Badge>}
                      </div>
                    </div>
                    <button onClick={() => remove(it.id)} className="text-muted-foreground transition-colors hover:text-destructive" aria-label="Retirer">
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                  <div className="mt-auto flex items-center justify-between pt-2">
                    {it.isSubscription ? (
                      <span className="text-sm text-muted-foreground">Abonnement</span>
                    ) : (
                      <div className="flex items-center gap-1 rounded-lg border border-border p-1">
                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => update(it.id, it.quantity - 1)}><Minus className="h-3.5 w-3.5" /></Button>
                        <span className="w-7 text-center text-sm">{it.quantity}</span>
                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => update(it.id, it.quantity + 1)}><Plus className="h-3.5 w-3.5" /></Button>
                      </div>
                    )}
                    <span className="font-semibold">{formatPrice(it.lineTotal)}<span className="text-xs font-normal text-muted-foreground">{it.billing.suffix}</span></span>
                  </div>
                </div>
              </motion.div>
            ))}
          </AnimatePresence>
        </div>

        {/* Summary */}
        <div className="lg:sticky lg:top-24 lg:h-fit">
          <div className="rounded-2xl border border-border bg-card p-6">
            <h2 className="font-semibold">Récapitulatif</h2>

            {/* Coupon */}
            <div className="mt-4">
              {cart.coupon ? (
                <div className="flex items-center justify-between rounded-lg border border-success/30 bg-success/10 px-3 py-2">
                  <span className="flex items-center gap-2 text-sm text-success"><Tag className="h-4 w-4" /> {cart.coupon.code}</span>
                  <button onClick={removeCoupon} className="text-success hover:opacity-70"><X className="h-4 w-4" /></button>
                </div>
              ) : (
                <div className="flex gap-2">
                  <input value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} placeholder="Code promo" className="h-10 flex-1 rounded-lg border border-border bg-background/60 px-3 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/40" />
                  <Button variant="secondary" onClick={applyCoupon} disabled={busy}>{busy ? <Loader2 className="animate-spin" /> : "Appliquer"}</Button>
                </div>
              )}
            </div>

            <dl className="mt-5 space-y-2.5 text-sm">
              <Row label="Sous-total" value={formatPrice(cart.summary.subtotal)} />
              {cart.summary.setupFees > 0 && <Row label="Frais d'installation" value={formatPrice(cart.summary.setupFees)} />}
              {cart.summary.discount > 0 && <Row label="Réduction" value={`− ${formatPrice(cart.summary.discount)}`} accent />}
              <Row label="TVA (20%)" value={formatPrice(cart.summary.taxAmount)} muted />
              <div className="border-t border-border pt-3">
                <Row label="Total" value={formatPrice(cart.summary.total)} bold />
              </div>
            </dl>

            <Button className="mt-5 w-full" size="lg" disabled={!cart.summary.canCheckout} asChild={cart.summary.canCheckout}>
              {cart.summary.canCheckout ? <Link href="/checkout">Passer la commande <ArrowRight /></Link> : <span>Articles indisponibles</span>}
            </Button>
            <Button variant="ghost" className="mt-2 w-full" asChild><Link href="/products">Continuer mes achats</Link></Button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Row({ label, value, bold, muted, accent }: { label: string; value: string; bold?: boolean; muted?: boolean; accent?: boolean }) {
  return (
    <div className="flex items-center justify-between">
      <dt className={muted ? "text-muted-foreground" : bold ? "font-semibold" : ""}>{label}</dt>
      <dd className={accent ? "font-medium text-success" : bold ? "text-lg font-bold" : ""}>{value}</dd>
    </div>
  );
}

function CartSkeleton() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-10 lg:px-6">
      <Skeleton className="h-9 w-40" />
      <div className="mt-8 grid gap-8 lg:grid-cols-3">
        <div className="space-y-4 lg:col-span-2">{[0, 1].map((i) => <Skeleton key={i} className="h-32 w-full rounded-2xl" />)}</div>
        <Skeleton className="h-80 w-full rounded-2xl" />
      </div>
    </div>
  );
}
