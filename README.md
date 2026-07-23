# Inventario — Control de Stock

Aplicación web de control de inventario con **Next.js (App Router)**, **Tailwind CSS**, **SQLite** y **Prisma**.

## Funcionalidades

- **Login**: acceso con usuario y contraseña (sesión protegida).
- **Artículos**: crear, editar y listar productos (código, nombre, descripción, stock, CUP).
- **Documentos**:
  - **Entrada / Compra**: aumenta stock y recalcula el Costo Unitario Promedio (CUP/PMP).
  - **Salida / Rebaja**: disminuye stock; el CUP se mantiene.
- **Movimientos**: historial con fecha, tipo, N° documento, producto, cantidad y precio.
- **Dashboard**: stock, CUP y valor total (`stock × CUP`).

### Fórmula CUP (entrada)

```
Nuevo CUP = (Stock Actual × CUP Actual + Cantidad × Precio Unitario) / (Stock Actual + Cantidad)
```

## Requisitos

- Node.js 20+
- npm

## Instalación

```bash
npm install
cp .env.example .env   # si no existe .env
npx prisma migrate dev
npm run db:seed
npm run dev
```

Abre [http://localhost:3000](http://localhost:3000).

### Acceso

| Usuario | Contraseña |
|---------|------------|
| `admin` | `inventario2026` |

Define `AUTH_SECRET` en `.env` (ver `.env.example`).

El seed carga el catálogo de 18 productos Sonic/CMC, registra el **ingreso inicial**
(`INI-2026-01-01`) al **01/01/2026** con las cantidades de stock y asigna el **CUP en CLP**
de cada artículo según la planilla de costos.

## Scripts

| Script | Descripción |
|--------|-------------|
| `npm run dev` | Servidor de desarrollo |
| `npm run build` | Build de producción |
| `npm start` | Servidor de producción |
| `npm run db:migrate` | Migraciones Prisma |
| `npm run db:generate` | Generar cliente Prisma |
| `npm run db:seed` | Cargar catálogo de productos |

## API

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET/POST` | `/api/products` | Listar / crear productos |
| `GET/PUT/DELETE` | `/api/products/[id]` | Obtener / editar / eliminar |
| `GET/POST` | `/api/movements` | Listar / registrar movimientos |
| `GET` | `/api/dashboard` | Resumen e inventario valorizado |

## Estructura

```
prisma/schema.prisma     # Modelos Product y Movement
prisma/seed.ts           # Catálogo inicial de productos
src/lib/prisma.ts        # Cliente Prisma
src/lib/inventory.ts     # Cálculo de CUP
src/app/api/             # Rutas API
src/app/                 # Páginas (Dashboard, Artículos, Documentos, Movimientos)
```
