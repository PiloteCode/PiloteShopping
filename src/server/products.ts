import "server-only";
import { and, desc, eq, ilike, inArray, sql } from "drizzle-orm";
import { db } from "@/db";
import {
  productAccessTypes,
  productCategories,
  productOffers,
  productReviews,
  productTypes,
  products,
} from "@/db/schema";
import { getBillingInfo, type BillingCycle } from "@/lib/domain";

export interface OfferDTO {
  id: number;
  name: string;
  price: number;
  compareAtPrice: number | null;
  billingCycle: BillingCycle;
  setupFee: number;
  isDefault: boolean;
  inStock: boolean;
  stockQuantity: number | null;
  billing: ReturnType<typeof getBillingInfo>;
}

export interface AccessTypeDTO {
  id: number;
  name: string;
  accessMethod: string;
  deliveryMethod: string;
  instructions: string | null;
  offers: OfferDTO[];
}

export interface ProductCard {
  id: number;
  name: string;
  slug: string;
  shortDescription: string;
  image: string | null;
  images: string[];
  category: { id: number; name: string; slug: string; color: string | null; icon: string | null } | null;
  type: { id: number; name: string; icon: string | null } | null;
  ratingAverage: number;
  ratingCount: number;
  saleCount: number;
  isFeatured: boolean;
  fromPrice: number;
  compareAtPrice: number | null;
  defaultOfferId: number | null;
  hasSubscription: boolean;
  hasOneTime: boolean;
  available: boolean;
}

export interface ProductDetail extends ProductCard {
  description: string | null;
  features: string[];
  viewCount: number;
  accessTypes: AccessTypeDTO[];
  reviews: {
    id: number;
    rating: number;
    title: string | null;
    comment: string | null;
    author: string;
    createdAt: string;
    helpfulCount: number;
  }[];
}

type ProductWithRels = typeof products.$inferSelect & {
  category: typeof productCategories.$inferSelect | null;
  type: typeof productTypes.$inferSelect | null;
  accessTypes: (typeof productAccessTypes.$inferSelect & {
    offers: (typeof productOffers.$inferSelect)[];
  })[];
};

function mapOffer(o: typeof productOffers.$inferSelect): OfferDTO {
  const price = parseFloat(o.price);
  return {
    id: o.id,
    name: o.name,
    price,
    compareAtPrice: o.compareAtPrice ? parseFloat(o.compareAtPrice) : null,
    billingCycle: o.billingCycle as BillingCycle,
    setupFee: parseFloat(o.setupFee),
    isDefault: o.isDefault,
    inStock: !o.manageStock || o.stockQuantity > 0,
    stockQuantity: o.manageStock ? o.stockQuantity : null,
    billing: getBillingInfo(o.billingCycle as BillingCycle, price),
  };
}

function allOffers(p: ProductWithRels): OfferDTO[] {
  return p.accessTypes
    .filter((a) => a.isActive)
    .flatMap((a) => a.offers.filter((o) => o.isActive).map(mapOffer));
}

function toCard(p: ProductWithRels): ProductCard {
  const offers = allOffers(p);
  const cheapest = offers.reduce<OfferDTO | null>(
    (min, o) => (!min || o.price < min.price ? o : min),
    null,
  );
  const def = offers.find((o) => o.isDefault) ?? cheapest;
  const images = (p.images ?? []) as string[];
  return {
    id: p.id,
    name: p.name,
    slug: p.slug,
    shortDescription: p.shortDescription,
    image: images[0] ?? null,
    images,
    category: p.category
      ? { id: p.category.id, name: p.category.name, slug: p.category.slug, color: p.category.color, icon: p.category.icon }
      : null,
    type: p.type ? { id: p.type.id, name: p.type.name, icon: p.type.icon } : null,
    ratingAverage: parseFloat(p.ratingAverage),
    ratingCount: p.ratingCount,
    saleCount: p.saleCount,
    isFeatured: p.isFeatured,
    fromPrice: cheapest?.price ?? 0,
    compareAtPrice: cheapest?.compareAtPrice ?? null,
    defaultOfferId: def?.id ?? null,
    hasSubscription: offers.some((o) => o.billing.isSubscription),
    hasOneTime: offers.some((o) => o.billingCycle === "one_time"),
    available: offers.some((o) => o.inStock),
  };
}

const withRels = {
  category: true,
  type: true,
  accessTypes: { with: { offers: true } },
} as const;

export async function getCategories() {
  const rows = await db
    .select()
    .from(productCategories)
    .where(eq(productCategories.isActive, true))
    .orderBy(productCategories.sortOrder);
  return rows;
}

export async function getProductTypes() {
  return db.select().from(productTypes).where(eq(productTypes.isActive, true)).orderBy(productTypes.sortOrder);
}

export interface ListParams {
  category?: string;
  type?: string;
  q?: string;
  sort?: "popular" | "newest" | "price_asc" | "price_desc" | "rating";
  billing?: "all" | "one_time" | "subscription";
  page?: number;
  perPage?: number;
}

export async function listProducts(params: ListParams = {}) {
  const { category, type, q, sort = "popular", billing = "all", page = 1, perPage = 12 } = params;

  const conditions = [eq(products.isActive, true)];
  if (category) {
    const cat = await db.select({ id: productCategories.id }).from(productCategories).where(eq(productCategories.slug, category)).limit(1);
    if (cat[0]) conditions.push(eq(products.categoryId, cat[0].id));
  }
  if (type) {
    const t = await db.select({ id: productTypes.id }).from(productTypes).where(eq(productTypes.slug, type)).limit(1);
    if (t[0]) conditions.push(eq(products.productTypeId, t[0].id));
  }
  if (q) conditions.push(ilike(products.name, `%${q.replace(/[%_]/g, "")}%`));

  const rows = (await db.query.products.findMany({
    where: and(...conditions),
    with: withRels,
  })) as ProductWithRels[];

  let cards = rows.map(toCard);

  if (billing === "one_time") cards = cards.filter((c) => c.hasOneTime);
  if (billing === "subscription") cards = cards.filter((c) => c.hasSubscription);

  switch (sort) {
    case "price_asc": cards.sort((a, b) => a.fromPrice - b.fromPrice); break;
    case "price_desc": cards.sort((a, b) => b.fromPrice - a.fromPrice); break;
    case "rating": cards.sort((a, b) => b.ratingAverage - a.ratingAverage); break;
    case "newest": cards.sort((a, b) => b.id - a.id); break;
    default: cards.sort((a, b) => b.saleCount - a.saleCount);
  }

  const total = cards.length;
  const start = (page - 1) * perPage;
  return { items: cards.slice(start, start + perPage), total, page, perPage, pages: Math.ceil(total / perPage) };
}

export async function getFeaturedProducts(limit = 6): Promise<ProductCard[]> {
  const rows = (await db.query.products.findMany({
    where: and(eq(products.isActive, true), eq(products.isFeatured, true)),
    with: withRels,
    limit,
  })) as ProductWithRels[];
  return rows.map(toCard);
}

export async function getProductBySlug(slug: string): Promise<ProductDetail | null> {
  const p = (await db.query.products.findFirst({
    where: and(eq(products.slug, slug), eq(products.isActive, true)),
    with: withRels,
  })) as ProductWithRels | undefined;
  if (!p) return null;

  await db.update(products).set({ viewCount: sql`${products.viewCount} + 1` }).where(eq(products.id, p.id));

  const reviewRows = await db.query.productReviews.findMany({
    where: and(eq(productReviews.productId, p.id), eq(productReviews.status, "approved")),
    with: { user: true },
    orderBy: desc(productReviews.createdAt),
    limit: 12,
  });

  const card = toCard(p);
  return {
    ...card,
    description: p.description,
    features: (p.features ?? []) as string[],
    viewCount: p.viewCount,
    accessTypes: p.accessTypes
      .filter((a) => a.isActive)
      .map((a) => ({
        id: a.id,
        name: a.name,
        accessMethod: a.accessMethod,
        deliveryMethod: a.defaultDeliveryMethod,
        instructions: a.userInstructions,
        offers: a.offers.filter((o) => o.isActive).map(mapOffer).sort((x, y) => x.price - y.price),
      })),
    reviews: reviewRows.map((r) => ({
      id: r.id,
      rating: r.rating,
      title: r.title,
      comment: r.comment,
      author: r.user ? `${r.user.firstName} ${r.user.lastName.charAt(0)}.` : "Client",
      createdAt: r.createdAt.toISOString(),
      helpfulCount: r.helpfulCount,
    })),
  };
}

export async function getRecommended(opts: {
  source?: string;
  productId?: number;
  categoryId?: number;
  limit?: number;
  exclude?: number[];
}): Promise<ProductCard[]> {
  const { limit = 8, exclude = [], categoryId } = opts;
  const conditions = [eq(products.isActive, true)];
  if (categoryId) conditions.push(eq(products.categoryId, categoryId));

  const rows = (await db.query.products.findMany({
    where: and(...conditions),
    with: withRels,
    orderBy: desc(products.saleCount),
    limit: limit + exclude.length + 4,
  })) as ProductWithRels[];

  return rows
    .map(toCard)
    .filter((c) => !exclude.includes(c.id))
    .slice(0, limit);
}

export async function getProductsByIds(ids: number[]): Promise<ProductCard[]> {
  if (ids.length === 0) return [];
  const rows = (await db.query.products.findMany({
    where: inArray(products.id, ids),
    with: withRels,
  })) as ProductWithRels[];
  const map = new Map(rows.map((r) => [r.id, toCard(r)]));
  return ids.map((id) => map.get(id)).filter((c): c is ProductCard => !!c);
}

export async function getOfferById(offerId: number) {
  const rows = await db
    .select({
      offer: productOffers,
      access: productAccessTypes,
      product: products,
    })
    .from(productOffers)
    .innerJoin(productAccessTypes, eq(productAccessTypes.id, productOffers.accessTypeId))
    .innerJoin(products, eq(products.id, productAccessTypes.productId))
    .where(eq(productOffers.id, offerId))
    .limit(1);
  return rows[0] ?? null;
}
