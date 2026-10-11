import React, { useState, useMemo, useEffect, useRef, useContext, createContext, lazy, Suspense } from "react";
import {
  LayoutDashboard, CalendarDays, Users, Scissors, Package, BarChart3,
  Settings as SettingsIcon, Globe, Search, Plus, Check, X, Play, Minus,
  AlertTriangle, ToggleLeft, ToggleRight, LogOut, Sun, Moon, Menu,
  Pencil, ChevronLeft, ChevronRight, Star,
  ClipboardList, ShieldCheck,
} from "lucide-react";
// Charts live in dashboard-charts.jsx and load on demand (recharts is a large library).
const WeeklyRevenueChart = lazy(() => import("./dashboard-charts.jsx").then((m) => ({ default: m.WeeklyRevenueChart })));
const StatusPieChart = lazy(() => import("./dashboard-charts.jsx").then((m) => ({ default: m.StatusPieChart })));
const RevenueByServiceChart = lazy(() => import("./dashboard-charts.jsx").then((m) => ({ default: m.RevenueByServiceChart })));
function ChartPlaceholder({ height }) {
  return <div role="status" aria-label="Loading chart" style={{ height }} className="animate-pulse rounded-lg opacity-30 bg-current" />;
}

/* -------------------------------------------------------------------------
 * API LAYER
 * Set window.API_BASE to point this at your Laravel API. GET /settings,
 * /services, /stylists, and /reviews are public; everything else needs the
 * bearer token returned by login/register. There is no demo/offline mode:
 * if the API is unreachable, data stays empty rather than being masked
 * with fake content, and the sidebar shows "Connection issue".
 *
 * NOTE ON PERSISTENCE: this file intentionally keeps the auth token in
 * React state only, not localStorage/cookies - browser storage APIs are
 * disabled inside Claude artifact previews. Once you deploy this outside
 * that preview, swap the token state for a real httpOnly cookie or
 * localStorage so login survives a page refresh.
 * ---------------------------------------------------------------------- */
const API_BASE = (typeof window !== "undefined" && window.API_BASE) || "/api";
const GA_MEASUREMENT_ID = (typeof window !== "undefined" && window.GA_MEASUREMENT_ID) || null;

async function apiGet(path, token) {
  try {
    const res = await fetch(`${API_BASE}${path}`, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
    if (!res.ok) return null;
    return await res.json();
  } catch { return null; }
}
async function apiSend(path, method, body, token) {
  try {
    const res = await fetch(`${API_BASE}${path}`, {
      method,
      headers: { "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify(body),
    });
    const json = await res.json().catch(() => null);
    if (!res.ok) return { error: true, message: json?.message || firstError(json) || "Something went wrong." };
    return json;
  } catch { return null; }
}
async function apiDelete(path, token) {
  try {
    const res = await fetch(`${API_BASE}${path}`, { method: "DELETE", headers: token ? { Authorization: `Bearer ${token}` } : {} });
    return res.ok;
  } catch { return false; }
}
/* Keeps the login across page refreshes. Wrapped in try/catch because browser
 * storage can be blocked (private windows, strict privacy settings). */
const TOKEN_KEY = "vivi.token";
const savedToken = {
  get() { try { return localStorage.getItem(TOKEN_KEY); } catch { return null; } },
  set(t) { try { localStorage.setItem(TOKEN_KEY, t); } catch { /* ignore */ } },
  clear() { try { localStorage.removeItem(TOKEN_KEY); } catch { /* ignore */ } },
};

function firstError(json) {
  const errs = json?.errors;
  if (!errs) return null;
  const first = Object.values(errs)[0];
  return Array.isArray(first) ? first[0] : first;
}

/* ---------------------------------- Theme --------------------------------- */

const LIGHT_COLORS = {
  bgApp: "#FFFFFF", wine: "#7A3956", wineSoft: "#F3E4EA", rose: "#C97C96", // rose is for fills/icons only (3.1:1); text uses wine/subtle
  roseSoft: "#F8ECF0", ink: "#2E2229", subtle: "#6B5961", border: "#EFE4E9", // subtle darkened from #8C7A82: now >=5.3:1 on every light surface (WCAG AA)
  success: "#456644", successSoft: "#E9F0E8", danger: "#B0453F", dangerSoft: "#F7E9E7",
  warn: "#8A6A0E", warnSoft: "#FBF1D8", cardBg: "#FFFFFF", pageBg: "#FDFBFC",
};
const DARK_COLORS = {
  bgApp: "#1C1418", wine: "#E7A9BE", wineSoft: "#3A2530", rose: "#D794AC",
  roseSoft: "#2A1E23", ink: "#F3E9ED", subtle: "#B79AA5", border: "#3A2A31",
  success: "#8FC08B", successSoft: "#1E2B1D", danger: "#E5897C", dangerSoft: "#3A211D",
  warn: "#E3C15E", warnSoft: "#332A16", cardBg: "#241A1F", pageBg: "#170F13",
};

const ThemeContext = createContext({ colors: LIGHT_COLORS, theme: "light", toggleTheme: () => {} });
const useTheme = () => useContext(ThemeContext);

// Brand shown in the sidebar, auth screens and tab title. Follows the salon name
// from Settings so the dashboard matches the public site; falls back to the product name.
const DEFAULT_BRAND = "Vivi - Sphere";
const BrandContext = createContext(DEFAULT_BRAND);
const useBrand = () => useContext(BrandContext);

const sansFont = "'Work Sans', system-ui, sans-serif";
const serifFont = "'Newsreader', Georgia, serif";

function GlobalStyle() {
  const { colors } = useTheme();
  return (
    <style>{`
      body { font-family: ${sansFont}; -webkit-font-smoothing: antialiased; }
      button, a, input, select, textarea { transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease, transform 0.1s ease; }
      button { cursor: pointer; }
      button:disabled { cursor: not-allowed; opacity: 0.6; }
      button:focus-visible, a:focus-visible { outline: 2px solid ${colors.wine}; outline-offset: 2px; }
      input:focus, select:focus, textarea:focus { outline: none; border-color: ${colors.wine} !important; box-shadow: 0 0 0 3px ${colors.wineSoft}; }
      .btn-wine { background: ${colors.wine}; color: ${colors.bgApp}; box-shadow: 0 1px 2px rgba(46,34,41,0.12); }
      .btn-wine:hover { filter: brightness(0.9); }
      .btn-wine:active { transform: scale(0.98); }
      .btn-outline-wine:hover { background: ${colors.wineSoft}; }
      select { border-radius: 8px; padding-right: 1.75rem; }
      table thead tr { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; }
      table tbody tr { transition: background-color 0.12s ease; }
      table tbody tr:hover { background: ${colors.roseSoft}; }
      .dash-card { transition: box-shadow 0.2s ease, transform 0.2s ease; box-shadow: 0 1px 2px rgba(46,34,41,0.04), 0 4px 16px rgba(122,57,86,0.06); }
      .stat-card:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(46,34,41,0.05), 0 10px 28px rgba(122,57,86,0.12); }
      .nav-link:hover { background: ${colors.roseSoft}; }
      .icon-btn:hover { filter: brightness(0.92); }
      .link-btn:hover { text-decoration: underline; }
    `}</style>
  );
}

/* -------------------------------- Constants -------------------------------- */

function todayStr(offset = 0) {
  const d = new Date();
  d.setDate(d.getDate() + offset);
  return d.toISOString().slice(0, 10);
}
function formatDateLabel(dateStr) {
  const d = new Date(dateStr + "T00:00:00");
  return d.toLocaleDateString(undefined, { weekday: "short", month: "short", day: "numeric" });
}
function timeToMinutes(t) { const [h, m] = t.split(":").map(Number); return h * 60 + m; }
function rangesOverlap(startA, durA, startB, durB) {
  const aStart = timeToMinutes(startA), aEnd = aStart + durA;
  const bStart = timeToMinutes(startB), bEnd = bStart + durB;
  return aStart < bEnd && bStart < aEnd;
}

/* -------------------------------------------------------------------------
 * This app has no built-in demo/seed data. Every array below starts empty
 * and gets populated exclusively from the API on load; if the API is
 * unreachable, these stay empty and the UI shows genuine "no data" /
 * connection-error states rather than masking the problem with fake
 * content. See the `apiConnected` flag and the loading states in App().
 * ---------------------------------------------------------------------- */
const EMPTY_LANDING_CONTENT = {
  salonName: "",
  heroHeadline: "",
  heroSub: "",
  featuredServiceNames: [],
  faqs: [],
  contactPhone: "",
  contactAddress: "",
  mapQuery: "",
  cancellationPolicy: "",
  openTime: "09:00",
  closeTime: "19:00",
};

/** Real weekly revenue, computed from actual completed appointments - not a hardcoded chart. */
function computeWeeklyRevenue(appointments, services) {
  const days = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    days.push(d.toISOString().slice(0, 10));
  }
  return days.map((dateStr) => {
    const label = new Date(dateStr + "T00:00:00").toLocaleDateString(undefined, { weekday: "short" });
    const revenue = appointments
      .filter((a) => a.date === dateStr && a.status === "completed")
      .reduce((sum, a) => sum + (services[a.service]?.price || 0), 0);
    return { day: label, revenue };
  });
}

function toApiSettings(c) {
  return {
    salon_name: c.salonName, hero_headline: c.heroHeadline, hero_sub: c.heroSub, featured_services: c.featuredServiceNames,
    faqs: c.faqs, contact_phone: c.contactPhone, contact_address: c.contactAddress, map_query: c.mapQuery,
    cancellation_policy: c.cancellationPolicy, open_time: c.openTime, close_time: c.closeTime,
  };
}
function fromApiSettings(d) {
  if (!d) return null;
  return {
    salonName: d.salon_name || "", heroHeadline: d.hero_headline, heroSub: d.hero_sub, featuredServiceNames: d.featured_services || [],
    faqs: (d.faqs || []).map((f, i) => ({ id: i + 1, ...f })),
    contactPhone: d.contact_phone, contactAddress: d.contact_address, mapQuery: d.map_query,
    cancellationPolicy: d.cancellation_policy, openTime: d.open_time?.slice(0, 5) || "09:00", closeTime: d.close_time?.slice(0, 5) || "19:00",
  };
}



const NAV_ITEMS = [
  { key: "overview", label: "Overview", icon: LayoutDashboard, roles: ["admin", "manager", "stylist", "client"], group: null },
  { key: "appointments", label: "Appointments", icon: CalendarDays, roles: ["admin", "manager", "stylist", "client"], group: "Day to day" },
  { key: "clients", label: "Clients", icon: Users, roles: ["admin", "manager"], group: "Day to day" },
  { key: "stylists", label: "Stylists", icon: Scissors, roles: ["admin", "manager"], group: "Day to day" },
  { key: "inventory", label: "Inventory", icon: Package, roles: ["admin", "manager"], group: "Day to day" },
  { key: "reports", label: "Reports", icon: BarChart3, roles: ["admin", "manager"], group: "Insights" },
  { key: "reviews", label: "Reviews", icon: Star, roles: ["admin", "manager"], group: "Insights" },
  { key: "website", label: "Website", icon: Globe, roles: ["admin"], group: "Admin" },
  { key: "audit", label: "Audit log", icon: ShieldCheck, roles: ["admin"], group: "Admin" },
  { key: "settings", label: "Settings", icon: SettingsIcon, roles: ["admin", "manager", "stylist", "client"], group: "Admin" },
];

const STATUS_META = {
  confirmed: { label: "Confirmed" }, "in-progress": { label: "In progress" },
  completed: { label: "Completed" }, cancelled: { label: "Cancelled" },
};
function statusColor(status, colors) {
  return { confirmed: colors.subtle, "in-progress": colors.wine, completed: colors.success, cancelled: colors.danger }[status];
}
function initials(name) {
  return (name || "?").split(" ").map((p) => p[0]).slice(0, 2).join("").toUpperCase();
}

/* -------------------------------------------------------------------------
 * API response normalization.
 * The Laravel API returns Eloquent relationships as nested objects
 * (appointment.client = {id, name, phone, ...}, appointment.stylist =
 * {...}, appointment.service = {...}). This component was built around a
 * flatter shape (appointment.client = "Elena Marsh", a plain string) for
 * simplicity everywhere else in the file. Normalize at the API boundary
 * rather than rewriting every render site to handle both shapes.
 * ---------------------------------------------------------------------- */
function normalizeAppointment(a) {
  const [date, time] = String(a.scheduled_at).split(" ");
  return {
    id: a.id,
    date: date,
    time: (time || "00:00:00").slice(0, 5),
    client: a.client?.name ?? a.client,
    service: a.service?.name ?? a.service,
    stylist: a.stylist?.name ?? a.stylist,
    status: a.status,
  };
}

function relativeLastVisit(lastVisitAt) {
  if (!lastVisitAt) return "New client";
  const days = Math.floor((Date.now() - new Date(lastVisitAt).getTime()) / 86400000);
  if (days <= 0) return "Today";
  if (days < 14) return `${days} day${days === 1 ? "" : "s"} ago`;
  const weeks = Math.floor(days / 7);
  return `${weeks} week${weeks === 1 ? "" : "s"} ago`;
}

function normalizeClient(c) {
  return {
    name: c.name,
    phone: c.phone || "-",
    visits: c.visits ?? 0,
    lastVisit: c.last_visit_at !== undefined ? relativeLastVisit(c.last_visit_at) : c.lastVisit,
    spend: Number(c.spend) || 0,
  };
}

function normalizeInventoryItem(i) {
  return {
    id: i.id,
    name: i.name,
    category: i.category || "General",
    qty: i.qty ?? 0,
    reorder: i.reorder_level ?? i.reorder ?? 5,
    unitCost: Number(i.unit_cost ?? i.unitCost) || 0,
  };
}

function normalizeStylist(s) {
  return { id: s.id, name: s.name, role: s.role, active: !!s.active, photoUrl: s.photo_url ?? s.photoUrl ?? "", bio: s.bio ?? "" };
}

function normalizeReview(r) {
  return {
    id: r.id,
    appointmentId: r.appointment_id ?? r.appointmentId,
    clientName: r.client?.name ?? r.clientName,
    stylistName: r.stylist?.name ?? r.stylistName,
    rating: r.rating,
    comment: r.comment || "",
    status: r.status,
  };
}

function normalizeWaitlistEntry(w) {
  return {
    id: w.id,
    clientName: w.client_name ?? w.clientName,
    service: w.service?.name ?? w.service,
    stylist: w.stylist?.name ?? w.stylist ?? null,
    preferredDate: String(w.preferred_date ?? w.preferredDate).slice(0, 10),
  };
}

function useDocumentMeta(title, description) {
  useEffect(() => {
    if (typeof document === "undefined") return;
    document.title = title;
    let meta = document.querySelector('meta[name="description"]');
    if (!meta) { meta = document.createElement("meta"); meta.name = "description"; document.head.appendChild(meta); }
    meta.content = description;
    let og = document.querySelector('meta[property="og:title"]');
    if (!og) { og = document.createElement("meta"); og.setAttribute("property", "og:title"); document.head.appendChild(og); }
    og.content = title;
  }, [title, description]);
}

/* --------------------------------- Notify banner --------------------------------- */

function NotifyBanner({ notice }) {
  const { colors } = useTheme();
  if (!notice) return null;
  const isError = notice.type === "error";
  return (
    <div role="alert" aria-live="polite" style={{ background: isError ? colors.dangerSoft : colors.successSoft, color: isError ? colors.danger : colors.success, border: `1px solid ${isError ? colors.danger : colors.success}` }} className="text-sm px-4 py-2.5 rounded-md mb-4">
      {notice.message}
    </div>
  );
}

function ConfirmModal({ confirm, onCancel, onConfirm }) {
  const { colors } = useTheme();
  const dialogRef = useRef(null);
  const cancelRef = useRef(null);
  const onCancelRef = useRef(onCancel);
  onCancelRef.current = onCancel; // the parent passes a new function every render; don't re-run the effect for it

  // Accessible dialog behaviour: Esc cancels, focus moves into the dialog (on the safe
  // "Cancel" button), Tab stays inside it, and focus returns to where it was on close.
  useEffect(() => {
    if (!confirm) return undefined;
    const previouslyFocused = document.activeElement;
    cancelRef.current?.focus();
    const onKey = (e) => {
      if (e.key === "Escape") { e.preventDefault(); onCancelRef.current(); return; }
      if (e.key !== "Tab" || !dialogRef.current) return;
      const focusable = dialogRef.current.querySelectorAll("button, [href], input, select, textarea, [tabindex]:not([tabindex='-1'])");
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    };
    document.addEventListener("keydown", onKey);
    return () => { document.removeEventListener("keydown", onKey); previouslyFocused?.focus?.(); };
  }, [confirm]);

  if (!confirm) return null;
  return (
    <div style={{ background: "rgba(0,0,0,0.35)" }} className="fixed inset-0 z-50 flex items-center justify-center p-6">
      <div ref={dialogRef} role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-message" style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 max-w-sm w-full dash-card">
        <div id="confirm-dialog-message" className="text-sm mb-4">{confirm.message}</div>
        <div className="flex justify-end gap-2">
          <button ref={cancelRef} onClick={onCancel} style={{ color: colors.subtle }} className="text-sm px-3 py-1.5">Cancel</button>
          <button onClick={onConfirm} style={{ background: colors.danger, color: "#fff" }} className="text-sm px-3 py-1.5 rounded-md font-medium">{confirm.confirmLabel || "Confirm"}</button>
        </div>
      </div>
    </div>
  );
}

/* ---------------------------------- App --------------------------------- */

/* ------------------------------- Error boundary ------------------------------- */
// Without this, any uncaught render error (e.g. an unexpected API response
// shape) blanks the entire page white with no explanation - exactly what
// happened before normalizeAppointment/normalizeClient existed. This won't
// prevent bugs, but turns "blank white screen" into a visible, readable
// error instead of a silent crash.
class ErrorBoundary extends React.Component {
  constructor(props) { super(props); this.state = { error: null }; }
  static getDerivedStateFromError(error) { return { error }; }
  componentDidCatch(error, info) { console.error("Dashboard crashed:", error, info); }
  render() {
    if (this.state.error) {
      return (
        <div style={{ fontFamily: sansFont, padding: 32, maxWidth: 640, margin: "0 auto" }}>
          <h1 style={{ fontFamily: serifFont, fontSize: 24, marginBottom: 12 }}>Something went wrong</h1>
          <p style={{ color: "#8C7A82", marginBottom: 16 }}>
            The app hit an unexpected error and couldn't continue rendering. Check the browser console
            (F12 → Console) for the technical detail, or reload the page to start over.
          </p>
          <pre style={{ background: "#F7E9E7", color: "#B0453F", padding: 12, borderRadius: 8, fontSize: 12, overflowX: "auto" }}>
            {String(this.state.error?.message || this.state.error)}
          </pre>
          <button onClick={() => window.location.reload()} style={{ marginTop: 16, background: "#7A3956", color: "#fff", padding: "10px 16px", borderRadius: 6, fontSize: 14 }}>
            Reload
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}

export default function App() {
  const [theme, setTheme] = useState("light");
  const colors = theme === "dark" ? DARK_COLORS : LIGHT_COLORS;
  const toggleTheme = () => setTheme((t) => (t === "light" ? "dark" : "light"));

  const [screen, setScreen] = useState("login");
  const [user, setUser] = useState(null);
  const [token, setToken] = useState(null);
  const [resetInfo, setResetInfo] = useState(null);

  // When mounted inside the real Laravel app (see spa.blade.php / routes/web.php),
  // the marketing pages are server-rendered Blade views, not this component's
  // own "landing" screen. If we're sitting at bare /app with no sub-path,
  // send the visitor to the real landing page instead of duplicating it.
  // Harmless no-op in the Claude preview, where this path never matches.
  useEffect(() => {
    if (typeof window === "undefined") return;
    const path = window.location.pathname;
    if (path === "/app" || path === "/app/") { window.location.href = "/"; return; }
    if (path.endsWith("/login")) setScreen("login");
    else if (path.endsWith("/register")) setScreen("register");
    else if (path.includes("/reset-password/")) {
      // Link from the password-reset email: /app/reset-password/{token}?email=...
      setResetInfo({ token: decodeURIComponent(path.split("/reset-password/")[1] || ""), email: new URLSearchParams(window.location.search).get("email") || "" });
      setScreen("reset");
    }
  }, []);

  const [appointments, setAppointments] = useState([]);
  const [services, setServices] = useState({});
  const [stylists, setStylists] = useState([]);
  const [inventory, setInventory] = useState([]);
  const [clients, setClients] = useState([]);
  const [reviews, setReviews] = useState([]);
  const [waitlist, setWaitlist] = useState([]);
  const [auditLog, setAuditLog] = useState([]);
  const [landingContent, setLandingContent] = useState(EMPTY_LANDING_CONTENT);

  const [apiConnected, setApiConnected] = useState(false);
  const [bootLoading, setBootLoading] = useState(true);
  const [dashLoading, setDashLoading] = useState(false);
  const [notice, setNotice] = useState(null);
  const notify = (message, type = "success") => { setNotice({ message, type }); setTimeout(() => setNotice(null), 4000); };
  const [confirm, setConfirm] = useState(null);
  const askConfirm = (message, onYes, confirmLabel) => setConfirm({ message, confirmLabel, onYes });

  useEffect(() => {
    (async () => {
      const [settingsRes, servicesRes, stylistsRes, reviewsRes] = await Promise.all([
        apiGet("/settings"), apiGet("/services"), apiGet("/stylists"), apiGet("/reviews"),
      ]);
      if (settingsRes) { setLandingContent(fromApiSettings(settingsRes) || EMPTY_LANDING_CONTENT); setApiConnected(true); }
      if (servicesRes) {
        const map = {};
        servicesRes.forEach((s) => { map[s.name] = { id: s.id, price: Number(s.price), duration: s.duration_minutes, photoUrl: s.photo_url || "" }; });
        setServices(map);
        setApiConnected(true);
      }
      if (stylistsRes) setStylists(stylistsRes.map(normalizeStylist));
      if (reviewsRes) setReviews(reviewsRes.map(normalizeReview));

      // Restore the previous session so a page refresh doesn't log people out.
      const saved = savedToken.get();
      if (saved) {
        const me = await apiGet("/auth/me", saved);
        if (me?.id) { setUser(me); setToken(saved); setScreen("app"); }
        else savedToken.clear();
      }
      setBootLoading(false);
    })();
  }, []);

  useEffect(() => {
    if (!token) return;
    (async () => {
      setDashLoading(true);
      // Only ask for what this role may see; the API answers 403 otherwise.
      const isManager = user?.role === "admin" || user?.role === "manager";
      const skip = Promise.resolve(null);
      const [a, c, i, al, w, rv] = await Promise.all([
        apiGet("/appointments", token),
        isManager || user?.role === "client" ? apiGet("/clients", token) : skip,
        isManager ? apiGet("/inventory", token) : skip,
        user?.role === "admin" ? apiGet("/audit-log", token) : skip,
        isManager ? apiGet("/waitlist", token) : skip,
        isManager ? apiGet("/reviews", token) : skip, // managers also see pending/rejected reviews
      ]);
      if (rv) setReviews(rv.map(normalizeReview));
      if (a) setAppointments(a.map(normalizeAppointment));
      if (c) setClients(c.map(normalizeClient));
      if (i) setInventory(i.map(normalizeInventoryItem));
      if (al) setAuditLog(al);
      if (w) setWaitlist(w.map(normalizeWaitlistEntry));
      setDashLoading(false);
    })();
  }, [token]);

  useEffect(() => {
    if (!GA_MEASUREMENT_ID || typeof document === "undefined") return;
    const script = document.createElement("script");
    script.src = `https://www.googletagmanager.com/gtag/js?id=${GA_MEASUREMENT_ID}`;
    script.async = true;
    document.head.appendChild(script);
  }, []);

  const handleLogin = async ({ email, password }) => {
    const remote = await apiSend("/auth/login", "POST", { email, password });
    if (remote && !remote.error && remote.user) { savedToken.set(remote.token); setUser(remote.user); setToken(remote.token); setScreen("app"); return; }
    notify(remote?.message || "Couldn't reach the server. Is the API running?", "error");
  };

  const handleRegister = async ({ name, email, password }) => {
    const remote = await apiSend("/auth/register", "POST", { name, email, password });
    if (remote && !remote.error && remote.user) { savedToken.set(remote.token); setUser(remote.user); setToken(remote.token); setScreen("app"); notify("Account created. Check your email to verify it."); return; }
    notify(remote?.message || "Couldn't reach the server. Is the API running?", "error");
  };

  const handleForgotPassword = async (email) => { await apiSend("/auth/forgot-password", "POST", { email }); notify("If an account exists for that email, we've sent a reset link."); };
  const handleLogout = () => { apiSend("/auth/logout", "POST", {}, token); savedToken.clear(); setUser(null); setToken(null); setScreen("login"); };
  const handleResetPassword = async ({ password, confirm }) => {
    const r = await apiSend("/auth/reset-password", "POST", { token: resetInfo.token, email: resetInfo.email, password, password_confirmation: confirm });
    if (r && !r.error) {
      window.history.replaceState(null, "", "/app/login");
      setResetInfo(null); setScreen("login");
      notify("Password updated. Log in with your new password.");
      return;
    }
    notify(r?.message || "Couldn't reach the server. Is the API running?", "error");
  };
  const resendVerification = async () => {
    const result = await apiSend("/auth/resend-verification", "POST", {}, token);
    if (result?.error) notify(result.message, "error"); else notify("Verification email sent (demo mode: nothing is actually delivered without a mail server).");
  };

  const saveLandingContent = async (next) => {
    setLandingContent(next);
    const result = await apiSend("/settings", "PATCH", toApiSettings(next), token);
    if (result?.error) notify(result.message, "error"); else notify("Website updated.");
  };

  const goToMarketingSite = () => { window.location.href = "/"; };

  let body;
  if (bootLoading) body = <BootLoader />;
  else if (screen === "login") body = <LoginPage onLogin={handleLogin} onGoRegister={() => setScreen("register")} onGoForgot={() => setScreen("forgot")} onBack={goToMarketingSite} />;
  else if (screen === "register") body = <RegisterPage onRegister={handleRegister} onGoLogin={() => setScreen("login")} onBack={goToMarketingSite} />;
  else if (screen === "reset" && resetInfo) body = <ResetPasswordPage email={resetInfo.email} onSubmit={handleResetPassword} onBack={() => { window.history.replaceState(null, "", "/app/login"); setScreen("login"); }} />;
  else if (screen === "forgot") body = <ForgotPasswordPage onSubmit={handleForgotPassword} onBack={() => setScreen("login")} />;
  else {
    body = (
      <Dashboard
        user={user} token={token} onLogout={handleLogout} apiConnected={apiConnected} dashLoading={dashLoading}
        appointments={appointments} setAppointments={setAppointments}
        services={services} setServices={setServices}
        stylists={stylists} setStylists={setStylists}
        inventory={inventory} setInventory={setInventory}
        clients={clients} setClients={setClients}
        reviews={reviews} setReviews={setReviews}
        waitlist={waitlist} setWaitlist={setWaitlist}
        auditLog={auditLog}
        landingContent={landingContent} saveLandingContent={saveLandingContent}
        notify={notify} notice={notice} askConfirm={askConfirm}
        resendVerification={resendVerification}
      />
    );
  }

  return (
    <ErrorBoundary>
      <ThemeContext.Provider value={{ colors, theme, toggleTheme }}>
       <BrandContext.Provider value={landingContent.salonName || DEFAULT_BRAND}>
        <div style={{ fontFamily: sansFont }}>
          <GlobalStyle />
          {screen !== "app" && notice && <div className="max-w-md mx-auto mt-4 px-4"><NotifyBanner notice={notice} /></div>}
          {body}
          <ConfirmModal confirm={confirm} onCancel={() => setConfirm(null)} onConfirm={() => { confirm?.onYes(); setConfirm(null); }} />
        </div>
       </BrandContext.Provider>
      </ThemeContext.Provider>
    </ErrorBoundary>
  );
}

function BootLoader() {
  const { colors } = useTheme();
  return <div style={{ background: colors.bgApp, color: colors.subtle }} className="min-h-screen flex items-center justify-center text-sm">Loading...</div>;
}

function ThemeToggle({ compact }) {
  const { theme, toggleTheme, colors } = useTheme();
  return (
    <button onClick={toggleTheme} aria-label={theme === "light" ? "Switch to dark mode" : "Switch to light mode"} title={theme === "light" ? "Switch to dark mode" : "Switch to light mode"} style={{ border: `1px solid ${colors.border}`, color: colors.subtle }} className={`icon-btn flex items-center justify-center rounded-md ${compact ? "w-8 h-8" : "px-3 py-2 gap-2 text-sm"}`}>
      {theme === "light" ? <Moon size={15} /> : <Sun size={15} />}
      {!compact && (theme === "light" ? "Dark mode" : "Light mode")}
    </button>
  );
}

/* ---------------------------- Login / Register / Forgot ---------------------------- */

function AuthShell({ children, onBack }) {
  const { colors } = useTheme();
  const brand = useBrand();
  return (
    <div style={{ background: colors.roseSoft }} className="min-h-screen flex items-center justify-center p-6">
      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}`, color: colors.ink }} className="w-full max-w-sm rounded-2xl shadow-lg p-8 sm:p-9">
        <div className="flex items-center justify-between mb-6">
          <button onClick={onBack} style={{ color: colors.subtle }} className="text-xs">← Back to site</button>
          <ThemeToggle compact />
        </div>
        <div style={{ fontFamily: serifFont, color: colors.wine }} className="text-2xl mb-6">{brand}</div>
        {children}
      </div>
    </div>
  );
}

function LoginPage({ onLogin, onGoRegister, onGoForgot, onBack }) {
  const { colors } = useTheme();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");

  const submit = (e) => {
    e.preventDefault();
    if (!email || !password) { setError("Enter an email and password."); return; }
    setError("");
    onLogin({ email, password });
  };

  return (
    <AuthShell onBack={onBack}>
      <div style={{ fontFamily: serifFont }} className="text-xl mb-1">Welcome back</div>
      <div style={{ color: colors.subtle }} className="text-xs mb-6">Log in to manage your appointments.</div>
      <form onSubmit={submit} className="flex flex-col gap-3">
        <LabeledInput label="Email" type="email" value={email} onChange={setEmail} placeholder="you@example.com" autoComplete="email" />
        <div>
          <LabeledInput label="Password" type="password" value={password} onChange={setPassword} placeholder="********" autoComplete="current-password" />
          <button type="button" onClick={onGoForgot} style={{ color: colors.wine }} className="text-xs mt-1">Forgot your password?</button>
        </div>
        {error && <div style={{ color: colors.danger }} className="text-xs">{error}</div>}
        <button type="submit" className="btn-wine px-4 py-2.5 rounded-md text-sm font-medium mt-2">Log in</button>
      </form>
      <div style={{ color: colors.subtle }} className="text-xs mt-5 text-center">
        New here? <button onClick={onGoRegister} style={{ color: colors.wine }} className="font-medium">Create an account</button>
      </div>
    </AuthShell>
  );
}

function RegisterPage({ onRegister, onGoLogin, onBack }) {
  const { colors } = useTheme();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [error, setError] = useState("");
  const passwordScore = (pw) => (pw.length >= 12 ? "Strong" : pw.length >= 8 ? "Okay" : pw.length > 0 ? "Weak" : "");

  const submit = (e) => {
    e.preventDefault();
    if (!name || !email || !password) { setError("Fill in every field."); return; }
    if (password.length < 8) { setError("Password must be at least 8 characters."); return; }
    if (password !== confirm) { setError("Passwords don't match."); return; }
    setError("");
    onRegister({ name, email, password });
  };

  return (
    <AuthShell onBack={onBack}>
      <div style={{ fontFamily: serifFont }} className="text-xl mb-1">Create your account</div>
      <div style={{ color: colors.subtle }} className="text-xs mb-6">Book appointments and track your visit history.</div>
      <form onSubmit={submit} className="flex flex-col gap-3">
        <LabeledInput label="Full name" value={name} onChange={setName} placeholder="Jane Avery" autoComplete="name" />
        <LabeledInput label="Email" type="email" value={email} onChange={setEmail} placeholder="you@example.com" autoComplete="email" />
        <div>
          <LabeledInput label="Password" type="password" value={password} onChange={setPassword} placeholder="At least 8 characters" autoComplete="new-password" />
          {password && <div style={{ color: colors.subtle }} className="text-xs mt-1">Strength: {passwordScore(password)}</div>}
        </div>
        <LabeledInput label="Confirm password" type="password" value={confirm} onChange={setConfirm} placeholder="********" autoComplete="new-password" />
        {error && <div style={{ color: colors.danger }} className="text-xs">{error}</div>}
        <button type="submit" className="btn-wine px-4 py-2.5 rounded-md text-sm font-medium mt-2">Create account</button>
      </form>
      <div style={{ color: colors.subtle }} className="text-xs mt-5 text-center">
        Already registered? <button onClick={onGoLogin} style={{ color: colors.wine }} className="font-medium">Log in</button>
      </div>
    </AuthShell>
  );
}

function ForgotPasswordPage({ onSubmit, onBack }) {
  const { colors } = useTheme();
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState(false);
  const submit = (e) => { e.preventDefault(); if (!email) return; onSubmit(email); setSent(true); };
  return (
    <AuthShell onBack={onBack}>
      <div style={{ fontFamily: serifFont }} className="text-xl mb-1">Reset your password</div>
      <div style={{ color: colors.subtle }} className="text-xs mb-6">We'll email you a link to set a new one.</div>
      {sent ? (
        <div style={{ background: colors.successSoft, color: colors.success }} className="text-sm px-3 py-2.5 rounded-md">If an account exists for that email, we've sent a reset link.</div>
      ) : (
        <form onSubmit={submit} className="flex flex-col gap-3">
          <LabeledInput label="Email" type="email" value={email} onChange={setEmail} placeholder="you@example.com" autoComplete="email" />
          <button type="submit" className="btn-wine px-4 py-2.5 rounded-md text-sm font-medium mt-2">Send reset link</button>
        </form>
      )}
      <button onClick={onBack} style={{ color: colors.wine }} className="text-xs mt-5 font-medium block mx-auto">Back to log in</button>
    </AuthShell>
  );
}

function ResetPasswordPage({ email, onSubmit, onBack }) {
  const { colors } = useTheme();
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [error, setError] = useState("");
  const submit = (e) => {
    e.preventDefault();
    if (password.length < 8) { setError("Password must be at least 8 characters."); return; }
    if (password !== confirm) { setError("Passwords don't match."); return; }
    setError("");
    onSubmit({ password, confirm });
  };
  return (
    <AuthShell onBack={onBack}>
      <div style={{ fontFamily: serifFont }} className="text-xl mb-1">Choose a new password</div>
      <div style={{ color: colors.subtle }} className="text-xs mb-6">{email ? `For ${email}` : "Enter a new password for your account."}</div>
      <form onSubmit={submit} className="flex flex-col gap-3">
        <LabeledInput label="New password" type="password" value={password} onChange={setPassword} placeholder="At least 8 characters" autoComplete="new-password" />
        <LabeledInput label="Confirm password" type="password" value={confirm} onChange={setConfirm} placeholder="********" autoComplete="new-password" />
        {error && <div style={{ color: colors.danger }} className="text-xs">{error}</div>}
        <button type="submit" className="btn-wine px-4 py-2.5 rounded-md text-sm font-medium mt-2">Update password</button>
      </form>
    </AuthShell>
  );
}

function LabeledInput({ label, value, onChange, type = "text", placeholder, autoComplete }) {
  const { colors } = useTheme();
  const id = `field-${label.replace(/\s+/g, "-").toLowerCase()}`;
  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={id} style={{ color: colors.subtle }} className="text-xs">{label}</label>
      <input id={id} type={type} value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} autoComplete={autoComplete} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-3 py-2.5 rounded-lg text-sm" />
    </div>
  );
}

/* -------------------------------- Dashboard ------------------------------- */

function Dashboard(props) {
  const {
    user, token, onLogout, apiConnected, dashLoading,
    appointments, setAppointments, services, setServices, stylists, setStylists,
    inventory, setInventory, clients, setClients, reviews, setReviews,
    waitlist, setWaitlist, auditLog,
    landingContent, saveLandingContent, notify, notice, askConfirm, resendVerification,
  } = props;
  const { colors } = useTheme();
  const role = user.role;
  const isManagerView = role === "admin" || role === "manager";
  const isAdmin = role === "admin";
  const isStylistView = role === "stylist";
  const isClientView = role === "client";
  const currentStylist = isStylistView ? (stylists.find((s) => s.id === user.stylistId) || stylists.find((s) => s.name === user.name) || stylists[0]) : null;

  const [activeTab, setActiveTab] = useState("overview");
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [stylistFilter, setStylistFilter] = useState("All");
  const [statusFilter, setStatusFilter] = useState("all");
  const [clientSearch, setClientSearch] = useState("");
  const [selectedDate, setSelectedDate] = useState(todayStr(0));
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [formError, setFormError] = useState("");
  const emptyForm = { client: isClientView ? user.name : "", service: Object.keys(services)[0], stylist: "No preference", date: todayStr(0), time: "12:00" };
  const [form, setForm] = useState(emptyForm);
  const [reviewDraft, setReviewDraft] = useState({});

  const [reportRange, setReportRange] = useState("today");
  const [newStylist, setNewStylist] = useState({ name: "", role: "", photoUrl: "", bio: "" });
  const [newService, setNewService] = useState({ name: "", price: "", duration: "" });
  const [editingStylistId, setEditingStylistId] = useState(null);
  const [stylistEditDraft, setStylistEditDraft] = useState({});
  const [newItem, setNewItem] = useState({ name: "", category: "", qty: "", reorder: "", unitCost: "" });
  const [savedMsg, setSavedMsg] = useState("");
  const [siteDraft, setSiteDraft] = useState(landingContent);
  const [newFaq, setNewFaq] = useState({ question: "", answer: "" });

  const brand = useBrand();
  useDocumentMeta(`${isClientView ? "My account" : "Dashboard"} — ${brand}`, "Salon management dashboard.");

  const serviceInfo = (name) => services[name] || { price: 0, duration: 45 };
  const priceOf = (service) => serviceInfo(service).price;
  const durationOf = (service) => serviceInfo(service).duration;
  const visibleNav = NAV_ITEMS.filter((n) => n.roles.includes(role));

  const isSlotConflict = (stylistName, date, time, service, excludeId) =>
    appointments.some((a) => a.id !== excludeId && a.stylist === stylistName && a.date === date && a.status !== "cancelled" && rangesOverlap(a.time, durationOf(a.service), time, durationOf(service)));

  const isWithinHours = (time, service) => {
    const start = timeToMinutes(time);
    const end = start + durationOf(service);
    return start >= timeToMinutes(landingContent.openTime) && end <= timeToMinutes(landingContent.closeTime);
  };

  const advanceStatus = async (id, next) => {
    setAppointments((prev) => prev.map((a) => (a.id === id ? { ...a, status: next } : a)));
    const result = await apiSend(`/appointments/${id}`, "PATCH", { status: next }, token);
    if (result?.error) notify(result.message, "error");
  };

  const cancelAppointment = (a) => {
    askConfirm(`Cancel ${a.client}'s ${a.service} on ${a.date} at ${a.time}?`, () => advanceStatus(a.id, "cancelled"), "Cancel appointment");
  };

  const leastBookedActiveStylist = (date) => {
    const active = stylists.filter((s) => s.active);
    if (active.length === 0) return stylists[0]?.name;
    const counts = active.map((s) => ({ name: s.name, count: appointments.filter((a) => a.stylist === s.name && a.date === date && a.status !== "cancelled").length }));
    return counts.sort((a, b) => a.count - b.count)[0].name;
  };

  const resetForm = () => { setForm(emptyForm); setEditingId(null); setFormError(""); setShowForm(false); };

  const joinWaitlist = () => {
    const entry = { id: Date.now(), clientName: form.client, service: form.service, stylist: form.stylist === "No preference" ? null : form.stylist, preferredDate: form.date };
    setWaitlist((prev) => [...prev, entry]);
    apiSend("/waitlist", "POST", { client_name: entry.clientName, service_id: services[entry.service]?.id, preferred_date: entry.preferredDate }, token).then((r) => { if (r?.error) notify(r.message, "error"); });
    notify("Added to the waitlist. We'll reach out if a slot opens.");
    resetForm();
  };

  const submitAppointment = async () => {
    if (!form.client.trim()) { setFormError("Enter a client name."); return; }
    if (!isWithinHours(form.time, form.service)) { setFormError(`That's outside our hours (${landingContent.openTime}–${landingContent.closeTime}).`); return; }
    const stylistName = form.stylist === "No preference" ? leastBookedActiveStylist(form.date) : form.stylist;

    if (isSlotConflict(stylistName, form.date, form.time, form.service, editingId)) {
      setFormError("That stylist is already booked over this time. Pick another slot, or join the waitlist.");
      return;
    }
    setFormError("");

    if (editingId) {
      setAppointments((prev) => prev.map((a) => (a.id === editingId ? { ...a, date: form.date, time: form.time, service: form.service, stylist: stylistName } : a)));
      const result = await apiSend(`/appointments/${editingId}`, "PATCH", { scheduled_at: `${form.date} ${form.time}:00`, stylist_id: stylists.find((s) => s.name === stylistName)?.id, service_id: services[form.service]?.id }, token);
      if (result?.error) { notify(result.message, "error"); return; }
      notify("Appointment rescheduled.");
    } else {
      const tempId = `temp-${Date.now()}`;
      const newAppt = { id: tempId, date: form.date, time: form.time, client: form.client, service: form.service, stylist: stylistName, status: "confirmed" };
      setAppointments((prev) => [...prev, newAppt].sort((a, b) => (a.date + a.time).localeCompare(b.date + b.time)));
      const result = await apiSend("/appointments", "POST", { client_name: form.client, stylist_id: stylists.find((s) => s.name === stylistName)?.id, service_id: services[form.service]?.id, scheduled_at: `${form.date} ${form.time}:00` }, token);
      if (result?.error) {
        // Roll the optimistic row back out - the server rejected it, so
        // leaving it on screen would show an appointment that isn't saved.
        setAppointments((prev) => prev.filter((a) => a.id !== tempId));
        notify(result.message, "error");
        return;
      }
      // Swap in the real database id so later status changes / reschedules
      // target a row that actually exists server-side.
      if (result?.id) setAppointments((prev) => prev.map((a) => (a.id === tempId ? { ...newAppt, id: result.id } : a)));
      notify(isClientView ? "Appointment booked." : "Appointment added.");
    }
    setActiveTab("appointments");
    resetForm();
  };

  const startReschedule = (a) => { setEditingId(a.id); setForm({ client: a.client, service: a.service, stylist: a.stylist, date: a.date, time: a.time }); setFormError(""); setShowForm(true); };

  const toggleStylistActive = (id) => {
    const target = stylists.find((s) => s.id === id);
    setStylists((prev) => prev.map((s) => (s.id === id ? { ...s, active: !s.active } : s)));
    apiSend(`/stylists/${id}`, "PATCH", { active: !target?.active }, token).then((r) => { if (r?.error) notify(r.message, "error"); });
  };

  const startEditStylist = (s) => { setEditingStylistId(s.id); setStylistEditDraft({ name: s.name, role: s.role, photoUrl: s.photoUrl || "", bio: s.bio || "" }); };
  const saveStylistEdit = (id) => {
    setStylists((prev) => prev.map((s) => (s.id === id ? { ...s, ...stylistEditDraft } : s)));
    apiSend(`/stylists/${id}`, "PATCH", { name: stylistEditDraft.name, role: stylistEditDraft.role, photo_url: stylistEditDraft.photoUrl || null, bio: stylistEditDraft.bio || null }, token).then((r) => { if (r?.error) notify(r.message, "error"); });
    setEditingStylistId(null);
    notify("Stylist updated.");
  };

  const addStylist = async () => {
    if (!newStylist.name.trim()) return;
    const tempId = `temp-${Date.now()}`;
    const rec = { id: tempId, name: newStylist.name, role: newStylist.role || "Stylist", active: true, photoUrl: newStylist.photoUrl, bio: newStylist.bio };
    setStylists((prev) => [...prev, rec]);
    setNewStylist({ name: "", role: "", photoUrl: "", bio: "" });
    const result = await apiSend("/stylists", "POST", { name: rec.name, role: rec.role, photo_url: rec.photoUrl || null, bio: rec.bio || null }, token);
    if (result?.error) {
      setStylists((prev) => prev.filter((s) => s.id !== tempId));
      notify(result.message, "error");
      return;
    }
    if (result?.id) setStylists((prev) => prev.map((s) => (s.id === tempId ? { ...rec, id: result.id } : s)));
    notify("Stylist added.");
  };

  const adjustInventory = (id, delta) => {
    const target = inventory.find((i) => i.id === id);
    setInventory((prev) => prev.map((i) => (i.id === id ? { ...i, qty: Math.max(0, i.qty + delta) } : i)));
    if (target) apiSend(`/inventory/${id}`, "PATCH", { qty: Math.max(0, target.qty + delta) }, token).then((r) => { if (r?.error) notify(r.message, "error"); });
  };

  const addInventoryItem = async () => {
    if (!newItem.name.trim()) return;
    const tempId = `temp-${Date.now()}`;
    const rec = { id: tempId, name: newItem.name, category: newItem.category || "General", qty: Number(newItem.qty) || 0, reorder: Number(newItem.reorder) || 5, unitCost: Number(newItem.unitCost) || 0 };
    setInventory((prev) => [...prev, rec]);
    setNewItem({ name: "", category: "", qty: "", reorder: "", unitCost: "" });
    // The API uses snake_case column names (reorder_level, unit_cost) while
    // this component uses camelCase - translate rather than sending `rec` raw.
    const result = await apiSend("/inventory", "POST", {
      name: rec.name, category: rec.category, qty: rec.qty,
      reorder_level: rec.reorder, unit_cost: rec.unitCost,
    }, token);
    if (result?.error) { notify(result.message, "error"); return; }
    if (result?.id) setInventory((prev) => prev.map((i) => (i.id === tempId ? { ...rec, id: result.id } : i)));
    notify("Inventory item added.");
  };

  const updateServicePrice = (name, price) => {
    setServices((prev) => ({ ...prev, [name]: { ...prev[name], price: Number(price) || 0 } }));
    const id = services[name]?.id;
    if (id) apiSend(`/services/${id}`, "PATCH", { price: Number(price) || 0 }, token).then((r) => { if (r?.error) notify(r.message, "error"); });
  };

  const updateServiceDuration = (name, duration) => {
    setServices((prev) => ({ ...prev, [name]: { ...prev[name], duration: Number(duration) || 5 } }));
    const id = services[name]?.id;
    if (id) apiSend(`/services/${id}`, "PATCH", { duration_minutes: Number(duration) || 5 }, token).then((r) => { if (r?.error) notify(r.message, "error"); });
  };

  const updateServicePhoto = (name, photoUrl) => {
    setServices((prev) => ({ ...prev, [name]: { ...prev[name], photoUrl } }));
    const id = services[name]?.id;
    if (id) apiSend(`/services/${id}`, "PATCH", { photo_url: photoUrl || null }, token).then((r) => { if (r?.error) notify(r.message, "error"); });
  };

  const addService = async () => {
    if (!newService.name.trim()) { notify("Enter a service name.", "error"); return; }
    if (services[newService.name]) { notify("A service with that name already exists.", "error"); return; }
    const name = newService.name;
    const price = Number(newService.price) || 0;
    const duration = Number(newService.duration) || 45;
    const result = await apiSend("/services", "POST", { name, price, duration_minutes: duration }, token);
    if (result?.error) { notify(result.message, "error"); return; }
    setServices((prev) => ({ ...prev, [name]: { id: result?.id, price, duration, photoUrl: "" } }));
    setNewService({ name: "", price: "", duration: "" });
    notify("Service added.");
  };

  const deleteService = (name) => {
    const id = services[name]?.id;
    askConfirm(`Delete "${name}"? This can't be undone, and only works if it has no appointments booked against it.`, async () => {
      const result = id ? await apiSend(`/services/${id}`, "DELETE", {}, token) : null;
      if (result?.error) { notify(result.message, "error"); return; }
      setServices((prev) => { const next = { ...prev }; delete next[name]; return next; });
      setSiteDraft((prev) => ({ ...prev, featuredServiceNames: prev.featuredServiceNames.filter((n) => n !== name) }));
      notify("Service deleted.");
    }, "Delete service");
  };

  const saveSalonInfo = async () => {
    await saveLandingContent(siteDraft);
    setSavedMsg("Saved");
    setTimeout(() => setSavedMsg(""), 1800);
  };
  const saveSite = () => saveLandingContent(siteDraft);
  const toggleFeatured = (name) => setSiteDraft((prev) => ({ ...prev, featuredServiceNames: prev.featuredServiceNames.includes(name) ? prev.featuredServiceNames.filter((n) => n !== name) : [...prev.featuredServiceNames, name] }));
  const addFaq = () => { if (!newFaq.question.trim() || !newFaq.answer.trim()) return; setSiteDraft((prev) => ({ ...prev, faqs: [...prev.faqs, { id: Date.now(), ...newFaq }] })); setNewFaq({ question: "", answer: "" }); };
  const removeFaq = (id) => setSiteDraft((prev) => ({ ...prev, faqs: prev.faqs.filter((f) => f.id !== id) }));

  const submitReview = async (appointmentId) => {
    const draft = reviewDraft[appointmentId];
    if (!draft?.rating) { notify("Pick a star rating first.", "error"); return; }
    const appt = appointments.find((a) => a.id === appointmentId);
    const tempId = `temp-${Date.now()}`;
    const rec = { id: tempId, appointmentId, clientName: user.name, stylistName: appt.stylist, rating: draft.rating, comment: draft.comment || "", status: "pending" };
    setReviews((prev) => [...prev, rec]);
    const result = await apiSend("/reviews", "POST", { appointment_id: appointmentId, rating: draft.rating, comment: draft.comment }, token);
    if (result?.error) { notify(result.message, "error"); }
    else {
      notify("Thanks! Your review is pending approval.");
      // Swap the temporary local record for the real one so its id
      // actually matches the database row - otherwise an admin approving
      // it later would be updating an id that doesn't exist server-side.
      if (result?.id) setReviews((prev) => prev.map((r) => (r.id === tempId ? { ...rec, id: result.id } : r)));
    }
    setReviewDraft((prev) => ({ ...prev, [appointmentId]: { submitted: true } }));
  };

  const moderateReview = (id, status) => {
    setReviews((prev) => prev.map((r) => (r.id === id ? { ...r, status } : r)));
    apiSend(`/reviews/${id}`, "PATCH", { status }, token).then((r) => { if (r?.error) notify(r.message, "error"); });
  };

  const removeWaitlistEntry = async (id) => {
    const removed = waitlist.find((w) => w.id === id);
    setWaitlist((prev) => prev.filter((w) => w.id !== id));
    const ok = await apiDelete(`/waitlist/${id}`, token);
    if (!ok && removed) {
      setWaitlist((prev) => [...prev, removed]);
      notify("Couldn't remove that waitlist entry - try again.", "error");
    }
  };

  const scopedAppointments = isStylistView
    ? appointments.filter((a) => a.stylist === currentStylist?.name)
    : isClientView
    ? appointments.filter((a) => a.client === user.name)
    : appointments;

  const dayAppointments = isManagerView || isStylistView ? scopedAppointments.filter((a) => a.date === selectedDate) : scopedAppointments;

  // Overview's "today"/"upcoming" preview panels need their own date-scoped
  // slice - scopedAppointments alone is sorted but not filtered, so without
  // this, "Today's schedule" would show the earliest appointments overall
  // (likely from the past) rather than actually today's.
  const overviewPreview = isClientView
    ? scopedAppointments.filter((a) => a.date >= todayStr(0))
    : scopedAppointments.filter((a) => a.date === todayStr(0));

  const todaysRevenue = useMemo(() => scopedAppointments.filter((a) => a.date === todayStr(0) && a.status === "completed").reduce((sum, a) => sum + priceOf(a.service), 0), [scopedAppointments, services]);
  const yesterdaysRevenue = useMemo(() => scopedAppointments.filter((a) => a.date === todayStr(-1) && a.status === "completed").reduce((sum, a) => sum + priceOf(a.service), 0), [scopedAppointments, services]);
  const revenueTrend = yesterdaysRevenue > 0 ? Math.round(((todaysRevenue - yesterdaysRevenue) / yesterdaysRevenue) * 100) : null;

  const activeCount = dayAppointments.filter((a) => a.status !== "cancelled").length;
  const yesterdayCount = scopedAppointments.filter((a) => a.date === todayStr(-1) && a.status !== "cancelled").length;
  const countTrend = yesterdayCount > 0 ? Math.round(((activeCount - yesterdayCount) / yesterdayCount) * 100) : null;

  const newClientsToday = clients.filter((c) => c.lastVisit === "New client").length;
  const occupancyRate = Math.round((activeCount / (isStylistView ? 6 : isClientView ? 3 : 12)) * 100);

  const weeklyRevenue = useMemo(() => computeWeeklyRevenue(appointments, services), [appointments, services]);

  const statusBreakdown = useMemo(() => {
    const counts = { confirmed: 0, "in-progress": 0, completed: 0, cancelled: 0 };
    dayAppointments.forEach((a) => { counts[a.status] = (counts[a.status] || 0) + 1; });
    return Object.entries(counts).filter(([, v]) => v > 0).map(([status, value]) => ({ name: STATUS_META[status].label, value, status }));
  }, [dayAppointments]);

  const leaderboard = useMemo(() => {
    const totals = {};
    stylists.forEach((s) => (totals[s.name] = 0));
    appointments.filter((a) => a.status === "completed" || a.status === "in-progress").forEach((a) => { totals[a.stylist] = (totals[a.stylist] || 0) + priceOf(a.service); });
    const max = Math.max(1, ...Object.values(totals));
    return stylists.map((s) => ({ ...s, total: totals[s.name] || 0, pct: (totals[s.name] || 0) / max })).sort((a, b) => b.total - a.total);
  }, [appointments, stylists, services]);

  const reportRangeStart = useMemo(() => {
    if (reportRange === "today") return todayStr(0);
    if (reportRange === "week") return todayStr(-6);
    return todayStr(-29); // month: last 30 days including today
  }, [reportRange]);
  const reportAppointments = useMemo(() => appointments.filter((a) => a.date >= reportRangeStart && a.date <= todayStr(0)), [appointments, reportRangeStart]);

  const revenueByService = useMemo(() => {
    const totals = {};
    Object.keys(services).forEach((s) => (totals[s] = 0));
    reportAppointments.filter((a) => a.status === "completed").forEach((a) => { totals[a.service] = (totals[a.service] || 0) + priceOf(a.service); });
    return Object.entries(totals).map(([service, revenue]) => ({ service, revenue: Math.round(revenue) }));
  }, [reportAppointments, services]);

  const filteredAppointments = dayAppointments.filter((a) => {
    const stylistOk = isStylistView || isClientView || stylistFilter === "All" || a.stylist === stylistFilter;
    const statusOk = statusFilter === "all" || a.status === statusFilter;
    return stylistOk && statusOk;
  }).sort((a, b) => (a.date + a.time).localeCompare(b.date + b.time));

  const filteredClients = clients.filter((c) => c.name.toLowerCase().includes(clientSearch.toLowerCase()));
  const myClientRecord = isClientView ? clients.find((c) => c.name === user.name) : null;
  const reviewableAppointments = isClientView ? scopedAppointments.filter((a) => a.status === "completed" && !reviews.some((r) => r.appointmentId === a.id)) : [];

  const totalReportRevenue = Math.round(reportAppointments.filter((a) => a.status === "completed").reduce((s, a) => s + priceOf(a.service), 0));
  const completedInRange = reportAppointments.filter((a) => a.status === "completed").length;
  const cancelledInRange = reportAppointments.filter((a) => a.status === "cancelled").length;
  const cancellationRate = reportAppointments.length > 0 ? Math.round((cancelledInRange / reportAppointments.length) * 100) : 0;
  const avgTicket = Math.round(totalReportRevenue / Math.max(1, completedInRange));

  const pageTitle = isClientView ? `Hi, ${user.name.split(" ")[0]}` : isStylistView ? `Welcome back, ${currentStylist?.name.split(" ")[0]}` : NAV_ITEMS.find((n) => n.key === activeTab)?.label;

  return (
    <div style={{ background: colors.bgApp, color: colors.ink }} className="min-h-screen flex">
      {sidebarOpen && <div onClick={() => setSidebarOpen(false)} className="fixed inset-0 bg-black/30 z-30 md:hidden" />}

      <aside style={{ background: colors.cardBg, borderRight: `1px solid ${colors.border}` }} className={`fixed md:static inset-y-0 left-0 z-40 w-60 flex-shrink-0 flex flex-col py-6 px-4 transform transition-transform md:translate-x-0 ${sidebarOpen ? "translate-x-0" : "-translate-x-full"}`}>
        <div className="px-2 mb-8 flex items-start justify-between">
          <div className="flex items-center gap-3">
            <div style={{ background: colors.wine, color: colors.cardBg, fontFamily: serifFont }} className="w-9 h-9 rounded-lg flex items-center justify-center text-lg font-semibold flex-shrink-0">{(brand.trim()[0] || "V").toUpperCase()}</div>
            <div>
              <div style={{ fontFamily: serifFont, color: colors.wine }} className="text-xl leading-tight">{brand}</div>
              <div style={{ color: colors.subtle }} className="text-[11px]">{isClientView ? "My account" : "Salon administration"}</div>
            </div>
          </div>
          <button onClick={() => setSidebarOpen(false)} aria-label="Close menu" className="md:hidden" style={{ color: colors.subtle }}><X size={18} /></button>
        </div>

        <nav className="flex flex-col gap-1 overflow-y-auto">
          {visibleNav.map((item, i) => {
            const Icon = item.icon;
            const active = activeTab === item.key;
            const showGroupLabel = item.group && item.group !== visibleNav[i - 1]?.group;
            return (
              <React.Fragment key={item.key}>
                {showGroupLabel && (
                  <div style={{ color: colors.subtle }} className="text-[10px] font-semibold uppercase tracking-wide px-3 pt-3 pb-1 first:pt-0">
                    {item.group}
                  </div>
                )}
                <button onClick={() => { setActiveTab(item.key); setSidebarOpen(false); }} className={`nav-link flex items-center gap-3 px-3 py-2.5 text-sm rounded-lg transition-colors `} style={{ background: active ? colors.wineSoft : "transparent", color: active ? colors.wine : colors.subtle, fontWeight: active ? 600 : 500 }}>
                  <Icon size={16} />
                  {item.label}
                </button>
              </React.Fragment>
            );
          })}
        </nav>

        <div className="mt-auto px-2 flex flex-col gap-3">
          <div className="flex items-center justify-between">
            <span style={{ color: apiConnected ? colors.success : colors.subtle }} className="text-[11px] font-medium flex items-center gap-1">
              <span style={{ background: apiConnected ? colors.success : colors.subtle, width: 6, height: 6, borderRadius: "50%", display: "inline-block" }} />
              {apiConnected ? "Connected" : "Connection issue"}
            </span>
            <ThemeToggle compact />
          </div>
          <div style={{ borderTop: `1px solid ${colors.border}` }} className="pt-3 flex items-center justify-between">
            <div className="flex items-center gap-2 min-w-0">
              <div style={{ background: colors.roseSoft, color: colors.wine }} className="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold flex-shrink-0">{initials(user.name)}</div>
              <div className="text-xs min-w-0">
                <div className="font-medium truncate">{user.name}</div>
                <div style={{ color: colors.subtle }} className="capitalize">{role}</div>
              </div>
            </div>
            <button onClick={onLogout} aria-label="Log out" title="Log out" style={{ color: colors.subtle }}><LogOut size={15} /></button>
          </div>
        </div>
      </aside>

      <main className="flex-1 min-w-0" style={{ background: colors.pageBg }}>
        <div style={{ borderBottom: `1px solid ${colors.border}`, background: colors.cardBg }} className="flex items-center justify-between px-4 md:px-8 py-4 gap-3">
          <div className="flex items-center gap-3 min-w-0">
            <button onClick={() => setSidebarOpen(true)} aria-label="Open menu" className="md:hidden" style={{ color: colors.subtle }}><Menu size={20} /></button>
            <div className="min-w-0">
              <div style={{ fontFamily: serifFont, color: colors.ink }} className="text-xl md:text-2xl leading-tight truncate">{pageTitle}</div>
              <div style={{ color: colors.subtle }} className="text-xs hidden sm:block">{isStylistView ? currentStylist?.role : isClientView ? "Your appointments and visit history" : formatDateLabel(selectedDate)}</div>
            </div>
          </div>
          <div className="flex items-center gap-2 md:gap-4">
            <button onClick={() => { setActiveTab("appointments"); resetForm(); setShowForm(true); }} className="btn-wine flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium whitespace-nowrap">
              <Plus size={14} /> <span className="hidden sm:inline">{isClientView ? "Book appointment" : "New appointment"}</span><span className="sm:hidden">Book</span>
            </button>
          </div>
        </div>

        <div className="p-4 md:p-8">
          <NotifyBanner notice={notice} />
          {isClientView && user.emailVerified === false && (
            <div style={{ background: colors.warnSoft, color: colors.warn }} className="text-sm px-4 py-2.5 rounded-md mb-4 flex items-center justify-between gap-3 flex-wrap">
              <span>Verify your email to keep your account secure.</span>
              <button onClick={resendVerification} className="text-xs font-medium underline">Resend verification email</button>
            </div>
          )}
          {dashLoading && <div style={{ color: colors.subtle }} className="text-sm mb-4">Refreshing your data...</div>}

          {activeTab === "overview" && (
            <>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <StatCard label={isClientView ? "Total spent" : isStylistView ? "My revenue today" : "Today's revenue"} value={isClientView ? `$${(myClientRecord?.spend || 0).toLocaleString()}` : `$${todaysRevenue.toLocaleString()}`} sub={isClientView ? "lifetime with us" : "vs. yesterday"} trend={isClientView ? null : revenueTrend} />
                <StatCard label={isClientView ? "Visits" : isStylistView ? "My appointments" : "Appointments today"} value={isClientView ? (myClientRecord?.visits || 0) : activeCount} sub={isClientView ? "all time" : "vs. yesterday"} trend={isClientView ? null : countTrend} />
                {isClientView && <StatCard label="Upcoming" value={scopedAppointments.filter((a) => a.status === "confirmed").length} sub="confirmed bookings" />}
                {!isClientView && !isStylistView && <StatCard label="New clients" value={newClientsToday} sub="first visit today" />}
                {isStylistView && <StatCard label="Chair status" value={currentStylist?.active ? "Active" : "Off today"} sub="set by front desk" />}
                <StatCard label={isClientView ? "Favorite service" : isStylistView ? "My occupancy" : "Chair occupancy"} value={isClientView ? (scopedAppointments[0]?.service || "-") : `${occupancyRate}%`} sub={isClientView ? "most recent booking" : "of available slots"} />
              </div>

              {isClientView && reviewableAppointments.length > 0 && (
                <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card mb-6">
                  <div style={{ fontFamily: serifFont }} className="text-lg mb-3">How was your visit?</div>
                  {reviewableAppointments.map((a) => {
                    const draft = reviewDraft[a.id] || {};
                    if (draft.submitted) return <div key={a.id} style={{ color: colors.success }} className="text-sm">Thanks for reviewing your {a.service}.</div>;
                    return (
                      <div key={a.id} className="flex flex-col gap-2 mb-3">
                        <div className="text-sm">{a.service} with {a.stylist}</div>
                        <div className="flex gap-1">
                          {[1, 2, 3, 4, 5].map((n) => (
                            <button key={n} onClick={() => setReviewDraft((prev) => ({ ...prev, [a.id]: { ...draft, rating: n } }))} aria-label={`${n} stars`}>
                              <Star size={18} style={{ color: n <= (draft.rating || 0) ? colors.wine : colors.border }} fill={n <= (draft.rating || 0) ? colors.wine : "none"} />
                            </button>
                          ))}
                        </div>
                        <input value={draft.comment || ""} onChange={(e) => setReviewDraft((prev) => ({ ...prev, [a.id]: { ...draft, comment: e.target.value } }))} placeholder="Optional comment" style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm" />
                        <button onClick={() => submitReview(a.id)} className="btn-wine px-3 py-1.5 rounded-md text-xs font-medium self-start">Submit review</button>
                      </div>
                    );
                  })}
                </div>
              )}

              <div className="grid md:grid-cols-3 gap-4 mb-6">
                <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="md:col-span-2 rounded-xl p-5 dash-card">
                  <div className="flex items-center justify-between mb-4">
                    <div style={{ fontFamily: serifFont }} className="text-lg">{isClientView ? "My upcoming visits" : isStylistView ? "My schedule" : "Today's schedule"}</div>
                    <button onClick={() => setActiveTab("appointments")} style={{ color: colors.wine }} className="text-xs font-medium">View all</button>
                  </div>
                  <div className="flex flex-col gap-3">
                    {overviewPreview.slice(0, 5).map((a) => (
                      <div key={a.id} className="flex items-center justify-between text-sm gap-2">
                        <div className="flex items-center gap-3 min-w-0">
                          <span style={{ color: colors.subtle, fontFamily: serifFont }} className="w-12 flex-shrink-0">{a.time}</span>
                          <span className="font-medium truncate">{isClientView ? a.service : a.client}</span>
                          <span style={{ color: colors.subtle }} className="truncate hidden sm:inline">· {isClientView ? a.stylist : a.service}</span>
                        </div>
                        <span style={{ color: statusColor(a.status, colors) }} className="text-xs font-medium flex-shrink-0">{STATUS_META[a.status].label}</span>
                      </div>
                    ))}
                    {overviewPreview.length === 0 && <div style={{ color: colors.subtle }} className="text-sm">Nothing on the books yet.</div>}
                  </div>
                </div>

                {isManagerView && (
                  <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                    <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Stylist leaderboard</div>
                    <div className="flex flex-col gap-4">
                      {leaderboard.map((s, i) => (
                        <div key={s.name}>
                          <div className="flex items-center justify-between text-sm mb-1">
                            <span className="font-medium" style={{ color: i === 0 ? colors.wine : colors.ink }}>{s.name}{i === 0 && <span style={{ color: colors.subtle }} className="font-normal text-xs"> · top today</span>}</span>
                            <span style={{ color: colors.subtle }}>${s.total}</span>
                          </div>
                          <div style={{ background: colors.roseSoft }} className="h-1.5 rounded-full overflow-hidden">
                            <div style={{ width: `${s.pct * 100}%`, background: colors.rose }} className="h-full" />
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
                {isStylistView && (
                  <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                    <div style={{ fontFamily: serifFont }} className="text-lg mb-4">My upcoming</div>
                    <div className="flex flex-col gap-3">
                      {overviewPreview.filter((a) => a.status === "confirmed").slice(0, 4).map((a) => (
                        <div key={a.id} className="text-sm flex items-center justify-between"><span>{a.date} {a.time} · {a.client}</span><span style={{ color: colors.subtle }}>{a.service}</span></div>
                      ))}
                      {overviewPreview.filter((a) => a.status === "confirmed").length === 0 && <div style={{ color: colors.subtle }} className="text-sm">All caught up.</div>}
                    </div>
                  </div>
                )}
                {isClientView && (
                  <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                    <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Book something new</div>
                    <div style={{ color: colors.subtle }} className="text-sm mb-4">Pick a service and we'll match you with the next available stylist.</div>
                    <button onClick={() => { setActiveTab("appointments"); resetForm(); setShowForm(true); }} className="btn-wine w-full px-4 py-2.5 rounded-md text-sm font-medium">Book appointment</button>
                  </div>
                )}
              </div>

              {!isClientView && (
                <div className="grid md:grid-cols-3 gap-4">
                  <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="md:col-span-2 rounded-xl p-5 dash-card">
                    <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Weekly revenue</div>
                    <Suspense fallback={<ChartPlaceholder height={220} />}><WeeklyRevenueChart data={weeklyRevenue} colors={colors} /></Suspense>
                  </div>
                  <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                    <div style={{ fontFamily: serifFont }} className="text-lg mb-4">{isStylistView ? "My day" : "Today's status mix"}</div>
                    {statusBreakdown.length > 0 ? (
                      <Suspense fallback={<ChartPlaceholder height={220} />}><StatusPieChart data={statusBreakdown} colors={colors} statusColor={statusColor} /></Suspense>
                    ) : (
                      <div style={{ color: colors.subtle }} className="text-sm h-[220px] flex items-center justify-center text-center">Nothing scheduled for this day yet.</div>
                    )}
                  </div>
                </div>
              )}
            </>
          )}

          {activeTab === "appointments" && (
            <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl dash-card">
              {(isManagerView || isStylistView) && (
                <div style={{ borderBottom: `1px solid ${colors.border}` }} className="flex items-center gap-2 p-4 flex-wrap">
                  <button onClick={() => setSelectedDate((d) => { const dt = new Date(d + "T00:00:00"); dt.setDate(dt.getDate() - 1); return dt.toISOString().slice(0, 10); })} aria-label="Previous day" style={{ border: `1px solid ${colors.border}` }} className="icon-btn w-8 h-8 rounded-md flex items-center justify-center"><ChevronLeft size={14} style={{ color: colors.subtle }} /></button>
                  <input type="date" aria-label="Appointment date" value={selectedDate} onChange={(e) => setSelectedDate(e.target.value)} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="text-sm px-2 py-1.5 rounded-md" />
                  <button onClick={() => setSelectedDate((d) => { const dt = new Date(d + "T00:00:00"); dt.setDate(dt.getDate() + 1); return dt.toISOString().slice(0, 10); })} aria-label="Next day" style={{ border: `1px solid ${colors.border}` }} className="icon-btn w-8 h-8 rounded-md flex items-center justify-center"><ChevronRight size={14} style={{ color: colors.subtle }} /></button>
                  <button onClick={() => setSelectedDate(todayStr(0))} style={{ color: colors.wine }} className="text-xs font-medium px-2">Today</button>
                </div>
              )}
              <div style={{ borderBottom: `1px solid ${colors.border}` }} className="flex items-center justify-between p-5 flex-wrap gap-3">
                <div className="flex items-center gap-2 flex-wrap">
                  {isManagerView && (
                    <select aria-label="Filter by stylist" value={stylistFilter} onChange={(e) => setStylistFilter(e.target.value)} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="text-sm px-3 py-2 rounded-lg">
                      <option>All</option>
                      {stylists.map((s) => <option key={s.id}>{s.name}</option>)}
                    </select>
                  )}
                  {["all", "confirmed", "in-progress", "completed", "cancelled"].map((s) => (
                    <button key={s} onClick={() => setStatusFilter(s)} style={{ border: `1px solid ${statusFilter === s ? colors.wine : colors.border}`, color: statusFilter === s ? colors.wine : colors.subtle, background: statusFilter === s ? colors.wineSoft : "transparent" }} className="text-xs px-2.5 py-1.5 rounded-md capitalize">
                      {s === "all" ? "All statuses" : STATUS_META[s].label}
                    </button>
                  ))}
                </div>
                <button onClick={() => { resetForm(); setShowForm(true); }} className="btn-wine flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium">
                  <Plus size={14} /> {isClientView ? "Book" : "Add"}
                </button>
              </div>

              {showForm && (
                <div style={{ borderBottom: `1px solid ${colors.border}`, background: colors.roseSoft }} className="p-5 flex flex-col gap-3">
                  <div className="flex items-end gap-3 flex-wrap">
                    {!isClientView && (
                      <Field label="Client name">
                        <input value={form.client} onChange={(e) => setForm({ ...form, client: e.target.value })} disabled={!!editingId} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-40" placeholder="Jane Avery" />
                      </Field>
                    )}
                    <Field label="Service">
                      <select value={form.service} onChange={(e) => setForm({ ...form, service: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-3 py-2 rounded-lg text-sm">
                        {Object.keys(services).map((s) => <option key={s}>{s} ({services[s].duration}m)</option>)}
                      </select>
                    </Field>
                    <Field label="Stylist">
                      <select value={form.stylist} onChange={(e) => setForm({ ...form, stylist: e.target.value })} disabled={isStylistView} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-3 py-2 rounded-lg text-sm">
                        {(isClientView || !editingId) && <option>No preference</option>}
                        {stylists.filter((s) => s.active).map((s) => <option key={s.id}>{s.name}</option>)}
                      </select>
                    </Field>
                    <Field label="Date">
                      <input type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} min={todayStr(0)} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm" />
                    </Field>
                    <Field label="Time">
                      <input type="time" value={form.time} onChange={(e) => setForm({ ...form, time: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm" />
                    </Field>
                    <button onClick={submitAppointment} className="btn-wine px-4 py-2 rounded-md text-sm font-medium">{editingId ? "Save changes" : "Save"}</button>
                    <button onClick={resetForm} style={{ color: colors.subtle }} className="px-2 py-2 text-sm">Cancel</button>
                  </div>
                  {formError && (
                    <div className="flex items-center gap-3 flex-wrap">
                      <span style={{ color: colors.danger }} className="text-xs">{formError}</span>
                      {formError.includes("booked over this time") && <button onClick={joinWaitlist} style={{ color: colors.wine }} className="text-xs font-medium underline">Join waitlist instead</button>}
                    </div>
                  )}
                  {isClientView && <div style={{ color: colors.subtle }} className="text-xs">{landingContent.cancellationPolicy}</div>}
                </div>
              )}

              <div className="p-2">
                {filteredAppointments.length === 0 && <div style={{ color: colors.subtle }} className="text-sm text-center py-14 flex flex-col items-center gap-2"><CalendarDays size={28} style={{ color: colors.rose }} />No appointments match these filters.</div>}
                {filteredAppointments.map((a) => (
                  <div key={a.id} style={{ borderBottom: `1px solid ${colors.border}` }} className="flex items-center justify-between px-3 py-3 text-sm gap-2">
                    <div className="flex items-center gap-3 min-w-0">
                      <span style={{ color: colors.subtle }} className="w-16 flex-shrink-0 text-xs">{isClientView || !isStylistView ? a.date.slice(5) : ""} {a.time}</span>
                      <div style={{ background: colors.roseSoft, color: colors.wine }} className="w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium flex-shrink-0">{initials(isClientView ? a.service : a.client)}</div>
                      <div className="min-w-0">
                        <div className="font-medium truncate">{isClientView ? a.service : a.client}</div>
                        <div style={{ color: colors.subtle }} className="text-xs truncate">{isClientView ? `with ${a.stylist}` : `${a.service} · ${a.stylist}`} · ${priceOf(a.service)} · {durationOf(a.service)}m</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2 flex-shrink-0">
                      <span style={{ color: statusColor(a.status, colors) }} className="text-xs font-medium w-20 text-right hidden sm:block">{STATUS_META[a.status].label}</span>
                      {a.status === "confirmed" && <IconBtn onClick={() => startReschedule(a)} title="Reschedule" icon={Pencil} color={colors.subtle} />}
                      {!isClientView && a.status === "confirmed" && <IconBtn onClick={() => advanceStatus(a.id, "in-progress")} title="Start" icon={Play} color={colors.rose} />}
                      {!isClientView && a.status === "in-progress" && <IconBtn onClick={() => advanceStatus(a.id, "completed")} title="Complete" icon={Check} color={colors.success} />}
                      {(a.status === "confirmed" || (!isClientView && a.status === "in-progress")) && <IconBtn onClick={() => cancelAppointment(a)} title="Cancel" icon={X} color={colors.danger} />}
                    </div>
                  </div>
                ))}
              </div>

              {isManagerView && waitlist.length > 0 && (
                <div style={{ borderTop: `1px solid ${colors.border}` }} className="p-5">
                  <div style={{ fontFamily: serifFont }} className="text-base mb-3">Waitlist</div>
                  <div className="flex flex-col gap-2">
                    {waitlist.map((w) => (
                      <div key={w.id} style={{ border: `1px solid ${colors.border}` }} className="flex items-center justify-between px-3 py-2 rounded-md text-sm">
                        <span>{w.clientName} &middot; {w.service} &middot; wants {w.preferredDate}{w.stylist ? ` with ${w.stylist}` : ""}</span>
                        <div className="flex items-center gap-2">
                          <button onClick={() => { setForm({ client: w.clientName, service: w.service, stylist: w.stylist || "No preference", date: w.preferredDate, time: "12:00" }); setShowForm(true); }} style={{ color: colors.wine }} className="text-xs font-medium">Convert</button>
                          <IconBtn onClick={() => removeWaitlistEntry(w.id)} title="Remove" icon={X} color={colors.danger} />
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}

          {activeTab === "clients" && isManagerView && (
            <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl dash-card">
              <div style={{ borderBottom: `1px solid ${colors.border}` }} className="p-5">
                <div style={{ border: `1px solid ${colors.border}` }} className="flex items-center gap-2 px-3 py-2 rounded-md w-full md:w-72">
                  <Search size={14} style={{ color: colors.subtle }} />
                  <input value={clientSearch} onChange={(e) => setClientSearch(e.target.value)} placeholder="Search clients" aria-label="Search clients" style={{ background: "transparent", color: colors.ink }} className="outline-none text-sm flex-1" />
                </div>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr style={{ color: colors.subtle, borderBottom: `1px solid ${colors.border}` }} className="text-left">
                      <th className="font-semibold py-3 px-5">Client</th>
                      <th className="font-semibold py-3 px-5">Phone</th>
                      <th className="font-semibold py-3 px-5">Visits</th>
                      <th className="font-semibold py-3 px-5">Last visit</th>
                      <th className="font-semibold py-3 px-5">Total spend</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filteredClients.map((c) => (
                      <tr key={c.name} style={{ borderBottom: `1px solid ${colors.border}` }}>
                        <td className="py-3 px-5">
                          <div className="flex items-center gap-2">
                            <div style={{ background: colors.roseSoft, color: colors.wine }} className="w-7 h-7 rounded-full flex items-center justify-center text-xs font-medium">{initials(c.name)}</div>
                            {c.name}
                            {c.spend > 1000 && <span style={{ background: colors.wineSoft, color: colors.wine }} className="text-[10px] px-1.5 py-0.5 rounded-md font-medium">VIP</span>}
                          </div>
                        </td>
                        <td className="py-3 px-5" style={{ color: colors.subtle }}>{c.phone}</td>
                        <td className="py-3 px-5">{c.visits}</td>
                        <td className="py-3 px-5" style={{ color: colors.subtle }}>{c.lastVisit}</td>
                        <td className="py-3 px-5 font-medium">${c.spend.toLocaleString()}</td>
                      </tr>
                    ))}
                    {filteredClients.length === 0 && <tr><td colSpan={5} className="text-center py-8" style={{ color: colors.subtle }}>No clients found.</td></tr>}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {activeTab === "stylists" && isManagerView && (
            <div className="flex flex-col gap-4">
              <div className="grid md:grid-cols-2 gap-4">
                {stylists.map((s) => {
                  const stats = leaderboard.find((l) => l.id === s.id) || { total: 0 };
                  const count = appointments.filter((a) => a.stylist === s.name && a.status !== "cancelled").length;
                  const isEditing = editingStylistId === s.id;
                  if (isEditing) {
                    return (
                      <div key={s.id} style={{ background: colors.cardBg, border: `1px solid ${colors.wine}` }} className="rounded-xl p-5 dash-card flex flex-col gap-2">
                        <Field label="Name"><input value={stylistEditDraft.name} onChange={(e) => setStylistEditDraft({ ...stylistEditDraft, name: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                        <Field label="Title"><input value={stylistEditDraft.role} onChange={(e) => setStylistEditDraft({ ...stylistEditDraft, role: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                        <Field label="Photo URL"><input value={stylistEditDraft.photoUrl} onChange={(e) => setStylistEditDraft({ ...stylistEditDraft, photoUrl: e.target.value })} placeholder="https://..." style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                        <Field label="Bio"><textarea value={stylistEditDraft.bio} onChange={(e) => setStylistEditDraft({ ...stylistEditDraft, bio: e.target.value })} rows={2} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                        <div className="flex gap-2 mt-1">
                          <button onClick={() => saveStylistEdit(s.id)} className="btn-wine px-3 py-1.5 rounded-md text-xs font-medium">Save</button>
                          <button onClick={() => setEditingStylistId(null)} style={{ color: colors.subtle }} className="text-xs px-2">Cancel</button>
                        </div>
                      </div>
                    );
                  }
                  return (
                    <div key={s.id} style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card flex items-center justify-between gap-2">
                      <div className="flex items-center gap-3 min-w-0">
                        {s.photoUrl ? <img src={s.photoUrl} alt={s.name} className="w-11 h-11 rounded-full object-cover flex-shrink-0" /> : <div style={{ background: colors.roseSoft, color: colors.wine }} className="w-11 h-11 rounded-full flex items-center justify-center font-medium flex-shrink-0">{initials(s.name)}</div>}
                        <div className="min-w-0">
                          <div className="font-medium truncate">{s.name}</div>
                          <div style={{ color: colors.subtle }} className="text-xs truncate">{s.role} · {count} booked · ${stats.total} earned</div>
                        </div>
                      </div>
                      <div className="flex items-center gap-3 flex-shrink-0">
                        <IconBtn onClick={() => startEditStylist(s)} title="Edit" icon={Pencil} color={colors.subtle} />
                        <button onClick={() => toggleStylistActive(s.id)} aria-pressed={s.active} className="flex items-center gap-1.5 text-xs font-medium" style={{ color: s.active ? colors.success : colors.subtle }}>
                          {s.active ? <ToggleRight size={22} /> : <ToggleLeft size={22} />}
                          {s.active ? "On floor" : "Off today"}
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
              <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Add a stylist</div>
                <div className="flex items-end gap-3 flex-wrap">
                  <Field label="Name"><input value={newStylist.name} onChange={(e) => setNewStylist({ ...newStylist, name: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-44" placeholder="Full name" /></Field>
                  <Field label="Title"><input value={newStylist.role} onChange={(e) => setNewStylist({ ...newStylist, role: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-36" placeholder="e.g. Colorist" /></Field>
                  <Field label="Photo URL"><input value={newStylist.photoUrl} onChange={(e) => setNewStylist({ ...newStylist, photoUrl: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-44" placeholder="https://..." /></Field>
                  <Field label="Bio"><input value={newStylist.bio} onChange={(e) => setNewStylist({ ...newStylist, bio: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-56" placeholder="Short bio for their profile" /></Field>
                  <button onClick={addStylist} className="btn-wine px-4 py-2 rounded-md text-sm font-medium">Add stylist</button>
                </div>
              </div>
            </div>
          )}

          {activeTab === "inventory" && isManagerView && (
            <div className="flex flex-col gap-4">
              <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl dash-card overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr style={{ color: colors.subtle, borderBottom: `1px solid ${colors.border}` }} className="text-left">
                      <th className="font-semibold py-3 px-5">Item</th>
                      <th className="font-semibold py-3 px-5">Category</th>
                      <th className="font-semibold py-3 px-5">Unit cost</th>
                      <th className="font-semibold py-3 px-5">Quantity</th>
                      <th className="font-semibold py-3 px-5">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {inventory.map((i) => {
                      const low = i.qty <= i.reorder;
                      return (
                        <tr key={i.id} style={{ borderBottom: `1px solid ${colors.border}` }}>
                          <td className="py-3 px-5 font-medium">{i.name}</td>
                          <td className="py-3 px-5" style={{ color: colors.subtle }}>{i.category}</td>
                          <td className="py-3 px-5" style={{ color: colors.subtle }}>${i.unitCost.toFixed(2)}</td>
                          <td className="py-3 px-5">
                            <div className="flex items-center gap-2">
                              <IconBtn onClick={() => adjustInventory(i.id, -1)} icon={Minus} color={colors.subtle} title="Decrease" />
                              <span className="w-6 text-center">{i.qty}</span>
                              <IconBtn onClick={() => adjustInventory(i.id, 1)} icon={Plus} color={colors.wine} title="Increase" />
                            </div>
                          </td>
                          <td className="py-3 px-5">
                            {low ? (
                              <span style={{ background: colors.dangerSoft, color: colors.danger }} className="flex items-center gap-1 w-fit text-xs px-2 py-1 rounded-md font-medium"><AlertTriangle size={12} /> Low stock</span>
                            ) : (
                              <span style={{ background: colors.successSoft, color: colors.success }} className="text-xs px-2 py-1 rounded-md font-medium w-fit">In stock</span>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
              <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Add inventory item</div>
                <div className="flex items-end gap-3 flex-wrap">
                  <Field label="Name"><input value={newItem.name} onChange={(e) => setNewItem({ ...newItem, name: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-44" /></Field>
                  <Field label="Category"><input value={newItem.category} onChange={(e) => setNewItem({ ...newItem, category: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-32" /></Field>
                  <Field label="Qty"><input type="number" value={newItem.qty} onChange={(e) => setNewItem({ ...newItem, qty: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-20" /></Field>
                  <Field label="Reorder at"><input type="number" value={newItem.reorder} onChange={(e) => setNewItem({ ...newItem, reorder: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-24" /></Field>
                  <Field label="Unit cost"><input type="number" value={newItem.unitCost} onChange={(e) => setNewItem({ ...newItem, unitCost: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-24" /></Field>
                  <button onClick={addInventoryItem} className="btn-wine px-4 py-2 rounded-md text-sm font-medium">Add item</button>
                </div>
              </div>
            </div>
          )}

          {activeTab === "reports" && isManagerView && (
            <div className="flex flex-col gap-4">
              <div className="flex items-center gap-2">
                {["today", "week", "month"].map((r) => (
                  <button key={r} onClick={() => setReportRange(r)} style={{ border: `1px solid ${reportRange === r ? colors.wine : colors.border}`, color: reportRange === r ? colors.wine : colors.subtle, background: reportRange === r ? colors.wineSoft : "transparent" }} className="text-xs px-3 py-1.5 rounded-md capitalize">{r}</button>
                ))}
              </div>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <StatCard label="Total revenue" value={`$${totalReportRevenue.toLocaleString()}`} sub={`this ${reportRange}`} />
                <StatCard label="Completed services" value={completedInRange} sub={`this ${reportRange}`} />
                <StatCard label="Average ticket" value={`$${avgTicket}`} sub="per completed visit" />
                <StatCard label="Cancellation rate" value={`${cancellationRate}%`} sub={`this ${reportRange}`} />
              </div>
              <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Revenue by service</div>
                <Suspense fallback={<ChartPlaceholder height={240} />}><RevenueByServiceChart data={revenueByService} colors={colors} /></Suspense>
              </div>
            </div>
          )}

          {activeTab === "reviews" && isManagerView && (
            <div className="flex flex-col gap-3">
              {reviews.length === 0 && <div style={{ color: colors.subtle }} className="text-sm">No reviews yet.</div>}
              {reviews.map((r) => (
                <div key={r.id} style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-lg p-4 flex items-center justify-between gap-3 flex-wrap">
                  <div className="min-w-0">
                    <div className="flex gap-0.5 mb-1">{[1, 2, 3, 4, 5].map((n) => <Star key={n} size={12} style={{ color: n <= r.rating ? colors.wine : colors.border }} fill={n <= r.rating ? colors.wine : "none"} />)}</div>
                    <div className="text-sm">"{r.comment}"</div>
                    <div style={{ color: colors.subtle }} className="text-xs">{r.clientName} · {r.stylistName}</div>
                  </div>
                  <div className="flex items-center gap-2 flex-shrink-0">
                    <span style={{ color: r.status === "approved" ? colors.success : r.status === "rejected" ? colors.danger : colors.subtle }} className="text-xs font-medium capitalize">{r.status}</span>
                    {r.status === "pending" && (
                      <>
                        <IconBtn onClick={() => moderateReview(r.id, "approved")} title="Approve" icon={Check} color={colors.success} />
                        <IconBtn onClick={() => moderateReview(r.id, "rejected")} title="Reject" icon={X} color={colors.danger} />
                      </>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}

          {activeTab === "website" && isAdmin && (
            <div className="flex flex-col gap-4 max-w-3xl">
              <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Hero section</div>
                <div className="flex flex-col gap-3">
                  <Field label="Headline"><input value={siteDraft.heroHeadline} onChange={(e) => setSiteDraft({ ...siteDraft, heroHeadline: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                  <Field label="Subtext"><textarea value={siteDraft.heroSub} onChange={(e) => setSiteDraft({ ...siteDraft, heroSub: e.target.value })} rows={2} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                </div>
              </div>

              <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                <div style={{ fontFamily: serifFont }} className="text-lg mb-1">Featured services</div>
                <div style={{ color: colors.subtle }} className="text-xs mb-4">Check the ones to show on the landing page, and optionally add a photo for each.</div>
                <div className="flex flex-col gap-3">
                  {Object.keys(services).map((name) => (
                    <div key={name} style={{ border: `1px solid ${colors.border}` }} className="flex items-center gap-3 p-3 rounded-md flex-wrap">
                      <label className="flex items-center gap-2 text-sm w-40 flex-shrink-0">
                        <input type="checkbox" checked={siteDraft.featuredServiceNames.includes(name)} onChange={() => toggleFeatured(name)} />
                        {name}
                      </label>
                      <input
                        value={services[name].photoUrl || ""}
                        onChange={(e) => updateServicePhoto(name, e.target.value)}
                        aria-label={`${name} photo URL`}
                        placeholder="Photo URL (optional)"
                        style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }}
                        className="px-2 py-1.5 rounded-md text-sm flex-1 min-w-[180px]"
                      />
                      {services[name].photoUrl && <img src={services[name].photoUrl} alt="" className="w-10 h-10 rounded-md object-cover flex-shrink-0" />}
                    </div>
                  ))}
                </div>
              </div>

              <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                <div style={{ fontFamily: serifFont }} className="text-lg mb-4">FAQs</div>
                <div className="flex flex-col gap-2 mb-4">
                  {siteDraft.faqs.map((f) => (
                    <div key={f.id} style={{ border: `1px solid ${colors.border}` }} className="flex items-center justify-between gap-2 px-3 py-2 rounded-md text-sm">
                      <span className="truncate">{f.question}</span>
                      <button onClick={() => removeFaq(f.id)} aria-label="Remove FAQ" style={{ color: colors.danger }}><X size={14} /></button>
                    </div>
                  ))}
                </div>
                <div className="flex items-end gap-3 flex-wrap">
                  <Field label="Question"><input value={newFaq.question} onChange={(e) => setNewFaq({ ...newFaq, question: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-56" /></Field>
                  <Field label="Answer"><input value={newFaq.answer} onChange={(e) => setNewFaq({ ...newFaq, answer: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-64" /></Field>
                  <button onClick={addFaq} className="btn-wine px-4 py-2 rounded-md text-sm font-medium">Add</button>
                </div>
              </div>

              <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Contact, hours & policy</div>
                <div className="grid sm:grid-cols-2 gap-3 mb-3">
                  <Field label="Phone"><input value={siteDraft.contactPhone} onChange={(e) => setSiteDraft({ ...siteDraft, contactPhone: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                  <Field label="Address / map query"><input value={siteDraft.contactAddress} onChange={(e) => setSiteDraft({ ...siteDraft, contactAddress: e.target.value, mapQuery: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                  <Field label="Opens"><input type="time" value={siteDraft.openTime} onChange={(e) => setSiteDraft({ ...siteDraft, openTime: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                  <Field label="Closes"><input type="time" value={siteDraft.closeTime} onChange={(e) => setSiteDraft({ ...siteDraft, closeTime: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                </div>
                <Field label="Cancellation policy"><textarea value={siteDraft.cancellationPolicy} onChange={(e) => setSiteDraft({ ...siteDraft, cancellationPolicy: e.target.value })} rows={2} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
              </div>

              <button onClick={saveSite} className="btn-wine px-4 py-2.5 rounded-md text-sm font-medium self-start">Publish changes to landing page</button>
            </div>
          )}

          {activeTab === "audit" && isAdmin && (
            <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl dash-card">
              {auditLog.length === 0 && <div style={{ color: colors.subtle }} className="text-sm text-center py-10">No activity recorded yet.</div>}
              {auditLog.map((log) => (
                <div key={log.id} style={{ borderBottom: `1px solid ${colors.border}` }} className="flex items-center justify-between px-4 py-3 text-sm gap-2">
                  <div className="flex items-center gap-2 min-w-0">
                    <ClipboardList size={14} style={{ color: colors.subtle }} className="flex-shrink-0" />
                    <span className="font-medium">{log.action}</span>
                    {log.meta && Object.keys(log.meta).length > 0 && (
                      <span style={{ color: colors.subtle }} className="truncate">
                        — {Object.entries(log.meta).map(([k, v]) => `${k}: ${v}`).join(", ")}
                      </span>
                    )}
                  </div>
                  <div style={{ color: colors.subtle }} className="text-xs flex-shrink-0">{log.user?.name || "System"} · {log.created_at ? new Date(log.created_at).toLocaleString() : ""}</div>
                </div>
              ))}
            </div>
          )}

          {activeTab === "settings" && (
            <div className="flex flex-col gap-4 max-w-2xl">
              <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                <div style={{ fontFamily: serifFont }} className="text-lg mb-4">{isClientView ? "My profile" : isStylistView ? "My profile" : "Salon profile"}</div>
                {isAdmin && (
                  <div className="grid sm:grid-cols-2 gap-4">
                    <Field label="Salon name"><input value={siteDraft.salonName} onChange={(e) => setSiteDraft({ ...siteDraft, salonName: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                    <Field label="Phone"><input value={siteDraft.contactPhone} onChange={(e) => setSiteDraft({ ...siteDraft, contactPhone: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                    <Field label="Address"><input value={siteDraft.contactAddress} onChange={(e) => setSiteDraft({ ...siteDraft, contactAddress: e.target.value, mapQuery: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
                  </div>
                )}
                {isManagerView && !isAdmin && <div className="text-sm" style={{ color: colors.subtle }}>Salon profile and pricing are managed by an admin. You can still manage appointments, clients, stylists, and inventory.</div>}
                {isStylistView && <div className="text-sm" style={{ color: colors.subtle }}>{currentStylist?.name} · {currentStylist?.role}. Availability is managed by the front desk.</div>}
                {isClientView && (
                  <div className="grid sm:grid-cols-2 gap-4">
                    <Field label="Name"><input value={user.name} disabled style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full opacity-70" /></Field>
                    <Field label="Email"><input value={user.email} disabled style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full opacity-70" /></Field>
                  </div>
                )}
                {isAdmin && (
                  <div className="flex items-center gap-3 mt-4">
                    <button onClick={saveSalonInfo} className="btn-wine px-4 py-2 rounded-md text-sm font-medium">Save changes</button>
                    {savedMsg && <span style={{ color: colors.success }} className="text-xs font-medium">{savedMsg}</span>}
                  </div>
                )}
              </div>

              <div className="flex items-center justify-between p-4 rounded-lg" style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }}>
                <div>
                  <div className="text-sm font-medium">Appearance</div>
                  <div style={{ color: colors.subtle }} className="text-xs">Switch between light and dark mode.</div>
                </div>
                <ThemeToggle />
              </div>

              {isAdmin && (
                <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
                  <div style={{ fontFamily: serifFont }} className="text-lg mb-1">Manage services</div>
                  <div style={{ color: colors.subtle }} className="text-xs mb-4">Add, reprice, or remove what the salon offers. Names can't be edited once created, since past appointments reference them by name.</div>
                  <div className="flex flex-col gap-2 mb-4">
                    {Object.entries(services).map(([name, info]) => (
                      <div key={name} style={{ border: `1px solid ${colors.border}` }} className="flex items-center gap-2 p-2 rounded-md flex-wrap">
                        <span className="flex-1 min-w-[140px] text-sm font-medium truncate">{name}</span>
                        <div className="flex items-center gap-1">
                          <span style={{ color: colors.subtle }} className="text-xs">$</span>
                          <input type="number" aria-label={`${name} price`} value={info.price} onChange={(e) => updateServicePrice(name, e.target.value)} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="w-16 px-2 py-1 rounded-md text-sm" />
                        </div>
                        <div className="flex items-center gap-1">
                          <input type="number" aria-label={`${name} duration in minutes`} value={info.duration} onChange={(e) => updateServiceDuration(name, e.target.value)} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="w-14 px-2 py-1 rounded-md text-sm" />
                          <span style={{ color: colors.subtle }} className="text-xs">min</span>
                        </div>
                        <IconBtn onClick={() => deleteService(name)} title="Delete service" icon={X} color={colors.danger} />
                      </div>
                    ))}
                    {Object.keys(services).length === 0 && <div style={{ color: colors.subtle }} className="text-sm">No services yet - add one below.</div>}
                  </div>
                  <div style={{ borderTop: `1px solid ${colors.border}` }} className="pt-4 flex items-end gap-3 flex-wrap">
                    <Field label="New service name"><input value={newService.name} onChange={(e) => setNewService({ ...newService, name: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-44" placeholder="e.g. Scalp Treatment" /></Field>
                    <Field label="Price"><input type="number" value={newService.price} onChange={(e) => setNewService({ ...newService, price: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-24" placeholder="65" /></Field>
                    <Field label="Duration (min)"><input type="number" value={newService.duration} onChange={(e) => setNewService({ ...newService, duration: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-28" placeholder="45" /></Field>
                    <button onClick={addService} className="btn-wine px-4 py-2 rounded-md text-sm font-medium">Add service</button>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </main>
    </div>
  );
}

function StatCard({ label, value, sub, trend }) {
  const { colors } = useTheme();
  return (
    <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="stat-card dash-card rounded-xl p-5">
      <div className="flex items-center justify-between mb-3 gap-2">
        <div style={{ color: colors.subtle }} className="text-[11px] font-semibold uppercase tracking-wider">{label}</div>
        {trend !== null && trend !== undefined && (
          <div style={{ color: trend >= 0 ? colors.success : colors.danger, background: trend >= 0 ? colors.successSoft : colors.dangerSoft }} className="text-[11px] font-semibold flex items-center gap-0.5 px-1.5 py-0.5 rounded-full">
            {trend >= 0 ? "▲" : "▼"} {Math.abs(trend)}%
          </div>
        )}
      </div>
      <div style={{ fontFamily: serifFont, color: colors.wine }} className="text-2xl md:text-3xl leading-none mb-2">{value}</div>
      <div style={{ color: colors.subtle }} className="text-xs">{sub}</div>
    </div>
  );
}

function Field({ label, children }) {
  const { colors } = useTheme();
  return (
    // The <label> wraps the control, so the text is programmatically tied to the input
    // (screen readers announce it, and clicking the text focuses the field).
    <label className="flex flex-col gap-1">
      <span style={{ color: colors.subtle }} className="text-xs">{label}</span>
      {children}
    </label>
  );
}

function IconBtn({ onClick, icon: Icon, color, title }) {
  return (
    <button onClick={onClick} title={title} aria-label={title} style={{ border: `1px solid ${color}`, color }} className="icon-btn w-7 h-7 rounded-md flex items-center justify-center flex-shrink-0">
      <Icon size={13} />
    </button>
  );
}
