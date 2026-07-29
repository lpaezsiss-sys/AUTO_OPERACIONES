import { readdirSync, readFileSync, existsSync } from "fs";
import path from "path";
import { prisma } from "@/lib/prisma";

function migrationsDir(): string {
  const candidates = [
    path.join(process.cwd(), "prisma", "migrations"),
    path.join(__dirname, "..", "..", "prisma", "migrations"),
  ];
  for (const dir of candidates) {
    if (existsSync(dir)) return dir;
  }
  return candidates[0];
}

async function ensureMigrationsTable() {
  await prisma.$executeRawUnsafe(`
    CREATE TABLE IF NOT EXISTS "_prisma_migrations" (
      "id" TEXT PRIMARY KEY NOT NULL,
      "checksum" TEXT NOT NULL,
      "finished_at" DATETIME,
      "migration_name" TEXT NOT NULL,
      "logs" TEXT,
      "rolled_back_at" DATETIME,
      "started_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      "applied_steps_count" INTEGER NOT NULL DEFAULT 0
    )
  `);
}

async function appliedNames(): Promise<Set<string>> {
  try {
    const rows = await prisma.$queryRawUnsafe<Array<{ migration_name: string }>>(
      `SELECT migration_name FROM "_prisma_migrations"`
    );
    return new Set(rows.map((r) => r.migration_name));
  } catch {
    return new Set();
  }
}

function splitSql(sql: string): string[] {
  return sql
    .split(";")
    .map((chunk) =>
      chunk
        .split("\n")
        .filter((line) => !line.trim().startsWith("--"))
        .join("\n")
        .trim()
    )
    .filter((s) => s.length > 0);
}

/** Aplica migraciones pendientes leyendo prisma/migrations (sin CLI). */
export async function applyPendingMigrations(): Promise<{
  applied: string[];
  skipped: string[];
}> {
  const dir = migrationsDir();
  if (!existsSync(dir)) {
    throw new Error(`No se encontró prisma/migrations en ${dir}`);
  }

  await ensureMigrationsTable();
  const done = await appliedNames();
  const folders = readdirSync(dir, { withFileTypes: true })
    .filter((d) => d.isDirectory())
    .map((d) => d.name)
    .sort();

  const applied: string[] = [];
  const skipped: string[] = [];

  for (const name of folders) {
    if (done.has(name)) {
      skipped.push(name);
      continue;
    }
    const file = path.join(dir, name, "migration.sql");
    if (!existsSync(file)) continue;
    const sql = readFileSync(file, "utf8");
    const statements = splitSql(sql);

    for (const stmt of statements) {
      await prisma.$executeRawUnsafe(stmt);
    }

    const id = `${Date.now()}-${name}`;
    await prisma.$executeRawUnsafe(
      `INSERT INTO "_prisma_migrations"
        ("id", "checksum", "finished_at", "migration_name", "logs", "rolled_back_at", "started_at", "applied_steps_count")
       VALUES (?, ?, CURRENT_TIMESTAMP, ?, NULL, NULL, CURRENT_TIMESTAMP, ?)`,
      id,
      "manual-setup",
      name,
      statements.length
    );
    applied.push(name);
  }

  return { applied, skipped };
}
