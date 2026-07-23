"use client";

import { FormEvent, Suspense, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/Button";
import { Field, Input } from "@/components/FormFields";

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError("");

    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username, password }),
        credentials: "same-origin",
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(json.error || "Error al iniciar sesión");
      }

      const next = searchParams.get("next");
      const target =
        next && next.startsWith("/") && !next.startsWith("//") ? next : "/";

      // Navegación completa para que el middleware lea la cookie nueva
      window.location.assign(target);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error al iniciar sesión");
      setLoading(false);
    }
  }

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

      <form onSubmit={onSubmit} className="space-y-4">
        <Field label="Usuario" htmlFor="username">
          <Input
            id="username"
            name="username"
            autoComplete="username"
            required
            value={username}
            onChange={(e) => setUsername(e.target.value)}
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
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
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

        <Button type="submit" disabled={loading} className="w-full">
          {loading ? "Ingresando…" : "Ingresar"}
        </Button>

        <p className="text-center text-xs text-ink-muted">
          Usuario por defecto: <span className="font-medium text-ink">admin</span>{" "}
          · contraseña:{" "}
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
