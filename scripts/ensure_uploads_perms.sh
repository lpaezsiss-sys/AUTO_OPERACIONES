#!/usr/bin/env bash
# Permisos CageFS: directorios uploads 0775 (aceptable 0755), archivos de control 0644.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
UPLOADS="$ROOT/uploads"

mkdir -p "$UPLOADS/comex"
chmod 775 "$UPLOADS" "$UPLOADS/comex"

find "$UPLOADS" -type d -exec chmod 775 {} \;
find "$UPLOADS" -type f \( -name '.htaccess' -o -name '.gitkeep' -o -name '.webdav-exclude' \) -exec chmod 644 {} \;

echo "uploads/ $(stat -c '%a' "$UPLOADS")  uploads/comex/ $(stat -c '%a' "$UPLOADS/comex")"
