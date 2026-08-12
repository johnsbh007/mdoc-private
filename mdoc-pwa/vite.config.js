import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

// API base URL — swap to the live server for production builds
const API_BASE = process.env.MDOC_API_BASE || 'https://mdoc.apis.com.la/api/v1'

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.svg', 'icons/icon-192.png', 'icons/icon-512.png'],
      manifest: {
        name: 'mDoc — ເອກະສານເອເລັກໂຕຣນິກ',
        short_name: 'mDoc',
        description: 'Citizen e-document service: apply, pay, track, download official documents',
        theme_color: '#0f766e',
        background_color: '#ffffff',
        display: 'standalone',
        start_url: '/',
        lang: 'lo',
        icons: [
          {
            src: 'icons/icon-192.png',
            sizes: '192x192',
            type: 'image/png',
            purpose: 'any maskable',
          },
          {
            src: 'icons/icon-512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'any maskable',
          },
        ],
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,svg,png,woff2}'],
        runtimeCaching: [
          {
            // Cache catalog + hierarchy API responses (never stale for long)
            urlPattern: ({ url }) => url.origin === 'https://mdoc.apis.com.la' && /\/api\/v1\/(doc-types|doc-items|hierarchy)/.test(url.pathname),
            handler: 'NetworkFirst',
            options: {
              cacheName: 'mdoc-catalog',
              expiration: { maxEntries: 20, maxAgeSeconds: 60 * 60 * 2 }, // 2h
              cacheableResponse: { statuses: [0, 200] },
            },
          },
          {
            // Auth/user endpoints: never cache (sensitive)
            urlPattern: ({ url }) => url.origin === 'https://mdoc.apis.com.la' && /\/api\/v1\/(auth|documents|payments)/.test(url.pathname),
            handler: 'NetworkOnly',
          },
        ],
      },
      devOptions: {
        enabled: true,
      },
    }),
  ],
  define: {
    __API_BASE__: JSON.stringify(API_BASE),
  },
  server: {
    host: true,
    port: 5173,
  },
})