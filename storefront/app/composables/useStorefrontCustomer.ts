import { computed } from 'vue'

export type StorefrontCustomer = {
  id: number
  name: string
  phone: string
  email: string | null
  phone_verified_at?: string | null
}

type CustomerResponse = { data: StorefrontCustomer }

export function useStorefrontCustomer() {
  const config = useRuntimeConfig()
  const customer = useState<StorefrontCustomer | null>('storefront-customer', () => null)
  const loaded = useState<boolean>('storefront-customer-loaded', () => false)
  const firstName = computed(() => customer.value?.name?.trim().split(/\s+/)[0] || '')

  async function refreshCustomer() {
    try {
      const response = await $fetch<CustomerResponse>(`${config.public.apiBase}/auth/me`, {
        credentials: 'include',
      })
      customer.value = response.data
    } catch (cause: any) {
      if (cause?.statusCode === 401 || cause?.status === 401 || cause?.response?.status === 401) {
        customer.value = null
      } else {
        throw cause
      }
    } finally {
      loaded.value = true
    }
    return customer.value
  }

  function setCustomer(value: StorefrontCustomer | null) {
    customer.value = value
    loaded.value = true
  }

  async function signOut() {
    const csrf = await $fetch<{ token: string }>(`${config.public.apiBase}/csrf-token`, {
      credentials: 'include',
    })
    await $fetch(`${config.public.apiBase}/auth/logout`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-CSRF-TOKEN': csrf.token },
    })
    setCustomer(null)
  }

  return { customer, firstName, loaded, refreshCustomer, setCustomer, signOut }
}
