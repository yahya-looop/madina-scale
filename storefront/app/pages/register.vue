<script setup lang="ts">
const config = useRuntimeConfig()
const { setCustomer } = useStorefrontCustomer()
const name = ref('')
const phone = ref('')
const email = ref('')
const code = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const sendingCode = ref(false)
const registering = ref(false)
const codeSent = ref(false)
const error = ref('')
const notice = ref('')

async function csrfToken() {
  const response = await $fetch<{ token: string }>(`${config.public.apiBase}/csrf-token`, {
    credentials: 'include',
  })
  return response.token
}

async function sendCode() {
  error.value = ''
  notice.value = ''
  sendingCode.value = true
  try {
    const csrf = await csrfToken()
    const response = await $fetch<{ message?: string }>(`${config.public.apiBase}/auth/register/otp`, {
      method: 'POST',
      body: { phone: phone.value.trim() },
      credentials: 'include',
      headers: { 'X-CSRF-TOKEN': csrf },
    })
    codeSent.value = true
    notice.value = response.message || 'Verification code sent. Please check your phone.'
  } catch (cause: any) {
    error.value = cause?.data?.errors?.phone?.[0] || cause?.data?.message || 'Unable to send a verification code. Please try again.'
  } finally {
    sendingCode.value = false
  }
}

async function register() {
  error.value = ''
  notice.value = ''
  registering.value = true
  try {
    const csrf = await csrfToken()
    const response = await $fetch<{ data: { customer: import('~/composables/useStorefrontCustomer').StorefrontCustomer } }>(`${config.public.apiBase}/auth/register`, {
      method: 'POST',
      body: {
        name: name.value.trim(),
        phone: phone.value.trim(),
        email: email.value.trim() || null,
        code: code.value.trim(),
        password: password.value,
        password_confirmation: passwordConfirmation.value,
      },
      credentials: 'include',
      headers: { 'X-CSRF-TOKEN': csrf },
    })
    setCustomer(response.data.customer)
    await navigateTo('/account')
  } catch (cause: any) {
    const errors = cause?.data?.errors
    error.value = errors?.code?.[0] || errors?.phone?.[0] || errors?.email?.[0] || errors?.password?.[0] || errors?.name?.[0] || cause?.data?.message || 'Unable to create your account. Please check the details and try again.'
  } finally {
    registering.value = false
  }
}

useSeoMeta({ title: 'Create Customer Account | MADINA Scale', robots: 'noindex, nofollow' })
</script>

<template>
  <main class="customer-login-page container">
    <section class="customer-login-card">
      <div class="customer-login-card__mark"><UiIcon name="user-round" :size="27" /></div>
      <p class="section-kicker">MADINA CUSTOMER ACCOUNT</p>
      <h1>Create your account</h1>
      <p class="customer-login-card__intro">Create a MADINA account with your mobile number and verify it with a one-time code.</p>

      <form class="customer-login-form" @submit.prevent="register">
        <label><span>Full name</span><input v-model="name" type="text" autocomplete="name" required maxlength="160" placeholder="Enter your full name"></label>
        <label><span>Mobile number</span><input v-model="phone" type="tel" autocomplete="tel" required maxlength="40" placeholder="01XXXXXXXXX" :disabled="codeSent"></label>
        <button v-if="!codeSent" type="button" :disabled="sendingCode || phone.trim().length < 10" @click="sendCode">
          {{ sendingCode ? 'Sending code…' : 'Send verification code' }}
        </button>
        <template v-else>
          <label><span>Verification code</span><input v-model="code" type="text" inputmode="numeric" autocomplete="one-time-code" required minlength="6" maxlength="6" placeholder="Enter 6-digit code"></label>
          <p class="customer-login-card__foot">Code sent to {{ phone }}. <button class="customer-register-resend" type="button" :disabled="sendingCode" @click="sendCode">{{ sendingCode ? 'Sending…' : 'Send again' }}</button></p>
        </template>
        <label><span>Email address (optional)</span><input v-model="email" type="email" autocomplete="email" maxlength="190" placeholder="you@example.com"></label>
        <label><span>Password</span><input v-model="password" type="password" autocomplete="new-password" required minlength="8" maxlength="200" placeholder="At least 8 characters"></label>
        <label><span>Confirm password</span><input v-model="passwordConfirmation" type="password" autocomplete="new-password" required minlength="8" maxlength="200" placeholder="Enter password again"></label>
        <p v-if="error" class="customer-login-form__error" role="alert">{{ error }}</p>
        <p v-if="notice" class="customer-login-form__success" role="status">{{ notice }}</p>
        <button v-if="codeSent" type="submit" :disabled="registering || code.length !== 6">
          {{ registering ? 'Creating account…' : 'Create account' }} <UiIcon name="arrow-right" :size="17" />
        </button>
      </form>
      <p class="customer-login-card__foot">Already have an account? <NuxtLink to="/login">Sign in</NuxtLink>.</p>
    </section>
  </main>
</template>
