<script setup lang="ts">
const route = useRoute()
const { t } = useStorefrontLocale()
const { itemCount } = useStorefrontCart()

// Shared with SiteHeader so the More tab opens the existing mobile menu.
const mobileNavigationOpen = useState<boolean>(
  'storefront-mobile-navigation-open',
  () => false,
)

const isHomeActive = computed(() => route.path === '/')
const isCategoriesActive = computed(() => route.path === '/products')
const isCartActive = computed(() => ['/cart', '/checkout'].includes(route.path))
const isAccountActive = computed(() =>
  ['/account', '/login', '/register'].includes(route.path),
)

function toggleMore() {
  mobileNavigationOpen.value = !mobileNavigationOpen.value
}
</script>

<template>
  <nav class="mobile-bottom-nav" :aria-label="t('Quick navigation', 'দ্রুত নেভিগেশন')">
    <NuxtLink
      to="/"
      class="mobile-bottom-nav__item"
      :class="{ 'is-active': isHomeActive }"
      :aria-current="isHomeActive ? 'page' : undefined"
      @click="mobileNavigationOpen = false"
    >
      <UiIcon name="layout-grid" :size="21" />
      <span>{{ t('Home', 'হোম') }}</span>
    </NuxtLink>

    <NuxtLink
      to="/products"
      class="mobile-bottom-nav__item"
      :class="{ 'is-active': isCategoriesActive }"
      :aria-current="isCategoriesActive ? 'page' : undefined"
      @click="mobileNavigationOpen = false"
    >
      <UiIcon name="boxes" :size="21" />
      <span>{{ t('Categories', 'ক্যাটাগরি') }}</span>
    </NuxtLink>

    <NuxtLink
      to="/cart"
      class="mobile-bottom-nav__item mobile-bottom-nav__item--cart"
      :class="{ 'is-active': isCartActive }"
      :aria-current="isCartActive ? 'page' : undefined"
      :aria-label="`${t('Cart', 'কার্ট')}${itemCount ? `, ${itemCount} ${t('items', 'টি পণ্য')}` : ''}`"
      @click="mobileNavigationOpen = false"
    >
      <span class="mobile-bottom-nav__cart-icon">
        <UiIcon name="shopping-cart" :size="22" />
        <span v-if="itemCount" class="mobile-bottom-nav__badge">
          {{ itemCount > 99 ? '99+' : itemCount }}
        </span>
      </span>
      <span>{{ t('Cart', 'কার্ট') }}</span>
    </NuxtLink>

    <NuxtLink
      to="/account"
      class="mobile-bottom-nav__item"
      :class="{ 'is-active': isAccountActive }"
      :aria-current="isAccountActive ? 'page' : undefined"
      @click="mobileNavigationOpen = false"
    >
      <UiIcon name="user-round" :size="21" />
      <span>{{ t('Account', 'অ্যাকাউন্ট') }}</span>
    </NuxtLink>

    <button
      type="button"
      class="mobile-bottom-nav__item"
      :class="{ 'is-active': mobileNavigationOpen }"
      :aria-expanded="mobileNavigationOpen"
      aria-controls="mobile-navigation"
      @click="toggleMore"
    >
      <UiIcon :name="mobileNavigationOpen ? 'x' : 'menu'" :size="21" />
      <span>{{ t('More', 'আরও') }}</span>
    </button>
  </nav>
</template>

<style scoped>
.mobile-bottom-nav {
  display: none;
}

@media (max-width: 767px) {
  .mobile-bottom-nav {
    position: fixed;
    z-index: 90;
    right: 0;
    bottom: 0;
    left: 0;
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    align-items: end;
    min-height: 66px;
    padding: 7px 7px calc(7px + env(safe-area-inset-bottom, 0px));
    border-top: 1px solid rgba(16, 24, 40, 0.08);
    background: rgba(255, 255, 255, 0.97);
    box-shadow: 0 -7px 24px rgba(16, 24, 40, 0.08);
    -webkit-backdrop-filter: blur(16px);
    backdrop-filter: blur(16px);
  }

  .mobile-bottom-nav__item {
    position: relative;
    display: flex;
    min-width: 0;
    min-height: 49px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    border: 0;
    border-radius: 12px;
    background: transparent;
    color: #667085;
    font: inherit;
    text-decoration: none;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
  }

  .mobile-bottom-nav__item > span:last-child {
    max-width: 100%;
    overflow: hidden;
    color: inherit;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.2;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .mobile-bottom-nav__item > svg {
    flex: 0 0 auto;
  }

  .mobile-bottom-nav__item.is-active {
    color: var(--brand-700, #087a32);
  }

  .mobile-bottom-nav__item.is-active > span:last-child {
    font-weight: 850;
  }

  .mobile-bottom-nav__item:focus-visible {
    outline: 2px solid var(--brand-700, #087a32);
    outline-offset: -2px;
  }

  .mobile-bottom-nav__item--cart {
    gap: 2px;
  }

  .mobile-bottom-nav__cart-icon {
    position: relative;
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    margin-top: -17px;
    border: 4px solid #fff;
    border-radius: 50%;
    background: var(--brand-700, #087a32);
    color: #fff;
    box-shadow: 0 5px 14px rgba(8, 122, 50, 0.25);
  }

  .mobile-bottom-nav__item--cart > span:last-child {
    margin-top: -1px;
  }

  .mobile-bottom-nav__badge {
    position: absolute;
    top: -3px;
    right: -5px;
    display: grid;
    min-width: 17px;
    height: 17px;
    place-items: center;
    padding-inline: 4px;
    border: 2px solid #fff;
    border-radius: 999px;
    background: #e53935;
    color: #fff;
    font-size: 9px;
    font-weight: 850;
    line-height: 1;
  }

  .mobile-bottom-nav__item--cart.is-active .mobile-bottom-nav__cart-icon {
    background: var(--brand-900, #034b25);
  }
}

@media (max-width: 360px) {
  .mobile-bottom-nav {
    padding-inline: 4px;
  }

  .mobile-bottom-nav__item {
    gap: 3px;
  }

  .mobile-bottom-nav__item > span:last-child {
    font-size: 9px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .mobile-bottom-nav,
  .mobile-bottom-nav * {
    scroll-behavior: auto;
    transition: none !important;
  }
}
</style>
