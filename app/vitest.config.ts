/// <reference types="vitest" />
import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import angular from '@analogjs/vite-plugin-angular';
import viteTsConfigPaths from 'vite-tsconfig-paths';

export default defineConfig(({ mode }) => ({
  plugins: [angular(), viteTsConfigPaths()],
  resolve: {
    alias: {
      'lucide-angular': fileURLToPath(
        new URL('./src/test-helpers/lucide-angular.stub.ts', import.meta.url),
      ),
    },
  },
  test: {
    globals: true,
    setupFiles: ['src/test-setup.ts'],
    environment: 'jsdom',
    include: ['src/**/*.{test,spec}.{js,mjs,cjs,ts,mts,cts,jsx,tsx}'],
    reporters: ['default'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      reportsDirectory: './coverage',
      include: ['src/**/*.ts'],
      exclude: ['src/**/*.spec.ts', 'src/main.ts', 'src/environments/**'],
    },
  },
}));
