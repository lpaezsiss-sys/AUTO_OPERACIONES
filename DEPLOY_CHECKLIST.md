# Checklist de despliegue — crm.lpaezsis.cl (BlueHosting / cPanel)

PHP del subdominio: **7.4 LTS**. Document root típico: `public_html/crm.lpaezsis.cl/`.

## Tareas en cPanel

- [ ] **a) Base de datos.** Crear BD y usuario MySQL. En phpMyAdmin, seleccionar la BD e importar:
  1. Primero `sql/schema.mysql.sql` (estructura MySQL).
  2. Luego, si el dump es MySQL, `sql/respaldo_completo_local.sql`.  
     Si phpMyAdmin rechaza el dump (el respaldo local puede ser dialecto SQLite), no lo importes: ejecuta en el servidor `php sql/install.php` para seed mínimo. **No** reimportar `productos` si el inventario ya existe en esa BD.
- [ ] **b) Entorno.** Subir el código. Renombrar `.env.production` → `.env` en la raíz del CRM. Completar `DB_NAME`, `DB_USER` y `DB_PASS` de cPanel. Dejar `APP_ENV=production`, `APP_URL=https://crm.lpaezsis.cl` y `DISPLAY_ERRORS=off`.
- [ ] **c) Permisos.** Carpeta `uploads/` en **755**. El usuario de PHP debe poder escribir el logo (`uploads/logo.png`). `.env` solo lectura para PHP, no accesible por HTTP.
- [ ] MultiPHP **7.4** en el subdominio. Confirmar SSL del subdominio (AutoSSL).

## Endpoints a probar tras la subida

| URL | Qué validar |
| --- | --- |
| https://crm.lpaezsis.cl/ | Login (`ivan.p@example.net`) y dashboard con KPIs |
| https://crm.lpaezsis.cl/api/health.php | JSON `"ok":true`, `"compat":"7.4"`, `"db":"ok"` |
| https://crm.lpaezsis.cl/cotizador.php | Cotización mixta producto + servicio y PDF |
| https://crm.lpaezsis.cl/reportes.php | Gráficos Chart.js y botón Exportar CSV |
| https://crm.lpaezsis.cl/actividades.php | Agenda: programar seguimiento y marcar realizada |

HTTP debe redirigir a HTTPS. `https://crm.lpaezsis.cl/.env` debe dar **403**.
