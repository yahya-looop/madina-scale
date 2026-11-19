<script setup lang="ts">
type CheckoutForm = {
  customer_name: string
  customer_phone: string
  division: string
  district: string
  area: string
  address_line: string
  notes: string
}

const { cart, loading, error, refresh } = useStorefrontCart()
const form = reactive<CheckoutForm>({
  customer_name: '',
  customer_phone: '',
  division: '',
  district: '',
  area: '',
  address_line: '',
  notes: '',
})

const submitting = ref(false)
const message = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const orderAttemptKey = ref('')
const paymentMethod = ref<'cash_on_delivery' | 'bank_transfer'>('cash_on_delivery')
const bankForm = reactive({
  sender_account: '',
  transaction_reference: '',
  amount: '',
})
const demoBank = {
  name: 'MADINA Demo Bank',
  account_name: 'MADINA Scale',
  account_number: '012345678901',
  branch: 'Dhaka Main Branch',
  routing_number: '123456789',
}

const money = (value: number) =>
  `৳${Number(value || 0).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`

const totalUnits = computed(() =>
  cart.value.items.reduce((total, item) => total + Number(item.quantity || 0), 0),
)

const phoneDigits = computed(() =>
  form.customer_phone.replace(/[০-৯]/g, digit =>
    String('০১২৩৪৫৬৭৮৯'.indexOf(digit)),
  ),
)

const normalizedPhone = computed(() => phoneDigits.value.replace(/[\s-]+/g, ''))

const phoneLooksValid = computed(() =>
  /^(?:\+?88)?01[3-9]\d{8}$/.test(normalizedPhone.value),
)

onMounted(() => {
  refresh().catch(() => undefined)
})

useSeoMeta({
  title: 'Secure Checkout | MADINA Scale',
  description: 'Enter your delivery details and place your MADINA Scale order.',
})

function errorFor(field: string) {
  return fieldErrors.value[field]?.[0] || ''
}

function clearFieldError(field: string) {
  if (fieldErrors.value[field]) {
    const next = { ...fieldErrors.value }
    delete next[field]
    fieldErrors.value = next
  }
  message.value = ''
}

async function placeOrder() {
  if (submitting.value || !cart.value.items.length) return

  message.value = ''
  fieldErrors.value = {}
  if (paymentMethod.value === 'bank_transfer') {
    if (!bankForm.sender_account.trim() || !bankForm.transaction_reference.trim() || Number(bankForm.amount) <= 0) {
      message.value = 'Please enter the sender account, transaction reference and transferred amount.'
      return
    }
  }
  submitting.value = true

  try {
    const config = useRuntimeConfig()
    const token = await $fetch<{ token: string }>(
      `${config.public.apiBase}/csrf-token`,
      { credentials: 'include' },
    )

    if (!orderAttemptKey.value) {
      orderAttemptKey.value = crypto.randomUUID()
    }

    const result = await $fetch<{ data: { order_number: string } }>(
      `${config.public.apiBase}/checkout`,
      {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-CSRF-TOKEN': token.token },
        body: {
          ...form,
          customer_phone: normalizedPhone.value,
          payment_method: paymentMethod.value,
          ...(paymentMethod.value === 'bank_transfer' ? {
            bank_sender_account: bankForm.sender_account.trim(),
            bank_transaction_reference: bankForm.transaction_reference.trim(),
            bank_transfer_amount: bankForm.amount,
          } : {}),
          idempotency_key: orderAttemptKey.value,
        },
      },
    )

    await navigateTo(
      `/order-success?order=${encodeURIComponent(result.data.order_number)}&payment=${encodeURIComponent(result.data.payment_status || 'unpaid')}`,
    )
  } catch (cause: any) {
    const errors = cause?.data?.errors
    if (errors && typeof errors === 'object') {
      fieldErrors.value = Object.fromEntries(
        Object.entries(errors).map(([key, value]) => [
          key,
          Array.isArray(value) ? value.map(String) : [String(value)],
        ]),
      )
    }

    message.value =
      cause?.data?.message ||
      Object.values(errors || {}).flat().map(String).join(' ') ||
      'We could not place your order. Please review your details and try again.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#f6f8f6] text-slate-900">
    <header class="border-b border-white/10 bg-[#073f31] text-white">
      <div class="container flex min-h-[76px] items-center justify-between gap-4">
        <NuxtLink to="/" class="text-xl font-black tracking-tight">
          MADINA<span class="text-emerald-300"> SCALE</span>
        </NuxtLink>
        <div class="hidden items-center gap-2 text-xs font-semibold text-emerald-50/80 sm:flex">
          <span class="grid size-7 place-items-center rounded-full bg-white/10">1</span>
          <span>Cart</span>
          <span class="mx-1 h-px w-7 bg-white/25" />
          <span class="grid size-7 place-items-center rounded-full bg-white text-emerald-900">2</span>
          <span class="text-white">Checkout</span>
          <span class="mx-1 h-px w-7 bg-white/25" />
          <span class="grid size-7 place-items-center rounded-full bg-white/10">3</span>
          <span>Confirmation</span>
        </div>
        <NuxtLink
          to="/cart"
          class="inline-flex min-h-10 items-center rounded-xl border border-white/20 px-3 py-2 text-xs font-bold text-white transition hover:bg-white/10"
        >
          ← Back to cart
        </NuxtLink>
      </div>
    </header>

    <main class="container max-w-7xl py-8 sm:py-10 lg:py-12">
      <div class="mb-8 max-w-2xl">
        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-emerald-800">
          Secure checkout
        </p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
          Complete your order
        </h1>
        <p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base">
          Add your contact and delivery details. Please review your items before placing the order.
        </p>
      </div>

      <div
        v-if="loading"
        class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_390px]"
        aria-live="polite"
        aria-busy="true"
      >
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="h-5 w-48 animate-pulse rounded bg-slate-100" />
          <div v-for="index in 5" :key="index" class="mt-5 h-12 animate-pulse rounded-xl bg-slate-100" />
        </section>
        <aside class="h-80 animate-pulse rounded-2xl border border-slate-200 bg-white" />
      </div>

      <section
        v-else-if="error && !cart.items.length"
        class="rounded-2xl border border-red-200 bg-white p-6 shadow-sm"
        role="alert"
      >
        <h2 class="font-extrabold text-red-900">We couldn't load your cart</h2>
        <p class="mt-2 text-sm text-red-800">{{ error }}</p>
        <button
          type="button"
          class="mt-4 rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-900"
          @click="refresh().catch(() => undefined)"
        >
          Try again
        </button>
      </section>

      <section
        v-else-if="!cart.items.length"
        class="rounded-3xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm sm:py-20"
      >
        <div class="mx-auto grid size-20 place-items-center rounded-3xl bg-emerald-50 text-3xl">🛒</div>
        <h2 class="mt-6 text-2xl font-black tracking-tight text-slate-950">Your cart is empty</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">
          Add a product to your cart before continuing to checkout.
        </p>
        <NuxtLink
          to="/products"
          class="mt-6 inline-flex min-h-12 items-center justify-center rounded-xl bg-emerald-800 px-6 py-3 text-sm font-extrabold text-white transition hover:bg-emerald-900"
        >
          Explore products
        </NuxtLink>
      </section>

      <div v-else class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_390px] lg:gap-8">
        <form
          class="min-w-0 space-y-5"
          novalidate
          @submit.prevent="placeOrder"
        >
          <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-5 sm:px-7">
              <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-lg">01</span>
              <div>
                <h2 class="text-lg font-extrabold text-slate-950">Contact information</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                  We’ll use these details to confirm and coordinate your order.
                </p>
              </div>
            </div>

            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
              <label class="grid gap-2 text-sm font-bold text-slate-700">
                Full name <span class="text-red-600">*</span>
                <input
                  v-model.trim="form.customer_name"
                  type="text"
                  name="customer_name"
                  autocomplete="name"
                  required
                  maxlength="160"
                  placeholder="Enter your full name"
                  class="min-h-12 rounded-xl border border-slate-200 bg-white px-4 py-3 font-normal text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"
                  :aria-invalid="!!errorFor('customer_name')"
                  @input="clearFieldError('customer_name')"
                >
                <span v-if="errorFor('customer_name')" class="text-xs font-medium text-red-600">{{ errorFor('customer_name') }}</span>
              </label>

              <label class="grid gap-2 text-sm font-bold text-slate-700">
                Mobile number <span class="text-red-600">*</span>
                <input
                  v-model.trim="form.customer_phone"
                  type="tel"
                  name="customer_phone"
                  autocomplete="tel"
                  inputmode="tel"
                  required
                  maxlength="40"
                  placeholder="01XXXXXXXXX"
                  class="min-h-12 rounded-xl border border-slate-200 bg-white px-4 py-3 font-normal text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"
                  :aria-invalid="!!errorFor('customer_phone') || (!!form.customer_phone && !phoneLooksValid)"
                  @input="clearFieldError('customer_phone')"
                >
                <span v-if="errorFor('customer_phone')" class="text-xs font-medium text-red-600">{{ errorFor('customer_phone') }}</span>
                <span v-else-if="form.customer_phone && !phoneLooksValid" class="text-xs font-medium text-amber-700">
                  Enter a valid Bangladesh mobile number, e.g. 01XXXXXXXXX.
                </span>
                <span v-else class="text-xs font-normal text-slate-500">Bangladeshi mobile number required</span>
              </label>
            </div>
          </section>

          <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-5 sm:px-7">
              <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-lg">02</span>
              <div>
                <h2 class="text-lg font-extrabold text-slate-950">Delivery address</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                  Provide a clear address so your order can reach you without delay.
                </p>
              </div>
            </div>

            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
              <label class="grid gap-2 text-sm font-bold text-slate-700">
                Division
                <input
                  v-model.trim="form.division"
                  type="text"
                  name="division"
                  autocomplete="address-level1"
                  maxlength="120"
                  placeholder="e.g. Dhaka"
                  class="min-h-12 rounded-xl border border-slate-200 px-4 py-3 font-normal outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"
                  @input="clearFieldError('division')"
                >
                <span v-if="errorFor('division')" class="text-xs font-medium text-red-600">{{ errorFor('division') }}</span>
              </label>

              <label class="grid gap-2 text-sm font-bold text-slate-700">
                District
                <input
                  v-model.trim="form.district"
                  type="text"
                  name="district"
                  autocomplete="address-level2"
                  maxlength="120"
                  placeholder="Enter your district"
                  class="min-h-12 rounded-xl border border-slate-200 px-4 py-3 font-normal outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"
                  @input="clearFieldError('district')"
                >
                <span v-if="errorFor('district')" class="text-xs font-medium text-red-600">{{ errorFor('district') }}</span>
              </label>

              <label class="grid gap-2 text-sm font-bold text-slate-700 sm:col-span-2">
                Area / Thana
                <input
                  v-model.trim="form.area"
                  type="text"
                  name="area"
                  autocomplete="address-level3"
                  maxlength="120"
                  placeholder="Enter your area, thana or upazila"
                  class="min-h-12 rounded-xl border border-slate-200 px-4 py-3 font-normal outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"
                  @input="clearFieldError('area')"
                >
                <span v-if="errorFor('area')" class="text-xs font-medium text-red-600">{{ errorFor('area') }}</span>
              </label>

              <label class="grid gap-2 text-sm font-bold text-slate-700 sm:col-span-2">
                House, road and detailed address <span class="text-red-600">*</span>
                <textarea
                  v-model.trim="form.address_line"
                  name="address_line"
                  autocomplete="street-address"
                  required
                  maxlength="500"
                  rows="3"
                  placeholder="House / building, road, landmark and any details that help the delivery team find you"
                  class="resize-y rounded-xl border border-slate-200 px-4 py-3 font-normal leading-6 outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"
                  :aria-invalid="!!errorFor('address_line')"
                  @input="clearFieldError('address_line')"
                />
                <span v-if="errorFor('address_line')" class="text-xs font-medium text-red-600">{{ errorFor('address_line') }}</span>
              </label>

              <label class="grid gap-2 text-sm font-bold text-slate-700 sm:col-span-2">
                Order note <span class="font-normal text-slate-400">(optional)</span>
                <textarea
                  v-model.trim="form.notes"
                  name="notes"
                  maxlength="1000"
                  rows="2"
                  placeholder="Any delivery instructions or additional information?"
                  class="resize-y rounded-xl border border-slate-200 px-4 py-3 font-normal leading-6 outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"
                  @input="clearFieldError('notes')"
                />
                <span v-if="errorFor('notes')" class="text-xs font-medium text-red-600">{{ errorFor('notes') }}</span>
              </label>
            </div>
          </section>

          <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-5 sm:px-7">
              <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-lg">03</span>
              <div>
                <h2 class="text-lg font-extrabold text-slate-950">Payment method</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                  Available payment option for this storefront checkout.
                </p>
              </div>
            </div>
            <div class="space-y-4 p-5 sm:p-7">
              <label class="flex cursor-pointer items-start gap-4 rounded-xl border-2 p-4 transition"
                :class="paymentMethod === 'cash_on_delivery' ? 'border-emerald-700 bg-emerald-50/60' : 'border-slate-200 hover:border-slate-300'">
                <input v-model="paymentMethod" type="radio" value="cash_on_delivery" class="mt-1 size-4 accent-emerald-700">
                <span class="min-w-0 flex-1">
                  <span class="block font-extrabold text-slate-950">Cash on Delivery</span>
                  <span class="mt-1 block text-sm leading-6 text-slate-600">Place your order now and pay when your order is delivered.</span>
                </span>
                <span v-if="paymentMethod === 'cash_on_delivery'" class="rounded-lg bg-white px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-emerald-800">Selected</span>
              </label>

              <label class="flex cursor-pointer items-start gap-4 rounded-xl border-2 p-4 transition"
                :class="paymentMethod === 'bank_transfer' ? 'border-emerald-700 bg-emerald-50/60' : 'border-slate-200 hover:border-slate-300'">
                <input v-model="paymentMethod" type="radio" value="bank_transfer" class="mt-1 size-4 accent-emerald-700">
                <span class="min-w-0 flex-1">
                  <span class="block font-extrabold text-slate-950">Bank Transfer</span>
                  <span class="mt-1 block text-sm leading-6 text-slate-600">Transfer the amount manually and submit your transaction details. Our team will verify receipt before confirming your order.</span>
                </span>
                <span v-if="paymentMethod === 'bank_transfer'" class="rounded-lg bg-white px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-emerald-800">Selected</span>
              </label>

              <div class="grid gap-3 sm:grid-cols-2">
                <div class="flex items-center justify-between rounded-xl border border-slate-200 p-3">
                  <div><p class="font-extrabold text-slate-900">bKash</p><p class="text-xs text-slate-500">API integration planned</p></div>
                  <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-800">Coming soon</span>
                </div>
                <div class="flex items-center justify-between rounded-xl border border-slate-200 p-3">
                  <div><p class="font-extrabold text-slate-900">Nagad</p><p class="text-xs text-slate-500">API integration planned</p></div>
                  <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-800">Coming soon</span>
                </div>
              </div>

              <div v-if="paymentMethod === 'bank_transfer'" class="space-y-4 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 sm:p-5">
                <div>
                  <p class="font-extrabold text-slate-950">Bank account details</p>
                  <p class="mt-1 text-xs leading-5 text-slate-600">Demo credentials for now. These will later be managed from the backend dashboard. Do not transfer real money to this demo account.</p>
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                  <div><dt class="text-xs text-slate-500">Bank</dt><dd class="font-bold text-slate-900">{{ demoBank.name }}</dd></div>
                  <div><dt class="text-xs text-slate-500">Account name</dt><dd class="font-bold text-slate-900">{{ demoBank.account_name }}</dd></div>
                  <div><dt class="text-xs text-slate-500">Account number</dt><dd class="font-bold tracking-wide text-slate-900">{{ demoBank.account_number }}</dd></div>
                  <div><dt class="text-xs text-slate-500">Branch</dt><dd class="font-bold text-slate-900">{{ demoBank.branch }}</dd></div>
                  <div><dt class="text-xs text-slate-500">Routing number</dt><dd class="font-bold text-slate-900">{{ demoBank.routing_number }}</dd></div>
                </dl>
                <div class="grid gap-4 sm:grid-cols-2">
                  <label class="grid gap-2 text-sm font-bold text-slate-700">Sender account number
                    <input v-model.trim="bankForm.sender_account" required maxlength="80" placeholder="Account used to send payment" class="min-h-12 rounded-xl border border-slate-200 bg-white px-4 py-3 font-normal outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10">
                  </label>
                  <label class="grid gap-2 text-sm font-bold text-slate-700">Transaction reference / ID
                    <input v-model.trim="bankForm.transaction_reference" required maxlength="120" placeholder="Bank transaction reference" class="min-h-12 rounded-xl border border-slate-200 bg-white px-4 py-3 font-normal outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10">
                  </label>
                  <label class="grid gap-2 text-sm font-bold text-slate-700 sm:col-span-2">Transferred amount (BDT)
                    <input v-model="bankForm.amount" required type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="Enter amount transferred" class="min-h-12 rounded-xl border border-slate-200 bg-white px-4 py-3 font-normal outline-none focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10">
                    <span class="text-xs font-normal text-slate-500">Enter the exact amount you transferred. It must match the final order amount for approval.</span>
                  </label>
                </div>
                <p class="rounded-lg bg-white p-3 text-xs leading-5 text-amber-800">Your order will remain pending until staff verifies the transfer, approves it and records the funds as received in the bank account.</p>
              </div>
            </div>
          </section>

          <div
            v-if="message"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-6 text-red-800"
            role="alert"
            aria-live="assertive"
          >
            {{ message }}
          </div>

          <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <p class="text-xs leading-5 text-slate-500">
              By placing this order, you confirm that your contact and delivery information is accurate. Our team will use it to process and coordinate this order.
            </p>
            <button
              type="submit"
              :disabled="submitting || !cart.items.length || !form.customer_name.trim() || !phoneLooksValid || !form.address_line.trim()"
              class="mt-4 flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-emerald-800 px-5 py-4 text-sm font-extrabold text-white shadow-sm transition hover:bg-emerald-900 focus:outline-none focus:ring-4 focus:ring-emerald-800/20 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <span v-if="submitting" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />
              {{ submitting ? 'Submitting your order…' : (paymentMethod === 'bank_transfer' ? 'Submit bank transfer details · ' : 'Place order · ') + money(cart.subtotal) }}
            </button>
            <p class="mt-3 text-center text-[11px] leading-5 text-slate-500">
              Please click once. Bank transfer orders remain pending until our team verifies and receives the payment.
            </p>
          </div>
        </form>

        <aside class="space-y-4 lg:sticky lg:top-6">
          <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
              <p class="text-xs font-extrabold uppercase tracking-[0.15em] text-emerald-800">
                Your purchase
              </p>
              <div class="mt-1 flex items-center justify-between gap-3">
                <h2 class="text-xl font-black tracking-tight text-slate-950">Order summary</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">
                  {{ totalUnits }} {{ totalUnits === 1 ? 'item' : 'items' }}
                </span>
              </div>
            </div>

            <div class="max-h-[360px] space-y-4 overflow-y-auto px-5 py-5 sm:px-6">
              <article
                v-for="item in cart.items"
                :key="item.variant_id"
                class="flex gap-3"
              >
                <NuxtLink
                  :to="`/products/${item.product_slug}`"
                  class="size-[68px] shrink-0 overflow-hidden rounded-xl border border-slate-100 bg-slate-50"
                >
                  <StorefrontImage
                    :src="item.image_url"
                    :alt="item.product_name"
                    kind="product"
                    label="Checkout Product Image"
                    dimensions="160 × 160 px"
                    ratio="1 / 1"
                    :width="160"
                    :height="160"
                  />
                </NuxtLink>
                <div class="min-w-0 flex-1">
                  <NuxtLink
                    :to="`/products/${item.product_slug}`"
                    class="line-clamp-2 text-sm font-bold leading-5 text-slate-900 hover:text-emerald-800"
                  >
                    {{ item.product_name }}
                  </NuxtLink>
                  <p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ item.variant_name }}</p>
                  <div class="mt-2 flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-slate-500">Qty: {{ item.quantity }}</span>
                    <strong class="text-sm tabular-nums text-slate-900">{{ money(item.line_total) }}</strong>
                  </div>
                </div>
              </article>
            </div>

            <div class="space-y-3 border-t border-slate-100 px-5 py-5 sm:px-6">
              <div class="flex justify-between gap-3 text-sm">
                <span class="text-slate-600">Items subtotal</span>
                <strong class="tabular-nums text-slate-900">{{ money(cart.subtotal) }}</strong>
              </div>
              <div class="flex justify-between gap-3 text-sm">
                <span class="text-slate-600">Delivery charge</span>
                <span class="max-w-[150px] text-right text-xs leading-5 text-slate-500">Confirmed during order processing</span>
              </div>
              <div class="border-t border-dashed border-slate-200 pt-4">
                <div class="flex items-end justify-between gap-3">
                  <span class="font-extrabold text-slate-950">Subtotal</span>
                  <strong class="text-2xl font-black tracking-tight tabular-nums text-slate-950">
                    {{ money(cart.subtotal) }}
                  </strong>
                </div>
                <p class="mt-2 text-[11px] leading-5 text-slate-500">
                  Delivery charge and final payable amount will be confirmed by our team. No unconfirmed fee is added here.
                </p>
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 sm:p-5">
            <div class="flex gap-3">
              <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-emerald-800">✓</span>
              <div>
                <h3 class="text-sm font-extrabold text-slate-950">Order support</h3>
                <p class="mt-1 text-xs leading-5 text-slate-600">
                  Need help before placing your order? Our team is ready to assist.
                </p>
                <NuxtLink
                  to="/pages/contact"
                  class="mt-2 inline-flex text-xs font-extrabold text-emerald-800 hover:underline"
                >
                  Contact MADINA →
                </NuxtLink>
              </div>
            </div>
          </section>

          <NuxtLink
            to="/cart"
            class="flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-emerald-700 hover:text-emerald-800"
          >
            Review or edit cart
          </NuxtLink>
        </aside>
      </div>
    </main>

    <footer class="border-t border-slate-200 bg-white py-5">
      <div class="container flex flex-col items-center justify-between gap-2 text-center text-xs text-slate-500 sm:flex-row sm:text-left">
        <p>© {{ new Date().getFullYear() }} MADINA Scale. All rights reserved.</p>
        <p>Checkout securely through MADINA Storefront.</p>
      </div>
    </footer>
  </div>
</template>
