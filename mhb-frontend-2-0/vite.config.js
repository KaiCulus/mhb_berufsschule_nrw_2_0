import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

export default defineConfig({
  plugins: [vue(), vueDevTools()],
  base: process.env.NODE_ENV === 'production' ? '/mhb/' : '/',
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) }
  },
  server: {
    proxy: {
      '/oauth': { target: 'https://localhost:443', changeOrigin: true, secure: false },
      '/api':   { target: 'https://localhost:443', changeOrigin: true, secure: false },
    }
  }
})