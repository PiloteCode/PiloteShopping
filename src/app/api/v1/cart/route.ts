import { assertCsrf, readBody, route, success } from "@/lib/api";
import { cartOwner } from "@/lib/auth";
import { addToCartSchema } from "@/lib/schemas";
import { addToCart, getCart } from "@/server/cart";

export const GET = route(async () => {
  const owner = await cartOwner();
  const cart = await getCart(owner);
  return success(cart);
});

export const POST = route(async (req) => {
  await assertCsrf(req);
  const body = await readBody(req, addToCartSchema);
  const owner = await cartOwner();
  const result = await addToCart(owner, body);
  const cart = await getCart(owner);
  return success({ ...result, summary: cart.summary }, "Produit ajouté au panier");
});
