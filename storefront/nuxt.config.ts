export default defineNuxtConfig({
  compatibilityDate: '2026-11-15',
  devtools: { enabled: false },
  modules: ['@nuxtjs/tailwindcss'],
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    public: {
      // Same-origin URL: Nuxt proxies these requests to Laravel.
      apiBase: process.env.NUXT_PUBLIC_API_BASE || '/api/v1/storefront',
    },
  },
  app: {
    head: {
      htmlAttrs: { lang: 'en' },
      meta: [{ name: 'theme-color', content: '#123f32' }],
    },
  },
  // Local and production routing: Nuxt serves the storefront while Laravel
  // remains responsible for ERP, APIs, uploaded files and admin routes.
  routeRules: {
    '/api/**': { proxy: 'http://127.0.0.1:8001/api/**' },
    '/backend': { proxy: 'http://127.0.0.1:8001/backend' },
    '/backend/**': { proxy: 'http://127.0.0.1:8001/backend/**' },
    '/build/**': { proxy: 'http://127.0.0.1:8001/build/**' },
    '/storage/**': { proxy: 'http://127.0.0.1:8001/storage/**' },
    '/robots.txt': { proxy: 'http://127.0.0.1:8001/robots.txt' },
    '/sitemap.xml': { proxy: 'http://127.0.0.1:8001/sitemap.xml' },
    '/products/**': { swr: 60 },
    '/categories/**': { swr: 60 },
  },
});
