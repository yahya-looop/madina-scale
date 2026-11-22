<script setup lang="ts">
type ProductAttribute = {
  name: string;
  value: string;
  color_code?: string | null;
};
type ProductVariant = {
  id: number;
  name: string;
  sku?: string | null;
  price: number | string | null;
  regular_price: number | string | null;
  has_flash_sale?: boolean;
  has_wholesale_pricing?: boolean;
  is_default?: boolean;
  attributes?: ProductAttribute[];
};
type ProductImage = { url: string; alt?: string | null };
type Product = {
  id: number;
  name: string;
  slug: string;
  category: string | null;
  category_slug?: string | null;
  brand?: string | null;
  unit?: string | null;
  sku?: string | null;
  model_number?: string | null;
  barcode?: string | null;
  product_type?: string | null;
  short_description?: string | null;
  description?: string | null;
  image_url: string | null;
  images?: ProductImage[];
  price: number | string | null;
  regular_price?: number | string | null;
  has_flash_sale?: boolean;
  has_wholesale_pricing?: boolean;
  variant_count?: number;
  default_variant_id: number | null;
  variants?: ProductVariant[];
};
type ProductCardItem = {
  id: number;
  name: string;
  slug: string;
  category: string | null;
  image_url: string | null;
  price: number | string | null;
  regular_price?: number | string | null;
  has_flash_sale?: boolean;
  default_variant_id: number | null;
};
type ProductResponse = { data: Product };
type ProductListResponse = { data: ProductCardItem[] };
const route = useRoute();
const config = useRuntimeConfig();
const { data: response, error, refresh } = await useFetch<ProductResponse>(
  () => `${config.public.apiBase}/products/${route.params.slug}`,
  { key: `storefront-product-detail-${String(route.params.slug)}` }
);
const product = computed(() => response.value?.data ?? null);
const { add, error: cartError } = useStorefrontCart();
const adding = ref(false);
const added = ref(false);
const quantity = ref(1);
const selectedVariantId = ref<number | null>(null);
const activeImageIndex = ref(0);
const buyNowLoading = ref(false);
const expandedAssurance = ref<string | null>(null);
const galleryZoom = ref(false);
const galleryZoomPosition = ref({ x: 50, y: 50 });
const assuranceItems = [
  {
    id: "delivery",
    title: "Delivery information",
    subtitle: "Delivery options, coverage and estimated timelines",
    summary:
      "Check available delivery coverage, estimated delivery timelines and the options available for your location.",
    link: "/pages/shipping-delivery-policy",
    linkLabel: "View delivery policy",
    icon: "truck",
  },
  {
    id: "warranty",
    title: "Warranty support",
    subtitle: "Coverage, service and after-sales assistance",
    summary:
      "Review the product warranty coverage and learn how to request support if your product needs service.",
    link: "/pages/warranty",
    linkLabel: "View warranty details",
    icon: "shield-check",
  },
  {
    id: "returns",
    title: "Returns & refunds",
    subtitle: "Return eligibility and refund guidance",
    summary:
      "Understand the return conditions, eligibility requirements and refund process before placing your order.",
    link: "/pages/return-refund-policy",
    linkLabel: "View return policy",
    icon: "refresh-ccw",
  },
] as const;
function changeGalleryImage(direction: -1 | 1) {
  if (gallery.value.length < 2) return;
  activeImageIndex.value =
    (activeImageIndex.value + direction + gallery.value.length) % gallery.value.length;
}
function handleGalleryKeydown(event: KeyboardEvent) {
  if (event.key === "ArrowLeft") {
    event.preventDefault();
    changeGalleryImage(-1);
  } else if (event.key === "ArrowRight") {
    event.preventDefault();
    changeGalleryImage(1);
  }
}
function handleGalleryPointerMove(event: MouseEvent) {
  const element = event.currentTarget as HTMLElement;
  const bounds = element.getBoundingClientRect();
  galleryZoomPosition.value = {
    x: Math.min(100, Math.max(0, ((event.clientX - bounds.left) / bounds.width) * 100)),
    y: Math.min(100, Math.max(0, ((event.clientY - bounds.top) / bounds.height) * 100)),
  };
}
const galleryZoomStyle = computed(() => ({
  "--zoom-x": `${galleryZoomPosition.value.x}%`,
  "--zoom-y": `${galleryZoomPosition.value.y}%`,
}));
watch(
  product,
  (item) => {
    selectedVariantId.value =
      item?.variants?.find((variant) => variant.is_default)?.id ??
      item?.variants?.[0]?.id ??
      null;
    activeImageIndex.value = 0;
    expandedAssurance.value = null;
    galleryZoom.value = false;
    quantity.value = 1;
  },
  { immediate: true }
);
const selectedVariant = computed(
  () =>
    product.value?.variants?.find((variant) => variant.id === selectedVariantId.value) ??
    null
);
const gallery = computed<ProductImage[]>(() => {
  if (!product.value) return [];
  const images = (product.value.images ?? []).filter((image) => Boolean(image.url));
  if (
    product.value.image_url &&
    !images.some((image) => image.url === product.value?.image_url)
  ) {
    images.unshift({ url: product.value.image_url, alt: product.value.name });
  }
  return images;
});
const activeImage = computed(() => gallery.value[activeImageIndex.value] ?? null);
const recommendedQuery = computed(() => ({
  category: product.value?.category_slug || undefined,
  page: 1,
}));
const { data: recommendedResponse } = useFetch<ProductListResponse>(
  () => `${config.public.apiBase}/products`,
  {
    key: "storefront-product-recommendations",
    query: recommendedQuery,
    watch: [recommendedQuery],
    default: () => ({ data: [] }),
  }
);
const recommendedProducts = computed(() =>
  (recommendedResponse.value?.data ?? [])
    .filter((item) => item.id !== product.value?.id)
    .slice(0, 4)
);
const price = computed(
  () => selectedVariant.value?.price ?? product.value?.price ?? null
);
const regularPrice = computed(
  () => selectedVariant.value?.regular_price ?? product.value?.regular_price ?? null
);
const hasDiscount = computed(() => {
  const current = Number(price.value);
  const regular = Number(regularPrice.value);
  return Number.isFinite(current) && Number.isFinite(regular) && regular > current;
});
const discountPercent = computed(() => {
  if (!hasDiscount.value) return 0;
  return Math.round((1 - Number(price.value) / Number(regularPrice.value)) * 100);
});
const money = (value: number | string | null | undefined) => {
  if (value === null || value === undefined || value === "") return "Contact for price";
  const amount = Number(value);
  return Number.isFinite(amount)
    ? `৳${amount.toLocaleString("en-BD", { maximumFractionDigits: 2 })}`
    : "Contact for price";
};
const detailRows = computed(() => {
  if (!product.value) return [];
  return [
    { label: "Brand", value: product.value.brand },
    { label: "Category", value: product.value.category },
    {
      label: "Product type",
      value: product.value.product_type === "variable" ? "Multiple variants" : "Standard",
    },
    { label: "Model number", value: product.value.model_number },
    { label: "SKU", value: selectedVariant.value?.sku || product.value.sku },
    { label: "Barcode", value: product.value.barcode },
    { label: "Unit", value: product.value.unit },
  ].filter((row) => Boolean(row.value));
});
const variantAttributes = computed(() => selectedVariant.value?.attributes ?? []);
async function addToCart(goToCart = false) {
  if (!selectedVariant.value || adding.value || buyNowLoading.value) return;
  adding.value = true;
  added.value = false;
  try {
    await add(selectedVariant.value.id, quantity.value);
    added.value = true;
    if (goToCart) await navigateTo("/cart");
  } catch {
    // The cart composable exposes a customer-safe error message.
  } finally {
    adding.value = false;
  }
}
async function buyNow() {
  if (!selectedVariant.value || adding.value || buyNowLoading.value) return;
  buyNowLoading.value = true;
  added.value = false;
  try {
    await add(selectedVariant.value.id, quantity.value);
    added.value = true;
    await navigateTo("/checkout");
  } catch {
    // The cart composable exposes a customer-safe error message.
  } finally {
    buyNowLoading.value = false;
  }
}
useSeoMeta({
  title: () =>
    product.value ? `${product.value.name} | MADINA Scale` : "Product | MADINA Scale",
  description: () =>
    product.value?.short_description ||
    "Explore product details and weighing solutions from MADINA Scale.",
  ogTitle: () => product.value?.name || "MADINA Scale",
  ogDescription: () =>
    product.value?.short_description || "Explore weighing solutions from MADINA Scale.",
  ogImage: () => product.value?.image_url || undefined,
  ogType: "website",
});
</script>
<template>
  <main class="container product-detail-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <NuxtLink to="/">Home</NuxtLink>
      <span aria-hidden="true">/</span>
      <NuxtLink to="/products">Products</NuxtLink>
      <span v-if="product?.category" aria-hidden="true">/</span>
      <NuxtLink
        v-if="product?.category_slug"
        :to="`/products?category=${product.category_slug}`"
        >{{ product.category }}</NuxtLink
      >
      <span v-else-if="product?.category">{{ product.category }}</span>
      <span aria-hidden="true">/</span>
      <span class="truncate" aria-current="page">{{
        product?.name || "Product details"
      }}</span>
    </nav>
    <div v-if="error" class="product-detail-error" role="alert">
      <div class="product-detail-error__icon">!</div>
      <h1>We couldn't load this product</h1>
      <p>The product may be unavailable or there may be a temporary connection issue.</p>
      <div class="mt-5 flex flex-wrap justify-center gap-3">
        <button type="button" class="btn-primary" @click="refresh()">Try again</button>
        <NuxtLink to="/products" class="product-detail-secondary"
          >Browse products</NuxtLink
        >
      </div>
    </div>
    <template v-else-if="product">
      <section class="product-detail-layout">
        <div class="product-gallery">
          <div
            class="product-gallery__main"
            :class="{
              'is-zooming': galleryZoom && Boolean(activeImage || product.image_url),
            }"
            :style="galleryZoomStyle"
            tabindex="0"
            role="group"
            :aria-label="`Product image gallery. Image ${activeImageIndex + 1} of ${
              gallery.length || 1
            }. Use left and right arrow keys to change image.`"
            @keydown="handleGalleryKeydown"
            @mousemove="handleGalleryPointerMove"
            @mouseenter="galleryZoom = true"
            @mouseleave="galleryZoom = false"
          >
            <span v-if="hasDiscount" class="product-gallery__discount"
              >Save {{ discountPercent }}%</span
            >
            <StorefrontImage
              v-if="activeImage"
              :key="activeImage.url"
              :src="activeImage.url"
              :alt="activeImage.alt || product.name"
              kind="product"
              label="Product image"
              dimensions="1400 × 1400 px"
              ratio="1 / 1"
              :width="1400"
              :height="1400"
              fit="contain"
              loading="eager"
              priority="high"
            />
            <StorefrontImage
              v-else
              :src="product.image_url"
              :alt="product.name"
              kind="product"
              label="Product image"
              dimensions="1400 × 1400 px"
              ratio="1 / 1"
              :width="1400"
              :height="1400"
              fit="contain"
              loading="eager"
              priority="high"
            />
            <div class="product-gallery__tools">
              <span class="product-gallery__zoom-hint">
                <UiIcon name="search" :size="14" />
                Hover to zoom
              </span>
              <span class="product-gallery__image-count"
                >{{ activeImageIndex + 1 }} / {{ gallery.length || 1 }}</span
              >
            </div>
            <template v-if="gallery.length > 1">
              <button
                type="button"
                class="product-gallery__nav product-gallery__nav--previous"
                aria-label="Previous product image"
                @click.stop="changeGalleryImage(-1)"
              >
                <UiIcon name="arrow-right" :size="21" />
              </button>
              <button
                type="button"
                class="product-gallery__nav product-gallery__nav--next"
                aria-label="Next product image"
                @click.stop="changeGalleryImage(1)"
              >
                <UiIcon name="arrow-right" :size="21" />
              </button>
            </template>
          </div>
          <div
            v-if="gallery.length > 1"
            class="product-gallery__thumbnails"
            aria-label="Product images"
          >
            <button
              v-for="(image, index) in gallery"
              :key="image.url"
              type="button"
              class="product-gallery__thumbnail"
              :class="{ 'is-active': activeImageIndex === index }"
              :aria-label="`View product image ${index + 1}`"
              :aria-pressed="activeImageIndex === index"
              @click="activeImageIndex = index"
            >
              <StorefrontImage
                :src="image.url"
                :alt="image.alt || `${product.name} image ${index + 1}`"
                kind="product"
                label="Thumbnail"
                dimensions="160 × 160 px"
                ratio="1 / 1"
                :width="160"
                :height="160"
              />
            </button>
          </div>
          <div class="product-gallery__caption">
            <span><UiIcon name="package-search" :size="15" /> Product images</span>
            <span v-if="gallery.length"
              >{{ activeImageIndex + 1 }} / {{ gallery.length }}</span
            >
          </div>
        </div>
        <div class="product-purchase-column">
          <div class="product-product-meta">
            <NuxtLink
              v-if="product.category_slug"
              :to="`/products?category=${product.category_slug}`"
              class="product-category-link"
            >
              {{ product.category }}
            </NuxtLink>
            <span v-else-if="product.category" class="product-category-link">{{
              product.category
            }}</span>
            <span v-if="product.brand" class="product-brand">{{ product.brand }}</span>
          </div>
          <h1 class="product-detail-title">{{ product.name }}</h1>
          <p v-if="product.short_description" class="product-detail-summary">
            {{ product.short_description }}
          </p>
          <div class="product-detail-price-panel">
            <div class="product-detail-price-line">
              <strong class="product-detail-price">{{ money(price) }}</strong>
              <del v-if="hasDiscount" class="product-detail-old-price">{{
                money(regularPrice)
              }}</del>
              <span v-if="hasDiscount" class="product-detail-saving"
                >-{{ discountPercent }}%</span
              >
            </div>
            <p
              v-if="
                selectedVariant?.has_wholesale_pricing || product.has_wholesale_pricing
              "
              class="product-detail-price-note"
            >
              Wholesale pricing may be available for eligible quantities. Contact our team
              for details.
            </p>
            <p v-else class="product-detail-price-note">
              Final payable amount and delivery options are confirmed during checkout.
            </p>
          </div>
          <div v-if="(product.variants?.length ?? 0) > 1" class="product-variant-picker">
            <div class="product-field-heading">
              <span>Choose an option</span>
              <span>{{ product.variants?.length }} available</span>
            </div>
            <div class="product-variant-options">
              <button
                v-for="variant in product.variants"
                :key="variant.id"
                type="button"
                class="product-variant-option"
                :class="{ 'is-selected': selectedVariantId === variant.id }"
                :aria-pressed="selectedVariantId === variant.id"
                @click="
                  selectedVariantId = variant.id;
                  added = false;
                "
              >
                <span>{{ variant.name }}</span>
                <small>{{ money(variant.price) }}</small>
              </button>
            </div>
          </div>
          <div v-if="variantAttributes.length" class="product-selected-attributes">
            <div
              v-for="attribute in variantAttributes"
              :key="`${attribute.name}-${attribute.value}`"
              class="product-attribute-chip"
            >
              <span>{{ attribute.name }}</span>
              <strong>{{ attribute.value }}</strong>
            </div>
          </div>
          <div class="product-buy-controls">
            <div class="product-quantity-control" aria-label="Quantity">
              <button
                type="button"
                aria-label="Decrease quantity"
                :disabled="quantity <= 1"
                @click="quantity = Math.max(1, quantity - 1)"
              >
                −
              </button>
              <input
                v-model.number="quantity"
                type="number"
                min="1"
                max="99"
                aria-label="Product quantity"
                @change="quantity = Math.min(99, Math.max(1, Number(quantity) || 1))"
              />
              <button
                type="button"
                aria-label="Increase quantity"
                :disabled="quantity >= 99"
                @click="quantity = Math.min(99, quantity + 1)"
              >
                +
              </button>
            </div>
            <button
              type="button"
              class="product-add-button"
              :disabled="!selectedVariant || adding || buyNowLoading"
              @click="addToCart()"
            >
              <UiIcon name="shopping-cart" :size="18" />
              {{ adding ? "Adding to cart…" : "Add to cart" }}
            </button>
          </div>
          <button
            type="button"
            class="product-buy-now"
            :disabled="!selectedVariant || adding || buyNowLoading"
            @click="buyNow"
          >
            {{ buyNowLoading ? "Preparing checkout…" : "Buy now" }}
          </button>
          <p v-if="added" class="product-cart-success" role="status">
            <UiIcon name="file-check" :size="17" /> Added to your cart successfully.
            <NuxtLink to="/cart">View cart</NuxtLink>
          </p>
          <p v-if="cartError" class="product-cart-error" role="alert">{{ cartError }}</p>
          <p v-if="!selectedVariant" class="product-cart-error" role="status">
            This product currently has no purchasable option.
          </p>
          <section class="product-assurance" aria-label="Shopping information">
            <div class="product-assurance__heading">
              <span>SHOP WITH CONFIDENCE</span>
              <small>Helpful information before you order</small>
            </div>
            <div class="product-assurance-list">
              <article
                v-for="item in assuranceItems"
                :key="item.id"
                class="product-assurance-item"
                :class="{ 'is-open': expandedAssurance === item.id }"
              >
                <h2 class="product-assurance-item__heading">
                  <button
                    type="button"
                    class="product-assurance-item__trigger"
                    :aria-expanded="expandedAssurance === item.id"
                    :aria-controls="`assurance-panel-${item.id}`"
                    @click="
                      expandedAssurance = expandedAssurance === item.id ? null : item.id
                    "
                  >
                    <span class="product-assurance-icon"
                      ><UiIcon :name="item.icon" :size="18"
                    /></span>
                    <span class="product-assurance-item__copy">
                      <strong>{{ item.title }}</strong>
                      <small>{{ item.subtitle }}</small>
                    </span>
                    <span class="product-assurance-item__chevron">
                      <UiIcon name="chevron-down" :size="18" />
                    </span>
                  </button>
                </h2>
                <Transition name="assurance-expand">
                  <div
                    v-if="expandedAssurance === item.id"
                    :id="`assurance-panel-${item.id}`"
                    class="product-assurance-item__panel"
                  >
                    <p>{{ item.summary }}</p>
                    <NuxtLink :to="item.link" class="product-assurance-item__link">
                      {{ item.linkLabel }}
                      <UiIcon name="arrow-up-right" :size="14" />
                    </NuxtLink>
                  </div>
                </Transition>
              </article>
            </div>
          </section>
        </div>
      </section>
      <section class="product-information">
        <div class="product-information__heading">
          <p class="section-kicker">PRODUCT INFORMATION</p>
          <h2>Everything you need to know</h2>
          <p>
            Review the product overview and available specifications before placing your
            order.
          </p>
        </div>
        <div class="product-information__grid">
          <article class="product-description-card">
            <h3><UiIcon name="file-check" :size="18" /> Product description</h3>
            <p v-if="product.description" class="product-description-text">
              {{ product.description }}
            </p>
            <p v-else-if="product.short_description" class="product-description-text">
              {{ product.short_description }}
            </p>
            <p v-else class="product-description-empty">
              Detailed product description has not been provided yet. Contact our team if
              you need more information.
            </p>
          </article>
          <article class="product-specification-card">
            <h3><UiIcon name="boxes" :size="18" /> Product specifications</h3>
            <dl v-if="detailRows.length" class="product-specification-list">
              <div v-for="row in detailRows" :key="row.label">
                <dt>{{ row.label }}</dt>
                <dd>{{ row.value }}</dd>
              </div>
              <div v-for="attribute in variantAttributes" :key="`spec-${attribute.name}`">
                <dt>{{ attribute.name }}</dt>
                <dd>{{ attribute.value }}</dd>
              </div>
            </dl>
            <p v-else class="product-description-empty">
              Specifications will appear here when they are available.
            </p>
          </article>
        </div>
      </section>
      <section v-if="recommendedProducts.length" class="product-recommendations">
        <div class="product-recommendations__heading">
          <div>
            <p class="section-kicker">YOU MAY ALSO LIKE</p>
            <h2>Recommended products</h2>
            <p>Explore more products from the same category.</p>
          </div>
          <NuxtLink
            v-if="product.category_slug"
            :to="`/products?category=${product.category_slug}`"
            >View all <UiIcon name="arrow-right" :size="15"
          /></NuxtLink>
        </div>
        <div class="product-grid">
          <ProductCard
            v-for="item in recommendedProducts"
            :key="item.id"
            :product="item"
          />
        </div>
      </section>
    </template>
    <div v-else class="product-detail-loading" aria-live="polite">
      <span class="product-detail-loading__spinner" />
      <p>Loading product details…</p>
    </div>
  </main>
</template>
<style scoped>
.product-detail-page {
  padding-block: 24px 64px;
}
.product-detail-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
  gap: clamp(28px, 4vw, 58px);
  align-items: start;
}
.product-gallery {
  min-width: 0;
}
.product-gallery__main {
  --zoom-x: 50%;
  --zoom-y: 50%;
  position: relative;
  display: grid;
  place-items: center;
  min-height: 360px;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 20px;
  background: radial-gradient(circle at 50% 42%, #fff 0%, #fff 58%, #f7faf8 100%);
  padding: clamp(18px, 3vw, 38px);
  isolation: isolate;
  outline: none;
}
.product-gallery__main:focus-visible {
  border-color: var(--brand-700);
  box-shadow: 0 0 0 3px #13754720;
}
.product-gallery__main > .storefront-image,
.product-gallery__main > .image-placeholder {
  width: 100%;
  height: 100%;
  max-height: 570px;
  aspect-ratio: 1/1;
  object-fit: contain;
  transition: transform 0.22s ease, transform-origin 0.08s linear;
  transform-origin: var(--zoom-x) var(--zoom-y);
}
.product-gallery__main.is-zooming > .storefront-image {
  transform: scale(1.85);
  cursor: zoom-in;
}
.product-gallery__main.is-zooming > .image-placeholder {
  transform: none;
}
.product-gallery__tools {
  position: absolute;
  right: 14px;
  bottom: 13px;
  left: 14px;
  z-index: 3;
  display: flex;
  align-items: center;
  justify-content: space-between;
  pointer-events: none;
}
.product-gallery__zoom-hint,
.product-gallery__image-count {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid #e8eeea;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  padding: 6px 9px;
  color: var(--ink-500);
  font-size: 10px;
  font-weight: 750;
  box-shadow: 0 3px 12px #122b1b0a;
  backdrop-filter: blur(8px);
}
.product-gallery__nav {
  position: absolute;
  top: 50%;
  z-index: 4;
  display: grid;
  width: 40px;
  height: 40px;
  place-items: center;
  border: 1px solid #e7ece8;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.94);
  color: var(--ink-700);
  box-shadow: 0 5px 18px #10291a16;
  cursor: pointer;
  transform: translateY(-50%);
  transition: background 0.18s, color 0.18s, box-shadow 0.18s;
}
.product-gallery__nav:hover {
  background: var(--brand-800);
  color: #fff;
  box-shadow: 0 7px 20px #0b653626;
}
.product-gallery__nav--previous {
  left: 14px;
}
.product-gallery__nav--previous :deep(svg) {
  transform: rotate(180deg);
}
.product-gallery__nav--next {
  right: 14px;
}
.product-gallery__discount {
  position: absolute;
  z-index: 2;
  top: 18px;
  left: 18px;
  padding: 7px 11px;
  border-radius: 999px;
  background: #fff0e9;
  color: #b54708;
  font-size: 11px;
  font-weight: 850;
}
.product-gallery__thumbnails {
  display: flex;
  gap: 10px;
  overflow-x: auto;
  padding: 14px 2px 5px;
}
.product-gallery__thumbnail {
  width: 76px;
  height: 76px;
  flex: none;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 10px;
  background: #fff;
  padding: 5px;
  cursor: pointer;
}
.product-gallery__thumbnail.is-active {
  border: 2px solid var(--brand-700);
  padding: 4px;
  box-shadow: 0 0 0 3px #13754712;
}
.product-gallery__thumbnail > .storefront-image,
.product-gallery__thumbnail > .image-placeholder {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.product-gallery__caption {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 5px 3px;
  color: var(--ink-500);
  font-size: 11px;
}
.product-gallery__caption > span:first-child {
  display: flex;
  align-items: center;
  gap: 6px;
}
.product-purchase-column {
  min-width: 0;
  padding-top: 4px;
}
.product-product-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 9px;
}
.product-category-link {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  background: #eaf4ed;
  padding: 6px 11px;
  color: var(--brand-800);
  font-size: 11px;
  font-weight: 800;
  text-decoration: none;
}
.product-brand {
  color: var(--ink-500);
  font-size: 12px;
  font-weight: 700;
}
.product-detail-title {
  margin: 15px 0 0;
  color: var(--ink-900);
  font-size: clamp(27px, 3vw, 39px);
  font-weight: 850;
  line-height: 1.15;
  letter-spacing: -1.1px;
}
.product-detail-summary {
  margin: 13px 0 0;
  color: var(--ink-700);
  font-size: 14px;
  line-height: 1.75;
}
.product-detail-price-panel {
  margin-top: 21px;
  padding: 17px 18px;
  border: 1px solid #e1ece4;
  border-radius: 13px;
  background: #f6faf7;
}
.product-detail-price-line {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}
.product-detail-price {
  color: var(--brand-900);
  font-size: clamp(24px, 2.5vw, 32px);
  font-weight: 900;
  letter-spacing: -0.6px;
}
.product-detail-old-price {
  color: var(--ink-500);
  font-size: 14px;
}
.product-detail-saving {
  border-radius: 5px;
  background: #ffede7;
  padding: 4px 7px;
  color: #b54708;
  font-size: 11px;
  font-weight: 850;
}
.product-detail-price-note {
  margin: 6px 0 0;
  color: var(--ink-500);
  font-size: 11px;
  line-height: 1.6;
}
.product-variant-picker {
  margin-top: 22px;
}
.product-field-heading {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
  color: var(--ink-700);
  font-size: 12px;
  font-weight: 850;
}
.product-field-heading > span + span {
  color: var(--ink-500);
  font-weight: 600;
}
.product-variant-options {
  display: flex;
  flex-wrap: wrap;
  gap: 9px;
}
.product-variant-option {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 105px;
  border: 1px solid var(--line);
  border-radius: 9px;
  background: #fff;
  padding: 10px 12px;
  text-align: left;
  cursor: pointer;
}
.product-variant-option > span {
  color: var(--ink-700);
  font-size: 12px;
  font-weight: 800;
}
.product-variant-option > small {
  color: var(--ink-500);
  font-size: 10px;
}
.product-variant-option.is-selected {
  border-color: var(--brand-700);
  background: #f1f8f3;
  box-shadow: 0 0 0 2px #13754712;
}
.product-selected-attributes {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 13px;
}
.product-attribute-chip {
  display: flex;
  gap: 6px;
  border-radius: 7px;
  background: #f3f5f4;
  padding: 7px 9px;
  color: var(--ink-700);
  font-size: 10px;
}
.product-attribute-chip strong {
  color: var(--ink-900);
}
.product-buy-controls {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 10px;
  margin-top: 24px;
}
.product-quantity-control {
  display: flex;
  align-items: center;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 9px;
  background: #fff;
}
.product-quantity-control button {
  width: 34px;
  height: 46px;
  border: 0;
  background: #f7f9f7;
  color: var(--ink-700);
  font-size: 19px;
  cursor: pointer;
}
.product-quantity-control button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.product-quantity-control input {
  width: 39px;
  height: 46px;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--ink-900);
  font-size: 13px;
  font-weight: 800;
  text-align: center;
  appearance: textfield;
}
.product-quantity-control input::-webkit-inner-spin-button,
.product-quantity-control input::-webkit-outer-spin-button {
  appearance: none;
  margin: 0;
}
.product-add-button,
.product-buy-now {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  min-height: 46px;
  border: 1px solid var(--brand-800);
  border-radius: 9px;
  background: var(--brand-800);
  padding: 10px 17px;
  color: #fff;
  font-size: 13px;
  font-weight: 850;
  cursor: pointer;
  transition: background 0.18s, border-color 0.18s;
}
.product-add-button:hover,
.product-buy-now:hover {
  border-color: var(--brand-900);
  background: var(--brand-900);
}
.product-add-button:disabled,
.product-buy-now:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.product-buy-now {
  width: 100%;
  margin-top: 10px;
  border-color: #d7e4da;
  background: #fff;
  color: var(--brand-900);
}
.product-buy-now:hover {
  border-color: var(--brand-800);
  background: #f0f7f2;
  color: var(--brand-900);
}
.product-cart-success,
.product-cart-error {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 7px;
  margin: 12px 0 0;
  font-size: 12px;
  font-weight: 700;
}
.product-cart-success {
  color: #147344;
}
.product-cart-success a {
  color: var(--brand-800);
  text-decoration: underline;
}
.product-cart-error {
  color: #b42318;
}
.product-assurance {
  margin-top: 24px;
  border: 1px solid #e8eeea;
  border-radius: 15px;
  background: linear-gradient(145deg, #fff 0%, #fbfdfb 100%);
  padding: 13px 15px 5px;
  box-shadow: 0 8px 24px #173b2307;
}
.product-assurance__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 2px 1px 10px;
}
.product-assurance__heading > span {
  color: var(--brand-800);
  font-size: 9px;
  font-weight: 900;
  letter-spacing: 1.1px;
}
.product-assurance__heading > small {
  color: var(--ink-500);
  font-size: 9px;
}
.product-assurance-list {
  display: grid;
}
.product-assurance-item {
  border-top: 1px solid #edf1ee;
}
.product-assurance-item__heading {
  margin: 0;
}
.product-assurance-item__trigger {
  display: grid;
  width: 100%;
  grid-template-columns: 38px minmax(0, 1fr) 28px;
  align-items: center;
  gap: 11px;
  border: 0;
  background: transparent;
  padding: 12px 1px;
  text-align: left;
  cursor: pointer;
}
.product-assurance-icon {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 11px;
  background: #edf5ef;
  color: var(--brand-800);
  transition: background 0.18s, color 0.18s;
}
.product-assurance-item__copy {
  display: grid;
  gap: 4px;
  min-width: 0;
}
.product-assurance-item__copy strong {
  color: var(--ink-700);
  font-size: 11px;
  font-weight: 850;
  transition: color 0.18s;
}
.product-assurance-item__copy small {
  color: var(--ink-500);
  font-size: 10px;
  line-height: 1.4;
}
.product-assurance-item__chevron {
  display: grid;
  width: 27px;
  height: 27px;
  place-items: center;
  border-radius: 50%;
  color: var(--ink-500);
  transition: transform 0.2s, background 0.2s, color 0.2s;
}
.product-assurance-item__trigger:hover .product-assurance-item__copy strong,
.product-assurance-item.is-open .product-assurance-item__copy strong {
  color: var(--brand-800);
}
.product-assurance-item.is-open .product-assurance-icon {
  background: var(--brand-800);
  color: #fff;
}
.product-assurance-item.is-open .product-assurance-item__chevron {
  transform: rotate(180deg);
  background: #edf5ef;
  color: var(--brand-800);
}
.product-assurance-item__trigger:focus-visible {
  outline: 2px solid var(--brand-700);
  outline-offset: 2px;
  border-radius: 8px;
}
.product-assurance-item__panel {
  padding: 0 12px 14px 50px;
}
.product-assurance-item__panel p {
  margin: 0 0 9px;
  color: var(--ink-500);
  font-size: 11px;
  line-height: 1.7;
}
.product-assurance-item__link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--brand-800);
  font-size: 10px;
  font-weight: 850;
  text-decoration: none;
}
.product-assurance-item__link:hover {
  text-decoration: underline;
}
.assurance-expand-enter-active,
.assurance-expand-leave-active {
  overflow: hidden;
  transition: opacity 0.18s ease, transform 0.18s ease;
}
.assurance-expand-enter-from,
.assurance-expand-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}
.product-information {
  margin-top: 66px;
  padding-top: 36px;
  border-top: 1px solid var(--line);
}
.product-information__heading {
  max-width: 620px;
  margin-bottom: 22px;
}
.product-information__heading h2,
.product-recommendations__heading h2 {
  margin: 7px 0 5px;
  color: var(--ink-900);
  font-size: clamp(22px, 2.5vw, 29px);
  font-weight: 900;
  letter-spacing: -0.7px;
}
.product-information__heading > p:last-child,
.product-recommendations__heading p {
  margin: 0;
  color: var(--ink-500);
  font-size: 12px;
  line-height: 1.7;
}
.product-information__grid {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
  gap: 16px;
}
.product-description-card,
.product-specification-card {
  min-width: 0;
  border: 1px solid var(--line);
  border-radius: 13px;
  background: #fff;
  padding: 20px;
}
.product-description-card h3,
.product-specification-card h3 {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 15px;
  color: var(--ink-900);
  font-size: 14px;
  font-weight: 850;
}
.product-description-card h3 :deep(svg),
.product-specification-card h3 :deep(svg) {
  color: var(--brand-700);
}
.product-description-text,
.product-description-empty {
  margin: 0;
  color: var(--ink-700);
  font-size: 12px;
  line-height: 1.9;
  white-space: pre-line;
}
.product-description-empty {
  color: var(--ink-500);
  font-style: italic;
}
.product-specification-list {
  margin: 0;
}
.product-specification-list > div {
  display: grid;
  grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
  gap: 14px;
  padding: 10px 0;
  border-bottom: 1px solid #edf0ee;
  font-size: 11px;
}
.product-specification-list > div:first-child {
  padding-top: 0;
}
.product-specification-list > div:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}
.product-specification-list dt {
  color: var(--ink-500);
}
.product-specification-list dd {
  margin: 0;
  color: var(--ink-900);
  font-weight: 800;
  overflow-wrap: anywhere;
}
.product-recommendations {
  margin-top: 64px;
  padding-top: 35px;
  border-top: 1px solid var(--line);
}
.product-recommendations__heading {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 20px;
}
.product-recommendations__heading > a {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--brand-700);
  font-size: 12px;
  font-weight: 850;
  text-decoration: none;
  white-space: nowrap;
}
.product-recommendations__heading > a:hover {
  text-decoration: underline;
}
.product-detail-error,
.product-detail-loading {
  padding: 80px 20px;
  text-align: center;
}
.product-detail-error__icon {
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  margin: 0 auto 13px;
  border-radius: 50%;
  background: #fff0ed;
  color: #b42318;
  font-size: 22px;
  font-weight: 900;
}
.product-detail-error h1 {
  margin: 0;
  color: var(--ink-900);
  font-size: 22px;
  font-weight: 850;
}
.product-detail-error p,
.product-detail-loading p {
  margin: 8px 0 0;
  color: var(--ink-500);
  font-size: 13px;
}
.product-detail-secondary {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--line);
  border-radius: 999px;
  padding: 10px 17px;
  color: var(--brand-800);
  font-size: 12px;
  font-weight: 800;
  text-decoration: none;
}
.product-detail-loading__spinner {
  display: block;
  width: 30px;
  height: 30px;
  margin: 0 auto;
  border: 3px solid #dce9df;
  border-top-color: var(--brand-700);
  border-radius: 50%;
  animation: product-spin 0.8s linear infinite;
}
@keyframes product-spin {
  to {
    transform: rotate(360deg);
  }
}
@media (max-width: 900px) {
  .product-detail-layout {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 25px;
  }
  .product-gallery__main {
    min-height: 300px;
  }
  .product-buy-controls {
    grid-template-columns: 1fr;
  }
  .product-quantity-control {
    width: max-content;
  }
  .product-information__grid {
    grid-template-columns: 1fr;
  }
}
@media (hover: none) {
  .product-gallery__main.is-zooming > .storefront-image {
    transform: none;
  }
  .product-gallery__zoom-hint {
    display: none;
  }
}
@media (max-width: 640px) {
  .product-detail-page {
    padding-block: 17px 42px;
  }
  .product-detail-layout {
    grid-template-columns: 1fr;
    gap: 22px;
  }
  .product-gallery__main {
    min-height: 0;
    padding: 15px;
  }
  .product-gallery__main > .storefront-image,
  .product-gallery__main > .image-placeholder {
    max-height: 390px;
  }
  .product-gallery__thumbnail {
    width: 65px;
    height: 65px;
  }
  .product-detail-title {
    font-size: 27px;
  }
  .product-detail-price-panel {
    margin-top: 16px;
    padding: 14px;
  }
  .product-buy-controls {
    grid-template-columns: auto minmax(0, 1fr);
  }
  .product-information {
    margin-top: 42px;
    padding-top: 27px;
  }
  .product-description-card,
  .product-specification-card {
    padding: 16px;
  }
  .product-recommendations {
    margin-top: 43px;
    padding-top: 27px;
  }
  .product-recommendations__heading {
    align-items: flex-start;
  }
  .product-recommendations__heading h2 {
    font-size: 22px;
  }
  .product-recommendations .product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .product-detail-page * {
    scroll-behavior: auto;
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
</style>
