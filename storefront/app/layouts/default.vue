<script setup lang="ts">
type Category = {
  id: number
  name: string
  slug: string
  image_url: string | null
  children: Category[]
}

const config = useRuntimeConfig()
const { locale } = useStorefrontLocale()
useHead({ htmlAttrs: { lang: computed(() => locale.value === 'bn' ? 'bn' : 'en') } })
const { data } = await useFetch<{ data: Category[] }>(`${config.public.apiBase}/categories`, {
  key: 'storefront-shared-categories',
  default: () => ({ data: [] }),
})
const categories = computed(() => data.value?.data || [])
</script>

<template>
  <div class="storefront-shell">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <SiteHeader :categories="categories" />
    <div id="main-content" class="storefront-content" tabindex="-1">
      <slot />
    </div>
    <SiteFooter />
    <StorefrontChatLauncher />
    <MobileBottomNavigation />
  </div>
</template>
