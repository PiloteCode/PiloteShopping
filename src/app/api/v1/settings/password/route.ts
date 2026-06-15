import { and, eq, ne } from "drizzle-orm";
import { cookies } from "next/headers";
import { db } from "@/db";
import { users, userSessions } from "@/db/schema";
import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { hashPassword, requireUser, SESSION_COOKIE, verifyPassword } from "@/lib/auth";
import { passwordChangeSchema } from "@/lib/schemas";
import { logSecurityEvent } from "@/lib/security";

export const PUT = route(async (req) => {
  await assertCsrf(req);
  const u = await requireUser();
  const body = await readBody(req, passwordChangeSchema);

  const row = (await db.select({ passwordHash: users.passwordHash }).from(users).where(eq(users.id, u.id)).limit(1))[0];
  if (!verifyPassword(body.currentPassword, row.passwordHash)) {
    throw badRequest("Mot de passe actuel incorrect");
  }
  if (verifyPassword(body.newPassword, row.passwordHash)) {
    throw badRequest("Le nouveau mot de passe doit être différent");
  }

  await db.update(users).set({ passwordHash: hashPassword(body.newPassword), updatedAt: new Date() }).where(eq(users.id, u.id));

  // Revoke other sessions
  const current = (await cookies()).get(SESSION_COOKIE)?.value ?? "";
  await db.delete(userSessions).where(and(eq(userSessions.userId, u.id), ne(userSessions.id, current)));
  await logSecurityEvent(u.id, "password_changed");

  return success({ updated: true }, "Mot de passe modifié. Vos autres sessions ont été fermées.");
});
