"use client";

import { useEffect, useMemo, useState } from "react";
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

type TabId = "general" | "bajo-stock";
type SortKey = "code" | "name" | "stock" | "averageUnitCost" | "totalValue";
type SortDir = "asc" | "desc";

const LOW_STOCK_THRESHOLD = 2;

function compareItems(
  a: DashboardItem,
  b: DashboardItem,
  key: SortKey,
  dir: SortDir
): number {
  const av = a[key];
  const bv = b[key];
  let result = 0;
  if (typeof av === "string" && typeof bv === "string") {
    result = av.localeCompare(bv, "es", { numeric: true, sensitivity: "base" });
  } else {
    result = Number(av) - Number(bv);
  }
  return dir === "asc" ? result : -result;
}

function SortableTh({
  label,
  column,
  sortKey,
  sortDir,
  align = "left",
  onSort,
}: {
  label: string;
  column: SortKey;
  sortKey: SortKey;
  sortDir: SortDir;
  align?: "left" | "right";
  onSort: (key: SortKey) => void;
}) {
  const active = sortKey === column;
  return (
    <th
      className={`px-4 py-3 font-semibold ${align === "right" ? "text-right" : "text-left"}`}
      aria-sort={
        active ? (sortDir === "asc" ? "ascending" : "descending") : "none"
      }
    >
      <button
        type="button"
        onClick={() => onSort(column)}
        className={`inline-flex items-center gap-1.5 transition-colors hover:text-accent ${
          active ? "text-accent" : "text-ink-muted"
        } ${align === "right" ? "flex-row-reverse" : ""}`}
      >
        <span>{label}</span>
        <span className="font-mono text-[10px] opacity-80" aria-hidden>
          {active ? (sortDir === "asc" ? "▲" : "▼") : "◇"}
        </span>
      </button>
    </th>
  );
}

export default function DashboardPage() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState<TabId>("general");
  const [sortKey, setSortKey] = useState<SortKey>("name");
  const [sortDir, setSortDir] = useState<SortDir>("asc");

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

  function handleSort(key: SortKey) {
    if (sortKey === key) {
      setSortDir((d) => (d === "asc" ? "desc" : "asc"));
    } else {
      setSortKey(key);
      setSortDir(key === "name" || key === "code" ? "asc" : "desc");
    }
  }

  const lowStockItems = useMemo(() => {
    if (!data) return [];
    return data.items.filter((item) => item.stock <= LOW_STOCK_THRESHOLD);
  }, [data]);

  const visibleItems = useMemo(() => {
    if (!data) return [];
    const source = tab === "bajo-stock" ? lowStockItems : data.items;
    return [...source].sort((a, b) => compareItems(a, b, sortKey, sortDir));
  }, [data, tab, lowStockItems, sortKey, sortDir]);

  const tableTotalValue = useMemo(
    () => visibleItems.reduce((sum, item) => sum + item.totalValue, 0),
    [visibleItems]
  );

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
                label: "Bajo stock",
                value: formatNumber(lowStockItems.length, 0),
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
            <div className="flex flex-col gap-3 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex gap-1 rounded-lg bg-bg-deep p-1">
                <button
                  type="button"
                  onClick={() => setTab("general")}
                  className={`rounded-md px-3 py-1.5 text-sm font-medium transition-all ${
                    tab === "general"
                      ? "bg-surface text-ink shadow-sm"
                      : "text-ink-muted hover:text-ink"
                  }`}
                >
                  Inventario general
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setTab("bajo-stock");
                    setSortKey("stock");
                    setSortDir("asc");
                  }}
                  className={`rounded-md px-3 py-1.5 text-sm font-medium transition-all ${
                    tab === "bajo-stock"
                      ? "bg-surface text-ink shadow-sm"
                      : "text-ink-muted hover:text-ink"
                  }`}
                >
                  Bajo stock
                  <span
                    className={`ml-2 inline-flex min-w-5 items-center justify-center rounded-md px-1.5 py-0.5 text-xs tabular-nums ${
                      lowStockItems.length > 0
                        ? "bg-salida/15 text-salida"
                        : "bg-border/60 text-ink-muted"
                    }`}
                  >
                    {lowStockItems.length}
                  </span>
                </button>
              </div>
              {tab === "bajo-stock" ? (
                <p className="text-xs text-ink-muted">
                  Artículos con stock ≤ {LOW_STOCK_THRESHOLD}
                </p>
              ) : null}
            </div>

            <div className="overflow-x-auto">
              <table className="w-full min-w-[640px] text-left text-sm">
                <thead className="bg-bg-deep/60 text-xs uppercase tracking-wide">
                  <tr>
                    <SortableTh
                      label="Código"
                      column="code"
                      sortKey={sortKey}
                      sortDir={sortDir}
                      onSort={handleSort}
                    />
                    <SortableTh
                      label="Producto"
                      column="name"
                      sortKey={sortKey}
                      sortDir={sortDir}
                      onSort={handleSort}
                    />
                    <SortableTh
                      label="Stock"
                      column="stock"
                      sortKey={sortKey}
                      sortDir={sortDir}
                      align="right"
                      onSort={handleSort}
                    />
                    <SortableTh
                      label="CUP"
                      column="averageUnitCost"
                      sortKey={sortKey}
                      sortDir={sortDir}
                      align="right"
                      onSort={handleSort}
                    />
                    <SortableTh
                      label="Valor total"
                      column="totalValue"
                      sortKey={sortKey}
                      sortDir={sortDir}
                      align="right"
                      onSort={handleSort}
                    />
                  </tr>
                </thead>
                <tbody>
                  {visibleItems.length === 0 ? (
                    <tr>
                      <td
                        colSpan={5}
                        className="px-4 py-10 text-center text-ink-muted"
                      >
                        {tab === "bajo-stock" ? (
                          "No hay artículos con bajo stock."
                        ) : (
                          <>
                            No hay artículos.{" "}
                            <Link
                              href="/articulos"
                              className="font-medium text-accent hover:underline"
                            >
                              Crear el primero
                            </Link>
                          </>
                        )}
                      </td>
                    </tr>
                  ) : (
                    visibleItems.map((item) => {
                      const isLow = item.stock <= LOW_STOCK_THRESHOLD;
                      return (
                        <tr
                          key={item.id}
                          className="border-t border-border/70 transition-colors hover:bg-accent-soft/40"
                        >
                          <td className="px-4 py-3 font-mono text-xs text-ink-muted">
                            {item.code}
                          </td>
                          <td className="px-4 py-3 font-medium">{item.name}</td>
                          <td
                            className={`px-4 py-3 text-right tabular-nums ${
                              isLow ? "font-semibold text-salida" : ""
                            }`}
                          >
                            {formatNumber(item.stock)}
                          </td>
                          <td className="px-4 py-3 text-right tabular-nums">
                            {formatCurrency(item.averageUnitCost)}
                          </td>
                          <td className="px-4 py-3 text-right font-medium tabular-nums">
                            {formatCurrency(item.totalValue)}
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
                {visibleItems.length > 0 ? (
                  <tfoot>
                    <tr className="border-t-2 border-border bg-bg-deep/40">
                      <td
                        colSpan={4}
                        className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-ink-muted"
                      >
                        {tab === "bajo-stock"
                          ? "Total bajo stock"
                          : "Total inventario"}
                      </td>
                      <td className="px-4 py-3 text-right font-[family-name:var(--font-fraunces)] text-base font-semibold tabular-nums">
                        {formatCurrency(tableTotalValue)}
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
