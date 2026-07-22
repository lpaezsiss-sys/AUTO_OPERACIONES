"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/Button";
import { Field, Input, Textarea } from "@/components/FormFields";
import { Modal } from "@/components/Modal";
import { formatCurrency, formatNumber } from "@/lib/format";

type Product = {
  id: string;
  code: string;
  name: string;
  description: string;
  stock: number;
  averageUnitCost: number;
};

const emptyForm = {
  code: "",
  name: "",
  description: "",
  stock: "0",
  averageUnitCost: "0",
};

export default function ArticulosPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Product | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState("");

  const load = useCallback(async () => {
    try {
      const res = await fetch("/api/products");
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Error al listar");
      setProducts(json);
      setError("");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Error al listar");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  function openCreate() {
    setEditing(null);
    setForm(emptyForm);
    setFormError("");
    setModalOpen(true);
  }

  function openEdit(product: Product) {
    setEditing(product);
    setForm({
      code: product.code,
      name: product.name,
      description: product.description,
      stock: String(product.stock),
      averageUnitCost: String(product.averageUnitCost),
    });
    setFormError("");
    setModalOpen(true);
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    setFormError("");

    try {
      const payload = editing
        ? {
            code: form.code,
            name: form.name,
            description: form.description,
          }
        : {
            code: form.code,
            name: form.name,
            description: form.description,
            stock: Number(form.stock) || 0,
            averageUnitCost: Number(form.averageUnitCost) || 0,
          };

      const res = await fetch(
        editing ? `/api/products/${editing.id}` : "/api/products",
        {
          method: editing ? "PUT" : "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        }
      );
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Error al guardar");

      setModalOpen(false);
      await load();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : "Error al guardar");
    } finally {
      setSaving(false);
    }
  }

  async function onDelete(product: Product) {
    if (!confirm(`¿Eliminar el artículo "${product.name}"?`)) return;
    try {
      const res = await fetch(`/api/products/${product.id}`, {
        method: "DELETE",
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Error al eliminar");
      await load();
    } catch (err) {
      alert(err instanceof Error ? err.message : "Error al eliminar");
    }
  }

  return (
    <div>
      <PageHeader
        title="Artículos"
        description="Crea y edita productos. El stock y el CUP se actualizan con documentos de entrada y salida."
        action={<Button onClick={openCreate}>Nuevo artículo</Button>}
      />

      {loading ? (
        <p className="text-ink-muted">Cargando artículos…</p>
      ) : error ? (
        <p className="rounded-md border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
          {error}
        </p>
      ) : (
        <div className="animate-fade-up overflow-hidden rounded-xl border border-border/80 bg-surface shadow-[var(--shadow)]">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-left text-sm">
              <thead className="bg-bg-deep/60 text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                  <th className="px-4 py-3 font-semibold">Código</th>
                  <th className="px-4 py-3 font-semibold">Nombre</th>
                  <th className="px-4 py-3 font-semibold">Descripción</th>
                  <th className="px-4 py-3 font-semibold text-right">Stock</th>
                  <th className="px-4 py-3 font-semibold text-right">CUP</th>
                  <th className="px-4 py-3 font-semibold text-right">Acciones</th>
                </tr>
              </thead>
              <tbody>
                {products.length === 0 ? (
                  <tr>
                    <td
                      colSpan={6}
                      className="px-4 py-10 text-center text-ink-muted"
                    >
                      Aún no hay artículos registrados.
                    </td>
                  </tr>
                ) : (
                  products.map((p) => (
                    <tr
                      key={p.id}
                      className="border-t border-border/70 transition-colors hover:bg-accent-soft/40"
                    >
                      <td className="px-4 py-3 font-mono text-xs">{p.code}</td>
                      <td className="px-4 py-3 font-medium">{p.name}</td>
                      <td className="max-w-[240px] truncate px-4 py-3 text-ink-muted">
                        {p.description || "—"}
                      </td>
                      <td className="px-4 py-3 text-right tabular-nums">
                        {formatNumber(p.stock)}
                      </td>
                      <td className="px-4 py-3 text-right tabular-nums">
                        {formatCurrency(p.averageUnitCost)}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex justify-end gap-1">
                          <Button
                            type="button"
                            variant="ghost"
                            className="!px-2 !py-1"
                            onClick={() => openEdit(p)}
                          >
                            Editar
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            className="!px-2 !py-1 text-danger"
                            onClick={() => onDelete(p)}
                          >
                            Eliminar
                          </Button>
                        </div>
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
        open={modalOpen}
        title={editing ? "Editar artículo" : "Nuevo artículo"}
        onClose={() => setModalOpen(false)}
      >
        <form onSubmit={onSubmit} className="space-y-4">
          <Field label="Código" htmlFor="code">
            <Input
              id="code"
              required
              value={form.code}
              onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))}
              placeholder="SKU-001"
            />
          </Field>
          <Field label="Nombre" htmlFor="name">
            <Input
              id="name"
              required
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              placeholder="Nombre del producto"
            />
          </Field>
          <Field label="Descripción" htmlFor="description">
            <Textarea
              id="description"
              value={form.description}
              onChange={(e) =>
                setForm((f) => ({ ...f, description: e.target.value }))
              }
              placeholder="Detalle opcional"
            />
          </Field>

          {!editing ? (
            <div className="grid grid-cols-2 gap-3">
              <Field label="Stock inicial" htmlFor="stock">
                <Input
                  id="stock"
                  type="number"
                  min="0"
                  step="any"
                  value={form.stock}
                  onChange={(e) =>
                    setForm((f) => ({ ...f, stock: e.target.value }))
                  }
                />
              </Field>
              <Field label="CUP inicial" htmlFor="cup">
                <Input
                  id="cup"
                  type="number"
                  min="0"
                  step="any"
                  value={form.averageUnitCost}
                  onChange={(e) =>
                    setForm((f) => ({
                      ...f,
                      averageUnitCost: e.target.value,
                    }))
                  }
                />
              </Field>
            </div>
          ) : (
            <p className="rounded-md bg-bg-deep px-3 py-2 text-xs text-ink-muted">
              Stock: {formatNumber(editing.stock)} · CUP:{" "}
              {formatCurrency(editing.averageUnitCost)}. Estos valores solo
              cambian con documentos de entrada/salida.
            </p>
          )}

          {formError ? (
            <p className="text-sm text-danger">{formError}</p>
          ) : null}

          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setModalOpen(false)}
            >
              Cancelar
            </Button>
            <Button type="submit" disabled={saving}>
              {saving ? "Guardando…" : editing ? "Guardar cambios" : "Crear"}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
