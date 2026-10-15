import { X } from "lucide-react";
import { useTheme } from "../theme.jsx";
import { Field, IconBtn, ThemeToggle } from "../ui.jsx";

// Number inputs here save when the field loses focus (or Enter is pressed), not on every keystroke.
// An empty or unchanged value just snaps back to what is saved.
const blurOnEnter = (e) => { if (e.key === "Enter") e.currentTarget.blur(); };
const commitIfChanged = (e, current, save) => {
  const next = e.target.value;
  if (next === "" || Number(next) === current) { e.target.value = current; return; }
  save(next);
};

export default function SettingsTab({ ctx }) {
  const { colors } = useTheme();
  const { addService, busy, currentStylist, deleteService, isAdmin, isClientView, isManagerView, isStylistView, newService, saveSalonInfo, savedMsg, services, setNewService, setSiteDraft, siteDraft, updateServiceDuration, updateServicePrice, user } = ctx;
  return (
    <div className="flex flex-col gap-4 max-w-2xl">
      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
        <div className="font-display text-lg mb-4">{isClientView ? "My profile" : isStylistView ? "My profile" : "Salon profile"}</div>
        {isAdmin && (
          <div className="grid sm:grid-cols-2 gap-4">
            <Field label="Salon name"><input value={siteDraft.salonName} onChange={(e) => setSiteDraft({ ...siteDraft, salonName: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
            <Field label="Phone"><input value={siteDraft.contactPhone} onChange={(e) => setSiteDraft({ ...siteDraft, contactPhone: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
            <Field label="Address"><input value={siteDraft.contactAddress} onChange={(e) => setSiteDraft({ ...siteDraft, contactAddress: e.target.value, mapQuery: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
          </div>
        )}
        {isManagerView && !isAdmin && <div className="dc-subtle text-sm">Salon profile and pricing are managed by an admin. You can still manage appointments, clients, stylists, and inventory.</div>}
        {isStylistView && <div className="dc-subtle text-sm">{currentStylist?.name} · {currentStylist?.role}. Availability is managed by the front desk.</div>}
        {isClientView && (
          <div className="grid sm:grid-cols-2 gap-4">
            <Field label="Name"><input value={user.name} disabled style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full opacity-70" /></Field>
            <Field label="Email"><input value={user.email} disabled style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full opacity-70" /></Field>
          </div>
        )}
        {isAdmin && (
          <div className="flex items-center gap-3 mt-4">
            <button onClick={saveSalonInfo} className="btn-wine px-4 py-2 rounded-md text-sm font-medium">Save changes</button>
            {savedMsg && <span className="dc-success text-xs font-medium">{savedMsg}</span>}
          </div>
        )}
      </div>

      <div className="flex items-center justify-between p-4 rounded-lg" style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }}>
        <div>
          <div className="text-sm font-medium">Appearance</div>
          <div className="dc-subtle text-xs">Switch between light and dark mode.</div>
        </div>
        <ThemeToggle />
      </div>

      {isAdmin && (
        <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
          <div className="font-display text-lg mb-1">Manage services</div>
          <div className="dc-subtle text-xs mb-4">Add, reprice, or remove what the salon offers. Names can't be edited once created, since past appointments reference them by name.</div>
          <div className="flex flex-col gap-2 mb-4">
            {Object.entries(services).map(([name, info]) => (
              <div key={name} style={{ border: `1px solid ${colors.border}` }} className="flex items-center gap-2 p-2 rounded-md flex-wrap">
                <span className="flex-1 min-w-[140px] text-sm font-medium truncate">{name}</span>
                <div className="flex items-center gap-1">
                  <span className="dc-subtle text-xs">₱</span>
                  <input type="number" aria-label={`${name} price`} key={`price-${info.price}`} defaultValue={info.price} onBlur={(e) => commitIfChanged(e, info.price, (v) => updateServicePrice(name, v))} onKeyDown={blurOnEnter} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="w-16 px-2 py-1 rounded-md text-sm" />
                </div>
                <div className="flex items-center gap-1">
                  <input type="number" aria-label={`${name} duration in minutes`} key={`duration-${info.duration}`} defaultValue={info.duration} onBlur={(e) => commitIfChanged(e, info.duration, (v) => updateServiceDuration(name, v))} onKeyDown={blurOnEnter} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="w-14 px-2 py-1 rounded-md text-sm" />
                  <span className="dc-subtle text-xs">min</span>
                </div>
                <IconBtn onClick={() => deleteService(name)} title="Delete service" icon={X} color={colors.danger} />
              </div>
            ))}
            {Object.keys(services).length === 0 && <div className="dc-subtle text-sm">No services yet - add one below.</div>}
          </div>
          <div style={{ borderTop: `1px solid ${colors.border}` }} className="pt-4 flex items-end gap-3 flex-wrap">
            <Field label="New service name"><input value={newService.name} onChange={(e) => setNewService({ ...newService, name: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-44" placeholder="e.g. Scalp Treatment" /></Field>
            <Field label="Price"><input type="number" value={newService.price} onChange={(e) => setNewService({ ...newService, price: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-24" placeholder="65" /></Field>
            <Field label="Duration (min)"><input type="number" value={newService.duration} onChange={(e) => setNewService({ ...newService, duration: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-28" placeholder="45" /></Field>
            <button onClick={addService} disabled={busy.service} className="btn-wine px-4 py-2 rounded-md text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed">{busy.service ? "Adding…" : "Add service"}</button>
          </div>
        </div>
      )}
    </div>
  );
}
