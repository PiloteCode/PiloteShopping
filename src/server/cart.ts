import "server-only";
import { cookies } from "next/headers";
import { and, eq, sql } from "drizzle-orm";
import { db } from "@/db";
import {
  cartItems,
  coupons,
  productAccessTypes,
  productCategories,
  productOffers,
  products,
} from "@/db/schema";
import { badRequest, notFound } from "@/lib/api";
import { getBillingInfo, isSubscriptionCycle, TAX_RATE, type BillingCycle } from "@/lib/domain";

export const COUPON_COOKIE = "pilote_coupon";

export interface CartOwner {
  userId: number | null;
  sessionId: string | null;
}

function ownerWhere(owner: CartOwner) {
  if (owner.userId != null) return eq(cartItems.userId, owner.userId);
  if (owner.sessionId) return eq(cartItems.sessionId, owner.sessionId);
  // No identity → impossible match
  return sql`false`;
}

export interface CartItemDTO {
  id: number;
  productId: number;
  offerId: number;
  name: string;
  slug: string;
  image: string | null;
  categoryName: string | null;
  categoryColor: string | null;
  accessTypeName: string;
  offerName: string;
  billingCycle: BillingCycle;
  billing: ReturnType<typeof getBillingInfo>;
  unitPrice: number;
  setupFee: number;
  quantity: number;
  lineTotal: number;
  isSubscription: boolean;
  inStock: boolean;
  maxStock: number | null;
}

export interface CartSummary {
  itemCount: number;
  subtotal: number;
  setupFees: number;
  discount: number;
  taxAmount: number;
  total: number;
  hasSubscription: boolean;
  canCheckout: boolean;
}

export interface AppliedCoupon {
  code: string;
  name: string;
  discountType: string;
  discountValue: number;
  discountAmount: number;
}

async function fetchItems(owner: CartOwner): Promise<CartItemDTO[]> {
  if (owner.userId == null && !owner.sessionId) return [];
  const rows = await db
    .select({
      item: cartItems,
      product: products,
      offer: productOffers,
      access: productAccessTypes,
      category: productCategories,
    })
    .from(cartItems)
    .innerJoin(productOffers, eq(productOffers.id, cartItems.productOfferId))
    .innerJoin(productAccessTypes, eq(productAccessTypes.id, productOffers.accessTypeId))
    .innerJoin(products, eq(products.id, cartItems.productId))
    .leftJoin(productCategories, eq(productCategories.id, products.categoryId))
    .where(ownerWhere(owner))
    .orderBy(cartItems.createdAt);

  return rows.map(({ item, product, offer, access, category }) => {
    const unitPrice = parseFloat(item.unitPrice);
    const setupFee = parseFloat(item.setupFee);
    const cycle = item.billingCycle as BillingCycle;
    const images = (product.images ?? []) as string[];
    return {
      id: item.id,
      productId: product.id,
      offerId: offer.id,
      name: product.name,
      slug: product.slug,
      image: images[0] ?? null,
      categoryName: category?.name ?? null,
      categoryColor: category?.color ?? null,
      accessTypeName: access.name,
      offerName: offer.name,
      billingCycle: cycle,
      billing: getBillingInfo(cycle, unitPrice),
      unitPrice,
      setupFee,
      quantity: item.quantity,
      lineTotal: unitPrice * item.quantity + setupFee,
      isSubscription: item.isSubscription,
      inStock: !offer.manageStock || offer.stockQuantity >= item.quantity,
      maxStock: offer.manageStock ? offer.stockQuantity : null,
    };
  });
}

export async function getAppliedCoupon(items: CartItemDTO[]): Promise<AppliedCoupon | null> {
  const jar = await cookies();
  const code = jar.get(COUPON_COOKIE)?.value;
  if (!code) return null;
  const result = await validateCoupon(code, items);
  return result.ok ? result.coupon : null;
}

interface CouponResult {
  ok: boolean;
  error?: string;
  coupon: AppliedCoupon;
}

export async function validateCoupon(code: string, items: CartItemDTO[]): Promise<CouponResult> {
  const empty: AppliedCoupon = { code, name: "", discountType: "", discountValue: 0, discountAmount: 0 };
  const rows = await db.select().from(coupons).where(eq(coupons.code, code.toUpperCase())).limit(1);
  const c = rows[0];
  if (!c || !c.isActive) return { ok: false, error: "Code promo invalide", coupon: empty };

  const now = new Date();
  if (c.startDate && c.startDate > now) return { ok: false, error: "Ce code n'est pas encore actif", coupon: empty };
  if (c.endDate && c.endDate < now) return { ok: false, error: "Ce code promo a expiré", coupon: empty };
  if (c.usageLimit != null && c.usageCount >= c.usageLimit) return { ok: false, error: "Ce code a atteint sa limite", coupon: empty };

  const eligible = items.filter((i) => !(c.excludeSubscriptions && i.isSubscription));
  const eligibleSubtotal = eligible.reduce((s, i) => s + i.unitPrice * i.quantity, 0);
  if (eligibleSubtotal <= 0) return { ok: false, error: "Aucun article éligible", coupon: empty };

  const minAmount = c.minimumAmount ? parseFloat(c.minimumAmount) : 0;
  if (minAmount && eligibleSubtotal < minAmount)
    return { ok: false, error: `Minimum de ${minAmount.toFixed(2)} € requis`, coupon: empty };

  const value = parseFloat(c.discountValue);
  let discount = 0;
  if (c.discountType === "percentage") {
    discount = (eligibleSubtotal * value) / 100;
    const cap = c.maxDiscountAmount ? parseFloat(c.maxDiscountAmount) : null;
    if (cap != null) discount = Math.min(discount, cap);
  } else if (c.discountType === "fixed") {
    discount = Math.min(value, eligibleSubtotal);
  } // free_shipping → 0 on digital goods

  return {
    ok: true,
    coupon: {
      code: c.code,
      name: c.name,
      discountType: c.discountType,
      discountValue: value,
      discountAmount: +discount.toFixed(2),
    },
  };
}

export function summarize(items: CartItemDTO[], coupon: AppliedCoupon | null): CartSummary {
  const subtotal = items.reduce((s, i) => s + i.unitPrice * i.quantity, 0);
  const setupFees = items.reduce((s, i) => s + i.setupFee, 0);
  const discount = coupon?.discountAmount ?? 0;
  const taxable = Math.max(0, subtotal - discount) + setupFees;
  const taxAmount = +(taxable * TAX_RATE).toFixed(2);
  const total = +(taxable + taxAmount).toFixed(2);
  return {
    itemCount: items.reduce((s, i) => s + i.quantity, 0),
    subtotal: +subtotal.toFixed(2),
    setupFees: +setupFees.toFixed(2),
    discount: +discount.toFixed(2),
    taxAmount,
    total,
    hasSubscription: items.some((i) => i.isSubscription),
    canCheckout: items.length > 0 && items.every((i) => i.inStock),
  };
}

export async function getCart(owner: CartOwner) {
  const items = await fetchItems(owner);
  const coupon = await getAppliedCoupon(items);
  return { items, coupon, summary: summarize(items, coupon) };
}

export async function addToCart(
  owner: CartOwner,
  input: { productId: number; offerId: number; quantity?: number; customData?: unknown },
) {
  const offerRows = await db
    .select({ offer: productOffers, access: productAccessTypes, product: products })
    .from(productOffers)
    .innerJoin(productAccessTypes, eq(productAccessTypes.id, productOffers.accessTypeId))
    .innerJoin(products, eq(products.id, productAccessTypes.productId))
    .where(eq(productOffers.id, input.offerId))
    .limit(1);

  const found = offerRows[0];
  if (!found || !found.offer.isActive || !found.product.isActive) throw notFound("Offre introuvable");
  if (found.product.id !== input.productId) throw badRequest("Produit/offre incohérents");

  const cycle = found.offer.billingCycle as BillingCycle;
  const isSub = isSubscriptionCycle(cycle);
  const quantity = isSub ? 1 : Math.max(1, Math.min(input.quantity ?? 1, 10));

  if (found.offer.manageStock && found.offer.stockQuantity < quantity)
    throw badRequest("Stock insuffisant pour cette offre");

  // Existing line?
  const existing = await db
    .select()
    .from(cartItems)
    .where(and(ownerWhere(owner), eq(cartItems.productOfferId, input.offerId)))
    .limit(1);

  if (existing[0]) {
    if (isSub) throw badRequest("Cet abonnement est déjà dans votre panier");
    const newQty = Math.min(existing[0].quantity + quantity, 10);
    await db.update(cartItems).set({ quantity: newQty, updatedAt: new Date() }).where(eq(cartItems.id, existing[0].id));
    return { itemId: existing[0].id, quantity: newQty };
  }

  const [row] = await db
    .insert(cartItems)
    .values({
      userId: owner.userId,
      sessionId: owner.userId == null ? owner.sessionId : null,
      productId: input.productId,
      productOfferId: input.offerId,
      quantity,
      unitPrice: found.offer.price,
      setupFee: found.offer.setupFee,
      billingCycle: cycle,
      isSubscription: isSub,
      customData: (input.customData as object) ?? null,
    })
    .returning({ id: cartItems.id });

  return { itemId: row.id, quantity };
}

export async function updateCartItem(owner: CartOwner, itemId: number, quantity: number) {
  const rows = await db
    .select({ item: cartItems, offer: productOffers })
    .from(cartItems)
    .innerJoin(productOffers, eq(productOffers.id, cartItems.productOfferId))
    .where(and(eq(cartItems.id, itemId), ownerWhere(owner)))
    .limit(1);
  const found = rows[0];
  if (!found) throw notFound("Article introuvable dans le panier");

  if (quantity <= 0) {
    await db.delete(cartItems).where(eq(cartItems.id, itemId));
    return { removed: true };
  }
  if (found.item.isSubscription && quantity > 1) quantity = 1;
  if (found.offer.manageStock && found.offer.stockQuantity < quantity) throw badRequest("Stock insuffisant");

  await db.update(cartItems).set({ quantity, updatedAt: new Date() }).where(eq(cartItems.id, itemId));
  return { removed: false, quantity };
}

export async function removeCartItem(owner: CartOwner, itemId: number) {
  const rows = await db.select().from(cartItems).where(and(eq(cartItems.id, itemId), ownerWhere(owner))).limit(1);
  if (!rows[0]) throw notFound("Article introuvable");
  await db.delete(cartItems).where(eq(cartItems.id, itemId));
  return { removed: true };
}

export async function clearCart(owner: CartOwner) {
  if (owner.userId == null && !owner.sessionId) return;
  await db.delete(cartItems).where(ownerWhere(owner));
  (await cookies()).delete(COUPON_COOKIE);
}

/** Move guest cart lines onto a user account at login. */
export async function mergeGuestCart(userId: number, sessionId: string | null) {
  if (!sessionId) return;
  await db.update(cartItems).set({ userId, sessionId: null }).where(eq(cartItems.sessionId, sessionId));
}
