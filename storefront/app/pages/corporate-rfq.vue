<script setup lang="ts">
const config = useRuntimeConfig()
type RfqLine = { product_variant_id: number | null; description: string; sku: string; quantity: number; unit: string; specifications: string }
const companyName = ref('')
const contactName = ref('')
const email = ref('')
const phone = ref('')
const requiredBy = ref('')
const deliveryLocation = ref('')
const requirements = ref('')
const items = ref<RfqLine[]>([{ product_variant_id: null, description: '', sku: '', quantity: 1, unit: 'pcs', specifications: '' }])
const submitting = ref(false)
const message = ref('')
const error = ref('')
function addLine() { items.value.push({ product_variant_id: null, description: '', sku: '', quantity: 1, unit: 'pcs', specifications: '' }) }
function removeLine(index: number) { if (items.value.length > 1) items.value.splice(index, 1) }
async function submitRfq() {
  error.value = ''; message.value = ''
  if (!companyName.value.trim() || !contactName.value.trim() || !phone.value.trim() || items.value.some(x => !x.description.trim() || Number(x.quantity) <= 0)) {
    error.value = 'Please complete company, contact, phone and every product line.'
    return
  }
  submitting.value = true
  try {
    const csrf = await $fetch<{ token: string }>(`${config.public.apiBase}/csrf-token`, { credentials: 'include' })
    const response = await $fetch<{ message: string; data: { rfq_number: string } }>(`${config.public.apiBase}/b2b/rfqs`, {
      method: 'POST', credentials: 'include', headers: { 'X-CSRF-TOKEN': csrf.token },
      body: {
        company_name: companyName.value.trim(), contact_name: contactName.value.trim(),
        email: email.value.trim() || null, phone: phone.value.trim(),
        required_by: requiredBy.value || null, delivery_location: deliveryLocation.value.trim() || null,
        requirements: requirements.value.trim() || null,
        items: items.value.map(x => ({
          product_variant_id: x.product_variant_id, description: x.description.trim(),
          sku: x.sku.trim() || null, quantity: Number(x.quantity), unit: x.unit.trim() || null,
          specifications: x.specifications.trim() || null,
        })),
      },
    })
    message.value = `Your RFQ ${response.data.rfq_number} has been submitted. Our corporate sales team will review it.`
    companyName.value = ''; contactName.value = ''; email.value = ''; phone.value = ''
    requiredBy.value = ''; deliveryLocation.value = ''; requirements.value = ''
    items.value = [{ product_variant_id: null, description: '', sku: '', quantity: 1, unit: 'pcs', specifications: '' }]
  } catch (e: any) {
    error.value = e?.data?.message || Object.values(e?.data?.errors || {}).flat()[0] as string || 'We could not submit your RFQ. Please try again.'
  } finally { submitting.value = false }
}
useSeoMeta({ title: 'Corporate RFQ | MADINA Scale', description: 'Request a formal quotation for bulk and corporate purchases from MADINA Scale.' })
</script>

<template>
  <main class="wc-page">
    <section class="wc-hero">
      <div class="wc-container wc-hero__inner">
        <div class="wc-hero__copy">
          <p class="wc-eyebrow">CORPORATE & BULK PROCUREMENT</p>
          <h1>Request a quotation</h1>
          <p>Share your company details and product requirements. Our corporate sales team will review your RFQ and prepare a quotation based on availability and commercial terms.</p>
        </div>
      </div>
    </section>
    <section class="wc-container" style="max-width:900px;padding-top:3rem;padding-bottom:4rem">
      <form class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-8" @submit.prevent="submitRfq">
        <div v-if="message" class="rounded-xl bg-emerald-50 p-4 text-emerald-800" role="status">{{ message }}</div>
        <div v-if="error" class="rounded-xl bg-red-50 p-4 text-red-700" role="alert">{{ error }}</div>
        <div class="grid gap-4 md:grid-cols-2">
          <label class="block">Company name *<input v-model="companyName" required maxlength="180" class="mt-1 w-full rounded-lg border p-3" /></label>
          <label class="block">Contact person *<input v-model="contactName" required maxlength="160" class="mt-1 w-full rounded-lg border p-3" /></label>
          <label class="block">Business email<input v-model="email" type="email" maxlength="190" class="mt-1 w-full rounded-lg border p-3" /></label>
          <label class="block">Phone number *<input v-model="phone" required maxlength="40" class="mt-1 w-full rounded-lg border p-3" /></label>
          <label class="block">Required by<input v-model="requiredBy" type="date" class="mt-1 w-full rounded-lg border p-3" /></label>
          <label class="block">Delivery location<input v-model="deliveryLocation" maxlength="500" class="mt-1 w-full rounded-lg border p-3" placeholder="Area, district, address" /></label>
        </div>
        <div class="border-t pt-5">
          <div class="mb-4 flex items-center justify-between"><h2 class="text-xl font-semibold">Requested products</h2><button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="addLine">Add product line</button></div>
          <div v-for="(line, index) in items" :key="index" class="mb-4 rounded-xl border p-4">
            <div class="mb-3 flex items-center justify-between"><strong>Product {{ index + 1 }}</strong><button type="button" class="text-sm text-red-600" :disabled="items.length === 1" @click="removeLine(index)">Remove</button></div>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="block md:col-span-2">Product / description *<input v-model="line.description" required maxlength="240" class="mt-1 w-full rounded-lg border p-3" placeholder="Product name or specification" /></label>
              <label class="block">SKU (optional)<input v-model="line.sku" maxlength="100" class="mt-1 w-full rounded-lg border p-3" /></label>
              <label class="block">Quantity *<input v-model.number="line.quantity" type="number" min="0.001" step="0.001" required class="mt-1 w-full rounded-lg border p-3" /></label>
              <label class="block">Unit<input v-model="line.unit" maxlength="40" class="mt-1 w-full rounded-lg border p-3" placeholder="pcs, set, carton" /></label>
              <label class="block md:col-span-3">Specifications<textarea v-model="line.specifications" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border p-3" /></label>
            </div>
          </div>
        </div>
        <label class="block">Additional requirements<textarea v-model="requirements" rows="4" maxlength="5000" class="mt-1 w-full rounded-lg border p-3" placeholder="Usage, delivery schedule, compliance or other requirements…" /></label>
        <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-slate-500">Submitting an RFQ does not confirm price, stock or an order.</p><button class="rounded-xl bg-emerald-800 px-6 py-3 font-semibold text-white disabled:opacity-60" :disabled="submitting">{{ submitting ? 'Submitting…' : 'Submit RFQ' }}</button></div>
      </form>
    </section>
  </main>
</template>
