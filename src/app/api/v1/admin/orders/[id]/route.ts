import { z } from "zod";
import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { requireAdmin } from "@/lib/auth";
import { updateAdminOrder } from "@/server/admin";

export const PATCH = route(async (req, ctx: { params: Promise<{ id: string }> }) => {
  await assertCsrf(req);
  await requireAdmin();
  const id = Number((await ctx.params).id);
  if (!id) throw badRequest("Identifiant invalide");
  const patch = await readBody(
    req,
    z.object({
      status: z.enum(["pending", "processing", "completed", "cancelled"]).optional(),
      paymentStatus: z.enum(["pending", "paid", "failed", "refunded"]).optional(),
    }),
  );
  return success(await updateAdminOrder(id, patch), "Commande mise à jour");
});
