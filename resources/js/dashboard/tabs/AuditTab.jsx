import { ClipboardList } from "lucide-react";
import { useTheme } from "../theme.jsx";

export default function AuditTab({ ctx }) {
  const { colors } = useTheme();
  const { auditLog } = ctx;
  return (
    <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl dash-card">
      {auditLog.length === 0 && <div style={{ color: colors.subtle }} className="text-sm text-center py-10">No activity recorded yet.</div>}
      {auditLog.map((log) => (
        <div key={log.id} style={{ borderBottom: `1px solid ${colors.border}` }} className="flex items-center justify-between px-4 py-3 text-sm gap-2">
          <div className="flex items-center gap-2 min-w-0">
            <ClipboardList size={14} style={{ color: colors.subtle }} className="flex-shrink-0" />
            <span className="font-medium">{log.action}</span>
            {log.meta && Object.keys(log.meta).length > 0 && (
              <span style={{ color: colors.subtle }} className="truncate">
                — {Object.entries(log.meta).map(([k, v]) => `${k}: ${v}`).join(", ")}
              </span>
            )}
          </div>
          <div style={{ color: colors.subtle }} className="text-xs flex-shrink-0">{log.user?.name || "System"} · {log.created_at ? new Date(log.created_at).toLocaleString() : ""}</div>
        </div>
      ))}
    </div>
  );
}
