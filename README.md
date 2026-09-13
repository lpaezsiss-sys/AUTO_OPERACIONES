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

`INV_SQLITE_PATH` queda fijado al SQLite de inventario (solo lectura):

```env
INV_SQLITE_PATH=/home/sistem29/app/data/prod.db
```

No commitear `.env` con contraseñas. Apache responde **403** a `.env` y a `config/`, `src/`, `includes/`, `sql/`, `data/`.

## Arquitectura PHP

- Namespaces PSR-4 `Crm\` → `src/Crm/` (case-sensitive en Linux).
- Autoload sin Composer en el servidor (`Crm\Autoloader`).
- Extensiones: `pdo_sqlite`, `sqlite3`, `pdo_mysql`, `mbstring`, `gd`, `zip`, `fileinfo`, `curl`, `json`.
- Inventario: `Crm\Inventory\InventarioStock` — `SELECT` sobre Prisma `Product`.

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
