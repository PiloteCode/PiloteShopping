import { route, success } from "@/lib/api";
import { getCategories, getProductTypes } from "@/server/products";

export const GET = route(async () => {
  const [categories, types] = await Promise.all([getCategories(), getProductTypes()]);
  return success({ categories, types });
});
