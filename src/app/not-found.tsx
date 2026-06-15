import Link from "next/link";
import { Home, Search } from "lucide-react";
import { Button } from "@/components/ui/button";

export default function NotFound() {
  return (
    <div className="relative flex min-h-screen flex-col items-center justify-center px-4 text-center">
      <div className="card-grid-bg absolute inset-0 -z-10 opacity-30" />
      <p className="text-8xl font-black text-gradient">404</p>
      <h1 className="mt-4 text-2xl font-bold">Page introuvable</h1>
      <p className="mt-2 max-w-md text-muted-foreground">
        La page que vous recherchez n&apos;existe pas ou a été déplacée.
      </p>
      <div className="mt-8 flex gap-3">
        <Button asChild><Link href="/"><Home /> Accueil</Link></Button>
        <Button variant="outline" asChild><Link href="/products"><Search /> Boutique</Link></Button>
      </div>
    </div>
  );
}
