import { authenticator } from "otplib";
import { z } from "zod";
import { eq } from "drizzle-orm";
import { db } from "@/db";
import { users2fa } from "@/db/schema";
import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";
import { logSecurityEvent } from "@/lib/security";

export const POST = route(async (req) => {
  await assertCsrf(req);
  const u = await requireUser();
  const { code } = await readBody(req, z.object({ code: z.string().regex(/^\d{6}$/, "Code à 6 chiffres requis") }));

  const row = (await db.select().from(users2fa).where(eq(users2fa.userId, u.id)).limit(1))[0];
  if (!row?.secret) throw badRequest("Aucune configuration 2FA trouvée");
  if (row.isEnabled) throw badRequest("La 2FA est déjà activée");
  if (!authenticator.check(code, row.secret)) throw badRequest("Code de vérification incorrect");

  await db.update(users2fa).set({ isEnabled: true, activatedAt: new Date(), updatedAt: new Date() }).where(eq(users2fa.userId, u.id));
  await logSecurityEvent(u.id, "2fa_enabled");
  return success({ enabled: true }, "Authentification à deux facteurs activée");
});
