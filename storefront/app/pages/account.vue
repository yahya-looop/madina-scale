<script setup lang="ts">
type OrderSummary = {
  id: number
  order_number: string
  status: string
  grand_total: number | string
  created_at: string
}

const config = useRuntimeConfig()
const { customer, refreshCustomer, signOut } = useStorefrontCustomer()
const orders = ref<OrderSummary[]>([])
const loading = ref(true)
const error = ref('')
const signingOut = ref(false)

onMounted(async () => {
  try {
    const current = await refreshCustomer()
    if (!current) {
      await navigateTo('/login')
      return
    }
    const response = await $fetch<{ data: { customer: typeof current; orders: OrderSummary[] } }>(`${config.public.apiBase}/account`, {
      credentials: 'include',
    })
    orders.value = response.data.orders || []
  } catch (cause: any) {
    error.value = cause?.data?.message || 'Unable to load your account details.'
  } finally {
    loading.value = false
  }
})

async function logout() {
  signingOut.value = true
  error.value = ''
  try {
    await signOut()
    await navigateTo('/')
  } catch (cause: any) {
    error.value = cause?.data?.message || 'Unable to sign out. Please try again.'
  } finally {
    signingOut.value = false
  }
}

useSeoMeta({ title: 'My Account | MADINA Scale', robots: 'noindex, nofollow' })
</script>

<template>
  <main class="customer-login-page container">
    <section class="customer-login-card customer-account-card">
      <p class="section-kicker">MADINA CUSTOMER ACCOUNT</p>
      <h1>My account</h1>
      <p v-if="loading" class="customer-login-card__intro">Loading your account…</p>
      <template v-else-if="customer">
        <p class="customer-login-card__intro">Welcome, {{ customer.name }}.</p>
        <div class="customer-account-details">
          <p><strong>Name</strong><span>{{ customer.name }}</span></p>
          <p><strong>Mobile</strong><span>{{ customer.phone }}</span></p>
          <p><strong>Email</strong><span>{{ customer.email || 'Not provided' }}</span></p>
        </div>
        <div class="customer-account-orders">
          <h2>Recent orders</h2>
          <p v-if="!orders.length" class="customer-login-card__intro">Your recent orders will appear here.</p>
          <div v-for="order in orders" :key="order.id" class="customer-account-order">
            <div><strong>{{ order.order_number }}</strong><small>{{ new Date(order.created_at).toLocaleDateString() }}</small></div>
            <div><strong>৳{{ Number(order.grand_total).toLocaleString('en-BD') }}</strong><small>{{ order.status }}</small></div>
          </div>
        </div>
        <button class="customer-account-logout" type="button" :disabled="signingOut" @click="logout">{{ signingOut ? 'Signing out…' : 'Sign out' }}</button>
      </template>
      <p v-if="error" class="customer-login-form__error" role="alert">{{ error }}</p>
    </section>
  </main>
</template>
