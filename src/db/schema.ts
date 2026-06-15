import { relations } from "drizzle-orm";
import {
  boolean,
  integer,
  jsonb,
  numeric,
  pgEnum,
  pgTable,
  serial,
  text,
  timestamp,
  unique,
  varchar,
} from "drizzle-orm/pg-core";

/* ──────────────────────────────────────────────────────────────────────────
 * Enums (carried over 1:1 from the legacy MySQL schema)
 * ────────────────────────────────────────────────────────────────────────── */
export const billingCycle = pgEnum("billing_cycle", [
  "one_time",
  "monthly",
  "quarterly",
  "semi_yearly",
  "yearly",
  "commitment_3",
  "commitment_6",
  "commitment_12",
  "commitment_24",
]);

export const deliveryMethod = pgEnum("delivery_method", [
  "automatic",
  "semi_automatic",
  "manual_24h",
  "manual_48h",
  "manual_72h",
  "manual_1week",
]);

export const accessMethod = pgEnum("access_method", [
  "no_connection",
  "account_required",
  "license_key",
  "api_access",
  "download_link",
]);

export const supportLevel = pgEnum("support_level", [
  "basic",
  "standard",
  "premium",
  "white_glove",
]);

export const adminRole = pgEnum("admin_role", [
  "super_admin",
  "admin",
  "moderator",
  "support",
]);

export const orderStatus = pgEnum("order_status", [
  "pending",
  "processing",
  "completed",
  "cancelled",
]);

export const paymentStatus = pgEnum("payment_status", [
  "pending",
  "paid",
  "failed",
  "refunded",
]);

export const deliveryStatus = pgEnum("delivery_status", [
  "pending",
  "processing",
  "delivered",
]);

export const subscriptionStatus = pgEnum("subscription_status", [
  "active",
  "paused",
  "cancelled",
  "expired",
  "past_due",
  "pending",
]);

export const txnType = pgEnum("txn_type", [
  "payment",
  "refund",
  "subscription_renewal",
]);

export const txnStatus = pgEnum("txn_status", [
  "pending",
  "completed",
  "failed",
  "processing",
  "refunded",
]);

export const couponType = pgEnum("coupon_type", [
  "percentage",
  "fixed",
  "free_shipping",
]);

export const reviewStatus = pgEnum("review_status", [
  "pending",
  "approved",
  "rejected",
]);

export const inventoryStatus = pgEnum("inventory_status", [
  "available",
  "sold",
  "revoked",
  "expired",
]);

export const fieldType = pgEnum("field_type", [
  "text",
  "email",
  "number",
  "date",
  "textarea",
  "select",
  "radio",
  "checkbox",
  "url",
]);

export const notificationPriority = pgEnum("notification_priority", [
  "low",
  "normal",
  "high",
]);

export const balanceTxnType = pgEnum("balance_txn_type", [
  "credit",
  "debit",
  "refund",
  "subscription_charge",
  "bonus",
]);

export const emailStatus = pgEnum("email_status", [
  "pending",
  "processing",
  "sent",
  "failed",
]);

/* Reusable timestamp columns */
const timestamps = {
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow().notNull(),
};

const money = (name: string) => numeric(name, { precision: 12, scale: 2 });

/* ──────────────────────────────────────────────────────────────────────────
 * Users / auth / security
 * ────────────────────────────────────────────────────────────────────────── */
export const users = pgTable("users", {
  id: serial("id").primaryKey(),
  uuid: varchar("uuid", { length: 36 }).notNull().unique(),
  username: varchar("username", { length: 30 }).notNull().unique(),
  email: varchar("email", { length: 255 }).notNull().unique(),
  passwordHash: varchar("password_hash", { length: 255 }).notNull().default(""),
  firstName: varchar("first_name", { length: 100 }).notNull().default(""),
  lastName: varchar("last_name", { length: 100 }).notNull().default(""),
  phone: varchar("phone", { length: 20 }),
  avatarUrl: varchar("avatar_url", { length: 500 }),
  balance: money("balance").notNull().default("0"),
  loyaltyPoints: integer("loyalty_points").notNull().default(0),
  isActive: boolean("is_active").notNull().default(true),
  isVerified: boolean("is_verified").notNull().default(false),
  profileCompleted: boolean("profile_completed").notNull().default(false),
  emailMarketingConsent: boolean("email_marketing_consent").notNull().default(false),
  stripeCustomerId: varchar("stripe_customer_id", { length: 255 }),
  lastLoginAt: timestamp("last_login_at", { withTimezone: true }),
  lastIp: varchar("last_ip", { length: 45 }),
  ...timestamps,
});

export const userSessions = pgTable("user_sessions", {
  id: varchar("id", { length: 64 }).primaryKey(),
  userId: integer("user_id")
    .notNull()
    .references(() => users.id, { onDelete: "cascade" }),
  ipAddress: varchar("ip_address", { length: 45 }),
  userAgent: varchar("user_agent", { length: 500 }),
  isMobile: boolean("is_mobile").notNull().default(false),
  expiresAt: timestamp("expires_at", { withTimezone: true }).notNull(),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
  lastActiveAt: timestamp("last_active_at", { withTimezone: true }).defaultNow().notNull(),
});

export const usersBilling = pgTable("users_billing", {
  id: serial("id").primaryKey(),
  userId: integer("user_id")
    .notNull()
    .references(() => users.id, { onDelete: "cascade" }),
  firstName: varchar("first_name", { length: 100 }).notNull(),
  lastName: varchar("last_name", { length: 100 }).notNull(),
  companyName: varchar("company_name", { length: 255 }),
  addressLine1: varchar("address_line1", { length: 255 }).notNull(),
  addressLine2: varchar("address_line2", { length: 255 }),
  city: varchar("city", { length: 100 }).notNull(),
  state: varchar("state", { length: 100 }),
  postalCode: varchar("postal_code", { length: 20 }).notNull(),
  country: varchar("country", { length: 2 }).notNull().default("FR"),
  phone: varchar("phone", { length: 20 }),
  isDefault: boolean("is_default").notNull().default(false),
  ...timestamps,
});

export const users2fa = pgTable("users_2fa", {
  id: serial("id").primaryKey(),
  userId: integer("user_id")
    .notNull()
    .unique()
    .references(() => users.id, { onDelete: "cascade" }),
  secret: varchar("secret", { length: 255 }),
  isEnabled: boolean("is_enabled").notNull().default(false),
  activatedAt: timestamp("activated_at", { withTimezone: true }),
  ...timestamps,
});

export const users2faBackup = pgTable("users_2fa_backup", {
  id: serial("id").primaryKey(),
  userId: integer("user_id")
    .notNull()
    .references(() => users.id, { onDelete: "cascade" }),
  codeHash: varchar("code_hash", { length: 255 }).notNull(),
  isUsed: boolean("is_used").notNull().default(false),
  usedAt: timestamp("used_at", { withTimezone: true }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const securityLogs = pgTable("security_logs", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").references(() => users.id, { onDelete: "set null" }),
  action: varchar("action", { length: 100 }).notNull(),
  details: jsonb("details"),
  ipAddress: varchar("ip_address", { length: 45 }),
  userAgent: varchar("user_agent", { length: 500 }),
  status: varchar("status", { length: 50 }).notNull().default("success"),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const adminUsers = pgTable("admin_users", {
  id: serial("id").primaryKey(),
  userId: integer("user_id")
    .notNull()
    .unique()
    .references(() => users.id, { onDelete: "cascade" }),
  role: adminRole("role").notNull().default("support"),
  permissions: jsonb("permissions").$type<string[]>().default([]),
  isActive: boolean("is_active").notNull().default(true),
  lastLoginAt: timestamp("last_login_at", { withTimezone: true }),
  loginCount: integer("login_count").notNull().default(0),
  ...timestamps,
});

/* ──────────────────────────────────────────────────────────────────────────
 * Catalog
 * ────────────────────────────────────────────────────────────────────────── */
export const productCategories = pgTable("product_categories", {
  id: serial("id").primaryKey(),
  name: varchar("name", { length: 255 }).notNull(),
  slug: varchar("slug", { length: 255 }).notNull().unique(),
  description: text("description"),
  icon: varchar("icon", { length: 100 }),
  color: varchar("color", { length: 7 }),
  isActive: boolean("is_active").notNull().default(true),
  sortOrder: integer("sort_order").notNull().default(0),
  ...timestamps,
});

export const productTypes = pgTable("product_types", {
  id: serial("id").primaryKey(),
  name: varchar("name", { length: 255 }).notNull(),
  slug: varchar("slug", { length: 255 }).notNull().unique(),
  description: text("description"),
  icon: varchar("icon", { length: 100 }),
  isActive: boolean("is_active").notNull().default(true),
  sortOrder: integer("sort_order").notNull().default(0),
  ...timestamps,
});

export const products = pgTable("products", {
  id: serial("id").primaryKey(),
  categoryId: integer("category_id")
    .notNull()
    .references(() => productCategories.id),
  productTypeId: integer("product_type_id")
    .notNull()
    .references(() => productTypes.id),
  name: varchar("name", { length: 255 }).notNull(),
  slug: varchar("slug", { length: 255 }).notNull().unique(),
  shortDescription: text("short_description").notNull().default(""),
  description: text("description"),
  features: jsonb("features").$type<string[]>().default([]),
  images: jsonb("images").$type<string[]>().default([]),
  sku: varchar("sku", { length: 100 }),
  isActive: boolean("is_active").notNull().default(true),
  isFeatured: boolean("is_featured").notNull().default(false),
  isDigital: boolean("is_digital").notNull().default(true),
  metaTitle: varchar("meta_title", { length: 255 }),
  metaDescription: text("meta_description"),
  viewCount: integer("view_count").notNull().default(0),
  saleCount: integer("sale_count").notNull().default(0),
  wishlistCount: integer("wishlist_count").notNull().default(0),
  ratingAverage: numeric("rating_average", { precision: 3, scale: 2 }).notNull().default("0"),
  ratingCount: integer("rating_count").notNull().default(0),
  ...timestamps,
});

export const productAccessTypes = pgTable("product_access_types", {
  id: serial("id").primaryKey(),
  productId: integer("product_id")
    .notNull()
    .references(() => products.id, { onDelete: "cascade" }),
  name: varchar("name", { length: 255 }).notNull(),
  slug: varchar("slug", { length: 255 }).notNull(),
  description: text("description"),
  icon: varchar("icon", { length: 100 }).default("Key"),
  accessMethod: accessMethod("access_method").notNull(),
  defaultDeliveryMethod: deliveryMethod("default_delivery_method").notNull().default("automatic"),
  supportLevel: supportLevel("support_level").default("standard"),
  estimatedDeliveryTime: varchar("estimated_delivery_time", { length: 100 }),
  userInstructions: text("user_instructions"),
  hasGlobalStock: boolean("has_global_stock").notNull().default(false),
  globalStockQuantity: integer("global_stock_quantity").notNull().default(0),
  sortOrder: integer("sort_order").notNull().default(0),
  isActive: boolean("is_active").notNull().default(true),
  ...timestamps,
});

export const productOffers = pgTable("product_offers", {
  id: serial("id").primaryKey(),
  accessTypeId: integer("access_type_id")
    .notNull()
    .references(() => productAccessTypes.id, { onDelete: "cascade" }),
  name: varchar("name", { length: 255 }).notNull(),
  slug: varchar("slug", { length: 255 }).notNull(),
  description: text("description"),
  price: money("price").notNull(),
  compareAtPrice: money("compare_at_price"),
  billingCycle: billingCycle("billing_cycle").notNull().default("one_time"),
  trialDays: integer("trial_days").notNull().default(0),
  setupFee: money("setup_fee").notNull().default("0"),
  manageStock: boolean("manage_stock").notNull().default(false),
  stockQuantity: integer("stock_quantity").notNull().default(0),
  sortOrder: integer("sort_order").notNull().default(0),
  isActive: boolean("is_active").notNull().default(true),
  isDefault: boolean("is_default").notNull().default(false),
  saleCount: integer("sale_count").notNull().default(0),
  ratingAverage: numeric("rating_average", { precision: 3, scale: 2 }).notNull().default("0"),
  ratingCount: integer("rating_count").notNull().default(0),
  ...timestamps,
});

export const productOfferCustomFields = pgTable("product_offer_custom_fields", {
  id: serial("id").primaryKey(),
  offerId: integer("offer_id")
    .notNull()
    .references(() => productOffers.id, { onDelete: "cascade" }),
  fieldName: varchar("field_name", { length: 100 }).notNull(),
  fieldLabel: varchar("field_label", { length: 255 }).notNull(),
  fieldType: fieldType("field_type").notNull().default("text"),
  fieldOptions: jsonb("field_options").$type<string[]>().default([]),
  isRequired: boolean("is_required").notNull().default(false),
  isVisibleCustomer: boolean("is_visible_customer").notNull().default(true),
  placeholder: varchar("placeholder", { length: 255 }),
  helpText: text("help_text"),
  sortOrder: integer("sort_order").notNull().default(0),
  isActive: boolean("is_active").notNull().default(true),
  ...timestamps,
});

export const productInventory = pgTable("product_inventory", {
  id: serial("id").primaryKey(),
  offerId: integer("offer_id")
    .notNull()
    .references(() => productOffers.id, { onDelete: "cascade" }),
  inventoryType: varchar("inventory_type", { length: 100 }),
  inventoryData: jsonb("inventory_data"),
  status: inventoryStatus("status").notNull().default("available"),
  soldToUserId: integer("sold_to_user_id").references(() => users.id, { onDelete: "set null" }),
  soldInOrderId: integer("sold_in_order_id"),
  ...timestamps,
});

export const productReviews = pgTable("product_reviews", {
  id: serial("id").primaryKey(),
  productId: integer("product_id")
    .notNull()
    .references(() => products.id, { onDelete: "cascade" }),
  userId: integer("user_id")
    .notNull()
    .references(() => users.id, { onDelete: "cascade" }),
  rating: integer("rating").notNull(),
  title: varchar("title", { length: 255 }),
  comment: text("comment"),
  status: reviewStatus("status").notNull().default("approved"),
  helpfulCount: integer("helpful_count").notNull().default(0),
  ...timestamps,
});

export const wishlists = pgTable(
  "wishlists",
  {
    id: serial("id").primaryKey(),
    userId: integer("user_id")
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    productId: integer("product_id")
      .notNull()
      .references(() => products.id, { onDelete: "cascade" }),
    offerId: integer("offer_id").references(() => productOffers.id, { onDelete: "set null" }),
    addedAt: timestamp("added_at", { withTimezone: true }).defaultNow().notNull(),
  },
  (t) => [unique("uq_wishlist_user_product").on(t.userId, t.productId)],
);

/* ──────────────────────────────────────────────────────────────────────────
 * Cart / coupons / orders / subscriptions / payments
 * ────────────────────────────────────────────────────────────────────────── */
export const cartItems = pgTable("cart_items", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").references(() => users.id, { onDelete: "cascade" }),
  sessionId: varchar("session_id", { length: 64 }),
  productId: integer("product_id")
    .notNull()
    .references(() => products.id, { onDelete: "cascade" }),
  productOfferId: integer("product_offer_id")
    .notNull()
    .references(() => productOffers.id, { onDelete: "cascade" }),
  quantity: integer("quantity").notNull().default(1),
  unitPrice: money("unit_price").notNull(),
  setupFee: money("setup_fee").notNull().default("0"),
  billingCycle: billingCycle("billing_cycle").notNull().default("one_time"),
  isSubscription: boolean("is_subscription").notNull().default(false),
  customData: jsonb("custom_data"),
  ...timestamps,
});

export const coupons = pgTable("coupons", {
  id: serial("id").primaryKey(),
  code: varchar("code", { length: 64 }).notNull().unique(),
  name: varchar("name", { length: 255 }).notNull(),
  description: text("description"),
  discountType: couponType("discount_type").notNull(),
  discountValue: money("discount_value").notNull(),
  maxDiscountAmount: money("max_discount_amount"),
  minimumAmount: money("minimum_amount"),
  usageLimit: integer("usage_limit"),
  usageCount: integer("usage_count").notNull().default(0),
  usageLimitPerUser: integer("usage_limit_per_user").notNull().default(0),
  requiresAccount: boolean("requires_account").notNull().default(false),
  excludeSubscriptions: boolean("exclude_subscriptions").notNull().default(false),
  startDate: timestamp("start_date", { withTimezone: true }),
  endDate: timestamp("end_date", { withTimezone: true }),
  isActive: boolean("is_active").notNull().default(true),
  ...timestamps,
});

export const couponUsages = pgTable("coupon_usages", {
  id: serial("id").primaryKey(),
  couponId: integer("coupon_id")
    .notNull()
    .references(() => coupons.id, { onDelete: "cascade" }),
  userId: integer("user_id").references(() => users.id, { onDelete: "set null" }),
  orderId: integer("order_id"),
  discountAmount: money("discount_amount").notNull(),
  usedAt: timestamp("used_at", { withTimezone: true }).defaultNow().notNull(),
});

export const orders = pgTable("orders", {
  id: serial("id").primaryKey(),
  orderNumber: varchar("order_number", { length: 50 }).notNull().unique(),
  userId: integer("user_id").references(() => users.id, { onDelete: "set null" }),
  status: orderStatus("status").notNull().default("pending"),
  paymentStatus: paymentStatus("payment_status").notNull().default("pending"),
  paymentMethod: varchar("payment_method", { length: 64 }).notNull().default("stripe"),
  currency: varchar("currency", { length: 3 }).notNull().default("EUR"),
  subtotal: money("subtotal").notNull().default("0"),
  taxAmount: money("tax_amount").notNull().default("0"),
  discountAmount: money("discount_amount").notNull().default("0"),
  totalAmount: money("total_amount").notNull().default("0"),
  couponCode: varchar("coupon_code", { length: 64 }),
  paymentReference: varchar("payment_reference", { length: 255 }),
  notes: text("notes"),
  billingAddress: jsonb("billing_address"),
  deliveredAt: timestamp("delivered_at", { withTimezone: true }),
  ...timestamps,
});

export const orderItems = pgTable("order_items", {
  id: serial("id").primaryKey(),
  orderId: integer("order_id")
    .notNull()
    .references(() => orders.id, { onDelete: "cascade" }),
  productId: integer("product_id").references(() => products.id, { onDelete: "set null" }),
  productOfferId: integer("product_offer_id").references(() => productOffers.id, { onDelete: "set null" }),
  productName: varchar("product_name", { length: 255 }).notNull(),
  productSlug: varchar("product_slug", { length: 255 }),
  accessTypeName: varchar("access_type_name", { length: 255 }),
  offerName: varchar("offer_name", { length: 255 }),
  quantity: integer("quantity").notNull().default(1),
  unitPrice: money("unit_price").notNull(),
  totalPrice: money("total_price").notNull(),
  setupFee: money("setup_fee").notNull().default("0"),
  billingCycle: billingCycle("billing_cycle").notNull().default("one_time"),
  productData: jsonb("product_data"),
  deliveryStatus: deliveryStatus("delivery_status").notNull().default("pending"),
  deliveryMethod: deliveryMethod("delivery_method").notNull().default("automatic"),
  deliveryData: jsonb("delivery_data"),
  deliveredAt: timestamp("delivered_at", { withTimezone: true }),
  ...timestamps,
});

export const subscriptions = pgTable("subscriptions", {
  id: serial("id").primaryKey(),
  subscriptionNumber: varchar("subscription_number", { length: 50 }).notNull().unique(),
  userId: integer("user_id")
    .notNull()
    .references(() => users.id, { onDelete: "cascade" }),
  orderItemId: integer("order_item_id").references(() => orderItems.id, { onDelete: "set null" }),
  productOfferId: integer("product_offer_id")
    .notNull()
    .references(() => productOffers.id),
  productName: varchar("product_name", { length: 255 }).notNull().default(""),
  status: subscriptionStatus("status").notNull().default("active"),
  billingCycle: billingCycle("billing_cycle").notNull(),
  amount: money("amount").notNull(),
  currency: varchar("currency", { length: 3 }).notNull().default("EUR"),
  startDate: timestamp("start_date", { withTimezone: true }).defaultNow().notNull(),
  nextBillingDate: timestamp("next_billing_date", { withTimezone: true }).notNull(),
  lastBillingDate: timestamp("last_billing_date", { withTimezone: true }),
  autoRenew: boolean("auto_renew").notNull().default(true),
  billingAttempts: integer("billing_attempts").notNull().default(0),
  cancellationReason: varchar("cancellation_reason", { length: 255 }),
  metadata: jsonb("metadata"),
  ...timestamps,
});

export const paymentAdapters = pgTable("payment_adapters", {
  id: serial("id").primaryKey(),
  slug: varchar("slug", { length: 64 }).notNull().unique(),
  name: varchar("name", { length: 255 }).notNull(),
  isActive: boolean("is_active").notNull().default(true),
  config: jsonb("config").notNull().default({}),
  sortOrder: integer("sort_order").notNull().default(0),
  ...timestamps,
});

export const paymentTransactions = pgTable("payment_transactions", {
  id: serial("id").primaryKey(),
  transactionId: varchar("transaction_id", { length: 64 }).notNull().unique(),
  orderId: integer("order_id").references(() => orders.id, { onDelete: "set null" }),
  userId: integer("user_id").references(() => users.id, { onDelete: "set null" }),
  paymentMethod: varchar("payment_method", { length: 64 }).notNull().default("stripe"),
  type: txnType("type").notNull().default("payment"),
  status: txnStatus("status").notNull().default("pending"),
  amount: money("amount").notNull(),
  currency: varchar("currency", { length: 3 }).notNull().default("EUR"),
  gatewayTransactionId: varchar("gateway_transaction_id", { length: 255 }),
  gatewayResponse: jsonb("gateway_response"),
  metadata: jsonb("metadata"),
  processedAt: timestamp("processed_at", { withTimezone: true }),
  ...timestamps,
});

export const balanceTransactions = pgTable("balance_transactions", {
  id: serial("id").primaryKey(),
  userId: integer("user_id")
    .notNull()
    .references(() => users.id, { onDelete: "cascade" }),
  transactionType: balanceTxnType("transaction_type").notNull(),
  amount: money("amount").notNull(),
  description: varchar("description", { length: 255 }),
  referenceOrderId: integer("reference_order_id").references(() => orders.id, { onDelete: "set null" }),
  balanceBefore: money("balance_before").notNull(),
  balanceAfter: money("balance_after").notNull(),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

/* ──────────────────────────────────────────────────────────────────────────
 * Notifications / system
 * ────────────────────────────────────────────────────────────────────────── */
export const notifications = pgTable("notifications", {
  id: serial("id").primaryKey(),
  userId: integer("user_id")
    .notNull()
    .references(() => users.id, { onDelete: "cascade" }),
  type: varchar("type", { length: 50 }).notNull().default("system"),
  title: varchar("title", { length: 255 }).notNull(),
  message: text("message").notNull(),
  link: varchar("link", { length: 500 }),
  icon: varchar("icon", { length: 50 }),
  color: varchar("color", { length: 20 }),
  priority: notificationPriority("priority").notNull().default("normal"),
  data: jsonb("data"),
  isRead: boolean("is_read").notNull().default(false),
  readAt: timestamp("read_at", { withTimezone: true }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

export const systemSettings = pgTable("system_settings", {
  id: serial("id").primaryKey(),
  settingKey: varchar("setting_key", { length: 100 }).notNull().unique(),
  settingValue: text("setting_value"),
  settingType: varchar("setting_type", { length: 50 }).default("string"),
  isPublic: boolean("is_public").notNull().default(false),
  ...timestamps,
});

export const emailQueue = pgTable("email_queue", {
  id: serial("id").primaryKey(),
  toEmail: varchar("to_email", { length: 255 }).notNull(),
  toName: varchar("to_name", { length: 255 }),
  subject: varchar("subject", { length: 500 }).notNull(),
  template: varchar("template", { length: 100 }),
  body: text("body"),
  variables: jsonb("variables"),
  priority: integer("priority").notNull().default(3),
  status: emailStatus("status").notNull().default("pending"),
  attempts: integer("attempts").notNull().default(0),
  lastError: text("last_error"),
  sentAt: timestamp("sent_at", { withTimezone: true }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow().notNull(),
});

/* ──────────────────────────────────────────────────────────────────────────
 * Relations
 * ────────────────────────────────────────────────────────────────────────── */
export const usersRelations = relations(users, ({ many, one }) => ({
  sessions: many(userSessions),
  billing: many(usersBilling),
  orders: many(orders),
  subscriptions: many(subscriptions),
  reviews: many(productReviews),
  wishlists: many(wishlists),
  notifications: many(notifications),
  cartItems: many(cartItems),
  balanceTransactions: many(balanceTransactions),
  twoFactor: one(users2fa),
  admin: one(adminUsers),
}));

export const adminUsersRelations = relations(adminUsers, ({ one }) => ({
  user: one(users, { fields: [adminUsers.userId], references: [users.id] }),
}));

export const categoriesRelations = relations(productCategories, ({ many }) => ({
  products: many(products),
}));

export const typesRelations = relations(productTypes, ({ many }) => ({
  products: many(products),
}));

export const productsRelations = relations(products, ({ one, many }) => ({
  category: one(productCategories, {
    fields: [products.categoryId],
    references: [productCategories.id],
  }),
  type: one(productTypes, {
    fields: [products.productTypeId],
    references: [productTypes.id],
  }),
  accessTypes: many(productAccessTypes),
  reviews: many(productReviews),
}));

export const accessTypesRelations = relations(productAccessTypes, ({ one, many }) => ({
  product: one(products, {
    fields: [productAccessTypes.productId],
    references: [products.id],
  }),
  offers: many(productOffers),
}));

export const offersRelations = relations(productOffers, ({ one, many }) => ({
  accessType: one(productAccessTypes, {
    fields: [productOffers.accessTypeId],
    references: [productAccessTypes.id],
  }),
  customFields: many(productOfferCustomFields),
  inventory: many(productInventory),
}));

export const offerCustomFieldsRelations = relations(productOfferCustomFields, ({ one }) => ({
  offer: one(productOffers, {
    fields: [productOfferCustomFields.offerId],
    references: [productOffers.id],
  }),
}));

export const reviewsRelations = relations(productReviews, ({ one }) => ({
  product: one(products, {
    fields: [productReviews.productId],
    references: [products.id],
  }),
  user: one(users, { fields: [productReviews.userId], references: [users.id] }),
}));

export const cartItemsRelations = relations(cartItems, ({ one }) => ({
  product: one(products, { fields: [cartItems.productId], references: [products.id] }),
  offer: one(productOffers, {
    fields: [cartItems.productOfferId],
    references: [productOffers.id],
  }),
  user: one(users, { fields: [cartItems.userId], references: [users.id] }),
}));

export const ordersRelations = relations(orders, ({ one, many }) => ({
  user: one(users, { fields: [orders.userId], references: [users.id] }),
  items: many(orderItems),
  transactions: many(paymentTransactions),
}));

export const orderItemsRelations = relations(orderItems, ({ one }) => ({
  order: one(orders, { fields: [orderItems.orderId], references: [orders.id] }),
  product: one(products, { fields: [orderItems.productId], references: [products.id] }),
}));

export const subscriptionsRelations = relations(subscriptions, ({ one }) => ({
  user: one(users, { fields: [subscriptions.userId], references: [users.id] }),
  offer: one(productOffers, {
    fields: [subscriptions.productOfferId],
    references: [productOffers.id],
  }),
}));

export const wishlistsRelations = relations(wishlists, ({ one }) => ({
  user: one(users, { fields: [wishlists.userId], references: [users.id] }),
  product: one(products, { fields: [wishlists.productId], references: [products.id] }),
}));

export const notificationsRelations = relations(notifications, ({ one }) => ({
  user: one(users, { fields: [notifications.userId], references: [users.id] }),
}));

/* Convenience type exports */
export type User = typeof users.$inferSelect;
export type NewUser = typeof users.$inferInsert;
export type Product = typeof products.$inferSelect;
export type ProductOffer = typeof productOffers.$inferSelect;
export type Order = typeof orders.$inferSelect;
export type OrderItem = typeof orderItems.$inferSelect;
export type Subscription = typeof subscriptions.$inferSelect;
export type CartItem = typeof cartItems.$inferSelect;
export type Notification = typeof notifications.$inferSelect;
export type Coupon = typeof coupons.$inferSelect;
