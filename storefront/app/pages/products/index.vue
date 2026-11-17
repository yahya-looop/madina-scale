<script setup lang="ts">
type Product = {
  id: number
  name: string
  slug: string
  category: string | null
  image_url: string | null
  price: number | string | null
  regular_price: number | string | null
  has_flash_sale: boolean
  default_variant_id: number | null
}

const route = useRoute()
const config = useRuntimeConfig()
const category = computed(() => String(route.query.category ?? ''))
const query = computed(() => String(route.query.q ?? ''))
const page = computed(() => Math.max(1, Number(route.query.page ?? 1) || 1))
const { data: categoriesData } = useFetch<{ data: { id: number; name: string; slug: string }[] }>(`${config.public.apiBase}/categories`, { key: 'storefront-shared-categories', default: () => ({ data: [] }) })
const { data, error, refresh } = await useFetch<{ data: Product[]; current_page: number; last_page: number; total: number }>(
  `${config.public.apiBase}/products`,
  {
    key: 'storefront-product-list',
    query: computed(() => ({ category: category.value || undefined, q: query.value || undefined, page: page.value })),
    watch: [category, query, page],
    default: () => ({ data: [], current_page: 1, last_page: 1, total: 0 }),
  },
)

const categoryName = computed(() => categoriesData.value?.data.find(item => item.slug === category.value)?.name || '')
const title = computed(() => category.value ? categoryName.value || 'Category products' : query.value ? `Search results for “${query.value}”` : 'All Products')
const paginationQuery = (nextPage: number) => ({ ...route.query, page: nextPage })

useSeoMeta({
  title: () => `${title.value} | MADINA Scale`,
  description: 'Browse digital weighing scales, commercial scales and professional weighing solutions from MADINA Scale, Bangladesh.',
  ogTitle: () => `${title.value} | MADINA Scale`,
  ogType: 'website',
})
</script>

<template>
  <main class="container catalogue-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><NuxtLink to="/">Home</NuxtLink><span aria-hidden="true">/</span><span>{{ title }}</span></nav>
    <div class="catalogue-heading">
      <div><p class="section-kicker">MADINA SCALE CATALOGUE</p><h1>{{ title }}</h1></div>
      <span class="catalogue-count">{{ data?.total ?? 0 }} products</span>
    </div>

    <div v-if="error" class="notice-error" role="alert">
      <p>Unable to load products. Please check your connection and try again.</p>
      <button type="button" class="retry-button" @click="refresh()">Try again</button>
    </div>
    <div v-else-if="data?.data?.length" class="product-grid catalogue-grid">
      <ProductCard v-for="product in data.data" :key="product.id" :product="product" />
    </div>
    <div v-else class="empty-state catalogue-empty">
      <h2>No products found</h2>
      <p>Try another search or browse the full catalogue.</p>
      <NuxtLink to="/products" class="btn-primary">Clear filters</NuxtLink>
    </div>

    <nav v-if="data && data.last_page > 1" class="catalogue-pagination" aria-label="Product pages">
      <NuxtLink v-if="data.current_page > 1" :to="{ query: paginationQuery(data.current_page - 1) }">Previous</NuxtLink>
      <span>Page {{ data.current_page }} of {{ data.last_page }}</span>
      <NuxtLink v-if="data.current_page < data.last_page" :to="{ query: paginationQuery(data.current_page + 1) }">Next</NuxtLink>
    </nav>
  </main>
</template>
