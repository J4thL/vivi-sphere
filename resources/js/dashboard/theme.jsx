import { createContext, useContext } from "react";

/* ---------------------------------- Theme --------------------------------- */

export const LIGHT_COLORS = {
  bgApp: "#FFFFFF", wine: "#7A3956", wineSoft: "#F3E4EA", rose: "#C97C96", // rose is for fills/icons only (3.1:1); text uses wine/subtle
  roseSoft: "#F8ECF0", ink: "#2E2229", subtle: "#6B5961", border: "#EFE4E9", // subtle darkened from #8C7A82: now >=5.3:1 on every light surface (WCAG AA)
  success: "#456644", successSoft: "#E9F0E8", danger: "#B0453F", dangerSoft: "#F7E9E7",
  warn: "#8A6A0E", warnSoft: "#FBF1D8", cardBg: "#FFFFFF", pageBg: "#FDFBFC",
};
export const DARK_COLORS = {
  bgApp: "#1C1418", wine: "#E7A9BE", wineSoft: "#3A2530", rose: "#D794AC",
  roseSoft: "#2A1E23", ink: "#F3E9ED", subtle: "#B79AA5", border: "#3A2A31",
  success: "#8FC08B", successSoft: "#1E2B1D", danger: "#E5897C", dangerSoft: "#3A211D",
  warn: "#E3C15E", warnSoft: "#332A16", cardBg: "#241A1F", pageBg: "#170F13",
};

export const THEME_KEY = "vivi-theme";
export const ThemeContext = createContext({ colors: LIGHT_COLORS, theme: "light", toggleTheme: () => {} });
export const useTheme = () => useContext(ThemeContext);

// Brand shown in the sidebar, auth screens and tab title. Follows the salon name
// from Settings so the dashboard matches the public site; falls back to the product name.
export const DEFAULT_BRAND = "Vivi - Sphere";
export const BrandContext = createContext(DEFAULT_BRAND);
export const useBrand = () => useContext(BrandContext);

export const sansFont = "'Work Sans', system-ui, sans-serif";
export const serifFont = "'Newsreader', Georgia, serif";

export function GlobalStyle() {
  const { colors } = useTheme();
  return (
    <style>{`
      body { font-family: ${sansFont}; -webkit-font-smoothing: antialiased; }
      /* Colour/font utilities driven by the active theme, so components don't need inline style={{}} for them. */
      :root { ${Object.entries(colors).map(([k, v]) => `--dc-${k}: ${v};`).join(" ")} }
      .dc-subtle { color: var(--dc-subtle); }
      .dc-wine { color: var(--dc-wine); }
      .dc-danger { color: var(--dc-danger); }
      .dc-success { color: var(--dc-success); }
      .font-display { font-family: ${serifFont}; }
      button, a, input, select, textarea { transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease, transform 0.1s ease; }
      button { cursor: pointer; }
      button:disabled { cursor: not-allowed; opacity: 0.6; }
      button:focus-visible, a:focus-visible { outline: 2px solid ${colors.wine}; outline-offset: 2px; }
      input:focus, select:focus, textarea:focus { outline: none; border-color: ${colors.wine} !important; box-shadow: 0 0 0 3px ${colors.wineSoft}; }
      .btn-wine { background: ${colors.wine}; color: ${colors.bgApp}; box-shadow: 0 1px 2px rgba(46,34,41,0.12); }
      .btn-wine:hover { filter: brightness(0.9); }
      .btn-wine:active { transform: scale(0.98); }
      .btn-outline-wine:hover { background: ${colors.wineSoft}; }
      select { border-radius: 8px; padding-right: 1.75rem; }
      table thead tr { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; }
      table tbody tr { transition: background-color 0.12s ease; }
      table tbody tr:hover { background: ${colors.roseSoft}; }
      .dash-card { transition: box-shadow 0.2s ease, transform 0.2s ease; box-shadow: 0 1px 2px rgba(46,34,41,0.04), 0 4px 16px rgba(122,57,86,0.06); }
      .stat-card:hover { transform: translateY(-1px); box-shadow: 0 2px 4px rgba(46,34,41,0.05), 0 10px 28px rgba(122,57,86,0.12); }
      .nav-link:hover { background: ${colors.roseSoft}; }
      .icon-btn:hover { filter: brightness(0.92); }
      .link-btn:hover { text-decoration: underline; }
    `}</style>
  );
}
