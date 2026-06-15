import { z } from "zod";
import { readQuery, route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";
import { getUserOrders } from "@/server/orders";

export const GET = route(async (req) => {
  const user = await requireUser();
  const { status, q } = readQuery(req, z.object({ status: z.string().optional(), q: z.string().optional() }));
  const orders = await getUserOrders(user.id, { status, q });
  return success({ orders, count: orders.length });
});
