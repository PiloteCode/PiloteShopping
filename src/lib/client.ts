"use client";

const CSRF_COOKIE = "pilote_csrf";

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp("(^|; )" + name + "=([^;]*)"));
  return match ? decodeURIComponent(match[2]) : null;
}

interface FetchOptions {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
}

export async function apiFetch<T = unknown>(path: string, opts: FetchOptions = {}): Promise<T> {
  const { method = "GET", body } = opts;
  const headers: Record<string, string> = {};
  if (body !== undefined) headers["content-type"] = "application/json";
  if (method !== "GET") {
    const csrf = getCookie(CSRF_COOKIE);
    if (csrf) headers["x-csrf-token"] = csrf;
  }
  const res = await fetch(path, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
    credentials: "same-origin",
  });
  let json: { success?: boolean; data?: T; error?: string } | null = null;
  try {
    json = await res.json();
  } catch {
    /* ignore */
  }
  if (!res.ok || !json?.success) {
    throw new Error(json?.error || "Une erreur est survenue");
  }
  return json.data as T;
}

export const apiGet = <T = unknown>(path: string) => apiFetch<T>(path);
export const apiPost = <T = unknown>(path: string, body?: unknown) => apiFetch<T>(path, { method: "POST", body });
export const apiPut = <T = unknown>(path: string, body?: unknown) => apiFetch<T>(path, { method: "PUT", body });
export const apiPatch = <T = unknown>(path: string, body?: unknown) => apiFetch<T>(path, { method: "PATCH", body });
export const apiDelete = <T = unknown>(path: string, body?: unknown) => apiFetch<T>(path, { method: "DELETE", body });
