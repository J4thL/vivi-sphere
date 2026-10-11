import { Check, Star, X } from "lucide-react";
import { useTheme } from "../theme.jsx";
import { IconBtn } from "../ui.jsx";

export default function ReviewsTab({ ctx }) {
  const { colors } = useTheme();
  const { moderateReview, reviews } = ctx;
  return (
    <div className="flex flex-col gap-3">
      {reviews.length === 0 && <div style={{ color: colors.subtle }} className="text-sm">No reviews yet.</div>}
      {reviews.map((r) => (
        <div key={r.id} style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-lg p-4 flex items-center justify-between gap-3 flex-wrap">
          <div className="min-w-0">
            <div className="flex gap-0.5 mb-1">{[1, 2, 3, 4, 5].map((n) => <Star key={n} size={12} style={{ color: n <= r.rating ? colors.wine : colors.border }} fill={n <= r.rating ? colors.wine : "none"} />)}</div>
            <div className="text-sm">"{r.comment}"</div>
            <div style={{ color: colors.subtle }} className="text-xs">{r.clientName} · {r.stylistName}</div>
          </div>
          <div className="flex items-center gap-2 flex-shrink-0">
            <span style={{ color: r.status === "approved" ? colors.success : r.status === "rejected" ? colors.danger : colors.subtle }} className="text-xs font-medium capitalize">{r.status}</span>
            {r.status === "pending" && (
              <>
                <IconBtn onClick={() => moderateReview(r.id, "approved")} title="Approve" icon={Check} color={colors.success} />
                <IconBtn onClick={() => moderateReview(r.id, "rejected")} title="Reject" icon={X} color={colors.danger} />
              </>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}
