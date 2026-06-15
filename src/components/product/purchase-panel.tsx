"use client";

import * as React from "react";
import { useRouter } from "next/navigation";
import { Check, Heart, Loader2, Minus, Plus, ShoppingCart, Zap } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { useCart } from "@/components/providers";
import { apiPost } from "@/lib/client";
import { cn, formatPrice } from "@/lib/utils";
import { toast } from "sonner";
import type { AccessTypeDTO } from "@/server/products";

export function PurchasePanel({
  productId,
  accessTypes,
}: {
  productId: number;
  accessTypes: AccessTypeDTO[];
}) {
  const router = useRouter();
  const { setCount, refresh } = useCart();
  const [accessId, setAccessId] = React.useState(String(accessTypes[0]?.id));
  const access = accessTypes.find((a) => String(a.id) === accessId) ?? accessTypes[0];
  const defaultOffer = access?.offers.find((o) => o.isDefault) ?? access?.offers[0];
  const [offerId, setOfferId] = React.useState(defaultOffer?.id ?? 0);
  const [qty, setQty] = React.useState(1);
  const [busy, setBusy] = React.useState<"add" | "buy" | null>(null);

  React.useEffect(() => {
    const def = access?.offers.find((o) => o.isDefault) ?? access?.offers[0];
    setOfferId(def?.id ?? 0);
    setQty(1);
  }, [accessId]); // eslint-disable-line react-hooks/exhaustive-deps

  const offer = access?.offers.find((o) => o.id === offerId);
  const isSub = offer?.billing.isSubscription ?? false;
  const lineTotal = offer ? offer.price * (isSub ? 1 : qty) + offer.setupFee : 0;

  async function addToCart(then?: "checkout") {
    if (!offer) return;
    setBusy(then === "checkout" ? "buy" : "add");
    try {
      const data = await apiPost<{ summary: { itemCount: number } }>("/api/v1/cart", {
        productId,
        offerId: offer.id,
        quantity: isSub ? 1 : qty,
      });
      setCount(data.summary.itemCount);
      refresh();
      if (then === "checkout") router.push("/checkout");
      else toast.success("Ajouté au panier");
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(null);
    }
  }

  async function addToWishlist() {
    try {
      await apiPost("/api/v1/wishlist", { productId });
      toast.success("Ajouté à votre liste de souhaits");
    } catch (e) {
      const msg = (e as Error).message;
      toast.error(msg.includes("Authentification") ? "Connectez-vous pour utiliser la liste de souhaits" : msg);
    }
  }

  if (!access) return null;

  return (
    <div className="rounded-2xl border border-border bg-card p-5">
      {accessTypes.length > 1 ? (
        <Tabs value={accessId} onValueChange={setAccessId}>
          <p className="mb-2 text-sm font-medium">Type d&apos;accès</p>
          <TabsList className="flex w-full flex-wrap">
            {accessTypes.map((a) => (
              <TabsTrigger key={a.id} value={String(a.id)} className="flex-1">{a.name}</TabsTrigger>
            ))}
          </TabsList>
          {accessTypes.map((a) => (
            <TabsContent key={a.id} value={String(a.id)}>
              <OfferList offers={a.offers} offerId={offerId} onSelect={setOfferId} />
            </TabsContent>
          ))}
        </Tabs>
      ) : (
        <OfferList offers={access.offers} offerId={offerId} onSelect={setOfferId} />
      )}

      {/* Quantity (one-time only) */}
      {!isSub && (
        <div className="mt-5 flex items-center justify-between">
          <span className="text-sm font-medium">Quantité</span>
          <div className="flex items-center gap-1 rounded-lg border border-border p-1">
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setQty((q) => Math.max(1, q - 1))} disabled={qty <= 1}><Minus className="h-4 w-4" /></Button>
            <span className="w-8 text-center text-sm font-medium">{qty}</span>
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setQty((q) => Math.min(10, q + 1))} disabled={qty >= 10}><Plus className="h-4 w-4" /></Button>
          </div>
        </div>
      )}

      {/* Total */}
      <div className="mt-5 flex items-baseline justify-between border-t border-border pt-4">
        <span className="text-sm text-muted-foreground">Total</span>
        <span className="text-2xl font-bold">
          {formatPrice(lineTotal)}
          {offer && <span className="ml-1 text-sm font-normal text-muted-foreground">{offer.billing.suffix}</span>}
        </span>
      </div>
      {offer?.setupFee ? (
        <p className="mt-1 text-right text-xs text-muted-foreground">dont {formatPrice(offer.setupFee)} de frais d&apos;installation</p>
      ) : null}

      {/* Actions */}
      <div className="mt-5 space-y-2.5">
        <Button className="w-full" size="lg" onClick={() => addToCart("checkout")} disabled={!offer || busy !== null}>
          {busy === "buy" ? <Loader2 className="animate-spin" /> : <Zap />} Acheter maintenant
        </Button>
        <div className="flex gap-2">
          <Button variant="secondary" className="flex-1" onClick={() => addToCart()} disabled={!offer || busy !== null}>
            {busy === "add" ? <Loader2 className="animate-spin" /> : <ShoppingCart />} Ajouter au panier
          </Button>
          <Button variant="outline" size="icon" onClick={addToWishlist} aria-label="Liste de souhaits"><Heart className="h-4 w-4" /></Button>
        </div>
      </div>

      <p className="mt-4 flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
        <Check className="h-3.5 w-3.5 text-success" /> Livraison {access.deliveryMethod === "automatic" ? "instantanée" : "rapide"} · Paiement sécurisé
      </p>
    </div>
  );
}

function OfferList({
  offers,
  offerId,
  onSelect,
}: {
  offers: AccessTypeDTO["offers"];
  offerId: number;
  onSelect: (id: number) => void;
}) {
  return (
    <div className="mt-3 space-y-2.5">
      {offers.map((o) => {
        const selected = o.id === offerId;
        const discount = o.compareAtPrice && o.compareAtPrice > o.price ? Math.round((1 - o.price / o.compareAtPrice) * 100) : 0;
        return (
          <button
            key={o.id}
            onClick={() => onSelect(o.id)}
            disabled={!o.inStock}
            className={cn(
              "flex w-full items-center justify-between rounded-xl border p-3.5 text-left transition-all",
              selected ? "border-primary bg-primary/5 ring-1 ring-primary" : "border-border hover:border-primary/40",
              !o.inStock && "cursor-not-allowed opacity-50",
            )}
          >
            <div className="flex items-center gap-3">
              <span className={cn("grid h-5 w-5 place-items-center rounded-full border-2", selected ? "border-primary" : "border-muted-foreground/40")}>
                {selected && <span className="h-2.5 w-2.5 rounded-full bg-primary" />}
              </span>
              <div>
                <p className="font-medium">{o.name}</p>
                <p className="text-xs text-muted-foreground">
                  {o.billing.label}
                  {!o.inStock && " · Rupture de stock"}
                  {o.billing.monthlyEquivalentPrice ? ` · ${formatPrice(o.billing.monthlyEquivalentPrice)}/mois` : ""}
                </p>
              </div>
            </div>
            <div className="text-right">
              <div className="flex items-baseline gap-1.5">
                <span className="font-bold">{formatPrice(o.price)}</span>
                <span className="text-xs text-muted-foreground">{o.billing.suffix}</span>
              </div>
              {discount > 0 && <Badge variant="success" className="mt-0.5">-{discount}%</Badge>}
            </div>
          </button>
        );
      })}
    </div>
  );
}
