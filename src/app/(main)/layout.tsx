import { Navbar } from "@/components/layout/navbar";
import { Footer } from "@/components/layout/footer";
import { getCurrentUser } from "@/lib/auth";
import { getCategories } from "@/server/products";

export const dynamic = "force-dynamic";

export default async function MainLayout({ children }: { children: React.ReactNode }) {
  const [user, categories] = await Promise.all([getCurrentUser(), getCategories()]);

  const navUser = user
    ? {
        firstName: user.firstName,
        lastName: user.lastName,
        username: user.username,
        email: user.email,
        avatarUrl: user.avatarUrl,
        balance: user.balance,
        isAdmin: user.isAdmin,
      }
    : null;

  return (
    <div className="flex min-h-screen flex-col">
      <Navbar user={navUser} categories={categories.map((c) => ({ name: c.name, slug: c.slug, icon: c.icon, color: c.color }))} />
      <main className="flex-1">{children}</main>
      <Footer />
    </div>
  );
}
