import { useEffect, useState } from "react";
import { apiGet, apiSend } from "../api.js";
import { useTheme } from "../theme.jsx";
import { Field } from "../ui.jsx";

const EMPTY = { name: "", email: "", role: "stylist", title: "" };

/* Admin-only: create manager and stylist logins, and see who already has one. */
export default function StaffTab({ token, notify }) {
  const { colors } = useTheme();
  const [staff, setStaff] = useState(null);
  const [form, setForm] = useState(EMPTY);
  const [saving, setSaving] = useState(false);
  const [created, setCreated] = useState(null); // shown once: {name, email, password}

  const [reload, setReload] = useState(0);
  useEffect(() => {
    let live = true;
    apiGet("/staff", token).then((r) => { if (live) setStaff(r || []); });
    return () => { live = false; };
  }, [token, reload]);

  const input = { border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink };

  const submit = async (e) => {
    e.preventDefault();
    if (saving) return;
    setSaving(true);
    const body = { name: form.name.trim(), email: form.email.trim(), role: form.role, title: form.role === "stylist" ? form.title.trim() : null };
    const result = await apiSend("/staff", "POST", body, token);
    setSaving(false);
    if (!result || result.error) { notify(result?.message || "Could not reach the server.", "error"); return; }
    setCreated({ name: result.user.name, email: result.user.email, password: result.temp_password });
    setForm(EMPTY);
    notify("Staff account created.");
    setReload((n) => n + 1);
  };

  return (
    <div className="flex flex-col gap-4">
      <form onSubmit={submit} style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
        <div className="font-display text-lg mb-1">Create staff account</div>
        <div className="dc-subtle text-xs mb-4">Staff can't sign up on their own. You create the login and give them the temporary password.</div>
        {created && (
          <div role="status" style={{ background: colors.roseSoft, border: `1px solid ${colors.wine}` }} className="rounded-lg p-3 mb-4 text-sm">
            <div className="font-medium">Account created for {created.name}</div>
            <div>Email: <span className="font-mono">{created.email}</span></div>
            <div>Temporary password: <span className="font-mono select-all">{created.password}</span></div>
            <div className="dc-subtle text-xs mt-1">Shown only once. Share it privately.</div>
            <button type="button" onClick={() => setCreated(null)} className="dc-subtle text-xs mt-2 underline">Dismiss</button>
          </div>
        )}
        <div className="flex items-end gap-3 flex-wrap">
          <Field label="Full name"><input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm w-48" /></Field>
          <Field label="Login email"><input required type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm w-56" placeholder="name@example.com" /></Field>
          <Field label="Role">
            <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm">
              <option value="stylist">Stylist</option>
              <option value="manager">Manager</option>
            </select>
          </Field>
          {form.role === "stylist" && <Field label="Job title"><input required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm w-40" placeholder="e.g. Colorist" /></Field>}
          <button type="submit" disabled={saving} className="btn-wine px-4 py-2 rounded-md text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed">{saving ? "Creating…" : "Create account"}</button>
        </div>
      </form>

      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl dash-card">
        {staff === null && <div className="dc-subtle text-sm text-center py-8">Loading…</div>}
        {staff && staff.length === 0 && <div className="dc-subtle text-sm text-center py-8">No staff accounts yet.</div>}
        {staff && staff.map((u) => (
          <div key={u.id} style={{ borderBottom: `1px solid ${colors.border}` }} className="flex items-center justify-between px-4 py-3 text-sm gap-2">
            <div className="min-w-0">
              <div className="font-medium truncate">{u.name}</div>
              <div className="dc-subtle text-xs truncate">{u.email}</div>
            </div>
            <div className="dc-subtle text-xs capitalize flex-shrink-0">{u.role}{u.stylist?.role ? ` · ${u.stylist.role}` : ""}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
