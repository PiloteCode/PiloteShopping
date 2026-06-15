import Link from "next/link";
import { notFound } from "next/navigation";
import { Check, ChevronRight, Clock, ShieldCheck, Star, Zap } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { PurchasePanel } from "@/components/product/purchase-panel";
import { ProductCard } from "@/components/product-card";
import { ProductGallery } from "@/components/product/gallery";
import { DynamicIcon } from "@/components/icon";
import { getProductBySlug, getRecommended } from "@/server/products";
import { deliveryLabel, accessLabel } from "@/lib/domain";
import { formatPrice, timeAgo } from "@/lib/utils";

export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const product = await getProductBySlug((await params).slug);
  return { title: product?.name ?? "Produit", description: product?.shortDescription };
}

export default async function ProductPage({ params }: { params: Promise<{ slug: string }> }) {
  const product = await getProductBySlug((await params).slug);
  if (!product) notFound();

  const recommended = (await getRecommended({ categoryId: product.category?.id, limit: 4, exclude: [product.id] })).slice(0, 4);
  const firstAccess = product.accessTypes[0];

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 lg:px-6">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-1.5 text-sm text-muted-foreground">
        <Link href="/products" className="hover:text-foreground">Boutique</Link>
        {product.category && (
          <>
            <ChevronRight className="h-4 w-4" />
            <Link href={`/products?category=${product.category.slug}`} className="hover:text-foreground">{product.category.name}</Link>
          </>
        )}
        <ChevronRight className="h-4 w-4" />
        <span className="text-foreground">{product.name}</span>
      </nav>

      <div className="mt-6 grid gap-8 lg:grid-cols-2">
        <ProductGallery images={product.images.length ? product.images : ["/placeholder.svg"]} name={product.name} />

        <div>
          <div className="flex flex-wrap items-center gap-2">
            {product.category && (
              <span className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" style={{ backgroundColor: (product.category.color ?? "#0ea5e9") + "22", color: product.category.color ?? "#0ea5e9" }}>
                <DynamicIcon name={product.category.icon} className="h-3.5 w-3.5" /> {product.category.name}
              </span>
            )}
            {product.type && <Badge variant="outline">{product.type.name}</Badge>}
          </div>

          <h1 className="mt-3 text-3xl font-bold">{product.name}</h1>

          <div className="mt-3 flex items-center gap-4 text-sm">
            {product.ratingCount > 0 ? (
              <span className="flex items-center gap-1">
                {Array.from({ length: 5 }).map((_, i) => (
                  <Star key={i} className={`h-4 w-4 ${i < Math.round(product.ratingAverage) ? "fill-chart-3 text-chart-3" : "text-muted-foreground/30"}`} />
                ))}
                <span className="ml-1 text-muted-foreground">{product.ratingAverage.toFixed(1)} · {product.ratingCount} avis</span>
              </span>
            ) : (
              <span className="text-muted-foreground">Nouveau produit</span>
            )}
            <span className="text-muted-foreground">·</span>
            <span className="text-muted-foreground">{product.saleCount} vendus</span>
          </div>

          <p className="mt-4 text-muted-foreground">{product.shortDescription}</p>

          {firstAccess && (
            <div className="mt-4 flex flex-wrap gap-2">
              <Badge variant="secondary" className="gap-1.5"><Zap className="h-3.5 w-3.5 text-primary" /> {deliveryLabel(firstAccess.deliveryMethod)}</Badge>
              <Badge variant="secondary" className="gap-1.5"><ShieldCheck className="h-3.5 w-3.5 text-success" /> {accessLabel(firstAccess.accessMethod)}</Badge>
              <Badge variant="secondary" className="gap-1.5"><Clock className="h-3.5 w-3.5 text-chart-4" /> Garantie incluse</Badge>
            </div>
          )}

          <div className="mt-6">
            <PurchasePanel productId={product.id} accessTypes={product.accessTypes} />
          </div>
        </div>
      </div>

      {/* Details */}
      <div className="mt-12 grid gap-10 lg:grid-cols-3">
        <div className="space-y-10 lg:col-span-2">
          {product.description && (
            <section>
              <h2 className="text-xl font-bold">Description</h2>
              <p className="mt-3 whitespace-pre-line leading-relaxed text-muted-foreground">{product.description}</p>
            </section>
          )}

          {product.features.length > 0 && (
            <section>
              <h2 className="text-xl font-bold">Caractéristiques</h2>
              <ul className="mt-4 grid gap-3 sm:grid-cols-2">
                {product.features.map((f) => (
                  <li key={f} className="flex items-start gap-2.5 rounded-lg border border-border bg-card p-3">
                    <Check className="mt-0.5 h-4 w-4 shrink-0 text-success" />
                    <span className="text-sm">{f}</span>
                  </li>
                ))}
              </ul>
            </section>
          )}

          {firstAccess?.instructions && (
            <section>
              <h2 className="text-xl font-bold">Instructions d&apos;activation</h2>
              <div className="mt-3 rounded-xl border border-border bg-card p-5 text-sm text-muted-foreground">{firstAccess.instructions}</div>
            </section>
          )}

          {product.reviews.length > 0 && (
            <section>
              <h2 className="text-xl font-bold">Avis clients</h2>
              <div className="mt-4 space-y-4">
                {product.reviews.map((r) => (
                  <div key={r.id} className="rounded-xl border border-border bg-card p-4">
                    <div className="flex items-center justify-between">
                      <span className="font-medium">{r.author}</span>
                      <span className="flex items-center gap-0.5">
                        {Array.from({ length: 5 }).map((_, i) => (
                          <Star key={i} className={`h-3.5 w-3.5 ${i < r.rating ? "fill-chart-3 text-chart-3" : "text-muted-foreground/30"}`} />
                        ))}
                      </span>
                    </div>
                    {r.title && <p className="mt-2 font-medium">{r.title}</p>}
                    {r.comment && <p className="mt-1 text-sm text-muted-foreground">{r.comment}</p>}
                    <p className="mt-2 text-xs text-muted-foreground">{timeAgo(r.createdAt)}</p>
                  </div>
                ))}
              </div>
            </section>
          )}
        </div>

        {/* Sidebar trust */}
        <aside className="space-y-4">
          <div className="rounded-2xl border border-border bg-card p-5">
            <h3 className="font-semibold">Pourquoi nous faire confiance ?</h3>
            <ul className="mt-4 space-y-3 text-sm text-muted-foreground">
              <li className="flex items-center gap-2.5"><Zap className="h-4 w-4 text-primary" /> Livraison automatique 24/7</li>
              <li className="flex items-center gap-2.5"><ShieldCheck className="h-4 w-4 text-success" /> Paiement 100% sécurisé</li>
              <li className="flex items-center gap-2.5"><Check className="h-4 w-4 text-chart-4" /> Garantie de remplacement</li>
              <li className="flex items-center gap-2.5"><Star className="h-4 w-4 text-chart-3" /> Support réactif 7j/7</li>
            </ul>
          </div>
        </aside>
      </div>

      {/* Recommended */}
      {recommended.length > 0 && (
        <section className="mt-16">
          <h2 className="mb-6 text-2xl font-bold">Vous aimerez aussi</h2>
          <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {recommended.map((p, i) => <ProductCard key={p.id} product={p} index={i} />)}
          </div>
        </section>
      )}
    </div>
  );
}
