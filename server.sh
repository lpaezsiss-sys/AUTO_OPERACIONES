#!/usr/bin/env bash
# Servidor HTTP local del CRM (PHP integrado).
# Uso: ./server.sh
#      PORT=8000 ./server.sh

set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"
PORT="${PORT:-8000}"

if ! command -v php >/dev/null 2>&1; then
  echo "FAIL: php no está en PATH. Instala PHP 7.4+ CLI."
  exit 1
fi

echo "CRM LPAEZsis — http://localhost:${PORT}/"
echo "  login:     http://localhost:${PORT}/login.php"
echo "  cotizador: http://localhost:${PORT}/cotizador.php"
echo "  health:    http://localhost:${PORT}/api/health.php"
echo "Ctrl+C para detener."
echo

exec php -S "localhost:${PORT}" -t "$ROOT" "$ROOT/router.php"
