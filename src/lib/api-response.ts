import { NextResponse } from "next/server";

/** Evita que Apache/CDN cachee 401 o respuestas autenticadas. */
export const NO_STORE_HEADERS = {
  "Cache-Control": "private, no-store, max-age=0, must-revalidate",
  Pragma: "no-cache",
  Expires: "0",
} as const;

export function jsonNoStore(
  body: unknown,
  init: { status?: number } = {}
): NextResponse {
  return NextResponse.json(body, {
    status: init.status ?? 200,
    headers: NO_STORE_HEADERS,
  });
}
