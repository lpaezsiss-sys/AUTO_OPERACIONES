#!/usr/bin/env bash
# Respaldo COMEX_lpaezsis — ejecutar en Git Bash (Windows).
#   bash scripts/descargar-respaldo-gitbash.sh
# O, sin clonar el repo:
#   curl -fsSL "https://comex.lpaezsis.cl/downloads/descargar-COMEX_lpaezsis.sh" | bash
set -euo pipefail

ZIP_NAME="${COMEX_BACKUP_ZIP:-COMEX_lpaezsis-2026-09-24.zip}"
ZIP_URL="${COMEX_BACKUP_URL:-https://comex.lpaezsis.cl/downloads/${ZIP_NAME}}"
DEST_DIR="${COMEX_BACKUP_DIR:-$PWD}"

mkdir -p "$DEST_DIR"
cd "$DEST_DIR"

echo "Descargando ${ZIP_URL}"
curl -fL --retry 3 --retry-delay 2 -o "$ZIP_NAME" "$ZIP_URL"

if curl -fsSL -o "${ZIP_NAME}.sha256" "${ZIP_URL}.sha256"; then
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum -c "${ZIP_NAME}.sha256"
  elif command -v shasum >/dev/null 2>&1; then
    EXPECTED="$(awk '{print $1}' "${ZIP_NAME}.sha256")"
    ACTUAL="$(shasum -a 256 "$ZIP_NAME" | awk '{print $1}')"
    if [[ "$EXPECTED" != "$ACTUAL" ]]; then
      echo "SHA256 no coincide" >&2
      exit 1
    fi
    echo "${ZIP_NAME}: OK"
  else
    echo "Aviso: no hay sha256sum/shasum; el ZIP se descargó sin verificar."
  fi
fi

echo
echo "Listo: ${DEST_DIR}/${ZIP_NAME}"
echo "Para extraer: unzip ${ZIP_NAME}"
echo "Clon Git (alternativa):"
echo "  git clone --branch freeze-comex-lpaezsis-2026-09-24 --single-branch --depth 1 \\"
echo "    https://github.com/lpaezsiss-sys/AUTO_OPERACIONES.git COMEX_lpaezsis"
echo "Primer respaldo (14-sep-2026): COMEX_lpaezsis-2026-09-14.zip / freeze-comex-lpaezsis-2026-09-14"
