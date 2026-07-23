import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();

/**
 * Catálogo inicial (código + descripción).
 * Stock y CUP parten en 0; se actualizan con documentos de entrada/salida.
 */
const products = [
  {
    code: "14453",
    name: "Sonic 100/150 Bearing Cartridge Kit",
    description: "Sonic 100/150 Bearing Cartridge Kit (PN #14453)",
  },
  {
    code: "13451",
    name: "Correa Sonic 16 GRV 13451",
    description: "Correa Sonic 16 GRV 13451",
  },
  {
    code: "13514",
    name: "Correa Sonic 16 GRV 13514",
    description: "Correa Sonic 16 GRV 13514",
  },
  {
    code: "13474",
    name: "Correa Sonic 16 GRV 13474",
    description: "Correa Sonic 16 GRV 13474",
  },
  {
    code: "13555",
    name: "Correa Sonic 16 GRV 13555",
    description: "Correa Sonic 16 GRV 13555",
  },
  {
    code: "12638",
    name: "Sonic 85/150 Impeller",
    description: "Sonic 85/150 Impeller (PN #12638)",
  },
  {
    code: "14452",
    name: "Sonic 70/85 Bearing Cartridge Kit",
    description: "Sonic 70/85 Bearing Cartridge Kit (PN #14452)",
  },
  {
    code: "13455",
    name: "Kit Tensor Correa",
    description: "Kit Tensor Correa",
  },
  {
    code: "10317",
    name: "Filtro SONIC 85 Poly",
    description: "Filtro SONIC 85 Poly",
  },
  {
    code: "13900A-150",
    name: "Sonic Pulley 13900A-150",
    description: "Sonic Pulley (PN #13900A-150)",
  },
  {
    code: "13900A-152",
    name: "Sonic Pulley 13900A-152",
    description: "Sonic Pulley (PN #13900A-152)",
  },
  {
    code: "13900A-160",
    name: "Sonic Pulley 13900A-160",
    description: "Sonic Pulley (PN #13900A-160)",
  },
  {
    code: "14454",
    name: "Blower S85 Completo",
    description: "Blower S85 Completo",
  },
  {
    code: "10434",
    name: 'Flexible 3" Largo 12 Pies',
    description: 'Flexible 3" Largo 12 Pies',
  },
  {
    code: "10435",
    name: 'Flexible 4" Largo 12 Pies',
    description: 'Flexible 4" Largo 12 Pies',
  },
  {
    code: "10976",
    name: "Filtro Completo Con Indicador de Saturacion",
    description: "Filtro Completo Con Indicador de Saturacion",
  },
  {
    code: "A08-10100",
    name: "CINTA Doble Fas CMC 10730",
    description: "CINTA Doble Fas CMC 10730 A25 L 33m",
  },
  {
    code: "A08-10101",
    name: "CMC 10431 RED 25 mm x 33 mt",
    description: "CMC 10431 RED Ancho 25 mm x 33 mt",
  },
] as const;

async function main() {
  let created = 0;
  let updated = 0;

  for (const product of products) {
    const existing = await prisma.product.findUnique({
      where: { code: product.code },
    });

    if (existing) {
      await prisma.product.update({
        where: { code: product.code },
        data: {
          name: product.name,
          description: product.description,
        },
      });
      updated += 1;
    } else {
      await prisma.product.create({
        data: {
          code: product.code,
          name: product.name,
          description: product.description,
          stock: 0,
          averageUnitCost: 0,
        },
      });
      created += 1;
    }
  }

  console.log(
    `Catálogo cargado: ${created} creados, ${updated} actualizados (${products.length} en total).`
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
