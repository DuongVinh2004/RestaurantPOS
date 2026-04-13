import { configDefaults, defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
    css: true,
    testTimeout: 10000,
    fileParallelism: false,
    maxWorkers: 1,
    minWorkers: 1,
    exclude: [...configDefaults.exclude, 'src/_legacy/**'],
  },
});
