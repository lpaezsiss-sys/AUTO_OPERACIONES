export type InventoryExportRow = {
  code: string;
  name: string;
  stock: number;
  lowStockThreshold: number;
  averageUnitCost: number;
  totalValue: number;
};

export type MovementExportRow = {
  date: string;
  type: string;
  documentNumber: string;
  productCode: string;
  productName: string;
  quantity: number;
  unitPrice: number;
  lineTotal: number;
};

const INVENTORY_HEADERS = [
  "Código",
  "Producto",
  "Stock",
  "Umbral Bajo Stock",
  "CUP",
  "Valor Total",
] as const;

const MOVEMENT_HEADERS = [
  "Fecha",
  "Tipo",
  "N° Documento",
  "Código",
  "Producto",
  "Cantidad",
  "Precio Unitario",
  "Total Línea",
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

function downloadCsv(content: string, filename: string): void {
  const blob = new Blob([content], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename.endsWith(".csv") ? filename : `${filename}.csv`;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  URL.revokeObjectURL(url);
}

/**
 * Genera CSV con BOM UTF-8 y separador `;` (compatible con Excel en es-CL).
 * Los campos numéricos van sin símbolo de moneda.
 */
export function buildInventoryCsv(rows: InventoryExportRow[]): string {
  const separator = ";";
  const lines = [
    INVENTORY_HEADERS.join(separator),
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
  downloadCsv(buildInventoryCsv(rows), filename);
}

export function buildMovementsCsv(rows: MovementExportRow[]): string {
  const separator = ";";
  const lines = [
    MOVEMENT_HEADERS.join(separator),
    ...rows.map((row) =>
      [
        escapeCsvCell(row.date, separator),
        escapeCsvCell(row.type, separator),
        escapeCsvCell(row.documentNumber, separator),
        escapeCsvCell(row.productCode, separator),
        escapeCsvCell(row.productName, separator),
        csvNumber(row.quantity),
        csvNumber(row.unitPrice),
        csvNumber(row.lineTotal),
      ].join(separator)
    ),
  ];
  return `\uFEFF${lines.join("\r\n")}`;
}

export function downloadMovementsCsv(
  rows: MovementExportRow[],
  filename: string
): void {
  downloadCsv(buildMovementsCsv(rows), filename);
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
