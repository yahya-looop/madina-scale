import { computed, ref, watch } from 'vue'

type CartItem = { variant_id: number; quantity: number }
type LocationInput = { division: string; district: string; area: string }
type PaymentMethod = 'cash_on_delivery' | 'bank_transfer'
type CourierOption = {
  code: string
  name: string
  shortName: string
  serviceLabel: string
  brandClass: string
  minDays: null
  maxDays: null
  features: string[]
  charge: number
}
type QuoteResponse = {
  zone: string
  parcel_weight_grams: number
  recommended_provider_code: string | null
  charge: string
  quotes: Array<{ provider_code: string; provider_name: string; charge: string }>
}

const PRESENTATION: Record<string, Pick<CourierOption, 'shortName' | 'serviceLabel' | 'brandClass'>> = {
  pathao: { shortName: 'pathao', serviceLabel: 'Configured delivery partner', brandClass: 'bg-[#fce7f3] text-[#be185d]' },
  redx: { shortName: 'REDX', serviceLabel: 'Configured delivery partner', brandClass: 'bg-[#fee2e2] text-[#b91c1c]' },
  carrybee: { shortName: 'CARRY', serviceLabel: 'Configured delivery partner', brandClass: 'bg-[#dbeafe] text-[#1d4ed8]' },
  bangladesh_post: { shortName: 'POST', serviceLabel: 'Configured delivery partner', brandClass: 'bg-[#dcfce7] text-[#166534]' },
  steadfast: { shortName: 'STEADFAST', serviceLabel: 'Configured delivery partner', brandClass: 'bg-[#fef3c7] text-[#92400e]' },
}

export function useDemoDeliveryOptions(
  cart: { value?: { items?: CartItem[]; subtotal?: number; item_count?: number } | null },
  getLocation: () => LocationInput,
  getPaymentMethod: () => PaymentMethod = () => 'cash_on_delivery',
) {
  const selectionMode = ref<'no_preference' | 'customer_choice'>('no_preference')
  const selectedCourierCode = ref('')
  const quote = ref<QuoteResponse | null>(null)
  const quoteLoading = ref(false)
  const quoteError = ref('')
  let requestSequence = 0

  const estimatedWeightGrams = computed(() => Number(quote.value?.parcel_weight_grams || 0))
  const deliveryZone = computed(() => {
    const code = quote.value?.zone || ''
    const labels: Record<string, string> = {
      inside_dhaka: 'Inside Dhaka',
      outside_dhaka: 'Outside Dhaka',
      remote_special: 'Remote / Special Area',
    }
    return { code, label: labels[code] || 'Delivery area not selected' }
  })

  const courierOptions = computed<CourierOption[]>(() => (quote.value?.quotes || []).map((item) => ({
    code: item.provider_code,
    name: item.provider_name,
    ...(PRESENTATION[item.provider_code] || {
      shortName: item.provider_code.toUpperCase(),
      serviceLabel: 'Configured delivery partner',
      brandClass: 'bg-slate-100 text-slate-700',
    }),
    minDays: null,
    maxDays: null,
    features: ['Admin-configured tariff'],
    charge: Number(item.charge || 0),
  })))

  const recommendedCourier = computed(() =>
    courierOptions.value.find(item => item.code === quote.value?.recommended_provider_code) || courierOptions.value[0] || null,
  )
  const selectedCourier = computed(() => selectionMode.value === 'customer_choice'
    ? courierOptions.value.find(item => item.code === selectedCourierCode.value) || null
    : recommendedCourier.value)
  const deliveryCharge = computed(() => selectedCourier.value?.charge || 0)

  async function refreshQuotes() {
    const sequence = ++requestSequence
    const items = cart.value?.items || []
    if (!items.length) {
      quote.value = null
      quoteError.value = ''
      return
    }

    quoteLoading.value = true
    quoteError.value = ''
    try {
      const config = useRuntimeConfig()
      const location = getLocation()
      const result = await $fetch<{ data: QuoteResponse }>(`${config.public.apiBase}/delivery/quotes`, {
        credentials: 'include',
        query: {
          division: location.division,
          district: location.district,
          area: location.area,
          payment_method: getPaymentMethod(),
        },
      })
      if (sequence !== requestSequence) return
      quote.value = result.data
      if (selectionMode.value === 'customer_choice'
        && !courierOptions.value.some(item => item.code === selectedCourierCode.value)) {
        selectionMode.value = 'no_preference'
        selectedCourierCode.value = ''
      }
    } catch (error: any) {
      if (sequence !== requestSequence) return
      quote.value = null
      quoteError.value = error?.data?.errors?.delivery?.[0]
        || error?.data?.message
        || 'Delivery rates are not configured for this destination yet.'
    } finally {
      if (sequence === requestSequence) quoteLoading.value = false
    }
  }

  watch(
    () => [
      (cart.value?.items || []).map(item => `${item.variant_id}:${item.quantity}`).join('|'),
      getLocation().division,
      getLocation().district,
      getLocation().area,
      getPaymentMethod(),
    ],
    () => { void refreshQuotes() },
    { immediate: true },
  )

  function setSelectionMode(mode: 'customer_choice' | 'no_preference') {
    selectionMode.value = mode
    if (mode === 'no_preference') selectedCourierCode.value = ''
  }

  function setCourier(code: string) {
    if (!courierOptions.value.some(item => item.code === code)) return
    selectedCourierCode.value = code
    selectionMode.value = 'customer_choice'
  }

  return {
    courierOptions,
    selectedCourierCode,
    selectionMode,
    estimatedWeightGrams,
    deliveryZone,
    selectedCourier,
    deliveryCharge,
    quoteLoading,
    quoteError,
    quoteDisclaimer: 'Delivery charges are calculated by Laravel from active courier rate rules configured in Admin. These are administrative tariffs, not live courier API quotes.',
    refreshQuotes,
    setSelectionMode,
    setCourier,
  }
}
