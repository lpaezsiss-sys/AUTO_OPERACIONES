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
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Error al iniciar sesión");

      const next = searchParams.get("next");
      const target =
        next && next.startsWith("/") && !next.startsWith("//") ? next : "/";
      router.replace(target);
      router.refresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Error al iniciar sesión");
    } finally {
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
            placeholder="usuario"
          />
        </Field>
        <Field label="Contraseña" htmlFor="password">
          <Input
            id="password"
            name="password"
            type="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="••••••••"
          />
        </Field>

        {error ? (
          <p className="rounded-md bg-danger/10 px-3 py-2 text-sm text-danger">
            {error}
          </p>
        ) : null}

        <Button type="submit" disabled={loading} className="w-full">
          {loading ? "Ingresando…" : "Ingresar"}
        </Button>
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
