# Agente — COMEX / ecosistema LPAEZSIS

- PHP **8.1.x** en BlueHosting (cPanel, CloudLinux CageFS). MultiPHP 8.1 para `comex.lpaezsis.cl`.
- Extensiones: `pdo_sqlite`, `sqlite3`, `pdo_mysql`, `mbstring`, `gd`, `zip`, `fileinfo`, `curl`, `json`.
- Namespaces PSR-4 `Crm\` → `src/Crm/`. Linux es case-sensitive: el directorio es `Crm`, nunca `crm`.
- Inventario: solo `SELECT` sobre SQLite `INV_SQLITE_PATH=/home/sistem29/app/data/prod.db` (tabla Prisma `Product`). No escribir stock.
- PDO MySQL de COMEX con prepared statements. `ATTR_EMULATE_PREPARES = false`.
- `.env` y `config/` no son públicos: `.htaccess` responde **403**. Forzar HTTPS.
- `uploads/` permisos **755/775**, excluido de WebDAV (puerto **2078**). Ver `.webdavignore` y `scripts/webdav-sync.sh`.
- No requiere Composer en el servidor: autoload propio en `Crm\Autoloader`.
