"use client";

import * as React from "react";
import Link from "next/link";
import { motion } from "framer-motion";
import { ArrowLeft, MailCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export default function ForgotPasswordPage() {
  const [sent, setSent] = React.useState(false);
  const [email, setEmail] = React.useState("");

  return (
    <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.4 }} className="w-full max-w-md">
      <div className="rounded-2xl border border-border bg-card p-8 shadow-xl">
        {sent ? (
          <div className="text-center">
            <span className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-success/15"><MailCheck className="h-7 w-7 text-success" /></span>
            <h1 className="mt-4 text-2xl font-bold">Vérifiez vos emails</h1>
            <p className="mt-2 text-sm text-muted-foreground">Si un compte existe pour <span className="font-medium text-foreground">{email}</span>, un lien de réinitialisation vient d&apos;être envoyé.</p>
          </div>
        ) : (
          <>
            <h1 className="text-2xl font-bold">Mot de passe oublié</h1>
            <p className="mt-1 text-sm text-muted-foreground">Entrez votre email pour recevoir un lien de réinitialisation.</p>
            <form onSubmit={(e) => { e.preventDefault(); setSent(true); }} className="mt-6 space-y-4">
              <div className="space-y-1.5"><Label>Email</Label><Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required placeholder="vous@exemple.com" /></div>
              <Button type="submit" className="w-full" size="lg">Envoyer le lien</Button>
            </form>
          </>
        )}
        <Link href="/login" className="mt-6 flex items-center justify-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground">
          <ArrowLeft className="h-4 w-4" /> Retour à la connexion
        </Link>
      </div>
    </motion.div>
  );
}
