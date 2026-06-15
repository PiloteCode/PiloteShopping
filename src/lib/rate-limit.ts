import { tooMany } from "@/lib/api";

interface Bucket {
  count: number;
  resetAt: number;
}

const buckets = new Map<string, Bucket>();

/**
 * Simple in-memory sliding-window rate limiter (per-process). Good enough for a
 * single-node demo; swap for Redis in a distributed deployment.
 */
export function rateLimit(key: string, limit: number, windowSeconds: number) {
  const now = Date.now();
  const windowMs = windowSeconds * 1000;
  const existing = buckets.get(key);

  if (!existing || existing.resetAt < now) {
    buckets.set(key, { count: 1, resetAt: now + windowMs });
    return;
  }
  existing.count += 1;
  if (existing.count > limit) {
    const retry = Math.ceil((existing.resetAt - now) / 1000);
    throw tooMany(`Trop de tentatives. Réessayez dans ${retry}s.`);
  }
}

/** Occasionally evict stale buckets to bound memory. */
setInterval(() => {
  const now = Date.now();
  for (const [k, v] of buckets) if (v.resetAt < now) buckets.delete(k);
}, 60_000).unref?.();
