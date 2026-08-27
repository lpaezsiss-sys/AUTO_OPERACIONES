/**
 * Calcula el nuevo Costo Unitario Promedio (CUP/PMP) tras una compra.
 * Nuevo CUP = (Stock Actual × CUP Actual + Cantidad Comprada × Precio Unitario) /
 *             (Stock Actual + Cantidad Comprada)
 */
export function calculateNewAverageCost(
  currentStock: number,
  currentCup: number,
  purchaseQty: number,
  purchaseUnitPrice: number
): number {
  const totalStock = currentStock + purchaseQty;
  if (totalStock <= 0) return 0;

  const currentValue = currentStock * currentCup;
  const purchaseValue = purchaseQty * purchaseUnitPrice;
  return (currentValue + purchaseValue) / totalStock;
}
