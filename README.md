# AUTO_OPERACIONES

Panel de automatización de operaciones — un tablero full-stack para definir,
ejecutar y monitorear operaciones automatizadas.

## Stack

- **Backend**: Node.js + Express + TypeScript (API REST), persistencia en archivo JSON.
- **Frontend**: React + Vite + TypeScript.
- **Monorepo**: npm workspaces (`server`, `web`).
- **Calidad**: ESLint (flat config) + Vitest.

## Requisitos

- Node.js >= 20
- npm >= 10

## Instalación

```bash
npm install
```

## Desarrollo

Levanta backend y frontend a la vez:

```bash
npm run dev
```

- Frontend (Vite): http://localhost:5173
- Backend (API): http://localhost:4000 (proxied como `/api` desde el frontend)

También puedes levantarlos por separado:

```bash
npm run dev:server
npm run dev:web
```

## Scripts útiles

| Comando          | Descripción                              |
| ---------------- | ---------------------------------------- |
| `npm run dev`    | Backend + frontend en modo desarrollo    |
| `npm run lint`   | ESLint sobre todo el monorepo            |
| `npm test`       | Pruebas del backend (Vitest)             |
| `npm run build`  | Build de producción (server + web)       |
| `npm start`      | Ejecuta el backend compilado             |

## API

| Método | Ruta                          | Descripción                       |
| ------ | ----------------------------- | --------------------------------- |
| GET    | `/api/health`                 | Healthcheck                       |
| GET    | `/api/operations`             | Lista operaciones                 |
| POST   | `/api/operations`             | Crea una operación                |
| PATCH  | `/api/operations/:id`         | Activa/pausa una operación        |
| POST   | `/api/operations/:id/run`     | Ejecuta una operación             |
| DELETE | `/api/operations/:id`         | Elimina una operación             |
