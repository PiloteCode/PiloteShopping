import { z } from "zod";
import { assertCsrf, created, readBody, route, success } from "@/lib/api";
import { requireAdmin } from "@/lib/auth";
import { createAdminProduct, listAdminProducts } from "@/server/admin";

export const GET = route(async () => {
  await requireAdmin();
  return success({ products: await listAdminProducts() });
});

export const POST = route(async (req) => {
  await assertCsrf(req);
  await requireAdmin();
  const body = await readBody(
    req,
    z.object({
      name: z.string().trim().min(2).max(255),
      categoryId: z.coerce.number().int().positive(),
      productTypeId: z.coerce.number().int().positive(),
      shortDescription: z.string().trim().min(2).max(500),
      price: z.coerce.number().min(0),
      accessMethod: z.enum(["no_connection", "account_required", "license_key", "api_access", "download_link"]),
    }),
  );
  const result = await createAdminProduct(body);
  return created(result, "Produit créé");
});
