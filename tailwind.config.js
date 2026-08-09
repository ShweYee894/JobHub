/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
        heading: ['Inter', 'system-ui', 'sans-serif'],
      },
      colors: {
        surface: {
          primary: {
            DEFAULT: '#FFFFFF',
            dark: '#0B132B',
          },
          secondary: {
            DEFAULT: '#F4F7FC',
            dark: '#1C2541',
          }
        },
        content: {
          primary: {
            DEFAULT: '#1C2541',
            dark: '#FFFFFF',
          },
          secondary: {
            DEFAULT: '#5C6B89',
            dark: '#8594B0',
          }
        },
        brand: {
          trust: {
            DEFAULT: '#1D4ED8',
            dark: '#3B82F6',
          },
          success: {
            DEFAULT: '#059669',
            dark: '#10B981',
          }
        }
      },
      boxShadow: {
        'custom': '0 20px 40px -10px rgba(0,0,0,0.05)',
        'premium': '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 10px 15px -3px rgba(0, 0, 0, 0.05)',
        'premium-hover': '0 20px 40px -12px rgba(67, 56, 202, 0.12)',
      },
    },
  },
  plugins: [],
}

