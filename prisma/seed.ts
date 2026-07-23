import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();

/** Documento de ingreso inicial de stock al 01/01/2026 */
const INITIAL_DOC = "INI-2026-01-01";
const INITIAL_DATE = new Date("2026-01-01T12:00:00.000Z");

/**
 * Catálogo con stock inicial (Cant Total).
 * CUP/precio unitario parten en 0 (sin costo en la planilla de importación).
 */
const products = [
  {
    code: "14453",
    name: "Sonic 100/150 Bearing Cartridge Kit",
    description: "Sonic 100/150 Bearing Cartridge Kit (PN #14453)",
    initialQty: 1,
  },
  {
    code: "13451",
    name: "Correa Sonic 16 GRV 13451",
    description: "Correa Sonic 16 GRV 13451",
    initialQty: 10,
  },
  {
    code: "13514",
    name: "Correa Sonic 16 GRV 13514",
    description: "Correa Sonic 16 GRV 13514",
    initialQty: 11,
  },
  {
    code: "13555",
    name: "Correa Sonic 16 GRV 13555",
    description: "Correa Sonic 16 GRV 13555",
    initialQty: 3,
  },
  {
    code: "13474",
    name: "Correa Sonic 16 GRV 13474",
    description: "Correa Sonic 16 GRV 13474",
    initialQty: 5,
  },
  {
    code: "12638",
    name: "Sonic 85/150 Impeller",
    description: "Sonic 85/150 Impeller (PN #12638)",
    initialQty: 1,
  },
  {
    code: "14452",
    name: "Sonic 70/85 Bearing Cartridge Kit",
    description: "Sonic 70/85 Bearing Cartridge Kit (PN #14452)",
    initialQty: 1,
  },
  {
    code: "13455",
    name: "Kit Tensor Correa",
    description: "Kit Tensor Correa",
    initialQty: 0,
  },
  {
    code: "10317",
    name: "Filtro SONIC 85 Poly",
    description: "Filtro SONIC 85 Poly",
    initialQty: 1,
  },
  {
    code: "13900A-150",
    name: "Sonic Pulley 13900A-150",
    description: "Sonic Pulley (PN #13900A-150)",
    initialQty: 2,
  },
  {
    code: "13900A-152",
    name: "Sonic Pulley 13900A-152",
    description: "Sonic Pulley (PN #13900A-152)",
    initialQty: 0,
  },
  {
    code: "13900A-160",
    name: "Sonic Pulley 13900A-160",
    description: "Sonic Pulley (PN #13900A-160)",
    initialQty: 1,
  },
  {
    code: "14454",
    name: "Blower S85 Completo",
    description: "Blower S85 Completo",
    initialQty: 0,
  },
  {
    code: "10976",
    name: "Filtro Completo Con Indicador de Saturacion",
    description: "Filtro Completo Con Indicador de Saturacion",
    initialQty: 1,
  },
  {
    code: "10434",
    name: 'Flexible 3" Largo 12 Pies',
    description: 'Flexible 3" Largo 12 Pies',
    initialQty: 0,
  },
  {
    code: "10435",
    name: 'Flexible 4" Largo 12 Pies',
    description: 'Flexible 4" Largo 12 Pies',
    initialQty: 0,
  },
  {
    code: "A08-10100",
    name: "CINTA Doble Fas CMC 10730",
    description: "CINTA Doble Fas CMC 10730 A25 L 33m",
    initialQty: 49,
  },
  {
    code: "A08-10101",
    name: "CMC 10431 RED 25 mm x 33 mt",
    description: "CMC 10431 RED Ancho 25 mm x 33 mt",
    initialQty: 24,
  },
] as const;

async function main() {
  let created = 0;
  let updated = 0;
  let movementsCreated = 0;
  let movementsSkipped = 0;

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
          },
        })
      : await prisma.product.create({
          data: {
            code: item.code,
            name: item.name,
            description: item.description,
            stock: 0,
            averageUnitCost: 0,
          },
        });

    if (existing) updated += 1;
    else created += 1;

    if (item.initialQty <= 0) {
      continue;
    }

    const alreadySeeded = await prisma.movement.findFirst({
      where: {
        productId: product.id,
        documentNumber: INITIAL_DOC,
        type: "ENTRADA",
      },
    });

    if (alreadySeeded) {
      movementsSkipped += 1;
      continue;
    }

    await prisma.$transaction(async (tx) => {
      await tx.movement.create({
        data: {
          type: "ENTRADA",
          documentNumber: INITIAL_DOC,
          productId: product.id,
          quantity: item.initialQty,
          unitPrice: 0,
          date: INITIAL_DATE,
        },
      });

      // Ajusta stock al inventario inicial si aún no había movimientos INI.
      // Si el producto ya tenía stock de un seed previo en 0, queda en initialQty.
      await tx.product.update({
        where: { id: product.id },
        data: {
          stock: product.stock + item.initialQty,
        },
      });
    });

    movementsCreated += 1;
  }

  const totalUnits = products.reduce((sum, p) => sum + p.initialQty, 0);

  console.log(
    `Catálogo: ${created} creados, ${updated} actualizados (${products.length} productos).`
  );
  console.log(
    `Ingreso inicial ${INITIAL_DOC} (01/01/2026): ${movementsCreated} movimientos nuevos, ${movementsSkipped} ya existían.`
  );
  console.log(`Unidades totales de importación: ${totalUnits}`);
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
