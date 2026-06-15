import "server-only";
import { and, desc, eq, sql } from "drizzle-orm";
import { db } from "@/db";
import {
  balanceTransactions,
  cartItems,
  coupons,
  couponUsages,
  notifications,
  orderItems,
  orders,
  paymentTransactions,
  productAccessTypes,
  productInventory,
  productOffers,
  products,
  subscriptions,
  users,
} from "@/db/schema";
import { badRequest, forbidden, notFound } from "@/lib/api";
import {
  addMonths,
  generateOrderNumber,
  generateSubscriptionNumber,
  generateTransactionId,
  isSubscriptionCycle,
  type BillingCycle,
} from "@/lib/domain";
import { getCart, type CartOwner, COUPON_COOKIE } from "@/server/cart";
import { cookies } from "next/headers";

const DEMO = (process.env.DEMO_MODE ?? "true") !== "false";

export interface CreateOrderInput {
  paymentMethod: "stripe" | "paypal" | "bank_transfer" | "balance";
  billingAddress: {
    firstName: string;
    lastName: string;
    addressLine1: string;
    addressLine2?: string;
    companyName?: string;
    city: string;
    postalCode: string;
    country: string;
    phone?: string;
  };
  notes?: string;
}

function deliveryEta(method: string): "delivered" | "processing" | "pending" {
  return method === "automatic" ? "delivered" : method === "semi_automatic" ? "processing" : "pending";
}

function randKey(prefix: string): string {
  const block = () => Math.random().toString(36).slice(2, 6).toUpperCase();
  return `${prefix}-${block()}-${block()}-${block()}`;
}

export async function createOrder(owner: CartOwner, input: CreateOrderInput) {
  const { items, summary, coupon } = await getCart(owner);
  if (items.length === 0) throw badRequest("Votre panier est vide");
  if (!summary.canCheckout) throw badRequest("Certains articles ne sont plus disponibles");
  if (summary.hasSubscription && owner.userId == null)
    throw badRequest("Veuillez vous connecter pour souscrire un abonnement");

  const orderNumber = generateOrderNumber();
  const ba = input.billingAddress;

  return db.transaction(async (tx) => {
    // Balance payment: verify & deduct
    let userBalanceBefore = 0;
    if (input.paymentMethod === "balance") {
      if (owner.userId == null) throw badRequest("Connexion requise pour payer avec le solde");
      const u = await tx.select().from(users).where(eq(users.id, owner.userId)).limit(1);
      userBalanceBefore = parseFloat(u[0]?.balance ?? "0");
      if (userBalanceBefore < summary.total) throw badRequest("Solde insuffisant");
    }

    // Demo gateways settle instantly; bank transfer stays pending.
    const settles = input.paymentMethod === "balance" || (DEMO && input.paymentMethod !== "bank_transfer");
    const paymentStatus = settles ? "paid" : "pending";

    const [order] = await tx
      .insert(orders)
      .values({
        orderNumber,
        userId: owner.userId,
        status: settles ? "processing" : "pending",
        paymentStatus,
        paymentMethod: input.paymentMethod,
        subtotal: summary.subtotal.toFixed(2),
        taxAmount: summary.taxAmount.toFixed(2),
        discountAmount: summary.discount.toFixed(2),
        totalAmount: summary.total.toFixed(2),
        couponCode: coupon?.code ?? null,
        paymentReference: generateTransactionId("pi"),
        notes: input.notes ?? null,
        billingAddress: {
          first_name: ba.firstName,
          last_name: ba.lastName,
          company_name: ba.companyName ?? null,
          address_line1: ba.addressLine1,
          address_line2: ba.addressLine2 ?? null,
          city: ba.city,
          postal_code: ba.postalCode,
          country: ba.country,
          phone: ba.phone ?? null,
        },
      })
      .returning({ id: orders.id });

    let allDelivered = true;

    for (const it of items) {
      const offerRows = await tx
        .select({
          offer: productOffers,
          accessMethod: productAccessTypes.accessMethod,
          deliveryMethod: productAccessTypes.defaultDeliveryMethod,
        })
        .from(productOffers)
        .innerJoin(productAccessTypes, eq(productAccessTypes.id, productOffers.accessTypeId))
        .where(eq(productOffers.id, it.offerId))
        .limit(1);
      const offer = offerRows[0]?.offer;
      const cycle = it.billingCycle as BillingCycle;
      const dm = offerRows[0]?.deliveryMethod ?? "automatic";
      const am = offerRows[0]?.accessMethod ?? "license_key";

      const status = deliveryEta(dm);
      let deliveryData: Record<string, unknown> | null = null;

      if (settles && status === "delivered") {
        if (am === "license_key" || am === "download_link") {
          const inv = await tx
            .select()
            .from(productInventory)
            .where(and(eq(productInventory.offerId, it.offerId), eq(productInventory.status, "available")))
            .limit(1);
          if (inv[0]) {
            await tx
              .update(productInventory)
              .set({ status: "sold", soldToUserId: owner.userId, soldInOrderId: order.id })
              .where(eq(productInventory.id, inv[0].id));
            deliveryData = { type: am, ...(inv[0].inventoryData as object), notes: it.accessTypeName };
          } else {
            deliveryData = { type: "license_key", license_key: randKey(it.slug.slice(0, 4).toUpperCase()) };
          }
        } else {
          deliveryData = {
            type: "account",
            email: `${it.slug}-${Math.random().toString(36).slice(2, 7)}@pilote.demo`,
            password: randKey("PW").replace(/-/g, ""),
            notes: "Compte de démonstration — ne pas partager.",
          };
        }
      } else if (status !== "delivered") {
        allDelivered = false;
      }

      const [item] = await tx
        .insert(orderItems)
        .values({
          orderId: order.id,
          productId: it.productId,
          productOfferId: it.offerId,
          productName: it.name,
          productSlug: it.slug,
          accessTypeName: it.accessTypeName,
          offerName: it.offerName,
          quantity: it.quantity,
          unitPrice: it.unitPrice.toFixed(2),
          totalPrice: (it.unitPrice * it.quantity).toFixed(2),
          setupFee: it.setupFee.toFixed(2),
          billingCycle: cycle,
          deliveryStatus: settles ? status : "pending",
          deliveryMethod: dm as never,
          deliveryData,
          deliveredAt: settles && status === "delivered" ? new Date() : null,
          productData: { category: it.categoryName, isSubscription: it.isSubscription },
        })
        .returning({ id: orderItems.id });

      // Stock + sale counters
      if (offer?.manageStock) {
        await tx
          .update(productOffers)
          .set({ stockQuantity: sql`GREATEST(${productOffers.stockQuantity} - ${it.quantity}, 0)`, saleCount: sql`${productOffers.saleCount} + ${it.quantity}` })
          .where(eq(productOffers.id, it.offerId));
      } else {
        await tx.update(productOffers).set({ saleCount: sql`${productOffers.saleCount} + ${it.quantity}` }).where(eq(productOffers.id, it.offerId));
      }
      await tx.update(products).set({ saleCount: sql`${products.saleCount} + ${it.quantity}` }).where(eq(products.id, it.productId));

      // Subscription contract
      if (isSubscriptionCycle(cycle) && owner.userId != null) {
        await tx.insert(subscriptions).values({
          subscriptionNumber: generateSubscriptionNumber(),
          userId: owner.userId,
          orderItemId: item.id,
          productOfferId: it.offerId,
          productName: it.name,
          status: settles ? "active" : "pending",
          billingCycle: cycle,
          amount: it.unitPrice.toFixed(2),
          startDate: new Date(),
          nextBillingDate: addMonths(new Date(), it.billing.durationMonths || 1),
          lastBillingDate: settles ? new Date() : null,
        });
      }
    }

    // Payment transaction
    await tx.insert(paymentTransactions).values({
      transactionId: generateTransactionId("TX"),
      orderId: order.id,
      userId: owner.userId,
      paymentMethod: input.paymentMethod,
      type: "payment",
      status: settles ? "completed" : "pending",
      amount: summary.total.toFixed(2),
      gatewayTransactionId: generateTransactionId("pi"),
      processedAt: settles ? new Date() : null,
    });

    // Balance debit
    if (input.paymentMethod === "balance" && owner.userId != null) {
      const after = +(userBalanceBefore - summary.total).toFixed(2);
      await tx.update(users).set({ balance: after.toFixed(2) }).where(eq(users.id, owner.userId));
      await tx.insert(balanceTransactions).values({
        userId: owner.userId,
        transactionType: "debit",
        amount: summary.total.toFixed(2),
        description: `Commande ${orderNumber}`,
        referenceOrderId: order.id,
        balanceBefore: userBalanceBefore.toFixed(2),
        balanceAfter: after.toFixed(2),
      });
    }

    // Coupon usage
    if (coupon) {
      await tx.update(coupons).set({ usageCount: sql`${coupons.usageCount} + 1` }).where(eq(coupons.code, coupon.code));
      await tx.insert(couponUsages).values({
        couponId: (await tx.select({ id: coupons.id }).from(coupons).where(eq(coupons.code, coupon.code)).limit(1))[0]?.id ?? 0,
        userId: owner.userId,
        orderId: order.id,
        discountAmount: coupon.discountAmount.toFixed(2),
      });
    }

    // Finalize status + loyalty + notifications
    if (settles) {
      await tx
        .update(orders)
        .set({ status: allDelivered ? "completed" : "processing", deliveredAt: allDelivered ? new Date() : null })
        .where(eq(orders.id, order.id));

      if (owner.userId != null) {
        await tx
          .update(users)
          .set({ loyaltyPoints: sql`${users.loyaltyPoints} + ${Math.round(summary.total)}` })
          .where(eq(users.id, owner.userId));
        await tx.insert(notifications).values({
          userId: owner.userId,
          type: "order",
          title: allDelivered ? "Commande livrée" : "Commande confirmée",
          message: `Votre commande ${orderNumber} (${summary.total.toFixed(2)} €) a été ${allDelivered ? "livrée" : "confirmée"}.`,
          link: `/dashboard/orders/${orderNumber}`,
          icon: "Package",
          color: "green",
        });
      }
    }

    // Clear cart
    if (owner.userId != null) await tx.delete(cartItems).where(eq(cartItems.userId, owner.userId));
    else if (owner.sessionId) await tx.delete(cartItems).where(eq(cartItems.sessionId, owner.sessionId));

    return {
      orderId: order.id,
      orderNumber,
      total: summary.total,
      paymentStatus,
      status: settles ? (allDelivered ? "completed" : "processing") : "pending",
    };
  });
}

export async function clearCouponCookie() {
  (await cookies()).delete(COUPON_COOKIE);
}

export async function getUserOrders(userId: number, opts: { status?: string; q?: string } = {}) {
  const rows = await db
    .select()
    .from(orders)
    .where(eq(orders.userId, userId))
    .orderBy(desc(orders.createdAt));

  const result = [];
  for (const o of rows) {
    if (opts.status && o.status !== opts.status) continue;
    if (opts.q && !o.orderNumber.toLowerCase().includes(opts.q.toLowerCase())) continue;
    const its = await db.select().from(orderItems).where(eq(orderItems.orderId, o.id));
    result.push({
      orderNumber: o.orderNumber,
      status: o.status,
      paymentStatus: o.paymentStatus,
      paymentMethod: o.paymentMethod,
      total: parseFloat(o.totalAmount),
      itemCount: its.reduce((s, i) => s + i.quantity, 0),
      firstProduct: its[0]?.productName ?? "—",
      createdAt: o.createdAt.toISOString(),
    });
  }
  return result;
}

export async function getOrderByNumber(userId: number | null, orderNumber: string) {
  const rows = await db.select().from(orders).where(eq(orders.orderNumber, orderNumber)).limit(1);
  const o = rows[0];
  if (!o) throw notFound("Commande introuvable");
  if (userId != null && o.userId != null && o.userId !== userId) throw forbidden();

  const its = await db.select().from(orderItems).where(eq(orderItems.orderId, o.id));
  return {
    id: o.id,
    orderNumber: o.orderNumber,
    status: o.status,
    paymentStatus: o.paymentStatus,
    paymentMethod: o.paymentMethod,
    subtotal: parseFloat(o.subtotal),
    taxAmount: parseFloat(o.taxAmount),
    discountAmount: parseFloat(o.discountAmount),
    total: parseFloat(o.totalAmount),
    couponCode: o.couponCode,
    billingAddress: o.billingAddress,
    createdAt: o.createdAt.toISOString(),
    deliveredAt: o.deliveredAt?.toISOString() ?? null,
    items: its.map((i) => ({
      id: i.id,
      productName: i.productName,
      productSlug: i.productSlug,
      accessTypeName: i.accessTypeName,
      offerName: i.offerName,
      quantity: i.quantity,
      unitPrice: parseFloat(i.unitPrice),
      totalPrice: parseFloat(i.totalPrice),
      billingCycle: i.billingCycle,
      deliveryStatus: i.deliveryStatus,
      deliveryMethod: i.deliveryMethod,
      hasDelivery: i.deliveryStatus === "delivered" && !!i.deliveryData,
    })),
  };
}

export async function accessProduct(userId: number, orderItemId: number) {
  const rows = await db
    .select({ item: orderItems, order: orders })
    .from(orderItems)
    .innerJoin(orders, eq(orders.id, orderItems.orderId))
    .where(eq(orderItems.id, orderItemId))
    .limit(1);
  const found = rows[0];
  if (!found) throw notFound("Article introuvable");
  if (found.order.userId !== userId) throw forbidden();
  if (found.order.paymentStatus !== "paid") throw badRequest("Cette commande n'est pas payée");
  if (found.item.deliveryStatus !== "delivered" || !found.item.deliveryData)
    throw badRequest("Cet article n'a pas encore été livré");

  return {
    productName: found.item.productName,
    accessTypeName: found.item.accessTypeName,
    deliveredAt: found.item.deliveredAt?.toISOString() ?? null,
    data: found.item.deliveryData,
  };
}
