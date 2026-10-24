import { useState } from "react";
import { apiSend } from "../api.js";
import { useTheme } from "../theme.jsx";
import { Field } from "../ui.jsx";
import BranchServicesCard from "./BranchServicesCard.jsx";

const EMPTY = { name: "", address: "", phone: "", open_time: "09:00", close_time: "19:00" };

/* Admin-only: add and edit the salon's branches. Branches are switched off, never deleted. */
export default function BranchesTab({ token, notify, branches, setBranches, services, branchServices, setBranchServices }) {
  const { colors } = useTheme();
  const [form, setForm] = useState(EMPTY);
  const [editingId, setEditingId] = useState(null);
  const [saving, setSaving] = useState(false);
  const input = { border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink };

  const startEdit = (b) => { setEditingId(b.id); setForm({ name: b.name, address: b.address || "", phone: b.phone || "", open_time: b.open_time, close_time: b.close_time }); };
  const reset = () => { setEditingId(null); setForm(EMPTY); };

  const save = async (e) => {
    e.preventDefault();
    if (saving) return;
    setSaving(true);
    const body = { ...form, name: form.name.trim(), address: form.address.trim() || null, phone: form.phone.trim() || null, map_query: form.address.trim() || null };
    const r = editingId ? await apiSend(`/branches/${editingId}`, "PATCH", body, token) : await apiSend("/branches", "POST", body, token);
    setSaving(false);
    if (!r || r.error) { notify(r?.message || "Could not reach the server.", "error"); return; }
    setBranches((prev) => (editingId ? prev.map((b) => (b.id === r.id ? r : b)) : [...prev, r]).sort((a, b) => a.name.localeCompare(b.name)));
    notify(editingId ? "Branch updated." : "Branch added.");
    reset();
  };

  const toggleActive = async (b) => {
    const r = await apiSend(`/branches/${b.id}`, "PATCH", { active: !b.active }, token);
    if (!r || r.error) { notify(r?.message || "Could not reach the server.", "error"); return; }
    setBranches((prev) => prev.map((x) => (x.id === r.id ? r : x)));
    notify(r.active ? `${r.name} is open for bookings again.` : `${r.name} is switched off.`);
  };

  return (
    <div className="flex flex-col gap-4">
      <form onSubmit={save} style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
        <div className="font-display text-lg mb-1">{editingId ? "Edit branch" : "Add a branch"}</div>
        <div className="dc-subtle text-xs mb-4">Each branch has its own staff, bookings and stock. Branches can be switched off but not deleted, so history is kept.</div>
        <div className="flex items-end gap-3 flex-wrap">
          <Field label="Branch name"><input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm w-48" placeholder="e.g. Makati" /></Field>
          <Field label="Address"><input value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm w-64" /></Field>
          <Field label="Phone"><input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm w-36" /></Field>
          <Field label="Opens"><input type="time" required value={form.open_time} onChange={(e) => setForm({ ...form, open_time: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm" /></Field>
          <Field label="Closes"><input type="time" required value={form.close_time} onChange={(e) => setForm({ ...form, close_time: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm" /></Field>
          <button type="submit" disabled={saving} className="btn-wine px-4 py-2 rounded-md text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed">{saving ? "Saving…" : editingId ? "Save branch" : "Add branch"}</button>
          {editingId && <button type="button" onClick={reset} className="dc-subtle text-xs px-2">Cancel</button>}
        </div>
      </form>

      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl dash-card">
        {branches.length === 0 && <div className="dc-subtle text-sm text-center py-8">No branches yet.</div>}
        {branches.map((b) => (
          <div key={b.id} style={{ borderBottom: `1px solid ${colors.border}`, opacity: b.active ? 1 : 0.6 }} className="flex items-center justify-between px-4 py-3 text-sm gap-2">
            <div className="min-w-0">
              <div className="font-medium truncate">{b.name}{!b.active && " (off)"}</div>
              <div className="dc-subtle text-xs truncate">{[b.address, b.phone, `${b.open_time}–${b.close_time}`].filter(Boolean).join(" · ")}</div>
            </div>
            <div className="flex items-center gap-3 flex-shrink-0 text-xs font-medium">
              <button type="button" onClick={() => startEdit(b)} className="underline dc-subtle">Edit</button>
              <button type="button" onClick={() => toggleActive(b)} className="underline" style={{ color: b.active ? colors.danger : colors.success }}>{b.active ? "Switch off" : "Switch on"}</button>
            </div>
          </div>
        ))}
      </div>

      <BranchServicesCard token={token} notify={notify} branches={branches} services={services} branchServices={branchServices} setBranchServices={setBranchServices} />
    </div>
  );
}
