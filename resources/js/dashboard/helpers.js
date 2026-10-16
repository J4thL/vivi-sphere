import { BarChart3, CalendarDays, Building2, Globe, LayoutDashboard, Package, Scissors, Settings as SettingsIcon, ShieldCheck, Star, UserPlus, Users } from "lucide-react";

/* -------------------------------- Constants -------------------------------- */

// A date as YYYY-MM-DD in the browser's local timezone. toISOString() would give the UTC date,
// which is the wrong day for several hours every day anywhere ahead of UTC (e.g. the Philippines).
export function localDateStr(d) {
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${d.getFullYear()}-${month}-${day}`;
}
export function todayStr(offset = 0) {
  const d = new Date();
  d.setDate(d.getDate() + offset);
  return localDateStr(d);
}
// Moves a YYYY-MM-DD date forward or back by whole days.
export function addDays(dateStr, n) {
  const d = new Date(dateStr + "T00:00:00");
  d.setDate(d.getDate() + n);
  return localDateStr(d);
}
export function formatDateLabel(dateStr) {
  const d = new Date(dateStr + "T00:00:00");
  return d.toLocaleDateString(undefined, { weekday: "short", month: "short", day: "numeric" });
}
export function timeToMinutes(t) { const [h, m] = t.split(":").map(Number); return h * 60 + m; }
export function rangesOverlap(startA, durA, startB, durB) {
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
export const EMPTY_LANDING_CONTENT = {
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
export function computeWeeklyRevenue(appointments, services) {
  const days = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    days.push(localDateStr(d));
  }
  return days.map((dateStr) => {
    const label = new Date(dateStr + "T00:00:00").toLocaleDateString(undefined, { weekday: "short" });
    const revenue = appointments
      .filter((a) => a.date === dateStr && a.status === "completed")
      .reduce((sum, a) => sum + (a.price ?? services[a.service]?.price ?? 0), 0);
    return { day: label, revenue };
  });
}

export function toApiSettings(c) {
  return {
    salon_name: c.salonName, hero_headline: c.heroHeadline, hero_sub: c.heroSub, featured_services: c.featuredServiceNames,
    faqs: c.faqs, contact_phone: c.contactPhone, contact_address: c.contactAddress, map_query: c.mapQuery,
    cancellation_policy: c.cancellationPolicy, open_time: c.openTime, close_time: c.closeTime,
  };
}
export function fromApiSettings(d) {
  if (!d) return null;
  return {
    salonName: d.salon_name || "", heroHeadline: d.hero_headline, heroSub: d.hero_sub, featuredServiceNames: d.featured_services || [],
    faqs: (d.faqs || []).map((f, i) => ({ id: i + 1, ...f })),
    contactPhone: d.contact_phone, contactAddress: d.contact_address, mapQuery: d.map_query,
    cancellationPolicy: d.cancellation_policy, openTime: d.open_time?.slice(0, 5) || "09:00", closeTime: d.close_time?.slice(0, 5) || "19:00",
  };
}



export const NAV_ITEMS = [
  { key: "overview", label: "Overview", icon: LayoutDashboard, roles: ["admin", "manager", "stylist", "client"], group: null },
  { key: "appointments", label: "Appointments", icon: CalendarDays, roles: ["admin", "manager", "stylist", "client"], group: "Day to day" },
  { key: "clients", label: "Clients", icon: Users, roles: ["admin", "manager"], group: "Day to day" },
  { key: "stylists", label: "Stylists", icon: Scissors, roles: ["admin", "manager"], group: "Day to day" },
  { key: "inventory", label: "Inventory", icon: Package, roles: ["admin", "manager"], group: "Day to day" },
  { key: "reports", label: "Reports", icon: BarChart3, roles: ["admin", "manager"], group: "Insights" },
  { key: "reviews", label: "Reviews", icon: Star, roles: ["admin", "manager"], group: "Insights" },
  { key: "branches", label: "Branches", icon: Building2, roles: ["admin"], group: "Admin" },
  { key: "staff", label: "Staff accounts", icon: UserPlus, roles: ["admin"], group: "Admin" },
  { key: "website", label: "Website", icon: Globe, roles: ["admin"], group: "Admin" },
  { key: "audit", label: "Audit log", icon: ShieldCheck, roles: ["admin"], group: "Admin" },
  { key: "settings", label: "Settings", icon: SettingsIcon, roles: ["admin", "manager", "stylist", "client"], group: "Admin" },
];

export const STATUS_META = {
  confirmed: { label: "Confirmed" }, "in-progress": { label: "In progress" },
  completed: { label: "Completed" }, cancelled: { label: "Cancelled" },
};
export function statusColor(status, colors) {
  return { confirmed: colors.subtle, "in-progress": colors.wine, completed: colors.success, cancelled: colors.danger }[status];
}
export function initials(name) {
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
export function normalizeAppointment(a) {
  const [date, time] = String(a.scheduled_at).split(" ");
  return {
    id: a.id,
    date: date,
    time: (time || "00:00:00").slice(0, 5),
    client: a.client?.name ?? a.client,
    service: a.service?.name ?? a.service,
    stylist: a.stylist?.name ?? a.stylist,
    status: a.status,
    // The price agreed when it was booked; reports must not follow later changes to the menu price.
    price: a.price != null ? Number(a.price) : null,
    // Length booked; older rows have none and use the service's current length.
    duration: a.duration_minutes != null ? Number(a.duration_minutes) : null,
    branchId: a.branch_id ?? a.branchId ?? null,
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

export function normalizeClient(c) {
  return {
    id: c.id,
    name: c.name,
    phone: c.phone || "-",
    visits: c.visits ?? 0,
    lastVisit: c.last_visit_at !== undefined ? relativeLastVisit(c.last_visit_at) : c.lastVisit,
    spend: Number(c.spend) || 0,
  };
}

export function normalizeInventoryItem(i) {
  return {
    id: i.id,
    name: i.name,
    category: i.category || "General",
    qty: i.qty ?? 0,
    reorder: i.reorder_level ?? i.reorder ?? 5,
    unitCost: Number(i.unit_cost ?? i.unitCost) || 0,
  };
}

export function normalizeStylist(s) {
  return { id: s.id, name: s.name, role: s.role, branchId: s.branch_id ?? s.branchId ?? null, active: !!s.active, photoUrl: s.photo_url ?? s.photoUrl ?? "", bio: s.bio ?? "" };
}

export function normalizeReview(r) {
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

export function normalizeWaitlistEntry(w) {
  return {
    id: w.id,
    clientName: w.client_name ?? w.clientName,
    service: w.service?.name ?? w.service,
    stylist: w.stylist?.name ?? w.stylist ?? null,
    preferredDate: String(w.preferred_date ?? w.preferredDate).slice(0, 10),
    branchId: w.branch_id ?? w.branchId ?? null,
  };
}

// Salon currency: Philippine peso, formatted in one place.
export const formatMoney = (n) => `₱${Number(n || 0).toLocaleString("en-PH", { maximumFractionDigits: 2 })}`;
