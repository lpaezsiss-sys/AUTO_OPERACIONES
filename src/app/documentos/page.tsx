"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/Button";
import { Field, Input, Select } from "@/components/FormFields";
import { formatCurrency, formatNumber } from "@/lib/format";

type Product = {
  id: string;
  code: string;
  name: string;
  stock: number;
  averageUnitCost: number;
};

type DocType = "ENTRADA" | "SALIDA";

export default function DocumentosPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [type, setType] = useState<DocType>("ENTRADA");
  const [documentNumber, setDocumentNumber] = useState("");
  const [productId, setProductId] = useState("");
  const [quantity, setQuantity] = useState("");
  const [unitPrice, setUnitPrice] = useState("");
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

  const selected = products.find((p) => p.id === productId);

  const loadProducts = useCallback(async () => {
    const res = await fetch("/api/products");
    const json = await res.json();
    if (res.ok) {
      setProducts(json);
      if (json.length && !productId) setProductId(json[0].id);
    }
  }, [productId]);

  useEffect(() => {
    loadProducts();
  }, [loadProducts]);

  const previewCup =
    type === "ENTRADA" && selected && Number(quantity) > 0
      ? (() => {
          const qty = Number(quantity);
          const price = Number(unitPrice) || 0;
          const total = selected.stock + qty;
          if (total <= 0) return selected.averageUnitCost;
          return (
            (selected.stock * selected.averageUnitCost + qty * price) / total
          );
        })()
      : selected?.averageUnitCost ?? 0;

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    setMessage(null);

    try {
      const res = await fetch("/api/movements", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          type,
          documentNumber,
          productId,
          quantity: Number(quantity),
          unitPrice: type === "ENTRADA" ? Number(unitPrice) || 0 : 0,
          date: new Date(date).toISOString(),
        }),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Error al registrar");

      setMessage({
        type: "ok",
        text:
          type === "ENTRADA"
            ? `Factura de compra ${documentNumber} registrada. Stock y CUP actualizados.`
            : `Guía de despacho ${documentNumber} registrada. Stock disminuido.`,
      });
      setDocumentNumber("");
      setQuantity("");
      setUnitPrice("");
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
        description="Ingresa facturas de compra (entrada) o guías de despacho/venta (salida)."
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
                placeholder={
                  type === "ENTRADA" ? "FAC-00123" : "GD-00456"
                }
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

          <Field label="Producto" htmlFor="product">
            <Select
              id="product"
              required
              value={productId}
              onChange={(e) => setProductId(e.target.value)}
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

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Cantidad" htmlFor="qty">
              <Input
                id="qty"
                type="number"
                min="0.0001"
                step="any"
                required
                value={quantity}
                onChange={(e) => setQuantity(e.target.value)}
              />
            </Field>
            {type === "ENTRADA" ? (
              <Field label="Precio unitario compra" htmlFor="price">
                <Input
                  id="price"
                  type="number"
                  min="0"
                  step="any"
                  required
                  value={unitPrice}
                  onChange={(e) => setUnitPrice(e.target.value)}
                />
              </Field>
            ) : (
              <Field label="CUP (sin cambio)" htmlFor="cup-ro">
                <Input
                  id="cup-ro"
                  readOnly
                  value={
                    selected
                      ? formatCurrency(selected.averageUnitCost)
                      : "—"
                  }
                />
              </Field>
            )}
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
            {selected ? (
              <dl className="mt-4 space-y-3 text-sm">
                <div className="flex justify-between gap-4">
                  <dt className="text-ink-muted">Stock actual</dt>
                  <dd className="font-medium tabular-nums">
                    {formatNumber(selected.stock)}
                  </dd>
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-ink-muted">CUP actual</dt>
                  <dd className="font-medium tabular-nums">
                    {formatCurrency(selected.averageUnitCost)}
                  </dd>
                </div>
                <div className="border-t border-border pt-3">
                  <div className="flex justify-between gap-4">
                    <dt className="text-ink-muted">
                      Stock {type === "ENTRADA" ? "nuevo" : "resultante"}
                    </dt>
                    <dd className="font-medium tabular-nums">
                      {formatNumber(
                        type === "ENTRADA"
                          ? selected.stock + (Number(quantity) || 0)
                          : Math.max(
                              0,
                              selected.stock - (Number(quantity) || 0)
                            )
                      )}
                    </dd>
                  </div>
                </div>
                <div className="flex justify-between gap-4">
                  <dt className="text-ink-muted">
                    {type === "ENTRADA" ? "Nuevo CUP" : "CUP (sin cambio)"}
                  </dt>
                  <dd className="font-[family-name:var(--font-fraunces)] text-base font-semibold tabular-nums text-accent">
                    {formatCurrency(previewCup)}
                  </dd>
                </div>
              </dl>
            ) : (
              <p className="mt-3 text-sm text-ink-muted">
                Selecciona un producto para ver el impacto.
              </p>
            )}
          </div>

          <div className="rounded-xl border border-dashed border-border bg-surface/60 p-5 text-sm text-ink-muted">
            {type === "ENTRADA" ? (
              <p>
                El CUP se recalcula con promedio ponderado: (Stock × CUP + Qty ×
                Precio) ÷ (Stock + Qty).
              </p>
            ) : (
              <p>
                La salida disminuye el stock. El costo unitario promedio se
                mantiene igual.
              </p>
            )}
          </div>
        </aside>
      </div>
    </div>
  );
}
