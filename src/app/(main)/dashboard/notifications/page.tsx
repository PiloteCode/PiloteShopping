"use client";

import * as React from "react";
import Link from "next/link";
import { AnimatePresence, motion } from "framer-motion";
import { BellOff, CheckCheck, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { DynamicIcon } from "@/components/icon";
import { apiGet, apiPost } from "@/lib/client";
import { cn, timeAgo } from "@/lib/utils";
import { toast } from "sonner";

interface Notification {
  id: number;
  type: string;
  title: string;
  message: string | null;
  link: string | null;
  icon: string | null;
  color: string | null;
  isRead: boolean;
  createdAt: string;
}
interface NotificationsResponse {
  notifications: Notification[];
  unreadCount: number;
}

const COLOR_MAP: Record<string, string> = {
  green: "bg-success/10 text-success",
  red: "bg-destructive/10 text-destructive",
  orange: "bg-warning/10 text-warning",
  yellow: "bg-warning/10 text-warning",
  blue: "bg-primary/10 text-primary",
  sky: "bg-primary/10 text-primary",
};

export default function NotificationsPage() {
  const [data, setData] = React.useState<NotificationsResponse | null>(null);

  const load = React.useCallback(async () => {
    setData(await apiGet<NotificationsResponse>("/api/v1/notifications"));
  }, []);

  React.useEffect(() => {
    load().catch(() => setData({ notifications: [], unreadCount: 0 }));
  }, [load]);

  async function action(body: Record<string, unknown>) {
    try {
      await apiPost("/api/v1/notifications", body);
      await load();
    } catch (e) {
      toast.error((e as Error).message);
    }
  }

  async function markAllRead() {
    if (!data?.unreadCount) return;
    try {
      await apiPost("/api/v1/notifications", { action: "mark_all_read" });
      toast.success("Toutes les notifications sont lues");
      await load();
    } catch (e) {
      toast.error((e as Error).message);
    }
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold sm:text-3xl">Notifications</h1>
          <p className="mt-1 text-muted-foreground">
            {data && data.unreadCount > 0
              ? `${data.unreadCount} notification${data.unreadCount > 1 ? "s" : ""} non lue${data.unreadCount > 1 ? "s" : ""}`
              : "Vous êtes à jour."}
          </p>
        </div>
        {data && data.unreadCount > 0 && (
          <Button variant="secondary" size="sm" onClick={markAllRead}>
            <CheckCheck /> Tout marquer comme lu
          </Button>
        )}
      </header>

      {data === null ? (
        <div className="space-y-3">
          {[0, 1, 2, 3].map((i) => <Skeleton key={i} className="h-20 w-full rounded-2xl" />)}
        </div>
      ) : data.notifications.length === 0 ? (
        <div className="flex flex-col items-center rounded-2xl border border-border bg-card px-4 py-16 text-center">
          <span className="grid h-16 w-16 place-items-center rounded-2xl bg-muted">
            <BellOff className="h-7 w-7 text-muted-foreground" />
          </span>
          <h2 className="mt-5 text-lg font-semibold">Aucune notification</h2>
          <p className="mt-1 text-muted-foreground">Vos notifications apparaîtront ici.</p>
        </div>
      ) : (
        <ul className="space-y-3">
          <AnimatePresence initial={false}>
            {data.notifications.map((n) => {
              const color = COLOR_MAP[n.color ?? ""] ?? "bg-primary/10 text-primary";
              return (
                <motion.li
                  key={n.id}
                  layout
                  initial={{ opacity: 0, y: 8 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, x: -20 }}
                  className={cn(
                    "flex items-start gap-3 rounded-2xl border p-4 transition-colors",
                    n.isRead ? "border-border bg-card" : "border-primary/20 bg-primary/5",
                  )}
                >
                  <span className={cn("relative grid h-10 w-10 shrink-0 place-items-center rounded-xl", color)}>
                    <DynamicIcon name={n.icon} className="h-5 w-5" />
                    {!n.isRead && (
                      <span className="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-primary ring-2 ring-card" />
                    )}
                  </span>

                  <div className="min-w-0 flex-1">
                    <NotificationBody n={n} onRead={() => !n.isRead && action({ action: "mark_read", id: n.id })} />
                    <p className="mt-1 text-xs text-muted-foreground">{timeAgo(n.createdAt)}</p>
                  </div>

                  <div className="flex shrink-0 items-center gap-1">
                    {!n.isRead && (
                      <button
                        type="button"
                        onClick={() => action({ action: "mark_read", id: n.id })}
                        className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        aria-label="Marquer comme lu"
                      >
                        <CheckCheck className="h-4 w-4" />
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={() => action({ action: "delete", id: n.id })}
                      className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-destructive"
                      aria-label="Supprimer"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                </motion.li>
              );
            })}
          </AnimatePresence>
        </ul>
      )}
    </div>
  );
}

function NotificationBody({ n, onRead }: { n: Notification; onRead: () => void }) {
  const content = (
    <>
      <p className="font-medium leading-tight">{n.title}</p>
      {n.message && <p className="mt-0.5 text-sm text-muted-foreground">{n.message}</p>}
    </>
  );

  if (n.link) {
    return (
      <Link href={n.link} onClick={onRead} className="block transition-opacity hover:opacity-80">
        {content}
      </Link>
    );
  }
  return (
    <button type="button" onClick={onRead} className="block w-full text-left">
      {content}
    </button>
  );
}
