<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue'
import { useStorefrontCustomer } from '~/composables/useStorefrontCustomer'

type ChatMessage = { id:number; sender_type:string; sender_name:string; message:string; created_at:string }
const config = useRuntimeConfig()
const isOpen = ref(false)
const loading = ref(false)
const sending = ref(false)
const error = ref('')
const conversationId = ref<number|null>(null)
const messages = ref<ChatMessage[]>([])
const draft = ref('')
const { customer } = useStorefrontCustomer()
const { t } = useStorefrontLocale()
let poller: ReturnType<typeof setInterval>|null = null
const lastId = computed(() => messages.value.reduce((max, item) => Math.max(max,item.id),0))
const endpoint = (path:string) => `${config.public.apiBase}/support-chat/${path}`
async function csrf() {
  const result = await $fetch<{token:string}>(`${config.public.apiBase}/csrf-token`, { credentials:'include' })
  return result.token
}
async function start() {
  loading.value=true; error.value=''
  try {
    const token=await csrf()
    const response=await $fetch<{data:{conversation_id:number;messages:ChatMessage[]}}>(endpoint('start'), {
      method:'POST', credentials:'include', headers:{'X-CSRF-TOKEN':token},
    })
    conversationId.value=response.data.conversation_id
    messages.value=response.data.messages
    if(poller) clearInterval(poller)
    poller=setInterval(()=>void poll(),3500)
  } catch { error.value=t('Unable to start chat. Please try again.','চ্যাট শুরু করা যাচ্ছে না। আবার চেষ্টা করুন।') }
  finally { loading.value=false }
}
async function poll() {
  if(!conversationId.value) return
  try {
    const response=await $fetch<{data:ChatMessage[]}>(endpoint(`messages?conversation_id=${conversationId.value}&after=${lastId.value}`),{credentials:'include'})
    if(response.data.length) messages.value.push(...response.data)
  } catch { /* retry on the next polling interval */ }
}
async function send() {
  if(!conversationId.value||!draft.value.trim()||sending.value) return
  sending.value=true; error.value=''
  try {
    const token=await csrf()
    const response=await $fetch<{data:ChatMessage}>(endpoint('messages'),{
      method:'POST',credentials:'include',headers:{'X-CSRF-TOKEN':token},
      body:{conversation_id:conversationId.value,message:draft.value.trim()},
    })
    messages.value.push(response.data); draft.value=''
  } catch { error.value=t('Message could not be sent. Please retry.','মেসেজ পাঠানো যায়নি। আবার চেষ্টা করুন।') }
  finally { sending.value=false }
}
async function toggle() {
  isOpen.value=!isOpen.value
  if(isOpen.value&&!conversationId.value&&!loading.value) await start()
}
onUnmounted(()=>{if(poller) clearInterval(poller)})
</script>

<template>
  <div class="chat-launcher">
    <section v-if="isOpen" class="chat-panel" aria-label="MADINA customer live chat">
      <header class="chat-panel__header">
        <span class="chat-panel__avatar"><UiIcon name="headset" :size="22" /></span>
        <div class="min-w-0 flex-1"><strong>{{ t('MADINA Support', 'MADINA সাপোর্ট') }}</strong><small>{{ customer?.name || t('We usually reply as soon as possible', 'আমরা যত দ্রুত সম্ভব উত্তর দিই') }}</small></div>
        <button type="button" :aria-label="t('Close chat','চ্যাট বন্ধ করুন')" @click="isOpen=false"><UiIcon name="x" :size="18" /></button>
      </header>
      <div class="chat-panel__body">
        <div v-if="loading" class="chat-panel__note">{{ t('Connecting securely…','নিরাপদ সংযোগ তৈরি হচ্ছে…') }}</div>
        <template v-else>
          <div class="chat-messages" aria-live="polite">
            <div v-for="message in messages" :key="message.id" class="chat-message" :class="message.sender_type==='customer'||message.sender_type==='guest'?'chat-message--mine':''">
              <small>{{ message.sender_name }}</small><p>{{ message.message }}</p><time>{{ new Date(message.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) }}</time>
            </div>
          </div>
          <p v-if="error" class="chat-panel__note chat-error">{{ error }}</p>
          <form class="chat-compose" @submit.prevent="send">
            <textarea v-model="draft" rows="2" maxlength="3000" :placeholder="t('Write your message…','আপনার মেসেজ লিখুন…')" @keydown.enter.exact.prevent="send" />
            <button type="submit" :disabled="sending||!draft.trim()" :aria-label="t('Send message','মেসেজ পাঠান')"><UiIcon name="send" :size="18" /></button>
          </form>
          <p class="chat-security-note">{{ t('Your conversation is protected with encrypted message storage.','আপনার কথোপকথন এনক্রিপ্টেডভাবে সংরক্ষিত।') }}</p>
        </template>
      </div>
      <footer class="chat-panel__footer">{{ t('MADINA Weighing Solutions · Live Support', 'MADINA Weighing Solutions · লাইভ সাপোর্ট') }}</footer>
    </section>
    <button type="button" class="chat-launcher__button" :aria-expanded="isOpen" @click="toggle">
      <UiIcon :name="isOpen ? 'x' : 'message-circle'" :size="21" />
      <span>{{ isOpen ? t('Close', 'বন্ধ') : t('Live Chat', 'লাইভ চ্যাট') }}</span>
    </button>
  </div>
</template>

<style scoped>
.chat-messages{display:flex;flex-direction:column;gap:10px;max-height:300px;overflow:auto;padding:4px 0 12px}
.chat-message{align-self:flex-start;max-width:88%;padding:10px 12px;border-radius:14px 14px 14px 4px;background:#f1f5f9;color:#172b25}
.chat-message--mine{align-self:flex-end;background:#e1f3e9;border-radius:14px 14px 4px 14px}
.chat-message small,.chat-message time{display:block;font-size:10px;opacity:.65}
.chat-message p{white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px;margin:4px 0}
.chat-message time{text-align:right}
.chat-compose{display:flex;gap:8px;align-items:end;border:1px solid #dce7e1;border-radius:12px;padding:7px}
.chat-compose textarea{width:100%;resize:none;border:0;outline:0;background:transparent;font:inherit;font-size:13px}
.chat-compose button{flex:none;border-radius:9px;background:#145b43;color:white;padding:9px}
.chat-compose button:disabled{opacity:.4}
.chat-security-note{font-size:10px;color:#82928b;margin-top:8px}
.chat-error{color:#b42318}
</style>
