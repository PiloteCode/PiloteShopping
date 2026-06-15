<div align="center">

# Pilote Shopping CMS

**Plateforme e-commerce PHP — abonnements, livraison numérique, paiement Stripe, panneau admin complet.**

[![Status](https://img.shields.io/badge/status-abandoned-red?style=for-the-badge)](#projet-abandonné)
[![License](https://img.shields.io/badge/license-MIT-blue?style=for-the-badge)](#licence)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A57.4-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Stripe](https://img.shields.io/badge/Stripe-Webhooks-635BFF?style=for-the-badge&logo=stripe&logoColor=white)](https://stripe.com/)

</div>

---

## Projet abandonné

> [!WARNING]
> **La boutique Pilote Shopping est définitivement fermée.**
>
> Ce dépôt est mis à disposition tel quel, à titre d'archive technique. Il n'est plus maintenu, plus déployé, plus surveillé. Aucune nouvelle fonctionnalité, aucun support, aucune correction de bug ne sera publiée.
>
> Le code est libéré sous licence **MIT** : reprenez-le, forkez-le, démontez-le, apprenez de ses erreurs. Une **revue de sécurité multi-agents** a été réalisée avant l'archivage — voir la section [Sécurité](#sécurité--audit-final) en bas de page. Les secrets historiques restent compromis et doivent être considérés comme tels.

---

## Vue d'ensemble

Pilote Shopping était un CMS e-commerce taillé pour la vente de **produits numériques** (clés de licence, comptes, accès API, abonnements) avec livraison automatique ou manuelle. Il a été construit en PHP « classique » — sans framework lourd — autour d'une architecture en deux mondes :

- **Front public** (`/`, `/products`, `/cart`, `/checkout`, `/orders`, `/dashboard`) servi par des pages PHP rendues côté serveur, avec Tailwind CSS via CDN et Alpine.js pour les interactions légères.
- **API REST `/api/v1/**`** consommée par le front en JSON, signée par token de session + protection CSRF, et par le back-office.

Le projet a tourné en production pendant plusieurs mois avant sa fermeture.

---

## Fonctionnalités clés

<table>
<tr>
<td valign="top" width="50%">

### Boutique
- Catalogue produits avec catégories, slugs SEO, images multiples
- **Offres** multiples par produit (différents tiers / périodes)
- Cycles de facturation flexibles : achat unique, mensuel, trimestriel, semestriel, annuel, engagements 3/6/12/24 mois
- Panier persistant (utilisateur connecté ou session anonyme)
- Coupons (% / fixe / livraison gratuite) avec limites usage globales et par utilisateur, dates de validité
- Wishlist
- Recommandations de produits

### Paiement
- Intégration **Stripe** complète (PaymentIntent, SetupIntent, webhooks signés HMAC, idempotence)
- Cartes sauvegardées par utilisateur (Stripe customer + payment_method)
- Wallet interne (solde utilisateur, transactions)
- Système de refund

### Livraison
- **Automatique** : clé/inventaire libéré instantanément après paiement
- **Semi-automatique** : sous quelques minutes
- **Manuelle** : 24h / 48h / 72h / 1 semaine
- Champs dynamiques par type de produit (email cible, plateforme, etc.)

</td>
<td valign="top" width="50%">

### Comptes & sécurité
- Inscription email + Google OAuth 2.0
- Vérification email obligatoire (token random_bytes, expiration)
- Reset password sécurisé
- **2FA TOTP** avec backup codes hashés
- Sessions multi-appareils avec révocation granulaire
- Remember-me avec rotation de token et IP pinning
- Cloudflare Turnstile sur login / register / forgot-password
- Rate-limiting (login, register, coupon, reset)
- Logs de sécurité horodatés (`security_logs`)

### Communications
- File d'attente d'emails (`email_queue` + `QueueProcessor`)
- Tracking ouvertures / clics avec tokens uniques
- Désinscription RFC 8058 (List-Unsubscribe-Post)
- DKIM-signed avec PHPMailer 6.10
- Webhook Discord pour annonces blog
- Notifications in-app temps réel

### Administration
- Rôles `super_admin` / `admin` / `moderator` / `support` avec permissions fines
- CRUD utilisateurs / produits / commandes
- Statistiques (revenus, conversions, notifications commerciales)
- Logs d'accès admin
- Export CSV des commandes
- Gestion d'inventaire (stock libéré à la livraison)

</td>
</tr>
</table>

---

## Stack technique

| Couche | Technologie |
|---|---|
| **Backend** | PHP 7.4+ (PDO, sessions natives, `random_bytes`, `password_hash` BCRYPT cost 12) |
| **Base de données** | MySQL 8 / MariaDB (UTF-8 MB4) |
| **Frontend** | Tailwind CSS (CDN), Alpine.js, Marked.js + DOMPurify pour le markdown, AOS pour les animations |
| **Paiement** | Stripe PHP SDK 17.x |
| **Email** | PHPMailer 6.10 + DKIM |
| **Auth tierce** | Google OAuth 2.0 |
| **Anti-bot** | Cloudflare Turnstile |
| **Serveur web** | Apache + `mod_rewrite` + `mod_headers` |

Aucun Composer, aucun npm. Les rares dépendances sont vendorées dans `plugins/` (PHPMailer, stripe-php).

---

## Structure du projet

```
PiloteShoppingCMS/
├── .htaccess                    Routage, HTTPS forcé, headers de sécurité, CSP, blocages
├── .env.example                 Variables d'environnement documentées
├── index.php                    Page d'accueil (désormais annonce de fermeture)
├── 404.php, legal.php, terms.php, privacy.php, refunds.php, apropos.php
│
├── auth/                        Authentification utilisateur
│   ├── login.php, register.php
│   ├── verify-email.php, forgot-password.php, reset-password.php
│   ├── complete-profile.php
│   └── google/                  OAuth 2.0
│
├── dashboard/                   Espace client (notifications, abonnements, cartes)
├── products/                    Catalogue, page produit, catégorie
├── cart/, checkout/, orders/    Tunnel d'achat & gestion commandes
├── wallet/                      Solde + transferts
├── support/                     Tickets / aide
│
├── admin/                       Back-office
│   ├── index.php                Tableau de bord
│   ├── includes/
│   │   ├── AdminAuthMiddleware.php   Auth + permissions + CSRF auto
│   │   └── menu.php                  Header admin + injection CSRF JS
│   ├── users/                   CRUD utilisateurs
│   ├── products/                CRUD produits + offres
│   └── orders/                  Commandes, custom fields, export, stats
│
├── api/v1/                      API REST (point d'entrée pour le JS front)
│   ├── config.php               Configuration centralisée (lit .env via EnvLoader)
│   ├── auth/                    register, login, logout, OAuth, reset, 2FA, RememberMeManager
│   ├── cart/, wishlist/         Gestion panier & wishlist
│   ├── checkout/                Création de commande server-side
│   ├── orders/                  status, create, access-product (update-payment-status désactivé)
│   ├── payments/stripe/         webhook signé, saved-cards, config publique
│   ├── settings/                profile, password, avatar, sessions, 2fa
│   ├── products/                dynamic-fields, recommandations
│   ├── mail/                    QueueProcessor, MailerManager, unsubscribe, tracking
│   ├── notifications/           list, actions
│   └── subscriptions/           manage
│
├── includes/                    Code partagé
│   ├── EnvLoader.php            Chargement .env sans dépendance
│   ├── CsrfProtection.php       Middleware CSRF (token + validation)
│   ├── AuthMiddleware.php       Sessions + remember-me + intended_url safe
│   ├── NotificationManager.php
│   ├── menu.php, footer.php, import.php, tailwind.php
│
├── cron/
│   └── subscriptions-pay.php    Prélèvements automatiques (CLI ou X-Cron-Secret)
│
├── plugins/                     Dépendances vendorées
│   ├── PHPMailer/
│   └── stripe-php/
│
├── uploads/                     Uploads utilisateurs (PHP désactivé via .htaccess)
│   └── avatars/
│
├── schema/                      SQL de schéma (hors webroot)
│   └── user.sql
│
└── scripts/                     Outils de maintenance (CLI only)
    ├── generate-secrets.php
    ├── strip-comments.php
    ├── strip-inline-comments.php
    ├── strip-string-comments.php
    ├── strip-script-comments.php
    ├── cleanup-debug.php
    ├── escape-like.php
    ├── wire-csrf.php
    └── wire-csrf-v2.php
```

---

## Installation locale (à titre éducatif)

```bash
# 1. Cloner
git clone https://github.com/PiloteCode/PiloteShoppingCMS.git
cd PiloteShoppingCMS

# 2. Préparer .env (NE JAMAIS commiter)
cp .env.example .env
php scripts/generate-secrets.php >> .env   # ajoute JWT, ENCRYPTION_KEY, HASH_SALT, CRON_SECRET
chmod 600 .env

# 3. Renseigner manuellement dans .env :
#    - DB_* (utilisateur dédié, droits minimaux)
#    - SMTP_*
#    - GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET
#    - CLOUDFLARE_TURNSTILE_SITE_KEY / CLOUDFLARE_TURNSTILE_SECRET_KEY
#    - Stripe secret_key et webhook_secret : stockés en BDD dans payment_adapters.config

# 4. Importer le schéma
mysql -u <user> -p <db> < schema/user.sql

# 5. Pointer Apache sur la racine du repo (DocumentRoot).
#    mod_rewrite, mod_headers, AllowOverride All requis.

# 6. Crontab pour les abonnements
*/15 * * * * php /var/www/PiloteShoppingCMS/cron/subscriptions-pay.php >> /var/log/pilote-cron.log 2>&1

# 7. Stocker la clé privée DKIM hors webroot
#    /etc/dkim/piloteshopping.private.key  perms 0400 owner www-data
```

Aucune installation officielle n'est plus supportée. Si vous redéployez, **vous opérez le projet à vos risques**.

---

## Sécurité — audit final

Avant archivage, le code a subi une **revue de sécurité multi-agents** en plusieurs vagues :

| Vague | Agents | Findings traités |
|---|---|---|
| Audit initial | 5 agents (SQL, auth, XSS/CSRF, config, business logic) | 12 critiques + 14 élevées + 18 moyennes |
| Corrections wave 1 | — | Externalisation secrets, htaccess durci, désactivation `update-payment-status`, refonte de `status.php`, CSRF middleware, sanitization Markdown, idempotence webhook Stripe, etc. |
| Re-audit | 5 agents | 11 nouvelles failles élevées détectées |
| Corrections wave 2 | — | CSRF wired sur 23 endpoints, AdminAuthMiddleware auto-CSRF, RememberMeManager rotation/IP pinning, Turnstile frontend, sessions.php réécrit, XSS commercial notifications, escape LIKE, etc. |
| Vérification finale | 1 agent (20 contrôles) | **20/20 OUI** |

### Mesures défensives en place

- PDO + prepared statements partout (`ATTR_EMULATE_PREPARES=false`)
- `password_hash(PASSWORD_BCRYPT, cost=12)` + sel global `HASH_SALT`
- Tokens sécurisés via `random_bytes` (jamais `mt_rand` pour de la crypto)
- `session.use_strict_mode=1`, `HttpOnly`, `Secure`, `SameSite=Lax`
- Middleware CSRF par session + `hash_equals` + auto-injection JS / form
- HSTS preload, X-Frame-Options DENY, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- **Content-Security-Policy** restrictive (whitelist Stripe / Turnstile / Tailwind / CDN connus)
- HTTPS forcé en .htaccess avec gestion `X-Forwarded-Proto`
- Upload : `finfo` magic-bytes + bornes dimensions + nom random + `php_flag engine off` sur `uploads/`
- Webhook Stripe : signature HMAC vérifiée + idempotence sur `event_id`
- Cron : CLI-only ou `X-Cron-Secret` timing-safe (`hash_equals`)
- Rate-limiting (login, register, coupon, password reset)
- 2FA TOTP avec tolérance 2 fenêtres, backup codes hashés

## Pourquoi c'est fermé

La boutique a fait ce qu'elle avait à faire. Le marché a évolué, les marges se sont effondrées, le temps d'opération est devenu disproportionné. Plutôt que de laisser la prod pourrir à petit feu avec des secrets compromis et des dépendances qui vieillissent, on a préféré :

1. Annoncer la fermeture proprement à la communauté.
2. Auditer le code sérieusement.
3. Le libérer sous MIT.

Le code n'est ni parfait ni canonique. Il est traversé d'idées qui ont fonctionné, d'autres qui ont vieilli, de patterns qui auraient gagné à être refactorés. Tel quel, il documente trois années de décisions techniques sur un e-commerce indé. Si ça inspire, tant mieux. Si ça sert de contre-exemple, c'est bien aussi.

---

## Licence

```
MIT License

Copyright (c) 2024 PiloteCode / Pilote Shopping

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

<div align="center">

*Merci à toute notre communauté pour ces années de confiance.*

*— L'équipe PiloteCode*

</div>
