"use client";

import * as React from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Search, SlidersHorizontal, X } from "lucide-react";

interface Category { name: string; slug: string }

const SORTS = [
  { value: "popular", label: "Popularité" },
  { value: "newest", label: "Nouveautés" },
  { value: "price_asc", label: "Prix croissant" },
  { value: "price_desc", label: "Prix décroissant" },
  { value: "rating", label: "Mieux notés" },
];
const BILLINGS = [
  { value: "all", label: "Tous les types" },
  { value: "one_time", label: "Achat unique" },
  { value: "subscription", label: "Abonnements" },
];

export function ProductFilters({ categories }: { categories: Category[] }) {
  const router = useRouter();
  const params = useSearchParams();
  const [q, setQ] = React.useState(params.get("q") ?? "");

  function update(next: Record<string, string | null>) {
    const sp = new URLSearchParams(params.toString());
    for (const [k, v] of Object.entries(next)) {
      if (v == null || v === "" || v === "all") sp.delete(k);
      else sp.set(k, v);
    }
    sp.delete("page");
    router.push(`/products?${sp.toString()}`);
  }

  const selectCls =
    "h-10 rounded-lg border border-border bg-card px-3 text-sm outline-none transition-colors focus:border-ring focus:ring-2 focus:ring-ring/40";
  const activeCategory = params.get("category") ?? "";
  const hasFilters = params.toString().length > 0;

  return (
    <div className="space-y-4">
      <form
        onSubmit={(e) => { e.preventDefault(); update({ q }); }}
        className="relative"
      >
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Rechercher…"
          className="h-10 w-full rounded-lg border border-border bg-card pl-9 pr-3 text-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/40"
        />
      </form>

      <div className="flex flex-wrap items-center gap-2.5">
        <span className="flex items-center gap-1.5 text-sm text-muted-foreground"><SlidersHorizontal className="h-4 w-4" /> Filtres :</span>
        <select className={selectCls} value={activeCategory} onChange={(e) => update({ category: e.target.value })}>
          <option value="">Toutes catégories</option>
          {categories.map((c) => (
            <option key={c.slug} value={c.slug}>{c.name}</option>
          ))}
        </select>
        <select className={selectCls} value={params.get("billing") ?? "all"} onChange={(e) => update({ billing: e.target.value })}>
          {BILLINGS.map((b) => <option key={b.value} value={b.value}>{b.label}</option>)}
        </select>
        <select className={selectCls} value={params.get("sort") ?? "popular"} onChange={(e) => update({ sort: e.target.value })}>
          {SORTS.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
        </select>
        {hasFilters && (
          <button onClick={() => { setQ(""); router.push("/products"); }} className="flex items-center gap-1 rounded-lg px-2.5 py-2 text-sm text-muted-foreground transition-colors hover:text-destructive">
            <X className="h-4 w-4" /> Réinitialiser
          </button>
        )}
      </div>
    </div>
  );
}
