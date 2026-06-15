import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // ESLint is run separately in CI; don't block production builds on it.
  eslint: { ignoreDuringBuilds: true },
  typescript: { ignoreBuildErrors: false },
  images: {
    remotePatterns: [
      { protocol: "https", hostname: "**" },
      { protocol: "http", hostname: "**" },
    ],
  },
  // PGlite ships a WASM binary + uses Node fs; keep it external so the
  // server bundler doesn't try to inline/transform it.
  serverExternalPackages: ["@electric-sql/pglite"],
};

export default nextConfig;
