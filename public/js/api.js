const API_BASE = "/api";

export async function apiGet(path) {
  const res = await fetch(`${API_BASE}${path}`, { method: "GET" });
  const data = await res.json().catch(() => null);
  if (!res.ok) throw new Error(data?.message || data?.error || "Error");
  return data;
}

export async function apiPost(path, payload) {
  const res = await fetch(`${API_BASE}${path}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  const data = await res.json().catch(() => null);

  if (data === null && res.ok) {
    throw new Error("El servidor envió una respuesta vacía o inválida.");
  }

  // Si está cerrado (403) o cualquier error
  if (!res.ok) {
    const msg = data?.message || data?.error || "No se pudo completar la acción.";
    const err = new Error(msg);
    err.status = res.status;
    err.data = data;
    throw err;
  }
  return data;
}