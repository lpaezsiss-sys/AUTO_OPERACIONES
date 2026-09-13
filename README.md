# AUTO_OPERACIONES — COMEX LPAEZSIS

Base PHP **8.1.x** para el ecosistema LPAEZSIS en BlueHosting (cPanel, CloudLinux CageFS):

- [crm.lpaezsis.cl](https://crm.lpaezsis.cl)
- [inventario.lpaezsis.cl](https://inventario.lpaezsis.cl)
- COMEX (`comex.lpaezsis.cl`)

## Entorno

Copiar plantilla y completar credenciales MySQL de cPanel:

```bash
bash scripts/install-env.sh
```

`INV_SQLITE_PATH` apunta al SQLite de inventario. La lectura usa `query_only`; al confirmar una importación/exportación se escribe `Movement` con WAL y `busy_timeout`:

```env
INV_SQLITE_PATH=/home/sistem29/app/data/prod.db
INV_SQLITE_WAL=1
INV_SQLITE_BUSY_TIMEOUT_MS=8000
```

No commitear `.env` con contraseñas. Apache responde **403** a `.env` y a `config/`, `src/`, `includes/`, `sql/`, `data/`.

## Arquitectura PHP

- Namespaces PSR-4 `Crm\` → `src/Crm/` (case-sensitive en Linux).
- Autoload sin Composer en el servidor (`Crm\Autoloader`).
- Extensiones: `pdo_sqlite`, `sqlite3`, `pdo_mysql`, `mbstring`, `gd`, `zip`, `fileinfo`, `curl`, `json`.
- Inventario: `Crm\Inventory\SqliteConnector` — Prisma `Product`/`Movement`. Fichas y operaciones COMEX se vinculan por SKU.
- Landed cost: `landed.php` — prorrateo FOB, IVA aduanero 19% sobre CIF, Estimada vs Real, PDF.
- Pipeline: `operaciones.php` — 13 etapas (Evaluación/Ejecución), Kanban y lista, bitácora.

## Uploads y WebDAV

`uploads/` usa permisos **755/775** y está excluido de sincronización WebDAV (puerto **2078**):

```bash
bash scripts/ensure_uploads_perms.sh
# ver .webdavignore, deploy/webdav.exclude, scripts/webdav-sync.sh
```

## Comprobar en local

```bash
php tests/run.php
php scripts/check_deploy_paths.php
bash tests/http_smoke.sh
php -S localhost:8080 router.php
```

Health: `http://localhost:8080/api/health.php`

Despliegue: [DEPLOY-BLUEHOSTING.md](DEPLOY-BLUEHOSTING.md).
