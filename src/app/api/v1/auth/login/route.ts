import { eq } from "drizzle-orm";
import { db } from "@/db";
import { adminUsers, users } from "@/db/schema";
import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { loginSchema } from "@/lib/schemas";
import { createSession, guestSessionId, verifyPassword } from "@/lib/auth";
import { mergeGuestCart } from "@/server/cart";
import { rateLimit } from "@/lib/rate-limit";
import { logSecurityEvent } from "@/lib/security";

export const POST = route(async (req) => {
  await assertCsrf(req);
  const ip = req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() || "local";
  rateLimit(`login:${ip}`, 10, 900);

  const body = await readBody(req, loginSchema);

  const rows = await db
    .select({ user: users, role: adminUsers.role })
    .from(users)
    .leftJoin(adminUsers, eq(adminUsers.userId, users.id))
    .where(eq(users.email, body.email))
    .limit(1);
  const found = rows[0];

  if (!found || !verifyPassword(body.password, found.user.passwordHash)) {
    await logSecurityEvent(found?.user.id ?? null, "login_failed", { email: body.email }, "failed");
    throw badRequest("Identifiants incorrects");
  }
  if (!found.user.isActive) throw badRequest("Ce compte est désactivé");

  const guest = await guestSessionId();
  await createSession(found.user.id);
  await mergeGuestCart(found.user.id, guest);
  await logSecurityEvent(found.user.id, "login_success");

  return success(
    {
      user: {
        id: found.user.id,
        uuid: found.user.uuid,
        username: found.user.username,
        email: found.user.email,
        firstName: found.user.firstName,
        lastName: found.user.lastName,
        avatarUrl: found.user.avatarUrl,
        balance: parseFloat(found.user.balance),
        isAdmin: !!found.role,
      },
    },
    "Connexion réussie",
  );
});
