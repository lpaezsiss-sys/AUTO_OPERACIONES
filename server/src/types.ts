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

export interface CreateOperationInput {
  name: string;
  description?: string;
}
