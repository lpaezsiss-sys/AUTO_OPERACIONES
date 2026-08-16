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

search="$(json "$base/api/crear_cotizacion.php?action=buscar_producto&q=13451")"
echo "$search" | grep -q '"sku":"13451"'
echo "$search" > /tmp/crm-buscar.json
json "$base/api/empresas.php" > /tmp/crm-empresas.json
payload="$(python3 - <<'PY'
import json
search = json.load(open("/tmp/crm-buscar.json"))
emps = json.load(open("/tmp/crm-empresas.json"))
prod = [p for p in search["productos"] if p.get("sku") == "13451"][0]
print(json.dumps({
    "empresa_id": int(emps["empresas"][0]["id"]),
    "estado": "borrador",
    "items": [{"producto_id": int(prod["id"]), "cantidad": 1}],
}))
PY
)"
saved="$(json -X POST "$base/api/crear_cotizacion.php?action=guardar" -d "$payload")"
echo "$saved" | grep -q '"folio":"COT-'
echo "$saved" | grep -q '"ok":true'

json "$base/api/configuracion.php" | grep -q '"razon_social"'
json "$base/api/vendedores.php" | grep -q '"comision_porcentaje"'
json "$base/api/comisiones.php" | grep -q '"comisiones"'

json "$base/api/reportes.php?tipo=resumen_kpis" | grep -q '"monto_cotizado"'
json "$base/api/reportes.php?tipo=pipeline" | grep -q '"etapas"'
json "$base/api/reportes.php?tipo=vendedores" | grep -q '"tasa_cierre_pct"'
json "$base/api/reportes.php?tipo=productos_top" | grep -q '"proporcion"'

echo "$saved" > /tmp/crm-cot-saved.json
pdf_id="$(python3 -c 'import json; print(json.load(open("/tmp/crm-cot-saved.json"))["id"])')"
pdf_head="$(curl -sS -b "$COOKIE" -c "$COOKIE" "$base/api/cotizacion_pdf.php?id=${pdf_id}" | head -c 8)"
test "$pdf_head" = "%PDF-1.4"

echo "HTTP smoke OK"
