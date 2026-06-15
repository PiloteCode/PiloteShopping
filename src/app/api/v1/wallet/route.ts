import { assertCsrf, readBody, route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";
import { addFundsSchema } from "@/lib/schemas";
import { addFunds, getWallet } from "@/server/account";

export const GET = route(async () => {
  const user = await requireUser();
  return success(await getWallet(user.id));
});

export const POST = route(async (req) => {
  await assertCsrf(req);
  const user = await requireUser();
  const { amount } = await readBody(req, addFundsSchema);
  const result = await addFunds(user.id, amount);
  return success(result, "Portefeuille rechargé");
});
