<script setup lang="ts">
type Product = {
  id: number
  name: string
  slug: string
  category: string | null
  image_url: string | null
  price: number | string | null
  regular_price?: number | string | null
  has_flash_sale?: boolean
  default_variant_id: number | null
}

const props = defineProps<{ product: Product }>()
const { add } = useStorefrontCart()
const adding = ref(false)
const added = ref(false)
const message = ref('')
const hasDiscount = computed(() =>
  Boolean(props.product.has_flash_sale) &&
  Number(props.product.regular_price) > Number(props.product.price),
)
const discountPercent = computed(() => {
  const regular = Number(props.product.regular_price)
  const current = Number(props.product.price)
  if (!hasDiscount.value || !Number.isFinite(regular) || regular <= 0) return null
  return Math.round(((regular - current) / regular) * 100)
})
const money = (value: number | string | null | undefined) => {
  if (value === null || value === undefined) return 'Contact for price'
  const amount = Number(value)
  return Number.isFinite(amount) ? `৳${amount.toLocaleString('en-BD', { maximumFractionDigits: 2 })}` : 'Contact for price'
}

async function addToCart() {
  if (!props.product.default_variant_id || adding.value) return
  adding.value = true
  added.value = false
  message.value = ''
  try {
    await add(props.product.default_variant_id, 1)
    added.value = true
    message.value = `${props.product.name} added to your cart.`
  } catch (cause: any) {
    message.value = cause?.data?.message || 'Unable to add this product. Please try again.'
  } finally {
    adding.value = false
  }
}
</script>

<template>
  <article class="product-card">
    <NuxtLink :to="`/products/${product.slug}`" class="product-card__media">
      <span v-if="discountPercent" class="product-card__badge">-{{ discountPercent }}%</span>
      <StorefrontImage
        :src="product.image_url"
        :alt="product.image_url ? product.name : ''"
        kind="product"
        label="Product Image"
        dimensions="800 × 800 px"
        ratio="1 / 1"
        :width="800"
        :height="800"
      />
    </NuxtLink>
    <div class="product-card__body">
      <p v-if="product.category" class="product-card__category">{{ product.category }}</p>
      <h3 class="product-card__name"><NuxtLink :to="`/products/${product.slug}`">{{ product.name }}</NuxtLink></h3>
      <div class="product-card__price-row">
        <strong class="product-card__price">{{ money(product.price) }}</strong>
        <del v-if="hasDiscount" class="product-card__regular-price">{{ money(product.regular_price) }}</del>
      </div>
      <button
        class="btn-primary product-card__action"
        type="button"
        :disabled="!product.default_variant_id || adding"
        @click="addToCart"
      >
        {{ adding ? 'Adding…' : added ? 'Added to cart' : product.default_variant_id ? 'Add to cart' : 'Unavailable' }}
      </button>
      <p v-if="message" class="product-card__feedback" :class="{ 'is-error': !added }" role="status">{{ message }}</p>
    </div>
  </article>
</template>
