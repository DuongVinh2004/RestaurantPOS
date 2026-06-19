/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f0f9ff',
          100: '#e0f2fe',
          500: '#0ea5e9',
          600: '#0284c7',
          900: '#0c4a6e',
        },
        status: {
          info: '#0284c7',
          success: '#16a34a',
          warning: '#d97706',
          danger: '#dc2626',
          pending: '#eab308',
          offline: '#64748b',
          syncing: '#3b82f6',
          conflict: '#f97316',
          critical: '#991b1b',
        }
      }
    },
  },
  plugins: [],
}
