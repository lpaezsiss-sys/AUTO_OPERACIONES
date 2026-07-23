import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Build listo para Node.js (cPanel / BlueHosting / VPS / PM2)
  output: "standalone",
  // Evita fallos si el host sirve detrás de proxy Apache/Nginx
  poweredByHeader: false,
};

export default nextConfig;
