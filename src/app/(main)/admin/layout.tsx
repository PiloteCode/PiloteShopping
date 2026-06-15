import { redirect } from "next/navigation";
import { ShieldCheck } from "lucide-react";
import { getCurrentUser } from "@/lib/auth";
import { SidebarNav } from "@/components/admin/sidebar-nav";

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  const user = await getCurrentUser();
  if (!user || !user.isAdmin) redirect("/");

  return (
    <div className="mx-auto max-w-7xl px-4 py-8">
      <div className="grid gap-6 lg:grid-cols-[15rem_1fr]">
        <aside className="lg:sticky lg:top-24 lg:h-fit">
          <div className="mb-4 flex items-center gap-2.5">
            <span className="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-primary/20 to-chart-4/20 text-primary">
              <ShieldCheck className="h-5 w-5" />
            </span>
            <div>
              <p className="text-sm font-semibold leading-tight">Administration</p>
              <p className="text-xs text-muted-foreground leading-tight">Pilote Shopping</p>
            </div>
          </div>
          <SidebarNav />
        </aside>

        <main className="min-w-0">{children}</main>
      </div>
    </div>
  );
}
