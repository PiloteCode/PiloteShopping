import { Badge, type BadgeProps } from "@/components/ui/badge";

type Variant = NonNullable<BadgeProps["variant"]>;

const ORDER_STATUS: Record<string, { label: string; variant: Variant }> = {
  completed: { label: "Terminée", variant: "success" },
  processing: { label: "En traitement", variant: "default" },
  pending: { label: "En attente", variant: "warning" },
  cancelled: { label: "Annulée", variant: "destructive" },
  refunded: { label: "Remboursée", variant: "secondary" },
};

const PAYMENT_STATUS: Record<string, { label: string; variant: Variant }> = {
  paid: { label: "Payée", variant: "success" },
  pending: { label: "En attente", variant: "warning" },
  failed: { label: "Échec", variant: "destructive" },
  refunded: { label: "Remboursée", variant: "secondary" },
  partially_refunded: { label: "Remboursée en partie", variant: "secondary" },
};

const DELIVERY_STATUS: Record<string, { label: string; variant: Variant }> = {
  delivered: { label: "Livré", variant: "success" },
  processing: { label: "En préparation", variant: "warning" },
  pending: { label: "En attente", variant: "secondary" },
  failed: { label: "Échec", variant: "destructive" },
};

export function OrderStatusBadge({ status }: { status: string }) {
  const s = ORDER_STATUS[status] ?? { label: status, variant: "secondary" as Variant };
  return <Badge variant={s.variant}>{s.label}</Badge>;
}

export function PaymentStatusBadge({ status }: { status: string }) {
  const s = PAYMENT_STATUS[status] ?? { label: status, variant: "secondary" as Variant };
  return <Badge variant={s.variant}>{s.label}</Badge>;
}

export function DeliveryStatusBadge({ status }: { status: string }) {
  const s = DELIVERY_STATUS[status] ?? { label: status, variant: "secondary" as Variant };
  return <Badge variant={s.variant}>{s.label}</Badge>;
}
