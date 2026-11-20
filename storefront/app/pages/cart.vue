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
  <main class="min-h-[70vh] bg-[#f5f8f6] pb-16 text-slate-900">
    <header class="border-b border-white/10 bg-[#073f31] text-white">
      <div class="container flex min-h-[76px] flex-wrap items-center justify-between gap-4 py-3">
        <NuxtLink to="/" class="text-xl font-black tracking-tight">MADINA<span class="text-emerald-300"> SCALE</span></NuxtLink>
        <nav aria-label="Checkout progress" class="flex items-center gap-2 text-[11px] font-bold sm:gap-3">
          <span class="flex items-center gap-2 text-white"><span class="grid size-8 place-items-center rounded-full bg-white text-emerald-950">1</span><span>Cart</span></span>
          <span class="h-px w-5 bg-white/25 sm:w-8" />
          <NuxtLink to="/checkout" class="flex items-center gap-2 text-emerald-100/75 transition hover:text-white"><span class="grid size-8 place-items-center rounded-full bg-white/15">2</span><span>Checkout</span></NuxtLink>
          <span class="h-px w-5 bg-white/25 sm:w-8" />
          <span class="flex items-center gap-2 text-emerald-100/55"><span class="grid size-8 place-items-center rounded-full bg-white/10">3</span><span>Confirmation</span></span>
        </nav>
        <NuxtLink to="/products" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-white/20 px-3 py-2 text-xs font-bold text-white transition hover:bg-white/10"><span aria-hidden="true">←</span><span>Continue shopping</span></NuxtLink>
      </div>
    </header>

    <section class="container max-w-7xl pt-7 sm:pt-9">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p class="flex items-center gap-2 text-[11px] font-extrabold uppercase tracking-[0.2em] text-emerald-800"><span class="size-1.5 rounded-full bg-emerald-600" /> Your selection</p>
          <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Shopping cart</h1>
          <p class="mt-2 max-w-xl text-sm leading-6 text-slate-600 sm:text-base">Review your selected weighing solutions before secure checkout.</p>
        </div>
        <p class="text-xs font-medium text-slate-500"><strong class="text-slate-900">{{ cart.item_count }}</strong> item(s) in your cart</p>
      </div>
    </section>

    <div class="container max-w-7xl pt-7 sm:pt-9">
      <div v-if="loading" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]" aria-live="polite" aria-busy="true">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="h-5 w-40 animate-pulse rounded bg-slate-100" />
          <div v-for="index in 3" :key="index" class="mt-6 flex gap-4 border-t border-slate-100 pt-6">
            <div class="size-24 shrink-0 animate-pulse rounded-2xl bg-slate-100" />
            <div class="flex-1 space-y-3"><div class="h-4 w-2/3 animate-pulse rounded bg-slate-100" /><div class="h-3 w-1/3 animate-pulse rounded bg-slate-100" /><div class="h-4 w-1/4 animate-pulse rounded bg-slate-100" /></div>
          </div>
        </section>
        <aside class="h-72 animate-pulse rounded-3xl border border-slate-200 bg-white" />
      </div>

      <section v-else-if="error && !cart.items.length" class="rounded-3xl border border-red-200 bg-white p-7 shadow-sm" role="alert">
        <div class="flex items-start gap-3">
          <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-red-50 text-red-700"><UiIcon name="info" /></span>
          <div><h2 class="font-extrabold text-red-950">We couldn't load your cart</h2><p class="mt-1 text-sm text-red-700">{{ error }}</p>
            <button type="button" class="mt-4 rounded-xl bg-[#073f31] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-950" @click="refresh().catch(() => undefined)">Try again</button>
          </div>
        </div>
      </section>

      <section v-else-if="!cart.items.length" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-xl flex-col items-center px-6 py-16 text-center sm:py-24">
          <div class="grid size-20 place-items-center rounded-[1.75rem] bg-emerald-50 text-emerald-800 ring-8 ring-emerald-50/50"><UiIcon name="shopping-cart" :size="34" /></div>
          <p class="mt-8 text-[11px] font-extrabold uppercase tracking-[0.2em] text-emerald-800">Nothing here yet</p>
          <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Your cart is waiting</h2>
          <p class="mt-3 max-w-md text-sm leading-6 text-slate-600">Explore our weighing solutions and add the products that fit your needs. Your selection will appear here.</p>
          <NuxtLink to="/products" class="mt-7 inline-flex min-h-12 items-center gap-2 rounded-xl bg-[#073f31] px-6 py-3 text-sm font-extrabold text-white shadow-lg shadow-emerald-950/10 transition hover:bg-emerald-950">
            Explore products <UiIcon name="arrow-right" :size="17" />
          </NuxtLink>
        </div>
      </section>

      <div v-else class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_370px] lg:gap-8">
        <section class="min-w-0">
          <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-emerald-800">Your selection</p><h2 class="mt-1 text-xl font-black tracking-tight text-slate-950">Cart items</h2>
              <p class="mt-1 text-sm text-slate-500">{{ cart.items.length }} {{ cart.items.length === 1 ? 'product' : 'products' }} <span class="px-1 text-slate-300">·</span> {{ totalUnits }} {{ totalUnits === 1 ? 'unit' : 'units' }} in total</p>
            </div>
            <span class="rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-xs font-extrabold text-emerald-900">{{ cart.item_count }} in cart</span>
          </div>

          <div v-if="actionError" class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ actionError }}</div>

          <div class="space-y-3">
            <article v-for="(item, index) in cart.items" :key="item.variant_id" class="group rounded-3xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-200 hover:shadow-md sm:p-5">
              <div class="grid gap-4 sm:grid-cols-[120px_minmax(0,1fr)] sm:items-center">
                <NuxtLink :to="`/products/${item.product_slug}`" class="block aspect-square w-full max-w-[120px] overflow-hidden rounded-2xl border border-slate-100 bg-slate-50" :aria-label="`View ${item.product_name}`">
                  <StorefrontImage :src="item.image_url" :alt="item.product_name" kind="product" label="Cart Product Image" dimensions="240 × 240 px" ratio="1 / 1" :width="240" :height="240" />
                </NuxtLink>
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2"><span class="text-[10px] font-extrabold uppercase tracking-[0.15em] text-slate-400">{{ item.sku ? `SKU: ${item.sku}` : 'MADINA product' }}</span><span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-800">In your cart</span></div>
                  <NuxtLink :to="`/products/${item.product_slug}`" class="mt-2 block text-base font-extrabold leading-6 text-slate-950 transition hover:text-emerald-800 sm:text-lg">{{ item.product_name }}</NuxtLink>
                  <p class="mt-1 text-sm text-slate-500">{{ item.variant_name }}</p>
                  <p class="mt-3 text-sm font-bold text-slate-700">{{ money(item.price) }} <span class="font-normal text-slate-400">/ unit</span></p>
                </div>
              </div>
              <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <div class="flex items-center gap-2">
                  <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Quantity</span>
                  <div class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-slate-50">
                    <button type="button" class="grid size-9 place-items-center rounded-l-xl text-lg font-semibold text-slate-600 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40" :disabled="item.quantity <= 1 || pendingVariantId !== null" :aria-label="`Decrease quantity of ${item.product_name}`" @click="changeQuantity(item.variant_id, item.quantity - 1)">−</button>
                    <span class="min-w-8 text-center text-sm font-extrabold tabular-nums text-slate-900">{{ item.quantity }}</span>
                    <button type="button" class="grid size-9 place-items-center rounded-r-xl text-lg font-semibold text-slate-600 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40" :disabled="item.quantity >= 99 || pendingVariantId !== null" :aria-label="`Increase quantity of ${item.product_name}`" @click="changeQuantity(item.variant_id, item.quantity + 1)">+</button>
                  </div>
                  <button type="button" class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2 text-xs font-bold text-slate-400 transition hover:bg-red-50 hover:text-red-700 disabled:cursor-wait disabled:opacity-50" :disabled="pendingVariantId !== null" @click="removeItem(item.variant_id)"><UiIcon name="x" :size="14" />{{ pendingVariantId === item.variant_id ? 'Updating…' : 'Remove' }}</button>
                </div>
                <div class="text-right"><p class="text-[10px] font-extrabold uppercase tracking-[0.15em] text-slate-400">Line total</p><p class="mt-1 text-xl font-black tabular-nums tracking-tight text-slate-950">{{ money(item.line_total) }}</p></div>
              </div>
            </article>
          </div>

          <div class="mt-5 flex flex-wrap items-center gap-3 rounded-3xl border border-emerald-100 bg-emerald-50/70 p-4 sm:p-5">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-white text-emerald-800 shadow-sm"><UiIcon name="shield-check" :size="21" /></span>
            <div class="min-w-0 flex-1"><p class="text-sm font-extrabold text-slate-950">Ready for a secure checkout?</p><p class="mt-1 text-xs leading-5 text-slate-600">Review your delivery details and payment method in the next step.</p></div>
            <NuxtLink to="/checkout" class="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-extrabold text-emerald-900 transition hover:bg-white">Continue <UiIcon name="arrow-right" :size="16" /></NuxtLink>
          </div>
        </section>

        <aside class="space-y-4 lg:sticky lg:top-24">
          <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="bg-[#073f31] px-5 py-5 text-white sm:px-6"><p class="text-[10px] font-extrabold uppercase tracking-[0.2em] text-emerald-200">Almost there</p><h2 class="mt-1 text-xl font-black tracking-tight">Order summary</h2><p class="mt-1 text-xs text-emerald-50/70">A clear overview before checkout</p></div>
            <div class="space-y-4 p-5 sm:p-6">
              <div class="flex items-center justify-between text-sm"><span class="text-slate-600">Products ({{ cart.item_count }})</span><strong class="font-bold tabular-nums text-slate-900">{{ money(cart.subtotal) }}</strong></div>
              <div class="flex items-start justify-between gap-3 text-sm"><span class="text-slate-600">Delivery charge</span><span class="max-w-[150px] text-right text-xs leading-5 text-slate-500">Calculated at checkout</span></div>
              <div class="border-t border-dashed border-slate-200 pt-4"><div class="flex items-end justify-between gap-3"><span class="text-sm font-extrabold text-slate-900">Subtotal</span><strong class="text-2xl font-black tracking-tight tabular-nums text-slate-950">{{ money(cart.subtotal) }}</strong></div><p class="mt-2 text-xs leading-5 text-slate-500">Final delivery charges and payable total will be confirmed during checkout.</p></div>
              <NuxtLink to="/checkout" class="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-[#073f31] px-5 py-3.5 text-sm font-extrabold text-white shadow-lg shadow-emerald-950/10 transition hover:bg-emerald-950 focus:outline-none focus:ring-4 focus:ring-emerald-800/20">Proceed to checkout <UiIcon name="arrow-right" :size="17" /></NuxtLink>
              <div class="flex items-center justify-center gap-2 pt-1 text-[11px] font-semibold text-slate-500"><UiIcon name="shield-check" :size="14" /> Secure order process</div>
            </div>
          </section>
          <section class="rounded-3xl border border-slate-200 bg-white p-5"><div class="flex gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-slate-50 text-emerald-800"><UiIcon name="headset" :size="19" /></span><div><p class="text-sm font-extrabold text-slate-950">Need help choosing?</p><p class="mt-1 text-xs leading-5 text-slate-600">Our team can help you find the right weighing solution for your requirements.</p><NuxtLink to="/pages/contact" class="mt-3 inline-flex text-xs font-extrabold text-emerald-800 hover:underline">Contact our team →</NuxtLink></div></div></section>
        </aside>
      </div>
    </div>
  </main>
</template>
