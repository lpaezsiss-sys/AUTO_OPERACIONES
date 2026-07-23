"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/Button";
import { Select } from "@/components/FormFields";
import { formatCurrency, formatDate, formatNumber } from "@/lib/format";
import { downloadMovementsCsv } from "@/lib/exportCsv";

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

export default function MovimientosPage() {
  const [movements, setMovements] = useState<Movement[]>([]);
  const [products, setProducts] = useState<ProductOption[]>([]);
  const [typeFilter, setTypeFilter] = useState<"ALL" | "ENTRADA" | "SALIDA">(
    "ALL"
  );
  const [productId, setProductId] = useState("ALL");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    let cancelled = false;
    async function loadProducts() {
      try {
        const res = await fetch("/api/products");
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
      const res = await fetch(`/api/movements${qs ? `?${qs}` : ""}`);
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
        description="Historial de entradas y salidas. Filtra por artículo o tipo y exporta el informe."
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
            <table className="w-full min-w-[760px] text-left text-sm">
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
                </tr>
              </thead>
              <tbody>
                {movements.length === 0 ? (
                  <tr>
                    <td
                      colSpan={7}
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
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
