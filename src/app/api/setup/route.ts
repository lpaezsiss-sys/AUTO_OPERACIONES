import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { prisma } from "@/lib/prisma";

function setupEnabled() {
  return Boolean(process.env.SETUP_TOKEN && process.env.SETUP_TOKEN.length >= 8);
}

function tokenOk(request: NextRequest, bodyToken?: string) {
  const expected = process.env.SETUP_TOKEN || "";
  const header = request.headers.get("x-setup-token") || "";
  const provided = header || bodyToken || "";
  return expected.length >= 8 && provided === expected;
}

/** Estado del servidor (sin secretos). Útil sin acceso a Terminal. */
export async function GET() {
  const hasAuthSecret = Boolean(process.env.AUTH_SECRET);
  const setupReady = setupEnabled();
  let dbOk = false;
  let userCount: number | null = null;
  let dbError: string | null = null;

  try {
    userCount = await prisma.user.count();
    dbOk = true;
  } catch (error) {
    dbError =
      error instanceof Error
        ? error.message.slice(0, 160)
        : "Error de base de datos";
  }

  return NextResponse.json({
    ok: true,
    hasAuthSecret,
    setupReady,
    dbOk,
    userCount,
    dbError,
    hint: !hasAuthSecret
      ? "Define AUTH_SECRET en .env o en Variables de entorno de Node.js"
      : !dbOk
        ? "Reinicia la app (app.js corre migrate). Revisa DATABASE_URL=file:../data/prod.db"
        : userCount === 0
          ? "No hay usuarios. Pon RUN_SEED=true y reinicia, o POST /api/setup con SETUP_TOKEN"
          : "Base lista. Prueba login admin / inventario2026",
  });
}

/**
 * Crea/actualiza admin sin Terminal.
 * Requiere SETUP_TOKEN en .env y el mismo valor en header x-setup-token o body.token
 */
export async function POST(request: NextRequest) {
  if (!setupEnabled()) {
    return NextResponse.json(
      {
        error:
          "Setup deshabilitado. Agrega SETUP_TOKEN=clave-larga en .env y reinicia.",
      },
      { status: 403 }
    );
  }

  let body: {
    token?: string;
    username?: string;
    password?: string;
    name?: string;
  } = {};
  try {
    body = await request.json();
  } catch {
    body = {};
  }

  if (!tokenOk(request, body.token)) {
    return NextResponse.json({ error: "Token inválido" }, { status: 401 });
  }

  if (!process.env.AUTH_SECRET) {
    return NextResponse.json(
      { error: "Falta AUTH_SECRET en el servidor" },
      { status: 500 }
    );
  }

  const username = String(body.username || "admin").trim() || "admin";
  const password = String(body.password || "inventario2026");
  const name = String(body.name || "Administrador");

  try {
    const passwordHash = await bcrypt.hash(password, 10);
    const user = await prisma.user.upsert({
      where: { username },
      create: { username, passwordHash, name },
      update: { passwordHash, name },
    });

    return NextResponse.json({
      ok: true,
      user: { id: user.id, username: user.username, name: user.name },
      message:
        "Usuario listo. Quita SETUP_TOKEN del .env, reinicia, e inicia sesión.",
    });
  } catch (error) {
    console.error("POST /api/setup", error);
    const msg = error instanceof Error ? error.message : "Error";
    return NextResponse.json(
      {
        error:
          "No se pudo crear usuario. ¿Corriste migrate? Reinicia con app.js. " +
          msg.slice(0, 160),
      },
      { status: 500 }
    );
  }
}
