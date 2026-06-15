import { z } from "zod";

export const emailSchema = z
  .string()
  .trim()
  .toLowerCase()
  .email("Adresse email invalide")
  .max(255);

export const passwordSchema = z
  .string()
  .min(8, "Le mot de passe doit faire au moins 8 caractères")
  .max(128)
  .regex(/[a-z]/, "Au moins une minuscule requise")
  .regex(/[A-Z]/, "Au moins une majuscule requise")
  .regex(/[0-9]/, "Au moins un chiffre requis")
  .regex(/[^A-Za-z0-9]/, "Au moins un caractère spécial requis");

const nameSchema = z
  .string()
  .trim()
  .min(2, "Trop court")
  .max(50)
  .regex(/^[a-zA-ZÀ-ÿ '-]+$/, "Caractères non autorisés");

export const registerSchema = z
  .object({
    email: emailSchema,
    password: passwordSchema,
    confirmPassword: z.string(),
    firstName: nameSchema,
    lastName: nameSchema,
    username: z
      .string()
      .trim()
      .min(3, "Au moins 3 caractères")
      .max(30)
      .regex(/^[a-zA-Z0-9_-]+$/, "Lettres, chiffres, _ et - uniquement"),
  })
  .refine((d) => d.password === d.confirmPassword, {
    message: "Les mots de passe ne correspondent pas",
    path: ["confirmPassword"],
  });

export const loginSchema = z.object({
  email: emailSchema,
  password: z.string().min(1, "Mot de passe requis"),
  rememberMe: z.boolean().optional().default(false),
  totpCode: z.string().optional(),
});

export const addressSchema = z.object({
  firstName: z.string().trim().min(1).max(100),
  lastName: z.string().trim().min(1).max(100),
  companyName: z.string().trim().max(255).optional(),
  addressLine1: z.string().trim().min(3, "Adresse trop courte").max(255),
  addressLine2: z.string().trim().max(255).optional(),
  city: z.string().trim().min(2).max(100),
  postalCode: z.string().trim().min(2).max(20),
  country: z.string().trim().length(2).toUpperCase().default("FR"),
  phone: z.string().trim().max(20).optional(),
});

export const addToCartSchema = z.object({
  productId: z.coerce.number().int().positive(),
  offerId: z.coerce.number().int().positive(),
  quantity: z.coerce.number().int().min(1).max(10).optional().default(1),
  customData: z.record(z.any()).optional(),
});

export const updateCartSchema = z.object({
  quantity: z.coerce.number().int().min(0).max(10),
});

export const couponSchema = z.object({
  code: z.string().trim().toUpperCase().min(2).max(64),
});

export const createOrderSchema = z.object({
  paymentMethod: z.enum(["stripe", "paypal", "bank_transfer", "balance"]),
  billingAddress: addressSchema,
  notes: z.string().trim().max(1000).optional(),
});

export const profileUpdateSchema = z.object({
  firstName: nameSchema.optional(),
  lastName: nameSchema.optional(),
  phone: z.string().trim().max(20).optional(),
});

export const passwordChangeSchema = z.object({
  currentPassword: z.string().min(1),
  newPassword: passwordSchema,
});

export const addFundsSchema = z.object({
  amount: z.coerce.number().min(5, "Minimum 5 €").max(1000, "Maximum 1000 €"),
});

export const reviewSchema = z.object({
  productId: z.coerce.number().int().positive(),
  rating: z.coerce.number().int().min(1).max(5),
  title: z.string().trim().max(255).optional(),
  comment: z.string().trim().max(2000).optional(),
});
