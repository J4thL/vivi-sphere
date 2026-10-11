import { lazy } from "react";

// Charts live in dashboard-charts.jsx and load on demand (recharts is a large library).
export const WeeklyRevenueChart = lazy(() => import("../dashboard-charts.jsx").then((m) => ({ default: m.WeeklyRevenueChart })));
export const StatusPieChart = lazy(() => import("../dashboard-charts.jsx").then((m) => ({ default: m.StatusPieChart })));
export const RevenueByServiceChart = lazy(() => import("../dashboard-charts.jsx").then((m) => ({ default: m.RevenueByServiceChart })));
export function ChartPlaceholder({ height }) {
  return <div role="status" aria-label="Loading chart" style={{ height }} className="animate-pulse rounded-lg opacity-30 bg-current" />;
}
