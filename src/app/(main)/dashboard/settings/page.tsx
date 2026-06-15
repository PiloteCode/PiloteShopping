"use client";

import * as React from "react";
import {
  Check,
  Copy,
  Loader2,
  Lock,
  Monitor,
  ShieldCheck,
  Smartphone,
  User,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { apiDelete, apiGet, apiPost, apiPut } from "@/lib/client";
import { timeAgo } from "@/lib/utils";
import { toast } from "sonner";

export default function SettingsPage() {
  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold sm:text-3xl">Paramètres</h1>
        <p className="mt-1 text-muted-foreground">Gérez votre profil, votre sécurité et vos sessions.</p>
      </header>

      <Tabs defaultValue="profile">
        <TabsList className="w-full max-w-md">
          <TabsTrigger value="profile" className="flex-1"><User /> Profil</TabsTrigger>
          <TabsTrigger value="security" className="flex-1"><ShieldCheck /> Sécurité</TabsTrigger>
          <TabsTrigger value="sessions" className="flex-1"><Monitor /> Sessions</TabsTrigger>
        </TabsList>

        <TabsContent value="profile"><ProfileTab /></TabsContent>
        <TabsContent value="security"><SecurityTab /></TabsContent>
        <TabsContent value="sessions"><SessionsTab /></TabsContent>
      </Tabs>
    </div>
  );
}

/* ── Profil ── */

interface Profile {
  firstName: string;
  lastName: string;
  phone: string | null;
  email: string;
  username: string;
}

function ProfileTab() {
  const [profile, setProfile] = React.useState<Profile | null>(null);
  const [form, setForm] = React.useState({ firstName: "", lastName: "", phone: "" });
  const [busy, setBusy] = React.useState(false);

  React.useEffect(() => {
    apiGet<{ profile: Profile }>("/api/v1/settings/profile")
      .then((d) => {
        setProfile(d.profile);
        setForm({ firstName: d.profile.firstName, lastName: d.profile.lastName, phone: d.profile.phone ?? "" });
      })
      .catch((e) => toast.error((e as Error).message));
  }, []);

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await apiPut("/api/v1/settings/profile", form);
      toast.success("Profil mis à jour");
    } catch (err) {
      toast.error((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  if (!profile) return <Skeleton className="h-80 w-full rounded-2xl" />;

  return (
    <form onSubmit={save} className="rounded-2xl border border-border bg-card p-6">
      <h2 className="font-semibold">Informations personnelles</h2>
      <p className="mt-1 text-sm text-muted-foreground">Ces informations apparaissent sur vos commandes.</p>

      <div className="mt-5 grid gap-4 sm:grid-cols-2">
        <div>
          <Label htmlFor="firstName">Prénom</Label>
          <Input id="firstName" className="mt-1.5" value={form.firstName} onChange={(e) => setForm((f) => ({ ...f, firstName: e.target.value }))} />
        </div>
        <div>
          <Label htmlFor="lastName">Nom</Label>
          <Input id="lastName" className="mt-1.5" value={form.lastName} onChange={(e) => setForm((f) => ({ ...f, lastName: e.target.value }))} />
        </div>
        <div>
          <Label htmlFor="phone">Téléphone</Label>
          <Input id="phone" type="tel" className="mt-1.5" value={form.phone} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} placeholder="Optionnel" />
        </div>
        <div>
          <Label htmlFor="email">Email</Label>
          <Input id="email" className="mt-1.5" value={profile.email} disabled />
        </div>
      </div>

      <div className="mt-5 flex items-center justify-between gap-3">
        <p className="text-xs text-muted-foreground">Identifiant : <span className="font-medium text-foreground">@{profile.username}</span></p>
        <Button type="submit" disabled={busy}>
          {busy ? <Loader2 className="animate-spin" /> : <Check />} Enregistrer
        </Button>
      </div>
    </form>
  );
}

/* ── Sécurité ── */

interface TwoFactorSetup {
  secret: string;
  otpauthUrl: string;
  qrCode: string;
  backupCodes: string[];
}

function SecurityTab() {
  return (
    <div className="space-y-6">
      <PasswordCard />
      <TwoFactorCard />
    </div>
  );
}

function PasswordCard() {
  const [form, setForm] = React.useState({ currentPassword: "", newPassword: "", confirm: "" });
  const [busy, setBusy] = React.useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (form.newPassword !== form.confirm) {
      toast.error("Les mots de passe ne correspondent pas");
      return;
    }
    setBusy(true);
    try {
      await apiPut("/api/v1/settings/password", {
        currentPassword: form.currentPassword,
        newPassword: form.newPassword,
      });
      toast.success("Mot de passe modifié. Vos autres sessions ont été fermées.");
      setForm({ currentPassword: "", newPassword: "", confirm: "" });
    } catch (err) {
      toast.error((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} className="rounded-2xl border border-border bg-card p-6">
      <h2 className="flex items-center gap-2 font-semibold"><Lock className="h-4 w-4 text-muted-foreground" /> Mot de passe</h2>
      <p className="mt-1 text-sm text-muted-foreground">Min. 8 caractères, avec majuscule, minuscule, chiffre et caractère spécial.</p>

      <div className="mt-5 grid gap-4 sm:max-w-md">
        <div>
          <Label htmlFor="currentPassword">Mot de passe actuel</Label>
          <Input id="currentPassword" type="password" autoComplete="current-password" className="mt-1.5" value={form.currentPassword} onChange={(e) => setForm((f) => ({ ...f, currentPassword: e.target.value }))} required />
        </div>
        <div>
          <Label htmlFor="newPassword">Nouveau mot de passe</Label>
          <Input id="newPassword" type="password" autoComplete="new-password" className="mt-1.5" value={form.newPassword} onChange={(e) => setForm((f) => ({ ...f, newPassword: e.target.value }))} required />
        </div>
        <div>
          <Label htmlFor="confirm">Confirmer le nouveau mot de passe</Label>
          <Input id="confirm" type="password" autoComplete="new-password" className="mt-1.5" value={form.confirm} onChange={(e) => setForm((f) => ({ ...f, confirm: e.target.value }))} required />
        </div>
      </div>

      <Button type="submit" className="mt-5" disabled={busy}>
        {busy ? <Loader2 className="animate-spin" /> : <Check />} Mettre à jour
      </Button>
    </form>
  );
}

function TwoFactorCard() {
  const [setup, setSetup] = React.useState<TwoFactorSetup | null>(null);
  const [enabled, setEnabled] = React.useState(false);
  const [code, setCode] = React.useState("");
  const [password, setPassword] = React.useState("");
  const [mode, setMode] = React.useState<"idle" | "setup" | "disable">("idle");
  const [busy, setBusy] = React.useState(false);

  async function startSetup() {
    setBusy(true);
    try {
      const data = await apiPost<TwoFactorSetup>("/api/v1/settings/2fa/setup");
      setSetup(data);
      setMode("setup");
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function verify(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await apiPost("/api/v1/settings/2fa/verify", { code });
      toast.success("Authentification à deux facteurs activée");
      setEnabled(true);
      setMode("idle");
      setSetup(null);
      setCode("");
    } catch (err) {
      toast.error((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function disable(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await apiPost("/api/v1/settings/2fa/disable", { password });
      toast.success("Authentification à deux facteurs désactivée");
      setEnabled(false);
      setMode("idle");
      setPassword("");
    } catch (err) {
      toast.error((err as Error).message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="rounded-2xl border border-border bg-card p-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="flex items-center gap-2 font-semibold">
            <ShieldCheck className="h-4 w-4 text-muted-foreground" /> Authentification à deux facteurs
            {enabled && <Badge variant="success">Activée</Badge>}
          </h2>
          <p className="mt-1 max-w-md text-sm text-muted-foreground">
            Ajoutez une couche de sécurité supplémentaire avec une application d&apos;authentification (Google Authenticator, Authy…).
          </p>
        </div>
        <div className="flex gap-2">
          {mode === "idle" && !enabled && (
            <Button variant="secondary" onClick={startSetup} disabled={busy}>
              {busy ? <Loader2 className="animate-spin" /> : <ShieldCheck />} Activer la 2FA
            </Button>
          )}
          {mode === "idle" && enabled && (
            <Button variant="ghost" className="text-destructive hover:text-destructive" onClick={() => setMode("disable")}>
              Désactiver
            </Button>
          )}
        </div>
      </div>

      {mode === "setup" && setup && (
        <div className="mt-6 grid gap-6 border-t border-border pt-6 md:grid-cols-2">
          <div>
            <p className="text-sm font-medium">1. Scannez ce QR code</p>
            <div className="mt-3 inline-block rounded-xl bg-white p-3">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={setup.qrCode} alt="QR code de configuration 2FA" className="h-44 w-44" />
            </div>
            <p className="mt-3 text-xs text-muted-foreground">Ou saisissez la clé manuellement :</p>
            <CopyRow value={setup.secret} mono />
          </div>

          <div>
            <p className="text-sm font-medium">2. Codes de secours</p>
            <p className="mt-1 text-xs text-muted-foreground">Conservez-les en lieu sûr. Chaque code n&apos;est utilisable qu&apos;une fois.</p>
            <div className="mt-3 grid grid-cols-2 gap-2">
              {setup.backupCodes.map((c) => (
                <code key={c} className="rounded-lg border border-border bg-background/40 px-3 py-1.5 text-center font-mono text-sm">{c}</code>
              ))}
            </div>

            <form onSubmit={verify} className="mt-5">
              <Label htmlFor="totp">3. Entrez le code à 6 chiffres</Label>
              <div className="mt-1.5 flex gap-2">
                <Input
                  id="totp"
                  inputMode="numeric"
                  pattern="\d{6}"
                  maxLength={6}
                  placeholder="000000"
                  className="font-mono tracking-widest"
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, "").slice(0, 6))}
                  required
                />
                <Button type="submit" disabled={busy || code.length !== 6}>
                  {busy ? <Loader2 className="animate-spin" /> : "Vérifier"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}

      {mode === "disable" && (
        <form onSubmit={disable} className="mt-6 border-t border-border pt-6 sm:max-w-md">
          <Label htmlFor="disable-pw">Confirmez avec votre mot de passe</Label>
          <div className="mt-1.5 flex gap-2">
            <Input id="disable-pw" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
            <Button type="submit" variant="destructive" disabled={busy}>
              {busy ? <Loader2 className="animate-spin" /> : "Désactiver"}
            </Button>
            <Button type="button" variant="ghost" onClick={() => { setMode("idle"); setPassword(""); }}>Annuler</Button>
          </div>
        </form>
      )}
    </div>
  );
}

function CopyRow({ value, mono }: { value: string; mono?: boolean }) {
  const [copied, setCopied] = React.useState(false);
  async function copy() {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      toast.success("Copié");
      setTimeout(() => setCopied(false), 1600);
    } catch {
      toast.error("Impossible de copier");
    }
  }
  return (
    <div className="mt-1.5 flex items-center gap-2 rounded-lg border border-border bg-background/40 p-2.5">
      <code className={`flex-1 truncate text-sm ${mono ? "font-mono" : ""}`}>{value}</code>
      <button type="button" onClick={copy} className="text-muted-foreground transition-colors hover:text-primary" aria-label="Copier">
        {copied ? <Check className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}
      </button>
    </div>
  );
}

/* ── Sessions ── */

interface Session {
  id: string;
  ipAddress: string | null;
  userAgent: string | null;
  isMobile: boolean;
  isCurrent: boolean;
  createdAt: string;
  lastActiveAt: string;
  expiresAt: string;
}

function deviceLabel(ua: string | null): string {
  if (!ua) return "Appareil inconnu";
  const browser =
    /edg/i.test(ua) ? "Edge" : /chrome/i.test(ua) ? "Chrome" : /firefox/i.test(ua) ? "Firefox" : /safari/i.test(ua) ? "Safari" : "Navigateur";
  const os =
    /windows/i.test(ua) ? "Windows" : /mac/i.test(ua) ? "macOS" : /android/i.test(ua) ? "Android" : /iphone|ipad|ios/i.test(ua) ? "iOS" : /linux/i.test(ua) ? "Linux" : "";
  return os ? `${browser} · ${os}` : browser;
}

function SessionsTab() {
  const [sessions, setSessions] = React.useState<Session[] | null>(null);
  const [busy, setBusy] = React.useState<string | null>(null);

  const load = React.useCallback(async () => {
    const d = await apiGet<{ sessions: Session[] }>("/api/v1/settings/sessions");
    setSessions(d.sessions);
  }, []);

  React.useEffect(() => {
    load().catch(() => setSessions([]));
  }, [load]);

  async function revoke(sessionId: string) {
    setBusy(sessionId);
    try {
      await apiDelete("/api/v1/settings/sessions", { sessionId });
      toast.success(sessionId === "__all_others__" ? "Autres sessions déconnectées" : "Session révoquée");
      await load();
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setBusy(null);
    }
  }

  if (sessions === null) return <Skeleton className="h-72 w-full rounded-2xl" />;

  const others = sessions.filter((s) => !s.isCurrent);

  return (
    <div className="rounded-2xl border border-border bg-card">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border p-5">
        <div>
          <h2 className="font-semibold">Sessions actives</h2>
          <p className="text-sm text-muted-foreground">Appareils connectés à votre compte.</p>
        </div>
        {others.length > 0 && (
          <Button variant="secondary" size="sm" onClick={() => revoke("__all_others__")} disabled={busy === "__all_others__"}>
            {busy === "__all_others__" ? <Loader2 className="animate-spin" /> : null} Déconnecter les autres
          </Button>
        )}
      </div>

      <ul className="divide-y divide-border">
        {sessions.map((s) => (
          <li key={s.id} className="flex items-center gap-4 p-4">
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-muted text-muted-foreground">
              {s.isMobile ? <Smartphone className="h-5 w-5" /> : <Monitor className="h-5 w-5" />}
            </span>
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">{deviceLabel(s.userAgent)}</span>
                {s.isCurrent && <Badge variant="success">Session actuelle</Badge>}
              </div>
              <p className="mt-0.5 text-xs text-muted-foreground">
                {s.ipAddress ?? "IP inconnue"} · Actif {timeAgo(s.lastActiveAt)}
              </p>
            </div>
            {!s.isCurrent && (
              <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive" onClick={() => revoke(s.id)} disabled={busy === s.id}>
                {busy === s.id ? <Loader2 className="animate-spin" /> : "Révoquer"}
              </Button>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}
