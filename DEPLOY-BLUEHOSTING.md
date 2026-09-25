# Despliegue en BlueHosting (cPanel) — COMEX LPAEZSIS

PHP del hosting: **8.1.x**. En MultiPHP Manager asignar 8.1 al subdominio. Extensiones CageFS: `pdo_sqlite`, `sqlite3`, `pdo_mysql`, `mbstring`, `gd`, `zip`, `fileinfo`, `curl`, `json`.

## 1. Subdominio

Crear `comex.lpaezsis.cl` apuntando a un document root, por ejemplo:

`public_html/comex.lpaezsis.cl/`

Subir el contenido de este repositorio (no hace falta `tests/` ni `.git`). El document root **es** la raíz del proyecto (`index.php` y `.htaccess` al mismo nivel).

Ecosistema:

- `https://crm.lpaezsis.cl` — CRM
- `https://inventario.lpaezsis.cl` — inventario (SQLite Prisma)
- `https://comex.lpaezsis.cl` — COMEX (esta app)

## 2. Archivo `.env`

1. En el document root: `cp .env.production .env` (o `bash scripts/install-env.sh`).
2. Completar `DB_*` de cPanel (MySQL de COMEX).
3. Dejar **sin cambiar**:

```env
INV_SQLITE_PATH=/home/sistem29/app/data/prod.db
```

Esa ruta es el SQLite de producción de inventario (`Product` + `Movement`). COMEX lee el catálogo y, al **confirmar** una operación, escribe `ENTRADA` (importación) o `SALIDA` (exportación) con WAL. El archivo **y** `/home/sistem29/app/data/` deben ser escribibles por `sistem29` (CageFS) para `prod.db-wal` / `prod.db-shm`.

```env
INV_SQLITE_WAL=1
INV_SQLITE_BUSY_TIMEOUT_MS=8000
INV_SQLITE_RETRIES=8
```

`.htaccess` deniega HTTP a `.env` (403). Aun así, **no** sincronizar `.env` por WebDAV para no pisar secretos del servidor.

## 3. `.htaccess`

La raíz incluye:

- 403 a `.env`, `*.ini`, `composer.json`, `config/`, `src/`, `includes/`, `sql/`, `data/`
- HTTPS forzado (`RewriteCond %{HTTPS}` + `X-Forwarded-Proto`)
- `www.` → canónico en hosts `*.lpaezsis.cl`
- Front controller hacia `index.php` si no existe el archivo

Cada carpeta sensible tiene su propio `.htaccess` con `Require all denied`.

## 4. Permisos `uploads/`

```bash
bash scripts/ensure_uploads_perms.sh
```

Directorios `775` (aceptable `755`), archivos `644`. Imágenes de ficha: `uploads/comex/productos/`. PDF de operación: `uploads/comex/pdf/`. `.htaccess` bloquea ejecución PHP.

## 5. WebDAV (puerto 2078)

No hacer mirror completo. Excluir `uploads/` y `.env`:

```bash
WEBDAV_URL=https://lpaezsis.cl:2078/comex.lpaezsis.cl \
WEBDAV_USER=sistem29 WEBDAV_PASS='…' \
bash scripts/webdav-sync.sh
```

Lista de exclusiones: `deploy/webdav.exclude`, `.webdavignore`, marker `uploads/.webdav-exclude`.

## 6. Comprobar

- `https://comex.lpaezsis.cl/api/health.php` → JSON `"compat":"8.1"`, extensiones `true`, `"inventory"` según visibilidad de `prod.db`.
- `https://comex.lpaezsis.cl/.env` → **403 Forbidden**
- `https://comex.lpaezsis.cl/config/` → **403 Forbidden**
