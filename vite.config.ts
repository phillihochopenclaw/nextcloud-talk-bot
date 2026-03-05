import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: 'js',
    rollupOptions: {
      input: {
        admin: resolve(__dirname, 'index.html'),
      },
      output: {
        entryFileNames: 'talk-bot-[name].js',
        chunkFileNames: 'talk-bot-[name]-[hash].js',
        assetFileNames: 'talk-bot-[name]-[hash].[ext]',
      },
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  css: {
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
      },
    },
  },
});
