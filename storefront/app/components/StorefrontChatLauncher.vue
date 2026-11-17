<script setup lang="ts">
type SupportContact = { phone: string | null; email: string | null; channels: string[] }

const config = useRuntimeConfig()
const isOpen = ref(false)
const contact = ref<SupportContact | null>(null)
const loading = ref(false)
const loaded = ref(false)
const { t } = useStorefrontLocale()

const phoneDigits = computed(() => (contact.value?.phone || '').replace(/\D/g, ''))
const canChat = computed(() => Boolean(contact.value?.channels.includes('chat') && phoneDigits.value))

async function toggleChat() {
  isOpen.value = !isOpen.value
  if (!isOpen.value || loaded.value || loading.value) return

  loading.value = true
  try {
    const response = await $fetch<{ data: SupportContact }>(`${config.public.apiBase}/support/contact`, { credentials: 'include' })
    contact.value = response.data
  } catch {
    contact.value = { phone: null, email: null, channels: [] }
  } finally {
    loading.value = false
    loaded.value = true
  }
}
</script>

<template>
  <div class="chat-launcher">
    <section v-if="isOpen" class="chat-panel" aria-label="MADINA customer support">
      <header class="chat-panel__header">
        <span class="chat-panel__avatar"><UiIcon name="headset" :size="22" /></span>
        <div><strong>{{ t('MADINA Support', 'MADINA সাপোর্ট') }}</strong><small>{{ t('How can we help you?', 'কীভাবে সাহায্য করতে পারি?') }}</small></div>
        <button type="button" :aria-label="t('Close support panel', 'সাপোর্ট বন্ধ করুন')" @click="isOpen = false"><UiIcon name="x" :size="18" /></button>
      </header>
      <div class="chat-panel__body">
        <p class="chat-panel__welcome">{{ t('Hello! Choose a topic and we’ll help you find the right information.', 'স্বাগতম! একটি বিষয় নির্বাচন করুন, আমরা প্রয়োজনীয় তথ্য পেতে সাহায্য করব।') }}</p>
        <NuxtLink to="/track-order" class="chat-panel__quick-link" @click="isOpen = false"><UiIcon name="package-search" :size="17" /> {{ t('Track an order', 'অর্ডার ট্র্যাক করুন') }} <span>→</span></NuxtLink>
        <NuxtLink to="/pages/warranty" class="chat-panel__quick-link" @click="isOpen = false"><UiIcon name="shield-check" :size="17" /> {{ t('Warranty information', 'ওয়ারেন্টি তথ্য') }} <span>→</span></NuxtLink>
        <NuxtLink to="/pages/contact" class="chat-panel__quick-link" @click="isOpen = false"><UiIcon name="message-circle" :size="17" /> {{ t('Contact the team', 'টিমের সাথে যোগাযোগ') }} <span>→</span></NuxtLink>
        <div v-if="loading" class="chat-panel__note">{{ t('Loading support options…', 'সাপোর্টের তথ্য লোড হচ্ছে…') }}</div>
        <template v-else-if="loaded">
          <a v-if="canChat" class="chat-panel__action" :href="`https://wa.me/${phoneDigits}`" target="_blank" rel="noopener noreferrer">
            <UiIcon name="message-circle" :size="17" /> {{ t('Start a chat', 'চ্যাট শুরু করুন') }}
          </a>
          <a v-else-if="contact?.phone && contact.channels.includes('phone')" class="chat-panel__action" :href="`tel:${contact.phone}`">
            <UiIcon name="phone" :size="17" /> {{ t('Call support', 'সাপোর্টে কল করুন') }}
          </a>
          <a v-else-if="contact?.email && contact.channels.includes('email')" class="chat-panel__action" :href="`mailto:${contact.email}`">
            <UiIcon name="mail" :size="17" /> {{ t('Email support', 'ইমেইল করুন') }}
          </a>
          <p v-else class="chat-panel__note">{{ t('Direct chat is not configured yet. Please use the Contact page and our team will assist you.', 'সরাসরি চ্যাট এখনো কনফিগার করা হয়নি। Contact পেজ থেকে আমাদের সাথে যোগাযোগ করুন।') }}</p>
        </template>
      </div>
      <footer class="chat-panel__footer">{{ t('MADINA Weighing Solutions', 'MADINA Weighing Solutions') }}</footer>
    </section>

    <button type="button" class="chat-launcher__button" :aria-expanded="isOpen" @click="toggleChat">
      <UiIcon :name="isOpen ? 'x' : 'message-circle'" :size="21" />
      <span>{{ isOpen ? t('Close', 'বন্ধ') : t('Live Chat', 'লাইভ চ্যাট') }}</span>
    </button>
  </div>
</template>
