import React, { useCallback, useMemo, useRef, useState } from "react";
import { LogOut, Menu, Plus, X } from "lucide-react";
import { apiDelete, apiSend } from "./api.js";
import { NAV_ITEMS, STATUS_META, computeWeeklyRevenue, formatDateLabel, initials, rangesOverlap, timeToMinutes, todayStr } from "./helpers.js";
import AppointmentsTab from "./tabs/AppointmentsTab.jsx";
import AuditTab from "./tabs/AuditTab.jsx";
import ClientsTab from "./tabs/ClientsTab.jsx";
import InventoryTab from "./tabs/InventoryTab.jsx";
import OverviewTab from "./tabs/OverviewTab.jsx";
import ReportsTab from "./tabs/ReportsTab.jsx";
import ReviewsTab from "./tabs/ReviewsTab.jsx";
import SettingsTab from "./tabs/SettingsTab.jsx";
import BranchesTab from "./tabs/BranchesTab.jsx";
import StaffTab from "./tabs/StaffTab.jsx";
import StylistsTab from "./tabs/StylistsTab.jsx";
import WebsiteTab from "./tabs/WebsiteTab.jsx";
import { serifFont, useBrand, useTheme } from "./theme.jsx";
import { NotifyBanner, ThemeToggle, useDocumentMeta } from "./ui.jsx";

/* -------------------------------- Dashboard ------------------------------- */

export default function Dashboard(props) {
  const {
    user, token, onLogout, apiConnected, dashLoading,
    appointments, setAppointments, services, setServices, stylists, setStylists,
    inventory, setInventory, clients, reviews, setReviews,
    waitlist, setWaitlist, auditLog,
    landingContent, saveLandingContent, notify, notice, askConfirm, resendVerification, onPasswordChanged, branches, setBranches, branchServices, setBranchServices,
  } = props;
  const { colors } = useTheme();
  const role = user.role;
  const isManagerView = role === "admin" || role === "manager";
  const isAdmin = role === "admin";
  const isStylistView = role === "stylist";
  const isClientView = role === "client";
  const currentStylist = isStylistView ? (stylists.find((s) => s.id === (user.stylist_id ?? user.stylist?.id)) || null) : null;

  // Guards against double-clicks: while a save is in flight the handler is ignored and its button disabled.
  const [busy, setBusy] = useState({});
  const busyRef = useRef({});
  const once = (key, fn) => async (...args) => {
    if (busyRef.current[key]) return;
    busyRef.current[key] = true;
    setBusy((b) => ({ ...b, [key]: true }));
    try { await fn(...args); } finally {
      busyRef.current[key] = false;
      setBusy((b) => ({ ...b, [key]: false }));
    }
  };
  const [activeTab, setActiveTab] = useState("overview");
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [stylistFilter, setStylistFilter] = useState("All");
  const [statusFilter, setStatusFilter] = useState("all");
  const [clientSearch, setClientSearch] = useState("");
  const [selectedDate, setSelectedDate] = useState(todayStr(0));
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [formError, setFormError] = useState("");
  const emptyForm = { client: isClientView ? user.name : "", clientId: null, newClient: false, service: Object.keys(services)[0], stylist: "No preference", branchId: "", date: todayStr(0), time: "12:00" };
  const [form, setForm] = useState(emptyForm);
  const [convertingWaitlistId, setConvertingWaitlistId] = useState(null);
  const [reviewDraft, setReviewDraft] = useState({});

  const [reportRange, setReportRange] = useState("today");
  const [newStylist, setNewStylist] = useState({ name: "", role: "", email: "", photoUrl: "", bio: "", branchId: "" });
  const [createdLogin, setCreatedLogin] = useState(null); // {name, email, password} shown once after the admin creates an employee login
  const [newService, setNewService] = useState({ name: "", price: "", duration: "" });
  const [editingStylistId, setEditingStylistId] = useState(null);
  const [stylistEditDraft, setStylistEditDraft] = useState({});
  const [newItem, setNewItem] = useState({ name: "", category: "", qty: "", reorder: "", unitCost: "", branchId: "" });
  const [savedMsg, setSavedMsg] = useState("");
  const [siteDraft, setSiteDraft] = useState(landingContent);
  const [newFaq, setNewFaq] = useState({ question: "", answer: "" });

  const brand = useBrand();
  useDocumentMeta(`${isClientView ? "My account" : "Dashboard"} — ${brand}`, "Salon management dashboard.");

  const serviceInfo = (name) => services[name] || { price: 0, duration: 45 };
  // Memoised so the revenue calculations below only re-run when the services change.
  const priceOf = useCallback((service) => (services[service] || { price: 0 }).price, [services]);
  const durationOf = (service) => serviceInfo(service).duration;
  // A branch can have its own price, length and availability for a service; otherwise the normal ones apply.
  const termsFor = (name, branchId) => {
    const base = serviceInfo(name);
    const o = branchId ? branchServices.find((x) => x.branch_id === branchId && x.service_id === base.id) : null;
    return { price: o?.price ?? base.price, duration: o?.duration_minutes ?? base.duration, available: o ? o.available : true };
  };
  const visibleNav = NAV_ITEMS.filter((n) => n.roles.includes(role));

  const isSlotConflict = (stylistName, date, time, service, excludeId) =>
    appointments.some((a) => a.id !== excludeId && a.stylist === stylistName && a.date === date && a.status !== "cancelled" && rangesOverlap(a.time, a.duration ?? durationOf(a.service), time, termsFor(service, formBranchId).duration));

  // Staff other than the admin work in one branch. Clients and the admin choose which branch a booking is for
  // (and if only one branch is open, it is simply that one).
  const activeBranches = branches.filter((b) => b.active);
  const lockedBranchId = (isManagerView && !isAdmin) || isStylistView ? user.branch_id : null;
  const formBranchId = lockedBranchId ?? (Number(form.branchId) || (activeBranches.length === 1 ? activeBranches[0].id : null));
  const formBranch = branches.find((b) => b.id === formBranchId) || null;

  const isWithinHours = (time, service, branch = formBranch) => {
    const start = timeToMinutes(time);
    const end = start + termsFor(service, branch?.id ?? null).duration;
    const open = branch?.open_time ?? landingContent.openTime;
    const close = branch?.close_time ?? landingContent.closeTime;
    return start >= timeToMinutes(open) && end <= timeToMinutes(close);
  };

  const advanceStatus = async (id, next) => {
    setAppointments((prev) => prev.map((a) => (a.id === id ? { ...a, status: next } : a)));
    const result = await apiSend(`/appointments/${id}`, "PATCH", { status: next }, token);
    if (result?.error) notify(result.message, "error");
  };

  const cancelAppointment = (a) => {
    askConfirm(`Cancel ${a.client}'s ${a.service} on ${a.date} at ${a.time}?`, () => advanceStatus(a.id, "cancelled"), "Cancel appointment");
  };

  const leastBookedActiveStylist = (date, branchId = formBranchId) => {
    const active = stylists.filter((s) => s.active && (branchId == null || s.branchId === branchId));
    if (active.length === 0) return stylists[0]?.name;
    const counts = active.map((s) => ({ name: s.name, count: appointments.filter((a) => a.stylist === s.name && a.date === date && a.status !== "cancelled").length }));
    return counts.sort((a, b) => a.count - b.count)[0].name;
  };

  const resetForm = () => { setForm(emptyForm); setEditingId(null); setConvertingWaitlistId(null); setFormError(""); setShowForm(false); };

  const joinWaitlist = async () => {
    const tempId = `temp-${Date.now()}`;
    if (!formBranchId) { setFormError("Please choose a branch."); return; }
    const entry = { id: tempId, clientName: form.client, service: form.service, stylist: form.stylist === "No preference" ? null : form.stylist, preferredDate: form.date, branchId: formBranchId };
    const stylistId = stylists.find((s) => s.name === entry.stylist)?.id;
    setWaitlist((prev) => [...prev, entry]);
    resetForm();
    const result = await apiSend("/waitlist", "POST", { client_name: entry.clientName, service_id: services[entry.service]?.id, ...(stylistId ? { stylist_id: stylistId } : {}), branch_id: entry.branchId, preferred_date: entry.preferredDate }, token);
    if (result?.error) {
      setWaitlist((prev) => prev.filter((w) => w.id !== tempId));
      notify(result.message, "error");
      return;
    }
    // Swap in the real database id so "Remove" targets a row that exists server-side.
    if (result?.id) setWaitlist((prev) => prev.map((w) => (w.id === tempId ? { ...entry, id: result.id } : w)));
    notify("Added to the waitlist. We'll reach out if a slot opens.");
  };

  // "Convert" pre-fills the booking form from a waitlist entry; remember which entry
  // so it can be removed once the appointment is actually saved.
  const convertWaitlistEntry = (w) => {
    setForm({ client: w.clientName, clientId: null, newClient: false, service: w.service, stylist: w.stylist || "No preference", branchId: w.branchId ?? "", date: w.preferredDate, time: "12:00" });
    setConvertingWaitlistId(w.id);
    setFormError("");
    setShowForm(true);
  };

  const submitAppointmentRaw = async () => {
    if (!form.client.trim()) { setFormError("Enter a client name."); return; }
    if (!formBranchId) { setFormError("Please choose a branch."); return; }
    if (!isWithinHours(form.time, form.service)) { setFormError(`That's outside this branch's hours (${formBranch?.open_time ?? landingContent.openTime}–${formBranch?.close_time ?? landingContent.closeTime}).`); return; }
    const stylistName = form.stylist === "No preference" ? leastBookedActiveStylist(form.date) : form.stylist;
    if (!stylistName) { setFormError("No stylist is available at this branch."); return; }

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
      const newAppt = { id: tempId, date: form.date, time: form.time, client: form.client, service: form.service, stylist: stylistName, branchId: formBranchId, price: termsFor(form.service, formBranchId).price, duration: termsFor(form.service, formBranchId).duration, status: "confirmed" };
      setAppointments((prev) => [...prev, newAppt].sort((a, b) => (a.date + a.time).localeCompare(b.date + b.time)));
      const result = await apiSend("/appointments", "POST", { client_name: form.client, ...(form.clientId ? { client_id: form.clientId } : {}), ...(form.newClient ? { new_client: true } : {}), stylist_id: stylists.find((s) => s.name === stylistName)?.id, service_id: services[form.service]?.id, scheduled_at: `${form.date} ${form.time}:00` }, token);
      if (result?.error) {
        // Roll the optimistic row back out - the server rejected it, so
        // leaving it on screen would show an appointment that isn't saved.
        setAppointments((prev) => prev.filter((a) => a.id !== tempId));
        // Name already belongs to a client: keep the form open and let the user choose (see AppointmentsTab).
        if (/already exists/i.test(result.message || "")) { setFormError(result.message); return; }
        notify(result.message, "error");
        return;
      }
      // Swap in the real database id so later status changes / reschedules
      // target a row that actually exists server-side.
      if (result?.id) setAppointments((prev) => prev.map((a) => (a.id === tempId ? { ...newAppt, id: result.id } : a)));
      notify(isClientView ? "Appointment booked." : "Appointment added.");
      if (convertingWaitlistId) removeWaitlistEntry(convertingWaitlistId);
    }
    setActiveTab("appointments");
    resetForm();
  };

  const startReschedule = (a) => { setEditingId(a.id); setForm({ client: a.client, clientId: null, newClient: false, service: a.service, stylist: a.stylist, branchId: a.branchId ?? "", date: a.date, time: a.time }); setFormError(""); setShowForm(true); };

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

  const addStylistRaw = async () => {
    if (!newStylist.name.trim()) return;
    if (isAdmin && !newStylist.branchId) { notify("Please choose a branch for this stylist.", "error"); return; }
    const tempId = `temp-${Date.now()}`;
    const rec = { id: tempId, name: newStylist.name, role: newStylist.role || "Stylist", active: true, branchId: isAdmin ? Number(newStylist.branchId) : user.branch_id, photoUrl: newStylist.photoUrl, bio: newStylist.bio };
    setStylists((prev) => [...prev, rec]);
    const loginEmail = isAdmin ? newStylist.email.trim() : "";
    setNewStylist({ name: "", role: "", email: "", photoUrl: "", bio: "", branchId: "" });
    const result = await apiSend("/stylists", "POST", { name: rec.name, role: rec.role, email: loginEmail || null, branch_id: isAdmin ? rec.branchId : null, photo_url: rec.photoUrl || null, bio: rec.bio || null }, token);
    if (result?.error) {
      setStylists((prev) => prev.filter((s) => s.id !== tempId));
      notify(result.message, "error");
      return;
    }
    if (result?.id) setStylists((prev) => prev.map((s) => (s.id === tempId ? { ...rec, id: result.id } : s)));
    if (result?.temp_password) setCreatedLogin({ name: rec.name, email: loginEmail, password: result.temp_password });
    notify("Stylist added.");
  };

  const adjustInventory = (id, delta) => {
    const target = inventory.find((i) => i.id === id);
    setInventory((prev) => prev.map((i) => (i.id === id ? { ...i, qty: Math.max(0, i.qty + delta) } : i)));
    if (target) apiSend(`/inventory/${id}`, "PATCH", { qty: Math.max(0, target.qty + delta) }, token).then((r) => { if (r?.error) notify(r.message, "error"); });
  };

  const addInventoryItemRaw = async () => {
    if (!newItem.name.trim()) return;
    if (isAdmin && !newItem.branchId) { notify("Please choose a branch for this item.", "error"); return; }
    const tempId = `temp-${Date.now()}`;
    const rec = { id: tempId, name: newItem.name, category: newItem.category || "General", qty: Number(newItem.qty) || 0, reorder: Number(newItem.reorder) || 5, unitCost: Number(newItem.unitCost) || 0, branchId: isAdmin ? Number(newItem.branchId) : user.branch_id };
    setInventory((prev) => [...prev, rec]);
    setNewItem({ name: "", category: "", qty: "", reorder: "", unitCost: "", branchId: "" });
    // The API uses snake_case column names (reorder_level, unit_cost) while
    // this component uses camelCase - translate rather than sending `rec` raw.
    const result = await apiSend("/inventory", "POST", {
      name: rec.name, category: rec.category, qty: rec.qty,
      reorder_level: rec.reorder, unit_cost: rec.unitCost, ...(isAdmin ? { branch_id: rec.branchId } : {}),
    }, token);
    if (result?.error) { setInventory((prev) => prev.filter((i) => i.id !== tempId)); notify(result.message, "error"); return; }
    if (result?.id) setInventory((prev) => prev.map((i) => (i.id === tempId ? { ...rec, id: result.id } : i)));
    notify("Inventory item added.");
  };

  // Saves one service field. The inputs call this on blur/Enter (not every keystroke), and if the
  // server rejects the value the local copy is rolled back so the screen matches what is saved.
  const patchService = async (name, localPatch, apiPatch) => {
    const before = services[name];
    setServices((prev) => ({ ...prev, [name]: { ...prev[name], ...localPatch } }));
    if (!before?.id) return;
    const result = await apiSend(`/services/${before.id}`, "PATCH", apiPatch, token);
    if (result?.error) {
      const restore = Object.fromEntries(Object.keys(localPatch).map((k) => [k, before[k]]));
      setServices((prev) => ({ ...prev, [name]: { ...prev[name], ...restore } }));
      notify(result.message, "error");
    }
  };

  const updateServicePrice = (name, price) => patchService(name, { price: Number(price) || 0 }, { price: Number(price) || 0 });
  const updateServiceDuration = (name, duration) => patchService(name, { duration: Number(duration) || 5 }, { duration_minutes: Number(duration) || 5 });
  const updateServicePhoto = (name, photoUrl) => patchService(name, { photoUrl }, { photo_url: photoUrl || null });

  const addServiceRaw = async () => {
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

  // The API already limits appointments to the signed-in stylist's or client's own,
  // so no name-based filtering here (names aren't unique and can differ from the login name).
  const scopedAppointments = appointments;

  const dayAppointments = isManagerView || isStylistView ? scopedAppointments.filter((a) => a.date === selectedDate) : scopedAppointments;

  // Overview's "today"/"upcoming" preview panels need their own date-scoped
  // slice - scopedAppointments alone is sorted but not filtered, so without
  // this, "Today's schedule" would show the earliest appointments overall
  // (likely from the past) rather than actually today's.
  const overviewPreview = isClientView
    ? scopedAppointments.filter((a) => a.date >= todayStr(0))
    : scopedAppointments.filter((a) => a.date === todayStr(0));

  const todaysRevenue = useMemo(() => scopedAppointments.filter((a) => a.date === todayStr(0) && a.status === "completed").reduce((sum, a) => sum + (a.price ?? priceOf(a.service)), 0), [scopedAppointments, priceOf]);
  const yesterdaysRevenue = useMemo(() => scopedAppointments.filter((a) => a.date === todayStr(-1) && a.status === "completed").reduce((sum, a) => sum + (a.price ?? priceOf(a.service)), 0), [scopedAppointments, priceOf]);
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
    appointments.filter((a) => a.status === "completed" || a.status === "in-progress").forEach((a) => { totals[a.stylist] = (totals[a.stylist] || 0) + (a.price ?? priceOf(a.service)); });
    const max = Math.max(1, ...Object.values(totals));
    return stylists.map((s) => ({ ...s, total: totals[s.name] || 0, pct: (totals[s.name] || 0) / max })).sort((a, b) => b.total - a.total);
  }, [appointments, stylists, priceOf]);

  const reportRangeStart = useMemo(() => {
    if (reportRange === "today") return todayStr(0);
    if (reportRange === "week") return todayStr(-6);
    return todayStr(-29); // month: last 30 days including today
  }, [reportRange]);
  const reportAppointments = useMemo(() => appointments.filter((a) => a.date >= reportRangeStart && a.date <= todayStr(0)), [appointments, reportRangeStart]);

  const revenueByService = useMemo(() => {
    const totals = {};
    Object.keys(services).forEach((s) => (totals[s] = 0));
    reportAppointments.filter((a) => a.status === "completed").forEach((a) => { totals[a.service] = (totals[a.service] || 0) + (a.price ?? priceOf(a.service)); });
    return Object.entries(totals).map(([service, revenue]) => ({ service, revenue: Math.round(revenue) }));
  }, [reportAppointments, services, priceOf]);

  const filteredAppointments = dayAppointments.filter((a) => {
    const stylistOk = isStylistView || isClientView || stylistFilter === "All" || a.stylist === stylistFilter;
    const statusOk = statusFilter === "all" || a.status === statusFilter;
    return stylistOk && statusOk;
  }).sort((a, b) => (a.date + a.time).localeCompare(b.date + b.time));

  const filteredClients = clients.filter((c) => c.name.toLowerCase().includes(clientSearch.toLowerCase()));
  const myClientRecord = isClientView ? (clients[0] ?? null) : null; // API returns only this client's own record
  const reviewableAppointments = isClientView ? scopedAppointments.filter((a) => a.status === "completed" && !reviews.some((r) => r.appointmentId === a.id)) : [];

  const totalReportRevenue = Math.round(reportAppointments.filter((a) => a.status === "completed").reduce((s, a) => s + (a.price ?? priceOf(a.service)), 0));
  const completedInRange = reportAppointments.filter((a) => a.status === "completed").length;
  const cancelledInRange = reportAppointments.filter((a) => a.status === "cancelled").length;
  const cancellationRate = reportAppointments.length > 0 ? Math.round((cancelledInRange / reportAppointments.length) * 100) : 0;
  const avgTicket = Math.round(totalReportRevenue / Math.max(1, completedInRange));

  const pageTitle = isClientView ? `Hi, ${user.name.split(" ")[0]}` : isStylistView ? `Welcome back, ${(currentStylist?.name ?? user.name).split(" ")[0]}` : NAV_ITEMS.find((n) => n.key === activeTab)?.label;

  const submitAppointment = once("appointment", submitAppointmentRaw);
  const addStylist = once("stylist", addStylistRaw);
  const addService = once("service", addServiceRaw);
  const addInventoryItem = once("inventory", addInventoryItemRaw);

  // Everything the tab components need, in one place.
  const ctx = {
    token, notify, onPasswordChanged, branches, busy, createdLogin, setCreatedLogin, activeCount, addFaq, addInventoryItem, addService, addStylist, adjustInventory,
    advanceStatus, appointments, auditLog, avgTicket, cancelAppointment, cancellationRate,
    clients, clientSearch, completedInRange, countTrend, currentStylist, deleteService, durationOf,
    editingId, editingStylistId, filteredAppointments, filteredClients, form, formError,
    activeBranches, lockedBranchId, formBranch, termsFor, branchServices, convertWaitlistEntry, inventory, isAdmin, isClientView, isManagerView, isStylistView, joinWaitlist,
    landingContent, leaderboard, moderateReview, myClientRecord, newClientsToday, newFaq,
    newItem, newService, newStylist, occupancyRate, overviewPreview, priceOf,
    removeFaq, removeWaitlistEntry, reportRange, resetForm, revenueByService, revenueTrend,
    reviewDraft, reviewableAppointments, reviews, saveSalonInfo, saveSite, saveStylistEdit,
    savedMsg, scopedAppointments, selectedDate, services, setActiveTab, setClientSearch,
    setEditingStylistId, setForm, setFormError, setNewFaq, setNewItem, setNewService, setNewStylist,
    setReportRange, setReviewDraft, setSelectedDate, setShowForm, setSiteDraft, setStatusFilter,
    setStylistEditDraft, setStylistFilter, showForm, siteDraft, startEditStylist, startReschedule,
    statusBreakdown, statusFilter, stylistEditDraft, stylistFilter, stylists, submitAppointment,
    submitReview, todaysRevenue, toggleFeatured, toggleStylistActive, totalReportRevenue, updateServiceDuration,
    updateServicePhoto, updateServicePrice, user, waitlist, weeklyRevenue,
  };

  return (
    <div style={{ background: colors.bgApp, color: colors.ink }} className="min-h-screen flex">
      {sidebarOpen && <div onClick={() => setSidebarOpen(false)} className="fixed inset-0 bg-black/30 z-30 md:hidden" />}

      <aside style={{ background: colors.cardBg, borderRight: `1px solid ${colors.border}` }} className={`fixed md:static inset-y-0 left-0 z-40 w-60 flex-shrink-0 flex flex-col py-6 px-4 transform transition-transform md:translate-x-0 ${sidebarOpen ? "translate-x-0" : "-translate-x-full"}`}>
        <div className="px-2 mb-8 flex items-start justify-between">
          <div className="flex items-center gap-3">
            <div style={{ background: colors.wine, color: colors.cardBg, fontFamily: serifFont }} className="w-9 h-9 rounded-lg flex items-center justify-center text-lg font-semibold flex-shrink-0">{(brand.trim()[0] || "V").toUpperCase()}</div>
            <div>
              <div style={{ fontFamily: serifFont, color: colors.wine }} className="text-xl leading-tight">{brand}</div>
              <div className="dc-subtle text-[11px]">{isClientView ? "My account" : "Salon administration"}</div>
            </div>
          </div>
          <button onClick={() => setSidebarOpen(false)} aria-label="Close menu" className="dc-subtle md:hidden"><X size={18} /></button>
        </div>

        <nav className="flex flex-col gap-1 overflow-y-auto">
          {visibleNav.map((item, i) => {
            const Icon = item.icon;
            const active = activeTab === item.key;
            const showGroupLabel = item.group && item.group !== visibleNav[i - 1]?.group;
            return (
              <React.Fragment key={item.key}>
                {showGroupLabel && (
                  <div className="dc-subtle text-[10px] font-semibold uppercase tracking-wide px-3 pt-3 pb-1 first:pt-0">
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
                <div className="dc-subtle capitalize">{role}</div>
              </div>
            </div>
            <button onClick={onLogout} aria-label="Log out" title="Log out" style={{ color: colors.subtle }}><LogOut size={15} /></button>
          </div>
        </div>
      </aside>

      <main className="flex-1 min-w-0" style={{ background: colors.pageBg }}>
        <div style={{ borderBottom: `1px solid ${colors.border}`, background: colors.cardBg }} className="flex items-center justify-between px-4 md:px-8 py-4 gap-3">
          <div className="flex items-center gap-3 min-w-0">
            <button onClick={() => setSidebarOpen(true)} aria-label="Open menu" className="dc-subtle md:hidden"><Menu size={20} /></button>
            <div className="min-w-0">
              <div style={{ fontFamily: serifFont, color: colors.ink }} className="text-xl md:text-2xl leading-tight truncate">{pageTitle}</div>
              <div className="dc-subtle text-xs hidden sm:block">{isStylistView ? currentStylist?.role : isClientView ? "Your appointments and visit history" : formatDateLabel(selectedDate)}</div>
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
          {isClientView && !user.email_verified_at && (
            <div style={{ background: colors.warnSoft, color: colors.warn }} className="text-sm px-4 py-2.5 rounded-md mb-4 flex items-center justify-between gap-3 flex-wrap">
              <span>Verify your email to keep your account secure.</span>
              <button onClick={resendVerification} className="text-xs font-medium underline">Resend verification email</button>
            </div>
          )}
          {dashLoading && <div className="dc-subtle text-sm mb-4">Refreshing your data...</div>}


          {activeTab === "overview" && <OverviewTab ctx={ctx} />}
          {activeTab === "appointments" && <AppointmentsTab ctx={ctx} />}
          {activeTab === "clients" && isManagerView && <ClientsTab ctx={ctx} />}
          {activeTab === "stylists" && isManagerView && <StylistsTab ctx={ctx} />}
          {activeTab === "inventory" && isManagerView && <InventoryTab ctx={ctx} />}
          {activeTab === "reports" && isManagerView && <ReportsTab ctx={ctx} />}
          {activeTab === "reviews" && isManagerView && <ReviewsTab ctx={ctx} />}
          {activeTab === "branches" && isAdmin && <BranchesTab token={token} notify={notify} branches={branches} setBranches={setBranches} services={services} branchServices={branchServices} setBranchServices={setBranchServices} />}
          {activeTab === "staff" && isAdmin && <StaffTab token={token} notify={notify} askConfirm={askConfirm} branches={branches} />}
          {activeTab === "website" && isAdmin && <WebsiteTab ctx={ctx} />}
          {activeTab === "audit" && isAdmin && <AuditTab ctx={ctx} />}
          {activeTab === "settings" && <SettingsTab ctx={ctx} />}
        </div>
      </main>
    </div>
  );
}
