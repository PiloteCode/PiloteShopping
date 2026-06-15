import { Badge, type BadgeProps } from "@/components/ui/badge";

type Variant = NonNullable<BadgeProps["variant"]>;

const ORDER_STATUS: Record<string, { label: string; variant: Variant }> = {
  pending: { label: "En attente", variant: "warning" },
  processing: { label: "En cours", variant: "default" },
  completed: { label: "Terminée", variant: "success" },
  cancelled: { label: "Annulée", variant: "destructive" },
};

const PAYMENT_STATUS: Record<string, { label: string; variant: Variant }> = {
  pending: { label: "En attente", variant: "warning" },
  paid: { label: "Payé", variant: "success" },
  failed: { label: "Échoué", variant: "destructive" },
  refunded: { label: "Remboursé", variant: "secondary" },
};

export function OrderStatusBadge({ status }: { status: string }) {
  const s = ORDER_STATUS[status] ?? { label: status, variant: "outline" as Variant };
  return <Badge variant={s.variant}>{s.label}</Badge>;
}

export function PaymentStatusBadge({ status }: { status: string }) {
  const s = PAYMENT_STATUS[status] ?? { label: status, variant: "outline" as Variant };
  return <Badge variant={s.variant}>{s.label}</Badge>;
}

export const ORDER_STATUS_OPTIONS = Object.entries(ORDER_STATUS).map(([value, { label }]) => ({ value, label }));
export const PAYMENT_STATUS_OPTIONS = Object.entries(PAYMENT_STATUS).map(([value, { label }]) => ({ value, label }));
