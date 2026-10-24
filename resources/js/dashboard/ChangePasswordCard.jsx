import { useState } from "react";
import { apiSend } from "./api.js";
import { useTheme } from "./theme.jsx";
import { Field } from "./ui.jsx";

/* Every signed-in role can replace their own password (staff start on a temporary one). */
export default function ChangePasswordCard({ token, notify, onChanged, intro }) {
  const { colors } = useTheme();
  const empty = { current: "", next: "", confirm: "" };
  const [f, setF] = useState(empty);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const input = { border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink };
  const submit = async (e) => {
    e.preventDefault();
    setError("");
    if (f.next.length < 8) { setError("New password must be at least 8 characters."); return; }
    if (f.next !== f.confirm) { setError("The new passwords don't match."); return; }
    setSaving(true);
    const r = await apiSend("/auth/change-password", "POST", { current_password: f.current, password: f.next, password_confirmation: f.confirm }, token);
    setSaving(false);
    if (!r || r.error) { setError(r?.message || "Could not reach the server."); return; }
    setF(empty);
    notify("Password updated.");
    onChanged?.(r.user);
  };
  return (
    <form onSubmit={submit} style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
      <div className="font-display text-lg mb-1">Change password</div>
      <div className="dc-subtle text-xs mb-4">{intro || "Other devices will be signed out. If you were given a temporary password, replace it here."}</div>
      <div className="grid sm:grid-cols-3 gap-4">
        <Field label="Current password"><input type="password" autoComplete="current-password" required value={f.current} onChange={(e) => setF({ ...f, current: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
        <Field label="New password"><input type="password" autoComplete="new-password" required value={f.next} onChange={(e) => setF({ ...f, next: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
        <Field label="Confirm new password"><input type="password" autoComplete="new-password" required value={f.confirm} onChange={(e) => setF({ ...f, confirm: e.target.value })} style={input} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
      </div>
      {error && <div role="alert" className="dc-danger text-xs mt-3">{error}</div>}
      <button type="submit" disabled={saving} className="btn-wine px-4 py-2 rounded-md text-sm font-medium mt-4 disabled:opacity-60 disabled:cursor-not-allowed">{saving ? "Saving…" : "Update password"}</button>
    </form>
  );
}
