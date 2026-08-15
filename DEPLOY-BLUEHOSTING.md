# Despliegue en BlueHosting (cPanel) — crm.lpaezsis.cl

PHP del hosting: **7.4 LTS**. No activar MultiPHP 8.x para este subdominio.

## 1. Subdominio

Crear `crm.lpaezsis.cl` apuntando a un document root, por ejemplo:

`public_html/crm.lpaezsis.cl/`

Subir el contenido de este repositorio (no hace falta `tests/` ni `.git`).

## 2. Base de datos MySQL

1. Crear BD y usuario en cPanel (nombre tipo `sistem29_crm`).
2. En phpMyAdmin, seleccionar la BD e importar `sql/schema.mysql.sql`.
3. Si **ya existe** la tabla `productos` del inventario en la misma BD, el `CREATE TABLE IF NOT EXISTS productos` no la pisa.
4. Copiar `.env.example` → `.env` en la raíz del CRM y completar:

```env
CRM_DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=sistem29_crm
DB_USER=usuario_bd
DB_PASS=clave_bd
APP_DEBUG=0
APP_URL=https://crm.lpaezsis.cl
```

5. Crear el primer usuario:

```bash
php sql/install.php
```

`install.php` inserta usuarios y datos demo solo si las tablas CRM están vacías. No inserta productos si `productos` ya tiene filas.

## 3. Permisos

- PHP debe poder leer `.env` (no accesible por HTTP: `.htaccess` lo bloquea).
- No se requiere Node ni Composer.

## 4. Comprobar

- `https://crm.lpaezsis.cl/api/health.php` debe devolver JSON con `"compat":"7.4"` y `"db":"ok"`.
- `https://crm.lpaezsis.cl/login.php`
