import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();

/** Documento de ingreso inicial de stock al 01/01/2026 */
const INITIAL_DOC = "INI-2026-01-01";
const INITIAL_DATE = new Date("2026-01-01T12:00:00.000Z");

/**
 * Inventario inicial corregido (Saldo + CUP en CLP) al 01/01/2026.
 */
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

async function main() {
  let created = 0;
  let updated = 0;
  let movementsCreated = 0;
  let movementsUpdated = 0;
  let movementsDeleted = 0;

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

    if (existing) updated += 1;
    else created += 1;

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
        movementsUpdated += 1;
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
        movementsCreated += 1;
      }
    } else if (iniMovement) {
      await prisma.movement.delete({ where: { id: iniMovement.id } });
      movementsDeleted += 1;
    }
  }

  /**
   * Facturas / documentos posteriores al inventario inicial.
   * Factura electrónica Nº170 — Viña Morandé — 20/01/2026
   * Código en factura: A07-13455 (Kit Tensor Correa → SKU 13455)
   * Ref. Guía despacho N°87 (2025-12-17): el egreso físico es anterior al
   * inventario 01/01/2026 (saldo 0), por eso no vuelve a descontar stock.
   */
  const postInitialDocs = [
    {
      documentNumber: "F170",
      type: "SALIDA" as const,
      productCode: "13455",
      quantity: 1,
      date: new Date("2026-01-20T12:00:00.000Z"),
      affectsStock: false,
    },
  ];

  let docsCreated = 0;
  let docsUpdated = 0;

  for (const doc of postInitialDocs) {
    const product = await prisma.product.findUnique({
      where: { code: doc.productCode },
    });
    if (!product) {
      console.warn(`Documento ${doc.documentNumber}: producto ${doc.productCode} no encontrado`);
      continue;
    }

    const existingDoc = await prisma.movement.findFirst({
      where: {
        documentNumber: doc.documentNumber,
        productId: product.id,
        type: doc.type,
      },
    });

    if (existingDoc) {
      await prisma.movement.update({
        where: { id: existingDoc.id },
        data: {
          quantity: doc.quantity,
          unitPrice: product.averageUnitCost,
          date: doc.date,
        },
      });
      docsUpdated += 1;
    } else {
      await prisma.movement.create({
        data: {
          type: doc.type,
          documentNumber: doc.documentNumber,
          productId: product.id,
          quantity: doc.quantity,
          unitPrice: product.averageUnitCost,
          date: doc.date,
        },
      });
      docsCreated += 1;
    }

    if (doc.affectsStock === false) {
      const catalog = products.find((p) => p.code === doc.productCode);
      await prisma.product.update({
        where: { id: product.id },
        data: { stock: catalog?.initialQty ?? product.stock },
      });
      continue;
    }

    const catalog = products.find((p) => p.code === doc.productCode);
    const initialQty = catalog?.initialQty ?? 0;
    const laterMoves = await prisma.movement.findMany({
      where: {
        productId: product.id,
        documentNumber: { not: INITIAL_DOC },
      },
    });
    const stock = laterMoves.reduce((sum, m) => {
      return m.type === "ENTRADA" ? sum + m.quantity : sum - m.quantity;
    }, initialQty);

    await prisma.product.update({
      where: { id: product.id },
      data: { stock },
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

  console.log(
    `Catálogo: ${created} creados, ${updated} actualizados (${products.length} productos).`
  );
  console.log(
    `Ingreso ${INITIAL_DOC}: ${movementsCreated} creados, ${movementsUpdated} actualizados, ${movementsDeleted} eliminados.`
  );
  console.log(
    `Documentos posteriores: ${docsCreated} creados, ${docsUpdated} actualizados.`
  );
  console.log(
    `Stock total: ${totalUnits} · Valor inventario: $${inventoryValue.toLocaleString("es-CL")}`
  );
}

main()
  .then(async () => {
    await prisma.$disconnect();
  })
  .catch(async (error) => {
    console.error(error);
    await prisma.$disconnect();
    process.exit(1);
  });
