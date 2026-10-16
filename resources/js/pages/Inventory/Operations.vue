<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Props {
  userName?: string;
  userEmail?: string;
}
type Operation = "receive" | "issue" | "adjust" | "transfer";
interface Product {
  id: number;
  name: string;
  sku?: string | null;
  product_type?: string;
  status?: string;
}
interface Variant {
  id: number;
  product_id: number;
  name?: string | null;
  sku?: string | null;
  status?: string;
}
interface Location {
  id: number;
  name: string;
  code?: string | null;
  is_active?: boolean;
}
interface ApiPayload<T> {
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
}
const props = withDefaults(defineProps<Props>(), { userName: "", userEmail: "" });

const operation = ref<Operation>("receive");
const products = ref<Product[]>([]);
const locations = ref<Location[]>([]);
const variants = ref<Variant[]>([]);
const productId = ref("");
const variantId = ref("");
const itemType = ref<"product" | "variant">("product");
const locationId = ref("");
const sourceLocationId = ref("");
const destinationLocationId = ref("");
const quantity = ref<string | number>("");
const reason = ref("");
const referenceType = ref("");
const referenceId = ref("");
const loadingOptions = ref(true);
const loadingVariants = ref(false);
const submitting = ref(false);
const error = ref("");
const success = ref("");
const fieldErrors = ref<Record<string, string>>({});
const requestId = ref(0);
let lastPayloadFingerprint = "";
let lastIdempotencyKey = "";

const currentProduct = computed(() =>
  products.value.find((p) => String(p.id) === productId.value)
);
const isVariable = computed(() => currentProduct.value?.product_type === "variable");
const selectedVariant = computed(() =>
  variants.value.find((v) => String(v.id) === variantId.value)
);
const chosenItemLabel = computed(() =>
  itemType.value === "variant"
    ? `${currentProduct.value?.name ?? "Product"}${
        selectedVariant.value?.name ? ` — ${selectedVariant.value.name}` : ""
      }`
    : currentProduct.value?.name ?? ""
);
const operationMeta = computed(
  () =>
    ({
      receive: {
        title: "Receive Stock",
        description: "Add received inventory to a location.",
        icon: "stock-in",
        endpoint: "/api/inventory/receive",
        permission: "system.inventory.receive",
      },
      issue: {
        title: "Issue Stock",
        description: "Record inventory leaving a location.",
        icon: "stock-out",
        endpoint: "/api/inventory/issue",
        permission: "system.inventory.issue",
      },
      adjust: {
        title: "Adjust Stock",
        description: "Apply a signed correction to the stock balance.",
        icon: "sliders-horizontal",
        endpoint: "/api/inventory/adjust",
        permission: "system.inventory.adjust",
      },
      transfer: {
        title: "Transfer Stock",
        description: "Move inventory atomically between two locations.",
        icon: "arrows-left-right",
        endpoint: "/api/inventory/transfer",
        permission: "system.inventory.transfer",
      },
    }[operation.value])
);
const quantityHelp = computed(() =>
  operation.value === "adjust"
    ? "Use a positive number to increase stock or a negative number to decrease it."
    : "Enter a positive quantity. Up to 4 decimal places are supported."
);

function numeric(value: string | number | null | undefined): number {
  const n = Number(value ?? 0);
  return Number.isFinite(n) ? n : 0;
}
function qty(value: string | number): string {
  return new Intl.NumberFormat("en-BD", { maximumFractionDigits: 4 }).format(
    numeric(value)
  );
}
function clearNotices(): void {
  error.value = "";
  success.value = "";
  fieldErrors.value = {};
}

/**
 * Laravel's web middleware validates mutating requests with the XSRF-TOKEN
 * cookie. The app shell does not expose a csrf-token meta tag, so reading
 * that meta tag alone sends an empty token and causes every POST to return 419.
 */
function getXsrfToken(): string {
  const cookie = document.cookie
    .split("; ")
    .find((entry) => entry.startsWith("XSRF-TOKEN="));

  if (!cookie) return "";

  const value = cookie.slice("XSRF-TOKEN=".length);

  try {
    return decodeURIComponent(value);
  } catch {
    return value;
  }
}

async function readApiPayload<T>(response: Response): Promise<ApiPayload<T>> {
  const contentType = response.headers.get("content-type") ?? "";

  if (contentType.includes("application/json")) {
    return (await response.json()) as ApiPayload<T>;
  }

  // Authentication/session middleware may return an HTML page instead of JSON.
  const text = await response.text();
  if (response.status === 419) {
    throw new Error(
      "Your session security token has expired. Refresh the page and try again."
    );
  }
  if (response.status === 401 || response.redirected) {
    throw new Error(
      "Your session may have expired. Sign in again, then retry this operation."
    );
  }
  if (response.status === 403) {
    throw new Error("You do not have permission to perform this stock operation.");
  }

  throw new Error(
    text.trim().slice(0, 180) || `Request failed (HTTP ${response.status}).`
  );
}
function resetForm(): void {
  productId.value = "";
  variantId.value = "";
  itemType.value = "product";
  locationId.value = "";
  sourceLocationId.value = "";
  destinationLocationId.value = "";
  quantity.value = "";
  reason.value = "";
  referenceType.value = "";
  referenceId.value = "";
  variants.value = [];
  clearNotices();
}
async function loadOptions(): Promise<void> {
  loadingOptions.value = true;
  error.value = "";
  try {
    const [productResponse, locationResponse] = await Promise.all([
      fetch("/api/products?status=active", {
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      }),
      fetch("/api/locations?active=1", {
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      }),
    ]);
    const productPayload = await readApiPayload<Product[]>(productResponse);
    const locationPayload = await readApiPayload<Location[]>(locationResponse);
    if (!productResponse.ok)
      throw new Error(
        productPayload.message ||
          `Products request failed (HTTP ${productResponse.status}).`
      );
    if (!locationResponse.ok)
      throw new Error(
        locationPayload.message ||
          `Locations request failed (HTTP ${locationResponse.status}).`
      );
    products.value = Array.isArray(productPayload.data)
      ? productPayload.data.filter((p) => !p.status || p.status === "active")
      : [];
    locations.value = Array.isArray(locationPayload.data)
      ? locationPayload.data.filter((l) => l.is_active !== false)
      : [];
    if (!products.value.length)
      error.value =
        "No active products are available, or your account cannot view products.";
    else if (!locations.value.length)
      error.value =
        "No active locations are available, or your account cannot view locations.";
  } catch (e) {
    error.value =
      e instanceof Error ? e.message : "Unable to load products and locations.";
  } finally {
    loadingOptions.value = false;
  }
}
async function loadVariants(): Promise<void> {
  variants.value = [];
  variantId.value = "";
  if (!productId.value || currentProduct.value?.product_type !== "variable") return;
  loadingVariants.value = true;
  try {
    const response = await fetch(
      `/api/products/${encodeURIComponent(productId.value)}/variants`,
      {
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      }
    );
    const payload = await readApiPayload<Variant[]>(response);
    if (!response.ok)
      throw new Error(
        payload.message || `Variants request failed (HTTP ${response.status}).`
      );
    variants.value = Array.isArray(payload.data)
      ? payload.data.filter((v) => !v.status || v.status === "active")
      : [];
    if (!variants.value.length)
      error.value = "This variable product has no active variants available.";
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Unable to load product variants.";
  } finally {
    loadingVariants.value = false;
  }
}
watch(productId, async () => {
  clearNotices();
  if (currentProduct.value?.product_type === "variable") {
    itemType.value = "variant";
    await loadVariants();
  } else {
    itemType.value = "product";
    variantId.value = "";
    variants.value = [];
  }
});
watch(operation, () => {
  clearNotices();
  success.value = "";
});
function validateForm(): boolean {
  fieldErrors.value = {};
  if (!productId.value) fieldErrors.value.product_id = "Select a product.";
  if (isVariable.value && !variantId.value)
    fieldErrors.value.product_variant_id = "Select an active variant.";
  if (operation.value === "transfer") {
    if (!sourceLocationId.value)
      fieldErrors.value.source_location_id = "Select a source location.";
    if (!destinationLocationId.value)
      fieldErrors.value.destination_location_id = "Select a destination location.";
    if (sourceLocationId.value && sourceLocationId.value === destinationLocationId.value)
      fieldErrors.value.destination_location_id =
        "Source and destination must be different.";
  } else if (!locationId.value) fieldErrors.value.location_id = "Select a location.";
  // v-model.number (or browser input normalization) can make this a number.
  // Normalize before string operations so submit validation never throws.
  const quantityText = String(quantity.value ?? "").trim();
  const n = Number(quantityText);
  const validPrecision = /^-?\d{1,14}(?:\.\d{1,4})?$/.test(quantityText);
  if (!quantityText || !Number.isFinite(n) || !validPrecision || n === 0)
    fieldErrors.value.quantity = "Enter a non-zero quantity with up to 4 decimal places.";
  else if (operation.value !== "adjust" && n < 0)
    fieldErrors.value.quantity = "Quantity must be greater than zero.";
  if (reason.value.length > 255)
    fieldErrors.value.reason = "Reason must be 255 characters or fewer.";
  return Object.keys(fieldErrors.value).length === 0;
}
async function submit(): Promise<void> {
  clearNotices();
  if (!validateForm()) {
    error.value = "Please correct the highlighted fields.";
    return;
  }
  const thisRequest = ++requestId.value;
  submitting.value = true;
  const payload: Record<string, unknown> = {
    quantity: String(quantity.value ?? "").trim(),
    reason: reason.value.trim() || null,
    reference_type: referenceType.value.trim() || null,
    reference_id: referenceId.value.trim() || null,
  };
  if (isVariable.value) payload.product_variant_id = Number(variantId.value);
  else payload.product_id = Number(productId.value);
  if (operation.value === "transfer") {
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
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": getXsrfToken(),
      },
      body: JSON.stringify(payload),
    });
    const result = await readApiPayload<unknown>(response);
    if (!response.ok) {
      if (result.errors) {
        for (const [key, messages] of Object.entries(result.errors))
          fieldErrors.value[key] = messages[0] ?? "Invalid value.";
      }
      throw new Error(
        result.message || `Stock operation failed (HTTP ${response.status}).`
      );
    }
    if (thisRequest !== requestId.value) return;
    success.value =
      result.message || `${operationMeta.value.title} completed successfully.`;
    lastPayloadFingerprint = "";
    lastIdempotencyKey = "";
    quantity.value = "";
    reason.value = "";
    referenceType.value = "";
    referenceId.value = "";
    window.dispatchEvent(
      new CustomEvent("inventory:operation-complete", {
        detail: { operation: operation.value },
      })
    );
  } catch (e) {
    if (thisRequest !== requestId.value) return;
    error.value =
      e instanceof Error ? e.message : "Unable to complete the stock operation.";
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
          <div class="breadcrumb">
            <a href="/inventory">Inventory</a
            ><Icon name="chevron-right" :size="12" /><strong>Stock Operations</strong>
          </div>
          <div class="title-row">
            <div class="page-title-icon">
              <Icon :name="operationMeta.icon" :size="20" />
            </div>
            <div class="title-copy">
              <h1>Stock Operations</h1>
              <p>
                Receive, issue, adjust or transfer inventory with a recorded stock ledger.
              </p>
            </div>
          </div>
        </div>
        <div class="header-actions">
          <a class="button secondary" href="/inventory/movements"
            ><Icon name="history" :size="14" /> Movement history</a
          >
        </div>
      </header>

      <section class="operation-tabs" aria-label="Stock operation type">
        <button
          v-for="item in [
            { value: 'receive', label: 'Receive', icon: 'stock-in' },
            { value: 'issue', label: 'Issue', icon: 'stock-out' },
            { value: 'adjust', label: 'Adjust', icon: 'sliders-horizontal' },
            { value: 'transfer', label: 'Transfer', icon: 'arrows-left-right' },
          ]"
          :key="item.value"
          type="button"
          class="operation-tab"
          :class="{ active: operation === item.value }"
          :aria-pressed="operation === item.value"
          @click="operation = item.value as Operation"
        >
          <Icon :name="item.icon" :size="16" /><span>{{ item.label }}</span>
        </button>
      </section>

      <section class="form-card">
        <div class="section-heading">
          <div class="section-icon"><Icon :name="operationMeta.icon" :size="17" /></div>
          <div class="section-heading-copy">
            <h2>{{ operationMeta.title }}</h2>
            <p>{{ operationMeta.description }}</p>
          </div>
        </div>

        <div v-if="loadingOptions" class="state-panel compact">
          <Icon name="loader-2" :size="20" class="spin" /><span
            >Loading products and locations…</span
          >
        </div>
        <template v-else>
          <div v-if="error" class="notice error" role="alert">
            <Icon name="alert-circle" :size="17" /><span>{{ error }}</span
            ><button
              type="button"
              class="notice-close"
              aria-label="Dismiss error"
              @click="error = ''"
            >
              <Icon name="x" :size="14" />
            </button>
          </div>
          <div v-if="success" class="notice success" role="status">
            <Icon name="check-circle-2" :size="17" /><span>{{ success }}</span
            ><button
              type="button"
              class="notice-close"
              aria-label="Dismiss success"
              @click="success = ''"
            >
              <Icon name="x" :size="14" />
            </button>
          </div>

          <form class="operation-form" @submit.prevent="submit">
            <div class="form-grid">
              <label class="field">
                <span>Product <b>*</b></span>
                <select
                  v-model="productId"
                  required
                  :disabled="submitting || !products.length"
                >
                  <option value="">Select product</option>
                  <option
                    v-for="product in products"
                    :key="product.id"
                    :value="String(product.id)"
                  >
                    {{ product.name }}{{ product.sku ? ` · ${product.sku}` : ""
                    }}{{ product.product_type === "variable" ? " · Variable" : "" }}
                  </option>
                </select>
                <small v-if="fieldErrors.product_id">{{ fieldErrors.product_id }}</small>
              </label>
              <label v-if="isVariable" class="field">
                <span>Variant <b>*</b></span>
                <select
                  v-model="variantId"
                  required
                  :disabled="submitting || loadingVariants || !variants.length"
                >
                  <option value="">
                    {{ loadingVariants ? "Loading variants…" : "Select variant" }}
                  </option>
                  <option
                    v-for="variant in variants"
                    :key="variant.id"
                    :value="String(variant.id)"
                  >
                    {{ variant.name || `Variant #${variant.id}`
                    }}{{ variant.sku ? ` · ${variant.sku}` : "" }}
                  </option>
                </select>
                <small v-if="fieldErrors.product_variant_id">{{
                  fieldErrors.product_variant_id
                }}</small>
              </label>

              <template v-if="operation === 'transfer'">
                <label class="field"
                  ><span>Source location <b>*</b></span>
                  <select v-model="sourceLocationId" required :disabled="submitting">
                    <option value="">Select source location</option>
                    <option
                      v-for="location in locations"
                      :key="location.id"
                      :value="String(location.id)"
                    >
                      {{ location.name }}{{ location.code ? ` · ${location.code}` : "" }}
                    </option>
                  </select>
                  <small v-if="fieldErrors.source_location_id">{{
                    fieldErrors.source_location_id
                  }}</small>
                </label>
                <label class="field"
                  ><span>Destination location <b>*</b></span>
                  <select v-model="destinationLocationId" required :disabled="submitting">
                    <option value="">Select destination location</option>
                    <option
                      v-for="location in locations.filter(
                        (l) => String(l.id) !== sourceLocationId
                      )"
                      :key="location.id"
                      :value="String(location.id)"
                    >
                      {{ location.name }}{{ location.code ? ` · ${location.code}` : "" }}
                    </option>
                  </select>
                  <small v-if="fieldErrors.destination_location_id">{{
                    fieldErrors.destination_location_id
                  }}</small>
                </label>
              </template>
              <label v-else class="field"
                ><span>Location <b>*</b></span>
                <select v-model="locationId" required :disabled="submitting">
                  <option value="">Select location</option>
                  <option
                    v-for="location in locations"
                    :key="location.id"
                    :value="String(location.id)"
                  >
                    {{ location.name }}{{ location.code ? ` · ${location.code}` : "" }}
                  </option>
                </select>
                <small v-if="fieldErrors.location_id">{{
                  fieldErrors.location_id
                }}</small>
              </label>

              <label class="field"
                ><span>Quantity <b>*</b></span>
                <input
                  v-model="quantity"
                  type="number"
                  inputmode="decimal"
                  step="0.0001"
                  required
                  :placeholder="operation === 'adjust' ? 'e.g. 5 or -2' : 'e.g. 10'"
                  :disabled="submitting"
                />
                <small>{{ quantityHelp }}</small>
                <small v-if="fieldErrors.quantity" class="field-error">{{
                  fieldErrors.quantity
                }}</small>
              </label>
              <label class="field"
                ><span>Reason <em>Optional</em></span>
                <input
                  v-model="reason"
                  type="text"
                  maxlength="255"
                  placeholder="e.g. supplier delivery, damaged stock"
                  :disabled="submitting"
                />
                <small v-if="fieldErrors.reason" class="field-error">{{
                  fieldErrors.reason
                }}</small>
              </label>
              <label class="field"
                ><span>Reference type <em>Optional</em></span
                ><input
                  v-model="referenceType"
                  type="text"
                  maxlength="100"
                  placeholder="e.g. purchase_order"
                  :disabled="submitting"
                /><small v-if="fieldErrors.reference_type" class="field-error">{{
                  fieldErrors.reference_type
                }}</small></label
              >
              <label class="field"
                ><span>Reference ID <em>Optional</em></span
                ><input
                  v-model="referenceId"
                  type="text"
                  maxlength="100"
                  placeholder="Document or transaction ID"
                  :disabled="submitting"
                /><small v-if="fieldErrors.reference_id" class="field-error">{{
                  fieldErrors.reference_id
                }}</small></label
              >
            </div>

            <div class="operation-note">
              <Icon name="shield-check" :size="16" /><span
                >Stock updates are processed by the server and recorded in the movement
                ledger. The operation permission is enforced by the API.</span
              >
            </div>
            <div class="form-actions">
              <button
                type="button"
                class="button secondary"
                :disabled="submitting"
                @click="resetForm"
              >
                Reset form</button
              ><button
                type="submit"
                class="button primary"
                :disabled="
                  submitting || loadingOptions || !products.length || !locations.length
                "
              >
                <Icon v-if="submitting" name="loader-2" :size="15" class="spin" /><Icon
                  v-else
                  :name="operationMeta.icon"
                  :size="15"
                />{{ submitting ? "Processing…" : operationMeta.title }}
              </button>
            </div>
          </form>
        </template>
      </section>
      <p class="page-footnote">
        <Icon name="info" :size="14" /> Confirm the product, location and quantity before
        submitting. A successful request is written to Stock Movements.
      </p>
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

.inventory-page *,
.inventory-page *::before,
.inventory-page *::after {
  box-sizing: border-box;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  max-width: 1480px;
  margin: 0 auto 18px;
}

.page-heading,
.title-copy,
.section-heading-copy {
  min-width: 0;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-bottom: 9px;
  color: var(--text-tertiary-color);
  font-size: 12px;
}

.breadcrumb strong {
  color: var(--text-secondary-color);
  font-weight: 600;
}

.title-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.page-title-icon,
.section-icon,
.stat-icon {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
}

.page-title-icon {
  width: 42px;
  height: 42px;
  margin-top: 2px;
  border: 1px solid rgb(37 99 235 / 11%);
  border-radius: 12px;
  background: rgb(37 99 235 / 8%);
  color: var(--primary-color);
}

h1 {
  margin: 0;
  color: var(--text-primary-color);
  font-size: 26px;
  line-height: 1.2;
  font-weight: 750;
  letter-spacing: -0.03em;
}

.page-header p {
  max-width: 680px;
  margin: 6px 0 0;
  color: var(--text-secondary-color);
  font-size: 13px;
  line-height: 1.5;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 9px;
  padding-top: 25px;
}

.button {
  display: inline-flex;
  min-height: 38px;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 0 13px;
  border: 1px solid var(--border-color);
  border-radius: 9px;
  background: var(--surface-color);
  color: var(--text-secondary-color);
  font: inherit;
  font-size: 12px;
  font-weight: 650;
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease, color 0.2s ease,
    transform 0.2s ease, box-shadow 0.2s ease;
}

.button:hover:not(:disabled) {
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.context-strip {
  display: flex;
  align-items: center;
  gap: 17px;
  max-width: 1480px;
  margin: 0 auto 20px;
  padding: 12px 15px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: var(--surface-color);
  box-shadow: 0 2px 8px rgb(16 24 40 / 2%);
}

.context-item {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 9px;
}

.context-item > div {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 2px;
}

.context-item strong {
  color: var(--text-primary-color);
  font-size: 11px;
  font-weight: 700;
}

.context-item div span {
  color: var(--text-tertiary-color);
  font-size: 10px;
}

.context-dot,
.context-number {
  display: grid;
  flex: 0 0 auto;
  width: 27px;
  height: 27px;
  place-items: center;
  border-radius: 8px;
}

.context-dot {
  background: rgb(37 99 235 / 9%);
}

.context-dot::after {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--primary-color);
  content: "";
}

.context-number {
  border: 1px solid var(--border-color);
  background: #fafbfc;
  color: var(--text-tertiary-color);
  font-size: 9px;
  font-weight: 700;
}

.context-divider {
  width: 1px;
  height: 27px;
  flex: 0 0 auto;
  background: var(--border-color);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  max-width: 1480px;
  margin: 0 auto 20px;
}

.stat-card {
  min-width: 0;
  padding: 17px 18px;
  border: 1px solid var(--border-color);
  border-radius: 14px;
  background: var(--surface-color);
  box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
  animation: card-enter 0.35s ease both;
}

.stat-card:hover {
  transform: translateY(-2px);
  border-color: #d7deeb;
  box-shadow: 0 7px 18px rgb(16 24 40 / 5%);
}

.stat-card:nth-child(2) {
  animation-delay: 40ms;
}

.stat-card:nth-child(3) {
  animation-delay: 80ms;
}

.stat-card:nth-child(4) {
  animation-delay: 120ms;
}

.stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 13px;
}

.stat-icon {
  width: 38px;
  height: 38px;
  border: 1px solid transparent;
  border-radius: 11px;
}

.stat-icon.blue {
  border-color: rgb(37 99 235 / 10%);
  background: rgb(37 99 235 / 8%);
  color: #2563eb;
}

.stat-icon.green {
  border-color: rgb(22 163 74 / 10%);
  background: rgb(22 163 74 / 8%);
  color: #16a34a;
}

.stat-icon.amber {
  border-color: rgb(217 119 6 / 12%);
  background: rgb(217 119 6 / 9%);
  color: #b7791f;
}

.stat-icon.violet {
  border-color: rgb(124 58 237 / 10%);
  background: rgb(124 58 237 / 8%);
  color: #7c3aed;
}

.stat-caption {
  color: var(--text-tertiary-color);
  font-size: 10px;
  font-weight: 600;
}

.stat-value {
  overflow-wrap: anywhere;
  color: var(--text-primary-color);
  font-size: 25px;
  line-height: 1.2;
  font-weight: 750;
  letter-spacing: -0.035em;
  font-variant-numeric: tabular-nums;
}

.stat-label {
  margin-top: 5px;
  color: var(--text-primary-color);
  font-size: 12px;
  font-weight: 650;
}

.stat-description {
  margin: 7px 0 0;
  color: var(--text-tertiary-color);
  font-size: 10px;
  line-height: 1.5;
}

.card {
  max-width: 1480px;
  margin: 0 auto;
  border: 1px solid var(--border-color);
  border-radius: 14px;
  background: var(--surface-color);
  box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
}

.stock-card {
  overflow: hidden;
}

.section-heading {
  display: flex;
  align-items: flex-start;
  gap: 11px;
  padding: 21px 21px 0;
}

.section-icon {
  width: 37px;
  height: 37px;
  border: 1px solid rgb(37 99 235 / 10%);
  border-radius: 10px;
  background: rgb(37 99 235 / 7%);
  color: var(--primary-color);
}

.section-heading h2 {
  margin: 1px 0 3px;
  color: var(--text-primary-color);
  font-size: 14px;
  font-weight: 700;
}

.section-heading p {
  margin: 0;
  color: var(--text-secondary-color);
  font-size: 11px;
  line-height: 1.5;
}

.record-badge {
  flex: 0 0 auto;
  margin-left: auto;
  padding: 6px 9px;
  border: 1px solid var(--border-color);
  border-radius: 7px;
  background: #fafbfc;
  color: var(--text-secondary-color);
  font-size: 10px;
  font-weight: 650;
  font-variant-numeric: tabular-nums;
}

.table-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 18px 21px;
}

.search-box {
  display: flex;
  width: min(100%, 390px);
  min-width: 0;
  height: 39px;
  align-items: center;
  gap: 9px;
  padding: 0 11px;
  border: 1px solid var(--border-color);
  border-radius: 9px;
  background: var(--surface-color);
  color: var(--text-tertiary-color);
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.search-box:focus-within {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgb(37 99 235 / 8%);
}

.search-box input {
  width: 100%;
  min-width: 0;
  height: 100%;
  padding: 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--text-primary-color);
  font: inherit;
  font-size: 11px;
}

.search-box input::placeholder {
  color: var(--text-tertiary-color);
}

.clear-search {
  display: grid;
  flex: 0 0 auto;
  width: 25px;
  height: 25px;
  place-items: center;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: var(--text-tertiary-color);
  cursor: pointer;
}

.clear-search:hover {
  background: #f3f4f6;
  color: var(--text-primary-color);
}

.toolbar-right {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 8px;
}

.rows-label {
  color: var(--text-secondary-color);
  font-size: 11px;
  font-weight: 600;
}

.select-wrap {
  position: relative;
  display: flex;
  min-width: 70px;
  height: 35px;
  align-items: center;
}

.select-wrap select {
  width: 100%;
  height: 100%;
  padding: 0 28px 0 10px;
  appearance: none;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  outline: none;
  background: var(--surface-color);
  color: var(--text-primary-color);
  font: inherit;
  font-size: 11px;
  cursor: pointer;
}

.select-wrap select:focus-visible {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgb(37 99 235 / 8%);
}

.select-wrap > svg {
  position: absolute;
  right: 9px;
  pointer-events: none;
  color: var(--text-tertiary-color);
}

.table-container {
  width: 100%;
  overflow-x: auto;
  border-top: 1px solid var(--border-color);
  transition: opacity 0.18s ease;
}

.table-container.is-refreshing {
  opacity: 0.65;
}

table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  text-align: left;
  white-space: nowrap;
}

thead {
  background: #f8fafc;
}

th {
  padding: 12px 15px;
  border-bottom: 1px solid var(--border-color);
  color: var(--text-secondary-color);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.025em;
}

th:first-child,
td:first-child {
  padding-left: 21px;
}

th:last-child,
td:last-child {
  padding-right: 21px;
}

td {
  padding: 13px 15px;
  border-bottom: 1px solid #eef0f4;
  color: var(--text-secondary-color);
  font-size: 11px;
  vertical-align: middle;
}

tbody tr {
  transition: background 0.16s ease;
}

tbody tr:hover {
  background: #fafcff;
}

tbody tr:last-child td {
  border-bottom: 0;
}

.numeric-cell {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.quantity-cell,
.available-cell {
  color: var(--text-primary-color);
  font-weight: 700;
}

.muted-number {
  color: var(--text-secondary-color);
  font-variant-numeric: tabular-nums;
}

.product-cell {
  display: flex;
  min-width: 180px;
  align-items: center;
  gap: 10px;
}

.product-avatar {
  display: grid;
  width: 35px;
  height: 35px;
  flex: 0 0 auto;
  place-items: center;
  border: 1px solid var(--border-color);
  border-radius: 9px;
  background: #f8fafc;
  color: #64748b;
}

.product-details,
.location-cell {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 4px;
}

.product-details strong,
.location-cell strong {
  max-width: 240px;
  overflow: hidden;
  color: var(--text-primary-color);
  font-size: 11px;
  font-weight: 650;
  text-overflow: ellipsis;
}

.product-details span,
.location-cell span {
  color: var(--text-tertiary-color);
  font-size: 10px;
}

.product-details small {
  color: var(--text-secondary-color);
  font-size: 10px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 8px;
  border: 1px solid transparent;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 650;
}

.status-dot {
  width: 6px;
  height: 6px;
  flex: 0 0 auto;
  border-radius: 50%;
  background: currentColor;
}

.status-normal {
  border-color: #bbf7d0;
  background: #f0fdf4;
  color: #15803d;
}

.status-low {
  border-color: #fed7aa;
  background: #fff7ed;
  color: #c2410c;
}

.state-panel {
  display: flex;
  min-height: 245px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px 22px;
  text-align: center;
}

.state-panel h3 {
  margin: 12px 0 5px;
  color: var(--text-primary-color);
  font-size: 13px;
  font-weight: 700;
}

.state-panel p {
  max-width: 410px;
  margin: 0 0 16px;
  color: var(--text-secondary-color);
  font-size: 11px;
  line-height: 1.6;
}

.state-icon,
.loading-icon {
  display: grid;
  width: 48px;
  height: 48px;
  place-items: center;
  border: 1px solid var(--border-color);
  border-radius: 13px;
  background: #f8fafc;
  color: #64748b;
}

.error-icon {
  border-color: #fecaca;
  background: #fef2f2;
  color: #dc2626;
}

.loading-icon {
  border-color: rgb(37 99 235 / 12%);
  background: rgb(37 99 235 / 7%);
  color: var(--primary-color);
}

.compact-state {
  min-height: 205px;
}

.text-button {
  padding: 5px;
  border: 0;
  background: transparent;
  color: var(--primary-color);
  font: inherit;
  font-size: 11px;
  font-weight: 650;
  cursor: pointer;
}

.text-button:hover {
  text-decoration: underline;
}

.table-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 15px;
  padding: 15px 21px;
  border-top: 1px solid var(--border-color);
}

.pagination-summary {
  margin: 0;
  color: var(--text-secondary-color);
  font-size: 10px;
  line-height: 1.6;
}

.pagination-summary strong,
.page-indicator strong {
  color: var(--text-primary-color);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.filtered-note {
  color: var(--text-tertiary-color);
}

.pagination {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 10px;
}

.pagination-button {
  display: grid;
  width: 32px;
  height: 32px;
  place-items: center;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--surface-color);
  color: var(--text-secondary-color);
  cursor: pointer;
  transition: border-color 0.18s ease, background 0.18s ease, color 0.18s ease;
}

.pagination-button:hover:not(:disabled) {
  border-color: var(--primary-color);
  background: rgb(37 99 235 / 5%);
  color: var(--primary-color);
}

.pagination-button:disabled {
  cursor: not-allowed;
  opacity: 0.4;
}

.page-indicator {
  color: var(--text-secondary-color);
  font-size: 10px;
  white-space: nowrap;
}

.page-footnote {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  margin: 14px 2px 0;
  color: var(--text-tertiary-color);
  font-size: 10px;
  line-height: 1.6;
}

.page-footnote svg {
  flex: 0 0 auto;
  margin-top: 1px;
}

.spin {
  animation: spin 0.9s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@keyframes card-enter {
  from {
    opacity: 0;
    transform: translateY(5px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 1100px) {
  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .context-strip {
    gap: 11px;
  }

  .context-item div span {
    max-width: 180px;
  }
}

@media (max-width: 760px) {
  .page-header {
    flex-direction: column;
    gap: 12px;
  }

  .header-actions {
    padding-top: 0;
  }

  h1 {
    font-size: 23px;
  }

  .page-header p {
    font-size: 12px;
  }

  .context-strip {
    align-items: stretch;
    flex-direction: column;
    gap: 12px;
    padding: 13px;
  }

  .context-divider {
    width: 100%;
    height: 1px;
  }

  .context-item div span {
    max-width: none;
  }

  .stats-grid {
    gap: 11px;
  }

  .stat-card {
    padding: 14px;
  }

  .stat-value {
    font-size: 22px;
  }

  .section-heading {
    padding: 16px 15px 0;
  }

  .table-toolbar {
    align-items: stretch;
    flex-direction: column;
    padding: 15px;
  }

  .search-box {
    width: 100%;
  }

  .toolbar-right {
    justify-content: flex-end;
  }

  th:first-child,
  td:first-child {
    padding-left: 15px;
  }

  th:last-child,
  td:last-child {
    padding-right: 15px;
  }

  .table-footer {
    align-items: flex-start;
    flex-direction: column;
    padding: 14px 15px;
  }

  .pagination {
    align-self: flex-end;
  }

  .record-badge {
    font-size: 9px;
  }
}

@media (max-width: 430px) {
  .stats-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .stat-card {
    padding: 15px;
  }

  .record-badge {
    max-width: 90px;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .section-heading p {
    max-width: 240px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .inventory-page *,
  .inventory-page *::before,
  .inventory-page *::after {
    scroll-behavior: auto !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

/* Compact typography refinement aligned with Settings > Activity Logs. */
.inventory-page {
  font-size: 12px;
}
.breadcrumb {
  font-size: 10px;
}
.title-copy h1 {
  font-size: 20px;
}
.title-copy p {
  font-size: 11px;
}
.stat-value {
  font-size: 21px;
}
.stat-label {
  font-size: 11px;
}
.stat-description {
  font-size: 10px;
}
.section-heading-copy h2 {
  font-size: 13px;
}
.section-heading-copy p {
  font-size: 10px;
}
.button,
.text-button {
  font-size: 11px;
}
.table-toolbar input,
.table-toolbar select,
.rows-label {
  font-size: 10px;
}
table {
  font-size: 11px;
}
table th {
  font-size: 9px;
}
.product-details strong,
.location-cell strong {
  font-size: 11px;
}
.product-details small,
.location-cell small {
  font-size: 9px;
}
.table-footer,
.pagination {
  font-size: 10px;
}
.state-panel h3 {
  font-size: 12px;
}
.state-panel p {
  font-size: 10px;
}
@media (max-width: 720px) {
  .title-copy h1 {
    font-size: 18px;
  }
  .stat-value {
    font-size: 19px;
  }
}

/* Operations-specific layout, using the same Stock Overview design tokens */
.form-card {
  max-width: 1480px;
  margin: 0 auto;
  overflow: hidden;
  border: 1px solid var(--border-color);
  border-radius: 14px;
  background: var(--surface-color);
  box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
}
.form-card .section-heading {
  align-items: center;
  padding: 18px 21px;
  border-bottom: 1px solid var(--border-color);
}
.form-card .section-heading-copy h2 {
  margin: 0 0 3px;
  color: var(--text-primary-color);
  font-size: 14px;
  font-weight: 700;
}
.form-card .section-heading-copy p {
  margin: 0;
  color: var(--text-secondary-color);
  font-size: 11px;
  line-height: 1.5;
}
.operation-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  max-width: 1480px;
  margin: 0 auto 14px;
  padding: 5px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: var(--surface-color);
  box-shadow: 0 2px 8px rgb(16 24 40 / 2.5%);
}
.operation-tab {
  display: inline-flex;
  min-height: 36px;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 0 12px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  color: var(--text-secondary-color);
  font: inherit;
  font-size: 12px;
  font-weight: 650;
  cursor: pointer;
  transition: background 0.16s ease, color 0.16s ease, border-color 0.16s ease;
}
.operation-tab:hover {
  border-color: var(--border-color);
  background: #f9fafb;
  color: var(--text-primary-color);
}
.operation-tab.active {
  border-color: color-mix(in srgb, var(--primary-color) 20%, transparent);
  background: color-mix(in srgb, var(--primary-color) 9%, white);
  color: var(--primary-color);
}
.operation-form {
  padding: 21px;
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px 18px;
}
.field {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 6px;
}
.field > span {
  color: var(--text-primary-color);
  font-size: 12px;
  font-weight: 650;
}
.field b {
  color: #d14343;
}
.field em {
  margin-left: 4px;
  color: var(--text-tertiary-color);
  font-size: 10px;
  font-style: normal;
  font-weight: 500;
}
.field input,
.field select {
  display: block;
  width: 100%;
  min-width: 0;
  min-height: 40px;
  padding: 8px 11px;
  border: 1px solid var(--border-color);
  border-radius: 9px;
  outline: none;
  background: var(--surface-color);
  color: var(--text-primary-color);
  font-family: inherit;
  font-size: 12px;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.field input::placeholder {
  color: var(--text-tertiary-color);
}
.field input:focus,
.field select:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 10%, transparent);
}
.field input:disabled,
.field select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  background: #f9fafb;
}
.field small {
  color: var(--text-tertiary-color);
  font-size: 10px;
  line-height: 1.45;
}
.field .field-error {
  color: #b42318;
}
.operation-note {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 18px;
  padding: 11px 13px;
  border: 1px solid color-mix(in srgb, var(--primary-color) 17%, transparent);
  border-radius: 9px;
  background: color-mix(in srgb, var(--primary-color) 4%, white);
  color: var(--text-secondary-color);
  font-size: 11px;
  line-height: 1.55;
}
.operation-note :deep(svg) {
  flex-shrink: 0;
  margin-top: 1px;
  color: var(--primary-color);
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  margin-top: 18px;
  padding-top: 15px;
  border-top: 1px solid var(--border-color);
}
.button.primary {
  border-color: var(--primary-color);
  background: var(--primary-color);
  color: #fff;
}
.button.primary:hover:not(:disabled) {
  border-color: var(--primary-color);
  background: var(--primary-color);
  color: #fff;
  filter: brightness(0.93);
}
.notice {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 14px 21px 0;
  padding: 11px 13px;
  border-radius: 9px;
  font-size: 12px;
  line-height: 1.5;
}
.notice > span {
  flex: 1;
  min-width: 0;
}
.notice.error {
  border: 1px solid #f3d1cb;
  background: #fff4f2;
  color: #a22b20;
}
.notice.success {
  border: 1px solid #c8ead3;
  background: #effaf3;
  color: #17663a;
}
.notice-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 2px;
  border: 0;
  border-radius: 5px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}
.notice-close:hover {
  background: rgb(0 0 0 / 5%);
}
.state-panel.compact {
  display: flex;
  min-height: 100px;
  align-items: center;
  justify-content: center;
  gap: 9px;
  padding: 20px;
  color: var(--text-secondary-color);
  font-size: 12px;
}
.page-footnote {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  max-width: 1480px;
  margin: 14px auto 0;
  color: var(--text-tertiary-color);
  font-size: 11px;
  line-height: 1.55;
}
.page-footnote :deep(svg) {
  flex-shrink: 0;
  margin-top: 1px;
  color: var(--text-secondary-color);
}
@media (max-width: 760px) {
  .page-header {
    flex-direction: column;
    gap: 10px;
  }
  .header-actions {
    padding-top: 0;
  }
  .form-grid {
    grid-template-columns: 1fr;
  }
  .operation-tabs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .operation-tab {
    padding: 0 8px;
  }
  .form-card .section-heading,
  .operation-form {
    padding: 15px;
  }
  .notice {
    margin-right: 15px;
    margin-left: 15px;
  }
}
@media (max-width: 430px) {
  .form-actions {
    flex-wrap: wrap;
  }
  .form-actions .button {
    flex: 1 1 auto;
  }
}
</style>
