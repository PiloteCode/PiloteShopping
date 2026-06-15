"use client";

import * as React from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { motion } from "framer-motion";
import { Loader2, UserPlus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { apiPost } from "@/lib/client";
import { toast } from "sonner";

export default function RegisterPage() {
  const router = useRouter();
  const [loading, setLoading] = React.useState(false);
  const [f, setF] = React.useState({ firstName: "", lastName: "", username: "", email: "", password: "", confirmPassword: "" });
  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement>) => setF((s) => ({ ...s, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (f.password !== f.confirmPassword) { toast.error("Les mots de passe ne correspondent pas"); return; }
    setLoading(true);
    try {
      await apiPost("/api/v1/auth/register", f);
      toast.success("Compte créé, bienvenue !");
      router.push("/dashboard");
      router.refresh();
    } catch (e) {
      toast.error((e as Error).message);
      setLoading(false);
    }
  }

  return (
    <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.4 }} className="w-full max-w-md">
      <div className="rounded-2xl border border-border bg-card p-8 shadow-xl">
        <h1 className="text-2xl font-bold">Créer un compte</h1>
        <p className="mt-1 text-sm text-muted-foreground">Rejoignez Pilote Shopping et profitez de -15% avec <span className="font-mono text-primary">BIENVENUE15</span>.</p>

        <form onSubmit={submit} className="mt-6 space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5"><Label>Prénom</Label><Input value={f.firstName} onChange={set("firstName")} required /></div>
            <div className="space-y-1.5"><Label>Nom</Label><Input value={f.lastName} onChange={set("lastName")} required /></div>
          </div>
          <div className="space-y-1.5"><Label>Nom d&apos;utilisateur</Label><Input value={f.username} onChange={set("username")} required placeholder="janedoe" /></div>
          <div className="space-y-1.5"><Label>Email</Label><Input type="email" value={f.email} onChange={set("email")} required placeholder="vous@exemple.com" /></div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5"><Label>Mot de passe</Label><Input type="password" value={f.password} onChange={set("password")} required placeholder="••••••••" /></div>
            <div className="space-y-1.5"><Label>Confirmer</Label><Input type="password" value={f.confirmPassword} onChange={set("confirmPassword")} required placeholder="••••••••" /></div>
          </div>
          <p className="text-xs text-muted-foreground">8+ caractères, avec majuscule, chiffre et caractère spécial.</p>
          <Button type="submit" className="w-full" size="lg" disabled={loading}>
            {loading ? <Loader2 className="animate-spin" /> : <UserPlus />} Créer mon compte
          </Button>
        </form>

        <p className="mt-6 text-center text-sm text-muted-foreground">
          Déjà inscrit ? <Link href="/login" className="font-medium text-primary hover:underline">Se connecter</Link>
        </p>
      </div>
    </motion.div>
  );
}
