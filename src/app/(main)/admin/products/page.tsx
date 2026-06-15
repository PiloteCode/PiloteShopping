"use client";

import * as React from "react";
import Link from "next/link";
import { Loader2, Plus, Star, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Skeleton } from "@/components/ui/skeleton";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { AdminSelect } from "@/components/admin/admin-select";
import { apiDelete, apiGet, apiPatch, apiPost } from "@/lib/client";
import { formatPrice } from "@/lib/utils";
import { toast } from "sonner";

interface Product {
  id: number;
  name: string;
  slug: string;
  isActive: boolean;
  isFeatured: boolean;
  saleCount: number;
  ratingAverage: number;
  categoryId: number;
  productTypeId: number;
  minPrice: number;
  offerCount: number;
}
interface Taxonomy {
  id: number;
  name: string;
  slug: string;
}

const ACCESS_METHODS: { value: string; label: string }[] = [
  { value: "license_key", label: "Clé de licence" },
  { value: "account_required", label: "Compte requis" },
  { value: "api_access", label: "Accès API" },
  { value: "download_link", label: "Lien de téléchargement" },
  { value: "no_connection", label: "Sans connexion" },
];

export default function AdminProductsPage() {
  const [products, setProducts] = React.useState<Product[] | null>(null);
  const [categories, setCategories] = React.useState<Taxonomy[]>([]);
  const [types, setTypes] = React.useState<Taxonomy[]>([]);
  const [busy, setBusy] = React.useState<number | null>(null);
  const [deleting, setDeleting] = React.useState<Product | null>(null);
  const [createOpen, setCreateOpen] = React.useState(false);

  const load = React.useCallback(async () => {
    const data = await apiGet<{ products: Product[] }>("/api/v1/admin/products");
    setProducts(data.products);
  }, []);

  React.useEffect(() => {
    load().catch((e) => {
      setProducts([]);
      toast.error((e as Error).message);
    });
    apiGet<{ categories: Taxonomy[]; types: Taxonomy[] }>("/api/v1/categories")
      .then((d) => {
        setCategories(d.categories);
        setTypes(d.types);
      })
      .catch(() => {});
  }, [load]);

  async function toggle(p: Product, patch: { isActive?: boolean; isFeatured?: boolean }) {
    setBusy(p.id);
    try {
      await apiPatch(`/api/v1/admin/products/${p.id}`, patch);
      await load();
      toast.success("Produit mis à jour");
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(null);
    }
  }

  async function confirmDelete() {
    if (!deleting) return;
    setBusy(deleting.id);
    try {
      await apiDelete(`/api/v1/admin/products/${deleting.id}`);
      setDeleting(null);
      await load();
      toast.success("Produit supprimé");
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(null);
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-3xl font-bold">Produits</h1>
          <p className="mt-1 text-muted-foreground">
            {products ? `${products.length} produit${products.length > 1 ? "s" : ""}` : "Gestion du catalogue"}
          </p>
        </div>
        <Dialog open={createOpen} onOpenChange={setCreateOpen}>
          <DialogTrigger asChild>
            <Button>
              <Plus /> Nouveau produit
            </Button>
          </DialogTrigger>
          <CreateProductDialog
            categories={categories}
            types={types}
            onCreated={async () => {
              setCreateOpen(false);
              await load();
            }}
          />
        </Dialog>
      </div>

      {!products ? (
        <Skeleton className="h-96 w-full rounded-2xl" />
      ) : products.length === 0 ? (
        <div className="rounded-2xl border border-border bg-card px-6 py-16 text-center text-muted-foreground">
          Aucun produit pour le moment.
        </div>
      ) : (
        <div className="overflow-x-auto rounded-2xl border border-border bg-card">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                <th className="px-5 py-3 font-medium">Produit</th>
                <th className="px-5 py-3 text-right font-medium">Prix</th>
                <th className="px-5 py-3 text-right font-medium">Offres</th>
                <th className="px-5 py-3 text-right font-medium">Ventes</th>
                <th className="px-5 py-3 text-center font-medium">Actif</th>
                <th className="px-5 py-3 text-center font-medium">Mis en avant</th>
                <th className="px-5 py-3 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {products.map((p) => (
                <tr key={p.id} className="border-b border-border/60 last:border-0 hover:bg-accent/40">
                  <td className="px-5 py-3">
                    <Link href={`/products/${p.slug}`} className="font-medium hover:text-primary">
                      {p.name}
                    </Link>
                    <div className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                      <Star className="h-3 w-3 fill-warning text-warning" /> {p.ratingAverage.toFixed(1)}
                    </div>
                  </td>
                  <td className="px-5 py-3 text-right font-semibold">{formatPrice(p.minPrice)}</td>
                  <td className="px-5 py-3 text-right text-muted-foreground">{p.offerCount}</td>
                  <td className="px-5 py-3 text-right text-muted-foreground">{p.saleCount}</td>
                  <td className="px-5 py-3">
                    <div className="flex justify-center">
                      <Switch
                        checked={p.isActive}
                        disabled={busy === p.id}
                        onCheckedChange={(v) => toggle(p, { isActive: v })}
                        aria-label="Activer le produit"
                      />
                    </div>
                  </td>
                  <td className="px-5 py-3">
                    <div className="flex justify-center">
                      <Switch
                        checked={p.isFeatured}
                        disabled={busy === p.id}
                        onCheckedChange={(v) => toggle(p, { isFeatured: v })}
                        aria-label="Mettre en avant"
                      />
                    </div>
                  </td>
                  <td className="px-5 py-3">
                    <div className="flex justify-end">
                      <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8 text-muted-foreground hover:text-destructive"
                        onClick={() => setDeleting(p)}
                        aria-label="Supprimer"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Dialog open={!!deleting} onOpenChange={(o) => !o && setDeleting(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Supprimer le produit</DialogTitle>
            <DialogDescription>
              Voulez-vous vraiment supprimer{" "}
              <span className="font-medium text-foreground">{deleting?.name}</span> ? Cette action est irréversible.
            </DialogDescription>
          </DialogHeader>
          <div className="flex justify-end gap-2">
            <DialogClose asChild>
              <Button variant="outline">Annuler</Button>
            </DialogClose>
            <Button variant="destructive" onClick={confirmDelete} disabled={busy === deleting?.id}>
              {busy === deleting?.id ? <Loader2 className="animate-spin" /> : <Trash2 />} Supprimer
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function CreateProductDialog({
  categories,
  types,
  onCreated,
}: {
  categories: Taxonomy[];
  types: Taxonomy[];
  onCreated: () => Promise<void>;
}) {
  const [name, setName] = React.useState("");
  const [shortDescription, setShortDescription] = React.useState("");
  const [price, setPrice] = React.useState("");
  const [categoryId, setCategoryId] = React.useState("");
  const [productTypeId, setProductTypeId] = React.useState("");
  const [accessMethod, setAccessMethod] = React.useState("license_key");
  const [submitting, setSubmitting] = React.useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!categoryId || !productTypeId) {
      toast.error("Sélectionnez une catégorie et un type");
      return;
    }
    setSubmitting(true);
    try {
      await apiPost("/api/v1/admin/products", {
        name,
        categoryId: Number(categoryId),
        productTypeId: Number(productTypeId),
        shortDescription,
        price: Number(price),
        accessMethod,
      });
      toast.success("Produit créé");
      await onCreated();
    } catch (err) {
      toast.error((err as Error).message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <DialogContent className="max-w-lg">
      <DialogHeader>
        <DialogTitle>Nouveau produit</DialogTitle>
        <DialogDescription>Créez un produit avec une offre standard.</DialogDescription>
      </DialogHeader>
      <form onSubmit={submit} className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="np-name">Nom</Label>
          <Input id="np-name" value={name} onChange={(e) => setName(e.target.value)} required minLength={2} maxLength={255} placeholder="Ex. Licence Office 365" />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="np-desc">Description courte</Label>
          <Input id="np-desc" value={shortDescription} onChange={(e) => setShortDescription(e.target.value)} required minLength={2} maxLength={500} placeholder="Résumé du produit" />
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="np-price">Prix (€)</Label>
            <Input id="np-price" type="number" min={0} step="0.01" value={price} onChange={(e) => setPrice(e.target.value)} required placeholder="0.00" />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="np-access">Méthode d&apos;accès</Label>
            <AdminSelect id="np-access" value={accessMethod} onChange={(e) => setAccessMethod(e.target.value)}>
              {ACCESS_METHODS.map((m) => (
                <option key={m.value} value={m.value}>
                  {m.label}
                </option>
              ))}
            </AdminSelect>
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="np-category">Catégorie</Label>
            <AdminSelect id="np-category" value={categoryId} onChange={(e) => setCategoryId(e.target.value)} required>
              <option value="" disabled>
                Sélectionner…
              </option>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </AdminSelect>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="np-type">Type</Label>
            <AdminSelect id="np-type" value={productTypeId} onChange={(e) => setProductTypeId(e.target.value)} required>
              <option value="" disabled>
                Sélectionner…
              </option>
              {types.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name}
                </option>
              ))}
            </AdminSelect>
          </div>
        </div>
        <div className="flex justify-end gap-2 pt-2">
          <DialogClose asChild>
            <Button type="button" variant="outline">
              Annuler
            </Button>
          </DialogClose>
          <Button type="submit" disabled={submitting}>
            {submitting ? <Loader2 className="animate-spin" /> : <Plus />} Créer
          </Button>
        </div>
      </form>
    </DialogContent>
  );
}
