"use client";

import * as React from "react";
import { motion } from "framer-motion";
import { ArrowDownLeft, ArrowUpRight, Loader2, Plus, Sparkles, Wallet } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { apiGet, apiPost } from "@/lib/client";
import { cn, formatPrice, timeAgo } from "@/lib/utils";
import { toast } from "sonner";

interface Transaction {
  id: number;
  type: string;
  amount: number;
  description: string | null;
  balanceAfter: number;
  createdAt: string;
}
interface WalletData {
  balance: number;
  loyaltyPoints: number;
  transactions: Transaction[];
}

const PRESETS = [10, 25, 50, 100];
const CREDIT_TYPES = ["credit", "bonus", "refund", "deposit"];

export default function WalletPage() {
  const [wallet, setWallet] = React.useState<WalletData | null>(null);
  const [amount, setAmount] = React.useState<number>(25);
  const [custom, setCustom] = React.useState("");
  const [busy, setBusy] = React.useState(false);

  const load = React.useCallback(async () => {
    setWallet(await apiGet<WalletData>("/api/v1/wallet"));
  }, []);

  React.useEffect(() => {
    load().catch(() => setWallet({ balance: 0, loyaltyPoints: 0, transactions: [] }));
  }, [load]);

  async function recharge() {
    const value = custom ? parseFloat(custom) : amount;
    if (!Number.isFinite(value) || value < 5) {
      toast.error("Le montant minimum est de 5 €");
      return;
    }
    setBusy(true);
    try {
      await apiPost("/api/v1/wallet", { amount: value });
      toast.success(`${formatPrice(value)} ajoutés à votre solde`);
      setCustom("");
      await load();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(false);
    }
  }

  if (!wallet) return <WalletSkeleton />;

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold sm:text-3xl">Portefeuille</h1>
        <p className="mt-1 text-muted-foreground">Gérez votre solde et vos points de fidélité.</p>
      </header>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="glow-primary relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary/15 via-card to-chart-4/10 p-6">
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Wallet className="h-4 w-4" /> Solde disponible
          </div>
          <p className="mt-3 text-4xl font-bold tracking-tight">{formatPrice(wallet.balance)}</p>
        </div>
        <div className="rounded-2xl border border-border bg-card p-6">
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Sparkles className="h-4 w-4 text-warning" /> Points de fidélité
          </div>
          <p className="mt-3 text-4xl font-bold tracking-tight">{wallet.loyaltyPoints}</p>
          <p className="mt-1 text-xs text-muted-foreground">Gagnez des points à chaque commande.</p>
        </div>
      </div>

      {/* Recharge */}
      <div className="rounded-2xl border border-border bg-card p-6">
        <h2 className="font-semibold">Recharger mon portefeuille</h2>
        <p className="mt-1 text-sm text-muted-foreground">Choisissez un montant ou saisissez le vôtre (min. 5 €).</p>

        <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
          {PRESETS.map((p) => (
            <button
              key={p}
              type="button"
              onClick={() => {
                setAmount(p);
                setCustom("");
              }}
              className={cn(
                "rounded-xl border px-4 py-3 text-center font-semibold transition-all",
                !custom && amount === p
                  ? "border-primary bg-primary/10 text-primary"
                  : "border-border bg-background/40 hover:border-primary/40",
              )}
            >
              {formatPrice(p)}
            </button>
          ))}
        </div>

        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
          <div className="flex-1">
            <Label htmlFor="custom-amount">Montant personnalisé</Label>
            <Input
              id="custom-amount"
              type="number"
              min={5}
              step="1"
              inputMode="decimal"
              value={custom}
              onChange={(e) => setCustom(e.target.value)}
              placeholder="Ex. 75"
              className="mt-1.5"
            />
          </div>
          <Button onClick={recharge} disabled={busy} size="lg" className="sm:w-auto">
            {busy ? <Loader2 className="animate-spin" /> : <Plus />} Recharger
          </Button>
        </div>
      </div>

      {/* History */}
      <div className="rounded-2xl border border-border bg-card">
        <h2 className="border-b border-border p-5 font-semibold">Historique des transactions</h2>
        {wallet.transactions.length === 0 ? (
          <p className="px-5 py-10 text-center text-sm text-muted-foreground">Aucune transaction pour le moment.</p>
        ) : (
          <ul className="divide-y divide-border">
            {wallet.transactions.map((t, i) => {
              const isCredit = CREDIT_TYPES.includes(t.type) || t.amount > 0;
              return (
                <motion.li
                  key={t.id}
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  transition={{ duration: 0.2, delay: Math.min(i * 0.02, 0.2) }}
                  className="flex items-center gap-3 p-4"
                >
                  <span
                    className={cn(
                      "grid h-9 w-9 shrink-0 place-items-center rounded-xl",
                      isCredit ? "bg-success/10 text-success" : "bg-destructive/10 text-destructive",
                    )}
                  >
                    {isCredit ? <ArrowDownLeft className="h-4 w-4" /> : <ArrowUpRight className="h-4 w-4" />}
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{t.description ?? "Transaction"}</p>
                    <p className="text-xs text-muted-foreground">{timeAgo(t.createdAt)}</p>
                  </div>
                  <div className="shrink-0 text-right">
                    <p className={cn("font-semibold", isCredit ? "text-success" : "text-destructive")}>
                      {isCredit ? "+" : "−"} {formatPrice(Math.abs(t.amount))}
                    </p>
                    <p className="text-xs text-muted-foreground">Solde : {formatPrice(t.balanceAfter)}</p>
                  </div>
                </motion.li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
}

function WalletSkeleton() {
  return (
    <div className="space-y-6">
      <Skeleton className="h-9 w-48" />
      <div className="grid gap-4 sm:grid-cols-2">
        <Skeleton className="h-32 w-full rounded-2xl" />
        <Skeleton className="h-32 w-full rounded-2xl" />
      </div>
      <Skeleton className="h-56 w-full rounded-2xl" />
      <Skeleton className="h-64 w-full rounded-2xl" />
    </div>
  );
}
