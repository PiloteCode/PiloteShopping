import "server-only";
import { and, desc, eq, sql } from "drizzle-orm";
import { db } from "@/db";
import {
  balanceTransactions,
  notifications,
  subscriptions,
  users,
} from "@/db/schema";
import { badRequest, notFound } from "@/lib/api";
import { addMonths, BILLING_CYCLES, type BillingCycle } from "@/lib/domain";

/* ── Subscriptions ── */
export async function getSubscriptions(userId: number) {
  const rows = await db
    .select()
    .from(subscriptions)
    .where(eq(subscriptions.userId, userId))
    .orderBy(desc(subscriptions.createdAt));

  return rows.map((sb) => ({
    id: sb.id,
    subscriptionNumber: sb.subscriptionNumber,
    productName: sb.productName,
    status: sb.status,
    billingCycle: sb.billingCycle,
    billingLabel: BILLING_CYCLES[sb.billingCycle as BillingCycle]?.label ?? sb.billingCycle,
    amount: parseFloat(sb.amount),
    currency: sb.currency,
    nextBillingDate: sb.nextBillingDate?.toISOString() ?? null,
    lastBillingDate: sb.lastBillingDate?.toISOString() ?? null,
    autoRenew: sb.autoRenew,
    createdAt: sb.createdAt.toISOString(),
  }));
}

export async function manageSubscription(
  userId: number,
  id: number,
  action: "pause" | "resume" | "cancel",
) {
  const rows = await db.select().from(subscriptions).where(eq(subscriptions.id, id)).limit(1);
  const sub = rows[0];
  if (!sub || sub.userId !== userId) throw notFound("Abonnement introuvable");

  if (action === "pause") {
    if (sub.status !== "active") throw badRequest("Seul un abonnement actif peut être suspendu");
    await db.update(subscriptions).set({ status: "paused", updatedAt: new Date() }).where(eq(subscriptions.id, id));
    return { status: "paused" };
  }
  if (action === "resume") {
    if (sub.status !== "paused") throw badRequest("Cet abonnement n'est pas suspendu");
    const months = BILLING_CYCLES[sub.billingCycle as BillingCycle]?.durationMonths || 1;
    await db
      .update(subscriptions)
      .set({ status: "active", nextBillingDate: addMonths(new Date(), months), updatedAt: new Date() })
      .where(eq(subscriptions.id, id));
    return { status: "active" };
  }
  // cancel
  if (sub.status === "cancelled") throw badRequest("Cet abonnement est déjà annulé");
  await db
    .update(subscriptions)
    .set({ status: "cancelled", autoRenew: false, cancellationReason: "user_request", updatedAt: new Date() })
    .where(eq(subscriptions.id, id));
  return { status: "cancelled" };
}

/* ── Wallet ── */
export async function getWallet(userId: number) {
  const u = await db.select({ balance: users.balance, loyaltyPoints: users.loyaltyPoints }).from(users).where(eq(users.id, userId)).limit(1);
  const txns = await db
    .select()
    .from(balanceTransactions)
    .where(eq(balanceTransactions.userId, userId))
    .orderBy(desc(balanceTransactions.createdAt))
    .limit(30);

  return {
    balance: parseFloat(u[0]?.balance ?? "0"),
    loyaltyPoints: u[0]?.loyaltyPoints ?? 0,
    transactions: txns.map((t) => ({
      id: t.id,
      type: t.transactionType,
      amount: parseFloat(t.amount),
      description: t.description,
      balanceAfter: parseFloat(t.balanceAfter),
      createdAt: t.createdAt.toISOString(),
    })),
  };
}

export async function addFunds(userId: number, amount: number) {
  return db.transaction(async (tx) => {
    const u = await tx.select({ balance: users.balance }).from(users).where(eq(users.id, userId)).limit(1);
    const before = parseFloat(u[0]?.balance ?? "0");
    const after = +(before + amount).toFixed(2);
    await tx.update(users).set({ balance: after.toFixed(2) }).where(eq(users.id, userId));
    await tx.insert(balanceTransactions).values({
      userId,
      transactionType: "credit",
      amount: amount.toFixed(2),
      description: "Rechargement du portefeuille",
      balanceBefore: before.toFixed(2),
      balanceAfter: after.toFixed(2),
    });
    await tx.insert(notifications).values({
      userId,
      type: "wallet",
      title: "Portefeuille rechargé",
      message: `${amount.toFixed(2)} € ont été ajoutés à votre solde.`,
      link: "/dashboard/wallet",
      icon: "Wallet",
      color: "green",
    });
    return { balance: after };
  });
}

/* ── Notifications ── */
export async function getNotifications(userId: number, opts: { limit?: number; offset?: number; unread?: boolean } = {}) {
  const { limit = 50, offset = 0, unread = false } = opts;
  const where = unread
    ? and(eq(notifications.userId, userId), eq(notifications.isRead, false))
    : eq(notifications.userId, userId);

  const rows = await db
    .select()
    .from(notifications)
    .where(where)
    .orderBy(desc(notifications.createdAt))
    .limit(limit)
    .offset(offset);

  const unreadCountRows = await db
    .select({ c: sql<number>`count(*)::int` })
    .from(notifications)
    .where(and(eq(notifications.userId, userId), eq(notifications.isRead, false)));

  return {
    notifications: rows.map((n) => ({
      id: n.id,
      type: n.type,
      title: n.title,
      message: n.message,
      link: n.link,
      icon: n.icon,
      color: n.color,
      isRead: n.isRead,
      createdAt: n.createdAt.toISOString(),
    })),
    unreadCount: unreadCountRows[0]?.c ?? 0,
  };
}

export async function notificationAction(
  userId: number,
  action: "mark_read" | "mark_all_read" | "delete",
  id?: number,
) {
  if (action === "mark_all_read") {
    await db.update(notifications).set({ isRead: true, readAt: new Date() }).where(eq(notifications.userId, userId));
    return { ok: true };
  }
  if (!id) throw badRequest("Identifiant de notification requis");
  const owns = and(eq(notifications.id, id), eq(notifications.userId, userId));
  if (action === "delete") {
    await db.delete(notifications).where(owns);
  } else {
    await db.update(notifications).set({ isRead: true, readAt: new Date() }).where(owns);
  }
  return { ok: true };
}
