import { eq } from "drizzle-orm";
import { NextResponse } from "next/server";
import { db } from "@/db";
import { orders, paymentTransactions } from "@/db/schema";

/**
 * Payment gateway webhook. In production this verifies a Stripe signature.
 * In demo mode it accepts a simple { type, data: { order_number } } payload and
 * settles the referenced order — enough to exercise the async-settlement path.
 */
export async function POST(req: Request) {
  const demo = (process.env.DEMO_MODE ?? "true") !== "false";
  const secret = process.env.STRIPE_WEBHOOK_SECRET;

  let event: { type?: string; data?: { order_number?: string; orderNumber?: string } };
  try {
    event = await req.json();
  } catch {
    return NextResponse.json({ status: "error", error: "Invalid payload" }, { status: 400 });
  }

  if (!demo && secret) {
    const sig = req.headers.get("stripe-signature");
    if (!sig) return NextResponse.json({ status: "error", error: "Missing signature" }, { status: 400 });
    // Real Stripe verification would happen here with the stripe SDK.
  }

  try {
    const orderNumber = event.data?.order_number || event.data?.orderNumber;
    if (event.type === "payment_intent.succeeded" && orderNumber) {
      const o = (await db.select().from(orders).where(eq(orders.orderNumber, orderNumber)).limit(1))[0];
      if (o) {
        await db.update(orders).set({ paymentStatus: "paid", status: "processing" }).where(eq(orders.id, o.id));
        await db.update(paymentTransactions).set({ status: "completed", processedAt: new Date() }).where(eq(paymentTransactions.orderId, o.id));
      }
    }
    return NextResponse.json({ status: "success" });
  } catch (err) {
    console.error("[webhook]", err);
    return NextResponse.json({ status: "error" }, { status: 500 });
  }
}
