"use client";

import * as React from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { motion } from "framer-motion";
import {
  ArrowLeft,
  Check,
  Copy,
  Eye,
  EyeOff,
  KeyRound,
  Loader2,
  MapPin,
  Package,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DeliveryStatusBadge,
  OrderStatusBadge,
  PaymentStatusBadge,
} from "@/components/dashboard/status-badge";
import { apiGet, apiPost } from "@/lib/client";
import { formatPrice } from "@/lib/utils";
import { toast } from "sonner";

interface BillingAddress {
  first_name?: string | null;
  last_name?: string | null;
  company_name?: string | null;
  address_line1?: string | null;
  address_line2?: string | null;
  city?: string | null;
  postal_code?: string | null;
  country?: string | null;
  phone?: string | null;
}
interface OrderItem {
  id: number;
  productName: string;
  productSlug: string;
  accessTypeName: string;
  offerName: string;
  quantity: number;
  unitPrice: number;
  totalPrice: number;
  billingCycle: string;
  deliveryStatus: string;
  deliveryMethod: string;
  hasDelivery: boolean;
}
interface OrderDetail {
  orderNumber: string;
  status: string;
  paymentStatus: string;
  paymentMethod: string;
  subtotal: number;
  taxAmount: number;
  discountAmount: number;
  total: number;
  couponCode: string | null;
  billingAddress: BillingAddress | null;
  createdAt: string;
  deliveredAt: string | null;
  items: OrderItem[];
}
interface AccessResult {
  productName: string;
  accessTypeName: string;
  deliveredAt: string | null;
  data: Record<string, unknown>;
}

const PAYMENT_LABELS: Record<string, string> = {
  stripe: "Carte bancaire",
  paypal: "PayPal",
  bank_transfer: "Virement bancaire",
  balance: "Portefeuille",
};

export default function OrderDetailPage() {
  const params = useParams<{ number: string }>();
  const number = params.number;

  const [order, setOrder] = React.useState<OrderDetail | null>(null);
  const [error, setError] = React.useState<string | null>(null);
  const [access, setAccess] = React.useState<AccessResult | null>(null);
  const [loadingItem, setLoadingItem] = React.useState<number | null>(null);

  React.useEffect(() => {
    if (!number) return;
    apiGet<OrderDetail>(`/api/v1/orders/${number}`)
      .then(setOrder)
      .catch((e) => setError((e as Error).message));
  }, [number]);

  async function revealAccess(itemId: number) {
    setLoadingItem(itemId);
    try {
      const data = await apiPost<AccessResult>("/api/v1/orders/access", { orderItemId: itemId });
      setAccess(data);
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setLoadingItem(null);
    }
  }

  if (error) {
    return (
      <div className="flex flex-col items-center rounded-2xl border border-border bg-card px-4 py-16 text-center">
        <span className="grid h-16 w-16 place-items-center rounded-2xl bg-muted">
          <Package className="h-7 w-7 text-muted-foreground" />
        </span>
        <h1 className="mt-5 text-lg font-semibold">{error}</h1>
        <Button className="mt-6" variant="outline" asChild>
          <Link href="/dashboard/orders"><ArrowLeft /> Retour aux commandes</Link>
        </Button>
      </div>
    );
  }

  if (!order) return <OrderDetailSkeleton />;

  return (
    <div className="space-y-6">
      <div>
        <Button variant="ghost" size="sm" className="-ml-2 mb-2 text-muted-foreground" asChild>
          <Link href="/dashboard/orders"><ArrowLeft /> Mes commandes</Link>
        </Button>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="font-mono text-2xl font-bold">{order.orderNumber}</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Passée le {new Date(order.createdAt).toLocaleDateString("fr-FR", { dateStyle: "long" })}
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <OrderStatusBadge status={order.status} />
            <PaymentStatusBadge status={order.paymentStatus} />
          </div>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Items */}
        <div className="space-y-4 lg:col-span-2">
          {order.items.map((it) => (
            <div key={it.id} className="rounded-2xl border border-border bg-card p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 gap-3">
                  <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                    {it.hasDelivery ? <KeyRound className="h-5 w-5" /> : <Package className="h-5 w-5" />}
                  </span>
                  <div className="min-w-0">
                    <Link href={`/products/${it.productSlug}`} className="font-semibold hover:text-primary">
                      {it.productName}
                    </Link>
                    <p className="text-xs text-muted-foreground">
                      {it.accessTypeName} · {it.offerName}
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">Quantité : {it.quantity}</p>
                  </div>
                </div>
                <div className="shrink-0 text-right">
                  <p className="font-semibold">{formatPrice(it.totalPrice)}</p>
                  <div className="mt-1.5">
                    <DeliveryStatusBadge status={it.deliveryStatus} />
                  </div>
                </div>
              </div>
              {it.hasDelivery && (
                <div className="mt-3 border-t border-border pt-3">
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => revealAccess(it.id)}
                    disabled={loadingItem === it.id}
                  >
                    {loadingItem === it.id ? <Loader2 className="animate-spin" /> : <KeyRound />}
                    Voir mes accès
                  </Button>
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Aside: summary + billing */}
        <div className="space-y-6">
          <div className="rounded-2xl border border-border bg-card p-5">
            <h2 className="font-semibold">Récapitulatif</h2>
            <dl className="mt-4 space-y-2.5 text-sm">
              <Row label="Sous-total" value={formatPrice(order.subtotal)} />
              {order.discountAmount > 0 && (
                <Row
                  label={`Réduction${order.couponCode ? ` (${order.couponCode})` : ""}`}
                  value={`− ${formatPrice(order.discountAmount)}`}
                  accent
                />
              )}
              <Row label="TVA" value={formatPrice(order.taxAmount)} muted />
              <div className="border-t border-border pt-2.5">
                <Row label="Total" value={formatPrice(order.total)} bold />
              </div>
            </dl>
            <p className="mt-4 text-xs text-muted-foreground">
              Mode de paiement : {PAYMENT_LABELS[order.paymentMethod] ?? order.paymentMethod}
            </p>
          </div>

          {order.billingAddress && (
            <div className="rounded-2xl border border-border bg-card p-5">
              <h2 className="flex items-center gap-2 font-semibold">
                <MapPin className="h-4 w-4 text-muted-foreground" /> Adresse de facturation
              </h2>
              <address className="mt-3 text-sm not-italic leading-relaxed text-muted-foreground">
                <span className="block font-medium text-foreground">
                  {order.billingAddress.first_name} {order.billingAddress.last_name}
                </span>
                {order.billingAddress.company_name && <span className="block">{order.billingAddress.company_name}</span>}
                {order.billingAddress.address_line1 && <span className="block">{order.billingAddress.address_line1}</span>}
                {order.billingAddress.address_line2 && <span className="block">{order.billingAddress.address_line2}</span>}
                <span className="block">
                  {order.billingAddress.postal_code} {order.billingAddress.city}
                </span>
                {order.billingAddress.country && <span className="block">{order.billingAddress.country}</span>}
                {order.billingAddress.phone && <span className="mt-1 block">{order.billingAddress.phone}</span>}
              </address>
            </div>
          )}
        </div>
      </div>

      <AccessDialog access={access} onClose={() => setAccess(null)} />
    </div>
  );
}

const SECRET_KEYS = ["license_key", "password", "key", "token", "secret", "code", "pin"];

function isSecret(key: string): boolean {
  return SECRET_KEYS.some((k) => key.toLowerCase().includes(k));
}

const FIELD_LABELS: Record<string, string> = {
  type: "Type",
  license_key: "Clé de licence",
  email: "Email",
  password: "Mot de passe",
  url: "Lien",
  username: "Identifiant",
  notes: "Notes",
  token: "Jeton",
  key: "Clé",
};

function AccessDialog({ access, onClose }: { access: AccessResult | null; onClose: () => void }) {
  return (
    <Dialog open={!!access} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        {access && (
          <>
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2">
                <span className="grid h-9 w-9 place-items-center rounded-xl bg-success/15 text-success">
                  <KeyRound className="h-4 w-4" />
                </span>
                Vos accès
              </DialogTitle>
              <DialogDescription>
                {access.productName} · {access.accessTypeName}
                {access.deliveredAt
                  ? ` — livré le ${new Date(access.deliveredAt).toLocaleDateString("fr-FR")}`
                  : ""}
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-2.5">
              {Object.entries(access.data)
                .filter(([k]) => k !== "type")
                .map(([key, value]) => (
                  <CredentialField key={key} fieldKey={key} value={String(value ?? "")} />
                ))}
            </div>

            <p className="text-xs text-muted-foreground">
              Conservez ces informations en lieu sûr. Ne les partagez avec personne.
            </p>
          </>
        )}
      </DialogContent>
    </Dialog>
  );
}

function CredentialField({ fieldKey, value }: { fieldKey: string; value: string }) {
  const secret = isSecret(fieldKey);
  const [revealed, setRevealed] = React.useState(!secret);
  const [copied, setCopied] = React.useState(false);
  const label = FIELD_LABELS[fieldKey] ?? fieldKey.charAt(0).toUpperCase() + fieldKey.slice(1).replace(/_/g, " ");

  async function copy() {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      toast.success("Copié dans le presse-papiers");
      setTimeout(() => setCopied(false), 1600);
    } catch {
      toast.error("Impossible de copier");
    }
  }

  return (
    <div className="rounded-xl border border-border bg-background/40 p-3">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <div className="mt-1.5 flex items-center gap-2">
        <code className="flex-1 truncate font-mono text-sm">
          {revealed ? value : "•".repeat(Math.min(value.length, 16))}
        </code>
        {secret && (
          <button
            type="button"
            onClick={() => setRevealed((r) => !r)}
            className="text-muted-foreground transition-colors hover:text-foreground"
            aria-label={revealed ? "Masquer" : "Afficher"}
          >
            {revealed ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
          </button>
        )}
        <button
          type="button"
          onClick={copy}
          className="text-muted-foreground transition-colors hover:text-primary"
          aria-label="Copier"
        >
          {copied ? <Check className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}
        </button>
      </div>
    </div>
  );
}

function Row({
  label,
  value,
  bold,
  muted,
  accent,
}: {
  label: string;
  value: string;
  bold?: boolean;
  muted?: boolean;
  accent?: boolean;
}) {
  return (
    <div className="flex items-center justify-between gap-2">
      <dt className={muted ? "text-muted-foreground" : bold ? "font-semibold" : ""}>{label}</dt>
      <dd className={accent ? "font-medium text-success" : bold ? "text-lg font-bold" : ""}>{value}</dd>
    </div>
  );
}

function OrderDetailSkeleton() {
  return (
    <div className="space-y-6">
      <Skeleton className="h-9 w-48" />
      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-4 lg:col-span-2">
          {[0, 1].map((i) => <Skeleton key={i} className="h-28 w-full rounded-2xl" />)}
        </div>
        <div className="space-y-6">
          <Skeleton className="h-52 w-full rounded-2xl" />
          <Skeleton className="h-44 w-full rounded-2xl" />
        </div>
      </div>
    </div>
  );
}
