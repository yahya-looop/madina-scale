const laravelProxyTarget = process.env.NUXT_LARAVEL_PROXY_TARGET || 'http://127.0.0.1:8001'

export default defineNuxtConfig({
  compatibilityDate: '2026-11-15',
  devtools: { enabled: false },
  modules: ['@nuxtjs/tailwindcss'],
  postcss: {
    plugins: {
      'tailwindcss/nesting': false,
    },
  },
  css: ['~/assets/css/main.css'],
  runtimeConfig: {
    public: {
      // Keep this same-origin. Laravel credentials must never be exposed here.
      apiBase: process.env.NUXT_PUBLIC_API_BASE || '/api/v1/storefront',
    },
  },
  app: {
    head: {
      htmlAttrs: { lang: 'en' },
      meta: [{ name: 'theme-color', content: '#123f32' }],
    },
  },
  // Nuxt owns public pages; Laravel remains the API and staff ERP.
  routeRules: {
    '/api/**': { proxy: `${laravelProxyTarget}/api/**` },
    '/backend': { proxy: `${laravelProxyTarget}/backend` },
    '/backend/**': { proxy: `${laravelProxyTarget}/backend/**` },
    '/build/**': { proxy: `${laravelProxyTarget}/build/**` },
    '/storage/**': { proxy: `${laravelProxyTarget}/storage/**` },
    '/robots.txt': { proxy: `${laravelProxyTarget}/robots.txt` },
    '/sitemap.xml': { proxy: `${laravelProxyTarget}/sitemap.xml` },
    '/': { swr: 60 },
    '/products/**': { swr: 60 },
    '/categories/**': { swr: 60 },
  },
})
