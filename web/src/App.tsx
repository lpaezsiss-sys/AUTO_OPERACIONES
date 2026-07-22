import { useEffect, useMemo, useState } from "react";
import { api, type Operation } from "./api";

export default function App() {
  const [operations, setOperations] = useState<Operation[]>([]);
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  async function refresh() {
    try {
      setOperations(await api.list());
      setError(null);
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void refresh();
  }, []);

  const stats = useMemo(() => {
    const total = operations.length;
    const active = operations.filter((o) => o.status === "active").length;
    const runs = operations.reduce((sum, o) => sum + o.runCount, 0);
    return { total, active, runs };
  }, [operations]);

  async function withErrors(fn: () => Promise<unknown>) {
    try {
      await fn();
      setError(null);
      await refresh();
    } catch (e) {
      setError((e as Error).message);
    }
  }

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    if (!name.trim()) {
      setError("El nombre de la operación es obligatorio");
      return;
    }
    await withErrors(async () => {
      await api.create(name.trim(), description.trim());
      setName("");
      setDescription("");
    });
  }

  return (
    <div className="app">
      <header className="hero">
        <div>
          <h1>AUTO_OPERACIONES</h1>
          <p className="subtitle">Panel de automatización de operaciones</p>
        </div>
        <div className="stats">
          <Stat label="Operaciones" value={stats.total} />
          <Stat label="Activas" value={stats.active} />
          <Stat label="Ejecuciones" value={stats.runs} />
        </div>
      </header>

      {error && <div className="banner error">{error}</div>}

      <section className="card">
        <h2>Nueva operación</h2>
        <form className="form" onSubmit={handleCreate}>
          <input
            className="input"
            placeholder="Nombre (p. ej. Backup diario)"
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
          <input
            className="input"
            placeholder="Descripción (opcional)"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
          />
          <button className="btn primary" type="submit">
            Crear
          </button>
        </form>
      </section>

      <section className="list">
        {loading ? (
          <p className="muted">Cargando…</p>
        ) : operations.length === 0 ? (
          <p className="muted">Aún no hay operaciones. Crea la primera arriba.</p>
        ) : (
          operations.map((op) => (
            <article key={op.id} className="op">
              <div className="op-head">
                <div>
                  <h3>{op.name}</h3>
                  {op.description && <p className="muted">{op.description}</p>}
                </div>
                <span className={`pill ${op.status}`}>
                  {op.status === "active" ? "Activa" : "Pausada"}
                </span>
              </div>
              <div className="op-meta">
                <span>Ejecuciones: {op.runCount}</span>
                <span>
                  Última: {op.lastRun ? new Date(op.lastRun).toLocaleString() : "nunca"}
                </span>
              </div>
              <div className="op-actions">
                <button
                  className="btn"
                  disabled={op.status !== "active"}
                  onClick={() => withErrors(() => api.run(op.id))}
                >
                  Ejecutar
                </button>
                <button className="btn" onClick={() => withErrors(() => api.toggle(op.id))}>
                  {op.status === "active" ? "Pausar" : "Activar"}
                </button>
                <button
                  className="btn danger"
                  onClick={() => withErrors(() => api.remove(op.id))}
                >
                  Eliminar
                </button>
              </div>
              {op.runs.length > 0 && (
                <ul className="runs">
                  {op.runs.slice(0, 3).map((r, i) => (
                    <li key={i}>
                      <time>{new Date(r.timestamp).toLocaleTimeString()}</time> — {r.message}
                    </li>
                  ))}
                </ul>
              )}
            </article>
          ))
        )}
      </section>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="stat">
      <span className="stat-value">{value}</span>
      <span className="stat-label">{label}</span>
    </div>
  );
}
