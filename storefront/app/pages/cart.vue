<script setup lang="ts">
const { cart, loading, error, refresh, update, remove } = useStorefrontCart()
const money = (value: number) => `৳${Number(value || 0).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`
onMounted(() => { refresh().catch(() => undefined) })
useSeoMeta({ title: 'Shopping Cart | MADINA Scale', description: 'Review your MADINA Scale shopping cart.' })
</script>
<template>
  <main class="container py-10 md:py-14">
      <p class="text-sm font-bold uppercase tracking-widest text-emerald-800">Your selection</p><h1 class="mt-2 text-3xl font-extrabold">Shopping cart</h1>
      <p v-if="error" class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>
      <div v-if="loading" class="surface mt-8 p-8 muted">Loading your cart…</div>
      <div v-else-if="!cart.items.length" class="surface mt-8 p-10 text-center"><div class="cart-empty-icon"><UiIcon name="shopping-cart" :size="34" /></div><h2 class="mt-4 text-xl font-bold">Your cart is empty</h2><p class="mt-2 muted">Explore our weighing solutions and add a product to get started.</p><NuxtLink to="/products" class="btn-primary mt-6 inline-flex">Explore products</NuxtLink></div>
      <div v-else class="mt-8 grid items-start gap-6 lg:grid-cols-[1fr_340px]">
        <section class="surface divide-y divide-slate-100 overflow-hidden">
          <article v-for="item in cart.items" :key="item.variant_id" class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-6">
            <div class="size-24 shrink-0 overflow-hidden rounded-xl bg-slate-100"><StorefrontImage :src="item.image_url" :alt="item.product_name" kind="product" label="Cart Product Image" dimensions="240 × 240 px" ratio="1 / 1" :width="240" :height="240" /></div>
            <div class="min-w-0 flex-1"><NuxtLink :to="`/products/${item.product_slug}`" class="font-bold hover:text-emerald-800">{{ item.product_name }}</NuxtLink><p class="mt-1 text-sm muted">{{ item.variant_name }} <span v-if="item.sku">· {{ item.sku }}</span></p><p class="mt-2 font-bold">{{ money(item.price) }}</p></div>
            <div class="flex items-center justify-between gap-4 sm:flex-col sm:items-end"><div class="flex items-center gap-2"><button class="grid size-8 place-items-center rounded-full border" aria-label="Decrease quantity" @click="update(item.variant_id, Math.max(1,item.quantity-1))">−</button><span class="w-7 text-center font-semibold">{{ item.quantity }}</span><button class="grid size-8 place-items-center rounded-full border" aria-label="Increase quantity" @click="update(item.variant_id, Math.min(99,item.quantity+1))">+</button></div><strong>{{ money(item.line_total) }}</strong><button class="text-xs font-semibold text-red-600" @click="remove(item.variant_id)">Remove</button></div>
          </article>
        </section>
        <aside class="surface p-6"><h2 class="text-lg font-extrabold">Order summary</h2><div class="mt-5 flex justify-between text-sm"><span class="muted">Items ({{ cart.item_count }})</span><strong>{{ money(cart.subtotal) }}</strong></div><div class="mt-4 flex justify-between border-t pt-4"><span class="font-bold">Subtotal</span><strong class="text-xl">{{ money(cart.subtotal) }}</strong></div><p class="mt-3 text-xs muted">Delivery charges and final payable amount will be confirmed at checkout.</p><NuxtLink to="/checkout" class="btn-primary mt-6 flex justify-center">Proceed to checkout</NuxtLink></aside>
      </div>
    </main>
</template>
