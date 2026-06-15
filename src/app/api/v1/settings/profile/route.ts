import { eq } from "drizzle-orm";
import { db } from "@/db";
import { users } from "@/db/schema";
import { assertCsrf, readBody, route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";
import { profileUpdateSchema } from "@/lib/schemas";
import { logSecurityEvent } from "@/lib/security";

export const GET = route(async () => {
  const u = await requireUser();
  const row = (await db.select().from(users).where(eq(users.id, u.id)).limit(1))[0];
  return success({
    profile: {
      id: row.id,
      uuid: row.uuid,
      username: row.username,
      email: row.email,
      firstName: row.firstName,
      lastName: row.lastName,
      phone: row.phone,
      avatarUrl: row.avatarUrl,
      balance: parseFloat(row.balance),
      loyaltyPoints: row.loyaltyPoints,
      isVerified: row.isVerified,
      profileCompleted: row.profileCompleted,
      createdAt: row.createdAt.toISOString(),
    },
  });
});

export const PUT = route(async (req) => {
  await assertCsrf(req);
  const u = await requireUser();
  const body = await readBody(req, profileUpdateSchema);
  await db
    .update(users)
    .set({
      ...(body.firstName ? { firstName: body.firstName } : {}),
      ...(body.lastName ? { lastName: body.lastName } : {}),
      ...(body.phone !== undefined ? { phone: body.phone } : {}),
      updatedAt: new Date(),
    })
    .where(eq(users.id, u.id));
  await logSecurityEvent(u.id, "profile_updated");
  return success({ updated: true }, "Profil mis à jour avec succès");
});
