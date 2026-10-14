import React, { useEffect, useRef } from "react";
import { Moon, Sun } from "lucide-react";
import { sansFont, serifFont, useTheme } from "./theme.jsx";

export function useDocumentMeta(title, description) {
  useEffect(() => {
    if (typeof document === "undefined") return;
    document.title = title;
    let meta = document.querySelector('meta[name="description"]');
    if (!meta) { meta = document.createElement("meta"); meta.name = "description"; document.head.appendChild(meta); }
    meta.content = description;
    let og = document.querySelector('meta[property="og:title"]');
    if (!og) { og = document.createElement("meta"); og.setAttribute("property", "og:title"); document.head.appendChild(og); }
    og.content = title;
  }, [title, description]);
}

/* --------------------------------- Notify banner --------------------------------- */

export function NotifyBanner({ notice }) {
  const { colors } = useTheme();
  if (!notice) return null;
  const isError = notice.type === "error";
  return (
    <div role="alert" aria-live="polite" style={{ background: isError ? colors.dangerSoft : colors.successSoft, color: isError ? colors.danger : colors.success, border: `1px solid ${isError ? colors.danger : colors.success}` }} className="text-sm px-4 py-2.5 rounded-md mb-4">
      {notice.message}
    </div>
  );
}

export function ConfirmModal({ confirm, onCancel, onConfirm }) {
  const { colors } = useTheme();
  const dialogRef = useRef(null);
  const cancelRef = useRef(null);
  const onCancelRef = useRef(onCancel);
  onCancelRef.current = onCancel; // the parent passes a new function every render; don't re-run the effect for it

  // Accessible dialog behaviour: Esc cancels, focus moves into the dialog (on the safe
  // "Cancel" button), Tab stays inside it, and focus returns to where it was on close.
  useEffect(() => {
    if (!confirm) return undefined;
    const previouslyFocused = document.activeElement;
    cancelRef.current?.focus();
    const onKey = (e) => {
      if (e.key === "Escape") { e.preventDefault(); onCancelRef.current(); return; }
      if (e.key !== "Tab" || !dialogRef.current) return;
      const focusable = dialogRef.current.querySelectorAll("button, [href], input, select, textarea, [tabindex]:not([tabindex='-1'])");
      if (focusable.length === 0) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    };
    document.addEventListener("keydown", onKey);
    return () => { document.removeEventListener("keydown", onKey); previouslyFocused?.focus?.(); };
  }, [confirm]);

  if (!confirm) return null;
  return (
    <div style={{ background: "rgba(0,0,0,0.35)" }} className="fixed inset-0 z-50 flex items-center justify-center p-6">
      <div ref={dialogRef} role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-message" style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 max-w-sm w-full dash-card">
        <div id="confirm-dialog-message" className="text-sm mb-4">{confirm.message}</div>
        <div className="flex justify-end gap-2">
          <button ref={cancelRef} onClick={onCancel} className="dc-subtle text-sm px-3 py-1.5">Cancel</button>
          <button onClick={onConfirm} style={{ background: colors.danger, color: "#fff" }} className="text-sm px-3 py-1.5 rounded-md font-medium">{confirm.confirmLabel || "Confirm"}</button>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------- Error boundary ------------------------------- */
// Without this, any uncaught render error (e.g. an unexpected API response
// shape) blanks the entire page white with no explanation - exactly what
// happened before normalizeAppointment/normalizeClient existed. This won't
// prevent bugs, but turns "blank white screen" into a visible, readable
// error instead of a silent crash.
export class ErrorBoundary extends React.Component {
  constructor(props) { super(props); this.state = { error: null }; }
  static getDerivedStateFromError(error) { return { error }; }
  componentDidCatch(error, info) { console.error("Dashboard crashed:", error, info); }
  render() {
    if (this.state.error) {
      return (
        <div style={{ fontFamily: sansFont, padding: 32, maxWidth: 640, margin: "0 auto" }}>
          <h1 style={{ fontFamily: serifFont, fontSize: 24, marginBottom: 12 }}>Something went wrong</h1>
          <p style={{ color: "#8C7A82", marginBottom: 16 }}>
            The app hit an unexpected error and couldn't continue rendering. Check the browser console
            (F12 → Console) for the technical detail, or reload the page to start over.
          </p>
          <pre style={{ background: "#F7E9E7", color: "#B0453F", padding: 12, borderRadius: 8, fontSize: 12, overflowX: "auto" }}>
            {String(this.state.error?.message || this.state.error)}
          </pre>
          <button onClick={() => window.location.reload()} style={{ marginTop: 16, background: "#7A3956", color: "#fff", padding: "10px 16px", borderRadius: 6, fontSize: 14 }}>
            Reload
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}

export function BootLoader() {
  const { colors } = useTheme();
  return <div style={{ background: colors.bgApp, color: colors.subtle }} className="min-h-screen flex items-center justify-center text-sm">Loading...</div>;
}

export function ThemeToggle({ compact }) {
  const { theme, toggleTheme, colors } = useTheme();
  return (
    <button onClick={toggleTheme} aria-label={theme === "light" ? "Switch to dark mode" : "Switch to light mode"} title={theme === "light" ? "Switch to dark mode" : "Switch to light mode"} style={{ border: `1px solid ${colors.border}`, color: colors.subtle }} className={`icon-btn flex items-center justify-center rounded-md ${compact ? "w-8 h-8" : "px-3 py-2 gap-2 text-sm"}`}>
      {theme === "light" ? <Moon size={15} /> : <Sun size={15} />}
      {!compact && (theme === "light" ? "Dark mode" : "Light mode")}
    </button>
  );
}

export function StatCard({ label, value, sub, trend }) {
  const { colors } = useTheme();
  return (
    <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="stat-card dash-card rounded-xl p-5">
      <div className="flex items-center justify-between mb-3 gap-2">
        <div className="dc-subtle text-[11px] font-semibold uppercase tracking-wider">{label}</div>
        {trend !== null && trend !== undefined && (
          <div style={{ color: trend >= 0 ? colors.success : colors.danger, background: trend >= 0 ? colors.successSoft : colors.dangerSoft }} className="text-[11px] font-semibold flex items-center gap-0.5 px-1.5 py-0.5 rounded-full">
            {trend >= 0 ? "▲" : "▼"} {Math.abs(trend)}%
          </div>
        )}
      </div>
      <div style={{ fontFamily: serifFont, color: colors.wine }} className="text-2xl md:text-3xl leading-none mb-2">{value}</div>
      <div className="dc-subtle text-xs">{sub}</div>
    </div>
  );
}

export function Field({ label, children }) {
  return (
    // The <label> wraps the control, so the text is programmatically tied to the input
    // (screen readers announce it, and clicking the text focuses the field).
    <label className="flex flex-col gap-1">
      <span className="dc-subtle text-xs">{label}</span>
      {children}
    </label>
  );
}

export function IconBtn({ onClick, icon: Icon, color, title }) {
  return (
    <button onClick={onClick} title={title} aria-label={title} style={{ border: `1px solid ${color}`, color }} className="icon-btn w-7 h-7 rounded-md flex items-center justify-center flex-shrink-0">
      <Icon size={13} />
    </button>
  );
}
