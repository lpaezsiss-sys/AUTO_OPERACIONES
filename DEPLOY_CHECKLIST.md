# Checklist de despliegue — crm.lpaezsis.cl (BlueHosting / cPanel)

PHP del subdominio: **7.4 LTS**. Document root típico: `public_html/crm/` o `public_html/crm.lpaezsis.cl/`.

## Estructura esperada en la raíz del subdominio

Estos archivos deben quedar **directamente** en el document root (no dentro de `AUTO_OPERACIONES-…/` ni `crm_backup/…`):

```text
public_html/crm/
  index.php
  login.php
  .htaccess
  .env                 ← copiado desde .env.production + credenciales MySQL
  api/
  config/
  uploads/
  sql/
  src/
  includes/
  assets/
```

Si ves **«Índice de /»** en https://crm.lpaezsis.cl, Apache está listando la carpeta porque `index.php` / `.htaccess` no están en esa raíz.

## Cómo corregirlo en el Administrador de archivos de cPanel

1. Entra a **cPanel → Administrador de archivos**.
2. Arriba a la derecha: **Configuración** (engranaje) → marca **Mostrar archivos ocultos (dotfiles)** → Guardar. Así verás `.htaccess` y `.env`.
3. Abre el document root del subdominio (`public_html/crm/` o el que tenga `crm.lpaezsis.cl`).
4. Si al descomprimir quedó una carpeta interna (`AUTO_OPERACIONES-freeze-crm-lpaezsis-2026-08-16/`, `crm_lpaezsis/`, etc.):
   1. Entra a esa subcarpeta.
   2. **Seleccionar todo** (Ctrl+A), incluido `.htaccess` y `.env.production` (dotfiles visibles).
   3. **Mover** → destino: `/public_html/crm/` (la raíz del subdominio, un nivel arriba).
   4. Confirma que `index.php`, `login.php` y `.htaccess` quedan al mismo nivel que `api/`, `config/`, `uploads/`, `sql/`.
   5. Borra la subcarpeta vacía que quedó.
5. Copia `.env.production` a `.env` (o renombra) y completa `DB_NAME`, `DB_USER`, `DB_PASS`.
6. Permisos de `uploads/`: **755**.
7. Recarga https://crm.lpaezsis.cl — debe abrir login/dashboard, no el índice.

**No uses el ZIP de GitHub** (`…/archive/refs/tags/….zip`): GitHub siempre encapsula en una carpeta `repositorio-tag/`. Usa el ZIP plano `downloads/crm_backup_YYYYMMDD_HHMM.zip` generado con `php scripts/crear_respaldo.php`.

Verificación local del paquete:

```bash
php scripts/check_deploy_paths.php
php scripts/check_deploy_paths.php downloads/crm_backup_YYYYMMDD_HHMM.zip
```

## Tareas en cPanel

- [ ] **a) Base de datos.** Crear BD y usuario MySQL. En phpMyAdmin, seleccionar la BD e importar:
  1. Primero `sql/schema.mysql.sql` (estructura MySQL).
  2. Luego, si el dump es MySQL, `sql/respaldo_completo_local.sql`.  
     Si phpMyAdmin rechaza el dump (el respaldo local puede ser dialecto SQLite), no lo importes: ejecuta en el servidor `php sql/install.php` para seed mínimo. **No** reimportar `productos` si el inventario ya existe en esa BD.
- [ ] **b) Entorno.** Subir el código **a la raíz del subdominio**. Renombrar `.env.production` → `.env`. Completar `DB_NAME`, `DB_USER` y `DB_PASS`. Dejar `APP_ENV=production`, `APP_URL=https://crm.lpaezsis.cl` y `DISPLAY_ERRORS=off`.
- [ ] **c) Permisos.** Carpeta `uploads/` en **755**. El usuario de PHP debe poder escribir el logo (`uploads/logo.png`). `.env` solo lectura para PHP, no accesible por HTTP.
- [ ] MultiPHP **7.4** en el subdominio. Confirmar SSL del subdominio (AutoSSL).

## Endpoints a probar tras la subida

| URL | Qué validar |
| --- | --- |
| https://crm.lpaezsis.cl/ | Login y dashboard con KPIs (no «Índice de /») |
| https://crm.lpaezsis.cl/api/health.php | JSON `"ok":true`, `"compat":"7.4"`, `"db":"ok"` |
| https://crm.lpaezsis.cl/cotizador.php | Cotización mixta producto + servicio y PDF |
| https://crm.lpaezsis.cl/reportes.php | Gráficos Chart.js y botón Exportar CSV |
| https://crm.lpaezsis.cl/actividades.php | Agenda: programar seguimiento y marcar realizada |

HTTP debe redirigir a HTTPS. `https://crm.lpaezsis.cl/.env` debe dar **403**.
