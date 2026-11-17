<script setup lang="ts">
type CmsPage = {
  title: string
  slug: string
  excerpt: string | null
  content: string | null
  featuredImage: string | null
  seo: {
    title: string
    description: string | null
    canonical: string
    index: boolean
    follow: boolean
  }
}

const route = useRoute()
const config = useRuntimeConfig()
const { data, error } = await useFetch<{ data: { page: CmsPage } }>(
  `${config.public.apiBase}/cms/pages/${encodeURIComponent(String(route.params.slug))}`,
)

if (error.value) {
  throw createError({
    statusCode: error.value.statusCode || 502,
    statusMessage: error.value.statusCode === 404 ? 'Page not found' : 'Unable to load this page',
  })
}

const page = computed(() => data.value?.data.page)

if (!page.value) {
  throw createError({ statusCode: 404, statusMessage: 'Page not found' })
}

useSeoMeta({
  title: () => page.value?.seo.title || page.value?.title || 'MADINA Scale',
  description: () => page.value?.seo.description || page.value?.excerpt || '',
  ogTitle: () => page.value?.seo.title || page.value?.title || 'MADINA Scale',
  robots: () => `${page.value?.seo.index ? 'index' : 'noindex'}, ${page.value?.seo.follow ? 'follow' : 'nofollow'}`,
})
</script>

<template>
  <CmsPageContent v-if="page" :page="page" />
</template>
