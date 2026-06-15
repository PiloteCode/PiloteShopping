import "./env";
import { rmSync } from "node:fs";

/**
 * Destroys the local PGlite data directory so the next migrate starts clean.
 * No-op (with a warning) when a real DATABASE_URL is configured.
 */
const url = process.env.DATABASE_URL?.trim();
const path = process.env.PGLITE_PATH?.trim() || ".pglite";

if (url) {
  console.warn("⚠ DATABASE_URL is set — refusing to auto-drop a real database. Reset skipped.");
  process.exit(0);
}

try {
  rmSync(path, { recursive: true, force: true });
  console.log(`✓ Removed local database directory: ${path}`);
} catch (err) {
  console.error("✗ Reset failed:", err);
  process.exit(1);
}
