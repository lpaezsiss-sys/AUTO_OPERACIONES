/**
 * Crea/actualiza solo el usuario admin (sin tocar productos).
 * Uso en cPanel/SSH:
 *   node scripts/seed-admin.mjs
 * o desde la raíz del deploy:
 *   node prisma/seed-admin.mjs
 */
const path = require("path");
const { PrismaClient } = require("@prisma/client");
const bcrypt = require("bcryptjs");

try {
  require("dotenv").config({ path: path.join(process.cwd(), ".env") });
} catch {
  // ignore
}

const prisma = new PrismaClient();

async function main() {
  const username = process.env.SEED_USER || "admin";
  const password = process.env.SEED_PASSWORD || "inventario2026";
  const passwordHash = await bcrypt.hash(password, 10);

  await prisma.user.upsert({
    where: { username },
    create: {
      username,
      passwordHash,
      name: "Administrador",
    },
    update: {
      passwordHash,
      name: "Administrador",
    },
  });

  console.log(`OK: usuario ${username} / ${password}`);
}

main()
  .catch((err) => {
    console.error(err);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
