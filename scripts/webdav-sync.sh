#!/usr/bin/env bash
# Sincroniza el document root hacia cPanel WebDAV (puerto 2078)
# sin pisar uploads/ ni .env del servidor.
#
# Uso:
#   WEBDAV_URL=https://lpaezsis.cl:2078/comex.lpaezsis.cl \
#   WEBDAV_USER=sistem29 WEBDAV_PASS='***' \
#   ./scripts/webdav-sync.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
URL="${WEBDAV_URL:-}"
USER="${WEBDAV_USER:-}"
PASS="${WEBDAV_PASS:-}"

if [[ -z "$URL" || -z "$USER" || -z "$PASS" ]]; then
  echo "Define WEBDAV_URL, WEBDAV_USER y WEBDAV_PASS." >&2
  echo "Ejemplo: https://lpaezsis.cl:2078/comex.lpaezsis.cl" >&2
  exit 1
fi

if ! command -v lftp >/dev/null 2>&1; then
  echo "Se requiere lftp (apt install lftp) para WebDAV/HTTPS puerto 2078." >&2
  exit 1
fi

EXCLUDE_FILE="$ROOT/deploy/webdav.exclude"
echo "Mirror → ${URL}  (excluye uploads/ y .env)"

# lftp mirror -R sube el árbol local. --exclude-glob evita adjuntos y secretos.
lftp -e "
set ssl:verify-certificate no;
open -u ${USER},${PASS} ${URL};
mirror -R --verbose=1 \
  --exclude-glob .git/ \
  --exclude-glob .env \
  --exclude-glob .env.local \
  --exclude-glob uploads/ \
  --exclude-glob uploads/** \
  --exclude-glob data/*.sqlite \
  --exclude-glob data/*.db \
  --exclude-glob vendor/ \
  --exclude-glob *.log \
  ${ROOT}/ .;
bye
"

echo "Listo. uploads/ y .env no se enviaron (ver ${EXCLUDE_FILE})."
