

<script setup lang="ts">
type Category = {
  id: number
  name: string
  slug: string
  image_url: string | null
  children: Category[]
}

const props = defineProps<{ categories: Category[] }>()
const route = useRoute()
const search = ref(String(route.query.q || ''))
type SearchProduct = { id: number; name: string; slug: string; image_url: string | null; price: number | string | null; regular_price: number | string | null }
const searchResults = ref<SearchProduct[]>([])
const searchLoading = ref(false)
const searchOpen = ref(false)
const searchCategories = computed(() => props.categories.filter(category => search.value.trim().length > 1 && category.name.toLowerCase().includes(search.value.trim().toLowerCase())).slice(0, 3))
let searchTimer: ReturnType<typeof setTimeout> | undefined
let searchRequestId = 0
const config = useRuntimeConfig()
const mobileOpen = ref(false)
const categoriesOpen = ref(false)
const { locale, t } = useStorefrontLocale()
const { itemCount, refresh } = useStorefrontCart()
const { customer, refreshCustomer, firstName } = useStorefrontCustomer()

watch(() => route.query.q, value => {
  search.value = String(value || '')
})

watch(search, value => {
  if (searchTimer) clearTimeout(searchTimer)
  const query = value.trim()
  const normalizedQuery = query.toLocaleLowerCase()
  const requestId = ++searchRequestId
  searchResults.value = []
  if (query.length < 2) { searchLoading.value = false; return }
  searchLoading.value = true
  searchTimer = setTimeout(async () => {
    try {
      // The Laravel catalogue uses case-insensitive matching (ILIKE).
      // Keep a normalized key for stale-response checks regardless of input casing.
      const response = await $fetch<{ data: SearchProduct[] }>(`${config.public.apiBase}/products`, { query: { q: query, page: 1 } })
      if (requestId === searchRequestId && search.value.trim().toLocaleLowerCase() === normalizedQuery) {
        searchResults.value = response.data || []
      }
    } catch {
      if (requestId === searchRequestId) searchResults.value = []
    } finally {
      if (requestId === searchRequestId) searchLoading.value = false
    }
  }, 180)
})

watch(() => route.fullPath, () => {
  mobileOpen.value = false
  categoriesOpen.value = false
})

onMounted(() => {
  if (!['/cart', '/checkout'].includes(route.path)) refresh().catch(() => undefined)
  refreshCustomer().catch(() => undefined)
  document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => { document.removeEventListener('keydown', handleEscape); if (searchTimer) clearTimeout(searchTimer) })

function handleEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    categoriesOpen.value = false
    mobileOpen.value = false
    searchOpen.value = false
  }
}

async function submitSearch() {
  const q = search.value.trim()
  mobileOpen.value = false
  categoriesOpen.value = false
  searchOpen.value = false
  await navigateTo(q ? { path: '/products', query: { q } } : '/products')
}

async function openProduct(product: SearchProduct) {
  // Navigate explicitly on selection so the input blur timer cannot swallow the result click.
  searchOpen.value = false
  mobileOpen.value = false
  categoriesOpen.value = false
  await navigateTo({ path: `/products/${encodeURIComponent(product.slug)}` })
}

function categoryUrl(category: Category) {
  return { path: '/products', query: { category: category.slug } }
}

function closeMenus() {
  categoriesOpen.value = false
  mobileOpen.value = false
}

function closeSearch() {
  setTimeout(() => { searchOpen.value = false }, 180)
}

function setLocale(value: 'en' | 'bn') {
  locale.value = value
}
</script>

<template>
  <header class="site-header">
    <div class="utility-bar">
      <div class="container utility-bar__inner">
        <NuxtLink to="/track-order" class="utility-track-link">
          <UiIcon name="package-search" :size="15" />
          {{ t('Track Order', 'অর্ডার ট্র্যাক') }}
        </NuxtLink>
        <div class="language-switcher" role="group" :aria-label="t('Choose language', 'ভাষা নির্বাচন করুন')">
          <button type="button" :class="{ 'is-active': locale === 'en' }" :aria-pressed="locale === 'en'" @click="setLocale('en')">EN</button>
          <button type="button" :class="{ 'is-active': locale === 'bn' }" :aria-pressed="locale === 'bn'" @click="setLocale('bn')">বাংলা</button>
        </div>
      </div>
    </div>

    <div class="header-main">
      <div class="container header-main__inner">
        <button
          class="icon-button mobile-menu-button"
          type="button"
          :aria-expanded="mobileOpen"
          :aria-label="mobileOpen ? t('Close navigation menu', 'মেনু বন্ধ করুন') : t('Open navigation menu', 'মেনু খুলুন')"
          @click="mobileOpen = !mobileOpen; categoriesOpen = false"
        >
          <UiIcon :name="mobileOpen ? 'x' : 'menu'" :size="22" />
        </button>

        <NuxtLink to="/" class="brand-lockup" aria-label="MADINA Scale home">
          <img src="/brand-mark.png" alt="" width="48" height="48">
          <span class="brand-lockup__text">MADINA<span>SCALE</span></span>
        </NuxtLink>

        <div class="header-search-wrap">
        <form class="header-search" role="search" @submit.prevent="submitSearch">
          <label class="sr-only" for="storefront-search">{{ t('Search products by name', 'পণ্যের নাম দিয়ে খুঁজুন') }}</label>
          <input
            id="storefront-search"
            @focus="searchOpen = true"
            @input="searchOpen = true"
            @keydown.esc="searchOpen = false" @blur="closeSearch"
            v-model="search"
            type="search"
            autocomplete="off"
            :placeholder="t('Search products, categories and brands…', 'পণ্য, ক্যাটাগরি ও ব্র্যান্ড খুঁজুন…')"
          >
          <button type="submit" :aria-label="t('Search products', 'পণ্য খুঁজুন')">
            <UiIcon name="search" :size="21" />
          </button>
        </form>
        <div v-if="searchOpen && search.trim().length > 1" class="search-suggestions">
          <div class="search-suggestions__top"><span>{{ t('Live catalogue results', 'লাইভ ক্যাটালগ ফলাফল') }}</span><small v-if="searchLoading">{{ t('Searching…', 'খোঁজা হচ্ছে…') }}</small><small v-else>{{ searchResults.length }} {{ t('products', 'টি পণ্য') }}</small></div>
          <NuxtLink v-for="product in searchResults.slice(0, 6)" :key="product.id" :to="`/products/${product.slug}`" class="search-suggestion" @pointerdown.prevent @click.prevent.stop="openProduct(product)">
            <span class="search-suggestion__image"><img v-if="product.image_url" :src="product.image_url" :alt="''" loading="lazy"><UiIcon v-else name="scale" :size="20" /></span>
            <span class="search-suggestion__copy"><strong>{{ product.name }}</strong><small>{{ product.price != null ? `৳${Number(product.price).toLocaleString('en-BD')}` : t('View product details', 'পণ্যের বিস্তারিত দেখুন') }}</small></span>
            <UiIcon name="arrow-up-right" :size="16" />
          </NuxtLink>
          <NuxtLink v-for="category in searchCategories" :key="`category-${category.id}`" :to="categoryUrl(category)" class="search-suggestion search-suggestion--category" @mousedown.prevent @click="searchOpen = false"><span class="search-suggestion__image"><UiIcon name="layout-grid" :size="18" /></span><span class="search-suggestion__copy"><strong>{{ category.name }}</strong><small>{{ t('Category', 'ক্যাটাগরি') }}</small></span></NuxtLink>
          <div v-if="!searchLoading && !searchResults.length && !searchCategories.length" class="search-suggestions__empty">{{ t('No matching products found.', 'মিল পাওয়া যায়নি।') }}</div>
          <button type="button" class="search-suggestions__all" @click="submitSearch">{{ t('View all search results', 'সব ফলাফল দেখুন') }} →</button>
        </div>
        </div>

        <NuxtLink
          :to="customer ? '/account' : '/login'"
          class="header-login-link header-login-link--account"
          :aria-label="customer ? `${t('Customer account', 'কাস্টমার অ্যাকাউন্ট')}: ${customer.name}` : t('Sign in or sign up', 'সাইন ইন অথবা সাইন আপ')"
          :title="customer ? customer.name : t('Sign in or sign up', 'সাইন ইন অথবা সাইন আপ')"
        >
          <UiIcon name="user-round" :size="19" />
          <span>{{ customer ? firstName : t('Sign In / Sign Up', 'সাইন ইন / সাইন আপ') }}</span>
        </NuxtLink>

        <NuxtLink to="/cart" class="header-cart" :aria-label="`${t('Shopping cart', 'শপিং কার্ট')}${itemCount ? `, ${itemCount} items` : ''}`">
          <span class="header-cart__icon"><UiIcon name="shopping-cart" :size="25" /></span>
          <span class="header-cart__label">{{ t('Cart', 'কার্ট') }}</span>
          <span v-if="itemCount" class="header-cart__count">{{ itemCount > 99 ? '99+' : itemCount }}</span>
        </NuxtLink>
      </div>
    </div>

    <nav class="main-nav" aria-label="Primary navigation">
      <div class="container main-nav__inner">
        <button
          class="category-toggle"
          type="button"
          :aria-expanded="categoriesOpen"
          aria-controls="desktop-category-menu"
          @click="categoriesOpen = !categoriesOpen"
        >
          <UiIcon name="layout-grid" :size="18" />
          <span>{{ t('All Categories', 'সকল ক্যাটাগরি') }}</span>
          <UiIcon name="chevron-down" :size="15" />
        </button>
        <NuxtLink to="/" class="main-nav__link">{{ t('Home', 'হোম') }}</NuxtLink>
        <NuxtLink to="/products" class="main-nav__link">{{ t('Products', 'পণ্যসমূহ') }}</NuxtLink>
        <NuxtLink to="/pages/special-offers" class="main-nav__link">{{ t('Special Offers', 'বিশেষ অফার') }}</NuxtLink>
        <NuxtLink to="/#flash-deals" class="main-nav__link main-nav__deals">
          <UiIcon name="zap" :size="16" /> {{ t('Flash Sales', 'ফ্ল্যাশ সেল') }}
        </NuxtLink>
        <NuxtLink to="/pages/warranty" class="main-nav__link">{{ t('Warranty', 'ওয়ারেন্টি') }}</NuxtLink>
        <NuxtLink to="/pages/contact" class="main-nav__link">{{ t('Contact', 'যোগাযোগ') }}</NuxtLink>
      </div>

      <template v-if="categoriesOpen">
        <button class="mega-menu-backdrop" type="button" aria-label="Close categories" @click="categoriesOpen = false" />
        <div id="desktop-category-menu" class="mega-menu">
          <div class="container mega-menu__inner">
            <div class="mega-menu__heading">
              <div>
                <span class="mega-menu__eyebrow">MADINA CATALOGUE</span>
                <h2>{{ t('Explore categories', 'ক্যাটাগরি দেখুন') }}</h2>
              </div>
              <NuxtLink to="/products" class="mega-menu__all" @click="closeMenus">{{ t('View all products', 'সব পণ্য দেখুন') }} <span aria-hidden="true">→</span></NuxtLink>
            </div>
            <div v-if="categories.length" class="mega-menu__grid">
              <section v-for="category in categories" :key="category.id" class="mega-category">
                <NuxtLink class="mega-category__title" :to="categoryUrl(category)" @click="closeMenus">
                  <span class="mega-category__icon">
                    <img v-if="category.image_url" :src="category.image_url" alt="" loading="lazy">
                    <UiIcon v-else name="layout-grid" :size="18" />
                  </span>
                  <span>{{ category.name }}</span>
                  <UiIcon name="arrow-right" :size="15" />
                </NuxtLink>
                <div v-if="category.children?.length" class="mega-category__children">
                  <NuxtLink v-for="child in category.children" :key="child.id" :to="categoryUrl(child)" @click="closeMenus">
                    {{ child.name }}
                  </NuxtLink>
                </div>
                <NuxtLink v-else class="mega-category__browse" :to="categoryUrl(category)" @click="closeMenus">{{ t('Browse products', 'পণ্য দেখুন') }} <span>→</span></NuxtLink>
              </section>
            </div>
            <div v-else class="mega-menu__empty">
              <UiIcon name="layout-grid" :size="22" />
              <span>{{ t('Published categories will appear here.', 'প্রকাশিত ক্যাটাগরি এখানে দেখা যাবে।') }}</span>
              <NuxtLink to="/products" @click="closeMenus">{{ t('Browse products', 'পণ্য দেখুন') }}</NuxtLink>
            </div>
          </div>
        </div>
      </template>
    </nav>

    <div v-if="mobileOpen" class="mobile-navigation">
      <div class="container mobile-navigation__inner">
        <nav class="mobile-navigation__primary" aria-label="Mobile primary navigation">
          <NuxtLink to="/" @click="closeMenus">{{ t('Home', 'হোম') }}</NuxtLink>
          <NuxtLink to="/products" @click="closeMenus">{{ t('Products', 'পণ্যসমূহ') }}</NuxtLink>
          <NuxtLink to="/pages/special-offers" @click="closeMenus">{{ t('Special Offers', 'বিশেষ অফার') }}</NuxtLink>
          <NuxtLink to="/#flash-deals" @click="closeMenus">{{ t('Flash Sales', 'ফ্ল্যাশ সেল') }}</NuxtLink>
          <NuxtLink to="/pages/warranty" @click="closeMenus">{{ t('Warranty', 'ওয়ারেন্টি') }}</NuxtLink>
          <NuxtLink to="/pages/contact" @click="closeMenus">{{ t('Contact', 'যোগাযোগ') }}</NuxtLink>
          <NuxtLink to="/track-order" @click="closeMenus">{{ t('Track Order', 'অর্ডার ট্র্যাক') }}</NuxtLink>
        </nav>
        <div class="mobile-navigation__categories">
          <h2>{{ t('Shop by category', 'ক্যাটাগরি অনুযায়ী কিনুন') }}</h2>
          <details v-for="category in categories" :key="category.id" class="mobile-category">
            <summary>{{ category.name }} <span>{{ category.children?.length || 0 }}</span></summary>
            <div>
              <NuxtLink :to="categoryUrl(category)" @click="closeMenus">{{ t('All', 'সব') }} {{ category.name }}</NuxtLink>
              <NuxtLink v-for="child in category.children" :key="child.id" :to="categoryUrl(child)" @click="closeMenus">{{ child.name }}</NuxtLink>
            </div>
          </details>
        </div>
      </div>
    </div>
  </header>
</template>


