import { NextResponse } from "next/server";
import { jsonNoStore } from "@/lib/api-response";
import { prisma } from "@/lib/prisma";

export const dynamic = "force-dynamic";
export const revalidate = 0;

export async function GET() {
  try {
    const products = await prisma.product.findMany({
      orderBy: { name: "asc" },
    });

    const items = products.map((p) => ({
      id: p.id,
      code: p.code,
      name: p.name,
      description: p.description,
      stock: p.stock,
      averageUnitCost: p.averageUnitCost,
      lowStockThreshold: p.lowStockThreshold,
      totalValue: p.stock * p.averageUnitCost,
      isLowStock: p.stock < p.lowStockThreshold,
    }));

    const totalInventoryValue = items.reduce(
      (sum, item) => sum + item.totalValue,
      0
    );
    const totalSku = items.length;
    const totalUnits = items.reduce((sum, item) => sum + item.stock, 0);

    const [entradas, salidas] = await Promise.all([
      prisma.movement.count({ where: { type: "ENTRADA" } }),
      prisma.movement.count({ where: { type: "SALIDA" } }),
    ]);

    return jsonNoStore({
      items,
      summary: {
        totalSku,
        totalUnits,
        totalInventoryValue,
        entradas,
        salidas,
      },
    });
  } catch (error) {
    console.error("GET /api/dashboard", error);
    return jsonNoStore(
      { error: "Error al obtener dashboard" },
      { status: 500 }
    );
  }
}
