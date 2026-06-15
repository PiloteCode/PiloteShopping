import { z } from "zod";
import { readQuery, route, success } from "@/lib/api";
import { getRecommended } from "@/server/products";

const querySchema = z.object({
  source: z.string().optional(),
  productId: z.coerce.number().int().optional(),
  categoryId: z.coerce.number().int().optional(),
  limit: z.coerce.number().int().min(1).max(20).optional(),
  exclude: z.string().optional(),
});

export const GET = route(async (req) => {
  const q = readQuery(req, querySchema);
  const exclude = q.exclude ? q.exclude.split(",").map(Number).filter(Boolean) : [];
  const products = await getRecommended({ ...q, exclude });
  return success({ source: q.source ?? "general", count: products.length, products });
});
