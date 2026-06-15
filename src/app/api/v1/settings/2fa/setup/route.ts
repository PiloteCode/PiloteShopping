import { authenticator } from "otplib";
import QRCode from "qrcode";
import bcrypt from "bcryptjs";
import { eq } from "drizzle-orm";
import { db } from "@/db";
import { users2fa, users2faBackup } from "@/db/schema";
import { assertCsrf, badRequest, route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";
import { logSecurityEvent } from "@/lib/security";

function backupCode(): string {
  const a = new Uint8Array(4);
  crypto.getRandomValues(a);
  return Array.from(a, (b) => (b % 100).toString().padStart(2, "0")).join("").slice(0, 8);
}

export const POST = route(async (req) => {
  await assertCsrf(req);
  const u = await requireUser();

  const existing = (await db.select().from(users2fa).where(eq(users2fa.userId, u.id)).limit(1))[0];
  if (existing?.isEnabled) throw badRequest("La 2FA est déjà activée");

  const secret = authenticator.generateSecret();
  const otpauth = authenticator.keyuri(u.email, "Pilote Shopping", secret);
  const qrCode = await QRCode.toDataURL(otpauth);

  if (existing) {
    await db.update(users2fa).set({ secret, isEnabled: false, updatedAt: new Date() }).where(eq(users2fa.userId, u.id));
  } else {
    await db.insert(users2fa).values({ userId: u.id, secret, isEnabled: false });
  }

  await db.delete(users2faBackup).where(eq(users2faBackup.userId, u.id));
  const codes = Array.from({ length: 10 }, backupCode);
  await db.insert(users2faBackup).values(codes.map((c) => ({ userId: u.id, codeHash: bcrypt.hashSync(c, 10) })));

  await logSecurityEvent(u.id, "2fa_setup_started");
  return success({ secret, otpauthUrl: otpauth, qrCode, backupCodes: codes });
});
