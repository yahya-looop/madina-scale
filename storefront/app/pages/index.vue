<script setup lang="ts">
type Banner = {
  id: number
  title: string
  image_url: string
  alt_text: string | null
  link_url: string | null
  placement: string
}
type Category = {
  id: number
  name: string
  slug: string
  image_url: string | null
  children: Category[]
}
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
type FlashSaleItem = {
  id: number
  product_name: string
  product_slug: string
  variant_name: string
  sku: string | null
  regular_price: number | string
  sale_price: number | string
  quantity_remaining: number | null
  image_url?: string | null
  product_variant_id?: number | null
}
type FlashSale = {
  id: number
  title: string
  slug: string
  tagline: string | null
  description: string | null
  banner_image: string | null
  accent_color: string
  starts_at: string
  ends_at: string
  items: FlashSaleItem[]
}
type FlashDealEntry = FlashSaleItem & {
  saleTitle: string
  endsAt: string
  product: Product | null
}

const config = useRuntimeConfig()
const [bannerResponse, categoryResponse, productResponse, flashSaleResponse] = await Promise.all([
  useFetch<{ data: Banner[] }>(`${config.public.apiBase}/cms/banners`, {
    key: 'storefront-home-banners',
    query: { placement: 'homepage' },
    default: () => ({ data: [] }),
  }),
  useFetch<{ data: Category[] }>(`${config.public.apiBase}/categories`, {
    key: 'storefront-shared-categories',
    default: () => ({ data: [] }),
  }),
  useFetch<{ data: Product[]; total: number }>(`${config.public.apiBase}/products`, {
    key: 'storefront-home-products',
    query: { page: 1 },
    default: () => ({ data: [], total: 0 }),
  }),
  useFetch<{ data: FlashSale[] }>(`${config.public.apiBase}/flash-sales`, {
    key: 'storefront-home-flash-sales',
    // Keep Nuxt's SSR payload hydration cache enabled. The API sends no-store
    // headers, so this avoids a duplicate hydration request without persisting stale offers.
    default: () => ({ data: [] }),
  }),
])

const banners = computed(() => bannerResponse.data.value?.data || [])
const categories = computed(() => categoryResponse.data.value?.data || [])
const products = computed(() => productResponse.data.value?.data || [])
const flashSales = computed(() => flashSaleResponse.data.value?.data || [])
const powerToolsCategory = computed(() =>
  categories.value.find(category =>
    /power[- ]?tools/i.test(`${category.name} ${category.slug}`)
  )
)
const digitalScaleCategory = computed(() =>
  categories.value.find(category =>
    category.id !== powerToolsCategory.value?.id &&
    /digital.*scale|scale.*digital/i.test(`${category.name} ${category.slug}`)
  )
)
const productBySlug = computed(() => new Map(products.value.map(product => [product.slug, product])))
const flashDealEntries = computed<FlashDealEntry[]>(() => flashSales.value.flatMap(sale =>
  (sale.items || []).map(item => ({
    ...item,
    saleTitle: sale.title,
    endsAt: sale.ends_at,
    product: productBySlug.value.get(item.product_slug) || null,
  })),
))
const hasHomeDataError = computed(() => Boolean(
  bannerResponse.error.value || categoryResponse.error.value ||
  productResponse.error.value || flashSaleResponse.error.value,
))
const activeBannerIndex = ref(0)
const activeBanner = computed(() => banners.value[activeBannerIndex.value] || null)

/**
 * Recover from a transient empty SSR response once the page is mounted.
 * The endpoint is no-store, and this runs only when no flash-deal items arrived.
 */
onMounted(() => {
  if (!flashDealEntries.value.length && !flashSaleResponse.error.value) {
    void flashSaleResponse.refresh()
  }
})
const trackingPrefill = useState<{ reference: string; phone: string }>('madina-track-order-prefill', () => ({ reference: '', phone: '' }))
const trackingReference = ref('')
const trackingPhone = ref('')
const newsletterContact = ref('')
const newsletterMessage = ref('')
async function requestUpdates() {
  const value = newsletterContact.value.trim()
  if (!value) return
  try {
    const response = await $fetch<{ data: { phone: string | null; email: string | null; channels: string[] } }>(`${config.public.apiBase}/support/contact`)
    const support = response.data
    const digits = (support.phone || '').replace(/\D/g, '')
    const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
    const isPhone = /^(?:\+?88)?01[3-9]\d{8}$/.test(value.replace(/[\s-]/g, ''))
    const message = `Please add me to MADINA product and offer updates. My preferred contact is: ${value}`
    if (isEmail && support.email && support.channels.includes('email')) {
      window.location.href = `mailto:${support.email}?subject=${encodeURIComponent('MADINA updates request')}&body=${encodeURIComponent(message)}`
      newsletterMessage.value = 'Your email app is ready. Send the message to complete your request.'
    } else if (isPhone && digits && support.channels.includes('chat')) {
      window.location.href = `https://wa.me/${digits}?text=${encodeURIComponent(message)}`
      newsletterMessage.value = 'WhatsApp is ready. Send the message to complete your request.'
    } else {
      newsletterMessage.value = 'Please enter a valid email or Bangladesh mobile number. If support contact is not configured, use Live Chat.'
    }
  } catch {
    newsletterMessage.value = 'Update requests are not connected yet. Please contact MADINA through Live Chat.'
  }
}

async function submitTracking() {
  trackingPrefill.value = { reference: trackingReference.value.trim(), phone: trackingPhone.value.trim() }
  await navigateTo('/track-order')
}

watch(banners, value => {
  if (activeBannerIndex.value >= value.length) activeBannerIndex.value = 0
})

function categoryUrl(category: Category) {
  return { path: '/products', query: { category: category.slug } }
}

function money(value: number | string | null | undefined) {
  if (value === null || value === undefined) return 'Contact for price'
  const amount = Number(value)
  return Number.isFinite(amount)
    ? `৳${amount.toLocaleString('en-BD', { maximumFractionDigits: 2 })}`
    : 'Contact for price'
}

function formattedEnd(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'See offer details'
  return new Intl.DateTimeFormat('en-BD', {
    day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit',
    timeZone: 'Asia/Dhaka',
  }).format(date)
}

function discountPercent(regular: number | string, sale: number | string) {
  const regularAmount = Number(regular)
  const saleAmount = Number(sale)
  if (!Number.isFinite(regularAmount) || regularAmount <= 0 || saleAmount < 0 || saleAmount >= regularAmount) return null
  return Math.round(((regularAmount - saleAmount) / regularAmount) * 100)
}

function bannerIsExternal(url: string | null) {
  return Boolean(url && /^https?:\/\//i.test(url))
}

function previousBanner() {
  if (banners.value.length < 2) return
  activeBannerIndex.value = (activeBannerIndex.value - 1 + banners.value.length) % banners.value.length
}

function nextBanner() {
  if (banners.value.length < 2) return
  activeBannerIndex.value = (activeBannerIndex.value + 1) % banners.value.length
}

useSeoMeta({
  title: 'MADINA Scale | Weighing Solutions',
  description: 'Explore digital weighing scales and professional weighing solutions from MADINA Scale.',
  ogTitle: 'MADINA Scale | Weighing Solutions',
  ogDescription: 'Explore the MADINA Scale product catalogue and current offers.',
  ogType: 'website',
})
</script>

<template>
  <main class="home-page">
    <!-- Hero: CMS banner and copy remain server-data driven. -->
    <section class="home-hero" aria-label="MADINA Scale featured products">
      <div class="container">
        <div class="home-hero__panel">
          <div class="home-hero__copy">
            <p class="eyebrow">PROFESSIONAL WEIGHING SOLUTIONS</p>
            <h1>{{ activeBanner?.title || 'Digital Weighing Scales for Every Business' }}</h1>
            <p class="home-hero__description">
              Explore weighing solutions for shops, businesses and professional use from the MADINA catalogue.
            </p>
            <div class="home-hero__actions">
              <a
                v-if="activeBanner && bannerIsExternal(activeBanner.link_url)"
                class="button-solid"
                :href="activeBanner.link_url || undefined"
                target="_blank"
                rel="noopener noreferrer"
              >Shop Now <span aria-hidden="true">→</span></a>
              <NuxtLink
                v-else-if="activeBanner?.link_url"
                class="button-solid"
                :to="activeBanner.link_url"
              >Shop Now <span aria-hidden="true">→</span></NuxtLink>
              <NuxtLink v-else class="button-solid" to="/products">Shop Now <span aria-hidden="true">→</span></NuxtLink>
              <NuxtLink class="button-outline" to="/products">Explore catalogue</NuxtLink>
            </div>
          </div>

          <div class="home-hero__art">
            <StorefrontImage
              :key="activeBanner?.image_url || 'hero-fallback'"
              :src="activeBanner?.image_url"
              :alt="activeBanner?.alt_text || activeBanner?.title || 'MADINA weighing solutions banner'"
              kind="hero"
              label="Home Hero Banner"
              dimensions="1920 × 600 px"
              ratio="3.2 / 1"
              :width="1920"
              :height="600"
              loading="eager"
              priority="high"
              fit="contain"
            />
          </div>

          <div v-if="banners.length > 1" class="home-hero__carousel" aria-label="Featured banner controls">
            <button type="button" aria-label="Previous banner" @click="previousBanner">
              <span aria-hidden="true">‹</span>
            </button>
            <div class="home-hero__dots">
              <button
                v-for="(banner, index) in banners"
                :key="banner.id"
                type="button"
                :class="{ 'is-active': index === activeBannerIndex }"
                :aria-label="`Show banner ${index + 1}`"
                :aria-current="index === activeBannerIndex ? 'true' : undefined"
                @click="activeBannerIndex = index"
              />
            </div>
            <button type="button" aria-label="Next banner" @click="nextBanner">
              <span aria-hidden="true">›</span>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Trust signals: customer-facing facts supplied by MADINA. -->
    <section class="service-highlights container" aria-label="Why customers choose MADINA">
      <article class="trust-highlight">
        <span class="trust-highlight__icon"><UiIcon name="target" :size="25" /></span>
        <div class="trust-highlight__copy">
          <strong>Since 2008</strong>
          <small>Serving weighing needs for years</small>
        </div>
      </article>
      <article class="trust-highlight">
        <span class="trust-highlight__icon"><UiIcon name="truck" :size="25" /></span>
        <div class="trust-highlight__copy">
          <strong>All 64 Districts</strong>
          <small>Dealer points across Bangladesh</small>
        </div>
      </article>
      <article class="trust-highlight">
        <span class="trust-highlight__icon"><UiIcon name="headset" :size="25" /></span>
        <div class="trust-highlight__copy">
          <strong>Dedicated Support</strong>
          <small>Helpful customer assistance</small>
        </div>
      </article>
      <article class="trust-highlight">
        <span class="trust-highlight__icon"><UiIcon name="shield-check" :size="25" /></span>
        <div class="trust-highlight__copy">
          <strong>After-Sales Care</strong>
          <small>Warranty guidance and support</small>
        </div>
      </article>
    </section>

    <!-- Only currently active, API-published sale items are displayed. -->
    <section id="flash-deals" class="content-section flash-deals-section">
      <div class="container">
        <div class="section-heading">
          <div class="section-heading__title">
            <h2><UiIcon name="zap" :size="25" /> Flash Deals</h2>
            <span aria-hidden="true" />
            <p>Limited-time offers from the MADINA catalogue</p>
          </div>
          <span class="flash-deals-section__note">Offer end times are shown in Bangladesh time</span>
        </div>

        <div
          v-if="flashSaleResponse.pending.value && !flashDealEntries.length"
          class="home-empty-state"
          role="status"
        >
          <UiIcon name="zap" :size="27" />
          <div>
            <strong>Loading current Flash Deals…</strong>
            <p>Checking the latest approved offers.</p>
          </div>
        </div>
        <div v-else-if="flashDealEntries.length" class="flash-deal-grid">
          <article v-for="item in flashDealEntries.slice(0, 5)" :key="item.id" class="flash-deal-card">
              <NuxtLink :to="`/products/${item.product_slug}`" class="flash-deal-card__image">
                <span
                  v-if="discountPercent(item.regular_price, item.sale_price)"
                  class="flash-deal-card__discount"
                >-{{ discountPercent(item.regular_price, item.sale_price) }}%</span>
                <StorefrontImage
                  :src="item.image_url || item.product?.image_url || null"
                  :alt="(item.image_url || item.product?.image_url) ? item.product_name : ''"
                  kind="product"
                  label="Flash Deal Product"
                  dimensions="800 × 800 px"
                  ratio="1 / 1"
                  :width="800"
                  :height="800"
                />
              </NuxtLink>
              <div class="flash-deal-card__body">
                <NuxtLink :to="`/products/${item.product_slug}`" class="flash-deal-card__name">{{ item.product_name }}</NuxtLink>
                <span v-if="item.variant_name" class="flash-deal-card__variant">{{ item.variant_name }}</span>
                <div class="flash-deal-card__prices">
                  <strong>{{ money(item.sale_price) }}</strong>
                  <del v-if="Number(item.regular_price) > Number(item.sale_price)">{{ money(item.regular_price) }}</del>
                </div>
                <span class="flash-deal-card__expiry">Ends {{ formattedEnd(item.endsAt) }}</span>
                <NuxtLink :to="`/products/${item.product_slug}`" class="flash-deal-card__action">View product <span aria-hidden="true">→</span></NuxtLink>
              </div>
          </article>
        </div>
        <div v-else class="home-empty-state">
          <UiIcon name="zap" :size="27" />
          <div>
            <strong>{{ flashSaleResponse.error.value ? 'Flash Deals could not be loaded' : 'No active Flash Deals right now' }}</strong>
            <p>{{ flashSaleResponse.error.value ? 'Approved offers will appear when the promotion service is available.' : 'Current approved offers will appear here automatically.' }}</p>
          </div>
          <NuxtLink to="/products">Explore products <span aria-hidden="true">→</span></NuxtLink>
        </div>
      </div>
    </section>

    <!-- Catalogue listing: no fabricated popularity/rating claims. -->
    <section class="content-section container home-products-section">
      <div class="section-heading">
        <div class="section-heading__title">
          <h2>Explore Products</h2>
          <span aria-hidden="true" />
        </div>
        <NuxtLink to="/products">View all products <span aria-hidden="true">→</span></NuxtLink>
      </div>
      <div v-if="products.length" class="product-grid">
        <ProductCard v-for="product in products.slice(0, 10)" :key="product.id" :product="product" />
      </div>
      <div v-else class="home-empty-state">
        <UiIcon name="scale" :size="27" />
        <div>
          <strong>{{ productResponse.error.value ? 'Products could not be loaded' : 'No published products to display' }}</strong>
          <p>{{ productResponse.error.value ? 'Please retry when the catalogue service is available.' : 'Active, visible catalogue products will appear here automatically.' }}</p>
        </div>
        <NuxtLink to="/products">Open catalogue <span aria-hidden="true">→</span></NuxtLink>
      </div>
    </section>

    <!-- Collection panels: Power Tools and Digital Scale. -->
    <section
      v-if="powerToolsCategory || digitalScaleCategory"
      class="collection-features container"
    >
      <NuxtLink
        v-if="powerToolsCategory"
        :to="categoryUrl(powerToolsCategory)"
        class="collection-feature collection-feature--tools"
      >
        <div>
          <p class="section-kicker">Explore the range</p>
          <h2>{{ powerToolsCategory.name }}</h2>
          <span>Shop collection <b aria-hidden="true">→</b></span>
        </div>
        <StorefrontImage
          :src="powerToolsCategory.image_url"
          :alt="powerToolsCategory.image_url ? powerToolsCategory.name : ''"
          kind="banner"
          label="Power Tools Collection"
          dimensions="1200 × 500 px"
          ratio="2.4 / 1"
          :width="1200"
          :height="500"
          fit="contain"
        />
      </NuxtLink>

      <NuxtLink
        v-if="digitalScaleCategory"
        :to="categoryUrl(digitalScaleCategory)"
        class="collection-feature collection-feature--ladder"
      >
        <div>
          <p class="section-kicker">Explore the range</p>
          <h2>{{ digitalScaleCategory.name }}</h2>
          <span>Shop collection <b aria-hidden="true">→</b></span>
        </div>
        <StorefrontImage
          :src="digitalScaleCategory.image_url"
          :alt="digitalScaleCategory.image_url ? digitalScaleCategory.name : ''"
          kind="banner"
          label="Digital Scale Collection"
          dimensions="1200 × 500 px"
          ratio="2.4 / 1"
          :width="1200"
          :height="500"
          fit="contain"
        />
      </NuxtLink>
    </section>

    <!-- MADINA value proposition: eight customer benefits, immediately before order tracking. -->
    <section class="value-section" aria-labelledby="madina-experience-title">
      <div class="container">
        <div class="value-section__intro">
          <p class="section-kicker">THE MADINA EXPERIENCE</p>
          <div class="section-heading">
            <div class="section-heading__title">
              <h2 id="madina-experience-title">Why Choose MADINA™?</h2>
              <span aria-hidden="true" />
            </div>
            <p>Everything you need to choose with confidence, from catalogue discovery to checkout.</p>
          </div>
        </div>
        <div class="value-grid">
          <article><span class="value-card__number">01</span><UiIcon name="target" :size="25" /><div><h3>Find the right solution</h3><p>Explore weighing equipment through clear product categories.</p></div><UiIcon class="value-card__arrow" name="arrow-up-right" :size="17" /></article>
          <article><span class="value-card__number">02</span><UiIcon name="layers" :size="25" /><div><h3>Compare product options</h3><p>Review published specifications, variants and pricing.</p></div><UiIcon class="value-card__arrow" name="arrow-up-right" :size="17" /></article>
          <article><span class="value-card__number">03</span><UiIcon name="shopping-cart" :size="25" /><div><h3>A simpler buying journey</h3><p>Keep selected products together in your online cart.</p></div><UiIcon class="value-card__arrow" name="arrow-up-right" :size="17" /></article>
          <article><span class="value-card__number">04</span><UiIcon name="headset" :size="25" /><div><h3>Support when you need it</h3><p>Find order tracking and customer support from one place.</p></div><UiIcon class="value-card__arrow" name="arrow-up-right" :size="17" /></article>
          <article><span class="value-card__number">05</span><UiIcon name="search" :size="25" /><div><h3>Easy catalogue discovery</h3><p>Browse the published catalogue to find suitable products.</p></div><UiIcon class="value-card__arrow" name="arrow-up-right" :size="17" /></article>
          <article><span class="value-card__number">06</span><UiIcon name="scale" :size="25" /><div><h3>Clear product information</h3><p>Review available product details before making a choice.</p></div><UiIcon class="value-card__arrow" name="arrow-up-right" :size="17" /></article>
          <article><span class="value-card__number">07</span><UiIcon name="truck" :size="25" /><div><h3>Order progress visibility</h3><p>Use order tracking to check the progress of your purchase.</p></div><UiIcon class="value-card__arrow" name="arrow-up-right" :size="17" /></article>
          <article><span class="value-card__number">08</span><UiIcon name="shield-check" :size="25" /><div><h3>Warranty information</h3><p>Find warranty details and contact MADINA for assistance.</p></div><UiIcon class="value-card__arrow" name="arrow-up-right" :size="17" /></article>
        </div>
      </div>
    </section>

    <section class="container home-track-panel" aria-labelledby="home-track-title">
      <div class="home-track-panel__intro">
        <div class="home-track-panel__icon"><UiIcon name="package-search" :size="28" /></div>
        <div class="home-track-panel__copy">
          <p class="section-kicker">CUSTOMER SUPPORT</p>
          <h2 id="home-track-title">Track Your Order</h2>
          <p>Enter your order number or courier tracking number with the mobile number used at checkout.</p>
        </div>
      </div>
      <form class="home-track-form" @submit.prevent="submitTracking">
        <label>
          <span>Order Number / Tracking Number</span>
          <input v-model="trackingReference" type="text" name="reference" autocomplete="off" placeholder="Enter your order number or courier tracking number" minlength="3" maxlength="160" required>
        </label>
        <label>
          <span>Mobile Number</span>
          <input v-model="trackingPhone" type="tel" name="phone" autocomplete="tel" inputmode="tel" placeholder="Enter checkout mobile number" minlength="8" maxlength="40" required>
        </label>
        <button type="submit" class="home-track-form__submit">Track Order <UiIcon name="arrow-right" :size="17" /></button>
      </form>
      <p class="home-track-panel__privacy">Your mobile number is used only to verify the order reference. It is not shown in tracking results.</p>
    </section>



    <section class="container home-newsletter" aria-labelledby="home-newsletter-title">
      <div class="home-newsletter__intro">
        <div class="home-newsletter__icon"><UiIcon name="send" :size="25" /></div>
        <div>
          <p class="section-kicker">MADINA UPDATES</p>
          <h2 id="home-newsletter-title">Stay in the loop</h2>
          <p>Leave your email or mobile number and request product and offer updates.</p>
        </div>
      </div>
      <form class="home-newsletter__form" @submit.prevent="requestUpdates">
        <label class="sr-only" for="newsletter-contact">Email address or mobile number</label>
        <input id="newsletter-contact" v-model="newsletterContact" type="text" autocomplete="email" placeholder="Email address or mobile number" required minlength="6" maxlength="190">
        <button type="submit">Request updates <UiIcon name="arrow-right" :size="16" /></button>
        <p v-if="newsletterMessage" class="home-newsletter__message" role="status">{{ newsletterMessage }}</p>
      </form>
    </section>





    <div v-if="hasHomeDataError" class="container home-data-notice" role="status">
      <span>Some storefront content could not be loaded.</span>
      <button type="button" @click="refreshNuxtData()">Try again</button>
    </div>
  </main>
</template>


<style scoped>
.value-section {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  padding: 42px 0 48px;
  background:
    radial-gradient(ellipse at 8% 8%, rgba(208, 244, 219, .28), transparent 38%),
    linear-gradient(135deg, #fbfefc 0%, #f5fbf6 48%, #eef8f1 100%);
}
.value-section::before,
.value-section::after {
  position: absolute;
  z-index: -1;
  content: "";
  pointer-events: none;
  border-radius: 50%;
}
.value-section::before {
  width: 360px;
  height: 220px;
  right: -130px;
  top: -130px;
  background: rgba(198, 239, 210, .24);
  filter: blur(3px);
}
.value-section::after {
  width: 280px;
  height: 180px;
  left: -150px;
  bottom: -130px;
  background: rgba(218, 245, 225, .32);
  filter: blur(3px);
}
.value-section__intro {
  position: relative;
  margin-bottom: 22px;
}
.value-section .section-kicker {
  margin: 0 0 10px;
  color: #078044;
  font-size: 10px;
  font-weight: 900;
  letter-spacing: .15em;
}
.value-section .section-heading {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 28px;
}
.value-section .section-heading__title {
  display: flex;
  align-items: center;
  gap: 16px;
}
.value-section .section-heading h2 {
  margin: 0;
  color: #142033;
  font-size: clamp(25px, 2.5vw, 32px);
  line-height: 1.12;
  font-weight: 900;
  letter-spacing: -.035em;
}
.value-section .section-heading__title > span {
  display: block;
  width: 34px;
  height: 3px;
  flex: 0 0 auto;
  border-radius: 99px;
  background: linear-gradient(90deg, #078044, #62c58a);
}
.value-section .section-heading > p {
  max-width: 430px;
  margin: 0;
  color: #718096;
  font-size: 13px;
  line-height: 1.65;
}
.value-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}
.value-grid > article {
  position: relative;
  isolation: isolate;
  display: grid;
  grid-template-columns: 38px minmax(0, 1fr) 17px;
  grid-template-rows: auto 1fr;
  align-items: center;
  column-gap: 13px;
  row-gap: 15px;
  min-width: 0;
  min-height: 164px;
  padding: 20px 19px;
  overflow: hidden;
  border: 1px solid rgba(184, 220, 195, .7);
  border-radius: 18px;
  background: linear-gradient(145deg, #ffffff 0%, #fbfefc 46%, #eff9f2 100%);
  box-shadow:
    0 7px 22px rgba(20, 75, 43, .045),
    inset 0 1px 0 rgba(255, 255, 255, .95);
  transition:
    transform .28s cubic-bezier(.2, .75, .25, 1),
    border-color .28s ease,
    box-shadow .28s ease,
    background .28s ease;
  animation: value-card-enter .58s cubic-bezier(.2, .7, .2, 1) both;
}
.value-grid > article::before {
  position: absolute;
  z-index: -1;
  content: "";
  width: 130px;
  height: 130px;
  right: -62px;
  bottom: -78px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(177, 231, 192, .32), rgba(177, 231, 192, 0) 70%);
  opacity: .7;
  transition: transform .35s ease, opacity .35s ease;
}
.value-grid > article::after {
  position: absolute;
  top: 0;
  left: 18px;
  right: 18px;
  height: 2px;
  border-radius: 0 0 99px 99px;
  content: "";
  background: linear-gradient(90deg, transparent, rgba(8, 128, 68, .32), transparent);
  opacity: .55;
}
.value-grid > article:nth-child(1) { animation-delay: 40ms; }
.value-grid > article:nth-child(2) { animation-delay: 90ms; }
.value-grid > article:nth-child(3) { animation-delay: 140ms; }
.value-grid > article:nth-child(4) { animation-delay: 190ms; }
.value-grid > article:nth-child(5) { animation-delay: 240ms; }
.value-grid > article:nth-child(6) { animation-delay: 290ms; }
.value-grid > article:nth-child(7) { animation-delay: 340ms; }
.value-grid > article:nth-child(8) { animation-delay: 390ms; }
.value-grid .value-card__number {
  grid-column: 1 / -1;
  grid-row: 1;
  color: #078044;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: .12em;
  line-height: 1;
}
.value-grid > article > :deep(svg:not(.value-card__arrow)) {
  grid-column: 1;
  grid-row: 2;
  align-self: start;
  width: 38px;
  height: 38px;
  padding: 8px;
  border: 1px solid rgba(173, 220, 188, .62);
  border-radius: 12px;
  color: #078044;
  background: linear-gradient(145deg, #f5fcf7, #e7f6eb);
  transition: transform .28s ease, background .28s ease, color .28s ease;
}
.value-grid > article > div {
  grid-column: 2;
  grid-row: 2;
  align-self: center;
  min-width: 0;
}
.value-grid > article h3 {
  margin: 0 0 7px;
  color: #172337;
  font-size: 14px;
  font-weight: 850;
  line-height: 1.35;
  letter-spacing: -.01em;
}
.value-grid > article p {
  margin: 0;
  color: #718096;
  font-size: 12px;
  line-height: 1.55;
}
.value-grid .value-card__arrow {
  grid-column: 3;
  grid-row: 2;
  align-self: start;
  margin-top: 2px;
  color: #79b991;
  opacity: .5;
  transition: transform .28s ease, opacity .28s ease, color .28s ease;
}
@media (hover: hover) and (pointer: fine) {
  .value-grid > article:hover {
    z-index: 1;
    transform: translateY(-5px);
    border-color: rgba(103, 183, 130, .78);
    background: linear-gradient(145deg, #ffffff 0%, #f6fcf7 48%, #e8f7ed 100%);
    box-shadow:
      0 16px 34px rgba(20, 95, 51, .105),
      inset 0 1px 0 rgba(255, 255, 255, .98);
  }
  .value-grid > article:hover::before {
    transform: scale(1.35);
    opacity: 1;
  }
  .value-grid > article:hover > :deep(svg:not(.value-card__arrow)) {
    transform: translateY(-2px) rotate(-4deg);
    color: #056a37;
    background: linear-gradient(145deg, #e9f8ed, #d9f1e1);
  }
  .value-grid > article:hover .value-card__arrow {
    transform: translate(2px, -2px);
    color: #078044;
    opacity: 1;
  }
}
@keyframes value-card-enter {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 1050px) {
  .value-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 680px) {
  .value-section { padding: 34px 0 38px; }
  .value-section .section-heading {
    align-items: flex-start;
    flex-direction: column;
    gap: 9px;
  }
  .value-section .section-heading > p { max-width: 100%; }
  .value-grid { grid-template-columns: 1fr; gap: 11px; }
  .value-grid > article { min-height: 132px; padding: 17px; }
}
@media (prefers-reduced-motion: reduce) {
  .value-grid > article { animation: none; transition: none; }
  .value-grid > article:hover { transform: none; }
  .value-grid > article:hover > :deep(svg:not(.value-card__arrow)) { transform: none; }
}

/* Compact trust strip below the homepage hero */
.service-highlights {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  padding-top: 14px;
  padding-bottom: 18px;
}
.service-highlights .trust-highlight {
  position: relative;
  isolation: isolate;
  display: flex;
  align-items: center;
  gap: 13px;
  min-width: 0;
  min-height: 88px;
  padding: 15px 17px;
  overflow: hidden;
  border: 1px solid #e0eee4;
  border-radius: 15px;
  background: linear-gradient(135deg, #ffffff 0%, #f8fcf9 55%, #edf8f0 100%);
  box-shadow: 0 5px 18px rgba(16, 75, 40, .045), inset 0 1px 0 rgba(255,255,255,.95);
  transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
  animation: trust-highlight-in .5s cubic-bezier(.2,.7,.2,1) both;
}
.service-highlights .trust-highlight:nth-child(2) { animation-delay: 70ms; }
.service-highlights .trust-highlight:nth-child(3) { animation-delay: 140ms; }
.service-highlights .trust-highlight:nth-child(4) { animation-delay: 210ms; }
.service-highlights .trust-highlight::after {
  position: absolute;
  z-index: -1;
  right: -32px;
  bottom: -52px;
  width: 112px;
  height: 112px;
  border-radius: 50%;
  content: "";
  background: radial-gradient(circle, rgba(177, 230, 192, .32), transparent 70%);
  transition: transform .3s ease;
}
.service-highlights .trust-highlight__icon {
  display: grid;
  flex: 0 0 43px;
  width: 43px;
  height: 43px;
  place-items: center;
  border: 1px solid #d7ecdd;
  border-radius: 13px;
  background: linear-gradient(145deg, #f5fcf7, #e5f5e9);
  color: #078044;
  transition: transform .25s ease, background .25s ease;
}
.service-highlights .trust-highlight__copy {
  display: grid;
  gap: 5px;
  min-width: 0;
}
.service-highlights .trust-highlight strong {
  color: #172337;
  font-size: 14px;
  font-weight: 650;
  letter-spacing: -0.01em;
  line-height: 1.25;
}
.service-highlights .trust-highlight small {
  color: #718096;
  font-size: 11px;
  font-weight: 550;
  line-height: 1.45;
}
@media (hover: hover) and (pointer: fine) {
  .service-highlights .trust-highlight:hover {
    transform: translateY(-3px);
    border-color: #b8dec3;
    box-shadow: 0 12px 26px rgba(16, 95, 48, .09);
  }
  .service-highlights .trust-highlight:hover::after { transform: scale(1.25); }
  .service-highlights .trust-highlight:hover .trust-highlight__icon {
    transform: translateY(-2px) rotate(-4deg);
    background: linear-gradient(145deg, #eaf8ee, #dff2e5);
  }
}
@keyframes trust-highlight-in {
  from { opacity: 0; transform: translateY(9px); }
  to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 980px) {
  .service-highlights { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
  .service-highlights { grid-template-columns: 1fr; gap: 9px; padding-top: 10px; }
  .service-highlights .trust-highlight { min-height: 76px; padding: 13px 14px; }
}
@media (prefers-reduced-motion: reduce) {
  .service-highlights .trust-highlight { animation: none; transition: none; }
  .service-highlights .trust-highlight:hover { transform: none; }
  .service-highlights .trust-highlight:hover .trust-highlight__icon { transform: none; }
}

</style>

