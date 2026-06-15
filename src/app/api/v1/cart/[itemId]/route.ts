import { assertCsrf, badRequest, readBody, route, success } from "@/lib/api";
import { cartOwner } from "@/lib/auth";
import { updateCartSchema } from "@/lib/schemas";
import { getCart, removeCartItem, updateCartItem } from "@/server/cart";

export const PATCH = route(async (req, ctx: { params: Promise<{ itemId: string }> }) => {
  await assertCsrf(req);
  const itemId = Number((await ctx.params).itemId);
  if (!itemId) throw badRequest("Identifiant invalide");
  const { quantity } = await readBody(req, updateCartSchema);
  const owner = await cartOwner();
  const result = await updateCartItem(owner, itemId, quantity);
  const cart = await getCart(owner);
  return success({ ...result, summary: cart.summary }, result.removed ? "Article retiré" : "Quantité mise à jour");
});

export const DELETE = route(async (req, ctx: { params: Promise<{ itemId: string }> }) => {
  await assertCsrf(req);
  const itemId = Number((await ctx.params).itemId);
  if (!itemId) throw badRequest("Identifiant invalide");
  const owner = await cartOwner();
  await removeCartItem(owner, itemId);
  const cart = await getCart(owner);
  return success({ removed: true, summary: cart.summary }, "Article retiré du panier");
});
