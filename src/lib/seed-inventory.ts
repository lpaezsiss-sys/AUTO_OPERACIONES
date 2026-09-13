import bcrypt from "bcryptjs";
import { prisma } from "@/lib/prisma";

const INITIAL_DOC = "INI-2026-01-01";
const INITIAL_DATE = new Date("2026-01-01T12:00:00.000Z");

const products = [
  {
    code: "14453",
    name: "Sonic 100/150 Bearing Cartridge Kit",
    description: "Sonic 100/150 Bearing Cartridge Kit (PN #14453)",
    initialQty: 1,
    cup: 1_384_732,
  },
  {
    code: "13451",
    name: "Correa Sonic 16 GRV 13451",
    description: "Correa Sonic 16 GRV 13451",
    initialQty: 10,
    cup: 76_337,
  },
  {
    code: "13514",
    name: "Correa Sonic 16 GRV 13514",
    description: "Correa Sonic 16 GRV 13514",
    initialQty: 11,
    cup: 65_980,
  },
  {
    code: "13474",
    name: "Correa Sonic 16 GRV 13474",
    description: "Correa Sonic 16 GRV 13474",
    initialQty: 3,
    cup: 73_494,
  },
  {
    code: "13555",
    name: "Correa Sonic 16 GRV 13555",
    description: "Correa Sonic 16 GRV 13555",
    initialQty: 5,
    cup: 71_632,
  },
  {
    code: "12638",
    name: "Sonic 85/150 Impeller",
    description: "Sonic 85/150 Impeller (PN #12638)",
    initialQty: 1,
    cup: 987_150,
  },
  {
    code: "14452",
    name: "Sonic 70/85 Bearing Cartridge Kit",
    description: "Sonic 70/85 Bearing Cartridge Kit (PN #14452)",
    initialQty: 1,
    cup: 1_120_636,
  },
  {
    code: "13455",
    name: "Kit Tensor Correa",
    description: "Kit Tensor Correa",
    initialQty: 0,
    cup: 328_498,
  },
  {
    code: "10317",
    name: "Filtro SONIC 85 Poly",
    description: "Filtro SONIC 85 Poly",
    initialQty: 1,
    cup: 208_746,
  },
  {
    code: "13900A-150",
    name: "Sonic Pulley 13900A-150",
    description: "Sonic Pulley (PN #13900A-150)",
    initialQty: 2,
    cup: 178_569,
  },
  {
    code: "13900A-152",
    name: "Sonic Pulley 13900A-152",
    description: "Sonic Pulley (PN #13900A-152)",
    initialQty: 0,
    cup: 171_405,
  },
  {
    code: "13900A-160",
    name: "Sonic Pulley 13900A-160",
    description: "Sonic Pulley (PN #13900A-160)",
    initialQty: 1,
    cup: 196_945,
  },
  {
    code: "14454",
    name: "Blower S85 Completo",
    description: "Blower S85 Completo",
    initialQty: 0,
    cup: 0,
  },
  {
    code: "10434",
    name: 'Flexible 3" Largo 12 Pies',
    description: 'Flexible 3" Largo 12 Pies',
    initialQty: 1,
    cup: 346_183,
  },
  {
    code: "10435",
    name: 'Flexible 4" Largo 12 Pies',
    description: 'Flexible 4" Largo 12 Pies',
    initialQty: 0,
    cup: 346_183,
  },
  {
    code: "10976",
    name: "Filtro Completo Con Indicador de Saturacion",
    description: "Filtro Completo Con Indicador de Saturacion",
    initialQty: 0,
    cup: 620_000,
  },
  {
    code: "A08-10100",
    name: "CINTA Doble Fas CMC 10730",
    description: "CINTA Doble Fas CMC 10730 A25 L 33m",
    initialQty: 49,
    cup: 45_756,
  },
  {
    code: "A08-10101",
    name: "CMC 10431 RED 25 mm x 33 mt",
    description: "CMC 10431 RED Ancho 25 mm x 33 mt",
    initialQty: 24,
    cup: 42_994,
  },
] as const;

/** Carga catálogo + ingreso inicial (sin tsx / esbuild / shell). */
export async function seedInventoryInProcess(): Promise<{
  products: number;
  totalUnits: number;
  inventoryValue: number;
}> {
  const passwordHash = await bcrypt.hash("inventario2026", 10);
  await prisma.user.upsert({
    where: { username: "admin" },
    create: {
      username: "admin",
      passwordHash,
      name: "Administrador",
    },
    update: {
      passwordHash,
      name: "Administrador",
    },
  });

  for (const item of products) {
    const existing = await prisma.product.findUnique({
      where: { code: item.code },
    });

    const product = existing
      ? await prisma.product.update({
          where: { code: item.code },
          data: {
            name: item.name,
            description: item.description,
            averageUnitCost: item.cup,
            stock: item.initialQty,
          },
        })
      : await prisma.product.create({
          data: {
            code: item.code,
            name: item.name,
            description: item.description,
            stock: item.initialQty,
            averageUnitCost: item.cup,
          },
        });

    const iniMovement = await prisma.movement.findFirst({
      where: {
        productId: product.id,
        documentNumber: INITIAL_DOC,
        type: "ENTRADA",
      },
    });

    if (item.initialQty > 0) {
      if (iniMovement) {
        await prisma.movement.update({
          where: { id: iniMovement.id },
          data: {
            quantity: item.initialQty,
            unitPrice: item.cup,
            date: INITIAL_DATE,
          },
        });
      } else {
        await prisma.movement.create({
          data: {
            type: "ENTRADA",
            documentNumber: INITIAL_DOC,
            productId: product.id,
            quantity: item.initialQty,
            unitPrice: item.cup,
            date: INITIAL_DATE,
          },
        });
      }
    } else if (iniMovement) {
      await prisma.movement.delete({ where: { id: iniMovement.id } });
    }
  }

  // Factura F170 — no afecta stock (ya salió antes del inventario)
  const kit = await prisma.product.findUnique({ where: { code: "13455" } });
  if (kit) {
    const existingDoc = await prisma.movement.findFirst({
      where: {
        documentNumber: "F170",
        productId: kit.id,
        type: "SALIDA",
      },
    });
    if (existingDoc) {
      await prisma.movement.update({
        where: { id: existingDoc.id },
        data: {
          quantity: 1,
          unitPrice: kit.averageUnitCost,
          date: new Date("2026-01-20T12:00:00.000Z"),
        },
      });
    } else {
      await prisma.movement.create({
        data: {
          type: "SALIDA",
          documentNumber: "F170",
          productId: kit.id,
          quantity: 1,
          unitPrice: kit.averageUnitCost,
          date: new Date("2026-01-20T12:00:00.000Z"),
        },
      });
    }
    await prisma.product.update({
      where: { id: kit.id },
      data: { stock: 0 },
    });
  }

  const allProducts = await prisma.product.findMany({
    where: { code: { in: products.map((p) => p.code) } },
  });
  const totalUnits = allProducts.reduce((sum, p) => sum + p.stock, 0);
  const inventoryValue = allProducts.reduce(
    (sum, p) => sum + p.stock * p.averageUnitCost,
    0
  );

  return {
    products: allProducts.length,
    totalUnits,
    inventoryValue,
  };
}
