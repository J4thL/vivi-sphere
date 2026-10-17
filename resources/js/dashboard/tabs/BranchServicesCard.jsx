import { useEffect, useState } from "react";
import { apiSend } from "../api.js";
import { formatMoney } from "../helpers.js";
import { useTheme } from "../theme.jsx";
import { Field } from "../ui.jsx";

/* Admin-only: give one branch its own price, length or availability for a service.
 * An empty box means "use the normal value", which is shown as the placeholder. */
export default function BranchServicesCard({ token, notify, branches, services, branchServices, setBranchServices }) {
  const { colors } = useTheme();
  const [branchId, setBranchId] = useState(branches[0]?.id ?? "");
  useEffect(() => { if (!branchId && branches[0]) setBranchId(branches[0].id); }, [branches, branchId]);
  const input = { border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink };

  const rows = Object.entries(services).map(([name, info]) => ({ name, info, o: branchServices.find((x) => x.branch_id === Number(branchId) && x.service_id === info.id) }));

  const save = async (info, body) => {
    const r = await apiSend(`/branches/${branchId}/services/${info.id}`, "PUT", body, token);
    if (!r || r.error) { notify(r?.message || "Could not reach the server.", "error"); return; }
    const isDefault = r.price == null && r.duration_minutes == null && r.available;
    setBranchServices((prev) => {
      const rest = prev.filter((x) => !(x.branch_id === r.branch_id && x.service_id === r.service_id));
      return isDefault ? rest : [...rest, r];
    });
    notify("Saved.");
  };

  // Saves a number box when it loses focus; empty clears it back to the normal value.
  const commit = (info, field, raw, current) => {
    const next = raw.trim() === "" ? null : Number(raw);
    if (next !== null && (Number.isNaN(next) || next < 0)) { notify("Please enter a valid number.", "error"); return; }
    if (next === (current ?? null)) return;
    save(info, { [field]: next });
  };

  return (
    <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
      <div className="font-display text-lg mb-1">Services &amp; prices by branch</div>
      <div className="dc-subtle text-xs mb-4">Leave a box empty to use the normal price or length. Past bookings keep the price they were booked at.</div>
      <Field label="Branch">
        <select value={branchId} onChange={(e) => setBranchId(e.target.value)} style={input} className="px-2 py-1.5 rounded-md text-sm w-56 mb-4">
          {branches.map((b) => <option key={b.id} value={b.id}>{b.name}{b.active ? "" : " (off)"}</option>)}
        </select>
      </Field>
      {branches.length === 0 && <div className="dc-subtle text-sm">Add a branch first.</div>}
      {branchId && (
        <div className="flex flex-col gap-2">
          {rows.map(({ name, info, o }) => (
            <div key={`${branchId}-${name}`} style={{ border: `1px solid ${colors.border}`, opacity: o && !o.available ? 0.6 : 1 }} className="flex items-center gap-3 p-2 rounded-md flex-wrap">
              <span className="flex-1 min-w-[140px] text-sm font-medium truncate">{name}</span>
              <label className="flex items-center gap-1 text-xs dc-subtle">Price
                <input type="number" min="0" step="0.01" defaultValue={o?.price ?? ""} placeholder={String(info.price)} onBlur={(e) => commit(info, "price", e.target.value, o?.price)} onKeyDown={(e) => e.key === "Enter" && e.currentTarget.blur()} style={input} className="w-24 px-2 py-1 rounded-md text-sm" aria-label={`${name} price at this branch (normal ${formatMoney(info.price)})`} />
              </label>
              <label className="flex items-center gap-1 text-xs dc-subtle">Minutes
                <input type="number" min="5" step="5" defaultValue={o?.duration_minutes ?? ""} placeholder={String(info.duration)} onBlur={(e) => commit(info, "duration_minutes", e.target.value, o?.duration_minutes)} onKeyDown={(e) => e.key === "Enter" && e.currentTarget.blur()} style={input} className="w-20 px-2 py-1 rounded-md text-sm" aria-label={`${name} length at this branch (normal ${info.duration} minutes)`} />
              </label>
              <label className="flex items-center gap-1 text-xs font-medium">
                <input type="checkbox" checked={o ? o.available : true} onChange={(e) => save(info, { available: e.target.checked })} />
                Offered
              </label>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
