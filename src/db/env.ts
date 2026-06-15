/**
 * Loads .env for standalone scripts (migrate/seed/test) run via tsx.
 * Next.js loads env itself, so this is only imported by CLI scripts.
 * Import this FIRST, before any module that reads process.env at load time.
 */
try {
  // Node 22: built-in .env loader, no dependency needed.
  process.loadEnvFile(".env");
} catch {
  // .env missing is fine — fall back to real environment variables.
}
