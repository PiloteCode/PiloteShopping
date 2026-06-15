import Link from "next/link";
import { PackageX } from "lucide-react";
import { ProductCard } from "@/components/product-card";
import { ProductFilters } from "@/components/catalog/product-filters";
import { Button } from "@/components/ui/button";
import { getCategories, listProducts, type ListParams } from "@/server/products";

export const dynamic = "force-dynamic";

export const metadata = { title: "Boutique" };

export default async function ProductsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | undefined>>;
}) {
  const sp = await searchParams;
  const page = Math.max(1, Number(sp.page) || 1);
  const params: ListParams = {
    category: sp.category,
    type: sp.type,
    q: sp.q,
    sort: (sp.sort as ListParams["sort"]) ?? "popular",
    billing: (sp.billing as ListParams["billing"]) ?? "all",
    page,
    perPage: 12,
  };

  const [{ items, total, pages }, categories] = await Promise.all([
    listProducts(params),
    getCategories(),
  ]);

  function pageHref(p: number) {
    const q = new URLSearchParams(sp as Record<string, string>);
    q.set("page", String(p));
    return `/products?${q.toString()}`;
  }

  return (
    <div className="mx-auto max-w-7xl px-4 py-10 lg:px-6">
      <div className="mb-8">
        <h1 className="text-3xl font-bold">Boutique</h1>
        <p className="mt-1 text-muted-foreground">{total} produit{total > 1 ? "s" : ""} disponible{total > 1 ? "s" : ""}</p>
      </div>

      <div className="mb-8">
        <ProductFilters categories={categories.map((c) => ({ name: c.name, slug: c.slug }))} />
      </div>

      {items.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border py-20 text-center">
          <PackageX className="h-10 w-10 text-muted-foreground" />
          <p className="mt-3 font-medium">Aucun produit ne correspond à votre recherche</p>
          <Button variant="outline" className="mt-4" asChild><Link href="/products">Voir tout le catalogue</Link></Button>
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {items.map((p, i) => (
              <ProductCard key={p.id} product={p} index={i} />
            ))}
          </div>

          {pages > 1 && (
            <div className="mt-10 flex items-center justify-center gap-2">
              {Array.from({ length: pages }, (_, i) => i + 1).map((p) => (
                <Button key={p} variant={p === page ? "default" : "outline"} size="icon" asChild>
                  <Link href={pageHref(p)}>{p}</Link>
                </Button>
              ))}
            </div>
          )}
        </>
      )}
    </div>
  );
}
