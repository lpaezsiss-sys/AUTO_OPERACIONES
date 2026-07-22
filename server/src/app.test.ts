import { describe, expect, it } from "vitest";
import request from "supertest";
import { createApp } from "./app.js";
import { OperationStore } from "./store.js";

function makeApp() {
  return createApp(new OperationStore(":memory:"));
}

describe("operations API", () => {
  it("healthcheck responds ok", async () => {
    const res = await request(makeApp()).get("/api/health");
    expect(res.status).toBe(200);
    expect(res.body.status).toBe("ok");
  });

  it("starts with an empty list", async () => {
    const res = await request(makeApp()).get("/api/operations");
    expect(res.status).toBe(200);
    expect(res.body).toEqual([]);
  });

  it("creates an operation", async () => {
    const app = makeApp();
    const res = await request(app)
      .post("/api/operations")
      .send({ name: "Backup diario", description: "Respaldo de la base de datos" });
    expect(res.status).toBe(201);
    expect(res.body.name).toBe("Backup diario");
    expect(res.body.status).toBe("active");
    expect(res.body.runCount).toBe(0);
  });

  it("rejects an operation without a name", async () => {
    const res = await request(makeApp()).post("/api/operations").send({ description: "x" });
    expect(res.status).toBe(400);
  });

  it("runs an operation and records the run", async () => {
    const app = makeApp();
    const created = await request(app).post("/api/operations").send({ name: "Sync" });
    const id = created.body.id;

    const run = await request(app).post(`/api/operations/${id}/run`);
    expect(run.status).toBe(200);
    expect(run.body.runCount).toBe(1);
    expect(run.body.lastRun).not.toBeNull();
    expect(run.body.runs).toHaveLength(1);
  });

  it("cannot run a paused operation", async () => {
    const app = makeApp();
    const created = await request(app).post("/api/operations").send({ name: "Sync" });
    const id = created.body.id;

    await request(app).patch(`/api/operations/${id}`); // toggle -> paused
    const run = await request(app).post(`/api/operations/${id}/run`);
    expect(run.status).toBe(409);
  });

  it("deletes an operation", async () => {
    const app = makeApp();
    const created = await request(app).post("/api/operations").send({ name: "Temp" });
    const id = created.body.id;

    const del = await request(app).delete(`/api/operations/${id}`);
    expect(del.status).toBe(204);

    const list = await request(app).get("/api/operations");
    expect(list.body).toEqual([]);
  });
});
