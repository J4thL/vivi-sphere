import { useState } from "react";
import { CalendarPlus, Phone, Search } from "lucide-react";
import { LAPSED_DAYS, VIP_SPEND, daysSince, formatMoney, initials } from "../helpers.js";
import { useTheme } from "../theme.jsx";

const FILTERS = [
  { key: "all", label: "All", test: () => true },
  { key: "vip", label: "VIP", test: (c) => c.spend > VIP_SPEND },
  { key: "new", label: "New", test: (c) => c.visits === 0 },
  { key: "lapsed", label: `Lapsed ${LAPSED_DAYS}+ days`, test: (c) => c.visits > 0 && (daysSince(c.lastVisitAt) ?? 0) >= LAPSED_DAYS },
];
const SORTS = [
  { key: "spend", label: "Highest spend", cmp: (a, b) => b.spend - a.spend },
  { key: "visits", label: "Most visits", cmp: (a, b) => b.visits - a.visits },
  { key: "recent", label: "Most recent visit", cmp: (a, b) => (b.lastVisitAt || "").localeCompare(a.lastVisitAt || "") },
  { key: "name", label: "Name A–Z", cmp: (a, b) => a.name.localeCompare(b.name) },
];
const exactDate = (iso) => (iso ? new Date(iso).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) : "");

export default function ClientsTab({ ctx }) {
  const { colors } = useTheme();
  const { bookForClient, clientSearch, clients, filteredClients, setClientSearch } = ctx;
  const [filter, setFilter] = useState("all");
  const [sort, setSort] = useState("spend");
  const rows = filteredClients.filter(FILTERS.find((f) => f.key === filter).test).sort(SORTS.find((s) => s.key === sort).cmp);
  const hasPhone = (c) => c.phone && c.phone !== "-";
  const vipBadge = (c) => c.spend > VIP_SPEND && <span style={{ background: colors.wineSoft, color: colors.wine }} className="text-[10px] px-1.5 py-0.5 rounded-md font-medium">VIP</span>;
  const actions = (c) => (
    <div className="flex items-center gap-1 justify-end">
      {hasPhone(c) && <a href={`tel:${c.phone}`} aria-label={`Call ${c.name}`} title="Call" style={{ border: `1px solid ${colors.border}`, color: colors.subtle }} className="icon-btn w-8 h-8 rounded-md flex items-center justify-center"><Phone size={14} /></a>}
      {c.id && <button onClick={() => bookForClient(c)} aria-label={`Book ${c.name}`} title="Book appointment" style={{ border: `1px solid ${colors.border}`, color: colors.wine }} className="icon-btn w-8 h-8 rounded-md flex items-center justify-center"><CalendarPlus size={14} /></button>}
    </div>
  );
  return (
    <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl dash-card">
      <div style={{ borderBottom: `1px solid ${colors.border}` }} className="p-5 flex flex-col gap-3">
        <div className="flex items-center gap-3 flex-wrap">
          <div style={{ border: `1px solid ${colors.border}` }} className="flex items-center gap-2 px-3 py-2 rounded-md w-full md:w-72">
            <Search size={14} style={{ color: colors.subtle }} />
            <input value={clientSearch} onChange={(e) => setClientSearch(e.target.value)} placeholder="Search clients" aria-label="Search clients" style={{ background: "transparent", color: colors.ink }} className="outline-none text-sm flex-1" />
          </div>
          <select aria-label="Sort clients" value={sort} onChange={(e) => setSort(e.target.value)} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="text-sm px-3 py-2 rounded-lg">
            {SORTS.map((s) => <option key={s.key} value={s.key}>{s.label}</option>)}
          </select>
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          {FILTERS.map((f) => (
            <button key={f.key} onClick={() => setFilter(f.key)} aria-pressed={filter === f.key} style={{ border: `1px solid ${filter === f.key ? colors.wine : colors.border}`, color: filter === f.key ? colors.wine : colors.subtle, background: filter === f.key ? colors.wineSoft : "transparent" }} className="text-xs px-2.5 py-1.5 rounded-md">{f.label}</button>
          ))}
          <span className="dc-subtle text-xs ml-auto">Showing {rows.length} of {clients.length}</span>
        </div>
      </div>

      {/* Phones and tablets: one card per client instead of a wide scrolling table. */}
      <div className="md:hidden">
        {rows.map((c) => (
          <div key={c.id ?? c.name} style={{ borderBottom: `1px solid ${colors.border}` }} className="p-4 flex items-start justify-between gap-3">
            <div className="min-w-0">
              <div className="flex items-center gap-2 text-sm font-medium"><span className="truncate">{c.name}</span>{vipBadge(c)}</div>
              <div className="dc-subtle text-xs mt-0.5">{hasPhone(c) ? c.phone : "No phone"}</div>
              <div className="text-xs mt-1">{c.visits} visit{c.visits === 1 ? "" : "s"} · {formatMoney(c.spend)} · <span className="dc-subtle" title={exactDate(c.lastVisitAt)}>{c.lastVisit}</span></div>
            </div>
            {actions(c)}
          </div>
        ))}
        {rows.length === 0 && <div className="dc-subtle text-center text-sm py-8">No clients found.</div>}
      </div>

      <div className="overflow-x-auto hidden md:block">
        <table className="w-full text-sm">
          <thead>
            <tr style={{ color: colors.subtle, borderBottom: `1px solid ${colors.border}` }} className="text-left">
              <th className="font-semibold py-3 px-5">Client</th>
              <th className="font-semibold py-3 px-5">Phone</th>
              <th className="font-semibold py-3 px-5">Visits</th>
              <th className="font-semibold py-3 px-5">Last visit</th>
              <th className="font-semibold py-3 px-5">Total spend</th>
              <th className="py-3 px-5"><span className="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            {rows.map((c) => (
              <tr key={c.id ?? c.name} style={{ borderBottom: `1px solid ${colors.border}` }}>
                <td className="py-3 px-5">
                  <div className="flex items-center gap-2">
                    <div style={{ background: colors.roseSoft, color: colors.wine }} className="w-7 h-7 rounded-full flex items-center justify-center text-xs font-medium">{initials(c.name)}</div>
                    {c.name}
                    {vipBadge(c)}
                  </div>
                </td>
                <td className="dc-subtle py-3 px-5">{c.phone}</td>
                <td className="py-3 px-5">{c.visits}</td>
                <td className="dc-subtle py-3 px-5" title={exactDate(c.lastVisitAt)}>{c.lastVisit}{c.lastVisitAt ? ` · ${exactDate(c.lastVisitAt)}` : ""}</td>
                <td className="py-3 px-5 font-medium">{formatMoney(c.spend)}</td>
                <td className="py-3 px-5">{actions(c)}</td>
              </tr>
            ))}
            {rows.length === 0 && <tr><td colSpan={6} className="dc-subtle text-center py-8">No clients found.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
