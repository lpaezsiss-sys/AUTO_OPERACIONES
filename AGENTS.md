# AGENTS.md

## Cursor Cloud specific instructions

This is an npm-workspaces monorepo (`server`, `web`) for **AUTO_OPERACIONES**, a full-stack
operations-automation dashboard. Standard commands live in the root `package.json` and
`README.md`; prefer those. Notes below are the non-obvious bits.

### Services

| Service | Dir      | Dev command          | Port | Notes                                             |
| ------- | -------- | -------------------- | ---- | ------------------------------------------------- |
| Backend | `server` | `npm run dev:server` | 4000 | Express + TS via `tsx watch`. REST under `/api`.  |
| Frontend| `web`    | `npm run dev:web`    | 5173 | Vite + React. Proxies `/api` → `localhost:4000`.  |

Run both together with `npm run dev` (uses `concurrently`).

### Non-obvious notes

- The frontend talks to the backend **only through the Vite dev proxy** (`/api` → port 4000,
  configured in `web/vite.config.ts`). If you hit the backend directly, use port 4000.
- Backend persistence is a JSON file at `server/data/operations.json` (path overridable via
  `DATA_FILE`). It is git-ignored; deleting it resets all operations. Tests use an in-memory
  store (`new OperationStore(":memory:")`) so they never touch that file.
- Lint/test/build are run from the repo root: `npm run lint`, `npm test`, `npm run build`.
  `npm test` only covers the backend (Vitest via `supertest`); there is no frontend test suite yet.
- The backend must be running for the frontend's data to load; an empty/blank list with an
  error banner usually means port 4000 isn't up.
