"use client";

import { useCallback, useEffect, useState } from "react";
import { PageHeader } from "@/components/PageHeader";
import { Select } from "@/components/FormFields";
import { formatCurrency, formatDate, formatNumber } from "@/lib/format";

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

export default function MovimientosPage() {
  const [movements, setMovements] = useState<Movement[]>([]);
  const [filter, setFilter] = useState<"ALL" | "ENTRADA" | "SALIDA">("ALL");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const qs =
        filter === "ALL" ? "" : `?type=${encodeURIComponent(filter)}`;
      const res = await fetch(`/api/movements${qs}`);
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || "Error al listar");
      setMovements(json);
      setError("");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Error al listar");
    } finally {
      setLoading(false);
    }
  }, [filter]);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <div>
      <PageHeader
        title="Movimientos"
        description="Historial de entradas y salidas: fecha, documento, producto, cantidad y precio."
        action={
          <Select
            value={filter}
            onChange={(e) =>
              setFilter(e.target.value as "ALL" | "ENTRADA" | "SALIDA")
            }
            aria-label="Filtrar por tipo"
          >
            <option value="ALL">Todos</option>
            <option value="ENTRADA">Entradas</option>
            <option value="SALIDA">Salidas</option>
          </Select>
        }
      />

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
                </tr>
              </thead>
              <tbody>
                {movements.length === 0 ? (
                  <tr>
                    <td
                      colSpan={6}
                      className="px-4 py-10 text-center text-ink-muted"
                    >
                      No hay movimientos registrados.
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
