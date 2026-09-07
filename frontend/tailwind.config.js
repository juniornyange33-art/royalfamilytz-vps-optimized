/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{js,jsx}"],
  theme: {
    extend: {
      colors: {
        // Gold / White / Black palette. Token names kept the same as before
        // (royal, navy, growth, parchment, ink) so every component that
        // already uses e.g. `bg-royal` or `text-growth` just inherits the
        // new look with no per-file changes.
        navy: "#0A0A0A",       // near-black — dark section backgrounds
        royal: "#111111",      // primary buttons / links (was purple, now black)
        gold: "#C9A227",       // signature gold accent
        parchment: "#FFFFFF",  // page background — pure white
        growth: "#9C7A1D",     // deeper gold — used for "active"/success text (needs contrast on white)
        ink: "#111111",        // body text — near-black
      },
      fontFamily: {
        display: ["'Fraunces'", "serif"],
        body: ["'Inter'", "sans-serif"],
      },
    },
  },
  plugins: [],
}
