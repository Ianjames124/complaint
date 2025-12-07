import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 3000,
    // Vite automatically handles SPA routing - all routes fallback to index.html
    // This is built-in, no configuration needed for dev server
  },
  // Build configuration for production
  build: {
    rollupOptions: {
      output: {
        manualChunks: undefined,
      },
    },
  },
});


