import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";

export async function GET() {
  try {
    const products = await prisma.product.findMany({
      orderBy: { name: "asc" },
    });
    return NextResponse.json(products);
  } catch (error) {
    console.error("GET /api/products", error);
    return NextResponse.json(
      { error: "Error al listar productos" },
      { status: 500 }
    );
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { code, name, description, stock, averageUnitCost } = body;

    if (!code?.trim() || !name?.trim()) {
      return NextResponse.json(
        { error: "Código y nombre son obligatorios" },
        { status: 400 }
      );
    }

    const existing = await prisma.product.findUnique({
      where: { code: code.trim() },
    });
    if (existing) {
      return NextResponse.json(
        { error: "Ya existe un producto con ese código" },
        { status: 409 }
      );
    }

    const product = await prisma.product.create({
      data: {
        code: code.trim(),
        name: name.trim(),
        description: description?.trim() ?? "",
        stock: Number(stock) || 0,
        averageUnitCost: Number(averageUnitCost) || 0,
      },
    });

    return NextResponse.json(product, { status: 201 });
  } catch (error) {
    console.error("POST /api/products", error);
    return NextResponse.json(
      { error: "Error al crear producto" },
      { status: 500 }
    );
  }
}
