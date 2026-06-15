/**
 * End-to-end API smoke test. Assumes the dev/prod server is running and the DB
 * is seeded. Run: pnpm test:api  (BASE_URL overridable).
 */
const BASE = process.env.BASE_URL || "http://localhost:3000";
const CSRF_COOKIE = process.env.CSRF_COOKIE || "pilote_csrf";

const jar = new Map<string, string>();
function cookieHeader() {
  return [...jar].map(([k, v]) => `${k}=${v}`).join("; ");
}
function store(res: Response) {
  const setCookies = (res.headers as Headers & { getSetCookie?: () => string[] }).getSetCookie?.() ?? [];
  for (const c of setCookies) {
    const pair = c.split(";")[0];
    const idx = pair.indexOf("=");
    const k = pair.slice(0, idx).trim();
    const v = pair.slice(idx + 1).trim();
    if (!v || v === "deleted") jar.delete(k);
    else jar.set(k, v);
  }
}

interface CallOpts { csrf?: boolean }
async function call(method: string, path: string, body?: unknown, opts: CallOpts = {}) {
  const headers: Record<string, string> = {};
  if (body) headers["content-type"] = "application/json";
  const cookie = cookieHeader();
  if (cookie) headers.cookie = cookie;
  const csrf = jar.get(CSRF_COOKIE);
  if (csrf && method !== "GET" && opts.csrf !== false) headers["x-csrf-token"] = csrf;
  const res = await fetch(BASE + path, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
    redirect: "manual",
  });
  store(res);
  let json: any = null;
  try { json = await res.json(); } catch { /* non-json */ }
  return { status: res.status, json };
}

let passed = 0;
let failed = 0;
function check(label: string, cond: boolean, detail = "") {
  if (cond) { passed++; console.log(`  ✓ ${label}`); }
  else { failed++; console.log(`  ✗ ${label}  ${detail}`); }
}

async function main() {
  console.log(`\nTesting API @ ${BASE}\n`);

  console.log("Public catalogue");
  const products = await call("GET", "/api/v1/products");
  check("GET /products 200 + items", products.status === 200 && products.json?.data?.items?.length > 0, `status=${products.status}`);
  const slug = products.json?.data?.items?.[0]?.slug;

  const detail = await call("GET", `/api/v1/products/${slug}`);
  check("GET /products/{slug} 200", detail.status === 200 && !!detail.json?.data?.name, `status=${detail.status}`);

  const cats = await call("GET", "/api/v1/categories");
  check("GET /categories has categories", (cats.json?.data?.categories?.length ?? 0) > 0);

  const reco = await call("GET", "/api/v1/products/recommended?limit=4");
  check("GET /products/recommended", reco.status === 200 && Array.isArray(reco.json?.data?.products));

  const spec = await call("GET", "/api/openapi.json");
  check("GET /api/openapi.json valid", spec.json?.openapi === "3.1.0" && !!spec.json?.paths);

  console.log("\nAuth (admin)");
  const adminLogin = await call("POST", "/api/v1/auth/login", { email: "admin@piloteshopping.com", password: "Admin1234!" });
  check("POST /auth/login admin 200", adminLogin.status === 200 && adminLogin.json?.data?.user?.isAdmin === true, `status=${adminLogin.status} ${JSON.stringify(adminLogin.json)}`);
  const me = await call("GET", "/api/v1/auth/me");
  check("GET /auth/me returns user", me.json?.data?.user?.email === "admin@piloteshopping.com");
  const stats = await call("GET", "/api/v1/admin/stats");
  check("GET /admin/stats 200 (admin)", stats.status === 200 && typeof stats.json?.data?.users?.total === "number", `status=${stats.status}`);
  await call("POST", "/api/v1/auth/logout");

  console.log("\nNegative checks");
  const noCsrf = await call("POST", "/api/v1/auth/login", { email: "x@x.com", password: "y" }, { csrf: false });
  check("POST without CSRF → 403", noCsrf.status === 403, `status=${noCsrf.status}`);
  const adminGuard = await call("GET", "/api/v1/admin/stats");
  check("GET /admin/stats unauth → 401", adminGuard.status === 401, `status=${adminGuard.status}`);

  console.log("\nShopping flow (demo user, balance payment)");
  const login = await call("POST", "/api/v1/auth/login", { email: "demo@piloteshopping.com", password: "Demo1234!" });
  check("POST /auth/login demo 200", login.status === 200, `status=${login.status}`);

  const win = await call("GET", "/api/v1/products/windows-11-pro");
  const offerId = win.json?.data?.accessTypes?.[0]?.offers?.[0]?.id;
  const productId = win.json?.data?.id;
  check("Windows 11 offer found", !!offerId && !!productId);

  const add = await call("POST", "/api/v1/cart", { productId, offerId, quantity: 1 });
  check("POST /cart add 200", add.status === 200 && add.json?.success === true, `status=${add.status} ${JSON.stringify(add.json)}`);

  const cart = await call("GET", "/api/v1/cart");
  check("GET /cart has 1 item", cart.json?.data?.items?.length === 1, JSON.stringify(cart.json?.data?.summary));

  const coupon = await call("POST", "/api/v1/cart/coupon", { code: "BIENVENUE15" });
  check("POST /cart/coupon applied", coupon.status === 200 && coupon.json?.data?.coupon?.discountAmount > 0, `status=${coupon.status} ${JSON.stringify(coupon.json)}`);

  const checkout = await call("POST", "/api/v1/checkout", {
    paymentMethod: "balance",
    billingAddress: { firstName: "Alex", lastName: "Martin", addressLine1: "12 rue des Lilas", city: "Lyon", postalCode: "69001", country: "FR" },
  });
  check("POST /checkout 200 + paid", checkout.status === 200 && checkout.json?.data?.paymentStatus === "paid", `status=${checkout.status} ${JSON.stringify(checkout.json)}`);
  const orderNumber = checkout.json?.data?.orderNumber;

  const orders = await call("GET", "/api/v1/orders");
  check("GET /orders includes new order", orders.json?.data?.orders?.some((o: any) => o.orderNumber === orderNumber));

  const od = await call("GET", `/api/v1/orders/${orderNumber}`);
  const deliveredItem = od.json?.data?.items?.find((i: any) => i.hasDelivery);
  check("GET /orders/{number} has delivered item", !!deliveredItem, JSON.stringify(od.json?.data?.items?.map((i: any) => i.deliveryStatus)));

  if (deliveredItem) {
    const access = await call("POST", "/api/v1/orders/access", { orderItemId: deliveredItem.id });
    check("POST /orders/access returns delivery data", access.status === 200 && !!access.json?.data?.data, `status=${access.status}`);
  }

  const wallet = await call("GET", "/api/v1/wallet");
  check("GET /wallet balance debited", typeof wallet.json?.data?.balance === "number" && wallet.json?.data?.balance < 42.5, `balance=${wallet.json?.data?.balance}`);

  const subs = await call("GET", "/api/v1/subscriptions");
  check("GET /subscriptions 200", subs.status === 200 && Array.isArray(subs.json?.data?.subscriptions));

  const notifs = await call("GET", "/api/v1/notifications");
  check("GET /notifications 200", notifs.status === 200 && Array.isArray(notifs.json?.data?.notifications));

  const profile = await call("GET", "/api/v1/settings/profile");
  check("GET /settings/profile 200", profile.status === 200 && !!profile.json?.data?.profile?.email);

  const twofa = await call("POST", "/api/v1/settings/2fa/setup");
  check("POST /2fa/setup returns secret+qr", twofa.status === 200 && !!twofa.json?.data?.secret && !!twofa.json?.data?.qrCode, `status=${twofa.status}`);

  await call("POST", "/api/v1/auth/logout");
  const meAfter = await call("GET", "/api/v1/auth/me");
  check("GET /auth/me after logout → null", meAfter.json?.data?.user === null);

  console.log(`\n──────────────────────────────\n  ${passed} passed, ${failed} failed\n`);
  process.exit(failed > 0 ? 1 : 0);
}

main().catch((e) => {
  console.error("Test runner crashed:", e);
  process.exit(1);
});
