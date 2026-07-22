import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";
import { createApp } from "./app.js";
import { OperationStore } from "./store.js";

const __dirname = dirname(fileURLToPath(import.meta.url));
const PORT = Number(process.env.PORT ?? 4000);
const DATA_FILE = process.env.DATA_FILE ?? resolve(__dirname, "../data/operations.json");

const store = new OperationStore(DATA_FILE);
const app = createApp(store);

app.listen(PORT, () => {
  console.log(`[auto-operaciones] API escuchando en http://localhost:${PORT}`);
  console.log(`[auto-operaciones] Datos persistidos en ${DATA_FILE}`);
});
