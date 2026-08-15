# Checklist de verificación local (antes de BlueHosting)

PHP objetivo en producción: **7.4 LTS**. En el PC de desarrollo puede usarse 7.4 u 8.x para `php -S`; el código del CRM no usa sintaxis de PHP 8.

## 0. Arranque

```bash
cp .env.example .env
```

En `.env` para prueba local (sin MySQL):

```env
CRM_DB_DRIVER=sqlite
CRM_SQLITE_PATH=data/crm.sqlite
APP_DEBUG=1
APP_URL=http://localhost:8000
IVA_PCT=19
```

Para MySQL local, deja `CRM_DB_DRIVER=mysql` y completa `DB_*`.

```bash
php sql/install.php
php scripts/test_local.php
./server.sh
```

Abre [http://localhost:8000/login.php](http://localhost:8000/login.php).

Usuario seed (solo entorno vacío / primera instalación): ver `README.md` / `src/Schema.php`.

---

## 1. Login y sesión

- [ ] Cargar `/login.php` (no deben verse credenciales de muestra en la pantalla).
- [ ] Ingresar email y contraseña del usuario seed.
- [ ] Tras “Ingresar”, el dashboard (`/index.php`) muestra KPIs y el email en la barra lateral.
- [ ] Recargar `/index.php`: la sesión se mantiene (no vuelve al login).
- [ ] “Salir” vuelve a `/login.php`.

---

## 2. Tablero Kanban de oportunidades

- [ ] Ir a **Oportunidades** (`/oportunidades.php`).
- [ ] Se ven columnas: prospecto, calificacion, propuesta, negociacion, ganada, perdida.
- [ ] Hay tarjetas con código `OPP-…`, empresa y valor en CLP.
- [ ] Cambiar la etapa de una tarjeta con el selector: la tarjeta se mueve de columna.

---

## 3. Buscador de productos (inventario)

- [ ] Ir a **Cotizador** (`/cotizador.php`).
- [ ] En “Buscar producto (SKU o nombre)” escribir `13451` (o un SKU real de `productos`).
- [ ] El desplegable aparece vía Fetch a `api/crear_cotizacion.php?action=buscar_producto`.
- [ ] Elegir un resultado: se agrega una fila con stock y precio (solo lectura de `productos`).
- [ ] **Inventario** (`/productos.php`) lista SKUs; no hay botón de crear/editar stock.

---

## 4. Cotización de prueba (IVA 19%)

- [ ] En el cotizador, empresa + al menos un ítem.
- [ ] Cambiar cantidad: Subtotal, **IVA 19%** y Total se recalculan.
- [ ] “Guardar cotización” llama `POST api/crear_cotizacion.php?action=guardar`.
- [ ] Aparece folio correlativo `COT-YYYY-NNNN` y el total con IVA.
- [ ] En **Cotizaciones** figura el folio nuevo.

---

## 5. Inventario de solo lectura (HTTP 405)

En otra terminal, con sesión o sin ella el POST debe rechazarse (401 si no hay cookie; 405 si hay sesión):

```bash
curl -sS -o /tmp/prod-post.json -w "%{http_code}\n" \
  -H "Content-Type: application/json" \
  -X POST http://localhost:8000/api/productos.php \
  -d '{}'
```

- [ ] Con sesión iniciada (cookie de login): cuerpo JSON de error y **HTTP 405**.
- [ ] El CRM no altera `stock` en `productos`.

Login + 405:

```bash
COOKIE=/tmp/crm-local.cookie
curl -sS -c "$COOKIE" -b "$COOKIE" -H "Content-Type: application/json" \
  -X POST http://localhost:8000/api/auth.php \
  -d '{"email":"TU_EMAIL","password":"TU_CLAVE"}'
curl -sS -c "$COOKIE" -b "$COOKIE" -H "Content-Type: application/json" \
  -w "\nHTTP %{http_code}\n" \
  -X POST http://localhost:8000/api/productos.php -d '{}'
```

---

## Listo para hosting

Si `php scripts/test_local.php` imprime `PASS` y esta lista está marcada, se puede importar `database/schema.sql` (o `sql/schema.mysql.sql`) en cPanel y seguir `DEPLOY-BLUEHOSTING.md`.
