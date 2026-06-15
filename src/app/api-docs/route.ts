import { ApiReference } from "@scalar/nextjs-api-reference";

export const GET = ApiReference({
  spec: { url: "/api/openapi.json" },
  theme: "purple",
  metaData: { title: "Pilote Shopping — API Reference" },
});
