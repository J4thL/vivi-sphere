import { useEffect, useRef, useState } from "react";
import ChangePasswordCard from "./ChangePasswordCard.jsx";
import Dashboard from "./Dashboard.jsx";
import { GA_MEASUREMENT_ID, apiGet, apiSend, savedToken, setUnauthorizedHandler } from "./api.js";
import { ForgotPasswordPage, LoginPage, RegisterPage, ResetPasswordPage } from "./AuthPages.jsx";
import { EMPTY_LANDING_CONTENT, fromApiSettings, normalizeAppointment, normalizeClient, normalizeInventoryItem, normalizeReview, normalizeStylist, normalizeWaitlistEntry, toApiSettings } from "./helpers.js";
import { BrandContext, DARK_COLORS, DEFAULT_BRAND, GlobalStyle, LIGHT_COLORS, THEME_KEY, ThemeContext, sansFont } from "./theme.jsx";
import { BootLoader, ConfirmModal, ErrorBoundary, NotifyBanner } from "./ui.jsx";

const SESSION_EXPIRED_MESSAGE = "Your session expired. Please log in again.";

export default function App() {
  // Theme: remembered per browser; first visit follows the OS preference. Storage can be blocked, so every access is guarded.
  const [theme, setTheme] = useState(() => {
    try {
      const saved = localStorage.getItem(THEME_KEY);
      if (saved === "light" || saved === "dark") return saved;
    } catch { /* storage unavailable */ }
    return window.matchMedia?.("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  });
  const colors = theme === "dark" ? DARK_COLORS : LIGHT_COLORS;
  const toggleTheme = () => setTheme((t) => {
    const next = t === "light" ? "dark" : "light";
    try { localStorage.setItem(THEME_KEY, next); } catch { /* storage unavailable */ }
    return next;
  });
  useEffect(() => { document.documentElement.style.colorScheme = theme; }, [theme]);

  const [screen, setScreen] = useState("login");
  const [user, setUser] = useState(null);
  const [branches, setBranches] = useState([]);
  const [branchServices, setBranchServices] = useState([]); // a branch's own price/length/availability for a service
  const [token, setToken] = useState(null);
  const tokenRef = useRef(null); // lets the 401 handler tell "my session expired" from "already logged out"
  // Set when a session expires. Requests still in flight then fail with their own
  // errors ("Unauthenticated."); those are suppressed so the person sees the real reason.
  const sessionEndedRef = useRef(false);
  useEffect(() => { tokenRef.current = token; }, [token]);
  const [resetInfo, setResetInfo] = useState(null);
  // "Book at this branch" on the public page links to /app/register?branch=ID. It is kept in memory so it
  // survives moving between the register and login screens, then opens the booking form for that branch.
  const [preferredBranchId, setPreferredBranchId] = useState(() => {
    if (typeof window === "undefined") return null;
    const n = Number(new URLSearchParams(window.location.search).get("branch"));
    return Number.isInteger(n) && n > 0 ? n : null;
  });

  // When mounted inside the real Laravel app (see spa.blade.php / routes/web.php),
  // the marketing pages are server-rendered Blade views, not this component's
  // own "landing" screen. If we're sitting at bare /app with no sub-path,
  // send the visitor to the real landing page instead of duplicating it.
  // Harmless no-op in the Claude preview, where this path never matches.
  useEffect(() => {
    if (typeof window === "undefined") return;
    const path = window.location.pathname;
    if (path === "/app" || path === "/app/") { window.location.href = "/"; return; }
    if (path.endsWith("/login")) setScreen("login");
    else if (path.endsWith("/register")) setScreen("register");
    else if (path.includes("/reset-password/")) {
      // Link from the password-reset email: /app/reset-password/{token}?email=...
      setResetInfo({ token: decodeURIComponent(path.split("/reset-password/")[1] || ""), email: new URLSearchParams(window.location.search).get("email") || "" });
      setScreen("reset");
    }
  }, []);

  const [appointments, setAppointments] = useState([]);
  const [services, setServices] = useState({});
  const [stylists, setStylists] = useState([]);
  const [inventory, setInventory] = useState([]);
  const [clients, setClients] = useState([]);
  const [reviews, setReviews] = useState([]);
  const [waitlist, setWaitlist] = useState([]);
  const [auditLog, setAuditLog] = useState([]);
  const [landingContent, setLandingContent] = useState(EMPTY_LANDING_CONTENT);

  const [apiConnected, setApiConnected] = useState(false);
  const [bootLoading, setBootLoading] = useState(true);
  const [dashLoading, setDashLoading] = useState(false);
  const [notice, setNotice] = useState(null);
  const notify = (message, type = "success") => {
    if (sessionEndedRef.current && message !== SESSION_EXPIRED_MESSAGE) return;
    setNotice({ message, type });
    setTimeout(() => setNotice(null), 4000);
  };
  const [confirm, setConfirm] = useState(null);
  const askConfirm = (message, onYes, confirmLabel) => setConfirm({ message, confirmLabel, onYes });

  useEffect(() => {
    (async () => {
      const [settingsRes, servicesRes, stylistsRes, reviewsRes, branchServicesRes] = await Promise.all([
        apiGet("/settings"), apiGet("/services"), apiGet("/stylists"), apiGet("/reviews"), apiGet("/branch-services"),
      ]);
      if (branchServicesRes) setBranchServices(branchServicesRes);
      if (settingsRes) { setLandingContent(fromApiSettings(settingsRes) || EMPTY_LANDING_CONTENT); setApiConnected(true); }
      if (servicesRes) {
        const map = {};
        servicesRes.forEach((s) => { map[s.name] = { id: s.id, price: Number(s.price), duration: s.duration_minutes, photoUrl: s.photo_url || "" }; });
        setServices(map);
        setApiConnected(true);
      }
      if (stylistsRes) setStylists(stylistsRes.map(normalizeStylist));
      if (reviewsRes) setReviews(reviewsRes.map(normalizeReview));

      // Restore the previous session so a page refresh doesn't log people out.
      const saved = savedToken.get();
      if (saved) {
        const me = await apiGet("/auth/me", saved);
        if (me?.id) { setUser(me); setToken(saved); setScreen("app"); }
        else savedToken.clear();
      }
      setBootLoading(false);
    })();
  }, []);

  useEffect(() => {
    if (!token) return;
    (async () => {
      setDashLoading(true);
      // Only ask for what this role may see; the API answers 403 otherwise.
      const isManager = user?.role === "admin" || user?.role === "manager";
      const skip = Promise.resolve(null);
      if (user?.must_change_password) { setDashLoading(false); return; } // the API refuses everything until the temporary password is replaced
      const [a, c, i, al, w, rv, br] = await Promise.all([
        apiGet("/appointments", token),
        isManager || user?.role === "client" ? apiGet("/clients", token) : skip,
        isManager ? apiGet("/inventory", token) : skip,
        user?.role === "admin" ? apiGet("/audit-log", token) : skip,
        isManager || user?.role === "client" ? apiGet("/waitlist", token) : skip, // a client gets only their own requests
        isManager || user?.role === "client" ? apiGet("/reviews", token) : skip, // managers see all; a client also sees their own pending/rejected ones
        apiGet(user?.role === "admin" ? "/branches?include_inactive=1" : "/branches", token),
      ]);
      if (br) setBranches(br);
      if (rv) setReviews(rv.map(normalizeReview));
      if (a) setAppointments(a.map(normalizeAppointment));
      if (c) setClients(c.map(normalizeClient));
      if (i) setInventory(i.map(normalizeInventoryItem));
      if (al) setAuditLog(al);
      if (w) setWaitlist(w.map(normalizeWaitlistEntry));
      setDashLoading(false);
    })();
  }, [token, user?.role, user?.must_change_password]);

  useEffect(() => {
    if (!GA_MEASUREMENT_ID || typeof document === "undefined") return;
    const script = document.createElement("script");
    script.src = `https://www.googletagmanager.com/gtag/js?id=${GA_MEASUREMENT_ID}`;
    script.async = true;
    document.head.appendChild(script);
  }, []);

  const handleLogin = async ({ email, password }) => {
    sessionEndedRef.current = false;
    const remote = await apiSend("/auth/login", "POST", { email, password });
    if (remote && !remote.error && remote.user) { savedToken.set(remote.token); setUser(remote.user); setToken(remote.token); setScreen("app"); return; }
    notify(remote?.message || "Couldn't reach the server. Is the API running?", "error");
  };

  const handleRegister = async ({ name, email, password }) => {
    sessionEndedRef.current = false;
    const remote = await apiSend("/auth/register", "POST", { name, email, password });
    if (remote && !remote.error && remote.user) { savedToken.set(remote.token); setUser(remote.user); setToken(remote.token); setScreen("app"); notify("Account created. Check your email to verify it."); return; }
    notify(remote?.message || "Couldn't reach the server. Is the API running?", "error");
  };

  const handleForgotPassword = async (email) => { sessionEndedRef.current = false; await apiSend("/auth/forgot-password", "POST", { email }); notify("If an account exists for that email, we've sent a reset link."); };
  // Ends the session and drops everything loaded for that user, so nothing is left
  // on screen (or in memory) for the next person on a shared computer.
  const endSession = () => {
    savedToken.clear();
    setUser(null); setToken(null); setScreen("login");
    setAppointments([]); setClients([]); setInventory([]); setWaitlist([]); setAuditLog([]);
    apiGet("/reviews").then((r) => { if (r) setReviews(r.map(normalizeReview)); }); // back to the public list
  };
  const handleLogout = () => {
    const t = token;
    tokenRef.current = null; // so a 401 from the logout call itself isn't reported as an expired session
    apiSend("/auth/logout", "POST", {}, t);
    endSession();
  };
  // Expired or revoked token: go back to the login screen with an explanation.
  // Several requests can fail at once; only the first one for this token acts.
  useEffect(() => {
    setUnauthorizedHandler((failedToken) => {
      if (tokenRef.current !== failedToken) return;
      tokenRef.current = null;
      endSession();
      sessionEndedRef.current = true;
      notify(SESSION_EXPIRED_MESSAGE, "error");
    });
    return () => setUnauthorizedHandler(null);
  });
  const handleResetPassword = async ({ password, confirm }) => {
    sessionEndedRef.current = false;
    const r = await apiSend("/auth/reset-password", "POST", { token: resetInfo.token, email: resetInfo.email, password, password_confirmation: confirm });
    if (r && !r.error) {
      window.history.replaceState(null, "", "/app/login");
      setResetInfo(null); setScreen("login");
      notify("Password updated. Log in with your new password.");
      return;
    }
    notify(r?.message || "Couldn't reach the server. Is the API running?", "error");
  };
  const resendVerification = async () => {
    const result = await apiSend("/auth/resend-verification", "POST", {}, token);
    if (result?.error) notify(result.message, "error"); else notify("Verification email sent (demo mode: nothing is actually delivered without a mail server).");
  };

  const saveLandingContent = async (next) => {
    setLandingContent(next);
    const result = await apiSend("/settings", "PATCH", toApiSettings(next), token);
    if (result?.error) notify(result.message, "error"); else notify("Website updated.");
  };

  const goToMarketingSite = () => { window.location.href = "/"; };

  let body;
  if (bootLoading) body = <BootLoader />;
  else if (screen === "login") body = <LoginPage onLogin={handleLogin} onGoRegister={() => setScreen("register")} onGoForgot={() => setScreen("forgot")} onBack={goToMarketingSite} />;
  else if (screen === "register") body = <RegisterPage onRegister={handleRegister} onGoLogin={() => setScreen("login")} onBack={goToMarketingSite} />;
  else if (screen === "reset" && resetInfo) body = <ResetPasswordPage email={resetInfo.email} onSubmit={handleResetPassword} onBack={() => { window.history.replaceState(null, "", "/app/login"); setScreen("login"); }} />;
  else if (screen === "forgot") body = <ForgotPasswordPage onSubmit={handleForgotPassword} onBack={() => setScreen("login")} />;
  else {
    body = (
      <Dashboard
        user={user} token={token} onLogout={handleLogout} apiConnected={apiConnected} dashLoading={dashLoading}
        appointments={appointments} setAppointments={setAppointments}
        services={services} setServices={setServices}
        stylists={stylists} setStylists={setStylists}
        inventory={inventory} setInventory={setInventory}
        clients={clients} setClients={setClients}
        reviews={reviews} setReviews={setReviews}
        waitlist={waitlist} setWaitlist={setWaitlist}
        auditLog={auditLog}
        landingContent={landingContent} saveLandingContent={saveLandingContent}
        notify={notify} notice={notice} askConfirm={askConfirm}
        resendVerification={resendVerification}
        onPasswordChanged={(u) => u && setUser(u)}
        onUserUpdated={(u) => u && setUser(u)}
        branches={branches} setBranches={setBranches}
        branchServices={branchServices} setBranchServices={setBranchServices}
        preferredBranchId={preferredBranchId} onPreferredBranchUsed={() => setPreferredBranchId(null)}
      />
    );
    if (user?.must_change_password) {
      body = (
        <div className="max-w-md mx-auto mt-10 px-4 flex flex-col gap-3">
          {notice && <NotifyBanner notice={notice} />}
          <ChangePasswordCard token={token} notify={notify} onChanged={(u) => u && setUser(u)} intro="You're signed in with a temporary password. Choose your own password to continue." />
          <button onClick={handleLogout} className="dc-subtle text-xs underline self-start">Log out</button>
        </div>
      );
    }
  }

  return (
    <ErrorBoundary>
      <ThemeContext.Provider value={{ colors, theme, toggleTheme }}>
       <BrandContext.Provider value={landingContent.salonName || DEFAULT_BRAND}>
        <div style={{ fontFamily: sansFont }}>
          <GlobalStyle />
          {screen !== "app" && notice && <div className="max-w-md mx-auto mt-4 px-4"><NotifyBanner notice={notice} /></div>}
          {body}
          <ConfirmModal confirm={confirm} onCancel={() => setConfirm(null)} onConfirm={() => { confirm?.onYes(); setConfirm(null); }} />
        </div>
       </BrandContext.Provider>
      </ThemeContext.Provider>
    </ErrorBoundary>
  );
}
