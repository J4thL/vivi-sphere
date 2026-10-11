import { useState } from "react";
import { serifFont, useBrand, useTheme } from "./theme.jsx";
import { ThemeToggle } from "./ui.jsx";

/* ---------------------------- Login / Register / Forgot ---------------------------- */

function AuthShell({ children, onBack }) {
  const { colors } = useTheme();
  const brand = useBrand();
  return (
    <div style={{ background: colors.roseSoft }} className="min-h-screen flex items-center justify-center p-6">
      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}`, color: colors.ink }} className="w-full max-w-sm rounded-2xl shadow-lg p-8 sm:p-9">
        <div className="flex items-center justify-between mb-6">
          <button onClick={onBack} style={{ color: colors.subtle }} className="text-xs">← Back to site</button>
          <ThemeToggle compact />
        </div>
        <div style={{ fontFamily: serifFont, color: colors.wine }} className="text-2xl mb-6">{brand}</div>
        {children}
      </div>
    </div>
  );
}

export function LoginPage({ onLogin, onGoRegister, onGoForgot, onBack }) {
  const { colors } = useTheme();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");

  const submit = (e) => {
    e.preventDefault();
    if (!email || !password) { setError("Enter an email and password."); return; }
    setError("");
    onLogin({ email, password });
  };

  return (
    <AuthShell onBack={onBack}>
      <div style={{ fontFamily: serifFont }} className="text-xl mb-1">Welcome back</div>
      <div style={{ color: colors.subtle }} className="text-xs mb-6">Log in to manage your appointments.</div>
      <form onSubmit={submit} className="flex flex-col gap-3">
        <LabeledInput label="Email" type="email" value={email} onChange={setEmail} placeholder="you@example.com" autoComplete="email" />
        <div>
          <LabeledInput label="Password" type="password" value={password} onChange={setPassword} placeholder="********" autoComplete="current-password" />
          <button type="button" onClick={onGoForgot} style={{ color: colors.wine }} className="text-xs mt-1">Forgot your password?</button>
        </div>
        {error && <div style={{ color: colors.danger }} className="text-xs">{error}</div>}
        <button type="submit" className="btn-wine px-4 py-2.5 rounded-md text-sm font-medium mt-2">Log in</button>
      </form>
      <div style={{ color: colors.subtle }} className="text-xs mt-5 text-center">
        New here? <button onClick={onGoRegister} style={{ color: colors.wine }} className="font-medium">Create an account</button>
      </div>
    </AuthShell>
  );
}

export function RegisterPage({ onRegister, onGoLogin, onBack }) {
  const { colors } = useTheme();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [error, setError] = useState("");
  const passwordScore = (pw) => (pw.length >= 12 ? "Strong" : pw.length >= 8 ? "Okay" : pw.length > 0 ? "Weak" : "");

  const submit = (e) => {
    e.preventDefault();
    if (!name || !email || !password) { setError("Fill in every field."); return; }
    if (password.length < 8) { setError("Password must be at least 8 characters."); return; }
    if (password !== confirm) { setError("Passwords don't match."); return; }
    setError("");
    onRegister({ name, email, password });
  };

  return (
    <AuthShell onBack={onBack}>
      <div style={{ fontFamily: serifFont }} className="text-xl mb-1">Create your account</div>
      <div style={{ color: colors.subtle }} className="text-xs mb-6">Book appointments and track your visit history.</div>
      <form onSubmit={submit} className="flex flex-col gap-3">
        <LabeledInput label="Full name" value={name} onChange={setName} placeholder="Jane Avery" autoComplete="name" />
        <LabeledInput label="Email" type="email" value={email} onChange={setEmail} placeholder="you@example.com" autoComplete="email" />
        <div>
          <LabeledInput label="Password" type="password" value={password} onChange={setPassword} placeholder="At least 8 characters" autoComplete="new-password" />
          {password && <div style={{ color: colors.subtle }} className="text-xs mt-1">Strength: {passwordScore(password)}</div>}
        </div>
        <LabeledInput label="Confirm password" type="password" value={confirm} onChange={setConfirm} placeholder="********" autoComplete="new-password" />
        {error && <div style={{ color: colors.danger }} className="text-xs">{error}</div>}
        <button type="submit" className="btn-wine px-4 py-2.5 rounded-md text-sm font-medium mt-2">Create account</button>
      </form>
      <div style={{ color: colors.subtle }} className="text-xs mt-5 text-center">
        Already registered? <button onClick={onGoLogin} style={{ color: colors.wine }} className="font-medium">Log in</button>
      </div>
    </AuthShell>
  );
}

export function ForgotPasswordPage({ onSubmit, onBack }) {
  const { colors } = useTheme();
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState(false);
  const submit = (e) => { e.preventDefault(); if (!email) return; onSubmit(email); setSent(true); };
  return (
    <AuthShell onBack={onBack}>
      <div style={{ fontFamily: serifFont }} className="text-xl mb-1">Reset your password</div>
      <div style={{ color: colors.subtle }} className="text-xs mb-6">We'll email you a link to set a new one.</div>
      {sent ? (
        <div style={{ background: colors.successSoft, color: colors.success }} className="text-sm px-3 py-2.5 rounded-md">If an account exists for that email, we've sent a reset link.</div>
      ) : (
        <form onSubmit={submit} className="flex flex-col gap-3">
          <LabeledInput label="Email" type="email" value={email} onChange={setEmail} placeholder="you@example.com" autoComplete="email" />
          <button type="submit" className="btn-wine px-4 py-2.5 rounded-md text-sm font-medium mt-2">Send reset link</button>
        </form>
      )}
      <button onClick={onBack} style={{ color: colors.wine }} className="text-xs mt-5 font-medium block mx-auto">Back to log in</button>
    </AuthShell>
  );
}

export function ResetPasswordPage({ email, onSubmit, onBack }) {
  const { colors } = useTheme();
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [error, setError] = useState("");
  const submit = (e) => {
    e.preventDefault();
    if (password.length < 8) { setError("Password must be at least 8 characters."); return; }
    if (password !== confirm) { setError("Passwords don't match."); return; }
    setError("");
    onSubmit({ password, confirm });
  };
  return (
    <AuthShell onBack={onBack}>
      <div style={{ fontFamily: serifFont }} className="text-xl mb-1">Choose a new password</div>
      <div style={{ color: colors.subtle }} className="text-xs mb-6">{email ? `For ${email}` : "Enter a new password for your account."}</div>
      <form onSubmit={submit} className="flex flex-col gap-3">
        <LabeledInput label="New password" type="password" value={password} onChange={setPassword} placeholder="At least 8 characters" autoComplete="new-password" />
        <LabeledInput label="Confirm password" type="password" value={confirm} onChange={setConfirm} placeholder="********" autoComplete="new-password" />
        {error && <div style={{ color: colors.danger }} className="text-xs">{error}</div>}
        <button type="submit" className="btn-wine px-4 py-2.5 rounded-md text-sm font-medium mt-2">Update password</button>
      </form>
    </AuthShell>
  );
}

function LabeledInput({ label, value, onChange, type = "text", placeholder, autoComplete }) {
  const { colors } = useTheme();
  const id = `field-${label.replace(/\s+/g, "-").toLowerCase()}`;
  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={id} style={{ color: colors.subtle }} className="text-xs">{label}</label>
      <input id={id} type={type} value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} autoComplete={autoComplete} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-3 py-2.5 rounded-lg text-sm" />
    </div>
  );
}
