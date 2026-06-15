import { NextResponse } from "next/server";
import { buildOpenApiDocument } from "@/lib/openapi";

export const dynamic = "force-dynamic";

export function GET() {
  return NextResponse.json(buildOpenApiDocument());
}
