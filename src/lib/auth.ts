import "server-only";
import { cookies, headers } from "next/headers";
import { and, eq, gt } from "drizzle-orm";
import bcrypt from "bcryptjs";
import { db } from "@/db";
import {
  adminUsers,
  users,
  userSessions,
  type User,
} from "@/db/schema";
import { generateSessionId } from "@/lib/domain";
import { unauthorized, forbidden } from "@/lib/api";

export const SESSION_COOKIE = process.env.SESSION_COOKIE || "pilote_session";
export const GUEST_COOKIE = "pilote_guest";
const SESSION_TTL_DAYS = 30;

export interface CurrentUser {
  id: number;
  uuid: string;
  username: string;
  email: string;
  firstName: string;
  lastName: string;
  avatarUrl: string | null;
  phone: string | null;
  balance: number;
  loyaltyPoints: number;
  isVerified: boolean;
  profileCompleted: boolean;
  isAdmin: boolean;
  role: string | null;
}

export function hashPassword(plain: string): string {
  return bcrypt.hashSync(plain, 10);
}
export function verifyPassword(plain: string, hash: string): boolean {
  if (!hash) return false;
  return bcrypt.compareSync(plain, hash);
}

function toCurrentUser(u: User, role: string | null): CurrentUser {
  return {
    id: u.id,
    uuid: u.uuid,
    username: u.username,
    email: u.email,
    firstName: u.firstName,
    lastName: u.lastName,
    avatarUrl: u.avatarUrl,
    phone: u.phone,
    balance: parseFloat(u.balance),
    loyaltyPoints: u.loyaltyPoints,
    isVerified: u.isVerified,
    profileCompleted: u.profileCompleted,
    isAdmin: !!role,
    role,
  };
}

/** Create a session row + set the httpOnly session cookie. Returns the token. */
export async function createSession(userId: number): Promise<string> {
  const token = generateSessionId();
  const h = await headers();
  const ua = h.get("user-agent")?.slice(0, 500) ?? null;
  const ip =
    h.get("x-forwarded-for")?.split(",")[0]?.trim() ||
    h.get("x-real-ip") ||
    null;
  const expiresAt = new Date(Date.now() + SESSION_TTL_DAYS * 86400_000);

  await db.insert(userSessions).values({
    id: token,
    userId,
    ipAddress: ip,
    userAgent: ua,
    isMobile: /mobile/i.test(ua ?? ""),
    expiresAt,
  });

  await db.update(users).set({ lastLoginAt: new Date(), lastIp: ip }).where(eq(users.id, userId));

  const jar = await cookies();
  jar.set(SESSION_COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge: SESSION_TTL_DAYS * 86400,
  });
  return token;
}

export async function destroySession(): Promise<void> {
  const jar = await cookies();
  const token = jar.get(SESSION_COOKIE)?.value;
  if (token) {
    await db.delete(userSessions).where(eq(userSessions.id, token));
  }
  jar.delete(SESSION_COOKIE);
}

/** Resolve the authenticated user from the session cookie, or null. */
export async function getCurrentUser(): Promise<CurrentUser | null> {
  const jar = await cookies();
  const token = jar.get(SESSION_COOKIE)?.value;
  if (!token) return null;

  const rows = await db
    .select({ user: users, role: adminUsers.role })
    .from(userSessions)
    .innerJoin(users, eq(users.id, userSessions.userId))
    .leftJoin(adminUsers, and(eq(adminUsers.userId, users.id), eq(adminUsers.isActive, true)))
    .where(and(eq(userSessions.id, token), gt(userSessions.expiresAt, new Date())))
    .limit(1);

  const row = rows[0];
  if (!row || !row.user.isActive) return null;
  return toCurrentUser(row.user, row.role ?? null);
}

export async function requireUser(): Promise<CurrentUser> {
  const u = await getCurrentUser();
  if (!u) throw unauthorized();
  return u;
}

export async function requireAdmin(): Promise<CurrentUser> {
  const u = await requireUser();
  if (!u.isAdmin) throw forbidden("Accès réservé à l'administration");
  return u;
}

export async function guestSessionId(): Promise<string | null> {
  return (await cookies()).get(GUEST_COOKIE)?.value ?? null;
}

/** Identify the cart owner: a logged-in user id, else the guest cookie id. */
export async function cartOwner(): Promise<{ userId: number | null; sessionId: string | null }> {
  const u = await getCurrentUser();
  if (u) return { userId: u.id, sessionId: null };
  const jar = await cookies();
  const sessionId = jar.get(GUEST_COOKIE)?.value ?? null;
  return { userId: null, sessionId };
}
