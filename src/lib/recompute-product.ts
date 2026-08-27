import { Prisma, MovementType } from "@prisma/client";
import { calculateNewAverageCost } from "@/lib/inventory";

/**
 * Recalcula stock y CUP de un producto a partir de todos sus movimientos.
 * Se usa tras editar/eliminar para corregir errores de digitación.
 */
export async function recomputeProductStockAndCup(
  tx: Prisma.TransactionClient,
  productId: string
) {
  const movements = await tx.movement.findMany({
    where: { productId },
    orderBy: [{ date: "asc" }, { createdAt: "asc" }, { id: "asc" }],
  });

  let stock = 0;
  let cup = 0;

  for (const m of movements) {
    if (m.type === ("ENTRADA" as MovementType)) {
      cup = calculateNewAverageCost(stock, cup, m.quantity, m.unitPrice);
      stock += m.quantity;
    } else {
      stock -= m.quantity;
      if (stock < 0) stock = 0;
    }
  }

  if (stock === 0 && movements.length === 0) {
    cup = 0;
  }

  await tx.product.update({
    where: { id: productId },
    data: {
      stock,
      averageUnitCost: cup,
    },
  });

  return { stock, averageUnitCost: cup };
}
