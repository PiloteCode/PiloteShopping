import { z } from "zod";
import { readQuery, route, success } from "@/lib/api";
import { listProducts } from "@/server/products";

const querySchema = z.object({
  category: z.string().optional(),
  type: z.string().optional(),
  q: z.string().optional(),
  sort: z.enum(["popular", "newest", "price_asc", "price_desc", "rating"]).optional(),
  billing: z.enum(["all", "one_time", "subscription"]).optional(),
  page: z.coerce.number().int().min(1).optional(),
  perPage: z.coerce.number().int().min(1).max(48).optional(),
});

export const GET = route(async (req) => {
  const params = readQuery(req, querySchema);
  const data = await listProducts(params);
  return success(data);
});
