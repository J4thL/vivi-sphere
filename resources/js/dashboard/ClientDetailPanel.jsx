import { useEffect, useState } from "react";
import { CalendarPlus, Phone, X } from "lucide-react";
import { apiGet } from "./api.js";
import { STATUS_META, formatMoney, statusColor } from "./helpers.js";
import { serifFont, useTheme } from "./theme.jsx";

/* Side panel with one client's history. Clients are shared across branches, so every visit shows its branch. */
export default function ClientDetailPanel({ client, token, onClose, onBook }) {
  const { colors } = useTheme();
  const [data, setData] = useState(null);
  const [error, setError] = useState(false);

  useEffect(() => {
    let live = true;
    setData(null); setError(false);
    apiGet(`/clients/${client.id}`, token).then((r) => { if (!live) return; if (r?.client) setData(r); else setError(true); });
    return () => { live = false; };
  }, [client.id, token]);

  useEffect(() => {
    const onKey = (e) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  const card = { background: colors.cardBg, border: `1px solid ${colors.border}` };
  const stat = (label, value) => (
    <div style={{ border: `1px solid ${colors.border}` }} className="rounded-lg px-3 py-2">
      <div className="dc-subtle text-[11px]">{label}</div>
      <div className="text-sm font-medium truncate">{value ?? "-"}</div>
    </div>
  );
  const fmt = (dt) => new Date(dt.replace(" ", "T")).toLocaleString(undefined, { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" });
  const now = new Date().toISOString().slice(0, 10);
  const upcoming = (data?.visits || []).filter((v) => v.scheduled_at.slice(0, 10) >= now && (v.status === "confirmed" || v.status === "in-progress")).reverse();
  const history = (data?.visits || []).filter((v) => !upcoming.includes(v));

  return (
    <div className="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true" aria-label={`${client.name} details`}>
      <div onClick={onClose} className="absolute inset-0 bg-black/30" />
      <aside style={{ ...card, borderRadius: 0 }} className="relative w-full max-w-md h-full overflow-y-auto p-5 flex flex-col gap-5">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <div style={{ fontFamily: serifFont, color: colors.ink }} className="text-xl leading-tight truncate">{client.name}</div>
            <div className="dc-subtle text-xs">{client.phone !== "-" ? client.phone : "No phone"} · {client.visits} visit{client.visits === 1 ? "" : "s"} · {formatMoney(client.spend)}</div>
          </div>
          <button onClick={onClose} aria-label="Close" className="dc-subtle"><X size={18} /></button>
        </div>

        <div className="flex gap-2">
          <button onClick={() => onBook(client)} className="btn-wine flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium"><CalendarPlus size={14} /> Book</button>
          {client.phone !== "-" && <a href={`tel:${client.phone}`} style={{ border: `1px solid ${colors.border}`, color: colors.subtle }} className="flex items-center gap-1.5 px-3 py-2 rounded-md text-sm"><Phone size={14} /> Call</a>}
        </div>

        {!data && !error && <div className="dc-subtle text-sm">Loading history…</div>}
        {error && <div role="alert" className="dc-danger text-sm">Couldn't load this client. Close the panel and try again.</div>}

        {data && (
          <>
            <div className="grid grid-cols-2 gap-2">
              {stat("Favourite service", data.favourite_service)}
              {stat("Favourite stylist", data.favourite_stylist)}
              {stat("Average per visit", data.average_spend != null ? formatMoney(data.average_spend) : null)}
              {stat("Waitlist requests", data.waitlist.length)}
            </div>

            {upcoming.length > 0 && (
              <section>
                <div className="font-display text-base mb-2">Upcoming</div>
                <div className="flex flex-col gap-2">
                  {upcoming.map((v) => <div key={v.id} style={{ border: `1px solid ${colors.border}` }} className="rounded-md px-3 py-2 text-sm">{fmt(v.scheduled_at)} · {v.service} with {v.stylist}{v.branch ? ` · ${v.branch}` : ""}</div>)}
                </div>
              </section>
            )}

            {data.waitlist.length > 0 && (
              <section>
                <div className="font-display text-base mb-2">On the waitlist</div>
                {data.waitlist.map((w) => <div key={w.id} className="text-sm dc-subtle">{w.service} · wants {w.preferred_date}{w.stylist ? ` with ${w.stylist}` : ""}</div>)}
              </section>
            )}

            <section>
              <div className="font-display text-base mb-2">History</div>
              {history.length === 0 && <div className="dc-subtle text-sm">No past visits yet.</div>}
              <div className="flex flex-col">
                {history.map((v) => (
                  <div key={v.id} style={{ borderBottom: `1px solid ${colors.border}` }} className="py-2 text-sm flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <div className="truncate">{v.service} · {v.stylist}</div>
                      <div className="dc-subtle text-xs">{fmt(v.scheduled_at)}{v.branch ? ` · ${v.branch}` : ""}</div>
                    </div>
                    <div className="text-right flex-shrink-0">
                      <div>{formatMoney(v.price)}</div>
                      <div style={{ color: statusColor(v.status, colors) }} className="text-xs">{STATUS_META[v.status]?.label ?? v.status}</div>
                    </div>
                  </div>
                ))}
              </div>
              {data.visits.length >= 50 && <div className="dc-subtle text-xs mt-2">Showing the latest 50 visits.</div>}
            </section>

            {data.reviews.length > 0 && (
              <section>
                <div className="font-display text-base mb-2">Reviews</div>
                {data.reviews.map((r) => <div key={r.id} className="text-sm py-1">{r.rating}★ for {r.stylist}{r.comment ? ` · “${r.comment}”` : ""} <span className="dc-subtle text-xs">({r.status})</span></div>)}
              </section>
            )}
          </>
        )}
      </aside>
    </div>
  );
}
