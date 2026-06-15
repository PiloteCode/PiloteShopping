import { assertCsrf, readBody, route, success } from "@/lib/api";
import { cartOwner } from "@/lib/auth";
import { createOrderSchema } from "@/lib/schemas";
import { createOrder } from "@/server/orders";

export const POST = route(async (req) => {
  await assertCsrf(req);
  const body = await readBody(req, createOrderSchema);
  const owner = await cartOwner();
  const result = await createOrder(owner, body);
  return success(result, "Commande créée avec succès");
});
