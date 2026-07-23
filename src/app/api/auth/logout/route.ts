import { NextResponse } from "next/server";
import { clearSessionCookieOnResponse } from "@/lib/auth";

export async function POST() {
  try {
    const response = NextResponse.json({ ok: true });
    return clearSessionCookieOnResponse(response);
  } catch (error) {
    console.error("POST /api/auth/logout", error);
    return NextResponse.json(
      { error: "Error al cerrar sesión" },
      { status: 500 }
    );
  }
}
