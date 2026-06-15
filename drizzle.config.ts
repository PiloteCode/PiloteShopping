import { defineConfig } from "drizzle-kit";

// Migrations are generated for Postgres and applied to either PGlite (dev)
// or a real Postgres (prod) by src/db/migrate.ts.
export default defineConfig({
  schema: "./src/db/schema.ts",
  out: "./drizzle",
  dialect: "postgresql",
  strict: true,
  verbose: true,
});
