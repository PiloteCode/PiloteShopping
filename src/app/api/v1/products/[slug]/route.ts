import { notFound, route, success } from "@/lib/api";
import { getProductBySlug } from "@/server/products";

export const GET = route(async (_req, ctx: { params: Promise<{ slug: string }> }) => {
  const { slug } = await ctx.params;
  const product = await getProductBySlug(slug);
  if (!product) throw notFound("Produit introuvable");
  return success(product);
});
