import { z } from "zod";
import { assertCsrf, readBody, readQuery, route, success } from "@/lib/api";
import { requireUser } from "@/lib/auth";
import { getNotifications, notificationAction } from "@/server/account";

export const GET = route(async (req) => {
  const user = await requireUser();
  const { limit, offset, unread } = readQuery(
    req,
    z.object({
      limit: z.coerce.number().int().min(1).max(100).optional(),
      offset: z.coerce.number().int().min(0).optional(),
      unread: z.enum(["true", "false"]).optional(),
    }),
  );
  const data = await getNotifications(user.id, { limit, offset, unread: unread === "true" });
  return success(data);
});

export const POST = route(async (req) => {
  await assertCsrf(req);
  const user = await requireUser();
  const { action, id } = await readBody(
    req,
    z.object({ action: z.enum(["mark_read", "mark_all_read", "delete"]), id: z.coerce.number().int().optional() }),
  );
  const result = await notificationAction(user.id, action, id);
  const data = await getNotifications(user.id);
  return success({ ...result, unreadCount: data.unreadCount });
});
