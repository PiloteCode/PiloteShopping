"use client";

import * as React from "react";
import Link from "next/link";
import { motion } from "framer-motion";
import { ArrowRight, Heart, Loader2, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { ProductCard } from "@/components/product-card";
import { apiDelete, apiGet } from "@/lib/client";
import { toast } from "sonner";
import type { ProductCard as ProductCardDTO } from "@/server/products";

type WishlistItem = ProductCardDTO & { addedAt: string | null };
interface WishlistResponse {
  items: WishlistItem[];
  count: number;
}

export default function WishlistPage() {
  const [items, setItems] = React.useState<WishlistItem[] | null>(null);
  const [removing, setRemoving] = React.useState<number | null>(null);

  const load = React.useCallback(async () => {
    const d = await apiGet<WishlistResponse>("/api/v1/wishlist");
    setItems(d.items);
  }, []);

  React.useEffect(() => {
    load().catch(() => setItems([]));
  }, [load]);

  async function remove(productId: number) {
    setRemoving(productId);
    try {
      await apiDelete(`/api/v1/wishlist/${productId}`);
      toast.success("Retiré de la liste de souhaits");
      await load();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setRemoving(null);
    }
  }

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold sm:text-3xl">Liste de souhaits</h1>
        <p className="mt-1 text-muted-foreground">
          {items && items.length > 0
            ? `${items.length} produit${items.length > 1 ? "s" : ""} sauvegardé${items.length > 1 ? "s" : ""}`
            : "Retrouvez ici les produits que vous avez sauvegardés."}
        </p>
      </header>

      {items === null ? (
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {[0, 1, 2].map((i) => <Skeleton key={i} className="h-80 w-full rounded-2xl" />)}
        </div>
      ) : items.length === 0 ? (
        <div className="flex flex-col items-center rounded-2xl border border-border bg-card px-4 py-16 text-center">
          <span className="grid h-16 w-16 place-items-center rounded-2xl bg-muted">
            <Heart className="h-7 w-7 text-muted-foreground" />
          </span>
          <h2 className="mt-5 text-lg font-semibold">Votre liste est vide</h2>
          <p className="mt-1 text-muted-foreground">Ajoutez des produits à votre liste de souhaits pour les retrouver ici.</p>
          <Button className="mt-6" asChild>
            <Link href="/products">Découvrir la boutique <ArrowRight /></Link>
          </Button>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {items.map((item, i) => (
            <div key={item.id} className="relative">
              <ProductCard product={item} index={i} />
              <motion.button
                type="button"
                onClick={() => remove(item.id)}
                disabled={removing === item.id}
                whileTap={{ scale: 0.92 }}
                aria-label="Retirer de la liste de souhaits"
                className="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full border border-border bg-card/80 text-muted-foreground backdrop-blur transition-colors hover:border-destructive/40 hover:text-destructive disabled:opacity-60"
              >
                {removing === item.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <X className="h-4 w-4" />}
              </motion.button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
