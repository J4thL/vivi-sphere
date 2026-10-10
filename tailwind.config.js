/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/views/**/*.blade.php",
    "./resources/js/**/*.{js,jsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ["'Work Sans'", "system-ui", "sans-serif"],
        serif: ["'Newsreader'", "Georgia", "serif"],
      },
      colors: {
        wine: "#7A3956",
        winesoft: "#F3E4EA",
        rose: "#C97C96",
        rosesoft: "#F8ECF0",
        ink: "#2E2229",
        subtle: "#8C7A82",
        line: "#EFE4E9",
      },
    },
  },
  plugins: [],
};
