# Agente — COMEX / ecosistema LPAEZSIS

- PHP **8.1.x** en BlueHosting (cPanel, CloudLinux CageFS). MultiPHP 8.1 para `comex.lpaezsis.cl`.
- Extensiones: `pdo_sqlite`, `sqlite3`, `pdo_mysql`, `mbstring`, `gd`, `zip`, `fileinfo`, `curl`, `json`.
- Namespaces PSR-4 `Crm\` → `src/Crm/`. Linux es case-sensitive: el directorio es `Crm`, nunca `crm`.
- Inventario: `Crm\Inventory\SqliteConnector` sobre `INV_SQLITE_PATH=/home/sistem29/app/data/prod.db`. Lectura con `query_only`; escritura/sincronización (Movement ENTRADA/SALIDA) con WAL + `busy_timeout` + `BEGIN IMMEDIATE` y reintentos SQLITE_BUSY. El directorio de `prod.db` debe ser escribible (`-wal`/`-shm`).
- Fichas COMEX y operaciones importación/exportación se vinculan por SKU (`Product.code`). No duplicar lógica de CUP: usar `StockSync::nuevoCostoPromedio`.
- Landed cost Chile: prorrateo por FOB, gastos origen USD/EUR (CIF) y locales CLP, IVA aduanero `IVA_PCT` (19%) sobre CIF. Versiones ESTIMADA vs REAL. PDF con `gd` + `fileinfo`.
- Pipeline operativo: al crear Importación/Exportación se siembran 13 etapas (Evaluación + Ejecución). Estados `PENDING|IN_PROGRESS|COMPLETED|BLOCKED`. UI Kanban/Lista en `operaciones.php` / `operacion.php`.
- Finanzas en el detalle (`operacion.php?tab=financials`): matriz Estimación vs Real, prorrateo FOB, IVA 19% CIF, unitario CLP/USD, recálculo en vivo, export XLSX (`zip`) y PDF (`gd`).
- Documentos (`operacion.php?tab=documents`): repositorio Factura Comercial, Packing List, BL/AWB, Certificados, DIN/DUS en `uploads/comex/docs/`, trazabilidad usuario/fecha.
- Dashboard (`index.php`): KPIs de operaciones activas, ciclo (días), costo promedio por embarque y alertas de retraso; gráfico SVG de volumen mensual.
- PDO MySQL de COMEX con prepared statements. `ATTR_EMULATE_PREPARES = false`.
- `.env` y `config/` no son públicos: `.htaccess` responde **403**. Forzar HTTPS.
- `uploads/` permisos **755/775**, excluido de WebDAV (puerto **2078**). Ver `.webdavignore` y `scripts/webdav-sync.sh`.
- No requiere Composer en el servidor: autoload propio en `Crm\Autoloader`.
