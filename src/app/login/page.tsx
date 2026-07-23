"use client";

import { Suspense, useState } from "react";
import { useSearchParams } from "next/navigation";
import { Button } from "@/components/Button";
import { Field, Input } from "@/components/FormFields";

function LoginForm() {
  const searchParams = useSearchParams();
  const next = searchParams.get("next") || "/";
  const error = searchParams.get("error");
  const [showPassword, setShowPassword] = useState(false);

  return (
    <div className="w-full max-w-md animate-fade-up rounded-xl border border-border/80 bg-surface p-6 shadow-[var(--shadow)] sm:p-8">
      <div className="mb-6 text-center">
        <p className="font-[family-name:var(--font-fraunces)] text-3xl font-semibold tracking-tight text-ink">
          Inventario
        </p>
        <p className="mt-2 text-sm text-ink-muted">
          Inicia sesión para gestionar stock y documentos
        </p>
      </div>

      <form
        action="/api/auth/login"
        method="post"
        className="space-y-4"
        autoComplete="on"
      >
        <input type="hidden" name="next" value={next} />

        <Field label="Usuario" htmlFor="username">
          <Input
            id="username"
            name="username"
            autoComplete="username"
            required
            defaultValue="admin"
            placeholder="admin"
          />
        </Field>

        <Field label="Contraseña" htmlFor="password">
          <div className="relative">
            <Input
              id="password"
              name="password"
              type={showPassword ? "text" : "password"}
              autoComplete="current-password"
              required
              defaultValue="inventario2026"
              placeholder="inventario2026"
              className="pr-24"
            />
            <button
              type="button"
              onClick={() => setShowPassword((v) => !v)}
              className="absolute inset-y-0 right-1 my-1 rounded-md px-3 text-xs font-medium text-ink-muted transition-colors hover:bg-bg-deep hover:text-ink"
              aria-pressed={showPassword}
              aria-label={
                showPassword ? "Ocultar contraseña" : "Mostrar contraseña"
              }
            >
              {showPassword ? "Ocultar" : "Mostrar"}
            </button>
          </div>
        </Field>

        {error ? (
          <p className="rounded-md bg-danger/10 px-3 py-2 text-sm text-danger">
            {error}
          </p>
        ) : null}

        <Button type="submit" className="w-full">
          Ingresar
        </Button>

        <p className="text-center text-xs text-ink-muted">
          Usuario: <span className="font-medium text-ink">admin</span> ·
          Contraseña:{" "}
          <span className="font-medium text-ink">inventario2026</span>
        </p>
      </form>
    </div>
  );
}

export default function LoginPage() {
  return (
    <div className="flex min-h-[70vh] items-center justify-center">
      <Suspense
        fallback={
          <p className="text-sm text-ink-muted">Cargando inicio de sesión…</p>
        }
      >
        <LoginForm />
      </Suspense>
    </div>
  );
}
