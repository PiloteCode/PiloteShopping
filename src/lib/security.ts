import "server-only";
import { headers } from "next/headers";
import { db } from "@/db";
import { securityLogs } from "@/db/schema";

/** Best-effort security audit log; never throws. */
export async function logSecurityEvent(
  userId: number | null,
  action: string,
  details: Record<string, unknown> = {},
  status: "success" | "failed" | "blocked" | "warning" = "success",
) {
  try {
    const h = await headers();
    await db.insert(securityLogs).values({
      userId: userId ?? null,
      action,
      details,
      ipAddress:
        h.get("x-forwarded-for")?.split(",")[0]?.trim() || h.get("x-real-ip") || null,
      userAgent: h.get("user-agent")?.slice(0, 500) ?? null,
      status,
    });
  } catch (err) {
    console.error("[security] log failed:", err);
  }
}
