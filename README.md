<div align="center">

# Pilote Shopping v2

**Marketplace de produits numériques — refonte complète en stack moderne.**

Next.js 15 · TypeScript · PostgreSQL (Drizzle) · Tailwind v4 · shadcn/ui · Framer Motion · OpenAPI

[![Next.js](https://img.shields.io/badge/Next.js-15-black?style=for-the-badge&logo=nextdotjs)](https://nextjs.org/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?style=for-the-badge&logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Drizzle-4169E1?style=for-the-badge&logo=postgresql&logoColor=white)](https://orm.drizzle.team/)
[![Tailwind](https://img.shields.io/badge/Tailwind-v4-38BDF8?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)

</div>

---

## Aperçu

Pilote Shopping est une marketplace de **produits numériques** (clés de licence, comptes premium, accès API, abonnements) avec **livraison automatique**. Cette version 2 est une **reconstruction intégrale** de l'ancien CMS PHP (archivé dans [`old/`](./old)) sur une stack TypeScript moderne, avec une **API REST documentée (OpenAPI/Scalar)**, une base **PostgreSQL** typée de bout en bout, et une UI **dark / glassmorphique** animée.

> L'ancien site PHP reste disponible tel quel dans le dossier `old/` à titre d'archive.

## Stack technique

| Couche | Technologie |
|---|---|
| **Framework** | Next.js 15 (App Router, Server Components, Route Handlers) + React 19 |
| **Langage** | TypeScript (strict) |
| **Base de données** | PostgreSQL via **Drizzle ORM**. En local : **PGlite** (Postgres compilé en WASM, zéro installation). En prod : n'importe quel Postgres via `DATABASE_URL`. |
| **UI** | Tailwind CSS v4 + **shadcn/ui** (composants hand-rolled) + **Framer Motion** |
| **Validation** | Zod (entrées API typées et validées) |
| **Auth** | Sessions par cookie httpOnly + table `user_sessions`, bcrypt, protection **CSRF** double-submit, **2FA TOTP** (otplib) |
| **API Docs** | **OpenAPI 3.1** servi en JSON + référence interactive **Scalar** sur `/api-docs` |
| **Paiement / Email** | Mode démo intégré (simulation Stripe + file d'emails), bascule possible vers de vraies clés |

## Démarrage rapide

```bash
# 1. Installer les dépendances
pnpm install

# 2. Préparer la base (PGlite, aucune installation Postgres requise)
pnpm db:setup        # = db:migrate + db:seed

# 3. Lancer
pnpm dev             # http://localhost:3000
```

Aucune configuration n'est nécessaire : `.env` fonctionne en l'état avec PGlite et le mode démo.
Pour un Postgres réel, renseignez `DATABASE_URL` dans `.env` puis relancez `pnpm db:setup`.

### Comptes de démonstration

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | `admin@piloteshopping.com` | `Admin1234!` |
| Client | `demo@piloteshopping.com` | `Demo1234!` |

## Scripts

| Commande | Description |
|---|---|
| `pnpm dev` | Serveur de développement |
| `pnpm build` / `pnpm start` | Build & serveur de production |
| `pnpm typecheck` | Vérification TypeScript (`tsc --noEmit`) |
| `pnpm db:generate` | Génère les migrations Drizzle depuis le schéma |
| `pnpm db:migrate` | Applique les migrations (PGlite ou Postgres) |
| `pnpm db:seed` | Remplit la base de données de démonstration |
| `pnpm db:reset` | Supprime la base PGlite locale |
| `pnpm db:setup` | `db:migrate` + `db:seed` |
| `pnpm test:api` | **Tests d'intégration** de l'API (25 contrôles) |

## Fonctionnalités

**Boutique** — catalogue filtrable (catégorie, type, abonnement/achat unique, tri), recherche, page produit riche (offres multiples, cycles de facturation, avis, recommandations), panier persistant (invité ou connecté), codes promo.

**Tunnel d'achat** — checkout multi-méthodes (carte, portefeuille, PayPal, virement), création de commande transactionnelle, **livraison automatique** (attribution d'inventaire / clés), création d'abonnements récurrents.

**Espace client** — tableau de bord, commandes & récupération des accès livrés, abonnements (pause/reprise/annulation), **portefeuille** (solde, rechargement, historique), liste de souhaits, notifications, paramètres (profil, mot de passe, **2FA**, sessions multi-appareils).

**Back-office admin** — statistiques (revenus, conversions), CRUD produits, gestion des commandes (statuts), gestion des utilisateurs.

**API REST** — surface complète sous `/api/v1/**`, enveloppe de réponse unifiée, validation Zod, CSRF, rate-limiting, documentée en **OpenAPI** → [`/api-docs`](http://localhost:3000/api-docs).

## Architecture

```
src/
├── app/
│   ├── (main)/              Pages avec navbar/footer (boutique, compte, admin, légal)
│   ├── (auth)/              Connexion, inscription, mot de passe oublié
│   ├── api/v1/**            API REST (route handlers)
│   ├── api/openapi.json     Spécification OpenAPI 3.1
│   └── api-docs             Référence interactive Scalar
├── components/
│   ├── ui/                  Primitives shadcn/ui
│   ├── layout/              Navbar, footer
│   ├── dashboard/ admin/    Composants spécifiques
│   └── …                    ProductCard, PurchasePanel, etc.
├── db/                      Schéma Drizzle (28 tables), client dual PGlite/Postgres, migrate, seed
├── lib/                     auth, csrf/api, domaine (cycles de facturation…), schémas Zod, OpenAPI
└── server/                  Services partagés (products, cart, orders, account, admin)
```

### Qualité & sécurité

- **Type-safe de bout en bout** : du schéma Drizzle aux composants React, `pnpm typecheck` passe sans erreur.
- Mots de passe **bcrypt**, sessions serveur révocables, **CSRF** sur toutes les mutations, **2FA TOTP**.
- Validation **Zod** systématique des entrées, enveloppe de réponse/erreur uniforme, rate-limiting.
- Création de commande **transactionnelle** (réservation de stock + abonnements dans une seule transaction SQL), webhook de paiement pour la confirmation asynchrone.

## Tests

```bash
pnpm db:setup && pnpm dev          # dans un terminal
pnpm test:api                      # dans un autre — 25 contrôles d'intégration
node scripts/smoke-pages.mjs       # rendu de toutes les pages (public + auth + admin)
```

Le flux complet est couvert : catalogue → panier → coupon → checkout → commande → **livraison de clé** → portefeuille → abonnement, plus l'authentification, la 2FA et les gardes admin/CSRF.

## Licence

MIT — voir [`LICENSE`](./LICENSE).

<div align="center">
<sub>Reconstruit avec ❤️ — l'ancien CMS PHP est conservé dans <a href="./old"><code>old/</code></a>.</sub>
</div>
