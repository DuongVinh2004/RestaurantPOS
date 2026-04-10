import { configDefaults, defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  test: {
    include: [
      'src/**/*.test.ts',
      'src/**/*.test.tsx',
      'src/**/*.spec.ts',
      'src/**/*.spec.tsx',
      'scripts/**/*.test.mjs',
      'scripts/**/*.spec.mjs',
    ],
    exclude: [...configDefaults.exclude, 'tmp-smoke/**'],
  },
  server: {
    host: 'localhost',
    port: 5173,
    strictPort: true,
    fs: {
      allow: ['..'],
    },
  },
  preview: {
    host: 'localhost',
    port: 4173,
  },
});
