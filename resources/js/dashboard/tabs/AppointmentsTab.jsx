import { CalendarDays, Check, ChevronLeft, ChevronRight, Pencil, Play, Plus, X } from "lucide-react";
import { STATUS_META, addDays, formatMoney, initials, statusColor, todayStr } from "../helpers.js";
import { useTheme } from "../theme.jsx";
import { Field, IconBtn } from "../ui.jsx";

export default function AppointmentsTab({ ctx }) {
  const { colors } = useTheme();
  const { advanceStatus, busy, cancelAppointment, clients, durationOf, editingId, filteredAppointments, form, formError, isClientView, isManagerView, isStylistView, joinWaitlist, landingContent, priceOf, removeWaitlistEntry, resetForm, setFormError, selectedDate, services, setForm, setSelectedDate, setShowForm, setStatusFilter, setStylistFilter, showForm, startReschedule, statusFilter, stylistFilter, stylists, submitAppointment, waitlist } = ctx;
  return (
    <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl dash-card">
      {(isManagerView || isStylistView) && (
        <div style={{ borderBottom: `1px solid ${colors.border}` }} className="flex items-center gap-2 p-4 flex-wrap">
          <button onClick={() => setSelectedDate((d) => addDays(d, -1))} aria-label="Previous day" style={{ border: `1px solid ${colors.border}` }} className="icon-btn w-8 h-8 rounded-md flex items-center justify-center"><ChevronLeft size={14} style={{ color: colors.subtle }} /></button>
          <input type="date" aria-label="Appointment date" value={selectedDate} onChange={(e) => setSelectedDate(e.target.value)} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="text-sm px-2 py-1.5 rounded-md" />
          <button onClick={() => setSelectedDate((d) => addDays(d, 1))} aria-label="Next day" style={{ border: `1px solid ${colors.border}` }} className="icon-btn w-8 h-8 rounded-md flex items-center justify-center"><ChevronRight size={14} style={{ color: colors.subtle }} /></button>
          <button onClick={() => setSelectedDate(todayStr(0))} className="dc-wine text-xs font-medium px-2">Today</button>
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
                <input value={form.client} onChange={(e) => setForm({ ...form, client: e.target.value, clientId: null, newClient: false })} disabled={!!editingId} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-40" placeholder="Jane Avery" />
              </Field>
            )}
            {!isClientView && !editingId && isManagerView && !form.clientId && form.client.trim().length >= 2 && (() => {
              const q = form.client.trim().toLowerCase();
              const matches = clients.filter((c) => c.id && c.name.toLowerCase().includes(q)).slice(0, 5);
              return matches.length > 0 ? (
                <div className="flex flex-col gap-1" aria-label="Matching clients">
                  <span className="dc-subtle text-xs">Existing clients - pick one to book under their record</span>
                  <div className="flex gap-1.5 flex-wrap">
                    {matches.map((c) => (
                      <button key={c.id} type="button" onClick={() => setForm({ ...form, client: c.name, clientId: c.id, newClient: false })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="text-xs px-2 py-1 rounded-md">
                        {c.name} · {c.visits} visit{c.visits === 1 ? "" : "s"}{c.phone && c.phone !== "-" ? ` · ${c.phone}` : ""}
                      </button>
                    ))}
                  </div>
                </div>
              ) : null;
            })()}
            {!isClientView && form.clientId && (
              <div className="dc-wine text-xs flex items-center gap-2 pb-2">Booking under existing client <strong>{form.client}</strong>
                <button type="button" onClick={() => setForm({ ...form, clientId: null })} className="dc-subtle underline">change</button>
              </div>
            )}
            <Field label="Service">
              <select value={form.service} onChange={(e) => setForm({ ...form, service: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-3 py-2 rounded-lg text-sm">
                {Object.keys(services).map((s) => <option key={s} value={s}>{s} ({services[s].duration}m)</option>)}
              </select>
            </Field>
            <Field label="Stylist">
              <select value={form.stylist} onChange={(e) => setForm({ ...form, stylist: e.target.value })} disabled={isStylistView} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-3 py-2 rounded-lg text-sm">
                {!editingId && <option>No preference</option>}
                {stylists.filter((s) => s.active).map((s) => <option key={s.id}>{s.name}</option>)}
              </select>
            </Field>
            <Field label="Date">
              <input type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} min={todayStr(0)} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm" />
            </Field>
            <Field label="Time">
              <input type="time" value={form.time} onChange={(e) => setForm({ ...form, time: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm" />
            </Field>
            <button onClick={submitAppointment} disabled={busy.appointment} className="btn-wine px-4 py-2 rounded-md text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed">{busy.appointment ? "Saving…" : editingId ? "Save changes" : "Save"}</button>
            <button onClick={resetForm} className="dc-subtle px-2 py-2 text-sm">Cancel</button>
          </div>
          {formError && (
            <div role="alert" className="flex items-center gap-3 flex-wrap">
              <span className="dc-danger text-xs">{formError}</span>
              {formError.includes("already exists") && <button onClick={() => { setForm({ ...form, newClient: true }); setFormError(""); }} className="dc-wine text-xs font-medium underline">Book as a new client with this name</button>}
              {formError.includes("booked over this time") && <button onClick={joinWaitlist} className="dc-wine text-xs font-medium underline">Join waitlist instead</button>}
            </div>
          )}
          {isClientView && <div className="dc-subtle text-xs">{landingContent.cancellationPolicy}</div>}
        </div>
      )}

      <div className="p-2">
        {filteredAppointments.length === 0 && <div className="dc-subtle text-sm text-center py-14 flex flex-col items-center gap-2"><CalendarDays size={28} style={{ color: colors.rose }} />No appointments match these filters.</div>}
        {filteredAppointments.map((a) => (
          <div key={a.id} style={{ borderBottom: `1px solid ${colors.border}` }} className="flex items-center justify-between px-3 py-3 text-sm gap-2">
            <div className="flex items-center gap-3 min-w-0">
              <span className="dc-subtle w-16 flex-shrink-0 text-xs">{isClientView || !isStylistView ? a.date.slice(5) : ""} {a.time}</span>
              <div style={{ background: colors.roseSoft, color: colors.wine }} className="w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium flex-shrink-0">{initials(isClientView ? a.service : a.client)}</div>
              <div className="min-w-0">
                <div className="font-medium truncate">{isClientView ? a.service : a.client}</div>
                <div className="dc-subtle text-xs truncate">{isClientView ? `with ${a.stylist}` : `${a.service} · ${a.stylist}`} · {formatMoney(a.price ?? priceOf(a.service))} · {(a.duration ?? durationOf(a.service))}m</div>
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
          <div className="font-display text-base mb-3">Waitlist</div>
          <div className="flex flex-col gap-2">
            {waitlist.map((w) => (
              <div key={w.id} style={{ border: `1px solid ${colors.border}` }} className="flex items-center justify-between px-3 py-2 rounded-md text-sm">
                <span>{w.clientName} &middot; {w.service} &middot; wants {w.preferredDate}{w.stylist ? ` with ${w.stylist}` : ""}</span>
                <div className="flex items-center gap-2">
                  <button onClick={() => { setForm({ client: w.clientName, service: w.service, stylist: w.stylist || "No preference", date: w.preferredDate, time: "12:00" }); setShowForm(true); }} className="dc-wine text-xs font-medium">Convert</button>
                  <IconBtn onClick={() => removeWaitlistEntry(w.id)} title="Remove" icon={X} color={colors.danger} />
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
