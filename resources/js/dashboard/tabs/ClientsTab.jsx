import { Search } from "lucide-react";
import { formatMoney, initials } from "../helpers.js";
import { useTheme } from "../theme.jsx";

export default function ClientsTab({ ctx }) {
  const { colors } = useTheme();
  const { clientSearch, filteredClients, setClientSearch } = ctx;
  return (
    <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl dash-card">
      <div style={{ borderBottom: `1px solid ${colors.border}` }} className="p-5">
        <div style={{ border: `1px solid ${colors.border}` }} className="flex items-center gap-2 px-3 py-2 rounded-md w-full md:w-72">
          <Search size={14} style={{ color: colors.subtle }} />
          <input value={clientSearch} onChange={(e) => setClientSearch(e.target.value)} placeholder="Search clients" aria-label="Search clients" style={{ background: "transparent", color: colors.ink }} className="outline-none text-sm flex-1" />
        </div>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr style={{ color: colors.subtle, borderBottom: `1px solid ${colors.border}` }} className="text-left">
              <th className="font-semibold py-3 px-5">Client</th>
              <th className="font-semibold py-3 px-5">Phone</th>
              <th className="font-semibold py-3 px-5">Visits</th>
              <th className="font-semibold py-3 px-5">Last visit</th>
              <th className="font-semibold py-3 px-5">Total spend</th>
            </tr>
          </thead>
          <tbody>
            {filteredClients.map((c) => (
              <tr key={c.id ?? c.name} style={{ borderBottom: `1px solid ${colors.border}` }}>
                <td className="py-3 px-5">
                  <div className="flex items-center gap-2">
                    <div style={{ background: colors.roseSoft, color: colors.wine }} className="w-7 h-7 rounded-full flex items-center justify-center text-xs font-medium">{initials(c.name)}</div>
                    {c.name}
                    {c.spend > 1000 && <span style={{ background: colors.wineSoft, color: colors.wine }} className="text-[10px] px-1.5 py-0.5 rounded-md font-medium">VIP</span>}
                  </div>
                </td>
                <td className="dc-subtle py-3 px-5">{c.phone}</td>
                <td className="py-3 px-5">{c.visits}</td>
                <td className="dc-subtle py-3 px-5">{c.lastVisit}</td>
                <td className="py-3 px-5 font-medium">{formatMoney(c.spend)}</td>
              </tr>
            ))}
            {filteredClients.length === 0 && <tr><td colSpan={5} className="dc-subtle text-center py-8">No clients found.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
