import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { prisma } from "@/lib/prisma";
import { applyPendingMigrations } from "@/lib/apply-migrations";
import { seedInventoryInProcess } from "@/lib/seed-inventory";

export const dynamic = "force-dynamic";
export const revalidate = 0;

function setupEnabled() {
  return Boolean(
    process.env.SETUP_TOKEN && process.env.SETUP_TOKEN.trim().length >= 8
  );
}

function expectedToken() {
  return (process.env.SETUP_TOKEN || "").trim();
}

function tokenOk(request: NextRequest, bodyToken?: string) {
  const expected = expectedToken();
  const header = (request.headers.get("x-setup-token") || "").trim();
  const query = (request.nextUrl.searchParams.get("token") || "").trim();
  const provided = (header || bodyToken || query || "").trim();
  return expected.length >= 8 && provided === expected;
}

/** Estado del servidor (sin secretos). */
export async function GET(request: NextRequest) {
  const hasAuthSecret = Boolean(process.env.AUTH_SECRET);
  const setupReady = setupEnabled();
  let dbOk = false;
  let userCount: number | null = null;
  let dbError: string | null = null;
  let migrated: { applied: string[]; skipped: string[] } | null = null;

  const wantMigrate = request.nextUrl.searchParams.get("migrate") === "1";
  const wantSeed = request.nextUrl.searchParams.get("seed") === "1";
  if (wantMigrate || wantSeed) {
    if (!tokenOk(request)) {
      return NextResponse.json({ error: "Token inválido" }, { status: 401 });
    }
  }

  if (wantMigrate) {
    try {
      migrated = await applyPendingMigrations();
    } catch (error) {
      return NextResponse.json(
        {
          error:
            error instanceof Error
              ? error.message.slice(0, 200)
              : "Error al migrar",
        },
        { status: 500 }
      );
    }
  }

  let seeded: {
    ok: boolean;
    products?: number;
    totalUnits?: number;
    inventoryValue?: number;
    detail?: string;
  } | null = null;

  if (wantSeed) {
    try {
      const result = await seedInventoryInProcess();
      seeded = { ok: true, ...result };
    } catch (error) {
      seeded = {
        ok: false,
        detail: error instanceof Error ? error.message.slice(0, 300) : "error",
      };
    }
  }

  try {
    userCount = await prisma.user.count();
    dbOk = true;
  } catch (error) {
    dbError =
      error instanceof Error
        ? error.message.slice(0, 200)
        : "Error de base de datos";
  }

  return NextResponse.json({
    ok: true,
    hasAuthSecret,
    setupReady,
    dbOk,
    userCount,
    dbError,
    migrated,
    seeded,
    hint: !hasAuthSecret
      ? "Define AUTH_SECRET en .env"
      : !dbOk
        ? "Abre /api/setup?migrate=1&token=TU_SETUP_TOKEN"
        : "Para cargar artículos: /api/setup?seed=1&token=TU_SETUP_TOKEN",
  });
}

/** Migra, crea admin y siembra inventario (sin tsx/esbuild). */
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
    seed?: boolean;
  } = {};
  try {
    body = await request.json();
  } catch {
    body = {};
  }

  if (!tokenOk(request, body.token)) {
    return NextResponse.json(
      {
        error: "Token inválido",
        hint: "Usa el mismo SETUP_TOKEN que está en tu .env del hosting",
      },
      { status: 401 }
    );
  }

  if (!process.env.AUTH_SECRET) {
    return NextResponse.json(
      { error: "Falta AUTH_SECRET en el servidor" },
      { status: 500 }
    );
  }

  let migrated: { applied: string[]; skipped: string[] } | null = null;
  try {
    migrated = await applyPendingMigrations();
  } catch (error) {
    console.error("migrate via setup", error);
    return NextResponse.json(
      {
        error:
          "No se pudieron aplicar migraciones: " +
          (error instanceof Error ? error.message.slice(0, 180) : "error"),
      },
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

    let seeded = null;
    if (body.seed !== false) {
      seeded = await seedInventoryInProcess();
    }

    return NextResponse.json({
      ok: true,
      migrated,
      seeded,
      user: { id: user.id, username: user.username, name: user.name },
      message:
        "Listo. Quita SETUP_TOKEN del .env, pon RUN_SEED=false, reinicia e inicia sesión.",
    });
  } catch (error) {
    console.error("POST /api/setup", error);
    const msg = error instanceof Error ? error.message : "Error";
    return NextResponse.json(
      { error: "No se pudo completar setup. " + msg.slice(0, 180), migrated },
      { status: 500 }
    );
  }
}
