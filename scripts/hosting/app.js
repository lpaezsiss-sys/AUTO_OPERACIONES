/**
 * Punto de entrada compatible con cPanel "Application startup file".
 * Uso: Application startup file = app.js
 */
const { existsSync, mkdirSync } = require("fs");
const { spawnSync } = require("child_process");
const path = require("path");

// Cargar .env si existe (dotenv puede no estar en standalone mínimo)
try {
  require("dotenv").config({ path: path.join(__dirname, ".env") });
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
    spawnSync("npx", ["tsx", "prisma/seed.ts"], {
      cwd: __dirname,
      stdio: "inherit",
      shell: true,
    });
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
