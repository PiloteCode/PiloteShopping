import { route, success } from "@/lib/api";
import { getCurrentUser } from "@/lib/auth";

export const GET = route(async () => {
  const user = await getCurrentUser();
  return success({ user });
});
