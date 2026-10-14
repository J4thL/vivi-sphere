import { Suspense } from "react";
import { ChartPlaceholder, RevenueByServiceChart } from "../lazyCharts.jsx";
import { formatMoney } from "../helpers.js";
import { useTheme } from "../theme.jsx";
import { StatCard } from "../ui.jsx";

export default function ReportsTab({ ctx }) {
  const { colors } = useTheme();
  const { avgTicket, cancellationRate, completedInRange, reportRange, revenueByService, setReportRange, totalReportRevenue } = ctx;
  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        {["today", "week", "month"].map((r) => (
          <button key={r} onClick={() => setReportRange(r)} style={{ border: `1px solid ${reportRange === r ? colors.wine : colors.border}`, color: reportRange === r ? colors.wine : colors.subtle, background: reportRange === r ? colors.wineSoft : "transparent" }} className="text-xs px-3 py-1.5 rounded-md capitalize">{r}</button>
        ))}
      </div>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard label="Total revenue" value={formatMoney(totalReportRevenue)} sub={`this ${reportRange}`} />
        <StatCard label="Completed services" value={completedInRange} sub={`this ${reportRange}`} />
        <StatCard label="Average ticket" value={formatMoney(avgTicket)} sub="per completed visit" />
        <StatCard label="Cancellation rate" value={`${cancellationRate}%`} sub={`this ${reportRange}`} />
      </div>
      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
        <div className="font-display text-lg mb-4">Revenue by service</div>
        <Suspense fallback={<ChartPlaceholder height={240} />}><RevenueByServiceChart data={revenueByService} colors={colors} /></Suspense>
      </div>
    </div>
  );
}
