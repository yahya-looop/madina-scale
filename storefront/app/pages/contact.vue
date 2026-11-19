<script setup lang="ts">
type SupportContact = { phone: string | null; email: string | null; channels: Array<'chat' | 'email' | 'phone'> }
const config = useRuntimeConfig()
const { data: supportResponse, pending, error } = await useFetch<{ data: SupportContact }>(
  `${config.public.apiBase}/support/contact`,
  { key: 'storefront-contact-details', default: () => ({ data: { phone: null, email: null, channels: [] } }) },
)
const support = computed<SupportContact>(() => supportResponse.value?.data || { phone: null, email: null, channels: [] })
const name = ref('')
const email = ref('')
const phone = ref('')
const subject = ref('Product enquiry')
const message = ref('')
const submitMessage = ref('')
const submitting = ref(false)
const phoneDigits = computed(() => {
  const digits = (support.value.phone || '').replace(/\D/g, '')
  if (digits.startsWith('880')) return digits
  if (digits.startsWith('0')) return `88${digits}`
  return digits
})
const whatsappUrl = computed(() => phoneDigits.value ? `https://wa.me/${phoneDigits.value}` : '')
const canEmail = computed(() => Boolean(support.value.email && support.value.channels.includes('email')))
const canChat = computed(() => Boolean(whatsappUrl.value && support.value.channels.includes('chat')))
const canCall = computed(() => Boolean(support.value.phone && support.value.channels.includes('phone')))

async function submitEnquiry() {
  submitMessage.value = ''
  const cleanName = name.value.trim()
  const cleanMessage = message.value.trim()
  if (!cleanName || !cleanMessage) {
    submitMessage.value = 'Please enter your name and message.'
    return
  }
  const body = [
    `Name: ${cleanName}`,
    `Email: ${email.value.trim() || 'Not provided'}`,
    `Phone: ${phone.value.trim() || 'Not provided'}`,
    '',
    cleanMessage,
  ].join('\n')
  const encodedSubject = encodeURIComponent(subject.value.trim() || 'MADINA website enquiry')
  submitting.value = true
  try {
    if (canEmail.value) {
      window.location.href = `mailto:${support.value.email}?subject=${encodedSubject}&body=${encodeURIComponent(body)}`
      submitMessage.value = 'Your email app has been opened with the enquiry prepared. Please send it to complete your request.'
    } else if (canChat.value) {
      window.open(`${whatsappUrl.value}?text=${encodeURIComponent(`${subject.value.trim()}\n\n${body}`)}`, '_blank', 'noopener,noreferrer')
      submitMessage.value = 'WhatsApp has been opened with your enquiry prepared. Please send it to complete your request.'
    } else if (canCall.value) {
      submitMessage.value = 'Please call the support number shown on this page to share your enquiry.'
    } else {
      submitMessage.value = 'Online message submission is not configured yet. Please use the Live Chat button to contact MADINA.'
    }
  } finally {
    submitting.value = false
  }
}

useSeoMeta({
  title: 'Contact MADINA Scale | Customer Support',
  description: 'Contact MADINA Scale for product enquiries, order assistance and after-sales support.',
  ogTitle: 'Contact MADINA Scale',
  ogDescription: 'Get in touch with the MADINA Scale team.',
})
</script>

<template>
  <main class="wc-page contact-page">
    <section class="wc-hero contact-hero">
      <div class="wc-container wc-hero__inner">
        <div class="wc-hero__copy">
          <p class="wc-eyebrow"><UiIcon name="message-circle" :size="16" /> WE’RE HERE TO HELP</p>
          <h1>Contact MADINA</h1>
          <p>Have a product question or need help with an order? Tell us what you need and we’ll help you find the right next step.</p>
          <div class="wc-hero__actions">
            <a v-if="canCall" class="wc-button wc-button--light" :href="`tel:${support.phone}`"><UiIcon name="phone" :size="16" /> Call support</a>
            <a v-else-if="canChat" class="wc-button wc-button--light" :href="whatsappUrl" target="_blank" rel="noopener noreferrer"><UiIcon name="message-circle" :size="16" /> WhatsApp support</a>
            <NuxtLink class="wc-button wc-button--outline" to="/track-order">Track an order</NuxtLink>
          </div>
        </div>
        <div class="wc-hero__visual" aria-hidden="true">
          <span class="wc-hero__orbit wc-hero__orbit--one" />
          <span class="wc-hero__orbit wc-hero__orbit--two" />
          <div class="wc-hero__shield"><UiIcon name="headset" :size="70" /></div>
        </div>
      </div>
    </section>

    <section class="wc-container contact-layout">
      <div class="contact-info">
        <div class="wc-section-heading">
          <p class="wc-eyebrow">CONTACT OPTIONS</p>
          <h2>Choose how to reach us</h2>
          <p>Contact channels are shown when they are configured in MADINA’s public support settings.</p>
        </div>
        <div v-if="pending" class="contact-loading" role="status">Loading contact details…</div>
        <div v-else-if="error" class="contact-loading contact-loading--error">Contact details could not be loaded. You can still reach us through Live Chat.</div>
        <div v-else class="contact-methods">
          <a v-if="canCall" class="contact-method" :href="`tel:${support.phone}`">
            <span class="contact-method__icon"><UiIcon name="phone" :size="22" /></span>
            <span><small>PHONE SUPPORT</small><strong>{{ support.phone }}</strong><em>Tap to call</em></span>
            <UiIcon name="arrow-up-right" :size="18" />
          </a>
          <a v-if="canEmail" class="contact-method" :href="`mailto:${support.email}`">
            <span class="contact-method__icon"><UiIcon name="mail" :size="22" /></span>
            <span><small>EMAIL SUPPORT</small><strong>{{ support.email }}</strong><em>Send an email</em></span>
            <UiIcon name="arrow-up-right" :size="18" />
          </a>
          <a v-if="canChat" class="contact-method" :href="whatsappUrl" target="_blank" rel="noopener noreferrer">
            <span class="contact-method__icon"><UiIcon name="message-circle" :size="22" /></span>
            <span><small>CHAT SUPPORT</small><strong>WhatsApp</strong><em>Start a conversation</em></span>
            <UiIcon name="arrow-up-right" :size="18" />
          </a>
          <div v-if="!canCall && !canEmail && !canChat" class="contact-method contact-method--empty">
            <span class="contact-method__icon"><UiIcon name="headset" :size="22" /></span>
            <span><small>LIVE CUSTOMER SUPPORT</small><strong>Use the Live Chat button</strong><em>Our online support entry point</em></span>
          </div>
        </div>
        <div class="contact-help-note">
          <UiIcon name="shield-check" :size="20" />
          <p>For warranty or service requests, include your product model, purchase date and invoice or order reference where available.</p>
        </div>
      </div>

      <section class="contact-form-card" aria-labelledby="contact-form-title">
        <p class="wc-eyebrow">SEND AN ENQUIRY</p>
        <h2 id="contact-form-title">How can we help?</h2>
        <p class="contact-form-intro">Complete the details below. Your email or chat app will open with the message ready for you to send.</p>
        <form class="contact-form" @submit.prevent="submitEnquiry">
          <label>Full name <input v-model="name" type="text" autocomplete="name" maxlength="120" required placeholder="Your name"></label>
          <div class="contact-form__row">
            <label>Email address <input v-model="email" type="email" autocomplete="email" maxlength="180" placeholder="you@example.com"></label>
            <label>Phone number <input v-model="phone" type="tel" autocomplete="tel" maxlength="40" placeholder="Your phone number"></label>
          </div>
          <label>Subject
            <select v-model="subject">
              <option>Product enquiry</option>
              <option>Order assistance</option>
              <option>Warranty and service</option>
              <option>Pricing and availability</option>
              <option>Other</option>
            </select>
          </label>
          <label>Message <textarea v-model="message" rows="5" maxlength="4000" required placeholder="Tell us how we can help…"></textarea></label>
          <button class="wc-button wc-button--solid contact-submit" type="submit" :disabled="submitting">
            Prepare enquiry <UiIcon name="send" :size="16" />
          </button>
          <p v-if="submitMessage" class="contact-submit-message" role="status">{{ submitMessage }}</p>
          <p class="contact-privacy-note">Your message is not stored by this form. It is prepared in your configured email or chat application for you to send.</p>
        </form>
      </section>
    </section>
  </main>
</template>

<style scoped>
.wc-page{--wc-green:#087d3b;--wc-dark:#06452b;--wc-ink:#142033;--wc-muted:#64748b;--wc-line:#e2ebe5;color:var(--wc-ink);background:#fff}
.wc-container{width:min(1440px,calc(100% - 48px));margin-inline:auto}
.wc-hero{overflow:hidden;background:linear-gradient(112deg,#06472d 0%,#087d4b 58%,#159565 100%);color:#fff}
.wc-hero__inner{min-height:330px;display:grid;grid-template-columns:1.25fr .75fr;align-items:center;gap:40px;padding-block:50px}
.wc-hero__copy{max-width:720px}.wc-eyebrow{display:flex;align-items:center;gap:9px;margin:0 0 14px;color:#078044;font-size:12px;font-weight:900;letter-spacing:.14em}
.wc-hero .wc-eyebrow{color:#b8f1d2}.wc-hero h1{margin:0;font-size:clamp(38px,5vw,62px);line-height:1.04;letter-spacing:-.045em;font-weight:900}
.wc-hero__copy>p:not(.wc-eyebrow){max-width:660px;margin:18px 0 0;color:#e3f5eb;font-size:17px;line-height:1.75}
.wc-hero__actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}.wc-button{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:46px;padding:0 19px;border-radius:999px;font-weight:800;text-decoration:none;transition:transform .2s,background .2s}.wc-button:hover{transform:translateY(-2px)}
.wc-button--light{background:#fff;color:#075b36}.wc-button--outline{border:1px solid #ffffff80;color:#fff;background:#ffffff12}.wc-button--solid{background:var(--wc-green);color:#fff;border:0;cursor:pointer}.wc-button:disabled{opacity:.6;cursor:not-allowed}
.wc-hero__visual{position:relative;display:grid;place-items:center;min-height:240px}.wc-hero__orbit{position:absolute;border:1px solid #ffffff32;border-radius:50%;aspect-ratio:1}.wc-hero__orbit--one{width:230px}.wc-hero__orbit--two{width:315px}.wc-hero__shield{width:126px;height:126px;display:grid;place-items:center;border:1px solid #ffffff55;border-radius:34px;background:#ffffff20;box-shadow:0 24px 60px #003c2850;transform:rotate(-8deg)}
.contact-layout{display:grid;grid-template-columns:.9fr 1.1fr;gap:44px;padding-block:64px;align-items:start}.wc-section-heading{max-width:620px;margin-bottom:25px}.wc-section-heading h2,.contact-form-card h2{margin:0;color:var(--wc-ink);font-size:clamp(25px,3vw,34px);line-height:1.15;font-weight:900;letter-spacing:-.03em}
.wc-section-heading>p:last-child,.contact-form-intro{margin:12px 0 0;color:var(--wc-muted);font-size:14px;line-height:1.75}
.contact-methods{display:grid;gap:12px}.contact-method{display:flex;align-items:center;gap:14px;padding:18px;border:1px solid var(--wc-line);border-radius:16px;background:#fff;color:var(--wc-ink);text-decoration:none;transition:border-color .2s,transform .2s,box-shadow .2s}
.contact-method:hover{border-color:#a9d5b9;transform:translateY(-2px);box-shadow:0 12px 28px #0c39200b}.contact-method>span:nth-child(2){display:grid;gap:3px;flex:1;min-width:0}.contact-method small{font-size:10px;letter-spacing:.12em;font-weight:900;color:var(--wc-green)}.contact-method strong{font-size:15px;overflow-wrap:anywhere}.contact-method em{font-size:12px;color:var(--wc-muted);font-style:normal}
.contact-method__icon{width:44px;height:44px;flex:0 0 auto;display:grid;place-items:center;border-radius:13px;background:#e9f6ee;color:var(--wc-green)}.contact-method--empty{background:#f8fbf9}
.contact-loading{padding:18px;border:1px solid var(--wc-line);border-radius:14px;color:var(--wc-muted);font-size:14px}.contact-loading--error{color:#9b3a30}
.contact-help-note{display:flex;gap:12px;margin-top:18px;padding:17px;border-radius:14px;background:#f3faf5;color:var(--wc-green)}.contact-help-note svg{flex:0 0 auto}.contact-help-note p{margin:0;color:#496456;font-size:13px;line-height:1.65}
.contact-form-card{padding:30px;border:1px solid var(--wc-line);border-radius:22px;background:#fff;box-shadow:0 18px 50px #0c39200b}.contact-form-card h2{font-size:27px}.contact-form-intro{margin-bottom:23px}
.contact-form{display:grid;gap:16px}.contact-form label{display:grid;gap:7px;color:#334155;font-size:13px;font-weight:750}.contact-form input,.contact-form select,.contact-form textarea{width:100%;min-height:46px;padding:11px 13px;border:1px solid #dce5df;border-radius:10px;background:#fff;color:var(--wc-ink);font:inherit;font-size:14px;outline:none;transition:border-color .2s,box-shadow .2s}
.contact-form textarea{min-height:130px;resize:vertical}.contact-form input:focus,.contact-form select:focus,.contact-form textarea:focus{border-color:#15965a;box-shadow:0 0 0 3px #15965a18}
.contact-form__row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.contact-submit{justify-self:start;min-width:190px}.contact-submit-message{margin:0;padding:12px 14px;border-radius:10px;background:#eff9f2;color:#17633b;font-size:13px;line-height:1.6}.contact-privacy-note{margin:0;color:#83909e;font-size:11px;line-height:1.6}
@media(max-width:900px){.contact-layout{grid-template-columns:1fr;gap:34px}.wc-hero__inner{grid-template-columns:1fr .5fr}.wc-hero__orbit--two{width:260px}}
@media(max-width:640px){.wc-container{width:min(100% - 32px,1440px)}.wc-hero__inner{grid-template-columns:1fr;padding-block:42px}.wc-hero__visual{display:none}.wc-hero h1{font-size:42px}.contact-layout{padding-block:42px}.contact-form-card{padding:22px}.contact-form__row{grid-template-columns:1fr}}
</style>