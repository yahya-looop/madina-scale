import { computed, ref } from 'vue'

type CartItem = {
  variant_id: number
  product_name: string
  product_slug: string
  variant_name: string
  sku: string | null
  image_url: string | null
  price: number
  quantity: number
  line_total: number
}
type CartPayload = { items: CartItem[]; subtotal: number; item_count: number }

const emptyCart = (): CartPayload => ({ items: [], subtotal: 0, item_count: 0 })
const state = ref<CartPayload>(emptyCart())
const loading = ref(false)
const error = ref('')

async function csrfToken(): Promise<string> {
  if (!import.meta.client) return ''
  const response = await $fetch<{ token: string }>(`${useRuntimeConfig().public.apiBase}/csrf-token`, { credentials: 'include' })
  return response.token
}

export function useStorefrontCart() {
  const config = useRuntimeConfig()
  const endpoint = `${config.public.apiBase}/cart`
  const headers = async () => ({ 'X-CSRF-TOKEN': await csrfToken() })
  async function refresh() {
    loading.value = true; error.value = ''
    try {
      const response = await $fetch<{ data: CartPayload }>(endpoint, { credentials: 'include' })
      state.value = response.data
    } catch (cause: any) { error.value = cause?.data?.message || 'Unable to load your cart.'; throw cause }
    finally { loading.value = false }
  }
  async function add(variantId: number, quantity = 1) {
    error.value = ''
    try {
      const response = await $fetch<{ data: CartPayload }>(`${endpoint}/items`, { method: 'POST', body: { product_variant_id: variantId, quantity }, credentials: 'include', headers: await headers() })
      state.value = response.data
    } catch (cause: any) { error.value = cause?.data?.message || 'Unable to add this product.'; throw cause }
  }
  async function update(variantId: number, quantity: number) {
    const response = await $fetch<{ data: CartPayload }>(`${endpoint}/items/${variantId}`, { method: 'PATCH', body: { quantity }, credentials: 'include', headers: await headers() })
    state.value = response.data
  }
  async function remove(variantId: number) {
    const response = await $fetch<{ data: CartPayload }>(`${endpoint}/items/${variantId}`, { method: 'DELETE', credentials: 'include', headers: await headers() })
    state.value = response.data
  }
  return { cart: state, loading, error, itemCount: computed(() => state.value.item_count), subtotal: computed(() => state.value.subtotal), refresh, add, update, remove }
}
