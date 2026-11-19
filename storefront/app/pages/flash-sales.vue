<script setup lang="ts">
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

const config = useRuntimeConfig()
const { locale, t } = useStorefrontLocale()
const { data, pending, error, refresh } = await useFetch<{ data: FlashSale[] }>(
  `${config.public.apiBase}/flash-sales`,
  {
    key: 'storefront-flash-sales-page',
    getCachedData: () => undefined,
    default: () => ({ data: [] }),
  },
)

const sales = computed(() => (data.value?.data || []).filter(sale =>
  Array.isArray(sale.items) && sale.items.length > 0
))
const now = ref(Date.now())
let countdownTimer: ReturnType<typeof setInterval> | undefined

onMounted(() => {
  countdownTimer = setInterval(() => { now.value = Date.now() }, 1000)
})
onBeforeUnmount(() => {
  if (countdownTimer) clearInterval(countdownTimer)
})

function money(value: number | string | null | undefined) {
  if (value === null || value === undefined) return t('Contact for price', 'দাম জানতে যোগাযোগ করুন')
  const amount = Number(value)
  return Number.isFinite(amount)
    ? `৳${amount.toLocaleString('en-BD', { maximumFractionDigits: 2 })}`
    : t('Contact for price', 'দাম জানতে যোগাযোগ করুন')
}

function discountPercent(regular: number | string, sale: number | string) {
  const regularAmount = Number(regular)
  const saleAmount = Number(sale)
  if (!Number.isFinite(regularAmount) || regularAmount <= 0 || saleAmount < 0 || saleAmount >= regularAmount) return null
  return Math.round(((regularAmount - saleAmount) / regularAmount) * 100)
}

function formattedEnd(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return t('See offer details', 'অফারের বিস্তারিত দেখুন')
  return new Intl.DateTimeFormat(locale.value === 'bn' ? 'bn-BD' : 'en-BD', {
    day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit',
    timeZone: 'Asia/Dhaka',
  }).format(date)
}

function countdown(value: string) {
  const distance = new Date(value).getTime() - now.value
  if (!Number.isFinite(distance)) return t('Time unavailable', 'সময় পাওয়া যাচ্ছে না')
  if (distance <= 0) return t('Offer ended', 'অফার শেষ')
  const days = Math.floor(distance / 86400000)
  const hours = Math.floor((distance % 86400000) / 3600000)
  const minutes = Math.floor((distance % 3600000) / 60000)
  const seconds = Math.floor((distance % 60000) / 1000)
  const pad = (part: number) => String(part).padStart(2, '0')
  return days > 0
    ? `${days}d ${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
    : `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
}

function campaignAccent(sale: FlashSale) {
  return /^#[0-9a-f]{3,8}$/i.test(sale.accent_color || '') ? sale.accent_color : '#176044'
}

useSeoMeta({
  title: 'Flash Sales | MADINA Scale',
  description: 'Explore current limited-time offers on MADINA weighing solutions.',
  ogTitle: 'Flash Sales | MADINA Scale',
  ogDescription: 'Explore current limited-time offers on MADINA weighing solutions.',
  ogType: 'website',
})
</script>

<template>
  <main class="flash-sales-page">
    <section class="container flash-sales-content">
      <div class="flash-sales-heading">
        <div>
          <p class="section-kicker">{{ t('CURRENT PROMOTIONS', 'চলমান অফার') }}</p>
          <h2>{{ t('Shop Flash Deals', 'ফ্ল্যাশ ডিল দেখুন') }}</h2>
          <p>{{ t('Offer end times are shown in Bangladesh time.', 'অফারের শেষ সময় বাংলাদেশ সময় অনুযায়ী দেখানো হচ্ছে।') }}</p>
        </div>
        <NuxtLink to="/products" class="flash-sales-back-link">
          {{ t('Browse all products', 'সব পণ্য দেখুন') }} <UiIcon name="arrow-up-right" :size="16" />
        </NuxtLink>
      </div>

      <div v-if="pending" class="flash-sales-state" role="status">
        <span class="flash-sales-loader" />
        <strong>{{ t('Loading current offers…', 'চলমান অফার লোড হচ্ছে…') }}</strong>
      </div>

      <div v-else-if="error" class="flash-sales-state flash-sales-state--error" role="alert">
        <UiIcon name="message-circle" :size="30" />
        <strong>{{ t('Flash sales could not be loaded', 'ফ্ল্যাশ সেল লোড করা যায়নি') }}</strong>
        <p>{{ t('Please check your connection and try again.', 'ইন্টারনেট সংযোগ পরীক্ষা করে আবার চেষ্টা করুন।') }}</p>
        <button type="button" class="flash-sales-retry" @click="refresh()">
          <UiIcon name="arrow-right" :size="15" /> {{ t('Try again', 'আবার চেষ্টা করুন') }}
        </button>
      </div>

      <div v-else-if="!sales.length" class="flash-sales-state">
        <span class="flash-sales-empty-icon"><UiIcon name="package-search" :size="30" /></span>
        <strong>{{ t('No active flash sales right now', 'এই মুহূর্তে কোনো ফ্ল্যাশ সেল চালু নেই') }}</strong>
        <p>{{ t('New limited-time offers will appear here when they are published.', 'নতুন সীমিত সময়ের অফার প্রকাশিত হলে এখানে দেখা যাবে।') }}</p>
        <NuxtLink to="/products" class="flash-sales-retry">{{ t('Explore products', 'পণ্য দেখুন') }} <UiIcon name="arrow-right" :size="15" /></NuxtLink>
      </div>

      <div v-else class="flash-sale-campaigns">
        <section
          v-for="sale in sales"
          :key="sale.id"
          class="flash-sale-campaign"
          :style="{ '--campaign-accent': campaignAccent(sale) }"
        >
          <div class="flash-sale-campaign__banner" :class="{ 'has-image': sale.banner_image }">
            <img v-if="sale.banner_image" :src="sale.banner_image" :alt="sale.title" loading="lazy">
            <div class="flash-sale-campaign__banner-copy">
              <span class="flash-sale-campaign__label"><UiIcon name="zap" :size="14" /> {{ t('FLASH SALE', 'ফ্ল্যাশ সেল') }}</span>
              <h3>{{ sale.title }}</h3>
              <p v-if="sale.tagline">{{ sale.tagline }}</p>
              <p v-else-if="sale.description">{{ sale.description }}</p>
            </div>
            <div class="flash-sale-campaign__timer">
              <span>{{ t('ENDS IN', 'শেষ হতে বাকি') }}</span>
              <strong>{{ countdown(sale.ends_at) }}</strong>
              <small>{{ t('Ends', 'শেষ') }} {{ formattedEnd(sale.ends_at) }}</small>
            </div>
          </div>

          <p v-if="sale.description && sale.tagline" class="flash-sale-campaign__description">{{ sale.description }}</p>

          <div class="flash-sale-products">
            <article v-for="item in sale.items" :key="item.id" class="flash-sale-product">
              <NuxtLink :to="`/products/${item.product_slug}`" class="flash-sale-product__image">
                <span v-if="discountPercent(item.regular_price, item.sale_price)" class="flash-sale-product__discount">
                  -{{ discountPercent(item.regular_price, item.sale_price) }}%
                </span>
                <StorefrontImage
                  :src="item.image_url || null"
                  :alt="item.image_url ? item.product_name : ''"
                  kind="product"
                  label="Flash sale product"
                  dimensions="800 × 800 px"
                  ratio="1 / 1"
                  :width="800"
                  :height="800"
                />
              </NuxtLink>
              <div class="flash-sale-product__body">
                <NuxtLink :to="`/products/${item.product_slug}`" class="flash-sale-product__name">{{ item.product_name }}</NuxtLink>
                <span v-if="item.variant_name" class="flash-sale-product__variant">{{ item.variant_name }}</span>
                <div class="flash-sale-product__prices">
                  <strong>{{ money(item.sale_price) }}</strong>
                  <del>{{ money(item.regular_price) }}</del>
                </div>
                <span v-if="item.quantity_remaining !== null" class="flash-sale-product__stock">
                  <UiIcon name="package-search" :size="13" />
                  {{ item.quantity_remaining > 0
                    ? `${item.quantity_remaining} ${t('left in this offer', 'টি অফার স্টকে আছে')}`
                    : t('Offer quantity sold out', 'অফারের স্টক শেষ') }}
                </span>
                <NuxtLink :to="`/products/${item.product_slug}`" class="flash-sale-product__action">
                  {{ t('View deal', 'ডিল দেখুন') }} <UiIcon name="arrow-right" :size="15" />
                </NuxtLink>
              </div>
            </article>
          </div>
        </section>
      </div>
    </section>
  </main>
</template>


<style scoped>
.flash-sales-page{padding-bottom:64px;background:#fff;color:var(--ink-900)}
.flash-sales-page .container{width:min(1440px,calc(100% - 64px));margin-inline:auto}
.flash-sales-hero{position:relative;isolation:isolate;overflow:hidden;background:linear-gradient(112deg,#064b2d 0%,#087a48 56%,#16865a 100%);color:#fff}
.flash-sales-hero>.container{min-height:286px;display:flex;align-items:center;justify-content:space-between;gap:40px;padding-block:42px}
.flash-sales-hero__content{position:relative;z-index:2;max-width:760px}
.flash-sales-eyebrow{display:flex;align-items:center;gap:9px;margin:0 0 16px;color:#c9f2d8;font-size:11px;font-weight:850;letter-spacing:.16em;text-transform:uppercase}
.flash-sales-hero h1{margin:0;color:#fff;font-size:clamp(34px,4vw,52px);font-weight:850;line-height:1.08;letter-spacing:-1.8px}
.flash-sales-hero__content>p:not(.flash-sales-eyebrow){max-width:680px;margin:15px 0 0;color:#e5f5eb;font-size:15px;line-height:1.75}
.flash-sales-hero__meta{display:flex;flex-wrap:wrap;gap:24px;margin-top:24px}
.flash-sales-hero__meta span{display:inline-flex;align-items:center;gap:8px;color:#fff;font-size:12px;font-weight:750}
.flash-sales-hero__art{position:relative;display:grid;place-items:center;flex:0 0 270px;min-height:210px}
.flash-sales-hero__ring{position:absolute;display:block;border:1px solid rgba(255,255,255,.17);border-radius:50%;aspect-ratio:1}
.flash-sales-hero__ring--one{width:205px}
.flash-sales-hero__ring--two{width:270px}
.flash-sales-hero__bolt{position:relative;z-index:1;display:grid;place-items:center;width:116px;height:116px;border:1px solid rgba(255,255,255,.24);border-radius:28px;background:rgba(255,255,255,.14);color:#fff;transform:rotate(-8deg);box-shadow:0 18px 45px rgba(0,0,0,.12)}
.flash-sales-content{padding-top:38px}
.flash-sales-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:22px}
.flash-sales-heading h2{margin:7px 0 5px;color:var(--ink-900);font-size:27px;font-weight:850;line-height:1.2;letter-spacing:-.7px}
.flash-sales-heading>div>p:last-child{margin:0;color:var(--ink-500);font-size:13px}
.flash-sales-back-link{display:inline-flex;align-items:center;gap:7px;flex:none;color:var(--brand-700);font-size:13px;font-weight:800}
.flash-sales-back-link:hover{text-decoration:underline}
.flash-sales-state{display:flex;min-height:220px;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:32px;border:1px solid var(--line);border-radius:16px;background:#fbfdfb;text-align:center;color:var(--ink-700)}
.flash-sales-state>strong{color:var(--ink-900);font-size:17px;font-weight:800}
.flash-sales-state>p{max-width:480px;margin:0;color:var(--ink-500);font-size:13px;line-height:1.65}
.flash-sales-state--error{border-color:#f3d2d0;background:#fffafa}
.flash-sales-empty-icon{display:grid;place-items:center;width:58px;height:58px;margin-bottom:4px;border-radius:50%;background:#eaf5ed;color:var(--brand-700)}
.flash-sales-loader{width:30px;height:30px;border:3px solid #dcebe0;border-top-color:var(--brand-700);border-radius:50%;animation:flash-sales-spin .8s linear infinite}
@keyframes flash-sales-spin{to{transform:rotate(360deg)}}
.flash-sales-retry{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:39px;margin-top:8px;padding:0 16px;border:1px solid #cfe3d4;border-radius:999px;background:#fff;color:var(--brand-800);font-size:12px;font-weight:800;cursor:pointer}
.flash-sales-retry:hover{border-color:var(--brand-700);background:#f3faf5}
.flash-sale-campaigns{display:grid;gap:26px}
.flash-sale-campaign{overflow:hidden;border:1px solid #e2ebe5;border-top:3px solid var(--campaign-accent,var(--brand-700));border-radius:16px;background:#fff;box-shadow:0 5px 20px rgba(16,24,40,.045)}
.flash-sale-campaign__banner{position:relative;display:flex;align-items:center;justify-content:space-between;gap:24px;min-height:190px;overflow:hidden;padding:30px;background:linear-gradient(115deg,var(--campaign-accent,#087a32),#218a57);color:#fff}
.flash-sale-campaign__banner::after{position:absolute;right:13%;top:-105px;width:330px;height:330px;border:1px solid rgba(255,255,255,.13);border-radius:50%;content:"";pointer-events:none}
.flash-sale-campaign__banner.has-image{background:#163f2e}
.flash-sale-campaign__banner>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.flash-sale-campaign__banner.has-image::before{position:absolute;z-index:1;inset:0;background:linear-gradient(90deg,rgba(3,45,25,.92),rgba(3,45,25,.65) 58%,rgba(3,45,25,.3));content:""}
.flash-sale-campaign__banner-copy,.flash-sale-campaign__timer{position:relative;z-index:2}
.flash-sale-campaign__banner-copy{max-width:720px}
.flash-sale-campaign__label{display:inline-flex;align-items:center;gap:7px;color:#e1f7e8;font-size:10px;font-weight:850;letter-spacing:.15em}
.flash-sale-campaign__banner h3{margin:14px 0 6px;color:#fff;font-size:clamp(23px,2.6vw,32px);font-weight:850;line-height:1.15;letter-spacing:-.7px}
.flash-sale-campaign__banner-copy p{max-width:680px;margin:0;color:#f0faf3;font-size:13px;line-height:1.65}
.flash-sale-campaign__timer{display:flex;flex:none;min-width:190px;flex-direction:column;align-items:flex-end;gap:6px;padding:15px 17px;border:1px solid rgba(255,255,255,.22);border-radius:12px;background:rgba(0,45,25,.25);text-align:right;backdrop-filter:blur(8px)}
.flash-sale-campaign__timer>span{color:#d8f2e1;font-size:10px;font-weight:850;letter-spacing:.12em}
.flash-sale-campaign__timer strong{color:#fff;font-size:22px;font-weight:850;font-variant-numeric:tabular-nums;letter-spacing:-.5px}
.flash-sale-campaign__timer small{color:#e0f2e6;font-size:10px}
.flash-sale-campaign__description{margin:0;padding:17px 22px 0;color:var(--ink-700);font-size:13px;line-height:1.7}
.flash-sale-products{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;padding:22px}
.flash-sale-product{min-width:0;overflow:hidden;border:1px solid #e9eeeb;border-radius:12px;background:#fff;transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.flash-sale-product:hover{transform:translateY(-3px);border-color:#c4ddcb;box-shadow:0 9px 22px rgba(16,24,40,.07)}
.flash-sale-product__image{position:relative;display:block;aspect-ratio:1/1;overflow:hidden;background:#f7faf8;padding:12px}
.flash-sale-product__image :deep(.storefront-image),.flash-sale-product__image :deep(.image-placeholder){width:100%;height:100%;border-radius:8px}
.flash-sale-product__discount{position:absolute;z-index:2;top:12px;left:12px;padding:5px 8px;border-radius:5px;background:#fff0e8;color:#b54708;font-size:11px;font-weight:850}
.flash-sale-product__body{display:flex;min-height:158px;flex-direction:column;align-items:flex-start;gap:7px;padding:13px}
.flash-sale-product__name{display:-webkit-box;overflow:hidden;min-height:38px;color:var(--ink-900);font-size:13px;font-weight:800;line-height:1.45;-webkit-box-orient:vertical;-webkit-line-clamp:2}
.flash-sale-product__name:hover{color:var(--brand-700)}
.flash-sale-product__variant{color:var(--ink-500);font-size:11px}
.flash-sale-product__prices{display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;margin-top:2px}
.flash-sale-product__prices strong{color:var(--brand-800);font-size:16px;font-weight:850}
.flash-sale-product__prices del{color:#98a2b3;font-size:11px}
.flash-sale-product__stock{display:inline-flex;align-items:center;gap:5px;color:var(--ink-500);font-size:10px}
.flash-sale-product__action{display:inline-flex;align-items:center;gap:7px;margin-top:auto;padding-top:5px;color:var(--brand-700);font-size:11px;font-weight:850}
.flash-sale-product__action:hover{color:var(--brand-900)}
@media(max-width:1000px){.flash-sales-hero__art{flex-basis:210px}.flash-sale-products{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:720px){.flash-sales-page .container{width:min(100% - 32px,1440px)}.flash-sales-hero>.container{min-height:0;padding-block:34px}.flash-sales-hero__art{display:none}.flash-sales-hero h1{font-size:36px}.flash-sales-hero__content>p:not(.flash-sales-eyebrow){font-size:14px}.flash-sales-hero__meta{gap:14px;margin-top:18px}.flash-sales-hero__meta span{font-size:11px}.flash-sales-content{padding-top:28px}.flash-sales-heading{align-items:flex-start}.flash-sales-heading h2{font-size:23px}.flash-sales-back-link{font-size:11px}.flash-sale-campaign__banner{align-items:flex-start;flex-direction:column;padding:23px;gap:18px}.flash-sale-campaign__timer{min-width:0;align-items:flex-start;text-align:left}.flash-sale-products{grid-template-columns:repeat(2,minmax(0,1fr));gap:11px;padding:14px}.flash-sale-product__body{padding:10px}.flash-sale-product__prices strong{font-size:14px}}
@media(max-width:420px){.flash-sales-heading{flex-direction:column;gap:12px}.flash-sale-products{grid-template-columns:1fr}.flash-sale-product{display:grid;grid-template-columns:112px minmax(0,1fr)}.flash-sale-product__image{aspect-ratio:1/1}.flash-sale-product__body{min-height:0}.flash-sales-hero__meta{flex-direction:column;gap:9px}}
</style>
