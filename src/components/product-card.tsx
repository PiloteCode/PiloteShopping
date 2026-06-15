"use client";

import Link from "next/link";
import { motion } from "framer-motion";
import { Star, Zap } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { DynamicIcon } from "@/components/icon";
import { AddToCartButton } from "@/components/add-to-cart-button";
import { formatPrice } from "@/lib/utils";
import type { ProductCard as ProductCardDTO } from "@/server/products";

export function ProductCard({ product, index = 0 }: { product: ProductCardDTO; index?: number }) {
  const discount =
    product.compareAtPrice && product.compareAtPrice > product.fromPrice
      ? Math.round((1 - product.fromPrice / product.compareAtPrice) * 100)
      : 0;

  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-40px" }}
      transition={{ duration: 0.35, delay: Math.min(index * 0.04, 0.3) }}
      className="group relative flex flex-col overflow-hidden rounded-2xl border border-border bg-card transition-all hover:border-primary/40 hover:shadow-xl hover:shadow-primary/5"
    >
      <Link href={`/products/${product.slug}`} className="relative block aspect-[4/3] overflow-hidden bg-muted">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={product.image ?? "/placeholder.svg"}
          alt={product.name}
          className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
        />
        <div className="absolute left-3 top-3 flex flex-col gap-1.5">
          {product.isFeatured && (
            <Badge className="bg-chart-4/20 text-chart-4 backdrop-blur">★ Populaire</Badge>
          )}
          {discount > 0 && <Badge variant="destructive">-{discount}%</Badge>}
        </div>
        {product.category && (
          <div className="absolute bottom-3 left-3">
            <span
              className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium backdrop-blur"
              style={{ backgroundColor: (product.category.color ?? "#0ea5e9") + "26", color: product.category.color ?? "#0ea5e9" }}
            >
              <DynamicIcon name={product.category.icon} className="h-3.5 w-3.5" />
              {product.category.name}
            </span>
          </div>
        )}
      </Link>

      <div className="flex flex-1 flex-col p-4">
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          {product.ratingCount > 0 ? (
            <span className="flex items-center gap-1">
              <Star className="h-3.5 w-3.5 fill-chart-3 text-chart-3" />
              {product.ratingAverage.toFixed(1)} ({product.ratingCount})
            </span>
          ) : (
            <span className="flex items-center gap-1"><Zap className="h-3.5 w-3.5 text-success" /> Livraison instantanée</span>
          )}
        </div>

        <Link href={`/products/${product.slug}`} className="mt-1.5">
          <h3 className="line-clamp-1 font-semibold transition-colors group-hover:text-primary">{product.name}</h3>
        </Link>
        <p className="mt-1 line-clamp-2 flex-1 text-sm text-muted-foreground">{product.shortDescription}</p>

        <div className="mt-4 flex items-end justify-between gap-2">
          <div>
            <p className="text-xs text-muted-foreground">À partir de</p>
            <div className="flex items-baseline gap-1.5">
              <span className="text-xl font-bold">{formatPrice(product.fromPrice)}</span>
              {product.compareAtPrice && product.compareAtPrice > product.fromPrice && (
                <span className="text-sm text-muted-foreground line-through">{formatPrice(product.compareAtPrice)}</span>
              )}
            </div>
          </div>
          <AddToCartButton
            productId={product.id}
            offerId={product.defaultOfferId}
            size="icon"
            label=""
            aria-label="Ajouter au panier"
          />
        </div>
      </div>
    </motion.div>
  );
}
