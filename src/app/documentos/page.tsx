"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/Button";
import { Field, Input, Select } from "@/components/FormFields";
import { Modal } from "@/components/Modal";
import { formatCurrency, formatDate, formatNumber } from "@/lib/format";
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

type Movement = {
  id: string;
  type: DocType;
  documentNumber: string;
  quantity: number;
  unitPrice: number;
  date: string;
  product: {
    id: string;
    code: string;
    name: string;
  };
};

type EditForm = {
  documentNumber: string;
  date: string;
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

function toDateInput(iso: string) {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
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

  const [recent, setRecent] = useState<Movement[]>([]);
  const [recentLoading, setRecentLoading] = useState(true);
  const [editing, setEditing] = useState<Movement | null>(null);
  const [editForm, setEditForm] = useState<EditForm>({
    documentNumber: "",
    date: "",
    quantity: "",
    unitPrice: "",
  });
  const [editError, setEditError] = useState("");
  const [editSaving, setEditSaving] = useState(false);

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

  const loadRecent = useCallback(async () => {
    setRecentLoading(true);
    try {
      const res = await apiFetch("/api/movements");
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Error al listar");
      setRecent((json as Movement[]).slice(0, 30));
    } catch {
      setRecent([]);
    } finally {
      setRecentLoading(false);
    }
  }, []);

  useEffect(() => {
    loadProducts();
    loadRecent();
  }, [loadProducts, loadRecent]);

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
    setLines((prev) =>
      prev.length <= 1 ? prev : prev.filter((l) => l.key !== key)
    );
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
      await Promise.all([loadProducts(), loadRecent()]);
    } catch (err) {
      setMessage({
        type: "error",
        text: err instanceof Error ? err.message : "Error al registrar",
      });
    } finally {
      setSaving(false);
    }
  }

  function openEdit(m: Movement) {
    setEditing(m);
    setEditForm({
      documentNumber: m.documentNumber,
      date: toDateInput(m.date),
      quantity: String(m.quantity),
      unitPrice: String(m.unitPrice),
    });
    setEditError("");
  }

  async function onSaveEdit(e: FormEvent) {
    e.preventDefault();
    if (!editing) return;
    setEditSaving(true);
    setEditError("");
    try {
      const payload: Record<string, unknown> = {
        documentNumber: editForm.documentNumber.trim(),
        date: editForm.date,
        quantity: Number(editForm.quantity),
      };
      if (editing.type === "ENTRADA") {
        payload.unitPrice = Number(editForm.unitPrice);
      }

      const res = await apiFetch(`/api/movements/${editing.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Error al guardar");
      setEditing(null);
      await Promise.all([loadProducts(), loadRecent()]);
    } catch (err) {
      setEditError(err instanceof Error ? err.message : "Error al guardar");
    } finally {
      setEditSaving(false);
    }
  }

  async function onDelete(m: Movement) {
    const label = `${m.type === "ENTRADA" ? "entrada" : "salida"} ${m.documentNumber} — ${m.product.code}`;
    if (
      !confirm(
        `¿Eliminar el ítem de ${label}?\n\nSe recalculará el stock y el CUP del artículo.`
      )
    ) {
      return;
    }
    try {
      const res = await apiFetch(`/api/movements/${m.id}`, {
        method: "DELETE",
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Error al eliminar");
      if (editing?.id === m.id) setEditing(null);
      await Promise.all([loadProducts(), loadRecent()]);
    } catch (err) {
      alert(err instanceof Error ? err.message : "Error al eliminar");
    }
  }

  return (
    <div>
      <PageHeader
        title="Documentos"
        description="Ingresa facturas de compra (entrada) o guías de despacho/venta (salida). Más abajo puedes editar o eliminar si hubo error de digitación."
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

      <section className="mt-8 animate-fade-up">
        <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 className="font-[family-name:var(--font-fraunces)] text-xl font-semibold text-ink">
              Corregir digitación
            </h2>
            <p className="mt-1 text-sm text-ink-muted">
              Últimos ítems registrados. Edita N° documento, fecha, cantidad o
              precio; o elimina el registro erróneo.
            </p>
          </div>
          <Button
            type="button"
            variant="ghost"
            onClick={() => loadRecent()}
            disabled={recentLoading}
          >
            Actualizar lista
          </Button>
        </div>

        <div className="overflow-hidden rounded-xl border border-border/80 bg-surface shadow-[var(--shadow)]">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[820px] text-left text-sm">
              <thead className="bg-bg-deep/60 text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                  <th className="px-4 py-3 font-semibold">Fecha</th>
                  <th className="px-4 py-3 font-semibold">Tipo</th>
                  <th className="px-4 py-3 font-semibold">N° Documento</th>
                  <th className="px-4 py-3 font-semibold">Producto</th>
                  <th className="px-4 py-3 font-semibold text-right">Cant.</th>
                  <th className="px-4 py-3 font-semibold text-right">P. unit.</th>
                  <th className="px-4 py-3 font-semibold text-right">Acciones</th>
                </tr>
              </thead>
              <tbody>
                {recentLoading ? (
                  <tr>
                    <td
                      colSpan={7}
                      className="px-4 py-8 text-center text-ink-muted"
                    >
                      Cargando documentos…
                    </td>
                  </tr>
                ) : recent.length === 0 ? (
                  <tr>
                    <td
                      colSpan={7}
                      className="px-4 py-8 text-center text-ink-muted"
                    >
                      Aún no hay documentos para corregir.
                    </td>
                  </tr>
                ) : (
                  recent.map((m) => (
                    <tr
                      key={m.id}
                      className="border-t border-border/70 transition-colors hover:bg-accent-soft/40"
                    >
                      <td className="px-4 py-3 whitespace-nowrap text-ink-muted">
                        {formatDate(m.date)}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={`inline-flex rounded-md px-2 py-0.5 text-xs font-semibold ${
                            m.type === "ENTRADA"
                              ? "bg-entrada/10 text-entrada"
                              : "bg-salida/10 text-salida"
                          }`}
                        >
                          {m.type === "ENTRADA" ? "Entrada" : "Salida"}
                        </span>
                      </td>
                      <td className="px-4 py-3 font-mono text-xs">
                        {m.documentNumber}
                      </td>
                      <td className="px-4 py-3">
                        <span className="font-medium">{m.product.name}</span>
                        <span className="ml-2 font-mono text-xs text-ink-muted">
                          {m.product.code}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right tabular-nums">
                        {formatNumber(m.quantity)}
                      </td>
                      <td className="px-4 py-3 text-right tabular-nums">
                        {formatCurrency(m.unitPrice)}
                      </td>
                      <td className="px-4 py-3 text-right whitespace-nowrap">
                        <Button
                          type="button"
                          variant="ghost"
                          className="!px-2"
                          onClick={() => openEdit(m)}
                        >
                          Editar
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          className="!px-2 text-danger"
                          onClick={() => onDelete(m)}
                        >
                          Eliminar
                        </Button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <Modal
        open={Boolean(editing)}
        title={
          editing
            ? `Corregir ${editing.type === "ENTRADA" ? "entrada" : "salida"}`
            : "Corregir documento"
        }
        onClose={() => {
          if (!editSaving) setEditing(null);
        }}
      >
        {editing ? (
          <form onSubmit={onSaveEdit} className="space-y-4">
            <p className="text-sm text-ink-muted">
              {editing.product.code} — {editing.product.name}. Tras guardar se
              recalcula el stock y el CUP.
            </p>
            <Field label="N° documento" htmlFor="edit-doc">
              <Input
                id="edit-doc"
                value={editForm.documentNumber}
                onChange={(e) =>
                  setEditForm((f) => ({
                    ...f,
                    documentNumber: e.target.value,
                  }))
                }
                required
              />
            </Field>
            <Field label="Fecha" htmlFor="edit-date">
              <Input
                id="edit-date"
                type="date"
                value={editForm.date}
                onChange={(e) =>
                  setEditForm((f) => ({ ...f, date: e.target.value }))
                }
                required
              />
            </Field>
            <Field label="Cantidad" htmlFor="edit-qty">
              <Input
                id="edit-qty"
                type="number"
                min="0.0001"
                step="any"
                value={editForm.quantity}
                onChange={(e) =>
                  setEditForm((f) => ({ ...f, quantity: e.target.value }))
                }
                required
              />
            </Field>
            {editing.type === "ENTRADA" ? (
              <Field label="Precio unitario" htmlFor="edit-price">
                <Input
                  id="edit-price"
                  type="number"
                  min="0"
                  step="any"
                  value={editForm.unitPrice}
                  onChange={(e) =>
                    setEditForm((f) => ({ ...f, unitPrice: e.target.value }))
                  }
                  required
                />
              </Field>
            ) : (
              <p className="text-xs text-ink-muted">
                En salidas el precio unitario es el CUP del momento y no se
                edita aquí.
              </p>
            )}
            {editError ? (
              <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
                {editError}
              </p>
            ) : null}
            <div className="flex flex-wrap justify-end gap-2 pt-1">
              <Button
                type="button"
                variant="ghost"
                disabled={editSaving}
                onClick={() => setEditing(null)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={editSaving}>
                {editSaving ? "Guardando…" : "Guardar corrección"}
              </Button>
            </div>
          </form>
        ) : null}
      </Modal>
    </div>
  );
}
