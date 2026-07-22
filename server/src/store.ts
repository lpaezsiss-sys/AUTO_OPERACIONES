import { randomUUID } from "node:crypto";
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname } from "node:path";
import type { CreateOperationInput, Operation } from "./types.js";

const MAX_RUN_HISTORY = 20;

/**
 * Store for automation operations. Pass a file path to persist to disk, or
 * omit it (or pass ":memory:") to keep everything in memory (used by tests).
 */
export class OperationStore {
  private operations: Operation[] = [];
  private readonly filePath: string | null;

  constructor(filePath?: string) {
    this.filePath = filePath && filePath !== ":memory:" ? filePath : null;
    this.load();
  }

  private load(): void {
    if (!this.filePath || !existsSync(this.filePath)) return;
    try {
      const raw = readFileSync(this.filePath, "utf-8");
      const parsed = JSON.parse(raw) as Operation[];
      if (Array.isArray(parsed)) this.operations = parsed;
    } catch {
      this.operations = [];
    }
  }

  private persist(): void {
    if (!this.filePath) return;
    mkdirSync(dirname(this.filePath), { recursive: true });
    writeFileSync(this.filePath, JSON.stringify(this.operations, null, 2), "utf-8");
  }

  list(): Operation[] {
    return [...this.operations].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
  }

  get(id: string): Operation | undefined {
    return this.operations.find((op) => op.id === id);
  }

  create(input: CreateOperationInput): Operation {
    const name = input.name?.trim();
    if (!name) {
      throw new ValidationError("El nombre de la operación es obligatorio");
    }
    const operation: Operation = {
      id: randomUUID(),
      name,
      description: input.description?.trim() ?? "",
      status: "active",
      runCount: 0,
      lastRun: null,
      createdAt: new Date().toISOString(),
      runs: [],
    };
    this.operations.push(operation);
    this.persist();
    return operation;
  }

  toggle(id: string): Operation | undefined {
    const op = this.get(id);
    if (!op) return undefined;
    op.status = op.status === "active" ? "paused" : "active";
    this.persist();
    return op;
  }

  run(id: string): Operation | undefined {
    const op = this.get(id);
    if (!op) return undefined;
    if (op.status !== "active") {
      throw new ValidationError("No se puede ejecutar una operación pausada");
    }
    const now = new Date().toISOString();
    op.runCount += 1;
    op.lastRun = now;
    op.runs.unshift({
      timestamp: now,
      message: `Operación "${op.name}" ejecutada correctamente`,
    });
    op.runs = op.runs.slice(0, MAX_RUN_HISTORY);
    this.persist();
    return op;
  }

  remove(id: string): boolean {
    const before = this.operations.length;
    this.operations = this.operations.filter((op) => op.id !== id);
    const removed = this.operations.length < before;
    if (removed) this.persist();
    return removed;
  }
}

export class ValidationError extends Error {}
