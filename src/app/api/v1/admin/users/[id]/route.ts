import { z } from "zod";
import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { requireAdmin } from "@/lib/auth";
import { updateAdminUser } from "@/server/admin";

export const PATCH = route(async (req, ctx: { params: Promise<{ id: string }> }) => {
  await assertCsrf(req);
  await requireAdmin();
  const id = Number((await ctx.params).id);
  if (!id) throw badRequest("Identifiant invalide");
  const patch = await readBody(
    req,
    z.object({ isActive: z.boolean().optional(), isVerified: z.boolean().optional() }),
  );
  return success(await updateAdminUser(id, patch), "Utilisateur mis à jour");
});
