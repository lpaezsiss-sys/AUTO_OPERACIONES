# Despliegue en BlueHosting

Guía para alojar **Inventario** (Next.js + Prisma) en BlueHosting (cPanel con Node.js o VPS).

> Next.js **no** funciona como sitio PHP estático. Necesitas **Node.js 20+** (Selector de aplicaciones Node.js en cPanel, o VPS con SSH).

---

## Requisitos en BlueHosting

1. Plan con **Node.js** (cPanel → *Setup Node.js App* / *Aplicación Node.js*) **o** VPS con SSH.
2. Dominio o subdominio con **HTTPS** (Let's Encrypt en cPanel).
3. Acceso a terminal SSH (recomendado) o File Manager + terminal del panel.

---

## Opción A — cPanel «Setup Node.js App» (la más común)

### 1. Preparar el paquete en tu PC

```bash
git checkout cursor/inventario-nextjs-fef4
npm run build:deploy
```

Se genera la carpeta `deploy/` lista para subir.

### 2. Crear la app Node en cPanel

1. Entra a **cPanel → Setup Node.js App**.
2. **Create Application**:
   - Node.js version: **20** (o superior).
   - Application mode: **Production**.
   - Application root: por ejemplo `inventario` (carpeta bajo tu home).
   - Application URL: tu dominio o subdominio (`inventario.tudominio.cl`).
   - Application startup file: **`app.js`** (recomendado en cPanel).
     Alternativas: `start.sh` o `server.js` (con migraciones manuales).
3. Guarda y anota la ruta física (ej. `/home/USUARIO/inventario`).

### 3. Subir archivos

Sube **todo el contenido** de `deploy/` a la *Application root* (FTP, SFTP o File Manager).

Estructura esperada:

```
inventario/
  server.js          # generado por Next standalone
  start.sh
  package.json
  prisma/
  data/              # se crea sola; debe ser escribible
  public/
  .next/
  .env.example
  README-DESPLIEGUE.md
```

### 4. Variables de entorno (cPanel)

En la app Node.js → **Environment variables** (o archivo `.env` en la raíz):

| Variable | Valor ejemplo |
|----------|----------------|
| `NODE_ENV` | `production` |
| `PORT` | el que asigne cPanel (a veces lo define solo) |
| `HOSTNAME` | `0.0.0.0` |
| `DATABASE_URL` | `file:../data/prod.db` |
| `AUTH_SECRET` | cadena larga aleatoria |
| `COOKIE_SECURE` | `true` |
| `RUN_SEED` | `true` solo el primer arranque |

**No uses** `HOSTNAME=0.0.0.0` en cPanel: rompe el login (redirect a `0.0.0.0:3000`).

Copia `.env.example` → `.env` y edítalo.

Genera un secreto:

```bash
openssl rand -base64 32
```

Si el login muestra error, en Terminal:

```bash
cd ~/app
npx prisma migrate deploy
node scripts/seed-admin.mjs
```

### 5. Instalar dependencias y migrar

En la terminal de la app Node.js (botón *Open* / SSH):

```bash
cd ~/inventario   # o la ruta de Application root
npm install --omit=dev
# Si hace falta el cliente Prisma en el servidor:
npx prisma generate
npx prisma migrate deploy
RUN_SEED=true npx tsx prisma/seed.ts   # solo primera vez
```

Luego en cPanel: **Restart** la aplicación.

### 6. Probar

Abre `https://tudominio.cl/login`

| Usuario | Contraseña |
|---------|------------|
| `admin` | `inventario2026` |

**Cambia la contraseña** después del primer acceso (o regenera el usuario en seed).

---

## Opción B — VPS BlueHosting (SSH + PM2)

```bash
# En el servidor
cd /var/www/inventario   # después de subir deploy/
cp .env.example .env
nano .env                # AUTH_SECRET, COOKIE_SECURE=true, DATABASE_URL

npm install --omit=dev
npx prisma generate
npx prisma migrate deploy
RUN_SEED=true npx tsx prisma/seed.ts

npm install -g pm2
pm2 start ecosystem.config.cjs
pm2 save
pm2 startup
```

Proxy inverso Nginx/Apache → `http://127.0.0.1:3000` y certificado SSL.

---

## Base de datos

### SQLite (por defecto en esta guía)

- Archivo en `data/prod.db`.
- La carpeta `data/` debe tener permisos de escritura del usuario de la app.
- Simple; ideal para un inventario interno.

### MySQL (si tu plan BlueHosting lo incluye)

1. Crea una BD y usuario en **cPanel → MySQL Databases**.
2. En `prisma/schema.prisma` cambia:

```prisma
datasource db {
  provider = "mysql"
  url      = env("DATABASE_URL")
}
```

3. En `.env`:

```env
DATABASE_URL="mysql://USUARIO:PASSWORD@localhost:3306/NOMBRE_BD"
```

4. Regenera migraciones en local contra MySQL (o `prisma db push` la primera vez) y vuelve a `npm run build:deploy`.

---

## HTTPS y login

Con HTTPS activo:

```env
COOKIE_SECURE=true
```

Sin esto, la sesión puede no guardarse en el navegador.

---

## Checklist rápido

- [ ] Node.js 20+ en el panel
- [ ] Subida de `deploy/`
- [ ] `.env` con `AUTH_SECRET` y `COOKIE_SECURE=true`
- [ ] `prisma migrate deploy` (+ seed inicial)
- [ ] Carpeta `data/` escribible (SQLite)
- [ ] SSL del dominio activo
- [ ] Restart de la aplicación Node
- [ ] Login en `/login`

---

## Problemas frecuentes

| Síntoma | Solución |
|---------|----------|
| `ERR_CONNECTION_REFUSED` en PC local | El hosting es remoto: usa tu **dominio**, no `localhost`. |
| Login no mantiene sesión | `COOKIE_SECURE=true` + HTTPS; revisa que no mezcles `http`/`https`. |
| Error Prisma / base de datos | Permisos en `data/` o `DATABASE_URL` incorrecta. En BlueHosting antiguo puede faltar el engine `debian-openssl-1.0.x` (ya incluido en `binaryTargets` del schema). |
| `/api/setup` → `No autenticado` | Build viejo: vuelve a generar `npm run build:deploy`, sube y reinicia con `app.js`. |
| Login: Query Engine openssl | Regenera con `binaryTargets` y sube `node_modules/.prisma/client` completo. |
| Página en blanco / 503 | Revisa logs de la app Node en cPanel; confirma `start.sh` / `server.js`. |
| Módulo no encontrado | Ejecuta `npm install --omit=dev` y `npx prisma generate` en el servidor. |

---

## Soporte BlueHosting

Si tu plan **no** incluye Node.js, pide a soporte:

1. Activar **Setup Node.js App**, o  
2. Un **VPS** con Node 20 y SSH.

Un hosting solo PHP/Apache **no** puede ejecutar esta app tal cual.
