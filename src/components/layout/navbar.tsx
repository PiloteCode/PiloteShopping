"use client";

import * as React from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { motion } from "framer-motion";
import {
  Bell,
  ChevronDown,
  LayoutDashboard,
  LogOut,
  Menu,
  Package,
  Search,
  Settings,
  ShieldCheck,
  ShoppingCart,
  Sparkles,
  User,
  Wallet,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Sheet, SheetContent, SheetTrigger, SheetClose } from "@/components/ui/sheet";
import { DynamicIcon } from "@/components/icon";
import { useCart } from "@/components/providers";
import { apiPost } from "@/lib/client";
import { cn, formatPrice } from "@/lib/utils";
import { toast } from "sonner";

interface NavUser {
  firstName: string;
  lastName: string;
  username: string;
  email: string;
  avatarUrl: string | null;
  balance: number;
  isAdmin: boolean;
}
interface NavCategory {
  name: string;
  slug: string;
  icon: string | null;
  color: string | null;
}

export function Navbar({ user, categories }: { user: NavUser | null; categories: NavCategory[] }) {
  const router = useRouter();
  const { count, refresh } = useCart();
  const [query, setQuery] = React.useState("");

  React.useEffect(() => {
    refresh();
  }, [refresh]);

  async function logout() {
    try {
      await apiPost("/api/v1/auth/logout");
      toast.success("Déconnexion réussie");
      router.push("/");
      router.refresh();
    } catch {
      toast.error("Erreur lors de la déconnexion");
    }
  }

  function submitSearch(e: React.FormEvent) {
    e.preventDefault();
    router.push(`/products?q=${encodeURIComponent(query)}`);
  }

  const initials = user ? `${user.firstName[0] ?? ""}${user.lastName[0] ?? ""}`.toUpperCase() : "";

  return (
    <motion.header
      initial={{ y: -16, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ duration: 0.4, ease: "easeOut" }}
      className="sticky top-0 z-40 border-b border-border/60 bg-background/70 backdrop-blur-xl"
    >
      <div className="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 lg:px-6">
        <Link href="/" className="flex items-center gap-2 font-semibold">
          <span className="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-primary to-chart-4 text-primary-foreground shadow-lg shadow-primary/30">
            <Sparkles className="h-5 w-5" />
          </span>
          <span className="hidden text-lg sm:inline">Pilote<span className="text-primary">Shopping</span></span>
        </Link>

        <nav className="ml-2 hidden items-center gap-1 md:flex">
          <Link href="/products" className="rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
            Produits
          </Link>
          <DropdownMenu>
            <DropdownMenuTrigger className="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground outline-none transition-colors hover:text-foreground">
              Catégories <ChevronDown className="h-4 w-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-64">
              {categories.map((c) => (
                <DropdownMenuItem key={c.slug} asChild>
                  <Link href={`/products?category=${c.slug}`} className="cursor-pointer">
                    <span className="grid h-7 w-7 place-items-center rounded-md" style={{ backgroundColor: (c.color ?? "#0ea5e9") + "22", color: c.color ?? "#0ea5e9" }}>
                      <DynamicIcon name={c.icon} className="h-4 w-4" />
                    </span>
                    {c.name}
                  </Link>
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>
          <Link href="/api-docs" className="rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
            API
          </Link>
          <Link href="/support" className="rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
            Support
          </Link>
        </nav>

        <form onSubmit={submitSearch} className="relative ml-auto hidden max-w-xs flex-1 lg:block">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Rechercher un produit…"
            className="h-10 w-full rounded-xl border border-border bg-background/60 pl-9 pr-3 text-sm outline-none transition-colors focus:border-ring focus:ring-2 focus:ring-ring/50"
          />
        </form>

        <div className="flex items-center gap-1.5 lg:ml-2">
          <Link href="/cart" className="relative">
            <Button variant="ghost" size="icon" aria-label="Panier">
              <ShoppingCart className="h-5 w-5" />
            </Button>
            {count > 0 && (
              <span className="absolute -right-0.5 -top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1 text-[11px] font-bold text-primary-foreground">
                {count}
              </span>
            )}
          </Link>

          {user ? (
            <>
              <Link href="/dashboard/notifications" className="hidden sm:block">
                <Button variant="ghost" size="icon" aria-label="Notifications">
                  <Bell className="h-5 w-5" />
                </Button>
              </Link>
              <DropdownMenu>
                <DropdownMenuTrigger className="outline-none">
                  <Avatar className="h-9 w-9 ring-2 ring-border transition-all hover:ring-primary/50">
                    {user.avatarUrl ? <AvatarImage src={user.avatarUrl} alt={user.username} /> : null}
                    <AvatarFallback>{initials || <User className="h-4 w-4" />}</AvatarFallback>
                  </Avatar>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-64">
                  <div className="px-2.5 py-2">
                    <p className="font-semibold">{user.firstName} {user.lastName}</p>
                    <p className="text-xs text-muted-foreground">@{user.username}</p>
                    <div className="mt-2 flex items-center gap-2 rounded-lg bg-muted/50 px-2.5 py-1.5 text-sm">
                      <Wallet className="h-4 w-4 text-primary" />
                      <span className="font-medium">{formatPrice(user.balance)}</span>
                    </div>
                  </div>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem asChild><Link href="/dashboard"><LayoutDashboard /> Tableau de bord</Link></DropdownMenuItem>
                  <DropdownMenuItem asChild><Link href="/dashboard/orders"><Package /> Mes commandes</Link></DropdownMenuItem>
                  <DropdownMenuItem asChild><Link href="/dashboard/wallet"><Wallet /> Portefeuille</Link></DropdownMenuItem>
                  <DropdownMenuItem asChild><Link href="/dashboard/settings"><Settings /> Paramètres</Link></DropdownMenuItem>
                  {user.isAdmin && (
                    <DropdownMenuItem asChild><Link href="/admin"><ShieldCheck /> Administration</Link></DropdownMenuItem>
                  )}
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={logout} className="text-destructive focus:text-destructive">
                    <LogOut /> Se déconnecter
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </>
          ) : (
            <div className="hidden items-center gap-2 sm:flex">
              <Button variant="ghost" size="sm" asChild><Link href="/login">Connexion</Link></Button>
              <Button size="sm" asChild><Link href="/register">Inscription</Link></Button>
            </div>
          )}

          {/* Mobile menu */}
          <Sheet>
            <SheetTrigger asChild>
              <Button variant="ghost" size="icon" className="md:hidden" aria-label="Menu">
                <Menu className="h-5 w-5" />
              </Button>
            </SheetTrigger>
            <SheetContent side="right" className="w-80">
              <div className="mt-6 flex flex-col gap-1">
                {!user && (
                  <div className="mb-3 grid grid-cols-2 gap-2">
                    <SheetClose asChild><Button variant="outline" asChild><Link href="/login">Connexion</Link></Button></SheetClose>
                    <SheetClose asChild><Button asChild><Link href="/register">Inscription</Link></Button></SheetClose>
                  </div>
                )}
                <MobileLink href="/products">Produits</MobileLink>
                {categories.map((c) => (
                  <MobileLink key={c.slug} href={`/products?category=${c.slug}`}>{c.name}</MobileLink>
                ))}
                <MobileLink href="/api-docs">API</MobileLink>
                <MobileLink href="/support">Support</MobileLink>
                {user && (
                  <>
                    <div className="my-2 h-px bg-border" />
                    <MobileLink href="/dashboard">Tableau de bord</MobileLink>
                    <MobileLink href="/dashboard/orders">Mes commandes</MobileLink>
                    <MobileLink href="/dashboard/wallet">Portefeuille</MobileLink>
                    {user.isAdmin && <MobileLink href="/admin">Administration</MobileLink>}
                  </>
                )}
              </div>
            </SheetContent>
          </Sheet>
        </div>
      </div>
    </motion.header>
  );
}

function MobileLink({ href, children }: { href: string; children: React.ReactNode }) {
  return (
    <SheetClose asChild>
      <Link href={href} className={cn("rounded-lg px-3 py-2.5 text-sm font-medium text-foreground/90 transition-colors hover:bg-accent")}>
        {children}
      </Link>
    </SheetClose>
  );
}
