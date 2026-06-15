/**
 * Hand-authored OpenAPI 3.1 description of the Pilote Shopping REST API.
 * Served at /api/openapi.json and rendered by Scalar at /api-docs.
 */

const SITE = process.env.SITE_URL || "http://localhost:3000";

const envelope = (dataSchema: object) => ({
  type: "object",
  properties: {
    success: { type: "boolean", example: true },
    message: { type: "string" },
    data: dataSchema,
  },
  required: ["success", "data"],
});

const errorResponse = {
  description: "Erreur",
  content: {
    "application/json": {
      schema: {
        type: "object",
        properties: {
          success: { type: "boolean", example: false },
          error: { type: "string", example: "Message d'erreur" },
          code: { type: "string", example: "VALIDATION_ERROR" },
        },
      },
    },
  },
};

const jsonBody = (schema: object, example?: object) => ({
  required: true,
  content: { "application/json": { schema, ...(example ? { example } : {}) } },
});

const okJson = (description: string, dataSchema: object, example?: object) => ({
  description,
  content: { "application/json": { schema: envelope(dataSchema), ...(example ? { example: { success: true, data: example } } : {}) } },
});

const ProductCard = {
  type: "object",
  properties: {
    id: { type: "integer" },
    name: { type: "string" },
    slug: { type: "string" },
    shortDescription: { type: "string" },
    image: { type: "string", nullable: true },
    fromPrice: { type: "number" },
    compareAtPrice: { type: "number", nullable: true },
    ratingAverage: { type: "number" },
    ratingCount: { type: "integer" },
    defaultOfferId: { type: "integer", nullable: true },
    hasSubscription: { type: "boolean" },
    available: { type: "boolean" },
    category: { type: "object", nullable: true },
    type: { type: "object", nullable: true },
  },
};

export function buildOpenApiDocument() {
  return {
    openapi: "3.1.0",
    info: {
      title: "Pilote Shopping API",
      version: "2.0.0",
      description:
        "API REST de la marketplace de produits numériques Pilote Shopping. " +
        "Authentification par cookie de session (`pilote_session`). " +
        "Les requêtes mutantes exigent l'en-tête `X-CSRF-Token` (double-submit, valeur du cookie `pilote_csrf`).",
      contact: { name: "PiloteCode", url: SITE },
      license: { name: "MIT" },
    },
    servers: [{ url: `${SITE}/api/v1`, description: "API v1" }],
    tags: [
      { name: "Auth", description: "Inscription, connexion, session" },
      { name: "Catalogue", description: "Produits, catégories, recommandations" },
      { name: "Panier", description: "Panier & codes promo" },
      { name: "Liste de souhaits" },
      { name: "Commandes", description: "Checkout, commandes, livraison" },
      { name: "Abonnements" },
      { name: "Portefeuille" },
      { name: "Notifications" },
      { name: "Paramètres", description: "Profil, mot de passe, sessions, 2FA" },
      { name: "Paiements" },
      { name: "Admin", description: "Back-office (rôle admin requis)" },
    ],
    components: {
      securitySchemes: {
        cookieAuth: { type: "apiKey", in: "cookie", name: "pilote_session" },
        csrfToken: { type: "apiKey", in: "header", name: "X-CSRF-Token" },
      },
      schemas: { ProductCard },
    },
    paths: {
      "/auth/register": {
        post: {
          tags: ["Auth"], summary: "Créer un compte", security: [{ csrfToken: [] }],
          requestBody: jsonBody(
            { type: "object", required: ["email", "password", "confirmPassword", "firstName", "lastName", "username"],
              properties: { email: { type: "string", format: "email" }, password: { type: "string" }, confirmPassword: { type: "string" }, firstName: { type: "string" }, lastName: { type: "string" }, username: { type: "string" } } },
            { email: "jane@example.com", password: "Secret123!", confirmPassword: "Secret123!", firstName: "Jane", lastName: "Doe", username: "janedoe" },
          ),
          responses: { "201": okJson("Compte créé", { type: "object" }), "400": errorResponse },
        },
      },
      "/auth/login": {
        post: {
          tags: ["Auth"], summary: "Se connecter", security: [{ csrfToken: [] }],
          requestBody: jsonBody({ type: "object", required: ["email", "password"], properties: { email: { type: "string" }, password: { type: "string" }, rememberMe: { type: "boolean" } } },
            { email: "demo@piloteshopping.com", password: "Demo1234!" }),
          responses: { "200": okJson("Connecté", { type: "object" }), "400": errorResponse },
        },
      },
      "/auth/logout": {
        post: { tags: ["Auth"], summary: "Se déconnecter", security: [{ cookieAuth: [], csrfToken: [] }], responses: { "200": okJson("Déconnecté", { type: "object" }) } },
      },
      "/auth/me": {
        get: { tags: ["Auth"], summary: "Utilisateur courant", responses: { "200": okJson("Session courante (user null si anonyme)", { type: "object", properties: { user: { type: "object", nullable: true } } }) } },
      },
      "/products": {
        get: {
          tags: ["Catalogue"], summary: "Lister les produits",
          parameters: [
            { name: "category", in: "query", schema: { type: "string" } },
            { name: "type", in: "query", schema: { type: "string" } },
            { name: "q", in: "query", schema: { type: "string" } },
            { name: "sort", in: "query", schema: { type: "string", enum: ["popular", "newest", "price_asc", "price_desc", "rating"] } },
            { name: "billing", in: "query", schema: { type: "string", enum: ["all", "one_time", "subscription"] } },
            { name: "page", in: "query", schema: { type: "integer" } },
            { name: "perPage", in: "query", schema: { type: "integer" } },
          ],
          responses: { "200": okJson("Page de produits", { type: "object", properties: { items: { type: "array", items: { $ref: "#/components/schemas/ProductCard" } }, total: { type: "integer" }, page: { type: "integer" }, pages: { type: "integer" } } }) },
        },
      },
      "/products/{slug}": {
        get: { tags: ["Catalogue"], summary: "Détail d'un produit", parameters: [{ name: "slug", in: "path", required: true, schema: { type: "string" } }], responses: { "200": okJson("Produit", { type: "object" }), "404": errorResponse } },
      },
      "/products/recommended": {
        get: { tags: ["Catalogue"], summary: "Recommandations", parameters: [{ name: "source", in: "query", schema: { type: "string" } }, { name: "limit", in: "query", schema: { type: "integer" } }], responses: { "200": okJson("Produits recommandés", { type: "object" }) } },
      },
      "/categories": {
        get: { tags: ["Catalogue"], summary: "Catégories & types", responses: { "200": okJson("Listes", { type: "object" }) } },
      },
      "/cart": {
        get: { tags: ["Panier"], summary: "Obtenir le panier", responses: { "200": okJson("Panier", { type: "object", properties: { items: { type: "array", items: { type: "object" } }, summary: { type: "object" }, coupon: { type: "object", nullable: true } } }) } },
        post: { tags: ["Panier"], summary: "Ajouter au panier", security: [{ csrfToken: [] }], requestBody: jsonBody({ type: "object", required: ["productId", "offerId"], properties: { productId: { type: "integer" }, offerId: { type: "integer" }, quantity: { type: "integer" } } }), responses: { "200": okJson("Ajouté", { type: "object" }), "400": errorResponse } },
      },
      "/cart/{itemId}": {
        patch: { tags: ["Panier"], summary: "Modifier la quantité", security: [{ csrfToken: [] }], parameters: [{ name: "itemId", in: "path", required: true, schema: { type: "integer" } }], requestBody: jsonBody({ type: "object", required: ["quantity"], properties: { quantity: { type: "integer" } } }), responses: { "200": okJson("Mis à jour", { type: "object" }) } },
        delete: { tags: ["Panier"], summary: "Retirer un article", security: [{ csrfToken: [] }], parameters: [{ name: "itemId", in: "path", required: true, schema: { type: "integer" } }], responses: { "200": okJson("Retiré", { type: "object" }) } },
      },
      "/cart/coupon": {
        get: { tags: ["Panier"], summary: "Coupon appliqué", responses: { "200": okJson("État", { type: "object" }) } },
        post: { tags: ["Panier"], summary: "Appliquer un coupon", security: [{ csrfToken: [] }], requestBody: jsonBody({ type: "object", required: ["code"], properties: { code: { type: "string" } } }, { code: "BIENVENUE15" }), responses: { "200": okJson("Appliqué", { type: "object" }), "400": errorResponse } },
        delete: { tags: ["Panier"], summary: "Retirer le coupon", security: [{ csrfToken: [] }], responses: { "200": okJson("Retiré", { type: "object" }) } },
      },
      "/wishlist": {
        get: { tags: ["Liste de souhaits"], summary: "Ma liste", security: [{ cookieAuth: [] }], responses: { "200": okJson("Liste", { type: "object" }), "401": errorResponse } },
        post: { tags: ["Liste de souhaits"], summary: "Ajouter", security: [{ cookieAuth: [], csrfToken: [] }], requestBody: jsonBody({ type: "object", required: ["productId"], properties: { productId: { type: "integer" } } }), responses: { "200": okJson("Ajouté", { type: "object" }) } },
      },
      "/wishlist/{productId}": {
        delete: { tags: ["Liste de souhaits"], summary: "Retirer", security: [{ cookieAuth: [], csrfToken: [] }], parameters: [{ name: "productId", in: "path", required: true, schema: { type: "integer" } }], responses: { "200": okJson("Retiré", { type: "object" }) } },
      },
      "/checkout": {
        post: { tags: ["Commandes"], summary: "Créer une commande", security: [{ csrfToken: [] }], requestBody: jsonBody({ type: "object", required: ["paymentMethod", "billingAddress"], properties: { paymentMethod: { type: "string", enum: ["stripe", "paypal", "bank_transfer", "balance"] }, billingAddress: { type: "object" }, notes: { type: "string" } } }), responses: { "200": okJson("Commande", { type: "object", properties: { orderNumber: { type: "string" }, total: { type: "number" }, paymentStatus: { type: "string" } } }), "400": errorResponse } },
      },
      "/orders": {
        get: { tags: ["Commandes"], summary: "Mes commandes", security: [{ cookieAuth: [] }], responses: { "200": okJson("Commandes", { type: "object" }), "401": errorResponse } },
      },
      "/orders/{number}": {
        get: { tags: ["Commandes"], summary: "Détail commande", security: [{ cookieAuth: [] }], parameters: [{ name: "number", in: "path", required: true, schema: { type: "string" } }], responses: { "200": okJson("Commande", { type: "object" }), "404": errorResponse } },
      },
      "/orders/access": {
        post: { tags: ["Commandes"], summary: "Récupérer le produit livré", security: [{ cookieAuth: [], csrfToken: [] }], requestBody: jsonBody({ type: "object", required: ["orderItemId"], properties: { orderItemId: { type: "integer" } } }), responses: { "200": okJson("Données de livraison", { type: "object" }), "400": errorResponse } },
      },
      "/subscriptions": {
        get: { tags: ["Abonnements"], summary: "Mes abonnements", security: [{ cookieAuth: [] }], responses: { "200": okJson("Abonnements", { type: "object" }) } },
      },
      "/subscriptions/{id}": {
        post: { tags: ["Abonnements"], summary: "Gérer (pause/resume/cancel)", security: [{ cookieAuth: [], csrfToken: [] }], parameters: [{ name: "id", in: "path", required: true, schema: { type: "integer" } }], requestBody: jsonBody({ type: "object", required: ["action"], properties: { action: { type: "string", enum: ["pause", "resume", "cancel"] } } }), responses: { "200": okJson("Mis à jour", { type: "object" }) } },
      },
      "/wallet": {
        get: { tags: ["Portefeuille"], summary: "Solde & transactions", security: [{ cookieAuth: [] }], responses: { "200": okJson("Portefeuille", { type: "object" }) } },
        post: { tags: ["Portefeuille"], summary: "Recharger", security: [{ cookieAuth: [], csrfToken: [] }], requestBody: jsonBody({ type: "object", required: ["amount"], properties: { amount: { type: "number" } } }), responses: { "200": okJson("Rechargé", { type: "object" }) } },
      },
      "/notifications": {
        get: { tags: ["Notifications"], summary: "Lister", security: [{ cookieAuth: [] }], responses: { "200": okJson("Notifications", { type: "object" }) } },
        post: { tags: ["Notifications"], summary: "Action (lire/supprimer)", security: [{ cookieAuth: [], csrfToken: [] }], requestBody: jsonBody({ type: "object", required: ["action"], properties: { action: { type: "string", enum: ["mark_read", "mark_all_read", "delete"] }, id: { type: "integer" } } }), responses: { "200": okJson("OK", { type: "object" }) } },
      },
      "/settings/profile": {
        get: { tags: ["Paramètres"], summary: "Mon profil", security: [{ cookieAuth: [] }], responses: { "200": okJson("Profil", { type: "object" }) } },
        put: { tags: ["Paramètres"], summary: "Mettre à jour le profil", security: [{ cookieAuth: [], csrfToken: [] }], requestBody: jsonBody({ type: "object", properties: { firstName: { type: "string" }, lastName: { type: "string" }, phone: { type: "string" } } }), responses: { "200": okJson("Mis à jour", { type: "object" }) } },
      },
      "/settings/password": {
        put: { tags: ["Paramètres"], summary: "Changer le mot de passe", security: [{ cookieAuth: [], csrfToken: [] }], requestBody: jsonBody({ type: "object", required: ["currentPassword", "newPassword"], properties: { currentPassword: { type: "string" }, newPassword: { type: "string" } } }), responses: { "200": okJson("Changé", { type: "object" }), "400": errorResponse } },
      },
      "/settings/sessions": {
        get: { tags: ["Paramètres"], summary: "Sessions actives", security: [{ cookieAuth: [] }], responses: { "200": okJson("Sessions", { type: "object" }) } },
        delete: { tags: ["Paramètres"], summary: "Révoquer une session", security: [{ cookieAuth: [], csrfToken: [] }], requestBody: jsonBody({ type: "object", required: ["sessionId"], properties: { sessionId: { type: "string" } } }), responses: { "200": okJson("Révoquée", { type: "object" }) } },
      },
      "/settings/2fa/setup": { post: { tags: ["Paramètres"], summary: "Initialiser la 2FA", security: [{ cookieAuth: [], csrfToken: [] }], responses: { "200": okJson("Secret + QR + backup codes", { type: "object" }) } } },
      "/settings/2fa/verify": { post: { tags: ["Paramètres"], summary: "Activer la 2FA", security: [{ cookieAuth: [], csrfToken: [] }], requestBody: jsonBody({ type: "object", required: ["code"], properties: { code: { type: "string" } } }), responses: { "200": okJson("Activée", { type: "object" }) } } },
      "/settings/2fa/disable": { post: { tags: ["Paramètres"], summary: "Désactiver la 2FA", security: [{ cookieAuth: [], csrfToken: [] }], requestBody: jsonBody({ type: "object", properties: { password: { type: "string" }, code: { type: "string" } } }), responses: { "200": okJson("Désactivée", { type: "object" }) } } },
      "/payments/config": { get: { tags: ["Paiements"], summary: "Config paiement publique", responses: { "200": okJson("Config", { type: "object" }) } } },
      "/payments/webhook": { post: { tags: ["Paiements"], summary: "Webhook gateway", responses: { "200": { description: "Reçu", content: { "application/json": { schema: { type: "object", properties: { status: { type: "string" } } } } } } } } },
      "/admin/stats": { get: { tags: ["Admin"], summary: "Statistiques", security: [{ cookieAuth: [] }], responses: { "200": okJson("Stats", { type: "object" }), "403": errorResponse } } },
      "/admin/products": {
        get: { tags: ["Admin"], summary: "Produits (admin)", security: [{ cookieAuth: [] }], responses: { "200": okJson("Produits", { type: "object" }) } },
        post: { tags: ["Admin"], summary: "Créer un produit", security: [{ cookieAuth: [], csrfToken: [] }], requestBody: jsonBody({ type: "object" }), responses: { "201": okJson("Créé", { type: "object" }) } },
      },
      "/admin/products/{id}": {
        patch: { tags: ["Admin"], summary: "Modifier un produit", security: [{ cookieAuth: [], csrfToken: [] }], parameters: [{ name: "id", in: "path", required: true, schema: { type: "integer" } }], requestBody: jsonBody({ type: "object" }), responses: { "200": okJson("Modifié", { type: "object" }) } },
        delete: { tags: ["Admin"], summary: "Supprimer un produit", security: [{ cookieAuth: [], csrfToken: [] }], parameters: [{ name: "id", in: "path", required: true, schema: { type: "integer" } }], responses: { "200": okJson("Supprimé", { type: "object" }) } },
      },
      "/admin/orders": { get: { tags: ["Admin"], summary: "Commandes (admin)", security: [{ cookieAuth: [] }], responses: { "200": okJson("Commandes", { type: "object" }) } } },
      "/admin/orders/{id}": { patch: { tags: ["Admin"], summary: "Modifier une commande", security: [{ cookieAuth: [], csrfToken: [] }], parameters: [{ name: "id", in: "path", required: true, schema: { type: "integer" } }], requestBody: jsonBody({ type: "object" }), responses: { "200": okJson("Modifiée", { type: "object" }) } } },
      "/admin/users": { get: { tags: ["Admin"], summary: "Utilisateurs (admin)", security: [{ cookieAuth: [] }], responses: { "200": okJson("Utilisateurs", { type: "object" }) } } },
      "/admin/users/{id}": { patch: { tags: ["Admin"], summary: "Modifier un utilisateur", security: [{ cookieAuth: [], csrfToken: [] }], parameters: [{ name: "id", in: "path", required: true, schema: { type: "integer" } }], requestBody: jsonBody({ type: "object" }), responses: { "200": okJson("Modifié", { type: "object" }) } } },
    },
  };
}
