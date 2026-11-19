<script setup lang="ts">
const { cart, loading, error, refresh, update, remove } = useStorefrontCart()

const money = (value: number) =>
  `৳${Number(value || 0).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`

const pendingVariantId = ref<number | null>(null)
const actionError = ref('')

const totalUnits = computed(() =>
  cart.value.items.reduce((total, item) => total + Number(item.quantity || 0), 0),
)

onMounted(() => {
  refresh().catch(() => undefined)
})

useSeoMeta({
  title: 'Shopping Cart | MADINA Scale',
  description: 'Review your selected MADINA Scale products before checkout.',
})

async function changeQuantity(variantId: number, quantity: number) {
  if (pendingVariantId.value !== null) return

  pendingVariantId.value = variantId
  actionError.value = ''

  try {
    await update(variantId, quantity)
  } catch (cause: any) {
    actionError.value =
      cause?.data?.message || 'We could not update the quantity. Please try again.'
  } finally {
    pendingVariantId.value = null
  }
}

async function removeItem(variantId: number) {
  if (pendingVariantId.value !== null) return

  pendingVariantId.value = variantId
  actionError.value = ''

  try {
    await remove(variantId)
  } catch (cause: any) {
    actionError.value =
      cause?.data?.message || 'We could not remove this item. Please try again.'
  } finally {
    pendingVariantId.value = null
  }
}
</script>

<template>
  <main class="container py-8 sm:py-10 lg:py-14">
    <div class="mx-auto max-w-7xl">
      <div class="flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-emerald-800">
            Your selection
          </p>
          <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
            Shopping cart
          </h1>
          <p class="mt-2 max-w-xl text-sm leading-6 text-slate-600">
            Review your products and quantities before moving to secure checkout.
          </p>
        </div>

        <NuxtLink
          to="/products"
          class="inline-flex min-h-11 items-center gap-2 self-start rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-emerald-700 hover:text-emerald-800 sm:self-auto"
        >
          <span aria-hidden="true">←</span>
          Continue shopping
        </NuxtLink>
      </div>

      <div
        v-if="loading"
        class="mt-8 grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]"
        aria-live="polite"
        aria-busy="true"
      >
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="h-5 w-40 animate-pulse rounded bg-slate-100" />
          <div v-for="index in 2" :key="index" class="mt-6 flex gap-4 border-t border-slate-100 pt-6">
            <div class="size-24 shrink-0 animate-pulse rounded-xl bg-slate-100" />
            <div class="flex-1 space-y-3">
              <div class="h-4 w-2/3 animate-pulse rounded bg-slate-100" />
              <div class="h-3 w-1/3 animate-pulse rounded bg-slate-100" />
              <div class="h-4 w-1/4 animate-pulse rounded bg-slate-100" />
            </div>
          </div>
        </section>
        <aside class="h-64 animate-pulse rounded-2xl border border-slate-200 bg-white" />
      </div>

      <div
        v-else-if="error && !cart.items.length"
        class="mt-8 rounded-2xl border border-red-200 bg-red-50 p-6"
        role="alert"
      >
        <p class="font-bold text-red-900">We couldn't load your cart</p>
        <p class="mt-1 text-sm text-red-800">{{ error }}</p>
        <button
          type="button"
          class="mt-4 rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-red-800"
          @click="refresh().catch(() => undefined)"
        >
          Try again
        </button>
      </div>

      <section
        v-else-if="!cart.items.length"
        class="mt-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
      >
        <div class="mx-auto flex max-w-xl flex-col items-center px-6 py-14 text-center sm:py-20">
          <div class="grid size-20 place-items-center rounded-3xl bg-emerald-50 text-emerald-800">
            <UiIcon name="shopping-cart" :size="36" />
          </div>
          <p class="mt-7 text-xs font-extrabold uppercase tracking-[0.16em] text-emerald-800">
            Nothing here yet
          </p>
          <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">
            Your cart is waiting
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-600">
            Explore our weighing solutions and add the products that fit your needs.
          </p>
          <NuxtLink
            to="/products"
            class="mt-7 inline-flex min-h-12 items-center justify-center rounded-xl bg-emerald-800 px-6 py-3 text-sm font-extrabold text-white shadow-sm transition hover:bg-emerald-900"
          >
            Explore products
          </NuxtLink>
        </div>
      </section>

      <div v-else class="mt-7 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <section class="min-w-0">
          <div class="mb-4 flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-extrabold text-slate-950">Cart items</h2>
              <p class="mt-1 text-sm text-slate-500">
                {{ cart.items.length }} {{ cart.items.length === 1 ? 'product' : 'products' }}
                <span class="px-1 text-slate-300">·</span>
                {{ totalUnits }} {{ totalUnits === 1 ? 'item' : 'items' }} in total
              </p>
            </div>
            <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800">
              {{ cart.item_count }} in cart
            </span>
          </div>

          <div
            v-if="actionError"
            class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            role="alert"
          >
            {{ actionError }}
          </div>

          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <article
              v-for="(item, index) in cart.items"
              :key="item.variant_id"
              class="grid gap-4 p-4 sm:grid-cols-[112px_minmax(0,1fr)_auto] sm:items-center sm:p-5"
              :class="{ 'border-t border-slate-100': index > 0 }"
            >
              <NuxtLink
                :to="`/products/${item.product_slug}`"
                class="block aspect-square w-28 overflow-hidden rounded-xl border border-slate-100 bg-slate-50 sm:w-28"
                :aria-label="`View ${item.product_name}`"
              >
                <StorefrontImage
                  :src="item.image_url"
                  :alt="item.product_name"
                  kind="product"
                  label="Cart Product Image"
                  dimensions="240 × 240 px"
                  ratio="1 / 1"
                  :width="240"
                  :height="240"
                />
              </NuxtLink>

              <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.13em] text-slate-400">
                  {{ item.sku ? `SKU: ${item.sku}` : 'MADINA product' }}
                </p>
                <NuxtLink
                  :to="`/products/${item.product_slug}`"
                  class="mt-1 block text-base font-extrabold leading-6 text-slate-950 transition hover:text-emerald-800"
                >
                  {{ item.product_name }}
                </NuxtLink>
                <p class="mt-1 text-sm text-slate-500">
                  {{ item.variant_name }}
                </p>
                <p class="mt-3 text-sm font-bold text-slate-700">
                  {{ money(item.price) }}
                  <span class="font-normal text-slate-400">/ unit</span>
                </p>
              </div>

              <div class="flex flex-row items-center justify-between gap-4 border-t border-slate-100 pt-3 sm:flex-col sm:items-end sm:border-0 sm:pt-0">
                <div class="text-right">
                  <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                    Line total
                  </p>
                  <p class="mt-1 text-lg font-black tabular-nums text-slate-950">
                    {{ money(item.line_total) }}
                  </p>
                </div>

                <div class="flex items-center gap-3">
                  <div class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white">
                    <button
                      type="button"
                      class="grid size-9 place-items-center rounded-l-xl text-lg font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                      :disabled="item.quantity <= 1 || pendingVariantId !== null"
                      :aria-label="`Decrease quantity of ${item.product_name}`"
                      @click="changeQuantity(item.variant_id, item.quantity - 1)"
                    >
                      −
                    </button>
                    <span class="min-w-8 text-center text-sm font-extrabold tabular-nums text-slate-900">
                      {{ item.quantity }}
                    </span>
                    <button
                      type="button"
                      class="grid size-9 place-items-center rounded-r-xl text-lg font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                      :disabled="item.quantity >= 99 || pendingVariantId !== null"
                      :aria-label="`Increase quantity of ${item.product_name}`"
                      @click="changeQuantity(item.variant_id, item.quantity + 1)"
                    >
                      +
                    </button>
                  </div>

                  <button
                    type="button"
                    class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-2 text-xs font-bold text-slate-500 transition hover:bg-red-50 hover:text-red-700 disabled:cursor-wait disabled:opacity-50"
                    :disabled="pendingVariantId !== null"
                    @click="removeItem(item.variant_id)"
                  >
                    <UiIcon name="x" :size="15" />
                    {{ pendingVariantId === item.variant_id ? 'Updating…' : 'Remove' }}
                  </button>
                </div>
              </div>
            </article>
          </div>

          <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-4">
            <div class="flex items-center gap-3">
              <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-emerald-800 shadow-sm">
                <UiIcon name="shield-check" :size="20" />
              </span>
              <div>
                <p class="text-sm font-extrabold text-slate-900">Ready for a secure checkout?</p>
                <p class="mt-0.5 text-xs text-slate-600">Review your delivery details in the next step.</p>
              </div>
            </div>
            <NuxtLink
              to="/checkout"
              class="text-sm font-extrabold text-emerald-800 underline-offset-4 hover:underline"
            >
              Continue to checkout →
            </NuxtLink>
          </div>
        </section>

        <aside class="lg:sticky lg:top-24">
          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
              <p class="text-xs font-extrabold uppercase tracking-[0.15em] text-emerald-800">
                Almost there
              </p>
              <h2 class="mt-1 text-xl font-black tracking-tight text-slate-950">
                Order summary
              </h2>
            </div>

            <div class="space-y-4 px-5 py-5 sm:px-6">
              <div class="flex items-center justify-between text-sm">
                <span class="text-slate-600">Products ({{ cart.item_count }})</span>
                <strong class="font-bold tabular-nums text-slate-900">{{ money(cart.subtotal) }}</strong>
              </div>
              <div class="flex items-center justify-between text-sm">
                <span class="text-slate-600">Delivery charge</span>
                <span class="text-right text-xs font-medium text-slate-500">Calculated at checkout</span>
              </div>
              <div class="border-t border-dashed border-slate-200 pt-4">
                <div class="flex items-end justify-between gap-3">
                  <span class="text-sm font-extrabold text-slate-900">Subtotal</span>
                  <strong class="text-2xl font-black tracking-tight tabular-nums text-slate-950">
                    {{ money(cart.subtotal) }}
                  </strong>
                </div>
                <p class="mt-2 text-xs leading-5 text-slate-500">
                  Final delivery charges and payable total will be confirmed during checkout.
                </p>
              </div>

              <NuxtLink
                to="/checkout"
                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-emerald-800 px-5 py-3 text-sm font-extrabold text-white shadow-sm transition hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
              >
                Proceed to checkout
                <UiIcon name="arrow-right" :size="17" />
              </NuxtLink>

              <div class="flex items-center justify-center gap-2 pt-1 text-xs font-medium text-slate-500">
                <UiIcon name="shield-check" :size="14" />
                Secure order process
              </div>
            </div>
          </div>

          <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-sm font-extrabold text-slate-900">Need help choosing?</p>
            <p class="mt-1 text-xs leading-5 text-slate-600">
              Our team can help you find the right weighing solution for your requirements.
            </p>
            <NuxtLink
              to="/pages/contact"
              class="mt-3 inline-flex text-xs font-extrabold text-emerald-800 hover:underline"
            >
              Contact our team →
            </NuxtLink>
          </div>
        </aside>
      </div>
    </div>
  </main>
</template>
