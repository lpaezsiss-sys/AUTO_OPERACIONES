#!/usr/bin/env bash
# Empaqueta la app para subir a BlueHosting / cPanel Node.js / VPS
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/deploy"
cd "$ROOT"

echo "==> Instalando dependencias y generando build standalone…"
npm ci
npx prisma generate
npm run build

echo "==> Armando carpeta deploy/…"
rm -rf "$OUT"
mkdir -p "$OUT"

# Servidor standalone de Next.js
cp -a .next/standalone/. "$OUT/"
# No subir .env local al paquete de hosting
rm -f "$OUT/.env"

# Assets estáticos y públicos
mkdir -p "$OUT/.next"
cp -a .next/static "$OUT/.next/static"
cp -a public "$OUT/public"

# Prisma (migraciones + schema) y datos
mkdir -p "$OUT/prisma" "$OUT/data"
cp -a prisma/schema.prisma "$OUT/prisma/"
cp -a prisma/migrations "$OUT/prisma/"
cp -a prisma/seed.ts "$OUT/prisma/"
mkdir -p "$OUT/scripts"
cp "$ROOT/scripts/seed-admin.mjs" "$OUT/scripts/seed-admin.mjs"
cp "$ROOT/scripts/seed-inventory.cjs" "$OUT/scripts/seed-inventory.cjs"
# Cliente Prisma ya viene en node_modules del standalone, pero aseguramos generate en destino

# Scripts de arranque en hosting
cp "$ROOT/scripts/hosting/start.sh" "$OUT/start.sh"
cp "$ROOT/scripts/hosting/ecosystem.config.cjs" "$OUT/ecosystem.config.cjs"
cp "$ROOT/scripts/hosting/app.js" "$OUT/app.js"
cp "$ROOT/.env.production.example" "$OUT/.env.example"
chmod +x "$OUT/start.sh"

# Dependencias runtime que el standalone a veces no copia (cPanel / seed / migrate)
mkdir -p "$OUT/node_modules"
for pkg in jose bcryptjs dotenv prisma tsx; do
  if [ -d "$ROOT/node_modules/$pkg" ]; then
    rm -rf "$OUT/node_modules/$pkg"
    cp -a "$ROOT/node_modules/$pkg" "$OUT/node_modules/$pkg"
  fi
done
# Query engines (BlueHosting: debian-openssl-1.0.x)
if [ -d "$ROOT/node_modules/.prisma/client" ]; then
  mkdir -p "$OUT/node_modules/.prisma"
  rm -rf "$OUT/node_modules/.prisma/client"
  cp -a "$ROOT/node_modules/.prisma/client" "$OUT/node_modules/.prisma/"
fi
if [ -d "$ROOT/node_modules/@prisma/client" ]; then
  mkdir -p "$OUT/node_modules/@prisma"
  rm -rf "$OUT/node_modules/@prisma/client"
  cp -a "$ROOT/node_modules/@prisma/client" "$OUT/node_modules/@prisma/"
fi
if [ -d "$ROOT/node_modules/@prisma/engines" ]; then
  mkdir -p "$OUT/node_modules/@prisma"
  cp -a "$ROOT/node_modules/@prisma/engines" "$OUT/node_modules/@prisma/"
fi
mkdir -p "$OUT/node_modules/.bin"
for bin in prisma tsx; do
  if [ -e "$ROOT/node_modules/.bin/$bin" ]; then
    cp -a "$ROOT/node_modules/.bin/$bin" "$OUT/node_modules/.bin/$bin"
  fi
done

# package.json mínimo para prisma CLI en el servidor (seed / migrate)
node <<'NODE'
const fs = require("fs");
const path = require("path");
const rootPkg = JSON.parse(fs.readFileSync("package.json", "utf8"));
const out = path.join("deploy", "package.hosting.json");
fs.writeFileSync(
  out,
  JSON.stringify(
    {
      name: rootPkg.name,
      private: true,
      scripts: {
        start: "bash start.sh",
        "db:migrate": "prisma migrate deploy",
        "db:seed": "tsx prisma/seed.ts",
        "db:seed-admin": "node scripts/seed-admin.mjs",
        "db:generate": "prisma generate",
      },
      dependencies: {
        "@prisma/client": rootPkg.dependencies["@prisma/client"],
        bcryptjs: rootPkg.dependencies.bcryptjs,
        dotenv: rootPkg.dependencies.dotenv,
        jose: rootPkg.dependencies.jose,
        next: rootPkg.dependencies.next,
        prisma: rootPkg.dependencies.prisma,
        react: rootPkg.dependencies.react,
        "react-dom": rootPkg.dependencies["react-dom"],
        tsx: rootPkg.devDependencies.tsx,
      },
      prisma: rootPkg.prisma,
      engines: { node: ">=20" },
    },
    null,
    2
  ) + "\n"
);
NODE

# Si el standalone no trae package.json usable, dejamos package.hosting.json como referencia
if [ ! -f "$OUT/package.json" ]; then
  cp "$OUT/package.hosting.json" "$OUT/package.json"
fi

# README de despliegue dentro del paquete
cp "$ROOT/DEPLOY-BLUEHOSTING.md" "$OUT/README-DESPLIEGUE.md"

echo ""
echo "Listo: $OUT"
echo "Siguiente: lee DEPLOY-BLUEHOSTING.md y sube la carpeta deploy/ al hosting."
