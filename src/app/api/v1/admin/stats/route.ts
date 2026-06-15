import { route, success } from "@/lib/api";
import { requireAdmin } from "@/lib/auth";
import { getAdminStats } from "@/server/admin";

export const GET = route(async () => {
  await requireAdmin();
  return success(await getAdminStats());
});
