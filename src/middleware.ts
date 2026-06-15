import { NextResponse, type NextRequest } from "next/server";

const CSRF_COOKIE = process.env.CSRF_COOKIE || "pilote_csrf";
const GUEST_COOKIE = "pilote_guest";

function randomToken(): string {
  return (
    crypto.randomUUID().replace(/-/g, "") + crypto.randomUUID().replace(/-/g, "")
  );
}

/**
 * Ensures every visitor carries:
 *  - a readable CSRF token cookie (double-submit pattern), and
 *  - an httpOnly guest id (for anonymous carts).
 */
export function middleware(req: NextRequest) {
  const res = NextResponse.next();

  if (!req.cookies.get(CSRF_COOKIE)) {
    res.cookies.set(CSRF_COOKIE, randomToken(), {
      sameSite: "lax",
      path: "/",
      httpOnly: false,
    });
  }
  if (!req.cookies.get(GUEST_COOKIE)) {
    res.cookies.set(GUEST_COOKIE, randomToken(), {
      sameSite: "lax",
      path: "/",
      httpOnly: true,
    });
  }
  return res;
}

export const config = {
  matcher: [
    "/((?!_next/static|_next/image|favicon.ico|.*\\.(?:png|jpg|jpeg|gif|svg|webp|ico|txt|xml)$).*)",
  ],
};
