import { PGlite } from "@electric-sql/pglite";
import { drizzle as drizzlePglite, type PgliteDatabase } from "drizzle-orm/pglite";
import { drizzle as drizzlePg } from "drizzle-orm/postgres-js";
import postgres from "postgres";
import * as schema from "./schema";

/**
 * Dual-driver database client.
 *
 *  - DATABASE_URL set   → real PostgreSQL (postgres.js) for production.
 *  - DATABASE_URL empty → embedded PGlite (PostgreSQL compiled to WASM),
 *    persisted to PGLITE_PATH. Zero install, identical SQL dialect — perfect
 *    for local dev and CI.
 *
 * A single instance is cached on globalThis so Next.js HMR / route handlers
 * reuse one connection (PGlite is single-process).
 */
const DATABASE_URL = process.env.DATABASE_URL?.trim();
const PGLITE_PATH = process.env.PGLITE_PATH?.trim() || ".pglite";

type Schema = typeof schema;
export type AppDb = PgliteDatabase<Schema> & { $client?: unknown };

interface DbHandle {
  db: AppDb;
  pglite: PGlite | null;
  driver: "pglite" | "postgres";
}

const globalForDb = globalThis as unknown as { __piloteDb?: DbHandle };

function init(): DbHandle {
  if (DATABASE_URL) {
    const sql = postgres(DATABASE_URL, { max: 10 });
    const db = drizzlePg(sql, { schema, casing: "snake_case" }) as unknown as AppDb;
    return { db, pglite: null, driver: "postgres" };
  }
  const pglite = new PGlite(PGLITE_PATH);
  const db = drizzlePglite(pglite, { schema }) as AppDb;
  return { db, pglite, driver: "pglite" };
}

const handle: DbHandle = globalForDb.__piloteDb ?? (globalForDb.__piloteDb = init());

export const db = handle.db;
export const pglite = handle.pglite;
export const dbDriver = handle.driver;
export { schema };
