import { z } from "zod";
import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { requireAdmin } from "@/lib/auth";
import { deleteAdminProduct, updateAdminProduct } from "@/server/admin";

export const PATCH = route(async (req, ctx: { params: Promise<{ id: string }> }) => {
  await assertCsrf(req);
  await requireAdmin();
  const id = Number((await ctx.params).id);
  if (!id) throw badRequest("Identifiant invalide");
  const patch = await readBody(
    req,
    z.object({
      isActive: z.boolean().optional(),
      isFeatured: z.boolean().optional(),
      name: z.string().trim().min(2).max(255).optional(),
      shortDescription: z.string().trim().max(500).optional(),
    }),
  );
  return success(await updateAdminProduct(id, patch), "Produit mis à jour");
});

export const DELETE = route(async (req, ctx: { params: Promise<{ id: string }> }) => {
  await assertCsrf(req);
  await requireAdmin();
  const id = Number((await ctx.params).id);
  if (!id) throw badRequest("Identifiant invalide");
  return success(await deleteAdminProduct(id), "Produit supprimé");
});
