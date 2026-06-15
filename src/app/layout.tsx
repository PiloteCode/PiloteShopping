import type { Metadata, Viewport } from "next";
import { Inter, JetBrains_Mono } from "next/font/google";
import { Providers } from "@/components/providers";
import "./globals.css";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-inter",
  display: "swap",
});

const jetbrains = JetBrains_Mono({
  subsets: ["latin"],
  variable: "--font-mono-jb",
  display: "swap",
});

export const metadata: Metadata = {
  metadataBase: new URL(process.env.SITE_URL ?? "http://localhost:3000"),
  title: {
    default: "Pilote Shopping — Produits numériques premium",
    template: "%s · Pilote Shopping",
  },
  description:
    "Marketplace de produits numériques : licences, comptes, accès API et abonnements livrés instantanément. Paiement sécurisé, livraison automatique.",
  keywords: ["produits numériques", "licences", "abonnements", "clés", "marketplace"],
  authors: [{ name: "PiloteCode" }],
  openGraph: {
    type: "website",
    siteName: "Pilote Shopping",
    title: "Pilote Shopping — Produits numériques premium",
    description:
      "Marketplace de produits numériques livrés instantanément. Paiement sécurisé.",
  },
};

export const viewport: Viewport = {
  themeColor: "#0a0e14",
  width: "device-width",
  initialScale: 1,
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html
      lang="fr"
      className={`dark ${inter.variable} ${jetbrains.variable}`}
      suppressHydrationWarning
    >
      <body className="min-h-screen bg-background font-sans text-foreground antialiased">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
