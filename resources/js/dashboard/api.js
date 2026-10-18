/* -------------------------------------------------------------------------
 * API LAYER
 * Set window.API_BASE to point this at your Laravel API. GET /settings,
 * /services, /stylists, and /reviews are public; everything else needs the
 * bearer token returned by login/register. There is no demo/offline mode:
 * if the API is unreachable, data stays empty rather than being masked
 * with fake content, and the sidebar shows "Connection issue".
 *
 * The token is kept in localStorage (see savedToken below) so a refresh does
 * not log people out.
 * ---------------------------------------------------------------------- */
export const API_BASE = (typeof window !== "undefined" && window.API_BASE) || "/api";
export const GA_MEASUREMENT_ID = (typeof window !== "undefined" && window.GA_MEASUREMENT_ID) || null;

/* A 401 on a request that carried a token means the token expired or was revoked.
 * App registers a handler so it can send the person back to the login screen instead
 * of leaving the dashboard silently empty. Login attempts send no token, so a wrong
 * password (also a 401) never triggers it. */
let unauthorizedHandler = null;
export function setUnauthorizedHandler(fn) { unauthorizedHandler = fn; }
function checkUnauthorized(res, token) {
  if (res.status === 401 && token && unauthorizedHandler) unauthorizedHandler(token);
}

export async function apiGet(path, token) {
  try {
    const res = await fetch(`${API_BASE}${path}`, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
    checkUnauthorized(res, token);
    if (!res.ok) return null;
    return await res.json();
  } catch { return null; }
}
export async function apiSend(path, method, body, token) {
  try {
    const res = await fetch(`${API_BASE}${path}`, {
      method,
      headers: { "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify(body),
    });
    checkUnauthorized(res, token);
    const json = await res.json().catch(() => null);
    if (!res.ok) return { error: true, code: json?.code, message: json?.message || firstError(json) || "Something went wrong." };
    return json;
  } catch { return null; }
}
export async function apiDelete(path, token) {
  try {
    const res = await fetch(`${API_BASE}${path}`, { method: "DELETE", headers: token ? { Authorization: `Bearer ${token}` } : {} });
    checkUnauthorized(res, token);
    return res.ok;
  } catch { return false; }
}
/* Keeps the login across page refreshes. Wrapped in try/catch because browser
 * storage can be blocked (private windows, strict privacy settings). */
const TOKEN_KEY = "vivi.token";
export const savedToken = {
  get() { try { return localStorage.getItem(TOKEN_KEY); } catch { return null; } },
  set(t) { try { localStorage.setItem(TOKEN_KEY, t); } catch { /* ignore */ } },
  clear() { try { localStorage.removeItem(TOKEN_KEY); } catch { /* ignore */ } },
};

/* Remembers the branch a client booked at last, so the next booking starts there. Same try/catch
 * reasoning as above: storage can be blocked, and the app works fine without it. */
const BRANCH_KEY = "vivi.branch";
export const savedBranch = {
  get() { try { return localStorage.getItem(BRANCH_KEY) || ""; } catch { return ""; } },
  set(id) { try { localStorage.setItem(BRANCH_KEY, String(id)); } catch { /* ignore */ } },
};

function firstError(json) {
  const errs = json?.errors;
  if (!errs) return null;
  const first = Object.values(errs)[0];
  return Array.isArray(first) ? first[0] : first;
}
