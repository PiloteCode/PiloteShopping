// Page render smoke test. Server must be running on BASE_URL.
const BASE = process.env.BASE_URL || "http://localhost:3000";
const CSRF = "pilote_csrf";
const jar = new Map();
const cookieHeader = () => [...jar].map(([k, v]) => `${k}=${v}`).join("; ");
function store(res) {
  for (const c of res.headers.getSetCookie?.() ?? []) {
    const p = c.split(";")[0];
    const i = p.indexOf("=");
    const k = p.slice(0, i).trim(), v = p.slice(i + 1).trim();
    if (!v || v === "deleted") jar.delete(k); else jar.set(k, v);
  }
}
async function raw(method, path, body) {
  const headers = {};
  if (body) headers["content-type"] = "application/json";
  const ck = cookieHeader(); if (ck) headers.cookie = ck;
  const csrf = jar.get(CSRF); if (csrf && method !== "GET") headers["x-csrf-token"] = csrf;
  const res = await fetch(BASE + path, { method, headers, body: body ? JSON.stringify(body) : undefined, redirect: "manual" });
  store(res);
  return res;
}
async function login(email, password) {
  await raw("GET", "/"); // get csrf + guest cookie
  const res = await raw("POST", "/api/v1/auth/login", { email, password });
  return res.status === 200;
}

let pass = 0, fail = 0;
async function page(path, keyword, expectStatus = 200) {
  const res = await raw("GET", path);
  const html = await res.text().catch(() => "");
  const statusOk = res.status === expectStatus;
  const kwOk = keyword ? html.toLowerCase().includes(keyword.toLowerCase()) : true;
  const noErr = !/Application error|Internal Server Error|__NEXT_ERROR|digest&quot;:&quot;/.test(html);
  const ok = statusOk && kwOk && noErr;
  if (ok) { pass++; console.log(`  ✓ ${path} (${res.status})`); }
  else { fail++; console.log(`  ✗ ${path} status=${res.status}${!kwOk ? ` missing "${keyword}"` : ""}${!noErr ? " ERROR-MARKER" : ""}`); }
}

const PUBLIC = [
  ["/", "marketplace"], ["/products", "Boutique"], ["/products/microsoft-365-famille", "Microsoft 365"],
  ["/cart", null], ["/checkout", null], ["/login", "Connexion"], ["/register", "Créer un compte"],
  ["/forgot-password", "oublié"], ["/about", "mission"], ["/support", "aide"],
  ["/legal", "légales"], ["/terms", null], ["/privacy", "confidentialité"], ["/refunds", "remboursement"],
  ["/api-docs", null], ["/api/openapi.json", "openapi"],
];
const DASH = [
  ["/dashboard", "Tableau de bord"], ["/dashboard/orders", "Commandes"], ["/dashboard/subscriptions", "Abonnements"],
  ["/dashboard/wallet", "Portefeuille"], ["/dashboard/notifications", "Notifications"],
  ["/dashboard/wishlist", "souhaits"], ["/dashboard/settings", "Param"],
];
const ADMIN = [["/admin", "Administration"], ["/admin/products", "Produits"], ["/admin/orders", "Commandes"], ["/admin/users", "Utilisateurs"]];

(async () => {
  console.log("\nPublic pages");
  for (const [p, k] of PUBLIC) await page(p, k);

  console.log("\n404 handling");
  await page("/this-page-does-not-exist", "introuvable", 404);

  console.log("\nDashboard pages (demo user)");
  if (!(await login("demo@piloteshopping.com", "Demo1234!"))) { console.log("  ✗ demo login failed"); fail++; }
  else for (const [p, k] of DASH) await page(p, k);

  console.log("\nAdmin pages (admin user)");
  if (!(await login("admin@piloteshopping.com", "Admin1234!"))) { console.log("  ✗ admin login failed"); fail++; }
  else for (const [p, k] of ADMIN) await page(p, k);

  console.log(`\n──────────────────────────────\n  ${pass} passed, ${fail} failed\n`);
  process.exit(fail > 0 ? 1 : 0);
})();
