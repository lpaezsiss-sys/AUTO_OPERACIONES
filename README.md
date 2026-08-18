# AUTO_OPERACIONES — CRM Industrial Omnicanal B2B

Módulo CRM para **LPAEZsis** en `crm.lpaezsis.cl`.

- PHP **7.4 LTS** (BlueHosting / cPanel Apache). Sin sintaxis de PHP 8+.
- MySQL/MariaDB con **PDO** y prepared statements.
- UI: Bootstrap 5 + JavaScript (Fetch API).
- Tablas CRM con prefijo `crm_`.
- Stock y precio se **leen** de la tabla existente `productos` (inventario). El CRM no escribe inventario.

## Estructura pedida (PHP 7.4)

| Archivo | Rol |
|---|---|
| `database/schema.sql` | `crm_empresas`, `crm_contactos`, `crm_oportunidades`, `crm_cotizaciones`, `crm_cotizacion_items` + FK a `productos` |
| `config/db.php` | PDO utf8mb4, `ATTR_EMULATE_PREPARES=false`, `ERRMODE_EXCEPTION` |
| `api/crear_cotizacion.php` | `GET ?action=buscar_producto` (LIKE nombre/SKU) · `POST ?action=guardar` (JSON + transacción + correlativo) |
| `cotizador.php` | Bootstrap 5 + Fetch: búsqueda en vivo, tabla dinámica, IVA 19% |

## Arranque local (preview)

```bash
cp .env.example .env
# en .env: CRM_DB_DRIVER=sqlite  y  CRM_SQLITE_PATH=data/crm.sqlite
php sql/install.php
php scripts/test_local.php    # PASS / FAIL
./server.sh                   # http://localhost:8000
```

Manual de usuario (local): `MANUAL_USUARIO.md` y vista `manual.php` (botón **Descargar Manual en PDF**).

Checklist de despliegue: `CHECKLIST_LOCAL.md`.

Usuario seed (solo entorno local / primera instalación):

- `ivan.p@example.net`
- contraseña definida en el seed de `src/Schema.php`

## API (`/api/`)

Todos los endpoints responden JSON (`Content-Type: application/json`).

| Endpoint | Métodos |
|---|---|
| `api/health.php` | GET |
| `api/auth.php` | GET me, POST login/logout |
| `api/dashboard.php` | GET |
| `api/empresas.php` | GET/POST/PUT/DELETE (`?id=`) |
| `api/contactos.php` | GET/POST/PUT/DELETE |
| `api/oportunidades.php` | GET/POST/PUT |
| `api/cotizaciones.php` | GET/POST/PUT |
| `api/actividades.php` | GET/POST/PUT |
| `api/productos.php` | GET (solo lectura) |
| `api/catalogos.php` | GET |
| `api/crear_cotizacion.php` | GET `buscar_producto`, POST `guardar` |

Las escrituras multi-tabla (cotización + ítems) usan transacción PDO y `rollBack()` ante error.

## Tabla `productos` (inventario)

Columnas esperadas (solo SELECT):

`id`, `codigo`, `nombre`, `descripcion`, `stock`, `precio_unitario`, `umbral_stock`, `unidad`, `activo`, `updated_at`

Si la tabla ya existe en la misma base (inventario.lpaezsis.cl), no se recrea (`CREATE TABLE IF NOT EXISTS`).

## Tests

```bash
php tests/php74_scan.php
php tests/run.php
php scripts/test_local.php
```
