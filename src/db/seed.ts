import "./env";
import bcrypt from "bcryptjs";
import { db, pglite } from "./index";
import * as s from "./schema";
import {
  addMonths,
  generateOrderNumber,
  generateSubscriptionNumber,
  generateSessionId,
  generateTransactionId,
  type BillingCycle,
} from "../lib/domain";

const img = (seed: string) => `https://picsum.photos/seed/${seed}/900/600`;
const hash = (pw: string) => bcrypt.hashSync(pw, 10);
const uuid = () => globalThis.crypto.randomUUID();

interface OfferDef {
  name: string;
  price: string;
  compareAt?: string;
  cycle: BillingCycle;
  setupFee?: string;
  isDefault?: boolean;
  stock?: number;
}
interface AccessDef {
  name: string;
  accessMethod: (typeof s.accessMethod.enumValues)[number];
  delivery: (typeof s.deliveryMethod.enumValues)[number];
  instructions: string;
  offers: OfferDef[];
}
interface ProductDef {
  name: string;
  slug: string;
  category: string;
  type: string;
  short: string;
  description: string;
  features: string[];
  featured?: boolean;
  access: AccessDef[];
}

const CATEGORIES = [
  { name: "Gaming", slug: "gaming", icon: "Gamepad2", color: "#8b5cf6", description: "Cartes cadeaux, abonnements et clés de jeu." },
  { name: "Streaming", slug: "streaming", icon: "Play", color: "#ef4444", description: "Comptes et abonnements vidéo & musique." },
  { name: "Productivité", slug: "productivite", icon: "Briefcase", color: "#0ea5e9", description: "Suites bureautiques et licences pro." },
  { name: "Créativité", slug: "creativite", icon: "Palette", color: "#ec4899", description: "Outils de design, photo et vidéo." },
  { name: "Sécurité & VPN", slug: "securite-vpn", icon: "ShieldCheck", color: "#22c55e", description: "VPN, antivirus et gestionnaires de mots de passe." },
  { name: "IA & Dev", slug: "ia-dev", icon: "Cpu", color: "#f59e0b", description: "Assistants IA et outils de développement." },
];

const TYPES = [
  { name: "Licence", slug: "licence", icon: "KeyRound" },
  { name: "Compte premium", slug: "compte-premium", icon: "UserRound" },
  { name: "Abonnement", slug: "abonnement", icon: "RefreshCw" },
  { name: "Clé d'activation", slug: "cle-activation", icon: "Key" },
  { name: "Accès API", slug: "acces-api", icon: "Webhook" },
];

const PRODUCTS: ProductDef[] = [
  {
    name: "Microsoft 365 Famille", slug: "microsoft-365-famille", category: "Productivité", type: "Licence", featured: true,
    short: "Word, Excel, PowerPoint et 1 To OneDrive pour 6 personnes.",
    description: "La suite Microsoft 365 complète : applications Office toujours à jour, 1 To de stockage OneDrive par personne, et sécurité avancée. Livraison de la clé d'activation instantanée.",
    features: ["Office complet (Word, Excel, PowerPoint…)", "1 To OneDrive / personne", "Jusqu'à 6 utilisateurs", "Multi-appareils", "Mises à jour incluses"],
    access: [{
      name: "Clé officielle", accessMethod: "license_key", delivery: "automatic",
      instructions: "Connectez-vous sur office.com, entrez la clé d'activation reçue, et installez les applications.",
      offers: [
        { name: "1 an", price: "59.99", compareAt: "99.00", cycle: "yearly", isDefault: true, stock: 40 },
        { name: "Achat unique (5 ans)", price: "169.99", compareAt: "249.00", cycle: "one_time", stock: 25 },
      ],
    }],
  },
  {
    name: "Adobe Creative Cloud", slug: "adobe-creative-cloud", category: "Créativité", type: "Abonnement", featured: true,
    short: "Toutes les apps Adobe : Photoshop, Illustrator, Premiere…",
    description: "Accès à plus de 20 applications créatives Adobe : Photoshop, Illustrator, Premiere Pro, After Effects, Lightroom et bien plus. Synchronisation cloud et polices incluses.",
    features: ["+20 applications créatives", "100 Go de stockage cloud", "Adobe Fonts inclus", "Mises à jour continues"],
    access: [{
      name: "Compte complet", accessMethod: "account_required", delivery: "semi_automatic",
      instructions: "Vous recevrez les identifiants de connexion Adobe. Connectez-vous sur creativecloud.adobe.com.",
      offers: [
        { name: "Mensuel", price: "29.99", compareAt: "59.99", cycle: "monthly", isDefault: true },
        { name: "Annuel", price: "299.99", compareAt: "719.00", cycle: "yearly" },
      ],
    }],
  },
  {
    name: "Netflix Premium 4K", slug: "netflix-premium-4k", category: "Streaming", type: "Compte premium", featured: true,
    short: "Ultra HD 4K sur 4 écrans simultanés.",
    description: "Profitez de Netflix en qualité Ultra HD 4K avec HDR, sur 4 écrans en simultané. Catalogue complet films et séries, sans publicité.",
    features: ["Qualité Ultra HD 4K + HDR", "4 écrans simultanés", "Téléchargements illimités", "Sans publicité"],
    access: [{
      name: "Accès premium", accessMethod: "account_required", delivery: "automatic",
      instructions: "Identifiants livrés instantanément. Ne modifiez pas le mot de passe du compte partagé.",
      offers: [
        { name: "1 mois", price: "9.99", compareAt: "19.99", cycle: "monthly", isDefault: true },
        { name: "Engagement 12 mois", price: "7.49", cycle: "commitment_12" },
      ],
    }],
  },
  {
    name: "Spotify Premium", slug: "spotify-premium", category: "Streaming", type: "Abonnement",
    short: "Musique sans pub, hors-ligne, qualité supérieure.",
    description: "Spotify Premium : écoute sans publicité, téléchargements hors-ligne, qualité audio supérieure et lecture dans l'ordre de votre choix.",
    features: ["Sans publicité", "Écoute hors-ligne", "Qualité audio élevée", "Skips illimités"],
    access: [{
      name: "Upgrade premium", accessMethod: "account_required", delivery: "manual_24h",
      instructions: "Communiquez l'email de votre compte Spotify ; l'upgrade Premium est appliqué sous 24h.",
      offers: [
        { name: "1 mois", price: "5.99", compareAt: "10.99", cycle: "monthly", isDefault: true },
        { name: "Annuel", price: "59.99", compareAt: "131.00", cycle: "yearly" },
      ],
    }],
  },
  {
    name: "NordVPN", slug: "nordvpn", category: "Sécurité & VPN", type: "Abonnement", featured: true,
    short: "VPN ultra-rapide, 6000+ serveurs, no-log.",
    description: "Protégez votre vie privée avec NordVPN : chiffrement de niveau militaire, plus de 6000 serveurs dans 60 pays, politique stricte de non-conservation des logs.",
    features: ["6000+ serveurs", "Politique no-log", "6 appareils simultanés", "Kill switch & Threat Protection"],
    access: [{
      name: "Abonnement", accessMethod: "license_key", delivery: "automatic",
      instructions: "Activez votre code sur nordvpn.com/redeem puis téléchargez l'application.",
      offers: [
        { name: "1 an", price: "59.88", compareAt: "143.00", cycle: "yearly", isDefault: true, stock: 60 },
        { name: "Engagement 24 mois", price: "3.29", compareAt: "11.99", cycle: "commitment_24", stock: 60 },
      ],
    }],
  },
  {
    name: "Windows 11 Pro", slug: "windows-11-pro", category: "Productivité", type: "Clé d'activation",
    short: "Clé d'activation à vie, 64 bits.",
    description: "Licence Windows 11 Pro authentique : activation à vie, BitLocker, Bureau à distance, Hyper-V. Compatible installation propre ou mise à niveau.",
    features: ["Activation à vie", "BitLocker & Hyper-V", "Bureau à distance", "Mises à jour Microsoft"],
    access: [{
      name: "Clé OEM", accessMethod: "license_key", delivery: "automatic",
      instructions: "Paramètres → Système → Activation → Modifier la clé de produit, puis saisissez la clé reçue.",
      offers: [{ name: "Licence à vie", price: "24.99", compareAt: "259.00", cycle: "one_time", isDefault: true, stock: 100 }],
    }],
  },
  {
    name: "ChatGPT Plus", slug: "chatgpt-plus", category: "IA & Dev", type: "Compte premium", featured: true,
    short: "GPT-4o, accès prioritaire, modèles avancés.",
    description: "Accédez à ChatGPT Plus : modèles les plus performants, réponses prioritaires même en charge, génération d'images et navigation avancée.",
    features: ["Modèles avancés (GPT-4 class)", "Accès prioritaire", "Génération d'images", "Navigation & outils"],
    access: [{
      name: "Compte Plus", accessMethod: "account_required", delivery: "semi_automatic",
      instructions: "Les identifiants du compte ChatGPT Plus vous sont livrés ; connectez-vous sur chat.openai.com.",
      offers: [{ name: "1 mois", price: "19.99", compareAt: "23.00", cycle: "monthly", isDefault: true }],
    }],
  },
  {
    name: "Steam Gift Card 50€", slug: "steam-gift-card-50", category: "Gaming", type: "Clé d'activation",
    short: "Crédit Steam de 50€, livraison instantanée.",
    description: "Rechargez votre portefeuille Steam avec 50€ de crédit. Idéal pour acheter jeux, DLC et objets sur la plateforme Steam.",
    features: ["50€ de crédit Steam", "Livraison instantanée", "Sans expiration", "Valable mondialement"],
    access: [{
      name: "Code Steam", accessMethod: "license_key", delivery: "automatic",
      instructions: "Steam → Jeux → Activer un produit / Échanger une carte cadeau Steam, puis saisissez le code.",
      offers: [{ name: "50€", price: "47.99", cycle: "one_time", isDefault: true, stock: 80 }],
    }],
  },
  {
    name: "Xbox Game Pass Ultimate", slug: "xbox-game-pass-ultimate", category: "Gaming", type: "Abonnement",
    short: "+100 jeux, EA Play, cloud gaming.",
    description: "Xbox Game Pass Ultimate : accès à plus de 100 jeux de qualité sur console, PC et cloud, EA Play inclus, et avantages en jeu.",
    features: ["100+ jeux", "Console, PC & Cloud", "EA Play inclus", "Nouveaux jeux day-one"],
    access: [{
      name: "Abonnement", accessMethod: "license_key", delivery: "automatic",
      instructions: "Échangez le code sur redeem.microsoft.com avec votre compte Microsoft.",
      offers: [
        { name: "1 mois", price: "12.99", compareAt: "17.99", cycle: "monthly", isDefault: true, stock: 50 },
        { name: "3 mois", price: "35.99", compareAt: "53.97", cycle: "quarterly", stock: 50 },
      ],
    }],
  },
  {
    name: "Canva Pro", slug: "canva-pro", category: "Créativité", type: "Abonnement",
    short: "Design illimité, 100M+ ressources premium.",
    description: "Canva Pro : accès à plus de 100 millions de photos, vidéos et éléments premium, suppression d'arrière-plan, redimensionnement magique et kit de marque.",
    features: ["100M+ ressources premium", "Background Remover", "Brand Kit", "Redimensionnement magique"],
    access: [{
      name: "Compte Pro", accessMethod: "account_required", delivery: "automatic",
      instructions: "Vous êtes ajouté à une équipe Canva Pro via votre email ; acceptez l'invitation reçue.",
      offers: [
        { name: "1 mois", price: "4.99", compareAt: "11.99", cycle: "monthly", isDefault: true },
        { name: "Annuel", price: "49.99", compareAt: "109.99", cycle: "yearly" },
      ],
    }],
  },
  {
    name: "JetBrains All Products Pack", slug: "jetbrains-all-products", category: "IA & Dev", type: "Licence",
    short: "Tous les IDE JetBrains : IntelliJ, PyCharm, WebStorm…",
    description: "Le pack ultime pour développeurs : accès à tous les IDE JetBrains (IntelliJ IDEA Ultimate, PyCharm Pro, WebStorm, GoLand, Rider…) avec une seule licence.",
    features: ["Tous les IDE JetBrains", "IntelliJ IDEA Ultimate", "Licence individuelle", "Mises à jour 1 an"],
    access: [{
      name: "Licence annuelle", accessMethod: "account_required", delivery: "manual_24h",
      instructions: "Activation via votre compte JetBrains ; la licence est attribuée sous 24h.",
      offers: [{ name: "1 an", price: "179.00", compareAt: "289.00", cycle: "yearly", isDefault: true }],
    }],
  },
  {
    name: "Disney+ Premium", slug: "disney-plus-premium", category: "Streaming", type: "Compte premium",
    short: "Disney, Marvel, Star Wars, Pixar en 4K.",
    description: "Disney+ Premium : tout l'univers Disney, Pixar, Marvel, Star Wars et National Geographic en 4K UHD, sans publicité, sur 4 écrans.",
    features: ["Catalogue Disney complet", "4K UHD sans pub", "4 écrans simultanés", "Téléchargements illimités"],
    access: [{
      name: "Accès premium", accessMethod: "account_required", delivery: "automatic",
      instructions: "Identifiants livrés instantanément ; connectez-vous sur disneyplus.com.",
      offers: [
        { name: "1 mois", price: "6.99", compareAt: "11.99", cycle: "monthly", isDefault: true },
        { name: "Annuel", price: "69.99", compareAt: "119.90", cycle: "yearly" },
      ],
    }],
  },
];

const REVIEW_TEXTS = [
  { rating: 5, title: "Parfait !", comment: "Livraison instantanée, tout fonctionne nickel. Je recommande à 100%." },
  { rating: 5, title: "Au top", comment: "Exactement comme décrit, activation sans problème. Service rapide." },
  { rating: 4, title: "Très bien", comment: "Bon produit, livraison un peu plus longue que prévu mais conforme." },
  { rating: 5, title: "Excellent rapport qualité-prix", comment: "Beaucoup moins cher qu'ailleurs et 100% légit." },
  { rating: 4, title: "Satisfait", comment: "Support réactif sur Discord, problème résolu en quelques minutes." },
  { rating: 5, title: "Rien à redire", comment: "Troisième achat, toujours impeccable." },
];

async function seed() {
  console.log("→ Seeding database…");

  /* System settings */
  await db.insert(s.systemSettings).values([
    { settingKey: "site_name", settingValue: "Pilote Shopping", isPublic: true },
    { settingKey: "maintenance_mode", settingValue: "false", settingType: "bool", isPublic: true },
    { settingKey: "promo_active", settingValue: "true", settingType: "bool", isPublic: true },
    { settingKey: "promo_text", settingValue: "-15% sur votre première commande avec le code BIENVENUE15", isPublic: true },
  ]);

  /* Payment adapters */
  await db.insert(s.paymentAdapters).values([
    { slug: "stripe", name: "Carte bancaire", isActive: true, sortOrder: 1, config: { mode: "demo", publishable_key: "pk_demo" } },
    { slug: "balance", name: "Solde du portefeuille", isActive: true, sortOrder: 2, config: {} },
    { slug: "paypal", name: "PayPal", isActive: true, sortOrder: 3, config: { mode: "demo" } },
    { slug: "bank_transfer", name: "Virement bancaire", isActive: true, sortOrder: 4, config: { iban: "FR76 3000 4000 0500 0000 0000 000", bic: "BNPAFRPP" } },
  ]);

  /* Users */
  const adminEmail = process.env.SEED_ADMIN_EMAIL || "admin@piloteshopping.com";
  const adminPw = process.env.SEED_ADMIN_PASSWORD || "Admin1234!";
  const userEmail = process.env.SEED_USER_EMAIL || "demo@piloteshopping.com";
  const userPw = process.env.SEED_USER_PASSWORD || "Demo1234!";

  const [admin] = await db.insert(s.users).values({
    uuid: uuid(), username: "admin", email: adminEmail, passwordHash: hash(adminPw),
    firstName: "Ewan", lastName: "Beraud", isActive: true, isVerified: true, profileCompleted: true,
    balance: "0", loyaltyPoints: 0,
  }).returning({ id: s.users.id });

  await db.insert(s.adminUsers).values({
    userId: admin.id, role: "super_admin", isActive: true,
    permissions: ["users", "products", "orders", "settings", "stats"],
  });

  const [demo] = await db.insert(s.users).values({
    uuid: uuid(), username: "demo", email: userEmail, passwordHash: hash(userPw),
    firstName: "Alex", lastName: "Martin", phone: "+33612345678", isActive: true, isVerified: true,
    profileCompleted: true, balance: "42.50", loyaltyPoints: 320,
  }).returning({ id: s.users.id });

  const reviewerNames = [
    ["Lucas", "Bernard"], ["Emma", "Petit"], ["Hugo", "Durand"], ["Léa", "Moreau"],
    ["Nathan", "Simon"], ["Chloé", "Laurent"], ["Tom", "Michel"], ["Manon", "Garcia"],
  ];
  const reviewerIds: number[] = [];
  for (let i = 0; i < reviewerNames.length; i++) {
    const [u] = await db.insert(s.users).values({
      uuid: uuid(), username: `client_${i + 1}`, email: `client${i + 1}@example.com`,
      passwordHash: hash("Client1234!"), firstName: reviewerNames[i][0], lastName: reviewerNames[i][1],
      isActive: true, isVerified: true, profileCompleted: true,
    }).returning({ id: s.users.id });
    reviewerIds.push(u.id);
  }

  /* Categories & types */
  const catMap = new Map<string, number>();
  for (let i = 0; i < CATEGORIES.length; i++) {
    const c = CATEGORIES[i];
    const [row] = await db.insert(s.productCategories).values({ ...c, sortOrder: i }).returning({ id: s.productCategories.id });
    catMap.set(c.name, row.id);
  }
  const typeMap = new Map<string, number>();
  for (let i = 0; i < TYPES.length; i++) {
    const t = TYPES[i];
    const [row] = await db.insert(s.productTypes).values({ ...t, sortOrder: i }).returning({ id: s.productTypes.id });
    typeMap.set(t.name, row.id);
  }

  /* Products → access types → offers → inventory → reviews */
  let firstDeliverableOfferId = 0;
  for (const p of PRODUCTS) {
    const [prod] = await db.insert(s.products).values({
      categoryId: catMap.get(p.category)!,
      productTypeId: typeMap.get(p.type)!,
      name: p.name, slug: p.slug, shortDescription: p.short, description: p.description,
      features: p.features, images: [img(p.slug), img(`${p.slug}-2`)],
      isActive: true, isFeatured: p.featured ?? false,
      saleCount: 20 + Math.floor((p.slug.length * 7) % 400),
      viewCount: 200 + ((p.slug.length * 53) % 5000),
    }).returning({ id: s.products.id });

    for (const a of p.access) {
      const [acc] = await db.insert(s.productAccessTypes).values({
        productId: prod.id, name: a.name, slug: a.name.toLowerCase().replace(/[^a-z0-9]+/g, "-"),
        accessMethod: a.accessMethod, defaultDeliveryMethod: a.delivery, userInstructions: a.instructions,
        hasGlobalStock: false, isActive: true,
      }).returning({ id: s.productAccessTypes.id });

      for (let oi = 0; oi < a.offers.length; oi++) {
        const o = a.offers[oi];
        const [offer] = await db.insert(s.productOffers).values({
          accessTypeId: acc.id, name: o.name, slug: o.name.toLowerCase().replace(/[^a-z0-9]+/g, "-"),
          price: o.price, compareAtPrice: o.compareAt, billingCycle: o.cycle,
          setupFee: o.setupFee ?? "0", isDefault: o.isDefault ?? oi === 0,
          manageStock: o.stock != null, stockQuantity: o.stock ?? 0,
          sortOrder: oi, isActive: true,
          saleCount: 5 + ((o.name.length * 13) % 200),
        }).returning({ id: s.productOffers.id });

        if (!firstDeliverableOfferId && a.accessMethod === "license_key") firstDeliverableOfferId = offer.id;

        // Inventory for license-key offers
        if (a.accessMethod === "license_key" && o.stock) {
          const rows = Array.from({ length: Math.min(o.stock, 12) }, () => ({
            offerId: offer.id, inventoryType: "license_key" as const,
            inventoryData: { license_key: `${p.slug.slice(0, 4).toUpperCase()}-${rand(4)}-${rand(4)}-${rand(4)}` },
            status: "available" as const,
          }));
          await db.insert(s.productInventory).values(rows);
        }
      }
    }

    // Reviews on featured products
    if (p.featured) {
      const n = 3 + ((p.slug.length) % 3);
      const ratingVals: number[] = [];
      for (let r = 0; r < n; r++) {
        const rv = REVIEW_TEXTS[(p.slug.length + r) % REVIEW_TEXTS.length];
        ratingVals.push(rv.rating);
        await db.insert(s.productReviews).values({
          productId: prod.id, userId: reviewerIds[(r * 3 + p.slug.length) % reviewerIds.length],
          rating: rv.rating, title: rv.title, comment: rv.comment, status: "approved",
          helpfulCount: (r * 7) % 25,
        });
      }
      const avg = ratingVals.reduce((a, b) => a + b, 0) / ratingVals.length;
      await db.update(s.products).set({ ratingAverage: avg.toFixed(2), ratingCount: n })
        .where(eq(s.products.id, prod.id));
    }
  }

  /* Coupons */
  await db.insert(s.coupons).values([
    { code: "BIENVENUE15", name: "Bienvenue -15%", description: "15% de réduction sur la première commande", discountType: "percentage", discountValue: "15", maxDiscountAmount: "30", usageLimitPerUser: 1, isActive: true },
    { code: "PROMO10", name: "Promo -10€", description: "10€ de réduction dès 50€ d'achat", discountType: "fixed", discountValue: "10", minimumAmount: "50", isActive: true },
    { code: "GAMING20", name: "Gaming -20%", description: "20% sur la catégorie Gaming", discountType: "percentage", discountValue: "20", maxDiscountAmount: "25", isActive: true },
  ]);

  /* Wishlist for demo user */
  const featured = await db.select({ id: s.products.id }).from(s.products).where(eq(s.products.isFeatured, true)).limit(3);
  for (const f of featured) {
    await db.insert(s.wishlists).values({ userId: demo.id, productId: f.id });
  }

  /* A sample completed order + subscription + notifications for the demo user */
  await seedDemoOrder(demo.id);

  console.log("✓ Seed complete.");
  console.log(`  Admin: ${adminEmail} / ${adminPw}`);
  console.log(`  User:  ${userEmail} / ${userPw}`);
  await pglite?.close();
  process.exit(0);
}

function rand(len: number): string {
  const chars = "ABCDEFGHIJKLMNPQRSTUVWXYZ23456789";
  const arr = new Uint8Array(len);
  globalThis.crypto.getRandomValues(arr);
  return Array.from(arr, (b) => chars[b % chars.length]).join("");
}

async function seedDemoOrder(userId: number) {
  // Pick Windows 11 (one_time, license_key) + Netflix (subscription)
  const win = await db.query.products.findFirst({
    where: eq(s.products.slug, "windows-11-pro"),
    with: { accessTypes: { with: { offers: true } } },
  });
  const netflix = await db.query.products.findFirst({
    where: eq(s.products.slug, "netflix-premium-4k"),
    with: { accessTypes: { with: { offers: true } } },
  });
  if (!win || !netflix) return;

  const winOffer = win.accessTypes[0].offers[0];
  const netOffer = netflix.accessTypes[0].offers.find((o) => o.isDefault) ?? netflix.accessTypes[0].offers[0];

  const subtotal = Number(winOffer.price) + Number(netOffer.price);
  const tax = +(subtotal * 0.2).toFixed(2);
  const total = +(subtotal + tax).toFixed(2);
  const orderNumber = generateOrderNumber();

  const [order] = await db.insert(s.orders).values({
    orderNumber, userId, status: "completed", paymentStatus: "paid", paymentMethod: "stripe",
    subtotal: subtotal.toFixed(2), taxAmount: tax.toFixed(2), totalAmount: total.toFixed(2),
    billingAddress: { first_name: "Alex", last_name: "Martin", address_line1: "12 rue des Lilas", city: "Lyon", postal_code: "69001", country: "FR" },
    paymentReference: generateTransactionId("pi"),
    deliveredAt: new Date(),
  }).returning({ id: s.orders.id });

  await db.insert(s.orderItems).values({
    orderId: order.id, productId: win.id, productOfferId: winOffer.id,
    productName: win.name, productSlug: win.slug, accessTypeName: win.accessTypes[0].name, offerName: winOffer.name,
    quantity: 1, unitPrice: winOffer.price, totalPrice: winOffer.price, billingCycle: "one_time",
    deliveryStatus: "delivered", deliveryMethod: "automatic", deliveredAt: new Date(),
    deliveryData: { type: "license_key", license_key: `WIN11-${rand(4)}-${rand(4)}-${rand(4)}`, notes: "Activez via Paramètres → Activation." },
  });

  const [netItem] = await db.insert(s.orderItems).values({
    orderId: order.id, productId: netflix.id, productOfferId: netOffer.id,
    productName: netflix.name, productSlug: netflix.slug, accessTypeName: netflix.accessTypes[0].name, offerName: netOffer.name,
    quantity: 1, unitPrice: netOffer.price, totalPrice: netOffer.price, billingCycle: netOffer.billingCycle,
    deliveryStatus: "delivered", deliveryMethod: "automatic", deliveredAt: new Date(),
    deliveryData: { type: "account", email: "shared-netflix@pilote.demo", password: "Netflix#Demo24", notes: "Ne changez pas le mot de passe." },
  }).returning({ id: s.orderItems.id });

  // Subscription for the Netflix item
  await db.insert(s.subscriptions).values({
    subscriptionNumber: generateSubscriptionNumber(), userId, orderItemId: netItem.id,
    productOfferId: netOffer.id, productName: netflix.name, status: "active",
    billingCycle: netOffer.billingCycle, amount: netOffer.price,
    startDate: new Date(), nextBillingDate: addMonths(new Date(), 1),
    lastBillingDate: new Date(), autoRenew: true,
  });

  // Payment transaction
  await db.insert(s.paymentTransactions).values({
    transactionId: generateTransactionId("TX"), orderId: order.id, userId,
    paymentMethod: "stripe", type: "payment", status: "completed",
    amount: total.toFixed(2), gatewayTransactionId: generateTransactionId("pi"), processedAt: new Date(),
  });

  // Balance transaction (welcome credit)
  await db.insert(s.balanceTransactions).values({
    userId, transactionType: "bonus", amount: "42.50",
    description: "Crédit de bienvenue", balanceBefore: "0", balanceAfter: "42.50",
  });

  // Notifications
  await db.insert(s.notifications).values([
    { userId, type: "order", title: "Commande livrée", message: `Votre commande ${orderNumber} a été livrée.`, link: `/dashboard/orders/${orderNumber}`, icon: "Package", color: "green", isRead: false },
    { userId, type: "subscription", title: "Abonnement activé", message: "Votre abonnement Netflix Premium 4K est actif.", link: "/dashboard/subscriptions", icon: "RefreshCw", color: "blue", isRead: false },
    { userId, type: "wallet", title: "Crédit reçu", message: "42,50 € ont été ajoutés à votre portefeuille.", link: "/dashboard/wallet", icon: "Wallet", color: "purple", isRead: true, readAt: new Date() },
  ]);
}

import { eq } from "drizzle-orm";

seed().catch((err) => {
  console.error("✗ Seed failed:", err);
  process.exit(1);
});
