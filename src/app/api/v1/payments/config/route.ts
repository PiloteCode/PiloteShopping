import { eq } from "drizzle-orm";
import { db } from "@/db";
import { paymentAdapters } from "@/db/schema";
import { route, success } from "@/lib/api";

export const GET = route(async () => {
  const adapters = await db
    .select()
    .from(paymentAdapters)
    .where(eq(paymentAdapters.isActive, true))
    .orderBy(paymentAdapters.sortOrder);

  const stripe = adapters.find((a) => a.slug === "stripe");
  const cfg = (stripe?.config ?? {}) as Record<string, unknown>;
  const demo = (process.env.DEMO_MODE ?? "true") !== "false";

  return success({
    demoMode: demo,
    publishableKey: process.env.STRIPE_PUBLISHABLE_KEY || (cfg.publishable_key as string) || "pk_demo",
    currency: "EUR",
    methods: adapters.map((a) => ({ slug: a.slug, name: a.name })),
  });
});
