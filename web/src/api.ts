export type OperationStatus = "active" | "paused";

export interface RunRecord {
  timestamp: string;
  message: string;
}

export interface Operation {
  id: string;
  name: string;
  description: string;
  status: OperationStatus;
  runCount: number;
  lastRun: string | null;
  createdAt: string;
  runs: RunRecord[];
}

async function handle<T>(res: Response): Promise<T> {
  if (!res.ok) {
    let message = `Error ${res.status}`;
    try {
      const body = await res.json();
      if (body?.error) message = body.error;
    } catch {
      /* ignore */
    }
    throw new Error(message);
  }
  if (res.status === 204) return undefined as T;
  return res.json() as Promise<T>;
}

export const api = {
  list: () => fetch("/api/operations").then((r) => handle<Operation[]>(r)),

  create: (name: string, description: string) =>
    fetch("/api/operations", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, description }),
    }).then((r) => handle<Operation>(r)),

  toggle: (id: string) =>
    fetch(`/api/operations/${id}`, { method: "PATCH" }).then((r) => handle<Operation>(r)),

  run: (id: string) =>
    fetch(`/api/operations/${id}/run`, { method: "POST" }).then((r) => handle<Operation>(r)),

  remove: (id: string) =>
    fetch(`/api/operations/${id}`, { method: "DELETE" }).then((r) => handle<void>(r)),
};
