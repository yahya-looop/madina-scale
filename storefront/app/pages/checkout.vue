<script setup lang="ts">
const { cart, loading, error, refresh } = useStorefrontCart()
const form = reactive({ customer_name: '', customer_phone: '', division: '', district: '', area: '', address_line: '', notes: '' })
const submitting = ref(false)
const message = ref('')
onMounted(() => { refresh().catch(() => undefined) })
useSeoMeta({ title: 'Checkout | MADINA Scale', description: 'Complete your MADINA Scale order.' })
async function placeOrder() {
  message.value = ''; submitting.value = true
  try {
    const config = useRuntimeConfig()
    const token = await $fetch<{token:string}>(`${config.public.apiBase}/csrf-token`, { credentials: 'include' })
    const result = await $fetch<any>(`${config.public.apiBase}/checkout`, { method: 'POST', credentials: 'include', headers: { 'X-CSRF-TOKEN': token.token }, body: { ...form, idempotency_key: crypto.randomUUID() } })
    await navigateTo(`/order-success?order=${encodeURIComponent(result.data.order_number)}`)
  } catch (cause: any) {
    message.value = cause?.data?.message || Object.values(cause?.data?.errors || {}).flat().join(' ') || 'We could not place your order. Please review your details and try again.'
  } finally { submitting.value = false }
}
</script>
<template>
  <main class="container py-10"><p class="text-sm font-bold uppercase tracking-widest text-emerald-800">Secure checkout</p><h1 class="mt-2 text-3xl font-extrabold">Delivery information</h1>
      <div v-if="loading" class="surface mt-8 p-6 muted">Loading cart…</div><div v-else-if="!cart.items.length" class="surface mt-8 p-8">Your cart is empty. <NuxtLink to="/products" class="font-bold text-emerald-800">Browse products</NuxtLink></div>
      <div v-else class="mt-8 grid items-start gap-6 lg:grid-cols-[1fr_340px]"><form class="surface grid gap-4 p-5 md:grid-cols-2 md:p-7" @submit.prevent="placeOrder"><label class="grid gap-1 text-sm font-semibold">Full name<input v-model="form.customer_name" class="rounded-xl border p-3 font-normal" autocomplete="name"></label><label class="grid gap-1 text-sm font-semibold">Mobile number *<input v-model="form.customer_phone" required inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" class="rounded-xl border p-3 font-normal"></label><label class="grid gap-1 text-sm font-semibold">Division<input v-model="form.division" class="rounded-xl border p-3 font-normal"></label><label class="grid gap-1 text-sm font-semibold">District<input v-model="form.district" class="rounded-xl border p-3 font-normal"></label><label class="grid gap-1 text-sm font-semibold">Area / Thana<input v-model="form.area" class="rounded-xl border p-3 font-normal"></label><label class="grid gap-1 text-sm font-semibold md:col-span-2">Delivery address<textarea v-model="form.address_line" rows="3" class="rounded-xl border p-3 font-normal"></textarea></label><label class="grid gap-1 text-sm font-semibold md:col-span-2">Order notes (optional)<textarea v-model="form.notes" rows="2" class="rounded-xl border p-3 font-normal"></textarea></label><div class="md:col-span-2 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900"><strong>Payment method: Cash on Delivery</strong><p class="mt-1">Pay when your order is delivered. Our team will confirm your order.</p></div><p v-if="message || error" class="md:col-span-2 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ message || error }}</p><button :disabled="submitting" class="btn-primary md:col-span-2 disabled:opacity-60">{{ submitting ? 'Placing order…' : 'Place order' }}</button></form>
      <aside class="surface p-6"><h2 class="font-extrabold">Order summary</h2><div v-for="item in cart.items" :key="item.variant_id" class="mt-4 flex justify-between gap-3 text-sm"><span>{{item.product_name}} × {{item.quantity}}</span><strong>৳{{Number(item.line_total).toLocaleString('en-BD')}}</strong></div><div class="mt-5 flex justify-between border-t pt-4 font-extrabold"><span>Subtotal</span><span>৳{{Number(cart.subtotal).toLocaleString('en-BD')}}</span></div></aside></div>
    </main>
</template>
