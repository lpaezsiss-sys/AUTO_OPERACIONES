import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { prisma } from "@/lib/prisma";
import {
  attachSessionCookie,
  createSessionToken,
} from "@/lib/auth";
import { absoluteUrl } from "@/lib/request-origin";

function safeNextPath(value: FormDataEntryValue | null): string {
  const raw = String(value ?? "/");
  return raw.startsWith("/") && !raw.startsWith("//") ? raw : "/";
}

function loginErrorMessage(error: unknown): string {
  const msg = error instanceof Error ? error.message : String(error);
  if (msg.includes("AUTH_SECRET")) {
    return "Falta AUTH_SECRET en el servidor (.env)";
  }
  if (
    msg.includes("Can't reach database") ||
    msg.includes("P1001") ||
    msg.includes("P1003") ||
    msg.includes("no such table") ||
    msg.includes("P2021") ||
    msg.includes("does not exist")
  ) {
    return "Base de datos no lista. Pon RUN_SEED=true, reinicia (app.js) o usa /api/setup";
  }
  // Mensaje corto para diagnosticar en hosting sin Terminal
  const short = msg.replace(/\s+/g, " ").slice(0, 140);
  return short ? `Error al iniciar sesión: ${short}` : "Error al iniciar sesión";
}

async function authenticate(username: string, password: string) {
  const user = await prisma.user.findUnique({ where: { username } });
  if (!user) return null;
  const valid = await bcrypt.compare(password, user.passwordHash);
  if (!valid) return null;
  return user;
}

/** JSON API (opcional / pruebas). */
export async function POST(request: NextRequest) {
  const contentType = request.headers.get("content-type") || "";

  try {
    // Formulario HTML clásico → cookie + redirect 303
    if (
      contentType.includes("application/x-www-form-urlencoded") ||
      contentType.includes("multipart/form-data")
    ) {
      const form = await request.formData();
      const username = String(form.get("username") ?? "").trim();
      const password = String(form.get("password") ?? "");
      const next = safeNextPath(form.get("next"));

      if (!username || !password) {
        const url = absoluteUrl(request, "/login");
        url.searchParams.set("error", "Usuario y contraseña son obligatorios");
        return NextResponse.redirect(url, 303);
      }

      const user = await authenticate(username, password);
      if (!user) {
        const url = absoluteUrl(request, "/login");
        url.searchParams.set("error", "Credenciales inválidas");
        url.searchParams.set("next", next);
        return NextResponse.redirect(url, 303);
      }

      const token = await createSessionToken({
        userId: user.id,
        username: user.username,
        name: user.name,
      });

      const response = NextResponse.redirect(
        absoluteUrl(request, next),
        303
      );
      return attachSessionCookie(response, token);
    }

    // JSON
    const body = await request.json();
    const username = String(body.username ?? "").trim();
    const password = String(body.password ?? "");

    if (!username || !password) {
      return NextResponse.json(
        { error: "Usuario y contraseña son obligatorios" },
        { status: 400 }
      );
    }

    const user = await authenticate(username, password);
    if (!user) {
      return NextResponse.json(
        { error: "Credenciales inválidas" },
        { status: 401 }
      );
    }

    const token = await createSessionToken({
      userId: user.id,
      username: user.username,
      name: user.name,
    });

    return attachSessionCookie(
      NextResponse.json({
        user: {
          id: user.id,
          username: user.username,
          name: user.name,
        },
      }),
      token
    );
  } catch (error) {
    console.error("POST /api/auth/login", error);
    const message = loginErrorMessage(error);
    if (
      contentType.includes("application/x-www-form-urlencoded") ||
      contentType.includes("multipart/form-data")
    ) {
      const url = absoluteUrl(request, "/login");
      url.searchParams.set("error", message);
      return NextResponse.redirect(url, 303);
    }
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
