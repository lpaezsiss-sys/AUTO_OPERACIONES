"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { PageHeader } from "@/components/PageHeader";
import { Button } from "@/components/Button";
import { Field, Input, Select } from "@/components/FormFields";
import { formatCurrency, formatNumber } from "@/lib/format";

type DocKind = "COMPRA" | "VENTA";
type Step = "upload" | "review" | "done";
type MatchStatus = "ok" | "review" | "missing";

type CatalogProduct = {
  code: string;
  name: string;
  stock: number;
  cup: number;
};

type ExtractedLine = {
  id: string;
  rawDescription: string;
  productCode: string;
  quantity: string;
  unitPrice: string;
  match: MatchStatus;
  note?: string;
};

const MOCK_CATALOG: CatalogProduct[] = [
  {
    code: "14454",
    name: "Blower S85 Completo",
    stock: 2,
    cup: 185000,
  },
  {
    code: "A08-10100",
    name: "CINTA Doble Fas CMC 10730",
    stock: 40,
    cup: 45756,
  },
  {
    code: "12852-48",
    name: "3x48 P AIR KNIFE SONIC XE",
    stock: 0,
    cup: 1215675,
  },
];

const MOCK_COMPRA_LINES: ExtractedLine[] = [
  {
    id: "c1",
    rawDescription: "BLOWER S85 COMPLETO",
    productCode: "14454",
    quantity: "3",
    unitPrice: "190000",
    match: "ok",
  },
  {
    id: "c2",
    rawDescription: "CINTA DOBLE FAZ CMC 10730",
    productCode: "A08-10100",
    quantity: "12",
    unitPrice: "45000",
    match: "review",
    note: "Descripción similar; confirma el código",
  },
  {
    id: "c3",
    rawDescription: "FILTRO HEPA GENÉRICO",
    productCode: "",
    quantity: "2",
    unitPrice: "22000",
    match: "missing",
    note: "No hay match en catálogo",
  },
];

const MOCK_VENTA_LINES: ExtractedLine[] = [
  {
    id: "v1",
    rawDescription: "CINTA DOBLE FAZ CMC",
    productCode: "A08-10100",
    quantity: "5",
    unitPrice: "62000",
    match: "ok",
    note: "Precio de venta solo informativo; no altera CUP",
  },
  {
    id: "v2",
    rawDescription: "BLOWER S85",
    productCode: "14454",
    quantity: "1",
    unitPrice: "250000",
    match: "ok",
  },
];

function catalogByCode(code: string) {
  return MOCK_CATALOG.find((p) => p.code === code) ?? null;
}

export default function ImportarPdfPrototypePage() {
  const [step, setStep] = useState<Step>("upload");
  const [kind, setKind] = useState<DocKind>("COMPRA");
  const [fileName, setFileName] = useState("");
  const [documentNumber, setDocumentNumber] = useState("FAC-45821");
  const [date, setDate] = useState("2026-09-10");
  const [lines, setLines] = useState<ExtractedLine[]>(MOCK_COMPRA_LINES);
  const [scanning, setScanning] = useState(false);
  const [approvedNote, setApprovedNote] = useState("");

  const movementType = kind === "COMPRA" ? "ENTRADA" : "SALIDA";

  const preview = useMemo(() => {
    return lines.map((line) => {
      const product = catalogByCode(line.productCode);
      const qty = Number(line.quantity) || 0;
      const price = Number(line.unitPrice) || 0;
      if (!product) {
        return { line, product: null, newStock: null as number | null, newCup: null as number | null };
      }
      if (kind === "COMPRA") {
        const newStock = product.stock + qty;
        const newCup =
          newStock > 0
            ? (product.stock * product.cup + qty * price) / newStock
            : product.cup;
        return { line, product, newStock, newCup };
      }
      return {
        line,
        product,
        newStock: Math.max(0, product.stock - qty),
        newCup: product.cup,
      };
    });
  }, [lines, kind]);

  const blockingIssues = useMemo(() => {
    const issues: string[] = [];
    if (!documentNumber.trim()) issues.push("Falta N° de documento");
    if (!date) issues.push("Falta fecha");
    for (const line of lines) {
      if (!line.productCode) {
        issues.push(`Línea sin producto: “${line.rawDescription}”`);
      }
      if (!(Number(line.quantity) > 0)) {
        issues.push(`Cantidad inválida en “${line.rawDescription}”`);
      }
      if (kind === "COMPRA" && !(Number(line.unitPrice) >= 0)) {
        issues.push(`Precio inválido en “${line.rawDescription}”`);
      }
      if (kind === "VENTA") {
        const product = catalogByCode(line.productCode);
        const qty = Number(line.quantity) || 0;
        if (product && qty > product.stock) {
          issues.push(
            `Stock insuficiente para ${product.code} (hay ${product.stock}, pide ${qty})`
          );
        }
      }
    }
    return issues;
  }, [lines, documentNumber, date, kind]);

  function onPickFile(file: File | null) {
    if (!file) return;
    setFileName(file.name);
  }

  function simulateScan() {
    if (!fileName) {
      setFileName(kind === "COMPRA" ? "factura-compra-demo.pdf" : "factura-venta-demo.pdf");
    }
    setScanning(true);
    window.setTimeout(() => {
      setLines(kind === "COMPRA" ? MOCK_COMPRA_LINES : MOCK_VENTA_LINES);
      setDocumentNumber(kind === "COMPRA" ? "FAC-45821" : "FV-1203");
      setStep("review");
      setScanning(false);
    }, 900);
  }

  function updateLine(id: string, patch: Partial<ExtractedLine>) {
    setLines((prev) =>
      prev.map((l) => {
        if (l.id !== id) return l;
        const next = { ...l, ...patch };
        if (patch.productCode !== undefined) {
          if (!patch.productCode) next.match = "missing";
          else if (catalogByCode(patch.productCode)) next.match = "ok";
          else next.match = "missing";
        }
        return next;
      })
    );
  }

  function removeLine(id: string) {
    setLines((prev) => prev.filter((l) => l.id !== id));
  }

  function onApprove() {
    if (blockingIssues.length > 0) return;
    setApprovedNote(
      kind === "COMPRA"
        ? `Prototipo: se registraría ENTRADA ${documentNumber} con ${lines.length} ítem(s). Stock y CUP se recalcularían.`
        : `Prototipo: se registraría SALIDA ${documentNumber} con ${lines.length} ítem(s). Solo bajaría stock.`
    );
    setStep("done");
  }

  function resetAll() {
    setStep("upload");
    setFileName("");
    setApprovedNote("");
    setLines(kind === "COMPRA" ? MOCK_COMPRA_LINES : MOCK_VENTA_LINES);
  }

  return (
    <div>
      <div className="mb-4 animate-fade-in rounded-lg border border-amber-500/30 bg-amber-50 px-4 py-3 text-sm text-amber-950">
        <strong>Prototipo de diseño (offline).</strong> No lee PDF reales ni
        guarda movimientos. Sirve para revisar el flujo y aprobar antes de
        implementar.{" "}
        <Link href="/documentos" className="font-medium underline underline-offset-2">
          Volver a Documentos
        </Link>
      </div>

      <PageHeader
        title="Importar factura PDF"
        description="Sube una factura de compra o venta, revisa lo detectado y aprueba el documento antes de impactar el inventario."
      />

      <ol className="mb-6 flex flex-wrap gap-2 text-sm">
        {(
          [
            { id: "upload", label: "1. Subir PDF" },
            { id: "review", label: "2. Revisar" },
            { id: "done", label: "3. Confirmar" },
          ] as const
        ).map((s) => (
          <li
            key={s.id}
            className={`rounded-md px-3 py-1.5 font-medium ${
              step === s.id
                ? "bg-accent text-white"
                : "bg-bg-deep text-ink-muted"
            }`}
          >
            {s.label}
          </li>
        ))}
      </ol>

      {step === "upload" ? (
        <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
          <section className="animate-fade-up space-y-5 rounded-xl border border-border/80 bg-surface p-5 shadow-[var(--shadow)] sm:p-6">
            <div className="flex gap-2 rounded-lg bg-bg-deep p-1">
              {(
                [
                  { id: "COMPRA", label: "Factura de compra → Entrada" },
                  { id: "VENTA", label: "Factura de venta → Salida" },
                ] as const
              ).map((opt) => (
                <button
                  key={opt.id}
                  type="button"
                  onClick={() => {
                    setKind(opt.id);
                    setLines(opt.id === "COMPRA" ? MOCK_COMPRA_LINES : MOCK_VENTA_LINES);
                  }}
                  className={`flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all ${
                    kind === opt.id
                      ? opt.id === "COMPRA"
                        ? "bg-entrada text-white shadow-sm"
                        : "bg-salida text-white shadow-sm"
                      : "text-ink-muted hover:text-ink"
                  }`}
                >
                  {opt.label}
                </button>
              ))}
            </div>

            <label
              htmlFor="pdf-file"
              className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-bg-deep/40 px-4 py-12 text-center transition hover:border-accent hover:bg-accent-soft/40"
            >
              <span className="font-[family-name:var(--font-fraunces)] text-lg font-semibold text-ink">
                Arrastra o elige un PDF
              </span>
              <span className="max-w-sm text-sm text-ink-muted">
                En este prototipo el archivo no se procesa: simulamos la
                extracción OCR con datos de ejemplo.
              </span>
              <span className="mt-2 text-sm font-medium text-accent">
                {fileName || "Ningún archivo seleccionado"}
              </span>
              <input
                id="pdf-file"
                type="file"
                accept="application/pdf,.pdf"
                className="sr-only"
                onChange={(e) => onPickFile(e.target.files?.[0] ?? null)}
              />
            </label>

            <div className="flex flex-wrap gap-2">
              <Button type="button" onClick={simulateScan} disabled={scanning}>
                {scanning ? "Leyendo PDF…" : "Simular lectura OCR"}
              </Button>
              <Button
                type="button"
                variant="secondary"
                onClick={() => {
                  setFileName(
                    kind === "COMPRA"
                      ? "factura-compra-demo.pdf"
                      : "factura-venta-demo.pdf"
                  );
                }}
              >
                Usar PDF de demo
              </Button>
            </div>
          </section>

          <aside className="animate-fade-up stagger-2 space-y-4">
            <div className="rounded-xl border border-border/80 bg-surface p-5 shadow-[var(--shadow)]">
              <h2 className="font-[family-name:var(--font-fraunces)] text-lg font-semibold">
                Qué hace este paso
              </h2>
              <ul className="mt-3 space-y-2 text-sm text-ink-muted">
                <li>Detecta N° documento, fecha e ítems del PDF.</li>
                <li>
                  Compra → entrada (precio alimenta CUP). Venta → salida (CUP no
                  cambia).
                </li>
                <li>Nada se guarda hasta que apruebes en el paso 2.</li>
              </ul>
            </div>
            <div className="rounded-xl border border-dashed border-border bg-surface/60 p-5 text-sm text-ink-muted">
              Tipo seleccionado:{" "}
              <span className="font-medium text-ink">
                {kind === "COMPRA" ? "Compra / Entrada" : "Venta / Salida"}
              </span>{" "}
              ({movementType})
            </div>
          </aside>
        </div>
      ) : null}

      {step === "review" ? (
        <div className="space-y-5">
          <section className="animate-fade-up rounded-xl border border-border/80 bg-surface p-5 shadow-[var(--shadow)] sm:p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                  Archivo
                </p>
                <p className="font-medium text-ink">{fileName || "demo.pdf"}</p>
              </div>
              <span
                className={`inline-flex rounded-md px-2 py-0.5 text-xs font-semibold ${
                  kind === "COMPRA"
                    ? "bg-entrada/10 text-entrada"
                    : "bg-salida/10 text-salida"
                }`}
              >
                {kind === "COMPRA" ? "Compra → Entrada" : "Venta → Salida"}
              </span>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="N° documento" htmlFor="doc-number">
                <Input
                  id="doc-number"
                  value={documentNumber}
                  onChange={(e) => setDocumentNumber(e.target.value)}
                />
              </Field>
              <Field label="Fecha" htmlFor="doc-date">
                <Input
                  id="doc-date"
                  type="date"
                  value={date}
                  onChange={(e) => setDate(e.target.value)}
                />
              </Field>
            </div>
          </section>

          <section className="animate-fade-up overflow-hidden rounded-xl border border-border/80 bg-surface shadow-[var(--shadow)]">
            <div className="border-b border-border/70 px-4 py-3">
              <h2 className="font-[family-name:var(--font-fraunces)] text-lg font-semibold">
                Líneas detectadas ({lines.length})
              </h2>
              <p className="text-sm text-ink-muted">
                Corrige códigos, cantidades y precios antes de aprobar.
              </p>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[920px] text-left text-sm">
                <thead className="bg-bg-deep/60 text-xs uppercase tracking-wide text-ink-muted">
                  <tr>
                    <th className="px-4 py-3 font-semibold">Texto PDF</th>
                    <th className="px-4 py-3 font-semibold">Producto</th>
                    <th className="px-4 py-3 font-semibold text-right">Cant.</th>
                    <th className="px-4 py-3 font-semibold text-right">
                      {kind === "COMPRA" ? "P. compra" : "P. venta"}
                    </th>
                    <th className="px-4 py-3 font-semibold">Estado</th>
                    <th className="px-4 py-3 font-semibold text-right">Impacto</th>
                    <th className="px-4 py-3 font-semibold text-right"> </th>
                  </tr>
                </thead>
                <tbody>
                  {lines.map((line, idx) => {
                    const impact = preview[idx];
                    return (
                      <tr
                        key={line.id}
                        className="border-t border-border/70 align-top"
                      >
                        <td className="px-4 py-3">
                          <p className="font-medium text-ink">
                            {line.rawDescription}
                          </p>
                          {line.note ? (
                            <p className="mt-1 text-xs text-ink-muted">
                              {line.note}
                            </p>
                          ) : null}
                        </td>
                        <td className="px-4 py-3">
                          <Select
                            value={line.productCode}
                            onChange={(e) =>
                              updateLine(line.id, {
                                productCode: e.target.value,
                              })
                            }
                            aria-label={`Producto línea ${idx + 1}`}
                          >
                            <option value="">Sin asignar</option>
                            {MOCK_CATALOG.map((p) => (
                              <option key={p.code} value={p.code}>
                                {p.code} — {p.name}
                              </option>
                            ))}
                          </Select>
                        </td>
                        <td className="px-4 py-3">
                          <Input
                            type="number"
                            min="0.0001"
                            step="any"
                            className="text-right"
                            value={line.quantity}
                            onChange={(e) =>
                              updateLine(line.id, { quantity: e.target.value })
                            }
                          />
                        </td>
                        <td className="px-4 py-3">
                          <Input
                            type="number"
                            min="0"
                            step="any"
                            className="text-right"
                            value={line.unitPrice}
                            onChange={(e) =>
                              updateLine(line.id, { unitPrice: e.target.value })
                            }
                          />
                        </td>
                        <td className="px-4 py-3">
                          <span
                            className={`inline-flex rounded-md px-2 py-0.5 text-xs font-semibold ${
                              line.match === "ok"
                                ? "bg-entrada/10 text-entrada"
                                : line.match === "review"
                                  ? "bg-salida/10 text-salida"
                                  : "bg-danger/10 text-danger"
                            }`}
                          >
                            {line.match === "ok"
                              ? "OK"
                              : line.match === "review"
                                ? "Revisar"
                                : "Sin match"}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-right text-xs text-ink-muted">
                          {impact.product ? (
                            <>
                              <div>
                                Stock →{" "}
                                <span className="tabular-nums text-ink">
                                  {formatNumber(impact.newStock ?? 0)}
                                </span>
                              </div>
                              {kind === "COMPRA" ? (
                                <div>
                                  CUP →{" "}
                                  <span className="tabular-nums text-ink">
                                    {formatCurrency(impact.newCup ?? 0)}
                                  </span>
                                </div>
                              ) : (
                                <div>CUP sin cambio</div>
                              )}
                            </>
                          ) : (
                            "—"
                          )}
                        </td>
                        <td className="px-4 py-3 text-right">
                          <Button
                            type="button"
                            variant="ghost"
                            className="!px-2 text-danger"
                            onClick={() => removeLine(line.id)}
                          >
                            Quitar
                          </Button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </section>

          {blockingIssues.length > 0 ? (
            <div className="rounded-md border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
              <p className="font-semibold">Hay que corregir antes de aprobar:</p>
              <ul className="mt-1 list-disc pl-5">
                {blockingIssues.map((issue) => (
                  <li key={issue}>{issue}</li>
                ))}
              </ul>
            </div>
          ) : (
            <div className="rounded-md border border-entrada/30 bg-entrada/5 px-4 py-3 text-sm text-entrada">
              Listo para aprobar. En la versión real aquí se crearían los
              movimientos.
            </div>
          )}

          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="ghost" onClick={resetAll}>
              Volver a subir
            </Button>
            <Button
              type="button"
              onClick={onApprove}
              disabled={blockingIssues.length > 0 || lines.length === 0}
            >
              Aprobar documento (demo)
            </Button>
          </div>
        </div>
      ) : null}

      {step === "done" ? (
        <section className="animate-fade-up max-w-xl space-y-4 rounded-xl border border-border/80 bg-surface p-6 shadow-[var(--shadow)]">
          <h2 className="font-[family-name:var(--font-fraunces)] text-2xl font-semibold text-ink">
            Diseño aprobado en demo
          </h2>
          <p className="text-sm text-ink-muted">{approvedNote}</p>
          <p className="text-sm text-ink-muted">
            Esto no escribió en la base de datos. Si el flujo te parece bien,
            autorizas la implementación real (OCR + guardado).
          </p>
          <div className="flex flex-wrap gap-2 pt-2">
            <Button type="button" onClick={resetAll}>
              Probar de nuevo
            </Button>
            <Link href="/documentos">
              <Button type="button" variant="secondary">
                Volver a Documentos
              </Button>
            </Link>
          </div>
        </section>
      ) : null}
    </div>
  );
}
