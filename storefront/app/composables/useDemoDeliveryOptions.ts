import { computed, ref, watch } from 'vue'

type DemoCartItem = {
  variant_id: number
  product_name: string
  variant_name: string
  quantity: number
}

type LocationInput = {
  division: string
  district: string
  area: string
}

type CourierOption = {
  code: string
  name: string
  shortName: string
  serviceLabel: string
  brandClass: string
  minDays: number
  maxDays: number
  features: string[]
  rates: { base: number; per500g: number }
  zoneMultiplier: number
  charge: number
}

const PROVIDERS: Omit<CourierOption, 'charge'>[] = [
  {
    code: 'pathao',
    name: 'Pathao Courier',
    shortName: 'pathao',
    serviceLabel: 'Nationwide delivery',
    brandClass: 'bg-[#fce7f3] text-[#be185d]',
    minDays: 1,
    maxDays: 2,
    features: ['COD supported*', 'Parcel tracking*'],
    rates: { base: 65, per500g: 12 },
    zoneMultiplier: 1,
  },
  {
    code: 'redx',
    name: 'REDX',
    shortName: 'REDX',
    serviceLabel: 'Nationwide delivery',
    brandClass: 'bg-[#fee2e2] text-[#b91c1c]',
    minDays: 1,
    maxDays: 3,
    features: ['COD supported*', 'Parcel tracking*'],
    rates: { base: 60, per500g: 11 },
    zoneMultiplier: 1.08,
  },
  {
    code: 'carrybee',
    name: 'CarryBee',
    shortName: 'CARRY',
    serviceLabel: 'Selected-area coverage*',
    brandClass: 'bg-[#dbeafe] text-[#1d4ed8]',
    minDays: 2,
    maxDays: 3,
    features: ['Parcel tracking*', 'Area coverage varies'],
    rates: { base: 68, per500g: 10 },
    zoneMultiplier: 1.12,
  },
  {
    code: 'bangladesh_post',
    name: 'Bangladesh Post',
    shortName: 'POST',
    serviceLabel: 'Postal delivery concept',
    brandClass: 'bg-[#dcfce7] text-[#166534]',
    minDays: 3,
    maxDays: 5,
    features: ['Postal network', 'Service subject to coverage'],
    rates: { base: 45, per500g: 8 },
    zoneMultiplier: 1.18,
  },
]

const REMOTE_DISTRICTS = new Set([
  'bandarban', 'khagrachhari', 'rangamati', 'bhola', 'barguna',
  'pat uakhali', 'patuakhali', 'sunamganj', 'habiganj', 'netrokona',
  'kurigram', 'lalmonirhat', 'gaibandha', 'cox s bazar', "cox's bazar",
])

function normalize(value: string) {
  return String(value || '').trim().toLocaleLowerCase().replace(/[।,.-]/g, '').replace(/\s+/g, ' ')
}

function estimateUnitWeight(item: DemoCartItem) {
  const text = `${item.product_name} ${item.variant_name}`.toLowerCase()
  // Demo-only weight catalogue. Replace with product/variant weight from Laravel later.
  if (/phone|mobile|smartphone|tablet/.test(text)) return 350
  if (/laptop|monitor|printer/.test(text)) return 1800
  if (/cable|adapter|charger|mouse|earphone|earbud/.test(text)) return 220
  if (/chair|table|rack|furniture/.test(text)) return 3500
  return 500
}

export function useDemoDeliveryOptions(
  cart: { value?: { items?: DemoCartItem[]; subtotal?: number; item_count?: number } | null },
  getLocation: () => LocationInput,
) {
  const selectionMode = ref<'no_preference' | 'customer_choice'>('no_preference')
  const selectedCourierCode = ref('')

  const estimatedWeightGrams = computed(() => {
    const items = Array.isArray(cart.value?.items) ? cart.value.items : []
    return Math.max(500, items.reduce(
      (total, item) => total + estimateUnitWeight(item) * Math.max(1, Number(item.quantity || 1)),
      0,
    ))
  })

  const deliveryZone = computed(() => {
    const location = getLocation()
    const district = normalize(location.district)
    const division = normalize(location.division)
    const area = normalize(location.area)

    if (REMOTE_DISTRICTS.has(district) || /remote|island|hill tract/.test(area)) {
      return { code: 'remote_special', label: 'Remote / Special Area', multiplier: 1.65 }
    }
    if (district === 'dhaka' || (division === 'dhaka' && !district)) {
      return { code: 'inside_dhaka', label: 'Inside Dhaka', multiplier: 1 }
    }
    return { code: 'outside_dhaka', label: 'Outside Dhaka', multiplier: 1.3 }
  })

  const courierOptions = computed<CourierOption[]>(() => {
    const slabs = Math.max(0, Math.ceil(estimatedWeightGrams.value / 500) - 1)
    return PROVIDERS.map((provider) => ({
      ...provider,
      charge: Math.ceil(
        (provider.rates.base + slabs * provider.rates.per500g)
        * provider.zoneMultiplier
        * deliveryZone.value.multiplier,
      ),
    }))
  })

  const recommendedCourier = computed(() =>
    [...courierOptions.value].sort((a, b) =>
      (a.charge * 0.7 + a.maxDays * 8) - (b.charge * 0.7 + b.maxDays * 8),
    )[0] || null,
  )

  const selectedCourier = computed(() => {
    if (selectionMode.value === 'customer_choice') {
      return courierOptions.value.find(item => item.code === selectedCourierCode.value) || recommendedCourier.value
    }
    return recommendedCourier.value
  })

  const deliveryCharge = computed(() => selectedCourier.value?.charge || 0)

  function setSelectionMode(mode: 'no_preference' | 'customer_choice') {
    selectionMode.value = mode
    if (mode === 'no_preference') selectedCourierCode.value = ''
  }

  function setCourier(code: string) {
    if (!courierOptions.value.some(item => item.code === code)) return
    selectedCourierCode.value = code
    selectionMode.value = 'customer_choice'
  }

  watch(courierOptions, (options) => {
    if (selectionMode.value === 'customer_choice'
      && !options.some(item => item.code === selectedCourierCode.value)) {
      selectionMode.value = 'no_preference'
      selectedCourierCode.value = ''
    }
  })

  return {
    courierOptions,
    selectedCourierCode,
    selectionMode,
    estimatedWeightGrams,
    deliveryZone,
    selectedCourier,
    deliveryCharge,
    quoteDisclaimer: 'Estimated parcel weight and zone-based rates are calculated from demo rules.',
    setSelectionMode,
    setCourier,
  }
}
