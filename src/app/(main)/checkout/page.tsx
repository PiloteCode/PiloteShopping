"use client";

import * as React from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Banknote, CreditCard, Landmark, Loader2, Lock, Wallet } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { useCart } from "@/components/providers";
import { apiGet, apiPost } from "@/lib/client";
import { cn, formatPrice } from "@/lib/utils";
import { toast } from "sonner";

interface Summary { itemCount: number; subtotal: number; setupFees: number; discount: number; taxAmount: number; total: number; canCheckout: boolean; hasSubscription: boolean }
interface Cart { items: { id: number; name: string; offerName: string; lineTotal: number; quantity: number; billing: { suffix: string } }[]; summary: Summary }
interface Me { user: { firstName: string; lastName: string; balance: number } | null }

const METHODS = [
  { slug: "stripe", name: "Carte bancaire", icon: CreditCard, desc: "Visa, Mastercard, Amex" },
  { slug: "balance", name: "Portefeuille", icon: Wallet, desc: "Payer avec mon solde", auth: true },
  { slug: "paypal", name: "PayPal", icon: Banknote, desc: "Compte PayPal" },
  { slug: "bank_transfer", name: "Virement", icon: Landmark, desc: "Sous 1-2 jours ouvrés" },
];

const COUNTRIES = [["FR", "France"], ["BE", "Belgique"], ["CH", "Suisse"], ["LU", "Luxembourg"], ["CA", "Canada"]];

export default function CheckoutPage() {
  const router = useRouter();
  const { setCount } = useCart();
  const [cart, setCart] = React.useState<Cart | null>(null);
  const [me, setMe] = React.useState<Me["user"]>(null);
  const [method, setMethod] = React.useState("stripe");
  const [submitting, setSubmitting] = React.useState(false);
  const [form, setForm] = React.useState({ firstName: "", lastName: "", companyName: "", addressLine1: "", addressLine2: "", city: "", postalCode: "", country: "FR", phone: "", notes: "" });

  React.useEffect(() => {
    Promise.all([apiGet<Cart>("/api/v1/cart"), apiGet<Me>("/api/v1/auth/me")]).then(([c, m]) => {
      setCart(c);
      setMe(m.user);
      if (m.user) setForm((f) => ({ ...f, firstName: m.user!.firstName, lastName: m.user!.lastName }));
    }).catch(() => setCart({ items: [], summary: { itemCount: 0, subtotal: 0, setupFees: 0, discount: 0, taxAmount: 0, total: 0, canCheckout: false, hasSubscription: false } }));
  }, []);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!cart) return;
    setSubmitting(true);
    try {
      const data = await apiPost<{ orderNumber: string; paymentStatus: string }>("/api/v1/checkout", {
        paymentMethod: method,
        billingAddress: {
          firstName: form.firstName, lastName: form.lastName, companyName: form.companyName || undefined,
          addressLine1: form.addressLine1, addressLine2: form.addressLine2 || undefined,
          city: form.city, postalCode: form.postalCode, country: form.country, phone: form.phone || undefined,
        },
        notes: form.notes || undefined,
      });
      setCount(0);
      toast.success("Commande confirmée !");
      router.push(`/checkout/success?order=${data.orderNumber}`);
    } catch (e) {
      toast.error((e as Error).message);
      setSubmitting(false);
    }
  }

  if (!cart) return <div className="mx-auto max-w-5xl px-4 py-10"><Skeleton className="h-[600px] w-full rounded-2xl" /></div>;

  if (cart.items.length === 0) {
    return (
      <div className="mx-auto max-w-xl px-4 py-24 text-center">
        <h1 className="text-2xl font-bold">Votre panier est vide</h1>
        <Button className="mt-6" asChild><Link href="/products">Voir la boutique</Link></Button>
      </div>
    );
  }

  const needsLogin = cart.summary.hasSubscription && !me;
  const balanceTooLow = method === "balance" && (me?.balance ?? 0) < cart.summary.total;

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 lg:px-6">
      <h1 className="text-3xl font-bold">Paiement</h1>

      <form onSubmit={submit} className="mt-8 grid gap-8 lg:grid-cols-3">
        <div className="space-y-8 lg:col-span-2">
          {needsLogin && (
            <div className="rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm">
              Votre panier contient un abonnement. <Link href="/login?next=/checkout" className="font-semibold text-primary underline">Connectez-vous</Link> pour continuer.
            </div>
          )}

          {/* Address */}
          <section className="rounded-2xl border border-border bg-card p-6">
            <h2 className="font-semibold">Adresse de facturation</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <Field label="Prénom" required><Input value={form.firstName} onChange={set("firstName")} required /></Field>
              <Field label="Nom" required><Input value={form.lastName} onChange={set("lastName")} required /></Field>
              <Field label="Société (optionnel)" className="sm:col-span-2"><Input value={form.companyName} onChange={set("companyName")} /></Field>
              <Field label="Adresse" required className="sm:col-span-2"><Input value={form.addressLine1} onChange={set("addressLine1")} required /></Field>
              <Field label="Complément (optionnel)" className="sm:col-span-2"><Input value={form.addressLine2} onChange={set("addressLine2")} /></Field>
              <Field label="Ville" required><Input value={form.city} onChange={set("city")} required /></Field>
              <Field label="Code postal" required><Input value={form.postalCode} onChange={set("postalCode")} required /></Field>
              <Field label="Pays" required>
                <select value={form.country} onChange={set("country")} className="h-10 w-full rounded-lg border border-input bg-background/60 px-3 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/40">
                  {COUNTRIES.map(([c, n]) => <option key={c} value={c}>{n}</option>)}
                </select>
              </Field>
              <Field label="Téléphone (optionnel)"><Input value={form.phone} onChange={set("phone")} /></Field>
            </div>
          </section>

          {/* Payment */}
          <section className="rounded-2xl border border-border bg-card p-6">
            <h2 className="font-semibold">Méthode de paiement</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              {METHODS.map((m) => {
                const disabled = m.auth && !me;
                const selected = method === m.slug;
                return (
                  <button
                    type="button"
                    key={m.slug}
                    disabled={disabled}
                    onClick={() => setMethod(m.slug)}
                    className={cn(
                      "flex items-center gap-3 rounded-xl border p-3.5 text-left transition-all",
                      selected ? "border-primary bg-primary/5 ring-1 ring-primary" : "border-border hover:border-primary/40",
                      disabled && "cursor-not-allowed opacity-40",
                    )}
                  >
                    <m.icon className="h-5 w-5 text-primary" />
                    <div>
                      <p className="text-sm font-medium">{m.name}</p>
                      <p className="text-xs text-muted-foreground">{m.slug === "balance" && me ? formatPrice(me.balance) + " disponible" : m.desc}</p>
                    </div>
                  </button>
                );
              })}
            </div>
            {balanceTooLow && <p className="mt-3 text-sm text-destructive">Solde insuffisant pour cette commande.</p>}
            <p className="mt-4 flex items-center gap-1.5 text-xs text-muted-foreground"><Lock className="h-3.5 w-3.5" /> Mode démo : aucun paiement réel n&apos;est effectué.</p>
          </section>
        </div>

        {/* Summary */}
        <div className="lg:sticky lg:top-24 lg:h-fit">
          <div className="rounded-2xl border border-border bg-card p-6">
            <h2 className="font-semibold">Votre commande</h2>
            <div className="mt-4 space-y-3">
              {cart.items.map((it) => (
                <div key={it.id} className="flex justify-between gap-2 text-sm">
                  <span className="text-muted-foreground">{it.name} <span className="text-xs">×{it.quantity}</span></span>
                  <span className="shrink-0 font-medium">{formatPrice(it.lineTotal)}{it.billing.suffix}</span>
                </div>
              ))}
            </div>
            <dl className="mt-4 space-y-2 border-t border-border pt-4 text-sm">
              <div className="flex justify-between"><dt className="text-muted-foreground">Sous-total</dt><dd>{formatPrice(cart.summary.subtotal)}</dd></div>
              {cart.summary.discount > 0 && <div className="flex justify-between text-success"><dt>Réduction</dt><dd>− {formatPrice(cart.summary.discount)}</dd></div>}
              <div className="flex justify-between"><dt className="text-muted-foreground">TVA</dt><dd>{formatPrice(cart.summary.taxAmount)}</dd></div>
              <div className="flex justify-between border-t border-border pt-2 text-base font-bold"><dt>Total</dt><dd>{formatPrice(cart.summary.total)}</dd></div>
            </dl>
            <Button type="submit" className="mt-5 w-full" size="lg" disabled={submitting || needsLogin || balanceTooLow || !cart.summary.canCheckout}>
              {submitting ? <Loader2 className="animate-spin" /> : <Lock className="h-4 w-4" />}
              Payer {formatPrice(cart.summary.total)}
            </Button>
          </div>
        </div>
      </form>
    </div>
  );
}

function Field({ label, required, children, className }: { label: string; required?: boolean; children: React.ReactNode; className?: string }) {
  return (
    <div className={cn("space-y-1.5", className)}>
      <Label>{label}{required && <span className="text-destructive"> *</span>}</Label>
      {children}
    </div>
  );
}
