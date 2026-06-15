import { NextResponse } from "next/server";
import { cookies } from "next/headers";
import { z, ZodError } from "zod";

export const CSRF_COOKIE = process.env.CSRF_COOKIE || "pilote_csrf";

/** Thrown anywhere in a handler to short-circuit with a JSON error. */
export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public code?: string,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export const badRequest = (m: string, code?: string) => new ApiError(400, m, code);
export const unauthorized = (m = "Authentification requise") => new ApiError(401, m, "UNAUTHENTICATED");
export const forbidden = (m = "Accès non autorisé") => new ApiError(403, m, "FORBIDDEN");
export const notFound = (m = "Ressource introuvable") => new ApiError(404, m, "NOT_FOUND");
export const tooMany = (m = "Trop de requêtes") => new ApiError(429, m, "RATE_LIMITED");

/** Success envelope: { success: true, data, message? }. */
export function success<T>(data: T, message?: string, init?: ResponseInit) {
  return NextResponse.json({ success: true, ...(message ? { message } : {}), data }, init);
}

export function created<T>(data: T, message?: string) {
  return success(data, message, { status: 201 });
}

export function failure(message: string, status = 400, code?: string) {
  return NextResponse.json({ success: false, error: message, ...(code ? { code } : {}) }, { status });
}

/** Wraps a route handler with uniform error handling. */
export function route<Ctx = unknown>(
  handler: (req: Request, ctx: Ctx) => Promise<Response> | Response,
) {
  return async (req: Request, ctx: Ctx): Promise<Response> => {
    try {
      return await handler(req, ctx);
    } catch (err) {
      if (err instanceof ApiError) {
        return failure(err.message, err.status, err.code);
      }
      if (err instanceof ZodError) {
        const first = err.errors[0];
        return failure(first?.message || "Données invalides", 400, "VALIDATION_ERROR");
      }
      console.error("[api] unhandled error:", err);
      const dev = process.env.NODE_ENV !== "production";
      return failure(dev ? `Erreur serveur: ${(err as Error).message}` : "Erreur serveur", 500, "SERVER_ERROR");
    }
  };
}

/** Parse + validate a JSON body against a zod schema. */
export async function readBody<T extends z.ZodTypeAny>(req: Request, schema: T): Promise<z.infer<T>> {
  let raw: unknown;
  try {
    raw = await req.json();
  } catch {
    throw badRequest("Corps de requête JSON invalide");
  }
  const parsed = schema.safeParse(raw);
  if (!parsed.success) {
    throw badRequest(parsed.error.errors[0]?.message || "Données invalides", "VALIDATION_ERROR");
  }
  return parsed.data;
}

/** Validate query params against a zod schema. */
export function readQuery<T extends z.ZodTypeAny>(req: Request, schema: T): z.infer<T> {
  const url = new URL(req.url);
  const obj = Object.fromEntries(url.searchParams.entries());
  const parsed = schema.safeParse(obj);
  if (!parsed.success) {
    throw badRequest(parsed.error.errors[0]?.message || "Paramètres invalides", "VALIDATION_ERROR");
  }
  return parsed.data;
}

/**
 * Double-submit CSRF check for mutating requests.
 * Compares the X-CSRF-Token header to the readable csrf cookie set by middleware.
 */
export async function assertCsrf(req: Request) {
  const header = req.headers.get("x-csrf-token");
  const cookie = (await cookies()).get(CSRF_COOKIE)?.value;
  if (!header || !cookie || header !== cookie) {
    throw new ApiError(403, "Jeton CSRF invalide ou manquant", "CSRF_INVALID");
  }
}

/** Convert a numeric DB string (numeric/decimal) to a JS number. */
export const num = (v: string | number | null | undefined): number =>
  v == null ? 0 : typeof v === "number" ? v : parseFloat(v);
