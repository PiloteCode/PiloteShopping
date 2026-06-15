import { z } from "zod";
import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";
import { manageSubscription } from "@/server/account";

export const POST = route(async (req, ctx: { params: Promise<{ id: string }> }) => {
  await assertCsrf(req);
  const user = await requireUser();
  const id = Number((await ctx.params).id);
  if (!id) throw badRequest("Identifiant invalide");
  const { action } = await readBody(req, z.object({ action: z.enum(["pause", "resume", "cancel"]) }));
  const result = await manageSubscription(user.id, id, action);
  return success(result, "Abonnement mis à jour");
});
