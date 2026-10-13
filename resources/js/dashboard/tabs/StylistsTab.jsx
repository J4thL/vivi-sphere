import { Pencil, ToggleLeft, ToggleRight } from "lucide-react";
import { initials } from "../helpers.js";
import { serifFont, useTheme } from "../theme.jsx";
import { Field, IconBtn } from "../ui.jsx";

export default function StylistsTab({ ctx }) {
  const { colors } = useTheme();
  const { addStylist, busy, appointments, editingStylistId, leaderboard, newStylist, saveStylistEdit, setEditingStylistId, setNewStylist, setStylistEditDraft, startEditStylist, stylistEditDraft, stylists, toggleStylistActive } = ctx;
  return (
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
          <button onClick={addStylist} disabled={busy.stylist} className="btn-wine px-4 py-2 rounded-md text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed">{busy.stylist ? "Adding…" : "Add stylist"}</button>
        </div>
      </div>
    </div>
  );
}
