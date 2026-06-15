"use client";

import * as React from "react";
import { Check, Loader2, ShoppingCart } from "lucide-react";
import { Button, type ButtonProps } from "@/components/ui/button";
import { useCart } from "@/components/providers";
import { apiPost } from "@/lib/client";
import { toast } from "sonner";

interface Props extends ButtonProps {
  productId: number;
  offerId: number | null;
  quantity?: number;
  label?: string;
  customData?: Record<string, unknown>;
}

export function AddToCartButton({ productId, offerId, quantity = 1, label = "Ajouter au panier", customData, ...buttonProps }: Props) {
  const { refresh, setCount } = useCart();
  const [state, setState] = React.useState<"idle" | "loading" | "done">("idle");

  async function add() {
    if (!offerId) return;
    setState("loading");
    try {
      const data = await apiPost<{ summary: { itemCount: number } }>("/api/v1/cart", { productId, offerId, quantity, customData });
      setCount(data.summary.itemCount);
      setState("done");
      toast.success("Ajouté au panier", { description: label === "Ajouter au panier" ? undefined : label });
      setTimeout(() => setState("idle"), 1600);
      refresh();
    } catch (e) {
      setState("idle");
      toast.error((e as Error).message);
    }
  }

  return (
    <Button onClick={add} disabled={state === "loading" || !offerId} {...buttonProps}>
      {state === "loading" ? (
        <Loader2 className="animate-spin" />
      ) : state === "done" ? (
        <Check />
      ) : (
        <ShoppingCart />
      )}
      {state === "done" ? "Ajouté !" : label}
    </Button>
  );
}
