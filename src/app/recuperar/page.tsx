"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/Button";
import { Field, Input } from "@/components/FormFields";

export default function RecuperarPage() {
  const router = useRouter();
  const [username, setUsername] = useState("");
  const [recoveryToken, setRecoveryToken] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [recoveryEnabled, setRecoveryEnabled] = useState<boolean | null>(null);

  useEffect(() => {
    fetch("/api/auth/reset-password")
      .then((r) => r.json())
      .then((data) => setRecoveryEnabled(Boolean(data.recoveryEnabled)))
      .catch(() => setRecoveryEnabled(false));
  }, []);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);

    if (newPassword !== confirmPassword) {
      setError("Las contraseñas no coinciden");
      return;
    }

    setLoading(true);
    try {
      const res = await fetch("/api/auth/reset-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username, recoveryToken, newPassword }),
      });
      const data = await res.json();
      if (!res.ok) {
        setError(data.error || "No se pudo restablecer");
        return;
      }
      router.push(
        "/login?info=" +
          encodeURIComponent("Contraseña actualizada. Inicia sesión.")
      );
    } catch {
      setError("Error de red al restablecer");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex min-h-[70vh] items-center justify-center px-4">
      <div className="w-full max-w-md animate-fade-up rounded-xl border border-border/80 bg-surface p-6 shadow-[var(--shadow)] sm:p-8">
        <div className="mb-6 text-center">
          <p className="font-[family-name:var(--font-fraunces)] text-3xl font-semibold tracking-tight text-ink">
            Recuperar acceso
          </p>
          <p className="mt-2 text-sm text-ink-muted">
            Usa el código de recuperación del servidor (RECOVERY_TOKEN en el
            archivo .env) para definir una nueva contraseña.
          </p>
        </div>

        {recoveryEnabled === false ? (
          <p className="rounded-md bg-danger/10 px-3 py-2 text-sm text-danger">
            La recuperación no está activa. En cPanel edita{" "}
            <code className="text-xs">.env</code> y agrega{" "}
            <code className="text-xs">RECOVERY_TOKEN=tu-codigo-secreto</code>,
            luego reinicia la app Node.
          </p>
        ) : (
          <form onSubmit={onSubmit} className="space-y-4">
            <Field label="Usuario" htmlFor="username">
              <Input
                id="username"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                autoComplete="username"
                required
                placeholder="Tu usuario"
              />
            </Field>

            <Field label="Código de recuperación" htmlFor="recoveryToken">
              <Input
                id="recoveryToken"
                value={recoveryToken}
                onChange={(e) => setRecoveryToken(e.target.value)}
                autoComplete="off"
                required
                placeholder="RECOVERY_TOKEN del .env"
              />
            </Field>

            <Field label="Nueva contraseña" htmlFor="newPassword">
              <div className="relative">
                <Input
                  id="newPassword"
                  type={showPassword ? "text" : "password"}
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  autoComplete="new-password"
                  required
                  minLength={8}
                  placeholder="Mínimo 8 caracteres"
                  className="pr-24"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((v) => !v)}
                  className="absolute inset-y-0 right-1 my-1 rounded-md px-3 text-xs font-medium text-ink-muted hover:bg-bg-deep hover:text-ink"
                >
                  {showPassword ? "Ocultar" : "Mostrar"}
                </button>
              </div>
            </Field>

            <Field label="Confirmar contraseña" htmlFor="confirmPassword">
              <Input
                id="confirmPassword"
                type={showPassword ? "text" : "password"}
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                autoComplete="new-password"
                required
                minLength={8}
                placeholder="Repite la nueva contraseña"
              />
            </Field>

            {error ? (
              <p className="rounded-md bg-danger/10 px-3 py-2 text-sm text-danger">
                {error}
              </p>
            ) : null}

            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? "Guardando…" : "Restablecer contraseña"}
            </Button>
          </form>
        )}

        <p className="mt-4 text-center text-sm text-ink-muted">
          <Link
            href="/login"
            className="font-medium text-accent underline-offset-2 hover:underline"
          >
            Volver al inicio de sesión
          </Link>
        </p>
      </div>
    </div>
  );
}
