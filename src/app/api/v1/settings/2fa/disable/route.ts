import { authenticator } from "otplib";
import { z } from "zod";
import { eq } from "drizzle-orm";
import { db } from "@/db";
import { users, users2fa, users2faBackup } from "@/db/schema";
import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { requireUser, verifyPassword } from "@/lib/auth";
import { logSecurityEvent } from "@/lib/security";

export const POST = route(async (req) => {
  await assertCsrf(req);
  const u = await requireUser();
  const body = await readBody(
    req,
    z.object({ password: z.string().optional(), code: z.string().optional() }),
  );

  const row = (await db.select().from(users2fa).where(eq(users2fa.userId, u.id)).limit(1))[0];
  if (!row?.isEnabled) throw badRequest("La 2FA n'est pas activée");

  let verified = false;
  if (body.code && row.secret) verified = authenticator.check(body.code, row.secret);
  if (!verified && body.password) {
    const usr = (await db.select({ h: users.passwordHash }).from(users).where(eq(users.id, u.id)).limit(1))[0];
    verified = verifyPassword(body.password, usr.h);
  }
  if (!verified) throw badRequest("Vérification échouée (mot de passe ou code incorrect)");

  await db.update(users2fa).set({ isEnabled: false, secret: null, updatedAt: new Date() }).where(eq(users2fa.userId, u.id));
  await db.delete(users2faBackup).where(eq(users2faBackup.userId, u.id));
  await logSecurityEvent(u.id, "2fa_disabled");
  return success({ disabled: true }, "Authentification à deux facteurs désactivée");
});
