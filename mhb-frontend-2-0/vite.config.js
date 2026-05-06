import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

export default defineConfig(({ command }) => ({
  plugins: [vue(), vueDevTools()],
  // 'build' = vite build → immer /mhb/, 'serve' = vite dev → /
  base: command === 'build' ? '/mhb/' : '/',
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) }
  },
  server: {
    proxy: {
      '/oauth': { target: 'https://localhost:443', changeOrigin: true, secure: false },
      '/api':   { target: 'https://localhost:443', changeOrigin: true, secure: false },
    }
  }
}))