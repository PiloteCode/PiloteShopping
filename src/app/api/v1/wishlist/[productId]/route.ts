import { and, eq, sql } from "drizzle-orm";
import { db } from "@/db";
import { products, wishlists } from "@/db/schema";
import { assertCsrf, badRequest, route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";

export const DELETE = route(async (req, ctx: { params: Promise<{ productId: string }> }) => {
  await assertCsrf(req);
  const user = await requireUser();
  const productId = Number((await ctx.params).productId);
  if (!productId) throw badRequest("Identifiant invalide");

  const deleted = await db
    .delete(wishlists)
    .where(and(eq(wishlists.userId, user.id), eq(wishlists.productId, productId)))
    .returning({ id: wishlists.id });

  if (deleted.length) {
    await db
      .update(products)
      .set({ wishlistCount: sql`GREATEST(${products.wishlistCount} - 1, 0)` })
      .where(eq(products.id, productId));
  }
  return success({ removed: deleted.length > 0 }, "Retiré de la liste de souhaits");
});
