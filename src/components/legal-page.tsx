import Link from "next/link";
import { ArrowLeft, CalendarClock, ShieldCheck } from "lucide-react";
import { Reveal } from "@/components/reveal";

/**
 * Shared layout for legal / informational prose pages
 * (mentions légales, CGU/CGV, confidentialité, remboursements).
 * Renders a glassmorphic hero with title + subtitle + "Dernière mise à jour"
 * date, then a styled prose container. Tailwind typography is NOT installed,
 * so the structured children must style their own headings/paragraphs/lists.
 */
export function LegalPage({
  title,
  subtitle,
  updatedAt,
  children,
}: {
  title: string;
  subtitle: string;
  updatedAt: string;
  children: React.ReactNode;
}) {
  return (
    <div>
      {/* Hero */}
      <section className="relative overflow-hidden border-b border-border/60">
        <div className="card-grid-bg absolute inset-0 opacity-40" />
        <div className="relative mx-auto max-w-4xl px-4 py-16 lg:px-6 lg:py-20">
          <Reveal>
            <Link
              href="/"
              className="inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
              <ArrowLeft className="h-4 w-4" /> Retour à l&apos;accueil
            </Link>
            <span className="mt-6 inline-flex items-center gap-2 rounded-full border border-border bg-card/50 px-4 py-1.5 text-sm text-muted-foreground backdrop-blur">
              <ShieldCheck className="h-4 w-4 text-primary" /> Informations légales
            </span>
            <h1 className="mt-5 text-balance text-4xl font-bold tracking-tight sm:text-5xl">
              <span className="text-gradient">{title}</span>
            </h1>
            <p className="mt-4 max-w-2xl text-balance text-lg text-muted-foreground">{subtitle}</p>
            <p className="mt-6 inline-flex items-center gap-2 text-sm text-muted-foreground">
              <CalendarClock className="h-4 w-4 text-primary" /> Dernière mise à jour : {updatedAt}
            </p>
          </Reveal>
        </div>
      </section>

      {/* Prose */}
      <section className="mx-auto max-w-4xl px-4 py-12 lg:px-6 lg:py-16">
        <Reveal className="space-y-6 rounded-2xl border border-border bg-card/40 p-6 sm:p-10">
          {children}
        </Reveal>
      </section>
    </div>
  );
}

/** A prose heading inside a LegalPage (h2). */
export function LegalSection({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="space-y-3">
      <h2 className="mt-8 text-xl font-bold first:mt-0">{title}</h2>
      {children}
    </div>
  );
}

/** A prose paragraph inside a LegalPage. */
export function LegalParagraph({ children }: { children: React.ReactNode }) {
  return <p className="leading-relaxed text-muted-foreground">{children}</p>;
}

/** A bulleted list inside a LegalPage. */
export function LegalList({ items }: { items: React.ReactNode[] }) {
  return (
    <ul className="list-disc space-y-1 pl-5 text-muted-foreground">
      {items.map((item, i) => (
        <li key={i} className="leading-relaxed">
          {item}
        </li>
      ))}
    </ul>
  );
}
