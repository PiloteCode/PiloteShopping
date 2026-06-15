/**
 * Domain constants & helpers — ported from the legacy api/v1/config.php so the
 * new app keeps identical business semantics (billing cycles, delivery, etc.).
 */

export const TAX_RATE = 0.2; // TVA 20%

export type BillingCycle =
  | "one_time"
  | "monthly"
  | "quarterly"
  | "semi_yearly"
  | "yearly"
  | "commitment_3"
  | "commitment_6"
  | "commitment_12"
  | "commitment_24";

interface CycleMeta {
  label: string;
  suffix: string;
  durationMonths: number;
}

export const BILLING_CYCLES: Record<BillingCycle, CycleMeta> = {
  one_time: { label: "Achat unique", suffix: "", durationMonths: 0 },
  monthly: { label: "Mensuel", suffix: "/mois", durationMonths: 1 },
  quarterly: { label: "Trimestriel", suffix: "/trimestre", durationMonths: 3 },
  semi_yearly: { label: "Semestriel", suffix: "/semestre", durationMonths: 6 },
  yearly: { label: "Annuel", suffix: "/an", durationMonths: 12 },
  commitment_3: { label: "Engagement 3 mois", suffix: "/mois", durationMonths: 3 },
  commitment_6: { label: "Engagement 6 mois", suffix: "/mois", durationMonths: 6 },
  commitment_12: { label: "Engagement 12 mois", suffix: "/mois", durationMonths: 12 },
  commitment_24: { label: "Engagement 24 mois", suffix: "/mois", durationMonths: 24 },
};

export const isSubscriptionCycle = (c: BillingCycle) => c !== "one_time";
export const isCommitmentCycle = (c: BillingCycle) => c.startsWith("commitment_");

export interface BillingInfo {
  cycle: BillingCycle;
  label: string;
  suffix: string;
  durationMonths: number;
  isSubscription: boolean;
  isCommitment: boolean;
  periodTotalPrice: number;
  monthlyEquivalentPrice: number | null;
}

export function getBillingInfo(cycle: BillingCycle, price: number): BillingInfo {
  const meta = BILLING_CYCLES[cycle] ?? BILLING_CYCLES.one_time;
  const duration = meta.durationMonths;
  let periodTotalPrice = price;
  let monthlyEquivalentPrice: number | null = null;

  if (isCommitmentCycle(cycle)) {
    periodTotalPrice = price * duration;
    monthlyEquivalentPrice = price;
  } else if (isSubscriptionCycle(cycle)) {
    monthlyEquivalentPrice = duration > 0 ? price / duration : null;
  }

  return {
    cycle,
    label: meta.label,
    suffix: meta.suffix,
    durationMonths: duration,
    isSubscription: isSubscriptionCycle(cycle),
    isCommitment: isCommitmentCycle(cycle),
    periodTotalPrice,
    monthlyEquivalentPrice,
  };
}

export const DELIVERY_METHODS: Record<string, string> = {
  automatic: "Instantané",
  semi_automatic: "Quelques minutes",
  manual_24h: "Sous 24h",
  manual_48h: "Sous 48h",
  manual_72h: "Sous 72h",
  manual_1week: "Sous 1 semaine",
};

export const ACCESS_METHODS: Record<string, string> = {
  no_connection: "Sans connexion",
  account_required: "Compte requis",
  license_key: "Clé de licence",
  api_access: "Accès API",
  download_link: "Téléchargement",
};

export function deliveryLabel(method: string): string {
  return DELIVERY_METHODS[method] ?? "Standard";
}
export function accessLabel(method: string): string {
  return ACCESS_METHODS[method] ?? "Standard";
}

/* ── ID generators (mirror the PS… formats) ── */
function randHex(bytes: number): string {
  const arr = new Uint8Array(bytes);
  globalThis.crypto.getRandomValues(arr);
  return Array.from(arr, (b) => b.toString(16).padStart(2, "0")).join("");
}

function ymd(d = new Date()): string {
  return `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, "0")}${String(
    d.getDate(),
  ).padStart(2, "0")}`;
}

export function generateOrderNumber(): string {
  return `PS${ymd()}-${randHex(4).toUpperCase()}`;
}
export function generateSubscriptionNumber(): string {
  return `SUB${ymd()}-${randHex(3).toUpperCase()}`;
}
export function generateTransactionId(prefix = "TX"): string {
  return `${prefix}_${randHex(10)}`;
}
export function generateSessionId(): string {
  return randHex(32);
}
export function generateToken(bytes = 32): string {
  return randHex(bytes);
}

export function addMonths(date: Date, months: number): Date {
  const d = new Date(date);
  d.setMonth(d.getMonth() + months);
  return d;
}
