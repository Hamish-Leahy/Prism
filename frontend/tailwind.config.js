/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // Arc-inspired color palette
        'arc-bg': '#0C0C0C',
        'arc-surface': '#1A1A1A',
        'arc-border': '#2A2A2A',
        'arc-text': '#FFFFFF',
        'arc-text-secondary': '#B3B3B3',
        'arc-accent': '#007AFF',
        'arc-accent-hover': '#0056CC',
        'arc-success': '#30D158',
        'arc-warning': '#FF9F0A',
        'arc-error': '#FF3B30',
      },
      fontFamily: {
        'sans': ['SF Pro Display', 'system-ui', 'sans-serif'],
        'mono': ['SF Mono', 'Monaco', 'Consolas', 'monospace'],
      },
      animation: {
        'fade-in': 'fadeIn 0.2s ease-in-out',
        'slide-up': 'slideUp 0.3s ease-out',
        'slide-down': 'slideDown 0.3s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        slideDown: {
          '0%': { transform: 'translateY(-10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
      },
    },
  },
  plugins: [],
}
