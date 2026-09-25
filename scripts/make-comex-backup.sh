#!/usr/bin/env bash
# Arma un ZIP del árbol COMEX versionado (sin .env, SQLite ni uploads).
#
# Uso:
#   bash scripts/make-comex-backup.sh
#   bash scripts/make-comex-backup.sh /tmp/COMEX_lpaezsis.zip
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

STAMP="${COMEX_BACKUP_STAMP:-$(date -u +%Y-%m-%d)}"
PREFIX="${COMEX_BACKUP_PREFIX:-COMEX_lpaezsis}"
OUT="${1:-$ROOT/downloads/${PREFIX}-${STAMP}.zip}"

mkdir -p "$(dirname "$OUT")"

git archive --format=zip --prefix="${PREFIX}/" -o "$OUT" HEAD

python3 - "$OUT" "$PREFIX" <<'PY'
import hashlib, sys, zipfile
from pathlib import Path
out, prefix = Path(sys.argv[1]), sys.argv[2]
bad = []
with zipfile.ZipFile(out) as z:
    names = z.namelist()
    for n in names:
        base = n.split("/")[-1]
        if base in {".env", ".env.local"} or n.endswith(".sqlite") or n.endswith(".db"):
            bad.append(n)
if bad:
    raise SystemExit("El ZIP no debe incluir secretos ni bases: " + ", ".join(bad))
if f"{prefix}/index.php" not in names:
    raise SystemExit("Falta index.php en el ZIP")
digest = hashlib.sha256(out.read_bytes()).hexdigest()
Path(str(out) + ".sha256").write_text(f"{digest}  {out.name}\n")
print(f"ZIP {out} ({out.stat().st_size} bytes)")
print(f"SHA256 {digest}")
print(f"archivos {len(names)}")
PY
