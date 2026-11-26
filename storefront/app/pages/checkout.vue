<script setup lang="ts">
type CheckoutForm = {
  customer_name: string
  customer_phone: string
  division: string
  district: string
  area: string
  postal_code: string
  address_line: string
  notes: string
}

const { cart: cartState, loading, error, refresh } = useStorefrontCart()
// The cart composable may be undefined briefly during SSR/initial hydration.
// Normalize it once so the checkout and delivery calculator always receive a safe shape.
const cart = computed(() => {
  const value = cartState.value as any
  return {
    ...(value && typeof value === 'object' ? value : {}),
    items: Array.isArray(value?.items) ? value.items : [],
    subtotal: Number(value?.subtotal || 0),
    item_count: Number(value?.item_count || 0),
  }
})

// Initialize the checkout form before creating delivery composables.
// Their computed/watch sources can evaluate the location getter immediately.
const form = reactive<CheckoutForm>({
  customer_name: '',
  customer_phone: '',
  division: '',
  district: '',
  area: '',
  postal_code: '',
  address_line: '',
  notes: '',
})

const paymentMethod = ref<'cash_on_delivery' | 'bank_transfer'>('cash_on_delivery')

const {
  courierOptions,
  selectedCourierCode,
  selectionMode,
  estimatedWeightGrams,
  deliveryZone,
  selectedCourier,
  deliveryCharge,
  hasAvailableCourier,
  quoteDisclaimer,
  quoteLoading,
  quoteError,
  setSelectionMode,
  setCourier,
} = useDeliveryOptions(cart, () => ({
  division: form.division,
  district: form.district,
  area: form.area,
}), () => paymentMethod.value)
const payableTotal = computed(() => Number(cart.value.subtotal || 0) + deliveryCharge.value)
const submitting = ref(false)
const message = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const orderAttemptKey = ref('')
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

// Bangladesh administrative reference data (MIT licensed).
// Division files are loaded on demand from the maintained GitHub dataset.
// Manual entry remains available if the source is unreachable or a location is missing.
const BD_DIVISIONS = [
  { name: 'Barishal', bnName: 'বরিশাল', slug: 'barishal' },
  { name: 'Chattogram', bnName: 'চট্টগ্রাম', slug: 'chattogram' },
  { name: 'Dhaka', bnName: 'ঢাকা', slug: 'dhaka' },
  { name: 'Khulna', bnName: 'খুলনা', slug: 'khulna' },
  { name: 'Mymensingh', bnName: 'ময়মনসিংহ', slug: 'mymensingh' },
  { name: 'Rajshahi', bnName: 'রাজশাহী', slug: 'rajshahi' },
  { name: 'Rangpur', bnName: 'রংপুর', slug: 'rangpur' },
  { name: 'Sylhet', bnName: 'সিলেট', slug: 'sylhet' },
]
const locationLoading = ref(false)
const divisionOpen = ref(false)
const districtOpen = ref(false)
const areaOpen = ref(false)
const locationDataError = ref('')
const selectedDivisionData = ref<any | null>(null)
const districtRecords = computed<any[]>(() =>
  selectedDivisionData.value?.districts || [],
)
const selectedDistrictRecord = computed<any | null>(() =>
  districtRecords.value.find((item: any) =>
    [item.name, item.bnName, ...(item.aliases || [])]
      .some((name: string) => normalizeLocationName(name) === normalizeLocationName(form.district)),
  ) || null,
)
const areaRecords = computed<any[]>(() => {
  const district = selectedDistrictRecord.value
  if (!district) return []
  return (district.upazilas || []).flatMap((upazila: any) => [
    { ...upazila, locationKind: upazila.type === 'thana' ? 'Thana' : 'Upazila' },
    ...(upazila.areas || []).map((area: any) => ({
      ...area,
      locationKind: 'Area / Union',
      parentName: upazila.name,
      parentBnName: upazila.bnName,
    })),
  ])
})
const filteredDivisions = computed(() => BD_DIVISIONS.filter(item =>
  !form.division || [item.name, item.bnName].some(name => normalizeLocationName(name).includes(normalizeLocationName(form.division))),
))
const filteredDistricts = computed(() => districtRecords.value.filter((item: any) =>
  !form.district || [item.name, item.bnName, ...(item.aliases || [])].some((name: string) => normalizeLocationName(name).includes(normalizeLocationName(form.district))),
).slice(0, 30))
const filteredAreas = computed(() => areaRecords.value.filter((item: any) =>
  !form.area || [item.name, item.bnName, ...(item.aliases || []), item.parentName, item.parentBnName].filter(Boolean).some((name: string) => normalizeLocationName(name).includes(normalizeLocationName(form.area))),
).slice(0, 40))
const selectedAreaRecord = computed<any | null>(() =>
  areaRecords.value.find((item: any) =>
    [item.name, item.bnName, ...(item.aliases || [])]
      .some((name: string) => normalizeLocationName(name) === normalizeLocationName(form.area)),
  ) || null,
)

function normalizeLocationName(value: unknown) {
  return String(value || '').trim().toLocaleLowerCase().replace(/[।,.-]/g, '').replace(/\s+/g, ' ')
}

async function loadDivisionLocations(value: string) {
  const division = BD_DIVISIONS.find(item =>
    [item.name, item.bnName].some(name => normalizeLocationName(name) === normalizeLocationName(value)),
  )
  selectedDivisionData.value = null
  locationDataError.value = ''
  if (!division) return

  locationLoading.value = true
  try {
    const data = await $fetch<any>(
      `https://cdn.jsdelivr.net/gh/HedaetShahriar/bangladesh-locations-dataset@main/data/divisions/${division.slug}.json`,
      { timeout: 12000 },
    )
    selectedDivisionData.value = data?.divisions?.[0] || null
    if (!selectedDivisionData.value) locationDataError.value = 'Location suggestions are temporarily unavailable. You can still type your address.'
  } catch {
    locationDataError.value = 'Could not load location suggestions. You can still type your address manually.'
  } finally {
    locationLoading.value = false
  }
}

watch(() => form.division, async (value) => {
  const isKnownDivision = BD_DIVISIONS.some(item =>
    [item.name, item.bnName].some(name => normalizeLocationName(name) === normalizeLocationName(value)),
  )
  if (isKnownDivision) await loadDivisionLocations(value)
  else selectedDivisionData.value = null
})

watch(selectedAreaRecord, (record) => {
  if (record?.postalCode && !form.postal_code) {
    form.postal_code = String(record.postalCode)
  }
})

const totalUnits = computed(() =>
  cart.value.items.reduce((total, item) => total + Number(item.quantity || 0), 0),
)

const normalizedPhone = computed(() => form.customer_phone)

const phoneLooksValid = computed(() => /^01[3-9]\d{8}$/.test(normalizedPhone.value))

function handlePhoneInput(event: Event) {
  const input = event.target as HTMLInputElement
  // Accept ASCII digits only and cap the value at the Bangladesh national 11-digit format.
  const digits = input.value.replace(/[^0-9]/g, '').slice(0, 11)
  form.customer_phone = digits
  input.value = digits
  clearFieldError('customer_phone')
}

function chooseDivision(item: typeof BD_DIVISIONS[number]) {
  form.division = item.name
  form.district = ''
  form.area = ''
  form.postal_code = ''
  divisionOpen.value = false
}

function chooseDistrict(item: any) {
  form.district = item.name
  form.area = ''
  form.postal_code = ''
  districtOpen.value = false
}

function chooseArea(item: any) {
  form.area = item.name
  if (item.postalCode) form.postal_code = String(item.postalCode)
  areaOpen.value = false
}

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
  if (submitting.value || cart.value.items.length === 0) return

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

    const result = await $fetch<{ data: { order_number: string; payment_status?: string | null } }>(
      `${config.public.apiBase}/checkout`,
      {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-CSRF-TOKEN': token.token },
        body: {
          ...(({ postal_code, ...checkoutFields }) => ({
            ...checkoutFields,
            address_line: [
              checkoutFields.address_line,
              postal_code ? `Post code: ${postal_code}` : '',
            ].filter(Boolean).join('\n').slice(0, 500),
          }))(form),
          customer_phone: normalizedPhone.value,
          payment_method: paymentMethod.value,
          // Courier choice is submitted as a preference; Laravel recalculates the charge from Admin rate rules.
          customer_preferred_courier_code: selectionMode.value === 'customer_choice' ? selectedCourierCode.value : null,
          delivery_selection_mode: selectionMode.value,
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
  <div class="min-h-screen bg-[#f5f8f6] text-slate-900">
    <header class="border-b border-white/10 bg-[#073f31] text-white">
      <div class="container flex min-h-[76px] items-center justify-between gap-4">
        <NuxtLink to="/" class="text-xl font-black tracking-tight">MADINA<span class="text-emerald-300"> SCALE</span></NuxtLink>
        <div class="hidden items-center gap-2 text-[11px] font-bold sm:flex">
          <NuxtLink to="/cart" class="flex items-center gap-2 text-emerald-100/80 transition hover:text-white"><span class="grid size-7 place-items-center rounded-full bg-white/15">1</span> Cart</NuxtLink>
          <span class="mx-1 h-px w-7 bg-white/25" />
          <span class="flex items-center gap-2 text-white"><span class="grid size-7 place-items-center rounded-full bg-white text-emerald-950">2</span> Checkout</span>
          <span class="mx-1 h-px w-7 bg-white/25" />
          <span class="flex items-center gap-2 text-emerald-100/60"><span class="grid size-7 place-items-center rounded-full bg-white/10">3</span> Confirmation</span>
        </div>
        <NuxtLink to="/cart" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-white/20 px-3 py-2 text-xs font-bold text-white transition hover:bg-white/10"><span aria-hidden="true">←</span><span class="hidden sm:inline">Back to cart</span><span class="sm:hidden">Cart</span></NuxtLink>
      </div>
    </header>

    <main class="container max-w-7xl py-8 sm:py-10 lg:py-12">
      <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div class="max-w-2xl"><p class="flex items-center gap-2 text-[11px] font-extrabold uppercase tracking-[0.2em] text-emerald-800"><span class="size-1.5 rounded-full bg-emerald-600" /> Secure checkout</p><h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Complete your order</h1><p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base">A few details and you’re all set. Add your contact and delivery information, then choose how you’d like to pay.</p></div>
        <div class="flex items-center gap-2 rounded-2xl border border-emerald-100 bg-white px-4 py-3 shadow-sm"><span class="grid size-9 place-items-center rounded-xl bg-emerald-50 text-emerald-800"><UiIcon name="shield-check" :size="19" /></span><div><p class="text-xs font-extrabold text-slate-900">Secure order process</p><p class="mt-0.5 text-[10px] text-slate-500">Your details are submitted securely</p></div></div>
      </div>

      <div v-if="loading" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_390px]" aria-live="polite" aria-busy="true">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div class="h-5 w-48 animate-pulse rounded bg-slate-100" /><div v-for="index in 6" :key="index" class="mt-5 h-12 animate-pulse rounded-xl bg-slate-100" /></section>
        <aside class="h-80 animate-pulse rounded-3xl border border-slate-200 bg-white" />
      </div>

      <section v-else-if="error && !cart.items.length" class="rounded-3xl border border-red-200 bg-white p-6 shadow-sm" role="alert"><div class="flex items-start gap-3"><span class="grid size-10 place-items-center rounded-xl bg-red-50 text-red-700"><UiIcon name="info" /></span><div><h2 class="font-extrabold text-red-950">We couldn't load your cart</h2><p class="mt-2 text-sm text-red-800">{{ error }}</p><button type="button" class="mt-4 rounded-xl bg-[#073f31] px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-950" @click="refresh().catch(() => undefined)">Try again</button></div></div></section>

      <section v-else-if="!cart.items.length" class="rounded-3xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm"><div class="mx-auto grid size-16 place-items-center rounded-2xl bg-emerald-50 text-emerald-800"><UiIcon name="shopping-cart" :size="30" /></div><h2 class="mt-5 text-2xl font-black text-slate-950">Your cart is empty</h2><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">Add a product to your cart before continuing to checkout.</p><NuxtLink to="/products" class="mt-6 inline-flex min-h-12 items-center gap-2 rounded-xl bg-[#073f31] px-6 py-3 text-sm font-extrabold text-white hover:bg-emerald-950">Explore products <UiIcon name="arrow-right" :size="17" /></NuxtLink></section>

      <div v-else class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_390px] lg:gap-8">
        <form class="min-w-0 space-y-5" novalidate @submit.prevent="placeOrder">
          <fieldset class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <legend class="sr-only">Contact information</legend>
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-5 sm:px-7"><span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-sm font-black text-emerald-900" aria-hidden="true">01</span><div><h2 class="text-lg font-extrabold text-slate-950">Contact information</h2><p class="mt-1 text-xs leading-5 text-slate-500">How can we reach you about your order?</p></div></div>
            <div class="grid gap-x-5 gap-y-3 p-5 sm:grid-cols-2 sm:p-7">
              <div class="grid content-start gap-2"><label for="customer-name" class="text-sm font-bold text-slate-700">Full name <span class="text-red-600" aria-hidden="true">*</span></label><input id="customer-name" v-model.trim="form.customer_name" type="text" name="customer_name" autocomplete="name" required maxlength="160" placeholder="Enter your full name" class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 font-normal text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:bg-white focus:ring-4 focus:ring-emerald-700/10" :aria-invalid="!!errorFor('customer_name')" :aria-describedby="errorFor('customer_name') ? 'customer-name-error' : undefined" @input="clearFieldError('customer_name')"><p v-if="errorFor('customer_name')" id="customer-name-error" class="text-xs font-medium text-red-600" role="alert">{{ errorFor('customer_name') }}</p></div>
              <div class="grid content-start gap-2"><label for="customer-phone" class="text-sm font-bold text-slate-700">Mobile number <span class="text-red-600" aria-hidden="true">*</span></label><div class="relative"><span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-sm font-semibold text-slate-500">🇧🇩 +88</span><input id="customer-phone" :value="form.customer_phone" type="tel" name="customer_phone" autocomplete="tel-national" inputmode="numeric" pattern="01[3-9][0-9]{8}" required maxlength="11" minlength="11" placeholder="01XXXXXXXXX" class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50/60 py-3 pl-[5.25rem] pr-4 font-normal tracking-wide text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:bg-white focus:ring-4 focus:ring-emerald-700/10" :aria-invalid="!!errorFor('customer_phone') || (!!form.customer_phone && !phoneLooksValid)" :aria-describedby="errorFor('customer_phone') ? 'customer-phone-error' : 'customer-phone-hint'" @input="handlePhoneInput"></div><p v-if="errorFor('customer_phone')" id="customer-phone-error" class="text-xs font-medium text-red-600" role="alert">{{ errorFor('customer_phone') }}</p><p v-else id="customer-phone-hint" class="text-xs font-normal text-slate-500">Enter 11 digits starting with 01. Numbers only ({{ form.customer_phone.length }}/11).</p></div>
            </div>
          </fieldset>

          <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-5 sm:px-7"><span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-sm font-black text-emerald-900">02</span><div><h2 class="text-lg font-extrabold text-slate-950">Delivery address</h2><p class="mt-1 text-xs leading-5 text-slate-500">Where should we deliver your order?</p></div></div>
            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
              <div class="relative grid content-start gap-2">
                <label for="checkout-division" class="text-sm font-bold text-slate-700">Division</label>
                <div class="relative"><input id="checkout-division" v-model.trim="form.division" type="text" name="division" autocomplete="address-level1" maxlength="120" placeholder="Select or search division" role="combobox" aria-autocomplete="list" aria-controls="division-options" :aria-expanded="divisionOpen" class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50/60 px-4 pr-11 py-3 font-normal outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:bg-white focus:ring-4 focus:ring-emerald-700/10" @focus="divisionOpen = true" @input="divisionOpen = true; clearFieldError('division')" @keydown.esc="divisionOpen = false"><span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-500">⌄</span></div>
                <ul v-if="divisionOpen" id="division-options" role="listbox" class="absolute left-0 right-0 top-full z-30 mt-1 max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                  <li v-for="item in filteredDivisions" :key="item.slug" role="option" :aria-selected="form.division === item.name"><button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm hover:bg-emerald-50" @mousedown.prevent="chooseDivision(item)"><span class="font-semibold text-slate-800">{{ item.name }}</span><span class="text-xs text-slate-500">{{ item.bnName }}</span></button></li>
                  <li v-if="!filteredDivisions.length" class="px-3 py-3 text-xs text-slate-600">No match. Your typed division will be used: <strong>{{ form.division }}</strong></li>
                </ul>
                <span v-if="errorFor('division')" class="text-xs font-medium text-red-600">{{ errorFor('division') }}</span>
              </div>
              <div class="relative grid content-start gap-2">
                <label for="checkout-district" class="text-sm font-bold text-slate-700">District</label>
                <div class="relative"><input id="checkout-district" v-model.trim="form.district" type="text" name="district" autocomplete="address-level2" maxlength="120" placeholder="Select or search district" role="combobox" aria-autocomplete="list" aria-controls="district-options" :aria-expanded="districtOpen" class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50/60 px-4 pr-11 py-3 font-normal outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:bg-white focus:ring-4 focus:ring-emerald-700/10" @focus="districtOpen = true" @input="districtOpen = true; clearFieldError('district')" @keydown.esc="districtOpen = false"><span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-500">⌄</span></div>
                <ul v-if="districtOpen" id="district-options" role="listbox" class="absolute left-0 right-0 top-full z-30 mt-1 max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                  <li v-for="item in filteredDistricts" :key="item.id || item.name" role="option" :aria-selected="form.district === item.name"><button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm hover:bg-emerald-50" @mousedown.prevent="chooseDistrict(item)"><span class="font-semibold text-slate-800">{{ item.name }}</span><span class="text-xs text-slate-500">{{ item.bnName }}</span></button></li>
                  <li v-if="!filteredDistricts.length" class="px-3 py-3 text-xs text-slate-600">No match. Your typed district will be used: <strong>{{ form.district }}</strong></li>
                </ul>
                <span v-if="locationLoading" class="text-xs text-emerald-700">Loading district data…</span><span v-if="errorFor('district')" class="text-xs font-medium text-red-600">{{ errorFor('district') }}</span>
              </div>
              <div class="relative grid content-start gap-2">
                <label for="checkout-area" class="text-sm font-bold text-slate-700">Thana / Upazila / Area</label>
                <div class="relative"><input id="checkout-area" v-model.trim="form.area" type="text" name="area" autocomplete="address-level3" maxlength="120" placeholder="Select or search thana / upazila / area" role="combobox" aria-autocomplete="list" aria-controls="area-options" :aria-expanded="areaOpen" class="min-h-12 w-full rounded-xl border border-slate-200 bg-slate-50/60 px-4 pr-11 py-3 font-normal outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:bg-white focus:ring-4 focus:ring-emerald-700/10" @focus="areaOpen = true" @input="areaOpen = true; clearFieldError('area')" @keydown.esc="areaOpen = false"><span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-500">⌄</span></div>
                <ul v-if="areaOpen" id="area-options" role="listbox" class="absolute left-0 right-0 top-full z-30 mt-1 max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                  <li v-for="item in filteredAreas" :key="`${item.locationKind}-${item.id || item.name}`" role="option" :aria-selected="form.area === item.name"><button type="button" class="flex w-full items-start justify-between gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-emerald-50" @mousedown.prevent="chooseArea(item)"><span><span class="block text-sm font-semibold text-slate-800">{{ item.name }}</span><span v-if="item.parentName" class="block text-xs text-slate-500">{{ item.parentName }}</span></span><span class="shrink-0 text-[10px] text-slate-500">{{ item.locationKind }}</span></button></li>
                  <li v-if="!filteredAreas.length" class="px-3 py-3 text-xs text-slate-600">No match. Your typed location will be used: <strong>{{ form.area }}</strong></li>
                </ul>
                <span v-if="locationLoading" class="text-xs text-emerald-700">Loading matching locations…</span><span v-else-if="locationDataError" class="text-xs text-amber-700">{{ locationDataError }}</span><span v-else class="text-xs text-slate-500">Choose a suggestion or keep your own typed location.</span><span v-if="errorFor('area')" class="text-xs font-medium text-red-600">{{ errorFor('area') }}</span>
              </div>
              <label class="grid content-start gap-2 text-sm font-bold text-slate-700">Post code
                <input v-model.trim="form.postal_code" type="text" name="postal_code" inputmode="numeric" maxlength="12" placeholder="Auto-suggested; you can edit" class="min-h-12 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 font-normal outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:bg-white focus:ring-4 focus:ring-emerald-700/10">
                <span class="text-xs font-normal text-slate-500">Suggested from the selected area when available.</span>
              </label>
              <label class="grid gap-2 text-sm font-bold text-slate-700 sm:col-span-2">House, road and detailed address <span class="text-red-600">*</span><textarea v-model.trim="form.address_line" name="address_line" autocomplete="street-address" required maxlength="500" rows="3" placeholder="House / building, road, landmark and any details that help the delivery team find you" class="resize-y rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 font-normal leading-6 outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:bg-white focus:ring-4 focus:ring-emerald-700/10" :aria-invalid="!!errorFor('address_line')" @input="clearFieldError('address_line')"></textarea><span v-if="errorFor('address_line')" class="text-xs font-medium text-red-600">{{ errorFor('address_line') }}</span></label>
              <label class="grid gap-2 text-sm font-bold text-slate-700 sm:col-span-2">Order note <span class="font-normal text-slate-400">(optional)</span><textarea v-model.trim="form.notes" name="notes" maxlength="1000" rows="2" placeholder="Any delivery instructions or additional information?" class="resize-y rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 font-normal leading-6 outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:bg-white focus:ring-4 focus:ring-emerald-700/10" @input="clearFieldError('notes')" /><span v-if="errorFor('notes')" class="text-xs font-medium text-red-600">{{ errorFor('notes') }}</span></label>
            </div>
          </section>

          <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-5 sm:px-7">
              <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-sm font-black text-emerald-900">03</span>
              <div><h2 class="text-lg font-extrabold text-slate-950">Choose your delivery partner</h2><p class="mt-1 text-xs leading-5 text-slate-500">Select a preferred courier, or let MADINA choose the best available option.</p></div>
            </div>
            <div class="space-y-4 p-5 sm:p-7">
              <div class="rounded-2xl border border-sky-100 bg-sky-50/70 p-4">
                <div class="flex items-start gap-3">
                  <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-white text-sky-800"><UiIcon name="info" :size="18" /></span>
                  <div class="min-w-0"><p class="text-sm font-extrabold text-sky-950">Delivery options</p><p class="mt-1 text-xs leading-5 text-sky-900/80">{{ quoteDisclaimer }}</p></div>
                </div>
              </div>
              <p v-if="quoteLoading" class="text-xs text-slate-500">Calculating delivery rates…</p>
              <p v-else-if="quoteError" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">{{ quoteError }}</p>
              <p v-else-if="courierOptions.length && !hasAvailableCourier" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Active courier partners are listed below, but none has a configured rate for this delivery zone and parcel weight. Please contact the store to complete delivery setup.</p>
              <p v-else-if="!courierOptions.length && !quoteLoading" class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">No active courier providers are currently available.</p>

              <button type="button" class="flex w-full items-start gap-3 rounded-2xl border-2 p-4 text-left transition"
                :class="selectionMode === 'no_preference' ? 'border-emerald-700 bg-emerald-50/60 shadow-sm' : 'border-slate-200 hover:border-emerald-200'"
                @click="setSelectionMode('no_preference')">
                <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full border-2" :class="selectionMode === 'no_preference' ? 'border-emerald-700' : 'border-slate-300'"><span v-if="selectionMode === 'no_preference'" class="size-2.5 rounded-full bg-emerald-700" /></span>
                <span class="min-w-0 flex-1"><span class="block text-sm font-extrabold text-slate-950">No preference — choose for me</span><span class="mt-1 block text-xs leading-5 text-slate-600">MADINA will recommend the lowest configured rate for your delivery area and parcel weight.</span></span>
                <span v-if="selectionMode === 'no_preference'" class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-emerald-900">Recommended</span>
              </button>

              <div class="grid gap-3 sm:grid-cols-2">
                <button v-for="courier in courierOptions" :key="courier.code" type="button" :disabled="!courier.isAvailable"
                  class="group rounded-2xl border-2 p-4 text-left transition enabled:hover:-translate-y-0.5 enabled:hover:shadow-md disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-75"
                  :class="selectionMode === 'customer_choice' && selectedCourierCode === courier.code ? 'border-emerald-700 bg-emerald-50/60 shadow-sm' : 'border-slate-200 bg-white enabled:hover:border-emerald-200'"
                  @click="setCourier(courier.code)">
                  <span class="flex items-center justify-between gap-3">
                    <span class="flex min-w-0 items-center gap-3">
                      <span class="grid size-11 shrink-0 place-items-center rounded-xl text-xs font-black tracking-tight" :class="courier.brandClass">{{ courier.shortName }}</span>
                      <span class="min-w-0"><span class="block truncate text-sm font-extrabold text-slate-950">{{ courier.name }}</span><span class="mt-0.5 block text-[10px] font-semibold" :class="courier.isAvailable ? 'text-emerald-700' : 'text-amber-700'">{{ courier.isAvailable ? courier.serviceLabel : 'Rate not configured' }}</span></span>
                    </span>
                    <span class="grid size-5 shrink-0 place-items-center rounded-full border-2" :class="selectionMode === 'customer_choice' && selectedCourierCode === courier.code ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-300'"><UiIcon v-if="selectionMode === 'customer_choice' && selectedCourierCode === courier.code" name="check" :size="12" /></span>
                  </span>
                  <span class="mt-4 flex items-end justify-between gap-2">
                    <span><span class="block text-[10px] text-slate-500">Availability</span><span class="mt-0.5 block text-xs font-bold" :class="courier.isAvailable ? 'text-emerald-700' : 'text-amber-700'">{{ courier.isAvailable ? 'Available for this address' : (courier.unavailableReason || 'Not available') }}</span></span>
                    <span class="text-right"><span class="block text-[10px] text-slate-500">Delivery charge</span><strong class="text-lg font-black tabular-nums text-slate-950">{{ courier.isAvailable ? money(courier.charge) : '—' }}</strong></span>
                  </span>
                  <span class="mt-3 flex flex-wrap gap-1.5">
                    <span v-for="feature in courier.features" :key="feature" class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-semibold text-slate-600">{{ feature }}</span>
                  </span>
                </button>
              </div>

              <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div><p class="text-xs font-extrabold text-slate-800">Delivery estimate</p><p class="mt-1 text-[11px] text-slate-500">{{ deliveryZone.label }} · Approx. {{ (estimatedWeightGrams / 1000).toFixed(2) }} kg parcel</p></div>
                <div class="text-right"><p class="text-[10px] text-slate-500">{{ selectedCourier?.name || 'Recommended partner' }}</p><p class="text-xl font-black tabular-nums text-emerald-900">{{ money(deliveryCharge) }}</p></div>
              </div>
              <p class="text-[11px] leading-5 text-slate-500">You can choose a preferred partner, but if the provider is unavailable or cannot accept the shipment, MADINA may assign another suitable partner. After booking, changes follow a cancel-and-rebook process and may require staff assistance.</p>
            </div>
          </section>

          <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-5 sm:px-7"><span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-sm font-black text-emerald-900">04</span><div><h2 class="text-lg font-extrabold text-slate-950">Choose payment method</h2><p class="mt-1 text-xs leading-5 text-slate-500">Select the payment option that works best for you.</p></div></div>
            <div class="space-y-4 p-5 sm:p-7">
              <label class="flex cursor-pointer items-start gap-4 rounded-2xl border-2 p-4 transition" :class="paymentMethod === 'cash_on_delivery' ? 'border-emerald-700 bg-emerald-50/60 shadow-sm' : 'border-slate-200 hover:border-emerald-200'"><input v-model="paymentMethod" type="radio" value="cash_on_delivery" class="mt-1 size-4 accent-emerald-700"><span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white text-emerald-800 shadow-sm"><UiIcon name="truck" :size="21" /></span><span class="min-w-0 flex-1"><span class="block font-extrabold text-slate-950">Cash on Delivery</span><span class="mt-1 block text-xs leading-5 text-slate-600">Place your order now and pay when your order is delivered.</span></span><span v-if="paymentMethod === 'cash_on_delivery'" class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-emerald-900">Selected</span></label>
              <label class="flex cursor-pointer items-start gap-4 rounded-2xl border-2 p-4 transition" :class="paymentMethod === 'bank_transfer' ? 'border-emerald-700 bg-emerald-50/60 shadow-sm' : 'border-slate-200 hover:border-emerald-200'"><input v-model="paymentMethod" type="radio" value="bank_transfer" class="mt-1 size-4 accent-emerald-700"><span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white text-emerald-800 shadow-sm"><UiIcon name="building-2" :size="21" /></span><span class="min-w-0 flex-1"><span class="block font-extrabold text-slate-950">Bank Transfer</span><span class="mt-1 block text-xs leading-5 text-slate-600">Transfer manually and submit your transaction details. Our team will verify receipt before confirming your order.</span></span><span v-if="paymentMethod === 'bank_transfer'" class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-emerald-900">Selected</span></label>

              <div class="grid gap-3 sm:grid-cols-2">
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4"><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-xl bg-pink-50 text-xs font-black text-pink-700">bK</span><div><p class="text-sm font-extrabold text-slate-900">bKash</p><p class="text-[10px] text-slate-500">API integration planned</p></div></div><span class="rounded-full bg-amber-50 px-2.5 py-1 text-[9px] font-extrabold uppercase tracking-wide text-amber-800">Coming soon</span></div>
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4"><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-xl bg-orange-50 text-xs font-black text-orange-700">N</span><div><p class="text-sm font-extrabold text-slate-900">Nagad</p><p class="text-[10px] text-slate-500">API integration planned</p></div></div><span class="rounded-full bg-amber-50 px-2.5 py-1 text-[9px] font-extrabold uppercase tracking-wide text-amber-800">Coming soon</span></div>
              </div>

              <div v-if="paymentMethod === 'bank_transfer'" class="space-y-5 rounded-3xl border border-emerald-200 bg-gradient-to-br from-emerald-50/90 to-white p-4 sm:p-6">
                <div class="flex items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-emerald-800 shadow-sm"><UiIcon name="building-2" :size="19" /></span><div><p class="font-extrabold text-slate-950">Bank account details</p><p class="mt-1 text-xs leading-5 text-slate-600">Demo credentials for now. These will later be managed from the backend dashboard. Do not transfer real money to this demo account.</p></div></div>
                <dl class="grid gap-3 sm:grid-cols-2">
                  <div class="rounded-xl border border-white bg-white/80 p-3"><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bank</dt><dd class="mt-1 text-sm font-extrabold text-slate-900">{{ demoBank.name }}</dd></div>
                  <div class="rounded-xl border border-white bg-white/80 p-3"><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Account name</dt><dd class="mt-1 text-sm font-extrabold text-slate-900">{{ demoBank.account_name }}</dd></div>
                  <div class="rounded-xl border border-white bg-white/80 p-3"><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Account number</dt><dd class="mt-1 text-sm font-extrabold tracking-wide text-slate-900">{{ demoBank.account_number }}</dd></div>
                  <div class="rounded-xl border border-white bg-white/80 p-3"><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Branch</dt><dd class="mt-1 text-sm font-extrabold text-slate-900">{{ demoBank.branch }}</dd></div>
                  <div class="rounded-xl border border-white bg-white/80 p-3 sm:col-span-2"><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Routing number</dt><dd class="mt-1 text-sm font-extrabold tracking-wide text-slate-900">{{ demoBank.routing_number }}</dd></div>
                </dl>
                <div class="border-t border-emerald-200/80 pt-4"><p class="mb-3 text-sm font-extrabold text-slate-950">Submit your transfer details</p><div class="grid gap-4 sm:grid-cols-2">
                  <label class="grid gap-2 text-sm font-bold text-slate-700">Sender account number<input v-model.trim="bankForm.sender_account" required maxlength="80" placeholder="Account used to send payment" class="min-h-12 rounded-xl border border-slate-200 bg-white px-4 py-3 font-normal outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"></label>
                  <label class="grid gap-2 text-sm font-bold text-slate-700">Transaction reference / ID<input v-model.trim="bankForm.transaction_reference" required maxlength="120" placeholder="Bank transaction reference" class="min-h-12 rounded-xl border border-slate-200 bg-white px-4 py-3 font-normal outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"></label>
                  <label class="grid gap-2 text-sm font-bold text-slate-700 sm:col-span-2">Transferred amount (BDT)<input v-model="bankForm.amount" required type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="Enter amount transferred" class="min-h-12 rounded-xl border border-slate-200 bg-white px-4 py-3 font-normal outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:ring-4 focus:ring-emerald-700/10"><span class="text-xs font-normal text-slate-500">Enter the exact amount you transferred. It must match the final order amount for approval.</span></label>
                </div></div>
                <div class="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4"><UiIcon name="info" :size="18" /><p class="text-xs leading-5 text-amber-900">Your order will remain pending until staff verifies the transfer, approves it and records the funds as received in the bank account.</p></div>
              </div>
            </div>
          </section>

          <div v-if="message" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-6 text-red-800" role="alert" aria-live="assertive">{{ message }}</div>
          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><p class="text-xs leading-5 text-slate-500">By placing this order, you confirm that your contact and delivery information is accurate. Our team will use it to process and coordinate this order.</p><button type="submit" :disabled="submitting || quoteLoading || !hasAvailableCourier || !cart.items.length || !form.customer_name.trim() || !phoneLooksValid || !form.address_line.trim()" class="mt-4 flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-[#073f31] px-5 py-4 text-sm font-extrabold text-white shadow-lg shadow-emerald-950/10 transition hover:bg-emerald-950 focus:outline-none focus:ring-4 focus:ring-emerald-800/20 disabled:cursor-not-allowed disabled:opacity-50"><span v-if="submitting" class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />{{ submitting ? 'Submitting your order…' : (paymentMethod === 'bank_transfer' ? 'Submit bank transfer details · ' : 'Place order · ') + money(payableTotal) }}</button><p class="mt-3 text-center text-[11px] leading-5 text-slate-500">Please click once. Bank transfer orders remain pending until our team verifies and receives the payment.</p></div>
        </form>

        <aside class="space-y-4 lg:sticky lg:top-6">
          <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="bg-[#073f31] px-5 py-5 text-white sm:px-6"><p class="text-[10px] font-extrabold uppercase tracking-[0.2em] text-emerald-200">Your purchase</p><div class="mt-1 flex items-center justify-between gap-3"><h2 class="text-xl font-black tracking-tight">Order summary</h2><span class="rounded-full bg-white/10 px-2.5 py-1 text-xs font-bold text-white">{{ totalUnits }} {{ totalUnits === 1 ? 'item' : 'items' }}</span></div></div>
            <div class="max-h-[390px] space-y-4 overflow-y-auto p-5 sm:px-6">
              <article v-for="item in cart.items" :key="item.variant_id" class="flex gap-3"><NuxtLink :to="`/products/${item.product_slug}`" class="size-[68px] shrink-0 overflow-hidden rounded-xl border border-slate-100 bg-slate-50"><StorefrontImage :src="item.image_url" :alt="item.product_name" kind="product" label="Checkout Product Image" dimensions="160 × 160 px" ratio="1 / 1" :width="160" :height="160" /></NuxtLink><div class="min-w-0 flex-1"><NuxtLink :to="`/products/${item.product_slug}`" class="line-clamp-2 text-sm font-bold leading-5 text-slate-900 hover:text-emerald-800">{{ item.product_name }}</NuxtLink><p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ item.variant_name }}</p><div class="mt-2 flex items-center justify-between gap-2"><span class="text-xs font-medium text-slate-500">Qty: {{ item.quantity }}</span><strong class="text-sm tabular-nums text-slate-900">{{ money(item.line_total) }}</strong></div></div></article>
            </div>
            <div class="space-y-3 border-t border-slate-100 px-5 py-5 sm:px-6"><div class="flex justify-between gap-3 text-sm"><span class="text-slate-600">Items subtotal</span><strong class="tabular-nums text-slate-900">{{ money(cart.subtotal) }}</strong></div><div class="flex justify-between gap-3 text-sm"><span class="text-slate-600">Delivery · {{ selectedCourier?.name || 'Recommended' }}</span><strong class="tabular-nums text-slate-900">{{ money(deliveryCharge) }}</strong></div><div class="border-t border-dashed border-slate-200 pt-4"><div class="flex items-end justify-between gap-3"><span class="font-extrabold text-slate-950">Total payable</span><strong class="text-2xl font-black tracking-tight tabular-nums text-slate-950">{{ money(payableTotal) }}</strong></div><p class="mt-2 text-[11px] leading-5 text-slate-500">Delivery amount is calculated by the backend using the active rate rules configured in Admin. Live courier API pricing is not implied.</p></div></div>
          </section>
          <section class="rounded-3xl border border-emerald-100 bg-emerald-50/70 p-5"><div class="flex gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-emerald-800"><UiIcon name="headset" :size="19" /></span><div><h3 class="text-sm font-extrabold text-slate-950">Order support</h3><p class="mt-1 text-xs leading-5 text-slate-600">Need help before placing your order? Our team is ready to assist.</p><NuxtLink to="/pages/contact" class="mt-2 inline-flex text-xs font-extrabold text-emerald-800 hover:underline">Contact MADINA →</NuxtLink></div></div></section>
          <NuxtLink to="/cart" class="flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-emerald-700 hover:text-emerald-800">Review or edit cart</NuxtLink>
        </aside>
      </div>
    </main>

    <footer class="border-t border-slate-200 bg-white py-5"><div class="container flex flex-col items-center justify-between gap-2 text-center text-xs text-slate-500 sm:flex-row sm:text-left"><p>© {{ new Date().getFullYear() }} MADINA Scale. All rights reserved.</p><p>Checkout securely through MADINA Storefront.</p></div></footer>
  </div>
</template>
