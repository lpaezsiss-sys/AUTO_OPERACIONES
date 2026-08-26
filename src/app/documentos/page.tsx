"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/Button";
import { Field, Input, Select } from "@/components/FormFields";
import { formatCurrency, formatNumber } from "@/lib/format";
import { apiFetch } from "@/lib/apiFetch";
import { calculateNewAverageCost } from "@/lib/inventory";

type Product = {
  id: string;
  code: string;
  name: string;
  stock: number;
  averageUnitCost: number;
};

type DocType = "ENTRADA" | "SALIDA";

type LineDraft = {
  key: string;
  productId: string;
  quantity: string;
  unitPrice: string;
};

function newLine(productId = ""): LineDraft {
  return {
    key: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    productId,
    quantity: "",
    unitPrice: "",
  };
}

export default function DocumentosPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [type, setType] = useState<DocType>("ENTRADA");
  const [documentNumber, setDocumentNumber] = useState("");
  const [lines, setLines] = useState<LineDraft[]>([newLine()]);
  const [date, setDate] = useState(() => {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    return now.toISOString().slice(0, 16);
  });
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{
    type: "ok" | "error";
    text: string;
  } | null>(null);

  const loadProducts = useCallback(async () => {
    const res = await apiFetch("/api/products");
    const json = await res.json();
    if (res.ok) {
      setProducts(json);
      setLines((prev) => {
        if (prev.length === 1 && !prev[0].productId && json.length) {
          return [{ ...prev[0], productId: json[0].id }];
        }
        return prev;
      });
    }
  }, []);

  useEffect(() => {
    loadProducts();
  }, [loadProducts]);

  const previews = useMemo(() => {
    return lines.map((line) => {
      const product = products.find((p) => p.id === line.productId);
      if (!product) return null;
      const qty = Number(line.quantity) || 0;
      const price = Number(line.unitPrice) || 0;
      if (type === "ENTRADA") {
        return {
          product,
          qty,
          newStock: product.stock + qty,
          newCup:
            qty > 0
              ? calculateNewAverageCost(
                  product.stock,
                  product.averageUnitCost,
                  qty,
                  price
                )
              : product.averageUnitCost,
        };
      }
      return {
        product,
        qty,
        newStock: Math.max(0, product.stock - qty),
        newCup: product.averageUnitCost,
      };
    });
  }, [lines, products, type]);

  function updateLine(key: string, patch: Partial<LineDraft>) {
    setLines((prev) =>
      prev.map((l) => (l.key === key ? { ...l, ...patch } : l))
    );
  }

  function addLine() {
    const used = new Set(lines.map((l) => l.productId).filter(Boolean));
    const nextProduct =
      products.find((p) => !used.has(p.id))?.id || products[0]?.id || "";
    setLines((prev) => [...prev, newLine(nextProduct)]);
  }

  function removeLine(key: string) {
    setLines((prev) => (prev.length <= 1 ? prev : prev.filter((l) => l.key !== key)));
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    setMessage(null);

    try {
      const payloadLines = lines.map((l) => ({
        productId: l.productId,
        quantity: Number(l.quantity),
        unitPrice: type === "ENTRADA" ? Number(l.unitPrice) || 0 : 0,
      }));

      const res = await apiFetch("/api/movements", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          type,
          documentNumber,
          date: new Date(date).toISOString(),
          lines: payloadLines,
        }),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Error al registrar");

      const count = json.count ?? payloadLines.length;
      setMessage({
        type: "ok",
        text:
          type === "ENTRADA"
            ? `Factura ${documentNumber}: ${count} ítem(s) registrados. Stock y CUP actualizados.`
            : `Guía ${documentNumber}: ${count} ítem(s) registrados. Stock disminuido.`,
      });
      setDocumentNumber("");
      setLines([newLine(products[0]?.id || "")]);
      await loadProducts();
    } catch (err) {
      setMessage({
        type: "error",
        text: err instanceof Error ? err.message : "Error al registrar",
      });
    } finally {
      setSaving(false);
    }
  }

  return (
    <div>
      <PageHeader
        title="Documentos"
        description="Ingresa facturas de compra (entrada) o guías de despacho/venta (salida). Puedes agregar varios productos en el mismo documento."
      />

      <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <form
          onSubmit={onSubmit}
          className="animate-fade-up space-y-5 rounded-xl border border-border/80 bg-surface p-5 shadow-[var(--shadow)] sm:p-6"
        >
          <div className="flex gap-2 rounded-lg bg-bg-deep p-1">
            {(
              [
                { id: "ENTRADA", label: "Ingreso / Compra" },
                { id: "SALIDA", label: "Salida / Rebaja" },
              ] as const
            ).map((opt) => (
              <button
                key={opt.id}
                type="button"
                onClick={() => setType(opt.id)}
                className={`flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all ${
                  type === opt.id
                    ? opt.id === "ENTRADA"
                      ? "bg-entrada text-white shadow-sm"
                      : "bg-salida text-white shadow-sm"
                    : "text-ink-muted hover:text-ink"
                }`}
              >
                {opt.label}
              </button>
            ))}
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="N° Documento" htmlFor="doc">
              <Input
                id="doc"
                required
                value={documentNumber}
                onChange={(e) => setDocumentNumber(e.target.value)}
                placeholder={type === "ENTRADA" ? "FAC-00123" : "GD-00456"}
              />
            </Field>
            <Field label="Fecha" htmlFor="date">
              <Input
                id="date"
                type="datetime-local"
                required
                value={date}
                onChange={(e) => setDate(e.target.value)}
              />
            </Field>
          </div>

          <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
              <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                Ítems del documento ({lines.length})
              </p>
              <Button
                type="button"
                variant="ghost"
                className="!px-3 !py-1.5"
                onClick={addLine}
                disabled={products.length === 0}
              >
                + Agregar ítem
              </Button>
            </div>

            {lines.map((line, index) => {
              const product = products.find((p) => p.id === line.productId);
              return (
                <div
                  key={line.key}
                  className="space-y-3 rounded-lg border border-border/70 bg-bg-deep/40 p-3"
                >
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-medium text-ink">
                      Ítem {index + 1}
                    </p>
                    {lines.length > 1 ? (
                      <button
                        type="button"
                        onClick={() => removeLine(line.key)}
                        className="text-xs font-medium text-danger hover:underline"
                      >
                        Quitar
                      </button>
                    ) : null}
                  </div>

                  <Field label="Producto" htmlFor={`product-${line.key}`}>
                    <Select
                      id={`product-${line.key}`}
                      required
                      value={line.productId}
                      onChange={(e) =>
                        updateLine(line.key, { productId: e.target.value })
                      }
                    >
                      {products.length === 0 ? (
                        <option value="">Sin productos — crea uno primero</option>
                      ) : (
                        products.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.code} — {p.name} (stock: {formatNumber(p.stock)})
                          </option>
                        ))
                      )}
                    </Select>
                  </Field>

                  <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Cantidad" htmlFor={`qty-${line.key}`}>
                      <Input
                        id={`qty-${line.key}`}
                        type="number"
                        min="0.0001"
                        step="any"
                        required
                        value={line.quantity}
                        onChange={(e) =>
                          updateLine(line.key, { quantity: e.target.value })
                        }
                      />
                    </Field>
                    {type === "ENTRADA" ? (
                      <Field
                        label="Precio unitario compra"
                        htmlFor={`price-${line.key}`}
                      >
                        <Input
                          id={`price-${line.key}`}
                          type="number"
                          min="0"
                          step="any"
                          required
                          value={line.unitPrice}
                          onChange={(e) =>
                            updateLine(line.key, { unitPrice: e.target.value })
                          }
                        />
                      </Field>
                    ) : (
                      <Field label="CUP (sin cambio)" htmlFor={`cup-${line.key}`}>
                        <Input
                          id={`cup-${line.key}`}
                          readOnly
                          value={
                            product
                              ? formatCurrency(product.averageUnitCost)
                              : "—"
                          }
                        />
                      </Field>
                    )}
                  </div>
                </div>
              );
            })}
          </div>

          {message ? (
            <p
              className={`rounded-md px-3 py-2 text-sm ${
                message.type === "ok"
                  ? "bg-accent-soft text-accent"
                  : "bg-danger/10 text-danger"
              }`}
            >
              {message.text}
            </p>
          ) : null}

          <Button
            type="submit"
            disabled={saving || products.length === 0}
            className="w-full sm:w-auto"
          >
            {saving
              ? "Registrando…"
              : type === "ENTRADA"
                ? "Registrar factura de compra"
                : "Registrar guía de despacho"}
          </Button>
        </form>

        <aside className="animate-fade-up stagger-2 space-y-4">
          <div className="rounded-xl border border-border/80 bg-surface p-5 shadow-[var(--shadow)]">
            <h2 className="font-[family-name:var(--font-fraunces)] text-lg font-semibold">
              Vista previa
            </h2>
            {previews.some(Boolean) ? (
              <ul className="mt-4 space-y-4">
                {previews.map((preview, idx) =>
                  preview ? (
                    <li
                      key={lines[idx].key}
                      className="rounded-lg border border-border/60 p-3 text-sm"
                    >
                      <p className="font-medium text-ink">
                        {preview.product.code} — {preview.product.name}
                      </p>
                      <dl className="mt-2 space-y-1.5">
                        <div className="flex justify-between gap-3">
                          <dt className="text-ink-muted">Stock actual</dt>
                          <dd className="tabular-nums">
                            {formatNumber(preview.product.stock)}
                          </dd>
                        </div>
                        <div className="flex justify-between gap-3">
                          <dt className="text-ink-muted">
                            Stock {type === "ENTRADA" ? "nuevo" : "resultante"}
                          </dt>
                          <dd className="font-medium tabular-nums">
                            {formatNumber(preview.newStock)}
                          </dd>
                        </div>
                        <div className="flex justify-between gap-3">
                          <dt className="text-ink-muted">
                            {type === "ENTRADA" ? "Nuevo CUP" : "CUP"}
                          </dt>
                          <dd className="font-semibold tabular-nums text-accent">
                            {formatCurrency(preview.newCup)}
                          </dd>
                        </div>
                      </dl>
                    </li>
                  ) : null
                )}
              </ul>
            ) : (
              <p className="mt-3 text-sm text-ink-muted">
                Agrega productos para ver el impacto.
              </p>
            )}
          </div>

          <div className="rounded-xl border border-dashed border-border bg-surface/60 p-5 text-sm text-ink-muted">
            {type === "ENTRADA" ? (
              <p>
                El CUP se recalcula por ítem con promedio ponderado: (Stock × CUP
                + Qty × Precio) ÷ (Stock + Qty).
              </p>
            ) : (
              <p>
                La salida disminuye el stock de cada ítem. El CUP se mantiene
                igual.
              </p>
            )}
          </div>
        </aside>
      </div>
    </div>
  );
}
