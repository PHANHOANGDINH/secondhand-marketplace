import process from 'node:process'
import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import { brandLine } from './src/config/brand.js'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const target = env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8000'
  const proxy = Object.fromEntries(
    ['/api', '/sanctum', '/storage', '/placeholders'].map((path) => [
      path,
      { target, changeOrigin: true },
    ]),
  )

  return {
    plugins: [
      vue(),
      vueDevTools(),
      // The static <title> comes from the same brand config as the app.
      {
        name: 'brand-title',
        transformIndexHtml: (html) => html.replace('%BRAND_LINE%', brandLine),
      },
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      host: 'localhost',
      port: 5173,
      strictPort: true,
      proxy,
    },
  }
})
