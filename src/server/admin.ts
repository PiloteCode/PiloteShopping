import "server-only";
import { and, count, desc, eq, gte, sql, sum } from "drizzle-orm";
import { db } from "@/db";
import {
  orders,
  productAccessTypes,
  productOffers,
  products,
  subscriptions,
  users,
} from "@/db/schema";
import { notFound } from "@/lib/api";
import { slugify } from "@/lib/utils";

const n = (v: unknown) => Number(v ?? 0);

export async function getAdminStats() {
  const since7 = new Date(Date.now() - 7 * 86400_000);

  const [[u], [v], [o], [paid], [rev], [rev7], [p], [pa], [subs]] = await Promise.all([
    db.select({ c: count() }).from(users),
    db.select({ c: count() }).from(users).where(eq(users.isVerified, true)),
    db.select({ c: count() }).from(orders),
    db.select({ c: count() }).from(orders).where(eq(orders.paymentStatus, "paid")),
    db.select({ s: sum(orders.totalAmount) }).from(orders).where(eq(orders.paymentStatus, "paid")),
    db.select({ s: sum(orders.totalAmount) }).from(orders).where(and(eq(orders.paymentStatus, "paid"), gte(orders.createdAt, since7))),
    db.select({ c: count() }).from(products),
    db.select({ c: count() }).from(products).where(eq(products.isActive, true)),
    db.select({ c: count() }).from(subscriptions).where(eq(subscriptions.status, "active")),
  ]);

  const recentOrders = await db
    .select({
      orderNumber: orders.orderNumber,
      total: orders.totalAmount,
      status: orders.status,
      paymentStatus: orders.paymentStatus,
      createdAt: orders.createdAt,
      email: users.email,
    })
    .from(orders)
    .leftJoin(users, eq(users.id, orders.userId))
    .orderBy(desc(orders.createdAt))
    .limit(8);

  const topProducts = await db
    .select({ name: products.name, slug: products.slug, saleCount: products.saleCount })
    .from(products)
    .orderBy(desc(products.saleCount))
    .limit(5);

  return {
    users: { total: n(u.c), verified: n(v.c) },
    orders: { total: n(o.c), paid: n(paid.c) },
    revenue: { total: n(rev.s), last7Days: n(rev7.s) },
    products: { total: n(p.c), active: n(pa.c) },
    subscriptions: { active: n(subs.c) },
    recentOrders: recentOrders.map((r) => ({
      orderNumber: r.orderNumber,
      total: n(r.total),
      status: r.status,
      paymentStatus: r.paymentStatus,
      email: r.email ?? "Invité",
      createdAt: r.createdAt.toISOString(),
    })),
    topProducts: topProducts.map((t) => ({ ...t, saleCount: n(t.saleCount) })),
  };
}

export async function listAdminProducts() {
  const rows = await db
    .select({
      id: products.id,
      name: products.name,
      slug: products.slug,
      isActive: products.isActive,
      isFeatured: products.isFeatured,
      saleCount: products.saleCount,
      ratingAverage: products.ratingAverage,
      categoryId: products.categoryId,
      productTypeId: products.productTypeId,
      minPrice: sql<number>`COALESCE(MIN(${productOffers.price}), 0)`,
      offerCount: sql<number>`COUNT(${productOffers.id})`,
    })
    .from(products)
    .leftJoin(productAccessTypes, eq(productAccessTypes.productId, products.id))
    .leftJoin(productOffers, eq(productOffers.accessTypeId, productAccessTypes.id))
    .groupBy(products.id)
    .orderBy(desc(products.id));

  return rows.map((r) => ({ ...r, minPrice: n(r.minPrice), offerCount: n(r.offerCount), ratingAverage: n(r.ratingAverage) }));
}

export async function createAdminProduct(input: {
  name: string;
  categoryId: number;
  productTypeId: number;
  shortDescription: string;
  price: number;
  accessMethod: string;
}) {
  return db.transaction(async (tx) => {
    const [p] = await tx
      .insert(products)
      .values({
        name: input.name,
        slug: slugify(input.name) + "-" + Math.random().toString(36).slice(2, 6),
        categoryId: input.categoryId,
        productTypeId: input.productTypeId,
        shortDescription: input.shortDescription,
        images: [`https://picsum.photos/seed/${slugify(input.name)}/900/600`],
        isActive: true,
      })
      .returning({ id: products.id });

    const [acc] = await tx
      .insert(productAccessTypes)
      .values({
        productId: p.id,
        name: "Standard",
        slug: "standard",
        accessMethod: input.accessMethod as never,
        defaultDeliveryMethod: "automatic",
      })
      .returning({ id: productAccessTypes.id });

    await tx.insert(productOffers).values({
      accessTypeId: acc.id,
      name: "Offre standard",
      slug: "offre-standard",
      price: input.price.toFixed(2),
      billingCycle: "one_time",
      isDefault: true,
    });

    return { id: p.id };
  });
}

export async function updateAdminProduct(id: number, patch: { isActive?: boolean; isFeatured?: boolean; name?: string; shortDescription?: string }) {
  const exists = (await db.select({ id: products.id }).from(products).where(eq(products.id, id)).limit(1))[0];
  if (!exists) throw notFound("Produit introuvable");
  await db.update(products).set({ ...patch, updatedAt: new Date() }).where(eq(products.id, id));
  return { id };
}

export async function deleteAdminProduct(id: number) {
  await db.delete(products).where(eq(products.id, id));
  return { id };
}

export async function listAdminOrders() {
  const rows = await db
    .select({
      id: orders.id,
      orderNumber: orders.orderNumber,
      status: orders.status,
      paymentStatus: orders.paymentStatus,
      paymentMethod: orders.paymentMethod,
      total: orders.totalAmount,
      createdAt: orders.createdAt,
      email: users.email,
    })
    .from(orders)
    .leftJoin(users, eq(users.id, orders.userId))
    .orderBy(desc(orders.createdAt))
    .limit(100);
  return rows.map((r) => ({ ...r, total: n(r.total), email: r.email ?? "Invité", createdAt: r.createdAt.toISOString() }));
}

export async function updateAdminOrder(id: number, patch: { status?: string; paymentStatus?: string }) {
  const exists = (await db.select({ id: orders.id }).from(orders).where(eq(orders.id, id)).limit(1))[0];
  if (!exists) throw notFound("Commande introuvable");
  await db
    .update(orders)
    .set({
      ...(patch.status ? { status: patch.status as never } : {}),
      ...(patch.paymentStatus ? { paymentStatus: patch.paymentStatus as never } : {}),
      updatedAt: new Date(),
    })
    .where(eq(orders.id, id));
  return { id };
}

export async function listAdminUsers() {
  const rows = await db
    .select({
      id: users.id,
      username: users.username,
      email: users.email,
      firstName: users.firstName,
      lastName: users.lastName,
      isActive: users.isActive,
      isVerified: users.isVerified,
      balance: users.balance,
      createdAt: users.createdAt,
      orderCount: sql<number>`(SELECT COUNT(*) FROM ${orders} WHERE ${orders.userId} = ${users.id})`,
      spent: sql<number>`(SELECT COALESCE(SUM(${orders.totalAmount}),0) FROM ${orders} WHERE ${orders.userId} = ${users.id} AND ${orders.paymentStatus} = 'paid')`,
    })
    .from(users)
    .orderBy(desc(users.id))
    .limit(100);
  return rows.map((r) => ({ ...r, balance: n(r.balance), orderCount: n(r.orderCount), spent: n(r.spent), createdAt: r.createdAt.toISOString() }));
}

export async function updateAdminUser(id: number, patch: { isActive?: boolean; isVerified?: boolean }) {
  const exists = (await db.select({ id: users.id }).from(users).where(eq(users.id, id)).limit(1))[0];
  if (!exists) throw notFound("Utilisateur introuvable");
  await db.update(users).set({ ...patch, updatedAt: new Date() }).where(eq(users.id, id));
  return { id };
}
