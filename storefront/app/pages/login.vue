<script setup lang="ts">
const config = useRuntimeConfig()
const identity = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const success = ref('')

async function signIn() {
  error.value = ''
  success.value = ''
  loading.value = true
  try {
    const csrf = await $fetch<{ token: string }>(`${config.public.apiBase}/csrf-token`, { credentials: 'include' })
    await $fetch(`${config.public.apiBase}/auth/login/password`, {
      method: 'POST',
      body: { identity: identity.value.trim(), password: password.value, remember: true },
      credentials: 'include',
      headers: { 'X-CSRF-TOKEN': csrf.token },
    })
    success.value = 'You are signed in successfully.'
    await navigateTo('/')
  } catch (cause: any) {
    error.value = cause?.data?.errors?.identity?.[0] || cause?.data?.message || 'Unable to sign in. Please check your details and try again.'
  } finally {
    loading.value = false
  }
}

useSeoMeta({ title: 'Customer Login | MADINA Scale', robots: 'noindex, nofollow' })
</script>

<template>
  <main class="customer-login-page container">
    <section class="customer-login-card">
      <div class="customer-login-card__mark"><UiIcon name="user-round" :size="27" /></div>
      <p class="section-kicker">MADINA CUSTOMER ACCOUNT</p>
      <h1>Welcome back</h1>
      <p class="customer-login-card__intro">Sign in with the phone number or email address linked to your MADINA account.</p>
      <form class="customer-login-form" @submit.prevent="signIn">
        <label><span>Phone number or email</span><input v-model="identity" type="text" autocomplete="username" required maxlength="190" placeholder="Enter phone number or email"></label>
        <label><span>Password</span><input v-model="password" type="password" autocomplete="current-password" required maxlength="200" placeholder="Enter your password"></label>
        <p v-if="error" class="customer-login-form__error" role="alert">{{ error }}</p>
        <p v-if="success" class="customer-login-form__success" role="status">{{ success }}</p>
        <button type="submit" :disabled="loading">{{ loading ? 'Signing in…' : 'Sign in' }} <UiIcon name="arrow-right" :size="17" /></button>
      </form>
      <p class="customer-login-card__foot">Need help accessing your account? <NuxtLink to="/pages/contact">Contact MADINA support</NuxtLink>.</p>
    </section>
  </main>
</template>
