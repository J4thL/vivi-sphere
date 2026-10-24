import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, Legend,
} from "recharts";

/*
 * Chart components for the dashboard. Kept in their own module so the (large) recharts
 * library is downloaded only when a screen that shows a chart is opened, instead of
 * being part of the first page load for everyone (including login).
 */

const tooltipStyle = (colors) => ({ borderRadius: 8, border: `1px solid ${colors.border}`, fontSize: 12, background: colors.cardBg, color: colors.ink });

export function WeeklyRevenueChart({ data, colors }) {
  return (
    <ResponsiveContainer width="100%" height={220}>
      <BarChart data={data}>
        <CartesianGrid stroke={colors.border} vertical={false} />
        <XAxis dataKey="day" tick={{ fontSize: 12, fill: colors.subtle }} axisLine={{ stroke: colors.border }} tickLine={false} />
        <YAxis tick={{ fontSize: 12, fill: colors.subtle }} axisLine={false} tickLine={false} />
        <Tooltip contentStyle={tooltipStyle(colors)} />
        <Bar dataKey="revenue" fill={colors.rose} radius={[4, 4, 0, 0]} />
      </BarChart>
    </ResponsiveContainer>
  );
}

export function StatusPieChart({ data, colors, statusColor }) {
  return (
    <ResponsiveContainer width="100%" height={220}>
      <PieChart>
        <Pie data={data} dataKey="value" nameKey="name" innerRadius={45} outerRadius={75} paddingAngle={2}>
          {data.map((entry) => <Cell key={entry.status} fill={statusColor(entry.status, colors)} />)}
        </Pie>
        <Tooltip contentStyle={tooltipStyle(colors)} />
        <Legend wrapperStyle={{ fontSize: 12, color: colors.subtle }} />
      </PieChart>
    </ResponsiveContainer>
  );
}

export function RevenueByServiceChart({ data, colors }) {
  return (
    <ResponsiveContainer width="100%" height={240}>
      <BarChart data={data} layout="vertical" margin={{ left: 40 }}>
        <CartesianGrid stroke={colors.border} horizontal={false} />
        <XAxis type="number" tick={{ fontSize: 12, fill: colors.subtle }} axisLine={false} tickLine={false} />
        <YAxis type="category" dataKey="service" tick={{ fontSize: 12, fill: colors.subtle }} axisLine={false} tickLine={false} width={130} />
        <Tooltip contentStyle={tooltipStyle(colors)} />
        <Bar dataKey="revenue" fill={colors.wine} radius={[0, 4, 4, 0]} />
      </BarChart>
    </ResponsiveContainer>
  );
}
