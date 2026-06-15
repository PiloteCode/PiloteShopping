import { cookies } from "next/headers";
import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { cartOwner } from "@/lib/auth";
import { couponSchema } from "@/lib/schemas";
import { COUPON_COOKIE, getAppliedCoupon, getCart, validateCoupon } from "@/server/cart";

export const GET = route(async () => {
  const owner = await cartOwner();
  const { items } = await getCart(owner);
  const coupon = await getAppliedCoupon(items);
  return success({ applied: !!coupon, coupon });
});

export const POST = route(async (req) => {
  await assertCsrf(req);
  const { code } = await readBody(req, couponSchema);
  const owner = await cartOwner();
  const { items } = await getCart(owner);
  if (items.length === 0) throw badRequest("Votre panier est vide");

  const result = await validateCoupon(code, items);
  if (!result.ok) throw badRequest(result.error || "Code promo invalide");

  (await cookies()).set(COUPON_COOKIE, result.coupon.code, { path: "/", sameSite: "lax", maxAge: 86400 });
  return success({ coupon: result.coupon }, "Code promo appliqué");
});

export const DELETE = route(async (req) => {
  await assertCsrf(req);
  (await cookies()).delete(COUPON_COOKIE);
  return success({ removed: true }, "Code promo retiré");
});
