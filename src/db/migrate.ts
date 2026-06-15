import "./env";
import { migrate as migratePglite } from "drizzle-orm/pglite/migrator";
import { migrate as migratePg } from "drizzle-orm/postgres-js/migrator";
import { db, dbDriver, pglite } from "./index";

async function main() {
  console.log(`→ Applying migrations via ${dbDriver} driver…`);
  if (dbDriver === "pglite") {
    await migratePglite(db as never, { migrationsFolder: "./drizzle" });
  } else {
    await migratePg(db as never, { migrationsFolder: "./drizzle" });
  }
  console.log("✓ Migrations applied.");
  await pglite?.close();
  process.exit(0);
}

main().catch((err) => {
  console.error("✗ Migration failed:", err);
  process.exit(1);
});
