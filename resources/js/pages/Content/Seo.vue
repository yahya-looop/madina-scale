<script setup lang="ts">
const checks = [
  { title: 'XML Sitemap', detail: 'Published and indexable CMS pages plus eligible storefront records.', href: '/sitemap.xml', action: 'Open sitemap' },
  { title: 'Robots.txt', detail: 'Crawler directives and sitemap discovery.', href: '/robots.txt', action: 'Open robots.txt' },
  { title: 'Page metadata', detail: 'Edit meta title, description, canonical URL and robots directives inside each CMS page.', href: '/content/pages', action: 'Manage content pages' },
];
</script>
<template>
  <main class="space-y-5 p-6">
    <header><p class="text-xs font-semibold tracking-widest text-emerald-600">WEBSITE MANAGEMENT</p><h1 class="text-2xl font-semibold">SEO Management</h1><p class="text-sm text-slate-500">Manage indexability and page-level search metadata for the public website.</p></header>
    <section class="grid gap-4 md:grid-cols-3">
      <article v-for="item in checks" :key="item.title" class="rounded-xl border bg-white p-5">
        <h2 class="font-semibold">{{ item.title }}</h2><p class="my-3 text-sm text-slate-500">{{ item.detail }}</p><a :href="item.href" target="_blank" class="text-sm font-medium text-blue-600">{{ item.action }} ↗</a>
      </article>
    </section>
    <p class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Search engine performance data (impressions, clicks and rankings) is not available until a Search Console provider is connected. This page currently manages on-site SEO controls and public crawl resources.</p>
  </main>
</template>
