/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'monospace'],
      },
      colors: {
        // ── Light White-Green Theme (RSI Brand) ──────────────────
        bg: '#f0f7f0',          // Latar halaman — hijau sangat muda
        surface: '#ffffff',          // Card / sidebar — putih bersih
        surface2: '#f4f9f4',          // Card secondary — hijau susu
        surface3: '#e8f2e8',          // Input / hover — hijau pucat
        border: '#c8e0c8',          // Border — hijau abu
        textMain: '#1a2e1a',          // Teks utama — hijau sangat gelap
        textMuted: '#4a6e4a',          // Teks sekunder — hijau sedang
        textDim: '#8aab8a',          // Teks tersier — hijau muda

        // ── Accent = Hijau RSI Brand ──────────────────────────────
        accent: '#4a9e4a',          // Hijau utama RSI (#4a9e4a)
        accentHover: '#3d883d',          // Hover lebih gelap
        accentGlow: 'rgba(74,158,74,0.15)',

        // ── Status Colors (adjusted for light theme) ──────────────
        brandGreen: '#2e7d32',
        brandGreenBg: 'rgba(46,125,50,0.10)',
        brandYellow: '#e65100',
        brandYellowBg: 'rgba(230,81,0,0.09)',
        brandRed: '#c62828',
        brandRedBg: 'rgba(198,40,40,0.09)',
        brandBlue: '#1565c0',
        brandBlueBg: 'rgba(21,101,192,0.10)',
        brandPurple: '#6a1b9a',
        brandPurpleBg: 'rgba(106,27,154,0.09)',
        brandCyan: '#00838f',
        brandCyanBg: 'rgba(0,131,143,0.09)',
      }
    },
  },
  plugins: [],
}
