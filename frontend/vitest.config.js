import { fileURLToPath } from 'node:url'
import { mergeConfig, defineConfig, configDefaults } from 'vitest/config'
import viteConfig from './vite.config.js'

export default defineConfig((env) => mergeConfig(
  viteConfig(env),
  defineConfig({
    test: {
      environment: 'jsdom',
      // Globals avoid the Vitest bare-import resolution bug on Windows.
      globals: true,
      exclude: [...configDefaults.exclude, 'e2e/**'],
      root: fileURLToPath(new URL('./', import.meta.url)),
    },
  }),
))
