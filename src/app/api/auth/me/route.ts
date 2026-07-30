import { NextResponse } from "next/server";
import { getSession } from "@/lib/auth";

export const dynamic = "force-dynamic";
export const revalidate = 0;

export async function GET() {
  try {
    const session = await getSession();
    if (!session) {
      return NextResponse.json({ user: null }, { status: 401 });
    }
    return NextResponse.json({
      user: {
        id: session.userId,
        username: session.username,
        name: session.name,
      },
    });
  } catch (error) {
    console.error("GET /api/auth/me", error);
    return NextResponse.json(
      { error: "Error al obtener sesión" },
      { status: 500 }
    );
  }
}
