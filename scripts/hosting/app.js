/**
 * Punto de entrada compatible con cPanel "Application startup file".
 * Uso: Application startup file = app.js
 */
const { existsSync, mkdirSync } = require("fs");
const { spawnSync } = require("child_process");
const path = require("path");

// Cargar .env si existe (dotenv puede no estar en standalone mínimo)
try {
  // override: true — en cPanel las env del panel no deben pisar un .env corregido
  require("dotenv").config({
    path: path.join(__dirname, ".env"),
    override: true,
  });
} catch {
  // ignore
}

mkdirSync(path.join(__dirname, "data"), { recursive: true });

if (process.env.SKIP_DB_SETUP !== "true") {
  const prisma = spawnSync("npx", ["prisma", "generate"], {
    cwd: __dirname,
    stdio: "inherit",
    shell: true,
  });
  if (prisma.status !== 0) {
    console.warn("Aviso: prisma generate falló; continúa el arranque…");
  }
  const migrate = spawnSync("npx", ["prisma", "migrate", "deploy"], {
    cwd: __dirname,
    stdio: "inherit",
    shell: true,
  });
  if (migrate.status !== 0) {
    console.warn("Aviso: prisma migrate deploy falló; revisa DATABASE_URL.");
  }
  if (process.env.RUN_SEED === "true") {
    const attempts = [
      ["node", ["scripts/seed-inventory.cjs"]],
      ["node", ["node_modules/tsx/dist/cli.mjs", "prisma/seed.ts"]],
      ["npx", ["tsx", "prisma/seed.ts"]],
      ["node", ["scripts/seed-admin.mjs"]],
    ];
    let seeded = false;
    for (const [cmd, args] of attempts) {
      const result = spawnSync(cmd, args, {
        cwd: __dirname,
        stdio: "inherit",
        shell: true,
        env: process.env,
      });
      if (result.status === 0) {
        seeded = true;
        break;
      }
      console.warn(`Seed intento falló: ${cmd} ${args.join(" ")}`);
    }
    if (!seeded) {
      console.warn("No se pudo ejecutar ningún seed.");
    }
  }
}

process.env.NODE_ENV = process.env.NODE_ENV || "production";
process.env.PORT = process.env.PORT || "3000";
// En cPanel/Passenger NO fijar HOSTNAME=0.0.0.0 (rompe redirects a https://0.0.0.0:PORT).
// Solo para VPS/PM2 local si hace falta escuchar en todas las interfaces:
if (
  process.env.HOSTNAME === "0.0.0.0" ||
  process.env.HOSTNAME === "127.0.0.1"
) {
  delete process.env.HOSTNAME;
}

if (!existsSync(path.join(__dirname, "server.js"))) {
  console.error("No se encontró server.js (build standalone incompleto).");
  process.exit(1);
}

require("./server.js");
