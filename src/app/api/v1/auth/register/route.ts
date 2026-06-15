import { or, eq } from "drizzle-orm";
import { db } from "@/db";
import { users } from "@/db/schema";
import { assertCsrf, badRequest, created, readBody, route } from "@/lib/api";
import { registerSchema } from "@/lib/schemas";
import { createSession, guestSessionId, hashPassword } from "@/lib/auth";
import { mergeGuestCart } from "@/server/cart";
import { rateLimit } from "@/lib/rate-limit";
import { logSecurityEvent } from "@/lib/security";

export const POST = route(async (req) => {
  await assertCsrf(req);
  const ip = req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() || "local";
  rateLimit(`register:${ip}`, 5, 3600);

  const body = await readBody(req, registerSchema);

  const existing = await db
    .select({ id: users.id, email: users.email })
    .from(users)
    .where(or(eq(users.email, body.email), eq(users.username, body.username)))
    .limit(1);
  if (existing[0]) {
    throw badRequest(
      existing[0].email === body.email
        ? "Cet email est déjà utilisé"
        : "Ce nom d'utilisateur est déjà pris",
    );
  }

  const [u] = await db
    .insert(users)
    .values({
      uuid: crypto.randomUUID(),
      email: body.email,
      username: body.username,
      passwordHash: hashPassword(body.password),
      firstName: body.firstName,
      lastName: body.lastName,
      isActive: true,
      isVerified: true, // demo: skip email verification step
      profileCompleted: true,
    })
    .returning({ id: users.id, uuid: users.uuid, email: users.email, username: users.username });

  const guest = await guestSessionId();
  await createSession(u.id);
  await mergeGuestCart(u.id, guest);
  await logSecurityEvent(u.id, "register", { email: u.email });

  return created(
    {
      user: { id: u.id, uuid: u.uuid, email: u.email, username: u.username, firstName: body.firstName, lastName: body.lastName },
      nextStep: "dashboard",
    },
    "Compte créé avec succès",
  );
});
