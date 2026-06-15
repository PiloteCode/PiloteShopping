import { route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";
import { getOrderByNumber } from "@/server/orders";

export const GET = route(async (_req, ctx: { params: Promise<{ number: string }> }) => {
  const user = await requireUser();
  const order = await getOrderByNumber(user.id, (await ctx.params).number);
  return success(order);
});
