#!/usr/bin/env bash
# Crea .env desde la plantilla de producción si aún no existe.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
if [[ -f "$ROOT/.env" ]]; then
  echo ".env ya existe — no se pisa (secretos locales / servidor)."
else
  cp "$ROOT/.env.production" "$ROOT/.env"
  echo "Creado .env desde .env.production (completa DB_* en cPanel)."
fi
bash "$ROOT/scripts/ensure_uploads_perms.sh"
