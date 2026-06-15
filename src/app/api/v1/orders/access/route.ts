import { z } from "zod";
import { assertCsrf, readBody, route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";
import { accessProduct } from "@/server/orders";

export const POST = route(async (req) => {
  await assertCsrf(req);
  const user = await requireUser();
  const { orderItemId } = await readBody(req, z.object({ orderItemId: z.coerce.number().int().positive() }));
  const data = await accessProduct(user.id, orderItemId);
  return success(data);
});
