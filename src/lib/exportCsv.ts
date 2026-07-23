export type InventoryExportRow = {
  code: string;
  name: string;
  stock: number;
  lowStockThreshold: number;
  averageUnitCost: number;
  totalValue: number;
};

const HEADERS = [
  "Código",
  "Producto",
  "Stock",
  "Umbral Bajo Stock",
  "CUP",
  "Valor Total",
] as const;

/** Números puros para Excel (punto decimal, sin símbolo de moneda). */
function csvNumber(value: number): string {
  if (!Number.isFinite(value)) return "0";
  // Evita notación científica y limpia ceros innecesarios
  return String(Number(value.toFixed(6)));
}

function escapeCsvCell(value: string, separator: string): string {
  if (
    value.includes('"') ||
    value.includes("\n") ||
    value.includes("\r") ||
    value.includes(separator)
  ) {
    return `"${value.replace(/"/g, '""')}"`;
  }
  return value;
}

/**
 * Genera CSV con BOM UTF-8 y separador `;` (compatible con Excel en es-CL).
 * Los campos numéricos van sin símbolo de moneda.
 */
export function buildInventoryCsv(rows: InventoryExportRow[]): string {
  const separator = ";";
  const lines = [
    HEADERS.join(separator),
    ...rows.map((row) =>
      [
        escapeCsvCell(row.code, separator),
        escapeCsvCell(row.name, separator),
        csvNumber(row.stock),
        csvNumber(row.lowStockThreshold),
        csvNumber(row.averageUnitCost),
        csvNumber(row.totalValue),
      ].join(separator)
    ),
  ];
  return `\uFEFF${lines.join("\r\n")}`;
}

export function downloadInventoryCsv(
  rows: InventoryExportRow[],
  filename: string
): void {
  const csv = buildInventoryCsv(rows);
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename.endsWith(".csv") ? filename : `${filename}.csv`;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  URL.revokeObjectURL(url);
}

export function matchesProductSearch(
  query: string,
  code: string,
  name: string
): boolean {
  const q = query.trim().toLowerCase();
  if (!q) return true;
  return (
    code.toLowerCase().includes(q) || name.toLowerCase().includes(q)
  );
}
