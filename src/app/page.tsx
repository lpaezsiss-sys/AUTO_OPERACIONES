"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/Button";
import { formatCurrency, formatNumber } from "@/lib/format";

type DashboardItem = {
  id: string;
  code: string;
  name: string;
  description: string;
  stock: number;
  averageUnitCost: number;
  totalValue: number;
};

type DashboardData = {
  items: DashboardItem[];
  summary: {
    totalSku: number;
    totalUnits: number;
    totalInventoryValue: number;
    entradas: number;
    salidas: number;
  };
};

export default function DashboardPage() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    async function load() {
      try {
        const res = await fetch("/api/dashboard");
        const json = await res.json();
        if (!res.ok) throw new Error(json.error || "Error al cargar");
        if (!cancelled) setData(json);
      } catch (e) {
        if (!cancelled) {
          setError(e instanceof Error ? e.message : "Error al cargar");
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    load();
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div>
      <PageHeader
        title="Dashboard"
        description="Stock actual, costo unitario promedio y valor total del inventario."
        action={
          <Link href="/documentos">
            <Button type="button">Registrar documento</Button>
          </Link>
        }
      />

      {loading ? (
        <p className="animate-fade-in text-ink-muted">Cargando inventario…</p>
      ) : error ? (
        <p className="rounded-md border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
          {error}
        </p>
      ) : data ? (
        <>
          <div className="mb-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {[
              {
                label: "Valor inventario",
                value: formatCurrency(data.summary.totalInventoryValue),
              },
              {
                label: "SKUs",
                value: formatNumber(data.summary.totalSku, 0),
              },
              {
                label: "Unidades",
                value: formatNumber(data.summary.totalUnits),
              },
              {
                label: "Movimientos",
                value: `${data.summary.entradas}↑ · ${data.summary.salidas}↓`,
              },
            ].map((card, i) => (
              <div
                key={card.label}
                className={`animate-fade-up stagger-${i + 1} rounded-xl border border-border/80 bg-surface/90 px-4 py-4 shadow-[var(--shadow)]`}
              >
                <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                  {card.label}
                </p>
                <p className="mt-2 font-[family-name:var(--font-fraunces)] text-2xl font-semibold text-ink">
                  {card.value}
                </p>
              </div>
            ))}
          </div>

          <div className="animate-fade-up stagger-2 overflow-hidden rounded-xl border border-border/80 bg-surface shadow-[var(--shadow)]">
            <div className="border-b border-border px-4 py-3">
              <h2 className="font-[family-name:var(--font-fraunces)] text-lg font-semibold">
                Inventario general
              </h2>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[640px] text-left text-sm">
                <thead className="bg-bg-deep/60 text-xs uppercase tracking-wide text-ink-muted">
                  <tr>
                    <th className="px-4 py-3 font-semibold">Código</th>
                    <th className="px-4 py-3 font-semibold">Producto</th>
                    <th className="px-4 py-3 font-semibold text-right">Stock</th>
                    <th className="px-4 py-3 font-semibold text-right">CUP</th>
                    <th className="px-4 py-3 font-semibold text-right">
                      Valor total
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data.items.length === 0 ? (
                    <tr>
                      <td
                        colSpan={5}
                        className="px-4 py-10 text-center text-ink-muted"
                      >
                        No hay artículos.{" "}
                        <Link
                          href="/articulos"
                          className="font-medium text-accent hover:underline"
                        >
                          Crear el primero
                        </Link>
                      </td>
                    </tr>
                  ) : (
                    data.items.map((item) => (
                      <tr
                        key={item.id}
                        className="border-t border-border/70 transition-colors hover:bg-accent-soft/40"
                      >
                        <td className="px-4 py-3 font-mono text-xs text-ink-muted">
                          {item.code}
                        </td>
                        <td className="px-4 py-3 font-medium">{item.name}</td>
                        <td className="px-4 py-3 text-right tabular-nums">
                          {formatNumber(item.stock)}
                        </td>
                        <td className="px-4 py-3 text-right tabular-nums">
                          {formatCurrency(item.averageUnitCost)}
                        </td>
                        <td className="px-4 py-3 text-right font-medium tabular-nums">
                          {formatCurrency(item.totalValue)}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
                {data.items.length > 0 ? (
                  <tfoot>
                    <tr className="border-t-2 border-border bg-bg-deep/40">
                      <td
                        colSpan={4}
                        className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-ink-muted"
                      >
                        Total inventario
                      </td>
                      <td className="px-4 py-3 text-right font-[family-name:var(--font-fraunces)] text-base font-semibold tabular-nums">
                        {formatCurrency(data.summary.totalInventoryValue)}
                      </td>
                    </tr>
                  </tfoot>
                ) : null}
              </table>
            </div>
          </div>
        </>
      ) : null}
    </div>
  );
}
