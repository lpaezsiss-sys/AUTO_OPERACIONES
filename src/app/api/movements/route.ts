import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { calculateNewAverageCost } from "@/lib/inventory";
import { MovementType, Prisma } from "@prisma/client";

export const dynamic = "force-dynamic";
export const revalidate = 0;

type LineInput = {
  productId: string;
  quantity: number;
  unitPrice?: number;
};

async function applyLine(
  tx: Prisma.TransactionClient,
  params: {
    type: MovementType;
    documentNumber: string;
    date: Date;
    line: LineInput;
  }
) {
  const { type, documentNumber, date, line } = params;
  const qty = Number(line.quantity);
  const price = Number(line.unitPrice) || 0;

  if (!line.productId) {
    throw new Error("PRODUCT_REQUIRED");
  }
  if (!Number.isFinite(qty) || qty <= 0) {
    throw new Error("INVALID_QTY");
  }
  if (type === "ENTRADA" && price < 0) {
    throw new Error("INVALID_PRICE");
  }

  const product = await tx.product.findUnique({
    where: { id: line.productId },
  });
  if (!product) {
    throw new Error("PRODUCT_NOT_FOUND");
  }

  if (type === "SALIDA" && product.stock < qty) {
    throw new Error(`INSUFFICIENT_STOCK:${product.code}`);
  }

  let newStock = product.stock;
  let newCup = product.averageUnitCost;

  if (type === "ENTRADA") {
    newCup = calculateNewAverageCost(
      product.stock,
      product.averageUnitCost,
      qty,
      price
    );
    newStock = product.stock + qty;
  } else {
    newStock = product.stock - qty;
  }

  await tx.product.update({
    where: { id: product.id },
    data: {
      stock: newStock,
      averageUnitCost: newCup,
    },
  });

  return tx.movement.create({
    data: {
      type,
      documentNumber,
      productId: product.id,
      quantity: qty,
      unitPrice: type === "ENTRADA" ? price : product.averageUnitCost,
      date,
    },
    include: {
      product: {
        select: { id: true, code: true, name: true },
      },
    },
  });
}

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const type = searchParams.get("type");
    const productId = searchParams.get("productId");

    const movements = await prisma.movement.findMany({
      where: {
        ...(type === "ENTRADA" || type === "SALIDA"
          ? { type: type as MovementType }
          : {}),
        ...(productId ? { productId } : {}),
      },
      include: {
        product: {
          select: { id: true, code: true, name: true },
        },
      },
      orderBy: { date: "desc" },
    });

    return NextResponse.json(movements);
  } catch (error) {
    console.error("GET /api/movements", error);
    return NextResponse.json(
      { error: "Error al listar movimientos" },
      { status: 500 }
    );
  }
}

/**
 * POST compatible:
 * - Un ítem: { type, documentNumber, productId, quantity, unitPrice?, date? }
 * - Varios ítems: { type, documentNumber, date?, lines: [{ productId, quantity, unitPrice? }, ...] }
 *
 * No cambia el schema: crea N registros Movement con el mismo documentNumber.
 * Los datos existentes no se alteran.
 */
export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { type, documentNumber, date } = body;

    if (type !== "ENTRADA" && type !== "SALIDA") {
      return NextResponse.json(
        { error: "Tipo debe ser ENTRADA o SALIDA" },
        { status: 400 }
      );
    }

    if (!documentNumber?.trim()) {
      return NextResponse.json(
        { error: "N° de documento es obligatorio" },
        { status: 400 }
      );
    }

    const movementDate = date ? new Date(date) : new Date();
    if (Number.isNaN(movementDate.getTime())) {
      return NextResponse.json({ error: "Fecha inválida" }, { status: 400 });
    }

    const doc = String(documentNumber).trim();

    let lines: LineInput[] = [];
    if (Array.isArray(body.lines) && body.lines.length > 0) {
      lines = body.lines.map(
        (l: { productId?: string; quantity?: number; unitPrice?: number }) => ({
          productId: String(l.productId ?? ""),
          quantity: Number(l.quantity),
          unitPrice: Number(l.unitPrice) || 0,
        })
      );
    } else if (body.productId) {
      lines = [
        {
          productId: String(body.productId),
          quantity: Number(body.quantity),
          unitPrice: Number(body.unitPrice) || 0,
        },
      ];
    }

    if (lines.length === 0) {
      return NextResponse.json(
        { error: "Agrega al menos un producto al documento" },
        { status: 400 }
      );
    }

    const productIds = lines.map((l) => l.productId);
    if (new Set(productIds).size !== productIds.length) {
      return NextResponse.json(
        { error: "No repitas el mismo producto en el documento" },
        { status: 400 }
      );
    }

    const movements = await prisma.$transaction(async (tx) => {
      const created = [];
      for (const line of lines) {
        created.push(
          await applyLine(tx, {
            type,
            documentNumber: doc,
            date: movementDate,
            line,
          })
        );
      }
      return created;
    });

    return NextResponse.json(
      {
        ok: true,
        documentNumber: doc,
        type,
        count: movements.length,
        movements,
        // Compat: si era 1 ítem, también devolver el movimiento suelto
        ...(movements.length === 1 ? movements[0] : {}),
      },
      { status: 201 }
    );
  } catch (error) {
    if (error instanceof Error) {
      if (error.message === "PRODUCT_NOT_FOUND") {
        return NextResponse.json(
          { error: "Producto no encontrado" },
          { status: 404 }
        );
      }
      if (error.message === "PRODUCT_REQUIRED") {
        return NextResponse.json(
          { error: "Producto es obligatorio" },
          { status: 400 }
        );
      }
      if (error.message === "INVALID_QTY") {
        return NextResponse.json(
          { error: "La cantidad debe ser un número mayor a 0" },
          { status: 400 }
        );
      }
      if (error.message === "INVALID_PRICE") {
        return NextResponse.json(
          { error: "El precio unitario no puede ser negativo" },
          { status: 400 }
        );
      }
      if (error.message.startsWith("INSUFFICIENT_STOCK")) {
        const code = error.message.split(":")[1] || "";
        return NextResponse.json(
          {
            error: code
              ? `Stock insuficiente para ${code}`
              : "Stock insuficiente para la salida",
          },
          { status: 400 }
        );
      }
    }
    console.error("POST /api/movements", error);
    return NextResponse.json(
      { error: "Error al registrar movimiento" },
      { status: 500 }
    );
  }
}
