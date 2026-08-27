import type { NextRequest } from "next/server";

/**
 * Origen público detrás de Proxy/Passenger (cPanel).
 * Evita redirects a http://0.0.0.0:PORT cuando HOSTNAME=0.0.0.0.
 */
export function getRequestOrigin(request: NextRequest): string {
  const forwardedHost = request.headers.get("x-forwarded-host");
  const host = forwardedHost || request.headers.get("host");
  if (!host || host.startsWith("0.0.0.0") || host.startsWith("127.0.0.1")) {
    try {
      const url = new URL(request.url);
      if (
        url.hostname &&
        url.hostname !== "0.0.0.0" &&
        url.hostname !== "127.0.0.1"
      ) {
        return url.origin;
      }
    } catch {
      // ignore
    }
  }

  const protoHeader = request.headers.get("x-forwarded-proto");
  const proto =
    protoHeader?.split(",")[0]?.trim() ||
    (host && !host.includes("localhost") ? "https" : "http");

  if (host && !host.startsWith("0.0.0.0") && !host.startsWith("127.0.0.1")) {
    return `${proto}://${host.split(",")[0].trim()}`;
  }

  return new URL(request.url).origin;
}

export function absoluteUrl(request: NextRequest, path: string): URL {
  return new URL(path, getRequestOrigin(request));
}
