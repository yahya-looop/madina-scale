<script setup lang="ts">
type TrackingEvent = { status: string; occurred_at: string | null }
type TrackingShipment = {
  provider: string
  tracking_code: string | null
  status: string
  booked_at: string | null
  delivered_at: string | null
  events: TrackingEvent[]
}
type TrackingResult = {
  order_number: string
  status: string
  placed_at: string | null
  updated_at: string | null
  items: Array<{ product_name: string; variant_name: string | null; quantity: string | number }>
  timeline: Array<{ type: string; status: string; occurred_at: string | null }>
  shipments: TrackingShipment[]
}

const config = useRuntimeConfig()
const prefill = useState<{ reference: string; phone: string }>('madina-track-order-prefill', () => ({ reference: '', phone: '' }))
const reference = ref(prefill.value.reference)
const phone = ref(prefill.value.phone)
const loading = ref(false)
const error = ref('')
const result = ref<TrackingResult | null>(null)

useSeoMeta({
  title: 'Track Your Order | MADINA Scale',
  description: 'Securely check your MADINA Scale order and shipment progress.',
})

onMounted(() => {
  if (reference.value.trim() && phone.value.trim()) submitTracking()
})

function statusLabel(status: string) {
  const labels: Record<string, string> = {
    pending: 'Order received', confirmed: 'Order confirmed', processing: 'Processing',
    partially_fulfilled: 'Partially fulfilled', fulfilled: 'Fulfilled',
    shipped: 'Shipped', booked: 'Shipment booked', in_transit: 'In transit',
    delivery_failed: 'Delivery attempt failed', partially_delivered: 'Partially delivered',
    delivered: 'Delivered', returned: 'Returned', cancelled: 'Cancelled',
    queued: 'Awaiting courier booking', booking: 'Booking shipment',
  }
  return labels[status] || status.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase())
}

function formatDate(value: string | null) {
  if (!value) return 'Not available'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Not available'
  return new Intl.DateTimeFormat('en-BD', {
    dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Dhaka',
  }).format(date)
}

async function submitTracking() {
  error.value = ''
  result.value = null
  loading.value = true

  try {
    const csrf = await $fetch<{ token: string }>(`${config.public.apiBase}/csrf-token`, { credentials: 'include' })
    const response = await $fetch<{ data: TrackingResult }>(`${config.public.apiBase}/orders/track`, {
      method: 'POST',
      body: { reference: reference.value.trim(), phone: phone.value.trim() },
      credentials: 'include',
      headers: { 'X-CSRF-TOKEN': csrf.token },
    })
    result.value = response.data
    prefill.value = { reference: reference.value.trim(), phone: '' }
  } catch (cause: any) {
    error.value = cause?.data?.message || 'We could not verify this order. Check the details and try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="track-page container">
    <div class="track-page__breadcrumb"><NuxtLink to="/">Home</NuxtLink><span>/</span><span>Track Order</span></div>

    <section class="track-hero">
      <div class="track-hero__copy">
        <span class="section-kicker">MADINA CUSTOMER CARE</span>
        <h1>Track Your Order</h1>
        <p>Follow your order from confirmation to delivery. Use the order number or courier tracking number and the mobile number provided at checkout.</p>
      </div>
      <div class="track-hero__mark"><UiIcon name="package-search" :size="62" /></div>
    </section>

    <section class="track-lookup" aria-labelledby="track-lookup-title">
      <div class="track-lookup__heading">
        <span class="track-lookup__icon"><UiIcon name="search" :size="21" /></span>
        <div>
          <h2 id="track-lookup-title">Find your order</h2>
          <p>Both fields are required to protect your order information.</p>
        </div>
      </div>
      <form class="track-lookup__form" @submit.prevent="submitTracking">
        <label>
          <span>Order Number / Tracking Number</span>
          <input v-model="reference" type="text" name="reference" autocomplete="off" placeholder="Enter your order ID or tracking number" minlength="3" maxlength="160" required>
        </label>
        <label>
          <span>Mobile Number</span>
          <input v-model="phone" type="tel" name="phone" autocomplete="tel" inputmode="tel" placeholder="Mobile number used at checkout" minlength="8" maxlength="40" required>
        </label>
        <button type="submit" :disabled="loading">
          <span v-if="loading" class="track-spinner" aria-hidden="true" />
          <span>{{ loading ? 'Verifying…' : 'Track Order' }}</span>
          <UiIcon v-if="!loading" name="arrow-right" :size="18" />
        </button>
      </form>
      <p v-if="error" class="track-error" role="alert">{{ error }}</p>
      <p class="track-privacy"><UiIcon name="shield-check" :size="15" /> Your mobile number is used only for verification and is never displayed in the result.</p>
    </section>

    <section v-if="result" class="track-result" aria-live="polite">
      <div class="track-result__header">
        <div>
          <span class="section-kicker">ORDER DETAILS</span>
          <h2>{{ result.order_number }}</h2>
          <p>Placed {{ formatDate(result.placed_at) }}</p>
        </div>
        <span class="track-status-pill" :class="`is-${result.status}`">{{ statusLabel(result.status) }}</span>
      </div>

      <div class="track-result__grid">
        <section class="track-result__panel">
          <h3>Order progress</h3>
          <ol v-if="result.timeline.length" class="track-timeline">
            <li v-for="(event, index) in result.timeline" :key="`${event.status}-${event.occurred_at}-${index}`">
              <span class="track-timeline__dot" />
              <div><strong>{{ statusLabel(event.status) }}</strong><small>{{ formatDate(event.occurred_at) }}</small></div>
            </li>
          </ol>
          <p v-else class="track-muted">The order has been received. Further status updates will appear here.</p>
          <div v-if="result.shipments.length" class="track-shipments">
            <h4>Shipment information</h4>
            <article v-for="(shipment, index) in result.shipments" :key="`${shipment.provider}-${shipment.tracking_code || index}`" class="track-shipment">
              <div><strong>{{ shipment.provider.replaceAll('_', ' ').toUpperCase() }}</strong><span>{{ statusLabel(shipment.status) }}</span></div>
              <p v-if="shipment.tracking_code">Tracking number: <b>{{ shipment.tracking_code }}</b></p>
              <p v-else>Courier tracking number has not been assigned yet.</p>
              <small v-if="shipment.delivered_at">Delivered {{ formatDate(shipment.delivered_at) }}</small>
              <small v-else-if="shipment.booked_at">Booked {{ formatDate(shipment.booked_at) }}</small>
              <ul v-if="shipment.events.length" class="track-shipment__events">
                <li v-for="(event, eventIndex) in shipment.events" :key="`${event.status}-${eventIndex}`">{{ statusLabel(event.status) }} <time>{{ formatDate(event.occurred_at) }}</time></li>
              </ul>
            </article>
          </div>
        </section>

        <aside class="track-result__panel track-result__items">
          <h3>Items in this order</h3>
          <div v-for="(item, index) in result.items" :key="`${item.product_name}-${index}`" class="track-item">
            <div><strong>{{ item.product_name }}</strong><small v-if="item.variant_name">{{ item.variant_name }}</small></div>
            <span>× {{ item.quantity }}</span>
          </div>
          <p class="track-muted">Last updated {{ formatDate(result.updated_at) }}</p>
        </aside>
      </div>
    </section>

    <section v-else class="track-help">
      <UiIcon name="headset" :size="25" />
      <div><strong>Need help finding your order?</strong><p>Check your order confirmation or contact the MADINA team for assistance.</p></div>
      <NuxtLink to="/pages/contact">Contact us <span>→</span></NuxtLink>
    </section>
  </main>
</template>
