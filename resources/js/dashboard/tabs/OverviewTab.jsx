import { Suspense } from "react";
import { Star } from "lucide-react";
import { STATUS_META, statusColor } from "../helpers.js";
import { ChartPlaceholder, StatusPieChart, WeeklyRevenueChart } from "../lazyCharts.jsx";
import { serifFont, useTheme } from "../theme.jsx";
import { StatCard } from "../ui.jsx";

export default function OverviewTab({ ctx }) {
  const { colors } = useTheme();
  const { activeCount, countTrend, currentStylist, isClientView, isManagerView, isStylistView, leaderboard, myClientRecord, newClientsToday, occupancyRate, overviewPreview, resetForm, revenueTrend, reviewDraft, reviewableAppointments, scopedAppointments, setActiveTab, setReviewDraft, setShowForm, statusBreakdown, submitReview, todaysRevenue, weeklyRevenue } = ctx;
  return (
    <>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <StatCard label={isClientView ? "Total spent" : isStylistView ? "My revenue today" : "Today's revenue"} value={isClientView ? `$${(myClientRecord?.spend || 0).toLocaleString()}` : `$${todaysRevenue.toLocaleString()}`} sub={isClientView ? "lifetime with us" : "vs. yesterday"} trend={isClientView ? null : revenueTrend} />
        <StatCard label={isClientView ? "Visits" : isStylistView ? "My appointments" : "Appointments today"} value={isClientView ? (myClientRecord?.visits || 0) : activeCount} sub={isClientView ? "all time" : "vs. yesterday"} trend={isClientView ? null : countTrend} />
        {isClientView && <StatCard label="Upcoming" value={scopedAppointments.filter((a) => a.status === "confirmed").length} sub="confirmed bookings" />}
        {!isClientView && !isStylistView && <StatCard label="New clients" value={newClientsToday} sub="first visit today" />}
        {isStylistView && <StatCard label="Chair status" value={currentStylist?.active ? "Active" : "Off today"} sub="set by front desk" />}
        <StatCard label={isClientView ? "Favorite service" : isStylistView ? "My occupancy" : "Chair occupancy"} value={isClientView ? (scopedAppointments[0]?.service || "-") : `${occupancyRate}%`} sub={isClientView ? "most recent booking" : "of available slots"} />
      </div>

      {isClientView && reviewableAppointments.length > 0 && (
        <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card mb-6">
          <div style={{ fontFamily: serifFont }} className="text-lg mb-3">How was your visit?</div>
          {reviewableAppointments.map((a) => {
            const draft = reviewDraft[a.id] || {};
            if (draft.submitted) return <div key={a.id} style={{ color: colors.success }} className="text-sm">Thanks for reviewing your {a.service}.</div>;
            return (
              <div key={a.id} className="flex flex-col gap-2 mb-3">
                <div className="text-sm">{a.service} with {a.stylist}</div>
                <div className="flex gap-1">
                  {[1, 2, 3, 4, 5].map((n) => (
                    <button key={n} onClick={() => setReviewDraft((prev) => ({ ...prev, [a.id]: { ...draft, rating: n } }))} aria-label={`${n} stars`}>
                      <Star size={18} style={{ color: n <= (draft.rating || 0) ? colors.wine : colors.border }} fill={n <= (draft.rating || 0) ? colors.wine : "none"} />
                    </button>
                  ))}
                </div>
                <input value={draft.comment || ""} onChange={(e) => setReviewDraft((prev) => ({ ...prev, [a.id]: { ...draft, comment: e.target.value } }))} placeholder="Optional comment" style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm" />
                <button onClick={() => submitReview(a.id)} className="btn-wine px-3 py-1.5 rounded-md text-xs font-medium self-start">Submit review</button>
              </div>
            );
          })}
        </div>
      )}

      <div className="grid md:grid-cols-3 gap-4 mb-6">
        <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="md:col-span-2 rounded-xl p-5 dash-card">
          <div className="flex items-center justify-between mb-4">
            <div style={{ fontFamily: serifFont }} className="text-lg">{isClientView ? "My upcoming visits" : isStylistView ? "My schedule" : "Today's schedule"}</div>
            <button onClick={() => setActiveTab("appointments")} style={{ color: colors.wine }} className="text-xs font-medium">View all</button>
          </div>
          <div className="flex flex-col gap-3">
            {overviewPreview.slice(0, 5).map((a) => (
              <div key={a.id} className="flex items-center justify-between text-sm gap-2">
                <div className="flex items-center gap-3 min-w-0">
                  <span style={{ color: colors.subtle, fontFamily: serifFont }} className="w-12 flex-shrink-0">{a.time}</span>
                  <span className="font-medium truncate">{isClientView ? a.service : a.client}</span>
                  <span style={{ color: colors.subtle }} className="truncate hidden sm:inline">· {isClientView ? a.stylist : a.service}</span>
                </div>
                <span style={{ color: statusColor(a.status, colors) }} className="text-xs font-medium flex-shrink-0">{STATUS_META[a.status].label}</span>
              </div>
            ))}
            {overviewPreview.length === 0 && <div style={{ color: colors.subtle }} className="text-sm">Nothing on the books yet.</div>}
          </div>
        </div>

        {isManagerView && (
          <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
            <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Stylist leaderboard <span style={{ color: colors.subtle }} className="text-xs font-sans">· last 6 months</span></div>
            <div className="flex flex-col gap-4">
              {leaderboard.map((s, i) => (
                <div key={s.name}>
                  <div className="flex items-center justify-between text-sm mb-1">
                    <span className="font-medium" style={{ color: i === 0 ? colors.wine : colors.ink }}>{s.name}{i === 0 && <span style={{ color: colors.subtle }} className="font-normal text-xs"> · top today</span>}</span>
                    <span style={{ color: colors.subtle }}>${s.total}</span>
                  </div>
                  <div style={{ background: colors.roseSoft }} className="h-1.5 rounded-full overflow-hidden">
                    <div style={{ width: `${s.pct * 100}%`, background: colors.rose }} className="h-full" />
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
        {isStylistView && (
          <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
            <div style={{ fontFamily: serifFont }} className="text-lg mb-4">My upcoming</div>
            <div className="flex flex-col gap-3">
              {overviewPreview.filter((a) => a.status === "confirmed").slice(0, 4).map((a) => (
                <div key={a.id} className="text-sm flex items-center justify-between"><span>{a.date} {a.time} · {a.client}</span><span style={{ color: colors.subtle }}>{a.service}</span></div>
              ))}
              {overviewPreview.filter((a) => a.status === "confirmed").length === 0 && <div style={{ color: colors.subtle }} className="text-sm">All caught up.</div>}
            </div>
          </div>
        )}
        {isClientView && (
          <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
            <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Book something new</div>
            <div style={{ color: colors.subtle }} className="text-sm mb-4">Pick a service and we'll match you with the next available stylist.</div>
            <button onClick={() => { setActiveTab("appointments"); resetForm(); setShowForm(true); }} className="btn-wine w-full px-4 py-2.5 rounded-md text-sm font-medium">Book appointment</button>
          </div>
        )}
      </div>

      {!isClientView && (
        <div className="grid md:grid-cols-3 gap-4">
          <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="md:col-span-2 rounded-xl p-5 dash-card">
            <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Weekly revenue</div>
            <Suspense fallback={<ChartPlaceholder height={220} />}><WeeklyRevenueChart data={weeklyRevenue} colors={colors} /></Suspense>
          </div>
          <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
            <div style={{ fontFamily: serifFont }} className="text-lg mb-4">{isStylistView ? "My day" : "Today's status mix"}</div>
            {statusBreakdown.length > 0 ? (
              <Suspense fallback={<ChartPlaceholder height={220} />}><StatusPieChart data={statusBreakdown} colors={colors} statusColor={statusColor} /></Suspense>
            ) : (
              <div style={{ color: colors.subtle }} className="text-sm h-[220px] flex items-center justify-center text-center">Nothing scheduled for this day yet.</div>
            )}
          </div>
        </div>
      )}
    </>
  );
}
