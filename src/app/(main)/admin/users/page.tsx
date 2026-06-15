"use client";

import * as React from "react";
import { Search } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { apiGet, apiPatch } from "@/lib/client";
import { cn, formatPrice } from "@/lib/utils";
import { toast } from "sonner";

interface AdminUser {
  id: number;
  username: string;
  email: string;
  firstName: string;
  lastName: string;
  isActive: boolean;
  isVerified: boolean;
  balance: number;
  createdAt: string;
  orderCount: number;
  spent: number;
}

export default function AdminUsersPage() {
  const [users, setUsers] = React.useState<AdminUser[] | null>(null);
  const [query, setQuery] = React.useState("");
  const [busy, setBusy] = React.useState<number | null>(null);

  const load = React.useCallback(async () => {
    const data = await apiGet<{ users: AdminUser[] }>("/api/v1/admin/users");
    setUsers(data.users);
  }, []);

  React.useEffect(() => {
    load().catch((e) => {
      setUsers([]);
      toast.error((e as Error).message);
    });
  }, [load]);

  async function patch(u: AdminUser, body: { isActive?: boolean; isVerified?: boolean }) {
    setBusy(u.id);
    try {
      await apiPatch(`/api/v1/admin/users/${u.id}`, body);
      await load();
      toast.success("Utilisateur mis à jour");
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(null);
    }
  }

  const q = query.trim().toLowerCase();
  const filtered =
    users?.filter(
      (u) => !q || u.email.toLowerCase().includes(q) || u.username.toLowerCase().includes(q),
    ) ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-3xl font-bold">Utilisateurs</h1>
          <p className="mt-1 text-muted-foreground">
            {users ? `${users.length} utilisateur${users.length > 1 ? "s" : ""}` : "Gestion des comptes"}
          </p>
        </div>
        <div className="relative w-full sm:w-72">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Rechercher par email ou pseudo…"
            className="pl-9"
          />
        </div>
      </div>

      {!users ? (
        <Skeleton className="h-96 w-full rounded-2xl" />
      ) : filtered.length === 0 ? (
        <div className="rounded-2xl border border-border bg-card px-6 py-16 text-center text-muted-foreground">
          Aucun utilisateur {q ? "ne correspond à la recherche" : ""}.
        </div>
      ) : (
        <div className="overflow-x-auto rounded-2xl border border-border bg-card">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                <th className="px-5 py-3 font-medium">Utilisateur</th>
                <th className="px-5 py-3 font-medium">Email</th>
                <th className="px-5 py-3 text-right font-medium">Commandes</th>
                <th className="px-5 py-3 text-right font-medium">Dépensé</th>
                <th className="px-5 py-3 text-right font-medium">Solde</th>
                <th className="px-5 py-3 text-center font-medium">Vérifié</th>
                <th className="px-5 py-3 text-center font-medium">Actif</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((u) => {
                const fullName = `${u.firstName} ${u.lastName}`.trim() || u.username;
                return (
                  <tr key={u.id} className={cn("border-b border-border/60 last:border-0 hover:bg-accent/40", busy === u.id && "opacity-60")}>
                    <td className="px-5 py-3">
                      <p className="font-medium">{fullName}</p>
                      <p className="text-xs text-muted-foreground">@{u.username}</p>
                    </td>
                    <td className="max-w-[14rem] truncate px-5 py-3 text-muted-foreground">{u.email}</td>
                    <td className="px-5 py-3 text-right text-muted-foreground">{u.orderCount}</td>
                    <td className="px-5 py-3 text-right font-semibold">{formatPrice(u.spent)}</td>
                    <td className="px-5 py-3 text-right text-muted-foreground">{formatPrice(u.balance)}</td>
                    <td className="px-5 py-3">
                      <div className="flex items-center justify-center gap-2">
                        <Badge variant={u.isVerified ? "success" : "outline"}>{u.isVerified ? "Oui" : "Non"}</Badge>
                        <Switch
                          checked={u.isVerified}
                          disabled={busy === u.id}
                          onCheckedChange={(v) => patch(u, { isVerified: v })}
                          aria-label="Vérifier l'utilisateur"
                        />
                      </div>
                    </td>
                    <td className="px-5 py-3">
                      <div className="flex justify-center">
                        <Switch
                          checked={u.isActive}
                          disabled={busy === u.id}
                          onCheckedChange={(v) => patch(u, { isActive: v })}
                          aria-label="Activer l'utilisateur"
                        />
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
