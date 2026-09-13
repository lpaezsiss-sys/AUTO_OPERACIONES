import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { prisma } from "@/lib/prisma";
import { getSession } from "@/lib/auth";

export const dynamic = "force-dynamic";
export const revalidate = 0;

function recoverySecret(): string {
  return (
    process.env.RECOVERY_TOKEN?.trim() ||
    process.env.SETUP_TOKEN?.trim() ||
    ""
  );
}

/**
 * Recuperar contraseña olvidada.
 * Requiere RECOVERY_TOKEN (o SETUP_TOKEN) definido en .env del servidor.
 */
export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const username = String(body.username ?? "").trim();
    const recoveryToken = String(body.recoveryToken ?? body.token ?? "").trim();
    const newPassword = String(body.newPassword ?? "");

    if (!username || !recoveryToken || !newPassword) {
      return NextResponse.json(
        { error: "Usuario, código de recuperación y nueva contraseña son obligatorios" },
        { status: 400 }
      );
    }

    if (newPassword.length < 8) {
      return NextResponse.json(
        { error: "La nueva contraseña debe tener al menos 8 caracteres" },
        { status: 400 }
      );
    }

    const expected = recoverySecret();
    if (!expected || expected.length < 8) {
      return NextResponse.json(
        {
          error:
            "Recuperación no configurada. Agrega RECOVERY_TOKEN en el .env del servidor y reinicia.",
        },
        { status: 503 }
      );
    }

    if (recoveryToken !== expected) {
      return NextResponse.json(
        { error: "Código de recuperación inválido" },
        { status: 401 }
      );
    }

    const user = await prisma.user.findUnique({ where: { username } });
    if (!user) {
      return NextResponse.json({ error: "Usuario no encontrado" }, { status: 404 });
    }

    const passwordHash = await bcrypt.hash(newPassword, 10);
    await prisma.user.update({
      where: { id: user.id },
      data: { passwordHash },
    });

    return NextResponse.json({
      ok: true,
      message: "Contraseña actualizada. Ya puedes iniciar sesión.",
    });
  } catch (error) {
    console.error("POST /api/auth/reset-password", error);
    return NextResponse.json(
      { error: "Error al restablecer la contraseña" },
      { status: 500 }
    );
  }
}

/** Indica si la recuperación está habilitada (sin revelar el token). */
export async function GET() {
  const enabled = recoverySecret().length >= 8;
  const session = await getSession();
  return NextResponse.json({
    recoveryEnabled: enabled,
    authenticated: Boolean(session),
  });
}
