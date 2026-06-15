import { assertCsrf, route, success } from "@/lib/api";
import { destroySession, getCurrentUser } from "@/lib/auth";
import { logSecurityEvent } from "@/lib/security";

export const POST = route(async (req) => {
  await assertCsrf(req);
  const u = await getCurrentUser();
  await destroySession();
  if (u) await logSecurityEvent(u.id, "logout");
  return success({ loggedOut: true }, "Déconnexion réussie");
});
