import { z } from "zod";
import { and, desc, eq, ne } from "drizzle-orm";
import { cookies } from "next/headers";
import { db } from "@/db";
import { userSessions } from "@/db/schema";
import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { requireUser, SESSION_COOKIE } from "@/lib/auth";

export const GET = route(async () => {
  const u = await requireUser();
  const current = (await cookies()).get(SESSION_COOKIE)?.value ?? "";
  const rows = await db
    .select()
    .from(userSessions)
    .where(eq(userSessions.userId, u.id))
    .orderBy(desc(userSessions.lastActiveAt));

  return success({
    sessions: rows.map((s) => ({
      id: s.id,
      ipAddress: s.ipAddress,
      userAgent: s.userAgent,
      isMobile: s.isMobile,
      isCurrent: s.id === current,
      createdAt: s.createdAt.toISOString(),
      lastActiveAt: s.lastActiveAt.toISOString(),
      expiresAt: s.expiresAt.toISOString(),
    })),
  });
});

export const DELETE = route(async (req) => {
  await assertCsrf(req);
  const u = await requireUser();
  const current = (await cookies()).get(SESSION_COOKIE)?.value ?? "";
  const { sessionId } = await readBody(req, z.object({ sessionId: z.string().min(1) }));

  if (sessionId === "__all_others__") {
    const deleted = await db
      .delete(userSessions)
      .where(and(eq(userSessions.userId, u.id), ne(userSessions.id, current)))
      .returning({ id: userSessions.id });
    return success({ revokedCount: deleted.length }, "Autres sessions révoquées");
  }
  if (sessionId === current) throw badRequest("Utilisez la déconnexion pour la session courante");

  await db.delete(userSessions).where(and(eq(userSessions.userId, u.id), eq(userSessions.id, sessionId)));
  return success({ revoked: true }, "Session révoquée");
});
