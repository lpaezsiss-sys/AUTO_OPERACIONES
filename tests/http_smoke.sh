#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PORT="${PORT:-8080}"
COOKIE="/tmp/crm-http-smoke.cookie"
rm -f "$COOKIE"

base="http://127.0.0.1:${PORT}"

json() {
  curl -sS -c "$COOKIE" -b "$COOKIE" -H "Content-Type: application/json" -H "Accept: application/json" "$@"
}

health="$(json "$base/api/health.php")"
echo "$health" | grep -q '"compat":"7.4"'
echo "$health" | grep -q '"ok":true'

json -X POST "$base/api/auth.php" -d '{"email":"ivan.p@example.net","password":"Lpaezsis.2026"}' | grep -q '"ok":true'

dash="$(json "$base/api/dashboard.php")"
echo "$dash" | grep -q '"pipeline_clp"'

prod="$(json "$base/api/productos.php")"
echo "$prod" | grep -q '"codigo":"13451"'

code="$(curl -sS -o /tmp/crm-prod-post.json -w "%{http_code}" -c "$COOKIE" -b "$COOKIE" -H "Content-Type: application/json" -X POST "$base/api/productos.php" -d '{}')"
test "$code" = "405"

echo "HTTP smoke OK"
