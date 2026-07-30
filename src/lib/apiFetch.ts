/**
 * fetch a APIs de la app: envía cookies y, si hay 401, vuelve al login.
 */
export async function apiFetch(
  input: RequestInfo | URL,
  init: RequestInit = {}
): Promise<Response> {
  const res = await fetch(input, {
    ...init,
    credentials: "same-origin",
    headers: init.headers,
  });

  if (res.status === 401 && typeof window !== "undefined") {
    const path = window.location.pathname + window.location.search;
    const next = path.startsWith("/login") ? "/" : path;
    window.location.assign(
      `/login?next=${encodeURIComponent(next)}&error=${encodeURIComponent(
        "Sesión expirada. Vuelve a iniciar sesión."
      )}`
    );
  }

  return res;
}
