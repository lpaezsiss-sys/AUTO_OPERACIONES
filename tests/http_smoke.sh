#!/usr/bin/env bash
# Humo HTTP: 403 a .env/config, 200 en portada y health.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${PORT:-8765}"
HOST="127.0.0.1"

php -S "${HOST}:${PORT}" -t "$ROOT" "$ROOT/router.php" >/tmp/comex-php-server.log 2>&1 &
PID=$!
cleanup() { kill "$PID" 2>/dev/null || true; }
trap cleanup EXIT

for _ in $(seq 1 30); do
  if curl -sf "http://${HOST}:${PORT}/api/health.php" >/dev/null 2>&1; then
    break
  fi
  sleep 0.1
done

code_env="$(curl -s -o /tmp/comex-env.out -w "%{http_code}" "http://${HOST}:${PORT}/.env")"
code_cfg="$(curl -s -o /tmp/comex-cfg.out -w "%{http_code}" "http://${HOST}:${PORT}/config/")"
code_src="$(curl -s -o /tmp/comex-src.out -w "%{http_code}" "http://${HOST}:${PORT}/src/Crm/Http.php")"
code_health="$(curl -s -o /tmp/comex-health.json -w "%{http_code}" "http://${HOST}:${PORT}/api/health.php")"
code_home="$(curl -s -o /tmp/comex-home.out -w "%{http_code}" "http://${HOST}:${PORT}/")"

echo "GET /.env            -> ${code_env}"
echo "GET /config/         -> ${code_cfg}"
echo "GET /src/Crm/Http.php -> ${code_src}"
echo "GET /api/health.php  -> ${code_health}"
echo "GET /                -> ${code_home}"

fail=0
[[ "$code_env" == "403" ]] || { echo "FAIL /.env debería ser 403"; fail=1; }
[[ "$code_cfg" == "403" ]] || { echo "FAIL /config/ debería ser 403"; fail=1; }
[[ "$code_src" == "403" ]] || { echo "FAIL /src/Crm/Http.php debería ser 403"; fail=1; }
[[ "$code_health" == "200" ]] || { echo "FAIL /api/health.php debería ser 200"; fail=1; }
[[ "$code_home" == "200" ]] || { echo "FAIL / debería ser 200"; fail=1; }

if [[ "$fail" -eq 0 ]]; then
  echo "PASS http smoke"
fi
exit "$fail"
