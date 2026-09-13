"use client";

import { ReactNode, useEffect } from "react";
import { Button } from "./Button";

export function Modal({
  open,
  title,
  children,
  onClose,
}: {
  open: boolean;
  title: string;
  children: ReactNode;
  onClose: () => void;
}) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
      <button
        type="button"
        aria-label="Cerrar"
        className="absolute inset-0 bg-ink/40 animate-fade-in"
        onClick={onClose}
      />
      <div className="relative z-10 w-full max-w-lg animate-fade-up rounded-xl border border-border bg-surface p-5 shadow-[var(--shadow)] sm:p-6">
        <div className="mb-4 flex items-start justify-between gap-3">
          <h2 className="font-[family-name:var(--font-fraunces)] text-xl font-semibold text-ink">
            {title}
          </h2>
          <Button type="button" variant="ghost" onClick={onClose} className="!px-2">
            Cerrar
          </Button>
        </div>
        {children}
      </div>
    </div>
  );
}
