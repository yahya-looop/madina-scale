<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface Props { userName?: string; userEmail?: string }
type Operation = 'receive' | 'issue' | 'adjust' | 'transfer';
interface Product { id: number; name: string; sku?: string | null; product_type?: string; status?: string }
interface Variant { id: number; product_id: number; name?: string | null; sku?: string | null; status?: string }
interface Location { id: number; name: string; code?: string | null; is_active?: boolean }
interface ApiPayload<T> { data?: T; message?: string; errors?: Record<string, string[]> }
const props = withDefaults(defineProps<Props>(), { userName: '', userEmail: '' });

const operation = ref<Operation>('receive');
const products = ref<Product[]>([]);
const locations = ref<Location[]>([]);
const variants = ref<Variant[]>([]);
const productId = ref('');
const variantId = ref('');
const itemType = ref<'product' | 'variant'>('product');
const locationId = ref('');
const sourceLocationId = ref('');
const destinationLocationId = ref('');
const quantity = ref('');
const reason = ref('');
const referenceType = ref('');
const referenceId = ref('');
const loadingOptions = ref(true);
const loadingVariants = ref(false);
const submitting = ref(false);
const error = ref('');
const success = ref('');
const fieldErrors = ref<Record<string, string>>({});
const requestId = ref(0);
let lastPayloadFingerprint = '';
let lastIdempotencyKey = '';

const currentProduct = computed(() => products.value.find(p => String(p.id) === productId.value));
const isVariable = computed(() => currentProduct.value?.product_type === 'variable');
const selectedVariant = computed(() => variants.value.find(v => String(v.id) === variantId.value));
const chosenItemLabel = computed(() => itemType.value === 'variant'
    ? `${currentProduct.value?.name ?? 'Product'}${selectedVariant.value?.name ? ` — ${selectedVariant.value.name}` : ''}`
    : currentProduct.value?.name ?? '');
const operationMeta = computed(() => ({
    receive: { title: 'Receive Stock', description: 'Add received inventory to a location.', icon: 'package-plus', endpoint: '/api/inventory/receive', permission: 'system.inventory.receive' },
    issue: { title: 'Issue Stock', description: 'Record inventory leaving a location.', icon: 'package-minus', endpoint: '/api/inventory/issue', permission: 'system.inventory.issue' },
    adjust: { title: 'Adjust Stock', description: 'Apply a signed correction to the stock balance.', icon: 'sliders-horizontal', endpoint: '/api/inventory/adjust', permission: 'system.inventory.adjust' },
    transfer: { title: 'Transfer Stock', description: 'Move inventory atomically between two locations.', icon: 'arrow-left-right', endpoint: '/api/inventory/transfer', permission: 'system.inventory.transfer' },
}[operation.value]));
const quantityHelp = computed(() => operation.value === 'adjust'
    ? 'Use a positive number to increase stock or a negative number to decrease it.'
    : 'Enter a positive quantity. Up to 4 decimal places are supported.');

function numeric(value: string | number | null | undefined): number {
    const n = Number(value ?? 0);
    return Number.isFinite(n) ? n : 0;
}
function qty(value: string | number): string {
    return new Intl.NumberFormat('en-BD', { maximumFractionDigits: 4 }).format(numeric(value));
}
function clearNotices(): void { error.value = ''; success.value = ''; fieldErrors.value = {}; }
function resetForm(): void {
    productId.value = ''; variantId.value = ''; itemType.value = 'product';
    locationId.value = ''; sourceLocationId.value = ''; destinationLocationId.value = '';
    quantity.value = ''; reason.value = ''; referenceType.value = ''; referenceId.value = '';
    variants.value = []; clearNotices();
}
async function loadOptions(): Promise<void> {
    loadingOptions.value = true; error.value = '';
    try {
        const [productResponse, locationResponse] = await Promise.all([
            fetch('/api/products?status=active', { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }),
            fetch('/api/locations?active=1', { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }),
        ]);
        const productPayload = await productResponse.json() as ApiPayload<Product[]>;
        const locationPayload = await locationResponse.json() as ApiPayload<Location[]>;
        if (!productResponse.ok) throw new Error(productPayload.message || `Products request failed (HTTP ${productResponse.status}).`);
        if (!locationResponse.ok) throw new Error(locationPayload.message || `Locations request failed (HTTP ${locationResponse.status}).`);
        products.value = Array.isArray(productPayload.data) ? productPayload.data.filter(p => !p.status || p.status === 'active') : [];
        locations.value = Array.isArray(locationPayload.data) ? locationPayload.data.filter(l => l.is_active !== false) : [];
        if (!products.value.length) error.value = 'No active products are available, or your account cannot view products.';
        else if (!locations.value.length) error.value = 'No active locations are available, or your account cannot view locations.';
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Unable to load products and locations.';
    } finally { loadingOptions.value = false; }
}
async function loadVariants(): Promise<void> {
    variants.value = []; variantId.value = '';
    if (!productId.value || currentProduct.value?.product_type !== 'variable') return;
    loadingVariants.value = true;
    try {
        const response = await fetch(`/api/products/${encodeURIComponent(productId.value)}/variants`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json() as ApiPayload<Variant[]>;
        if (!response.ok) throw new Error(payload.message || `Variants request failed (HTTP ${response.status}).`);
        variants.value = Array.isArray(payload.data) ? payload.data.filter(v => !v.status || v.status === 'active') : [];
        if (!variants.value.length) error.value = 'This variable product has no active variants available.';
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Unable to load product variants.';
    } finally { loadingVariants.value = false; }
}
watch(productId, async () => {
    clearNotices();
    if (currentProduct.value?.product_type === 'variable') {
        itemType.value = 'variant';
        await loadVariants();
    } else {
        itemType.value = 'product'; variantId.value = ''; variants.value = [];
    }
});
watch(operation, () => { clearNotices(); success.value = ''; });
function validateForm(): boolean {
    fieldErrors.value = {};
    if (!productId.value) fieldErrors.value.product_id = 'Select a product.';
    if (isVariable.value && !variantId.value) fieldErrors.value.product_variant_id = 'Select an active variant.';
    if (operation.value === 'transfer') {
        if (!sourceLocationId.value) fieldErrors.value.source_location_id = 'Select a source location.';
        if (!destinationLocationId.value) fieldErrors.value.destination_location_id = 'Select a destination location.';
        if (sourceLocationId.value && sourceLocationId.value === destinationLocationId.value) fieldErrors.value.destination_location_id = 'Source and destination must be different.';
    } else if (!locationId.value) fieldErrors.value.location_id = 'Select a location.';
    const n = Number(quantity.value);
    const validPrecision = /^-?\d{1,14}(?:\.\d{1,4})?$/.test(quantity.value.trim());
    if (!quantity.value.trim() || !Number.isFinite(n) || !validPrecision || n === 0) fieldErrors.value.quantity = 'Enter a non-zero quantity with up to 4 decimal places.';
    else if (operation.value !== 'adjust' && n < 0) fieldErrors.value.quantity = 'Quantity must be greater than zero.';
    if (reason.value.length > 255) fieldErrors.value.reason = 'Reason must be 255 characters or fewer.';
    return Object.keys(fieldErrors.value).length === 0;
}
async function submit(): Promise<void> {
    clearNotices();
    if (!validateForm()) { error.value = 'Please correct the highlighted fields.'; return; }
    const thisRequest = ++requestId.value;
    submitting.value = true;
    const payload: Record<string, unknown> = {
        quantity: quantity.value.trim(),
        reason: reason.value.trim() || null,
        reference_type: referenceType.value.trim() || null,
        reference_id: referenceId.value.trim() || null,
    };
    if (isVariable.value) payload.product_variant_id = Number(variantId.value);
    else payload.product_id = Number(productId.value);
    if (operation.value === 'transfer') {
        payload.source_location_id = Number(sourceLocationId.value);
        payload.destination_location_id = Number(destinationLocationId.value);
    } else {
        payload.location_id = Number(locationId.value);
    }
    const fingerprint = `${operation.value}:${JSON.stringify(payload)}`;
    if (fingerprint !== lastPayloadFingerprint || !lastIdempotencyKey) {
        lastPayloadFingerprint = fingerprint;
        lastIdempotencyKey = crypto.randomUUID();
    }
    payload.idempotency_key = lastIdempotencyKey;
    try {
        const response = await fetch(operationMeta.value.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(payload),
        });
        const result = await response.json() as ApiPayload<unknown>;
        if (!response.ok) {
            if (result.errors) {
                for (const [key, messages] of Object.entries(result.errors)) fieldErrors.value[key] = messages[0] ?? 'Invalid value.';
            }
            throw new Error(result.message || `Stock operation failed (HTTP ${response.status}).`);
        }
        if (thisRequest !== requestId.value) return;
        success.value = result.message || `${operationMeta.value.title} completed successfully.`;
        lastPayloadFingerprint = ''; lastIdempotencyKey = '';
        quantity.value = ''; reason.value = ''; referenceType.value = ''; referenceId.value = '';
        window.dispatchEvent(new CustomEvent('inventory:operation-complete', { detail: { operation: operation.value } }));
    } catch (e) {
        if (thisRequest !== requestId.value) return;
        error.value = e instanceof Error ? e.message : 'Unable to complete the stock operation.';
    } finally {
        if (thisRequest === requestId.value) submitting.value = false;
    }
}
onMounted(() => void loadOptions());
</script>

<template>
    <Head title="Stock Operations" />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <main class="inventory-page">
            <header class="page-header">
                <div class="page-heading">
                    <div class="breadcrumb"><a href="/inventory">Inventory</a><Icon name="chevron-right" :size="12" /><strong>Stock Operations</strong></div>
                    <div class="title-row">
                        <div class="page-title-icon"><Icon :name="operationMeta.icon" :size="20" /></div>
                        <div class="title-copy"><h1>Stock Operations</h1><p>Receive, issue, adjust or transfer inventory with a recorded stock ledger.</p></div>
                    </div>
                </div>
                <div class="header-actions"><a class="button secondary" href="/inventory/movements"><Icon name="history" :size="14" /> Movement history</a></div>
            </header>

            <section class="operation-tabs" aria-label="Stock operation type">
                <button v-for="item in [
                    { value: 'receive', label: 'Receive', icon: 'package-plus' },
                    { value: 'issue', label: 'Issue', icon: 'package-minus' },
                    { value: 'adjust', label: 'Adjust', icon: 'sliders-horizontal' },
                    { value: 'transfer', label: 'Transfer', icon: 'arrow-left-right' },
                ]" :key="item.value" type="button" class="operation-tab" :class="{ active: operation === item.value }" :aria-pressed="operation === item.value" @click="operation = item.value as Operation">
                    <Icon :name="item.icon" :size="16" /><span>{{ item.label }}</span>
                </button>
            </section>

            <section class="form-card">
                <div class="section-heading">
                    <div class="section-icon"><Icon :name="operationMeta.icon" :size="17" /></div>
                    <div class="section-heading-copy"><h2>{{ operationMeta.title }}</h2><p>{{ operationMeta.description }}</p></div>
                </div>

                <div v-if="loadingOptions" class="state-panel compact"><Icon name="loader-2" :size="20" class="spin" /><span>Loading products and locations…</span></div>
                <template v-else>
                    <div v-if="error" class="notice error" role="alert"><Icon name="alert-circle" :size="17" /><span>{{ error }}</span><button type="button" class="notice-close" aria-label="Dismiss error" @click="error = ''"><Icon name="x" :size="14" /></button></div>
                    <div v-if="success" class="notice success" role="status"><Icon name="check-circle-2" :size="17" /><span>{{ success }}</span><button type="button" class="notice-close" aria-label="Dismiss success" @click="success = ''"><Icon name="x" :size="14" /></button></div>

                    <form class="operation-form" @submit.prevent="submit">
                        <div class="form-grid">
                            <label class="field">
                                <span>Product <b>*</b></span>
                                <select v-model="productId" required :disabled="submitting || !products.length">
                                    <option value="">Select product</option>
                                    <option v-for="product in products" :key="product.id" :value="String(product.id)">{{ product.name }}{{ product.sku ? ` · ${product.sku}` : '' }}{{ product.product_type === 'variable' ? ' · Variable' : '' }}</option>
                                </select>
                                <small v-if="fieldErrors.product_id">{{ fieldErrors.product_id }}</small>
                            </label>
                            <label v-if="isVariable" class="field">
                                <span>Variant <b>*</b></span>
                                <select v-model="variantId" required :disabled="submitting || loadingVariants || !variants.length">
                                    <option value="">{{ loadingVariants ? 'Loading variants…' : 'Select variant' }}</option>
                                    <option v-for="variant in variants" :key="variant.id" :value="String(variant.id)">{{ variant.name || `Variant #${variant.id}` }}{{ variant.sku ? ` · ${variant.sku}` : '' }}</option>
                                </select>
                                <small v-if="fieldErrors.product_variant_id">{{ fieldErrors.product_variant_id }}</small>
                            </label>

                            <template v-if="operation === 'transfer'">
                                <label class="field"><span>Source location <b>*</b></span>
                                    <select v-model="sourceLocationId" required :disabled="submitting"><option value="">Select source location</option><option v-for="location in locations" :key="location.id" :value="String(location.id)">{{ location.name }}{{ location.code ? ` · ${location.code}` : '' }}</option></select>
                                    <small v-if="fieldErrors.source_location_id">{{ fieldErrors.source_location_id }}</small>
                                </label>
                                <label class="field"><span>Destination location <b>*</b></span>
                                    <select v-model="destinationLocationId" required :disabled="submitting"><option value="">Select destination location</option><option v-for="location in locations.filter(l => String(l.id) !== sourceLocationId)" :key="location.id" :value="String(location.id)">{{ location.name }}{{ location.code ? ` · ${location.code}` : '' }}</option></select>
                                    <small v-if="fieldErrors.destination_location_id">{{ fieldErrors.destination_location_id }}</small>
                                </label>
                            </template>
                            <label v-else class="field"><span>Location <b>*</b></span>
                                <select v-model="locationId" required :disabled="submitting"><option value="">Select location</option><option v-for="location in locations" :key="location.id" :value="String(location.id)">{{ location.name }}{{ location.code ? ` · ${location.code}` : '' }}</option></select>
                                <small v-if="fieldErrors.location_id">{{ fieldErrors.location_id }}</small>
                            </label>

                            <label class="field"><span>Quantity <b>*</b></span>
                                <input v-model="quantity" type="number" inputmode="decimal" step="0.0001" required :placeholder="operation === 'adjust' ? 'e.g. 5 or -2' : 'e.g. 10'" :disabled="submitting" />
                                <small>{{ quantityHelp }}</small>
                                <small v-if="fieldErrors.quantity" class="field-error">{{ fieldErrors.quantity }}</small>
                            </label>
                            <label class="field"><span>Reason <em>Optional</em></span>
                                <input v-model="reason" type="text" maxlength="255" placeholder="e.g. supplier delivery, damaged stock" :disabled="submitting" />
                                <small v-if="fieldErrors.reason" class="field-error">{{ fieldErrors.reason }}</small>
                            </label>
                            <label class="field"><span>Reference type <em>Optional</em></span><input v-model="referenceType" type="text" maxlength="100" placeholder="e.g. purchase_order" :disabled="submitting" /><small v-if="fieldErrors.reference_type" class="field-error">{{ fieldErrors.reference_type }}</small></label>
                            <label class="field"><span>Reference ID <em>Optional</em></span><input v-model="referenceId" type="text" maxlength="100" placeholder="Document or transaction ID" :disabled="submitting" /><small v-if="fieldErrors.reference_id" class="field-error">{{ fieldErrors.reference_id }}</small></label>
                        </div>

                        <div class="operation-note"><Icon name="shield-check" :size="16" /><span>Stock updates are processed by the server and recorded in the movement ledger. The operation permission is enforced by the API.</span></div>
                        <div class="form-actions"><button type="button" class="button secondary" :disabled="submitting" @click="resetForm">Reset form</button><button type="submit" class="button primary" :disabled="submitting || loadingOptions || !products.length || !locations.length"><Icon v-if="submitting" name="loader-2" :size="15" class="spin" /><Icon v-else :name="operationMeta.icon" :size="15" />{{ submitting ? 'Processing…' : operationMeta.title }}</button></div>
                    </form>
                </template>
            </section>
            <p class="page-footnote"><Icon name="info" :size="14" /> Confirm the product, location and quantity before submitting. A successful request is written to Stock Movements.</p>
        </main>
    </ErpLayout>
</template>

<style scoped>
.inventory-page{--surface:var(--surface,#fff);--border:var(--border,#e5eaf1);--text:var(--text-primary,#172033);--muted:var(--text-secondary,#657187);max-width:1440px;margin:0 auto;padding:22px 24px 30px;color:var(--text);font-size:12px}
.page-header{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:18px}
.breadcrumb{display:flex;align-items:center;gap:6px;color:var(--muted);font-size:10px;margin-bottom:12px}.breadcrumb a{color:inherit;text-decoration:none}.breadcrumb a:hover{color:var(--text)}.breadcrumb strong{font-weight:600;color:var(--text)}
.title-row{display:flex;align-items:center;gap:11px}.page-title-icon{width:40px;height:40px;border-radius:11px;background:#edf3ff;color:#3569c8;display:grid;place-items:center;flex-shrink:0}.title-copy h1{font-size:20px;line-height:1.25;letter-spacing:-.45px;margin:0;font-weight:700}.title-copy p{font-size:11px;color:var(--muted);margin:5px 0 0;line-height:1.5}
.header-actions{display:flex;gap:8px;padding-top:25px}.button{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:1px solid var(--border);background:var(--surface);color:var(--text);padding:8px 11px;border-radius:8px;font-family:inherit;font-size:11px;font-weight:600;text-decoration:none;cursor:pointer;transition:background .15s,border-color .15s,opacity .15s}.button:hover:not(:disabled){background:#f7f9fc}.button:disabled{opacity:.55;cursor:not-allowed}.button.primary{background:#285fc7;border-color:#285fc7;color:white}.button.primary:hover:not(:disabled){background:#214fa8}.button.secondary{background:var(--surface)}
.operation-tabs{display:flex;gap:5px;flex-wrap:wrap;padding:5px;border:1px solid var(--border);background:#f7f9fc;border-radius:11px;margin-bottom:14px}
.operation-tab{display:flex;align-items:center;justify-content:center;gap:7px;padding:9px 13px;border:1px solid transparent;border-radius:8px;background:transparent;color:var(--muted);font-family:inherit;font-size:11px;font-weight:600;cursor:pointer;transition:background .15s,color .15s,border-color .15s}.operation-tab:hover{color:var(--text);background:#fff}.operation-tab.active{background:#fff;border-color:#dfe6f0;color:#285fc7;box-shadow:0 1px 2px #1720330a}
.form-card{border:1px solid var(--border);border-radius:12px;background:var(--surface);box-shadow:0 2px 8px #17203306;overflow:hidden}
.section-heading{display:flex;align-items:center;gap:10px;padding:16px 18px;border-bottom:1px solid var(--border)}.section-icon{width:32px;height:32px;border-radius:9px;background:#edf3ff;color:#3569c8;display:grid;place-items:center;flex-shrink:0}.section-heading-copy h2{font-size:13px;margin:0;font-weight:700}.section-heading-copy p{font-size:10px;color:var(--muted);margin:4px 0 0}
.operation-form{padding:18px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 18px}.field{display:flex;flex-direction:column;gap:6px;min-width:0}.field>span{font-size:11px;font-weight:600;color:var(--text)}.field b{color:#d14343}.field em{font-size:9px;font-style:normal;font-weight:500;color:var(--muted);margin-left:4px}.field input,.field select{width:100%;min-height:36px;border:1px solid #dbe2ec;border-radius:7px;background:var(--surface);color:var(--text);padding:8px 10px;font-family:inherit;font-size:11px;font-weight:400;outline:none;transition:border-color .15s,box-shadow .15s}.field input:focus,.field select:focus{border-color:#6b95e5;box-shadow:0 0 0 3px #3569c81a}.field input:disabled,.field select:disabled{opacity:.6;cursor:not-allowed}.field small{font-size:9px;color:var(--muted);line-height:1.45}.field .field-error,.field small:has(+.field-error){color:#b42318}
.operation-note{display:flex;align-items:flex-start;gap:8px;padding:11px 12px;margin-top:18px;border:1px solid #dce8fb;border-radius:8px;background:#f5f8ff;color:#425779;font-size:10px;line-height:1.5}.operation-note :deep(svg){flex-shrink:0;color:#3569c8}
.form-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px;padding-top:14px;border-top:1px solid var(--border)}
.notice{display:flex;align-items:flex-start;gap:8px;padding:10px 12px;margin:14px 18px 0;border-radius:8px;font-size:11px;line-height:1.5}.notice>span{flex:1}.notice.error{background:#fff4f2;border:1px solid #f3d1cb;color:#a22b20}.notice.success{background:#effaf3;border:1px solid #c8ead3;color:#17663a}.notice-close{border:0;background:transparent;color:inherit;padding:0;cursor:pointer}.state-panel{display:flex;align-items:center;gap:8px;padding:18px;color:var(--muted);font-size:11px}.state-panel.compact{padding:20px}
.page-footnote{display:flex;align-items:flex-start;gap:7px;margin:13px 2px 0;color:var(--muted);font-size:10px;line-height:1.5}.page-footnote :deep(svg){flex-shrink:0;margin-top:1px}
.spin{animation:spin 1s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:720px){.inventory-page{padding:16px 12px 24px}.page-header{flex-direction:column}.header-actions{padding-top:0}.form-grid{grid-template-columns:1fr}.operation-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.operation-tab{padding:10px}.section-heading,.operation-form{padding:14px}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important;scroll-behavior:auto!important}}
</style>
