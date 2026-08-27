"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/Button";
import { Field, Input, Select } from "@/components/FormFields";
import { Modal } from "@/components/Modal";
import { formatCurrency, formatDate, formatNumber } from "@/lib/format";
import { downloadMovementsCsv } from "@/lib/exportCsv";
import { apiFetch } from "@/lib/apiFetch";

type Movement = {
  id: string;
  type: "ENTRADA" | "SALIDA";
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

type ProductOption = {
  id: string;
  code: string;
  name: string;
};

type EditForm = {
  documentNumber: string;
  date: string;
  quantity: string;
  unitPrice: string;
};

function toDateInput(iso: string) {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

export default function MovimientosPage() {
  const [movements, setMovements] = useState<Movement[]>([]);
  const [products, setProducts] = useState<ProductOption[]>([]);
  const [typeFilter, setTypeFilter] = useState<"ALL" | "ENTRADA" | "SALIDA">(
    "ALL"
  );
  const [productId, setProductId] = useState("ALL");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [editing, setEditing] = useState<Movement | null>(null);
  const [editForm, setEditForm] = useState<EditForm>({
    documentNumber: "",
    date: "",
    quantity: "",
    unitPrice: "",
  });
  const [editError, setEditError] = useState("");
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    let cancelled = false;
    async function loadProducts() {
      try {
        const res = await apiFetch("/api/products");
        const json = await res.json();
        if (!res.ok) throw new Error(json.error || "Error al cargar productos");
        if (!cancelled) {
          setProducts(
            (json as ProductOption[])
              .map((p) => ({ id: p.id, code: p.code, name: p.name }))
              .sort((a, b) =>
                a.code.localeCompare(b.code, "es", { numeric: true })
              )
          );
        }
      } catch {
        // La tabla de movimientos puede seguir; el select quedará vacío
      }
    }
    loadProducts();
    return () => {
      cancelled = true;
    };
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (typeFilter !== "ALL") params.set("type", typeFilter);
      if (productId !== "ALL") params.set("productId", productId);
      const qs = params.toString();
      const res = await apiFetch(`/api/movements${qs ? `?${qs}` : ""}`);
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Error al listar");
      setMovements(json);
      setError("");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Error al listar");
    } finally {
      setLoading(false);
    }
  }, [typeFilter, productId]);

  useEffect(() => {
    load();
  }, [load]);

  const selectedProduct = useMemo(
    () => products.find((p) => p.id === productId) ?? null,
    [products, productId]
  );

  const summary = useMemo(() => {
    let entradasQty = 0;
    let salidasQty = 0;
    let entradasValue = 0;
    let salidasValue = 0;
    for (const m of movements) {
      const line = m.quantity * m.unitPrice;
      if (m.type === "ENTRADA") {
        entradasQty += m.quantity;
        entradasValue += line;
      } else {
        salidasQty += m.quantity;
        salidasValue += line;
      }
    }
    return {
      count: movements.length,
      entradasQty,
      salidasQty,
      entradasValue,
      salidasValue,
    };
  }, [movements]);

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
    setSaving(true);
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
      await load();
    } catch (err) {
      setEditError(err instanceof Error ? err.message : "Error al guardar");
    } finally {
      setSaving(false);
    }
  }

  async function onDelete(m: Movement) {
    const label = `${m.type === "ENTRADA" ? "entrada" : "salida"} ${m.documentNumber} — ${m.product.code}`;
    if (
      !confirm(
        `¿Eliminar el movimiento de ${label}?\n\nSe recalculará el stock y el CUP del artículo.`
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
      await load();
    } catch (err) {
      alert(err instanceof Error ? err.message : "Error al eliminar");
    }
  }

  function handleExport() {
    if (movements.length === 0) return;
    const stamp = new Date().toISOString().slice(0, 10);
    const parts = ["movimientos", stamp];
    if (selectedProduct) parts.push(selectedProduct.code);
    if (typeFilter !== "ALL") parts.push(typeFilter.toLowerCase());

    downloadMovementsCsv(
      movements.map((m) => ({
        date: new Date(m.date).toISOString().slice(0, 10),
        type: m.type === "ENTRADA" ? "Entrada" : "Salida",
        documentNumber: m.documentNumber,
        productCode: m.product.code,
        productName: m.product.name,
        quantity: m.quantity,
        unitPrice: m.unitPrice,
        lineTotal: m.quantity * m.unitPrice,
      })),
      parts.join("-")
    );
  }

  return (
    <div>
      <PageHeader
        title="Movimientos"
        description="Historial de entradas y salidas. Edita o elimina un registro si hubo error de digitación; el stock y el CUP se recalculan solos."
        action={
          <Button
            type="button"
            variant="secondary"
            onClick={handleExport}
            disabled={movements.length === 0 || loading}
          >
            Exportar informe CSV
          </Button>
        }
      />

      <div className="mb-4 animate-fade-up rounded-xl border border-border/80 bg-surface p-4 shadow-[var(--shadow)]">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1.4fr_0.8fr_auto]">
          <div>
            <label
              htmlFor="product-filter"
              className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-ink-muted"
            >
              Historial por artículo
            </label>
            <Select
              id="product-filter"
              value={productId}
              onChange={(e) => setProductId(e.target.value)}
              aria-label="Filtrar por artículo"
            >
              <option value="ALL">Todos los artículos</option>
              {products.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.code} — {p.name}
                </option>
              ))}
            </Select>
          </div>
          <div>
            <label
              htmlFor="type-filter"
              className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-ink-muted"
            >
              Tipo de movimiento
            </label>
            <Select
              id="type-filter"
              value={typeFilter}
              onChange={(e) =>
                setTypeFilter(e.target.value as "ALL" | "ENTRADA" | "SALIDA")
              }
              aria-label="Filtrar por tipo"
            >
              <option value="ALL">Todos</option>
              <option value="ENTRADA">Entradas</option>
              <option value="SALIDA">Salidas</option>
            </Select>
          </div>
          <div className="flex items-end">
            <Button
              type="button"
              variant="ghost"
              className="w-full sm:w-auto"
              onClick={() => {
                setProductId("ALL");
                setTypeFilter("ALL");
              }}
              disabled={productId === "ALL" && typeFilter === "ALL"}
            >
              Limpiar filtros
            </Button>
          </div>
        </div>

        {!loading && !error ? (
          <div className="mt-4 flex flex-wrap gap-x-6 gap-y-2 border-t border-border/70 pt-3 text-sm text-ink-muted">
            {selectedProduct ? (
              <p>
                Artículo:{" "}
                <span className="font-medium text-ink">
                  {selectedProduct.code} — {selectedProduct.name}
                </span>
              </p>
            ) : null}
            <p>
              Movimientos:{" "}
              <span className="font-medium tabular-nums text-ink">
                {formatNumber(summary.count, 0)}
              </span>
            </p>
            <p>
              Entradas:{" "}
              <span className="font-medium tabular-nums text-entrada">
                {formatNumber(summary.entradasQty)}
              </span>
              <span className="mx-1">·</span>
              <span className="tabular-nums">
                {formatCurrency(summary.entradasValue)}
              </span>
            </p>
            <p>
              Salidas:{" "}
              <span className="font-medium tabular-nums text-salida">
                {formatNumber(summary.salidasQty)}
              </span>
              <span className="mx-1">·</span>
              <span className="tabular-nums">
                {formatCurrency(summary.salidasValue)}
              </span>
            </p>
          </div>
        ) : null}
      </div>

      {loading ? (
        <p className="text-ink-muted">Cargando movimientos…</p>
      ) : error ? (
        <p className="rounded-md border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
          {error}
        </p>
      ) : (
        <div className="animate-fade-up overflow-hidden rounded-xl border border-border/80 bg-surface shadow-[var(--shadow)]">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[860px] text-left text-sm">
              <thead className="bg-bg-deep/60 text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                  <th className="px-4 py-3 font-semibold">Fecha</th>
                  <th className="px-4 py-3 font-semibold">Tipo</th>
                  <th className="px-4 py-3 font-semibold">N° Documento</th>
                  <th className="px-4 py-3 font-semibold">Producto</th>
                  <th className="px-4 py-3 font-semibold text-right">Cantidad</th>
                  <th className="px-4 py-3 font-semibold text-right">
                    Precio unit.
                  </th>
                  <th className="px-4 py-3 font-semibold text-right">Total</th>
                  <th className="px-4 py-3 font-semibold text-right">Acciones</th>
                </tr>
              </thead>
              <tbody>
                {movements.length === 0 ? (
                  <tr>
                    <td
                      colSpan={8}
                      className="px-4 py-10 text-center text-ink-muted"
                    >
                      {productId !== "ALL" || typeFilter !== "ALL"
                        ? "No hay movimientos con los filtros seleccionados."
                        : "No hay movimientos registrados."}
                    </td>
                  </tr>
                ) : (
                  movements.map((m) => (
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
                      <td className="px-4 py-3 text-right font-medium tabular-nums">
                        {formatCurrency(m.quantity * m.unitPrice)}
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
      )}

      <Modal
        open={Boolean(editing)}
        title={
          editing
            ? `Corregir ${editing.type === "ENTRADA" ? "entrada" : "salida"}`
            : "Corregir movimiento"
        }
        onClose={() => {
          if (!saving) setEditing(null);
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
                disabled={saving}
                onClick={() => setEditing(null)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? "Guardando…" : "Guardar corrección"}
              </Button>
            </div>
          </form>
        ) : null}
      </Modal>
    </div>
  );
}
