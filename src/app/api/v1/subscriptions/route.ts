import { route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";
import { getSubscriptions } from "@/server/account";

export const GET = route(async () => {
  const user = await requireUser();
  const subscriptions = await getSubscriptions(user.id);
  return success({ subscriptions, count: subscriptions.length });
});
