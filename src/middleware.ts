import { NextRequest, NextResponse } from "next/server";
import { SESSION_COOKIE, verifySessionToken } from "@/lib/session-token";
import { absoluteUrl } from "@/lib/request-origin";
import { NO_STORE_HEADERS } from "@/lib/api-response";

const PUBLIC_PATHS = [
  "/login",
  "/recuperar",
  "/api/auth/login",
  "/api/auth/reset-password",
  "/api/setup",
];

export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  const isStaticAsset =
    pathname.startsWith("/_next") ||
    pathname.startsWith("/favicon") ||
    /\.(?:svg|png|jpg|jpeg|gif|webp|ico)$/.test(pathname);

  if (isStaticAsset) {
    return NextResponse.next();
  }

  const isPublic = PUBLIC_PATHS.some((p) => pathname === p);
  const token = request.cookies.get(SESSION_COOKIE)?.value;
  const session = token ? await verifySessionToken(token) : null;

  if (pathname === "/login" || pathname === "/recuperar") {
    if (session && pathname === "/login") {
      return NextResponse.redirect(absoluteUrl(request, "/"));
    }
    return NextResponse.next();
  }

  if (isPublic) {
    return NextResponse.next();
  }

  if (!session) {
    if (pathname.startsWith("/api/")) {
      return NextResponse.json(
        { error: "No autenticado" },
        { status: 401, headers: NO_STORE_HEADERS }
      );
    }
    const loginUrl = absoluteUrl(request, "/login");
    loginUrl.searchParams.set("next", pathname);
    const redirect = NextResponse.redirect(loginUrl);
    Object.entries(NO_STORE_HEADERS).forEach(([k, v]) =>
      redirect.headers.set(k, v)
    );
    return redirect;
  }

  if (pathname.startsWith("/api/")) {
    const res = NextResponse.next();
    Object.entries(NO_STORE_HEADERS).forEach(([k, v]) =>
      res.headers.set(k, v)
    );
    return res;
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next/static|_next/image).*)"],
};
