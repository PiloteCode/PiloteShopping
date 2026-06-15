import Link from "next/link";
import { ArrowLeft, Sparkles } from "lucide-react";

export const dynamic = "force-dynamic";

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="relative flex min-h-screen flex-col">
      <div className="card-grid-bg absolute inset-0 -z-10 opacity-30" />
      <header className="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-6 lg:px-6">
        <Link href="/" className="flex items-center gap-2 font-semibold">
          <span className="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-primary to-chart-4 text-primary-foreground">
            <Sparkles className="h-5 w-5" />
          </span>
          <span className="text-lg">Pilote<span className="text-primary">Shopping</span></span>
        </Link>
        <Link href="/" className="flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground">
          <ArrowLeft className="h-4 w-4" /> Retour
        </Link>
      </header>
      <main className="flex flex-1 items-center justify-center px-4 py-10">{children}</main>
    </div>
  );
}
