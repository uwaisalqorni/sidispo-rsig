import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'

export default definePreset(Aura, {
  semantic: {
    primary: {
      50: '#f0f7f0',
      100: '#e8f2e8',
      200: '#c8e0c8',
      300: '#9ccc9c',
      400: '#6db86d',
      500: '#4a9e4a',
      600: '#3d883d',
      700: '#2e7d32',
      800: '#1b5e20',
      900: '#1a2e1a',
      950: '#0d1f0d',
    },
    colorScheme: {
      light: {
        surface: {
          0: '#ffffff',
          50: '#f4f9f4',
          100: '#f0f7f0',
          200: '#e8f2e8',
          300: '#c8e0c8',
          400: '#9ccc9c',
          500: '#6db86d',
          600: '#4a9e4a',
          700: '#3d883d',
          800: '#2e7d32',
          900: '#1a2e1a',
          950: '#0d1f0d',
        },
      },
    },
  },
})
