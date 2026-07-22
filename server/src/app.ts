import cors from "cors";
import express, { type Express } from "express";
import { OperationStore, ValidationError } from "./store.js";

export function createApp(store: OperationStore): Express {
  const app = express();
  app.use(cors());
  app.use(express.json());

  app.get("/api/health", (_req, res) => {
    res.json({ status: "ok", service: "auto-operaciones", time: new Date().toISOString() });
  });

  app.get("/api/operations", (_req, res) => {
    res.json(store.list());
  });

  app.post("/api/operations", (req, res) => {
    try {
      const op = store.create({
        name: req.body?.name,
        description: req.body?.description,
      });
      res.status(201).json(op);
    } catch (err) {
      if (err instanceof ValidationError) {
        res.status(400).json({ error: err.message });
        return;
      }
      throw err;
    }
  });

  app.patch("/api/operations/:id", (req, res) => {
    const op = store.toggle(req.params.id);
    if (!op) {
      res.status(404).json({ error: "Operación no encontrada" });
      return;
    }
    res.json(op);
  });

  app.post("/api/operations/:id/run", (req, res) => {
    try {
      const op = store.run(req.params.id);
      if (!op) {
        res.status(404).json({ error: "Operación no encontrada" });
        return;
      }
      res.json(op);
    } catch (err) {
      if (err instanceof ValidationError) {
        res.status(409).json({ error: err.message });
        return;
      }
      throw err;
    }
  });

  app.delete("/api/operations/:id", (req, res) => {
    const removed = store.remove(req.params.id);
    if (!removed) {
      res.status(404).json({ error: "Operación no encontrada" });
      return;
    }
    res.status(204).send();
  });

  return app;
}
