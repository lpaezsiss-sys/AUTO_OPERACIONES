#!/usr/bin/env bash
# Arranque en BlueHosting / cPanel / VPS
set -euo pipefail

cd "$(dirname "$0")"

if [ ! -f .env ]; then
  echo "ERROR: falta archivo .env (copia .env.example y configura valores de producción)."
  exit 1
fi

# Carga variables para este script
set -a
# shellcheck disable=SC1091
source .env
set +a

mkdir -p data

echo "==> Prisma generate + migrate…"
npx prisma generate
npx prisma migrate deploy

if [ "${RUN_SEED:-false}" = "true" ]; then
  echo "==> Ejecutando seed…"
  npx tsx prisma/seed.ts
fi

export NODE_ENV=production
export PORT="${PORT:-3000}"
export HOSTNAME="${HOSTNAME:-0.0.0.0}"

echo "==> Iniciando Next.js en ${HOSTNAME}:${PORT}…"
exec node server.js
