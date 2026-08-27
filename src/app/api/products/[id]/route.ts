import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";

export const dynamic = "force-dynamic";
export const revalidate = 0;

type Params = { params: Promise<{ id: string }> };

export async function GET(_request: NextRequest, { params }: Params) {
  try {
    const { id } = await params;
    const product = await prisma.product.findUnique({ where: { id } });
    if (!product) {
      return NextResponse.json(
        { error: "Producto no encontrado" },
        { status: 404 }
      );
    }
    return NextResponse.json(product);
  } catch (error) {
    console.error("GET /api/products/[id]", error);
    return NextResponse.json(
      { error: "Error al obtener producto" },
      { status: 500 }
    );
  }
}

export async function PUT(request: NextRequest, { params }: Params) {
  try {
    const { id } = await params;
    const body = await request.json();
    const { code, name, description, lowStockThreshold } = body;

    if (!code?.trim() || !name?.trim()) {
      return NextResponse.json(
        { error: "Código y nombre son obligatorios" },
        { status: 400 }
      );
    }

    const threshold = Number(lowStockThreshold);
    if (!Number.isFinite(threshold) || threshold < 0) {
      return NextResponse.json(
        { error: "El umbral de bajo stock debe ser un número ≥ 0" },
        { status: 400 }
      );
    }

    const existing = await prisma.product.findUnique({ where: { id } });
    if (!existing) {
      return NextResponse.json(
        { error: "Producto no encontrado" },
        { status: 404 }
      );
    }

    const codeConflict = await prisma.product.findFirst({
      where: { code: code.trim(), NOT: { id } },
    });
    if (codeConflict) {
      return NextResponse.json(
        { error: "Ya existe otro producto con ese código" },
        { status: 409 }
      );
    }

    // Stock y CUP solo se modifican vía movimientos de inventario
    const product = await prisma.product.update({
      where: { id },
      data: {
        code: code.trim(),
        name: name.trim(),
        description: description?.trim() ?? "",
        lowStockThreshold: threshold,
      },
    });

    return NextResponse.json(product);
  } catch (error) {
    console.error("PUT /api/products/[id]", error);
    return NextResponse.json(
      { error: "Error al actualizar producto" },
      { status: 500 }
    );
  }
}

export async function DELETE(_request: NextRequest, { params }: Params) {
  try {
    const { id } = await params;
    const existing = await prisma.product.findUnique({
      where: { id },
      include: { _count: { select: { movements: true } } },
    });

    if (!existing) {
      return NextResponse.json(
        { error: "Producto no encontrado" },
        { status: 404 }
      );
    }

    if (existing._count.movements > 0) {
      return NextResponse.json(
        {
          error:
            "No se puede eliminar: el producto tiene movimientos registrados",
        },
        { status: 400 }
      );
    }

    await prisma.product.delete({ where: { id } });
    return NextResponse.json({ ok: true });
  } catch (error) {
    console.error("DELETE /api/products/[id]", error);
    return NextResponse.json(
      { error: "Error al eliminar producto" },
      { status: 500 }
    );
  }
}
