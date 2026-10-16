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
const quantity = ref<string | number>('');
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
    receive: { title: 'Receive Stock', description: 'Add received inventory to a location.', icon: 'package', endpoint: '/api/inventory/receive', permission: 'system.inventory.receive' },
    issue: { title: 'Issue Stock', description: 'Record inventory leaving a location.', icon: 'arrow-right', endpoint: '/api/inventory/issue', permission: 'system.inventory.issue' },
    adjust: { title: 'Adjust Stock', description: 'Apply a signed correction to the stock balance.', icon: 'adjustments', endpoint: '/api/inventory/adjust', permission: 'system.inventory.adjust' },
    transfer: { title: 'Transfer Stock', description: 'Move inventory atomically between two locations.', icon: 'refresh-cw', endpoint: '/api/inventory/transfer', permission: 'system.inventory.transfer' },
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

/**
 * Laravel's web middleware validates mutating requests with the XSRF-TOKEN
 * cookie. The app shell does not expose a csrf-token meta tag, so reading
 * that meta tag alone sends an empty token and causes every POST to return 419.
 */
function getXsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    if (!cookie) return '';

    const value = cookie.slice('XSRF-TOKEN='.length);

    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}

async function readApiPayload<T>(response: Response): Promise<ApiPayload<T>> {
    const contentType = response.headers.get('content-type') ?? '';

    if (contentType.includes('application/json')) {
        return await response.json() as ApiPayload<T>;
    }

    // Authentication/session middleware may return an HTML page instead of JSON.
    const text = await response.text();
    if (response.status === 419) {
        throw new Error('Your session security token has expired. Refresh the page and try again.');
    }
    if (response.status === 401 || response.redirected) {
        throw new Error('Your session may have expired. Sign in again, then retry this operation.');
    }
    if (response.status === 403) {
        throw new Error('You do not have permission to perform this stock operation.');
    }

    throw new Error(text.trim().slice(0, 180) || `Request failed (HTTP ${response.status}).`);
}
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
        const productPayload = await readApiPayload<Product[]>(productResponse);
        const locationPayload = await readApiPayload<Location[]>(locationResponse);
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
        const payload = await readApiPayload<Variant[]>(response);
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
    // v-model.number (or browser input normalization) can make this a number.
    // Normalize before string operations so submit validation never throws.
    const quantityText = String(quantity.value ?? '').trim();
    const n = Number(quantityText);
    const validPrecision = /^-?\d{1,14}(?:\.\d{1,4})?$/.test(quantityText);
    if (!quantityText || !Number.isFinite(n) || !validPrecision || n === 0) fieldErrors.value.quantity = 'Enter a non-zero quantity with up to 4 decimal places.';
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
        quantity: String(quantity.value ?? '').trim(),
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
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify(payload),
        });
        const result = await readApiPayload<unknown>(response);
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
                    { value: 'receive', label: 'Receive', icon: 'package' },
                    { value: 'issue', label: 'Issue', icon: 'arrow-right' },
                    { value: 'adjust', label: 'Adjust', icon: 'adjustments' },
                    { value: 'transfer', label: 'Transfer', icon: 'refresh-cw' },
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
.inventory-page {
  --page-bg: var(--background, #f6f8fb);
  --surface-color: var(--surface, #ffffff);
  --border-color: var(--border, #e5e7eb);
  --text-primary-color: var(--text-primary, #111827);
  --text-secondary-color: var(--text-secondary, #667085);
  --text-tertiary-color: var(--text-tertiary, #98a2b3);
  --primary-color: var(--primary, #2563eb);
  min-width: 0;
  min-height: 100%;
  padding: 0;
  color: var(--text-primary-color);
  background: var(--page-bg);
  font-size: 12px;
}
.inventory-page *, .inventory-page *::before, .inventory-page *::after { box-sizing: border-box; }
.page-header { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; max-width:1480px; margin:0 auto 18px; }
.page-heading, .title-copy, .section-heading-copy { min-width:0; }
.breadcrumb { display:flex; align-items:center; gap:7px; margin-bottom:9px; color:var(--text-tertiary-color); font-size:10px; }
.breadcrumb a { color:inherit; text-decoration:none; transition:color .15s ease; }
.breadcrumb a:hover { color:var(--primary-color); }
.breadcrumb strong { color:var(--text-secondary-color); font-weight:600; }
.title-row { display:flex; align-items:flex-start; gap:12px; }
.page-title-icon, .section-icon { display:grid; flex:0 0 auto; place-items:center; border:1px solid rgb(37 99 235 / 11%); background:rgb(37 99 235 / 8%); color:var(--primary-color); }
.page-title-icon { width:42px; height:42px; margin-top:2px; border-radius:12px; }
.title-copy h1 { margin:0; color:var(--text-primary-color); font-size:20px; line-height:1.25; font-weight:750; letter-spacing:-.03em; }
.title-copy p { max-width:680px; margin:6px 0 0; color:var(--text-secondary-color); font-size:11px; line-height:1.5; }
.header-actions { display:flex; align-items:center; gap:8px; padding-top:24px; }
.button { display:inline-flex; min-height:36px; align-items:center; justify-content:center; gap:7px; padding:0 12px; border:1px solid var(--border-color); border-radius:9px; background:var(--surface-color); color:var(--text-secondary-color); font-family:inherit; font-size:11px; font-weight:650; text-decoration:none; cursor:pointer; transition:border-color .18s ease, background .18s ease, color .18s ease, box-shadow .18s ease, opacity .18s ease; }
.button:hover:not(:disabled) { border-color:var(--primary-color); color:var(--primary-color); }
.button:focus-visible, .operation-tab:focus-visible, .field input:focus-visible, .field select:focus-visible { outline:2px solid var(--primary-color); outline-offset:2px; }
.button:disabled { cursor:not-allowed; opacity:.58; }
.button.primary { border-color:var(--primary-color); background:var(--primary-color); color:#fff; box-shadow:0 1px 2px rgb(16 24 40 / 10%); }
.button.primary:hover:not(:disabled) { border-color:var(--primary-color); background:var(--primary-color); color:#fff; filter:brightness(.92); box-shadow:0 3px 8px rgb(37 99 235 / 20%); }
.button.secondary { background:var(--surface-color); }
.operation-tabs { display:flex; flex-wrap:wrap; gap:5px; max-width:1480px; margin:0 auto 14px; padding:5px; border:1px solid var(--border-color); border-radius:12px; background:var(--surface-color); box-shadow:0 2px 8px rgb(16 24 40 / 2.5%); }
.operation-tab { display:inline-flex; min-height:34px; align-items:center; justify-content:center; gap:7px; padding:0 12px; border:1px solid transparent; border-radius:8px; background:transparent; color:var(--text-secondary-color); font-family:inherit; font-size:11px; font-weight:650; cursor:pointer; transition:background .16s ease, color .16s ease, border-color .16s ease, box-shadow .16s ease; }
.operation-tab:hover { border-color:var(--border-color); background:#f9fafb; color:var(--text-primary-color); }
.operation-tab.active { border-color:rgb(37 99 235 / 18%); background:rgb(37 99 235 / 8%); color:var(--primary-color); box-shadow:inset 0 0 0 1px rgb(37 99 235 / 3%); }
.form-card { max-width:1480px; margin:0 auto; overflow:hidden; border:1px solid var(--border-color); border-radius:14px; background:var(--surface-color); box-shadow:0 2px 10px rgb(16 24 40 / 2.5%); }
.section-heading { display:flex; align-items:center; gap:11px; padding:17px 19px; border-bottom:1px solid var(--border-color); }
.section-icon { width:36px; height:36px; border-radius:10px; }
.section-heading-copy h2 { margin:0 0 3px; color:var(--text-primary-color); font-size:13px; font-weight:700; }
.section-heading-copy p { margin:0; color:var(--text-secondary-color); font-size:10px; line-height:1.5; }
.operation-form { padding:19px; }
.form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:15px 18px; }
.field { display:flex; min-width:0; flex-direction:column; gap:6px; }
.field>span { color:var(--text-primary-color); font-size:11px; font-weight:650; }
.field b { color:#d14343; }
.field em { margin-left:4px; color:var(--text-tertiary-color); font-size:9px; font-style:normal; font-weight:500; }
.field input, .field select { display:block; width:100%; min-width:0; min-height:36px; padding:7px 10px; border:1px solid var(--border-color); border-radius:8px; outline:none; background:var(--surface-color); color:var(--text-primary-color); font-family:inherit; font-size:11px; font-weight:400; transition:border-color .15s ease, box-shadow .15s ease; }
.field input::placeholder { color:var(--text-tertiary-color); }
.field input:focus, .field select:focus { border-color:var(--primary-color); box-shadow:0 0 0 3px rgb(37 99 235 / 10%); }
.field input:disabled, .field select:disabled { opacity:.6; cursor:not-allowed; background:#f9fafb; }
.field small { color:var(--text-tertiary-color); font-size:9px; line-height:1.45; }
.field .field-error, .field small:has(+.field-error) { color:#b42318; }
.operation-note { display:flex; align-items:flex-start; gap:8px; margin-top:17px; padding:10px 12px; border:1px solid rgb(37 99 235 / 15%); border-radius:9px; background:rgb(37 99 235 / 4%); color:var(--text-secondary-color); font-size:10px; line-height:1.55; }
.operation-note :deep(svg) { flex-shrink:0; margin-top:1px; color:var(--primary-color); }
.form-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:17px; padding-top:14px; border-top:1px solid var(--border-color); }
.notice { display:flex; align-items:flex-start; gap:8px; margin:14px 19px 0; padding:10px 12px; border-radius:9px; font-size:11px; line-height:1.5; }
.notice>span { flex:1; min-width:0; }
.notice.error { border:1px solid #f3d1cb; background:#fff4f2; color:#a22b20; }
.notice.success { border:1px solid #c8ead3; background:#effaf3; color:#17663a; }
.notice-close { display:inline-flex; align-items:center; justify-content:center; padding:2px; border:0; border-radius:5px; background:transparent; color:inherit; cursor:pointer; }
.notice-close:hover { background:rgb(0 0 0 / 5%); }
.state-panel { display:flex; align-items:center; gap:8px; padding:18px 19px; color:var(--text-secondary-color); font-size:11px; }
.state-panel.compact { padding:20px; }
.page-footnote { display:flex; align-items:flex-start; gap:7px; max-width:1480px; margin:12px auto 0; color:var(--text-tertiary-color); font-size:10px; line-height:1.5; }
.page-footnote :deep(svg) { flex-shrink:0; margin-top:1px; color:var(--text-secondary-color); }
.spin { animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
@media(max-width:720px) { .page-header { flex-direction:column; gap:10px; } .header-actions { padding-top:0; } .form-grid { grid-template-columns:1fr; } .operation-tabs { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); } .operation-tab { padding:0 8px; } .section-heading, .operation-form { padding:14px; } .notice { margin-right:14px; margin-left:14px; } }
@media(max-width:420px) { .title-copy h1 { font-size:18px; } .title-row { gap:10px; } .form-actions { flex-wrap:wrap; } .form-actions .button { flex:1 1 auto; } }
@media(prefers-reduced-motion:reduce) { *,*::before,*::after { animation-duration:.01ms!important; transition-duration:.01ms!important; scroll-behavior:auto!important; } }
</style>
