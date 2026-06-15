import { z } from "zod";
import { and, desc, eq, sql } from "drizzle-orm";
import { db } from "@/db";
import { products, wishlists } from "@/db/schema";
import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";
import { getProductsByIds } from "@/server/products";

export const GET = route(async () => {
  const user = await requireUser();
  const rows = await db
    .select({ productId: wishlists.productId, addedAt: wishlists.addedAt })
    .from(wishlists)
    .where(eq(wishlists.userId, user.id))
    .orderBy(desc(wishlists.addedAt));

  const cards = await getProductsByIds(rows.map((r) => r.productId));
  const items = cards.map((c, i) => ({ ...c, addedAt: rows[i]?.addedAt?.toISOString() ?? null }));
  return success({ items, count: items.length });
});

export const POST = route(async (req) => {
  await assertCsrf(req);
  const user = await requireUser();
  const { productId, offerId } = await readBody(
    req,
    z.object({ productId: z.coerce.number().int().positive(), offerId: z.coerce.number().int().optional() }),
  );

  const prod = await db.select({ id: products.id }).from(products).where(eq(products.id, productId)).limit(1);
  if (!prod[0]) throw badRequest("Produit introuvable");

  const existing = await db
    .select({ id: wishlists.id })
    .from(wishlists)
    .where(and(eq(wishlists.userId, user.id), eq(wishlists.productId, productId)))
    .limit(1);

  if (existing[0]) return success({ alreadyExists: true }, "Déjà dans votre liste de souhaits");

  await db.insert(wishlists).values({ userId: user.id, productId, offerId: offerId ?? null });
  await db.update(products).set({ wishlistCount: sql`${products.wishlistCount} + 1` }).where(eq(products.id, productId));
  return success({ added: true }, "Ajouté à la liste de souhaits");
});
