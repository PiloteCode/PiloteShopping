import { redirect } from "next/navigation";
import { getCurrentUser } from "@/lib/auth";
import { SidebarNav } from "@/components/dashboard/sidebar-nav";

export const dynamic = "force-dynamic";

export default async function DashboardLayout({ children }: { children: React.ReactNode }) {
  const user = await getCurrentUser();
  if (!user) redirect("/login");

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 lg:px-6">
      <div className="flex flex-col gap-8 lg:flex-row">
        <aside className="lg:w-64 lg:shrink-0">
          <div className="lg:sticky lg:top-24">
            <div className="mb-4 hidden lg:block">
              <p className="text-xs uppercase tracking-wider text-muted-foreground">Espace client</p>
              <p className="mt-0.5 truncate font-semibold">{user.firstName} {user.lastName}</p>
            </div>
            <SidebarNav />
          </div>
        </aside>
        <div className="min-w-0 flex-1">{children}</div>
      </div>
    </div>
  );
}
