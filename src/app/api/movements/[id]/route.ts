import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { recomputeProductStockAndCup } from "@/lib/recompute-product";

export const dynamic = "force-dynamic";
export const revalidate = 0;

type Params = { params: Promise<{ id: string }> };

type MovLike = {
  id: string;
  type: string;
  quantity: number;
  unitPrice: number;
  date: Date;
};

function stockWouldGoNegative(movements: MovLike[]): boolean {
  let stock = 0;
  for (const m of movements) {
    if (m.type === "ENTRADA") {
      stock += m.quantity;
    } else {
      stock -= m.quantity;
      if (stock < 0) return true;
    }
  }
  return false;
}

export async function PATCH(req: NextRequest, { params }: Params) {
  try {
    const { id } = await params;
    const body = await req.json();
    const existing = await prisma.movement.findUnique({ where: { id } });
    if (!existing) {
      return NextResponse.json(
        { error: "Movimiento no encontrado" },
        { status: 404 }
      );
    }

    const documentNumber =
      body.documentNumber !== undefined
        ? String(body.documentNumber).trim()
        : existing.documentNumber;
    if (!documentNumber) {
      return NextResponse.json(
        { error: "Número de documento requerido" },
        { status: 400 }
      );
    }

    const date = body.date ? new Date(body.date) : existing.date;
    if (Number.isNaN(date.getTime())) {
      return NextResponse.json({ error: "Fecha inválida" }, { status: 400 });
    }

    let quantity = existing.quantity;
    if (body.quantity !== undefined) {
      quantity = Number(body.quantity);
      if (!Number.isFinite(quantity) || quantity <= 0) {
        return NextResponse.json(
          { error: "Cantidad inválida" },
          { status: 400 }
        );
      }
    }

    let unitPrice = existing.unitPrice;
    if (existing.type === "ENTRADA" && body.unitPrice !== undefined) {
      unitPrice = Number(body.unitPrice);
      if (!Number.isFinite(unitPrice) || unitPrice < 0) {
        return NextResponse.json(
          { error: "Precio unitario inválido" },
          { status: 400 }
        );
      }
    }

    const siblings = await prisma.movement.findMany({
      where: { productId: existing.productId },
      orderBy: [{ date: "asc" }, { createdAt: "asc" }, { id: "asc" }],
    });

    const projected: MovLike[] = siblings.map((m) =>
      m.id === id
        ? {
            id: m.id,
            type: m.type,
            quantity,
            unitPrice,
            date,
          }
        : {
            id: m.id,
            type: m.type,
            quantity: m.quantity,
            unitPrice: m.unitPrice,
            date: m.date,
          }
    );
    projected.sort(
      (a, b) =>
        a.date.getTime() - b.date.getTime() || a.id.localeCompare(b.id)
    );

    if (stockWouldGoNegative(projected)) {
      return NextResponse.json(
        {
          error:
            "El cambio dejaría stock insuficiente para salidas posteriores. Corrige o elimina esas salidas primero.",
        },
        { status: 400 }
      );
    }

    const updated = await prisma.$transaction(async (tx) => {
      await tx.movement.update({
        where: { id },
        data: {
          documentNumber,
          date,
          quantity,
          unitPrice:
            existing.type === "ENTRADA" ? unitPrice : existing.unitPrice,
        },
      });
      await recomputeProductStockAndCup(tx, existing.productId);
      return tx.movement.findUnique({
        where: { id },
        include: {
          product: { select: { id: true, code: true, name: true } },
        },
      });
    });

    return NextResponse.json(updated);
  } catch (e) {
    console.error("PATCH /api/movements/[id]", e);
    return NextResponse.json(
      { error: "Error al actualizar movimiento" },
      { status: 500 }
    );
  }
}

export async function DELETE(_req: NextRequest, { params }: Params) {
  try {
    const { id } = await params;
    const existing = await prisma.movement.findUnique({ where: { id } });
    if (!existing) {
      return NextResponse.json(
        { error: "Movimiento no encontrado" },
        { status: 404 }
      );
    }

    const siblings = await prisma.movement.findMany({
      where: { productId: existing.productId },
      orderBy: [{ date: "asc" }, { createdAt: "asc" }, { id: "asc" }],
    });

    const projected = siblings
      .filter((m) => m.id !== id)
      .map((m) => ({
        id: m.id,
        type: m.type,
        quantity: m.quantity,
        unitPrice: m.unitPrice,
        date: m.date,
      }));

    if (stockWouldGoNegative(projected)) {
      return NextResponse.json(
        {
          error:
            "No se puede eliminar: dejaría stock insuficiente para salidas posteriores. Elimina o ajusta esas salidas primero.",
        },
        { status: 400 }
      );
    }

    const productId = existing.productId;
    await prisma.$transaction(async (tx) => {
      await tx.movement.delete({ where: { id } });
      await recomputeProductStockAndCup(tx, productId);
    });

    return NextResponse.json({ ok: true });
  } catch (e) {
    console.error("DELETE /api/movements/[id]", e);
    return NextResponse.json(
      { error: "Error al eliminar movimiento" },
      { status: 500 }
    );
  }
}
