"use client";

import * as React from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { motion } from "framer-motion";
import { Loader2, LogIn } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { apiPost } from "@/lib/client";
import { toast } from "sonner";

function LoginForm() {
  const router = useRouter();
  const params = useSearchParams();
  const next = params.get("next") || "/dashboard";
  const [email, setEmail] = React.useState("");
  const [password, setPassword] = React.useState("");
  const [loading, setLoading] = React.useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    try {
      await apiPost("/api/v1/auth/login", { email, password });
      toast.success("Connexion réussie");
      router.push(next);
      router.refresh();
    } catch (e) {
      toast.error((e as Error).message);
      setLoading(false);
    }
  }

  function fill(kind: "admin" | "demo") {
    if (kind === "admin") { setEmail("admin@piloteshopping.com"); setPassword("Admin1234!"); }
    else { setEmail("demo@piloteshopping.com"); setPassword("Demo1234!"); }
  }

  return (
    <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.4 }} className="w-full max-w-md">
      <div className="rounded-2xl border border-border bg-card p-8 shadow-xl">
        <h1 className="text-2xl font-bold">Bon retour 👋</h1>
        <p className="mt-1 text-sm text-muted-foreground">Connectez-vous à votre compte Pilote Shopping.</p>

        <form onSubmit={submit} className="mt-6 space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="email">Email</Label>
            <Input id="email" type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required placeholder="vous@exemple.com" />
          </div>
          <div className="space-y-1.5">
            <div className="flex items-center justify-between">
              <Label htmlFor="password">Mot de passe</Label>
              <Link href="/forgot-password" className="text-xs text-primary hover:underline">Oublié ?</Link>
            </div>
            <Input id="password" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required placeholder="••••••••" />
          </div>
          <Button type="submit" className="w-full" size="lg" disabled={loading}>
            {loading ? <Loader2 className="animate-spin" /> : <LogIn />} Se connecter
          </Button>
        </form>

        <div className="mt-5 rounded-xl border border-dashed border-border bg-muted/30 p-3 text-xs text-muted-foreground">
          <p className="font-medium text-foreground">Comptes de démonstration</p>
          <div className="mt-2 flex gap-2">
            <button onClick={() => fill("admin")} className="rounded-md bg-background px-2 py-1 transition-colors hover:text-primary">Admin</button>
            <button onClick={() => fill("demo")} className="rounded-md bg-background px-2 py-1 transition-colors hover:text-primary">Client</button>
          </div>
        </div>

        <p className="mt-6 text-center text-sm text-muted-foreground">
          Pas encore de compte ? <Link href="/register" className="font-medium text-primary hover:underline">Créer un compte</Link>
        </p>
      </div>
    </motion.div>
  );
}

export default function LoginPage() {
  return (
    <React.Suspense fallback={null}>
      <LoginForm />
    </React.Suspense>
  );
}
