# CONTEXT_CRM.md — Memoria de desarrollo CRM LPAEZsis

Documento de ingeniería inversa del código **ya ejecutado**. Sirve para que futuras actualizaciones **extiendan** este diseño en lugar de reescribirlo.

**Producto:** CRM Industrial Omnicanal B2B  
**Producción:** https://crm.lpaezsis.cl  
**Repositorio:** `lpaezsiss-sys/AUTO_OPERACIONES`  
**Snapshot de este árbol:** rama `cursor/fix-itemimagen-class-fa62` (HEAD `0d8e757` — `Fix missing Crm\ItemImagen on quote save`)  
**Audiencia:** agentes y desarrolladores que toquen el CRM (no COMEX)

---

## Objetivo Actual

Proyecto completado. Esperando nuevas directrices o fase de mantenimiento.

---

## Resumen ejecutivo

El CRM es una **aplicación PHP clásica** (páginas `*.php` en la raíz + JSON en `/api/` + JS con `fetch`). No es un SPA, no usa Composer en servidor, no usa un framework MVC.

El núcleo comercial ya está cerrado:

1. Ficha de cliente (empresa, contacto, vendedor).
2. Cotizador vivo sobre inventario (SKU, stock overlay, precio defendible).
3. Ítems a pedido (demanda fuera de catálogo) y conversión a SKU con **stock 0**.
4. PDF corporativo (Dompdf) y manual de usuario in-app.
5. Roles `admin` / `vendedor`, listas de precios, comisiones, agenda, reportes.

**Regla de oro:** no inventar un segundo motor de precios, de folios, de stock o de PDF. Extender `src/*.php` y los `api/*.php` existentes.

---

## 1. Historial de cambios y funcionalidades ejecutadas

Orden aproximado según `git log` (de la base comercial hasta el HEAD de este árbol). Lo que sigue **ya está firme**; no reimplementarlo.

### 1.1 Núcleo y arranque

| Qué quedó | Dónde | No volver a hacer |
|---|---|---|
| Autoload PSR-4 `Crm\` → `src/` | `includes/bootstrap.php` | Composer / otro namespace |
| Polyfills PHP 7.4 (`str_starts_with`, `str_contains`, `str_ends_with`, `crm_lower`) | `includes/polyfills.php` | Usar nativos de PHP 8 en código de app |
| PDO único `crm_pdo()`, MySQL utf8mb4 o SQLite de laboratorio | `config/db.php` | ORM, query builder, segunda conexión CRM |
| `Schema::install()` + `Schema::ensureUpgrades()` al boot | `src/Schema.php` | Recrear tablas a mano en cada deploy |
| Timezone `America/Santiago` | bootstrap | Dejar fechas en UTC “porque es estándar” |
| Helpers de entrada `crm_str`, `crm_int`, `crm_float` (acepta `24,38` y `1.234,56`) | bootstrap | Otro parser de decimales chilenos |
| Servidor local `./server.sh` + `router.php` | raíz | Docker “para el CRM” salvo que se pida |

### 1.2 Autenticación y usuarios

| Qué quedó | Dónde |
|---|---|
| Sesión `crm_lpaezsis`, cookie httponly + SameSite Lax, `secure` si HTTPS | bootstrap |
| Login JSON `api/auth.php` (GET me, POST login/logout, DELETE logout) | `src/Auth.php` |
| Trim de email/clave (espacios no pueden fallar el login) | `Auth::login` |
| `session_regenerate_id(true)` al autenticar | `Auth::login` |
| Roles: `admin` y `vendedor`. Usuario inactivo → logout | `crm_usuarios` |
| Páginas: `crm_page_user()` redirige a `login.php`; admin: `crm_page_require_admin()` → `index.php` | bootstrap / `usuarios.php` |
| APIs: `Auth::requireUser()` 401, `Auth::requireAdmin()` 403 | `src/Auth.php` |
| Passwords: `password_hash` / `password_verify`, mínimo 8 caracteres | `src/Usuarios.php` |
| No se puede degradar ni desactivar al **último** admin activo | `Usuarios::protegerUltimoAdmin` |
| CRUD admin de usuarios | `usuarios.php` + `api/usuarios.php` |

### 1.3 Base de datos

Prefijo **`crm_`** para el dominio CRM. La tabla **`productos`** (sin prefijo) es el catálogo compartido con inventario; `CREATE TABLE IF NOT EXISTS` para no pisar MySQL de producción.

Tablas firmes:

- `crm_usuarios`, `crm_empresas` (RUT único, `lista_precio_id`, `ejecutivo_id`)
- `crm_contactos` (FK empresa CASCADE)
- `crm_oportunidades` (`codigo` UNIQUE `OPP-YYYY-NNNN`)
- `crm_cotizaciones` (`folio` UNIQUE `COT-YYYY-NNNN`, condiciones comerciales, IVA, lista, vendedor)
- `crm_cotizacion_items` (`tipo_item` producto/servicio/a_pedido, marca, costo, detalle, imagen, `stock_al_cotizar`)
- `crm_marcas` + `crm_cotizacion_marcas`
- `crm_configuracion_empresa` (singleton `id=1`, emisora del PDF)
- `crm_vendedores`, `crm_comisiones`
- `crm_actividades` (agenda postventa)
- `crm_secuencias` (piso de folio COT)
- `crm_listas_precios` (`porcentaje_ajuste`, `es_default`)

Migraciones: `Schema::ensureUpgrades()` en runtime **y** scripts `sql/alter_*.sql` / `sql/modulo_*.sql` para ops. No crear un sistema de migraciones tipo Laravel.

DDL canónico: `src/Schema.php` (MySQL y SQLite) alineado con `sql/schema.mysql.sql`.

### 1.4 API HTTP

Patrón único:

1. `require api/_init.php`
2. `\Crm\Http::handle(function () { ... return array(...); })`
3. Respuesta `{ ok: true, success: true, ... }` o `{ ok: false, success: false, error }`
4. Errores de negocio: `Http::fail($msg, $status)` → `ApiException`
5. `PDOException` / `Throwable`: rollback si hay transacción, JSON 500
6. Prepared statements; `ATTR_EMULATE_PREPARES = false`
7. Escrituras multi-tabla: `beginTransaction()` + `rollBack()`

Excepciones al JSON (binario o multipart, no reescribir como JSON “puro”):

- `api/cotizacion_pdf.php`, `api/manual_pdf.php`
- `api/cotizacion_item_imagen.php`, POST logo en `api/configuracion.php`
- `api/respaldo.php` (ZIP)
- `api/crear_cotizacion.php` (buscar/guardar del cotizador de alta)

`api/health.php` es público: PHP, `compat`, ping DB. No autenticar el healthcheck.

### 1.5 Frontend (UI)

- **No SPA.** Cada pantalla es un `*.php` de raíz con Bootstrap 5.3 (CDN) + `assets/css/app.css` + `assets/js/app.js` + script inline.
- Layout: `includes/layout.php` (sidebar, toast `#crmToast`, `window.crmRol`, logout).
- JS compartido: `crmApi` (`fetch`, `credentials: same-origin`, `cache: no-store`), `crmToast`, `crmEsc`, `crmClp`, `crmParseNum`, `crmForm`, `crmStockFromApi`.
- Paleta: navy `#05294B`, acento `#fec001`.
- Pantallas de cotización **separadas y estables**:
  - `cotizador.php` → **alta**
  - `cotizacion.php?id=N` → **edición**
- Inventario en UI es lectura (`productos.php`). Listas de precios y usuarios: solo menú admin.

### 1.6 Empresas, contactos, pipeline

- RUT chileno: `src/Rut.php` (normalizar / validar / formatear). No otro validador.
- Catálogos estáticos (regiones Chile, industrias, orígenes, canales, etapas, estados, monedas): `src/Catalog.php` + `api/catalogos.php`.
- Oportunidades: etapas `prospecto → calificacion → propuesta → negociacion → ganada | perdida`. Código `OPP-YYYY-NNNN` vía `Codes`.
- Eliminaciones con resguardo (FK / uso en cotizaciones): no borrar en cascada “porque es más simple”.

### 1.7 Cotizaciones, folios e ítems

| Decisión | Implementación |
|---|---|
| Folio `COT-YYYY-NNNN` | `src/Codes.php` (`peek` no reserva; `next` al insertar) |
| Piso automático **354** (números menores libres para histórico) | `Codes::COT_INICIO` + `crm_secuencias` |
| Admin puede **renombrar folio no usado** | `api/cotizacion_folio.php` |
| Badge “próximo folio” en alta (peek) | `cotizador.php` + `api/cotizaciones.php?proximo=1` |
| Tipos de ítem | `producto`, `servicio`, `a_pedido` (`Catalog::itemTipos`) |
| Cantidades **decimales** (2 decimales en PDF) | `crm_float` + UI cotizador/ficha |
| Descripción detallada + imagen de línea | `descripcion_detallada`, `imagen_url`, `src/ItemImagen.php` |
| Persistencia | DELETE ítems + reinsert en la misma transacción (`Cotizaciones::persist`) |
| IVA | `IVA_PCT` (default 19) vía `crm_iva_pct()`; no hardcodear 19 en fórmulas nuevas |
| Condiciones comerciales | validez, moneda (CLP/USD/UF/EUR), pago, plazo, lugar |
| Comisiones | se registran/sincronizan desde la cotización (`src/Comisiones.php`) |
| Clase `ItemImagen` | `require_once` explícito en bootstrap **y** en `Productos.php` (el autoload solo no bastó en prod al guardar) |

### 1.8 Precios (motor único)

Jerarquía **cerrada** en `src/Precios.php` / `api/precios.php`:

1. Último precio cotizado a esa empresa para el SKU (`tipo_item = producto`), **excluye** cotizaciones `rechazada` y `vencida`.
2. Si no hay historial: lista activa (`crm_listas_precios.porcentaje_ajuste` sobre `productos.precio_unitario`). Lista de la cotización → lista de la empresa → lista default.
3. Si no hay lista: precio base de inventario.

No duplicar esta cascada en el JS: el cliente llama `api/precios.php` y muestra el badge.

### 1.9 Inventario y stock

Reglas **intocables**:

- El CRM **no hace UPDATE de stock** de filas existentes.
- Lectura CRM: `SELECT` sobre `productos`.
- Stock **vivo**: overlay `INV_SQLITE_PATH` → SQLite Prisma tabla `Product` (`src/InventarioStock.php`), solo SELECT. Si el archivo no está, se usa `productos.stock`.
- SKU que existe solo en inventario: `Productos::asegurarDesdeInventario` hace **INSERT stock 0** para obtener `id` de cotizador. Nunca UPDATE.
- Búsqueda LIKE: escape `%` `_` y `LIKE ESCAPE '!'` (MariaDB). El guion del SKU es literal.
- Conversión “a pedido → catálogo”: `EstadisticasAPedido` → `Productos::altaCatalogo` (código `APD-YYYY-NNNN` si no se indica), **stock 0**, sin inflar existencias.

### 1.10 PDF y marcas

- Dompdf **vendorizado** en `lib/pdf/deps/` (sin Composer en cPanel).
- Cotización: `src/CotizacionPdf.php` + `templates/pdf/cotizacion.php`. Remote HTTP deshabilitado; imágenes `file://` bajo chroot.
- Logos de marcas representadas en el PDF; CRUD `marcas.php`. Checkboxes de marcas **debajo** de observaciones en el cotizador.
- PDF **no** antepone `[A pedido]` a la descripción (el distintivo visual es otro).
- Logo PNG de respaldo si falla SVG. Fechas chilenas `d/m/Y`.
- GD requerido para JPG→PNG de ítems y para el PDF del manual.

### 1.11 Agenda, reportes, respaldo, configuración

- Agenda postventa: `actividades.php` / `api/actividades.php`. KPIs de totales **independientes** del filtro de estado.
- Informes: `reportes.php` + Chart.js CDN + CSV. API `src/Reportes.php` (KPIs, pipeline, vendedores, top productos). Rebanadas en 0 no se grafican.
- Configuración emisora + logo: `configuracion.php`.
- Respaldo ZIP (admin): `src/Respaldo.php`, `api/respaldo.php`, scripts `scripts/crear_respaldo.php` / empaquetado. Workflow GitHub `release-backup.yml`.
- Deploy BlueHosting documentado: `DEPLOY_CHECKLIST.md`, `DEPLOY-BLUEHOSTING.md`, `.env.production` de plantilla. **Nunca sobrescribir `.env` ni `uploads/` de producción.**

### 1.12 Manual de usuario

- Fuente: `MANUAL_USUARIO.md` → HTML `manual.php` (`src/Manual.php`) y PDF `api/manual_pdf.php`.
- Capturas en `artifacts/`; PDF estático `docs/CRM_LPAEZsis_Manual_Usuario.pdf`.
- `AGENTS.md`: **no desplegar** manual/PDF/artifacts a producción sin autorización explícita.
- En este árbol el menú Manual ya está bajo Cotizador; el sidebar hace scroll.

### 1.13 Tests y calidad

Correr, no sustituir:

```bash
php tests/php74_scan.php
php tests/run.php
php scripts/test_local.php
```

- `php74_scan.php` prohíbe `match()`, `?->`, `mixed`, `never`, atributos PHP 8, constructor promotion (excluye `lib/pdf/deps/`).
- `tests/run.php`: suite de integración sobre SQLite temporal.
- Humo HTTP: `tests/http_smoke.sh`. Flujo comercial: `scripts/test_e2e_flujo_completo.php`.

---

## 2. Decisiones de arquitectura y restricciones

Estas restricciones se **deducen del código**. Una actualización futura que las ignore es un retrabajo, no una mejora.

### 2.1 Runtime y lenguaje

- **Sintaxis de aplicación: PHP 7.4-safe.** Prohibido en `src/`, `api/`, `includes/`, páginas: `match()`, named arguments, `?->`, union types, constructor property promotion, `mixed`, `never`, `str_starts_with` nativo (usar polyfill).
- Este checkout declara `compat: 7.4` en `api/health.php` y `AGENTS.md`. Ramas posteriores documentan **runtime de producción PHP 8.1** (CloudLinux PHP Selector) **sin cambiar la sintaxis 7.4**. No “modernizar” el código a PHP 8 idiomático.
- No usar `AddHandler` alt-php81 si el selector de la cuenta no es 8.1: CageFS no monta PDO y el sitio cae.
- `declare(strict_types=1)` está en todo el PHP de app. Tipos en PHPDoc, no en firmas modernas.
- Sin Composer en el servidor. Autoload propio. Dompdf va en `lib/pdf/deps/`.

### 2.2 Estilo de aplicación

- **PHP + HTML + Fetch.** No React/Vue/Next, no htaccess rewrite a `index.php` tipo front controller de framework, no REST “puro” con `/api/v1/resource/{id}` (los ids van en `?id=`).
- Estado de UI: variables JS de página + `localStorage` no es el patrón; la fuente de verdad es la API + recarga/`crmApi`.
- Validación **en servidor** (clases `Crm\*`). El JS es UX. Números comerciales pasan por `crm_float` / `crmParseNum`.
- JSON de API dual `ok` **y** `success` (el cliente comprueba ambos). No romper ese contrato.

### 2.3 Datos

- Una conexión CRM (`crm_pdo`). Inventario externo es **otro** PDO SQLite de solo lectura (`InventarioStock`), no el de COMEX ni Prisma Client.
- Prepared statements siempre. Concatenar SQL solo para identificadores ya allowlisteados (`Codes` tablas/columnas).
- LIKE de catálogo: `InventarioStock::likeNeedle` + `likeEscapeSql()` (`ESCAPE '!'`). No armar LIKE a mano.
- IVA Chile parametrizado (`IVA_PCT`, default 19). Totales: subtotal ítems − descuento global + IVA redondeado a 2 decimales.

### 2.4 Inventario (no negociable)

```
SELECT productos          → sí
Overlay stock SQLite      → sí (Product.code = productos.codigo)
INSERT productos stock 0  → sí (SKU nuevo / conversión a pedido)
UPDATE productos.stock    → NO
Escribir prod.db Prisma   → NO
Motor CUP / landed cost   → NO (eso es COMEX, otro módulo)
```

### 2.5 Seguridad

- `.htaccess` deniega `.env` y directorios `config|src|includes|sql|tests|data|database|scripts|lib|templates`.
- `uploads/` no ejecuta PHP.
- HTTPS forzado en `crm.lpaezsis.cl`.
- CORS de `includes/cors.php` solo localhost (dev). Producción = same-origin.
- No loguear passwords. Seed local en `Schema::seed`; no rotar ni documentar claves de producción aquí.

### 2.6 Despliegue

- Document root plano (no `public/` de Laravel). `index.php` / `login.php` en la raíz del subdominio.
- No pisar `.env` ni `uploads/` en producción.
- `ItemImagen` debe cargarse sí o sí (autoload + `require_once`): un save de cotización sin esa clase ya rompió producción una vez (`0d8e757`).

### 2.7 Este repo vs COMEX vs inventario Next

- **Este árbol es el CRM** (`crm.lpaezsis.cl`).
- Ramas `cursor/comex-*-b80a` y `cursor/inventario-nextjs-fef4` **no** se mezclan con este diseño: no copiar landed cost, pipeline 13 etapas, ni Next.js al CRM.
- Vínculo entre sistemas: **SKU** (`productos.codigo` / `Product.code`).

---

## 3. Mapa rápido de archivos (dónde extender)

| Quiero… | Extender |
|---|---|
| Nueva tabla CRM | `src/Schema.php` `ensureUpgrades()` + clase en `src/` + `api/*.php` + página raíz |
| Nuevo endpoint JSON | `api/foo.php` con `Http::handle` |
| Nueva pantalla | `foo.php` + `crm_layout_start` + entrada en `includes/layout.php` |
| Precio | solo `src/Precios.php` |
| Folio / correlativo | solo `src/Codes.php` |
| Stock / búsqueda SKU | `src/InventarioStock.php` + `src/Productos.php` |
| PDF cotización | `templates/pdf/cotizacion.php` + `src/CotizacionPdf.php` |
| PDF/manual | `MANUAL_USUARIO.md` + `src/Manual.php` |
| Helper JS global | `assets/js/app.js` (no otra bundle) |

Páginas raíz ya existentes (no duplicar con “vistas/”):  
`index.php`, `login.php`, `empresas.php`, `empresa.php`, `contactos.php`, `oportunidades.php`, `cotizaciones.php`, `cotizador.php`, `cotizacion.php`, `productos.php`, `listas_precios.php`, `vendedores.php`, `usuarios.php`, `comisiones.php`, `reportes.php`, `estadisticas_a_pedido.php`, `actividades.php`, `marcas.php`, `configuracion.php`, `manual.php`.

---

## 4. Trabajo ya hecho en ramas posteriores (no rehacer)

Este checkout **no** incluye aún los commits de las ramas hijas. Antes de reimplementar UX o PHP 8.1, integrar o cherry-pick:

| Rama | Qué aporta |
|---|---|
| `cursor/php81-compat-local-fa62` | Compat local PHP 8.1 **sin** sintaxis 8+; `health.compat = 8.1`; Selector CloudLinux documentado |
| `cursor/ux-cotizador-bloque1-fa62` | `cotizador.php?id=N` → redirect a `cotizacion.php?id=N`; botón Guardar con `crmBusyButton` (≥450 ms) + cache-bust de `app.js` |
| `cursor/ux-busqueda-productos-fa62` | Filas de sugerencia (SKU, stock, precio) y toasts al agregar ítem |
| `cursor/manual-usuario-20260913-fa62` | Manual v2026-09-13, sin guion de inversionistas, Anexo A = URLs; publicación a prod solo con autorización |

Si una tarea pide “mejorar el cotizador” o “subir el manual”, partir de esas ramas, no del diseño en blanco.

---

## 5. Anti-patrones (rechazar en review)

- Reescribir el CRM en Laravel / Symfony / Node / SPA.
- Segundo motor de precios o de folios.
- `UPDATE` de stock o escritura al SQLite de inventario.
- PHP 8 idiomático (`match`, `?->`, named args) en código de app.
- Carpeta `vistas/` o rutas tipo `/quotes/:id`.
- Composer obligatorio en cPanel.
- Subir `.env`, `uploads/`, o el manual a producción sin pedido explícito.
- Duplicar `crmApi` / toasts / parseo de números en un JS nuevo “de módulo”.

---

*Generado por ingeniería inversa del repositorio. Actualizar este archivo solo cuando una decisión de arquitectura quede realmente reemplazada en el código, no como diario de cada commit.*
