/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        // Design tokens — deliberately not the cream+terracotta or
        // dark+neon defaults; a deep navy + ocean teal pairing fits a
        // travel operations product without reading as templated.
        ink: {
          DEFAULT: '#1C2430',
          soft: '#4A5568',
        },
        navy: {
          DEFAULT: '#16233F',
          light: '#24365C',
          dark: '#0E1729',
        },
        teal: {
          DEFAULT: '#0E7C7B',
          light: '#12A19F',
          dark: '#0A5B5A',
        },
        canvas: '#F3F4F1',
        surface: '#FFFFFF',
        line: '#DDD9CE',
        success: '#2F7D5A',
        warn: '#B3742B',
        danger: '#B3432B',
      },
      fontFamily: {
        sans: ['"Inter"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
      },
      borderRadius: {
        DEFAULT: '6px',
      },
    },
  },
  plugins: [],
}
