import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { calculateNewAverageCost } from "@/lib/inventory";
import { MovementType } from "@prisma/client";

export const dynamic = "force-dynamic";
export const revalidate = 0;

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

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { type, documentNumber, productId, quantity, unitPrice, date } = body;

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

    if (!productId) {
      return NextResponse.json(
        { error: "Producto es obligatorio" },
        { status: 400 }
      );
    }

    const qty = Number(quantity);
    if (!Number.isFinite(qty) || qty <= 0) {
      return NextResponse.json(
        { error: "La cantidad debe ser un número mayor a 0" },
        { status: 400 }
      );
    }

    const price = Number(unitPrice) || 0;
    if (type === "ENTRADA" && price < 0) {
      return NextResponse.json(
        { error: "El precio unitario no puede ser negativo" },
        { status: 400 }
      );
    }

    const movementDate = date ? new Date(date) : new Date();
    if (Number.isNaN(movementDate.getTime())) {
      return NextResponse.json(
        { error: "Fecha inválida" },
        { status: 400 }
      );
    }

    const result = await prisma.$transaction(async (tx) => {
      const product = await tx.product.findUnique({ where: { id: productId } });
      if (!product) {
        throw new Error("PRODUCT_NOT_FOUND");
      }

      if (type === "SALIDA" && product.stock < qty) {
        throw new Error("INSUFFICIENT_STOCK");
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
        // CUP se mantiene igual en salidas
      }

      await tx.product.update({
        where: { id: productId },
        data: {
          stock: newStock,
          averageUnitCost: newCup,
        },
      });

      const movement = await tx.movement.create({
        data: {
          type,
          documentNumber: documentNumber.trim(),
          productId,
          quantity: qty,
          unitPrice: type === "ENTRADA" ? price : product.averageUnitCost,
          date: movementDate,
        },
        include: {
          product: {
            select: { id: true, code: true, name: true },
          },
        },
      });

      return movement;
    });

    return NextResponse.json(result, { status: 201 });
  } catch (error) {
    if (error instanceof Error) {
      if (error.message === "PRODUCT_NOT_FOUND") {
        return NextResponse.json(
          { error: "Producto no encontrado" },
          { status: 404 }
        );
      }
      if (error.message === "INSUFFICIENT_STOCK") {
        return NextResponse.json(
          { error: "Stock insuficiente para la salida" },
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
