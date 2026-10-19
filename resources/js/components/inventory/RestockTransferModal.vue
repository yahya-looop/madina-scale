<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import Icon from "@/components/dashboard/Icon.vue";

interface StockRow {
  id: number;
  product_id?: number | null;
  product_variant_id?: number | null;
  variant_id?: number | null;
  location_id?: number | null;
  quantity: string | number;
  reserved_quantity: string | number;
  available_quantity?: string | number;
  low_stock_threshold?: string | number;
  product?: { id?: number; name?: string | null; sku?: string | null } | null;
  variant?: { id?: number; name?: string | null; sku?: string | null } | null;
  location?: { id?: number; name?: string | null; code?: string | null } | null;
}
interface LocationOption {
  id: number;
  name: string;
  code?: string | null;
  is_active?: boolean;
}
interface ApiPayload<T> {
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
  current_page?: number;
  total?: number;
}
const props = defineProps<{ stock: StockRow }>();
const emit = defineEmits<{ close: []; completed: [message: string] }>();

const locations = ref<LocationOption[]>([]);
const sourceLocationId = ref("");
const sourceStock = ref<StockRow | null>(null);
const sourceLoading = ref(false);
const locationsLoading = ref(true);
const submitting = ref(false);
const quantity = ref("1");
const reason = ref("");
const error = ref("");
const locationsError = ref("");
let sourceRequestSequence = 0;

function number(value: string | number | null | undefined): number {
  const n = Number(value ?? 0);
  return Number.isFinite(n) ? n : 0;
}
function formatQuantity(value: string | number | null | undefined): string {
  return new Intl.NumberFormat("en-BD", { maximumFractionDigits: 4 }).format(
    number(value)
  );
}
function getAvailable(row: StockRow | null): number {
  if (!row) return 0;
  return row.available_quantity !== undefined
    ? number(row.available_quantity)
    : number(row.quantity) - number(row.reserved_quantity);
}
const destinationAvailable = computed(() => getAvailable(props.stock));
const sourceAvailable = computed(() => getAvailable(sourceStock.value));
const itemId = computed(
  () => props.stock.product_variant_id ?? props.stock.variant_id ?? null
);
const itemLabel = computed(() => {
  const product = props.stock.product?.name ?? "Product not linked";
  const variant = props.stock.variant?.name;
  return variant ? `${product} · ${variant}` : product;
});
const destinationLabel = computed(
  () => props.stock.location?.name ?? `Location #${props.stock.location_id ?? "?"}`
);
const sourceLocation = computed(
  () =>
    locations.value.find((location) => String(location.id) === sourceLocationId.value) ??
    null
);
const parsedQuantity = computed(() => Number(String(quantity.value ?? "").trim()));
const validQuantity = computed(
  () =>
    /^\d{1,14}(?:\.\d{1,4})?$/.test(String(quantity.value ?? "").trim()) &&
    Number.isFinite(parsedQuantity.value) &&
    parsedQuantity.value > 0
);
const enoughSourceStock = computed(
  () =>
    !!sourceLocationId.value &&
    !!sourceStock.value &&
    validQuantity.value &&
    parsedQuantity.value <= sourceAvailable.value
);
const canSubmit = computed(
  () =>
    !submitting.value &&
    !locationsLoading.value &&
    !sourceLoading.value &&
    !!props.stock.location_id &&
    !!(props.stock.product_id || itemId.value) &&
    !!sourceLocationId.value &&
    sourceLocationId.value !== String(props.stock.location_id) &&
    validQuantity.value &&
    enoughSourceStock.value
);

function xsrfToken(): string {
  const cookie = document.cookie
    .split("; ")
    .find((entry) => entry.startsWith("XSRF-TOKEN="));
  if (!cookie) return "";
  try {
    return decodeURIComponent(cookie.slice("XSRF-TOKEN=".length));
  } catch {
    return cookie.slice("XSRF-TOKEN=".length);
  }
}
async function readPayload<T>(response: Response): Promise<ApiPayload<T>> {
  return (await response.json()) as ApiPayload<T>;
}
function firstError(payload: ApiPayload<unknown>): string | null {
  const value = payload.errors ? Object.values(payload.errors).flat()[0] : null;
  return value ? String(value) : null;
}
async function loadLocations(): Promise<void> {
  locationsLoading.value = true;
  locationsError.value = "";
  try {
    const response = await fetch("/api/locations?active=1", {
      credentials: "same-origin",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const payload = await readPayload<LocationOption[]>(response);
    if (!response.ok)
      throw new Error(
        payload.message || `Locations request failed (HTTP ${response.status}).`
      );
    locations.value = Array.isArray(payload.data)
      ? payload.data.filter(
          (location) =>
            location.is_active !== false && location.id !== props.stock.location_id
        )
      : [];
    if (!locations.value.length)
      locationsError.value =
        "No other active inventory locations are available as a source.";
  } catch (cause) {
    locationsError.value =
      cause instanceof Error ? cause.message : "Unable to load locations.";
  } finally {
    locationsLoading.value = false;
  }
}
async function loadSourceStock(): Promise<void> {
  const requestId = ++sourceRequestSequence;
  sourceStock.value = null;
  error.value = "";
  if (!sourceLocationId.value) {
    sourceLoading.value = false;
    return;
  }
  if (!props.stock.product_id && !itemId.value) {
    error.value = "This inventory row is not linked to a product or variant.";
    return;
  }
  sourceLoading.value = true;
  try {
    const params = new URLSearchParams({
      location_id: sourceLocationId.value,
      per_page: "100",
    });
    if (props.stock.product_id) params.set("product_id", String(props.stock.product_id));
    else params.set("product_variant_id", String(itemId.value));
    const response = await fetch(`/api/inventory/stocks?${params.toString()}`, {
      credentials: "same-origin",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const payload = await readPayload<StockRow[]>(response);
    if (!response.ok)
      throw new Error(
        payload.message || `Source stock request failed (HTTP ${response.status}).`
      );
    if (requestId !== sourceRequestSequence) return;
    const rows = Array.isArray(payload.data) ? payload.data : [];
    sourceStock.value =
      rows.find((row) => Number(row.location_id) === Number(sourceLocationId.value)) ??
      null;
  } catch (cause) {
    if (requestId !== sourceRequestSequence) return;
    error.value =
      cause instanceof Error ? cause.message : "Unable to check source stock.";
  } finally {
    if (requestId === sourceRequestSequence) sourceLoading.value = false;
  }
}
watch(sourceLocationId, () => void loadSourceStock());
watch(
  () => props.stock.id,
  () => {
    sourceLocationId.value = "";
    sourceStock.value = null;
    quantity.value = "1";
    reason.value = "";
    error.value = "";
  }
);
onMounted(() => void loadLocations());

async function submit(): Promise<void> {
  error.value = "";
  if (!validQuantity.value) {
    error.value = "Enter a positive quantity with up to 4 decimal places.";
    return;
  }
  if (!sourceLocationId.value) {
    error.value = "Select the source location.";
    return;
  }
  if (sourceLocationId.value === String(props.stock.location_id)) {
    error.value = "Source and destination locations must be different.";
    return;
  }
  if (sourceLoading.value) {
    error.value = "Wait until source stock validation finishes.";
    return;
  }
  if (!sourceStock.value || sourceAvailable.value <= 0) {
    error.value =
      "There is no available stock for this product at the selected source location.";
    return;
  }
  if (parsedQuantity.value > sourceAvailable.value) {
    error.value = `Insufficient source stock. Available: ${formatQuantity(
      sourceAvailable.value
    )}; requested: ${formatQuantity(parsedQuantity.value)}.`;
    return;
  }
  if (!props.stock.location_id || (!props.stock.product_id && !itemId.value)) {
    error.value =
      "This destination stock row is missing its product, variant, or location link.";
    return;
  }

  submitting.value = true;
  try {
    const body: Record<string, unknown> = {
      source_location_id: Number(sourceLocationId.value),
      destination_location_id: Number(props.stock.location_id),
      quantity: String(quantity.value).trim(),
      reason: reason.value.trim() || `Restock transfer to ${destinationLabel.value}`,
      reference_type: "inventory_restock",
      reference_id: String(props.stock.id),
      idempotency_key: crypto.randomUUID(),
      metadata: { workflow: "restock_modal", destination_stock_id: props.stock.id },
    };
    if (props.stock.product_id) body.product_id = props.stock.product_id;
    else body.product_variant_id = itemId.value;
    const response = await fetch("/api/inventory/transfer", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": xsrfToken(),
      },
      body: JSON.stringify(body),
    });
    const payload = await readPayload<unknown>(response);
    if (!response.ok)
      throw new Error(
        firstError(payload) ||
          payload.message ||
          `Restock transfer failed (HTTP ${response.status}).`
      );
    emit(
      "completed",
      `Transferred ${formatQuantity(quantity.value)} ${itemLabel.value} from ${
        sourceLocation.value?.name ?? "source location"
      } to ${destinationLabel.value}.`
    );
    emit("close");
  } catch (cause) {
    error.value =
      cause instanceof Error ? cause.message : "Unable to complete restock transfer.";
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      class="restock-dialog-backdrop"
      @click.self="!submitting && emit('close')"
      @keydown.esc="!submitting && emit('close')"
      tabindex="-1"
    >
      <section
        class="restock-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="restock-dialog-title"
      >
        <header class="restock-dialog-header">
          <div class="restock-dialog-icon"><Icon name="repeat-once" :size="23" /></div>
          <div class="restock-dialog-heading">
            <span class="restock-eyebrow">INVENTORY OPERATIONS · STOCK TRANSFER</span>
            <h2 id="restock-dialog-title">Restock item</h2>
            <p>
              {{ itemLabel }} <span class="restock-dot">·</span>
              {{
                props.stock.variant?.sku ??
                props.stock.product?.sku ??
                "SKU not available"
              }}
            </p>
          </div>
          <button
            type="button"
            class="restock-close"
            aria-label="Close restock modal"
            :disabled="submitting"
            @click="emit('close')"
          >
            <Icon name="x" :size="19" />
          </button>
        </header>

        <div class="restock-dialog-body">
          <div class="restock-route" aria-label="Stock transfer route">
            <div class="restock-route-card">
              <span class="restock-route-label"
                ><Icon name="warehouse" :size="14" /> SOURCE LOCATION</span
              >
              <label class="sr-only" for="restock-source-location">Source location</label>
              <select
                id="restock-source-location"
                v-model="sourceLocationId"
                :disabled="submitting || locationsLoading"
                required
              >
                <option value="">
                  {{
                    locationsLoading ? "Loading locations…" : "Choose source warehouse"
                  }}
                </option>
                <option
                  v-for="location in locations"
                  :key="location.id"
                  :value="String(location.id)"
                >
                  {{ location.name }}{{ location.code ? ` · ${location.code}` : "" }}
                </option>
              </select>
              <div class="restock-route-balance">
                <span>{{
                  sourceLoading ? "Checking available stock…" : "Available to transfer"
                }}</span>
                <strong>{{
                  sourceLoading ? "—" : formatQuantity(sourceAvailable)
                }}</strong>
              </div>
              <p
                v-if="sourceLocationId && !sourceLoading && !sourceStock"
                class="restock-muted"
              >
                No stock record at this location. Available quantity is 0.
              </p>
              <p v-else-if="sourceStock" class="restock-muted">
                On hand {{ formatQuantity(sourceStock.quantity) }} · Reserved
                {{ formatQuantity(sourceStock.reserved_quantity) }}
              </p>
            </div>
            <div class="restock-route-arrow">
              <Icon name="arrow-right" :size="19" />
            </div>
            <div class="restock-route-card destination">
              <span class="restock-route-label"
                ><Icon name="map-pin" :size="14" /> DESTINATION LOCATION</span
              >
              <strong class="restock-destination-name">{{ destinationLabel }}</strong>
              <small>{{
                props.stock.location?.code ?? "Selected inventory location"
              }}</small>
              <div class="restock-route-balance">
                <span>Current available</span>
                <strong>{{ formatQuantity(destinationAvailable) }}</strong>
              </div>
              <p class="restock-muted">
                Threshold {{ formatQuantity(props.stock.low_stock_threshold ?? 0) }}
              </p>
            </div>
          </div>

          <div v-if="locationsError" class="restock-notice warning">
            <Icon name="alert-circle" :size="16" /> {{ locationsError }}
          </div>
          <div
            v-if="
              sourceLocationId &&
              !sourceLoading &&
              sourceStock &&
              validQuantity &&
              parsedQuantity > sourceAvailable
            "
            class="restock-notice warning"
          >
            <Icon name="alert-circle" :size="16" /> Insufficient source stock. You
            requested {{ formatQuantity(parsedQuantity) }}, but only
            {{ formatQuantity(sourceAvailable) }} is available.
          </div>
          <div class="restock-form-grid">
            <label class="restock-field">
              <span>Quantity to transfer <b>*</b></span>
              <input
                v-model="quantity"
                type="number"
                inputmode="decimal"
                min="0.0001"
                max="99999999999999"
                step="0.0001"
                required
                :disabled="submitting"
              />
              <small
                >Maximum 4 decimal places. Reserved stock cannot be transferred.</small
              >
            </label>
            <label class="restock-field">
              <span>Reason / reference <small>(optional)</small></span>
              <input
                v-model="reason"
                type="text"
                maxlength="255"
                placeholder="e.g. Replenishment, transfer note…"
                :disabled="submitting"
              />
              <small>Recorded in both stock movement ledger entries.</small>
            </label>
          </div>
          <div class="restock-ledger-note">
            <Icon name="shield-check" :size="17" /><span
              ><strong>Validated stock transfer</strong
              ><small
                >Source available stock is checked before submission and re-validated by
                the server. Source and destination movements are recorded
                atomically.</small
              ></span
            >
          </div>
          <p v-if="error" class="restock-error" role="alert">
            <Icon name="alert-circle" :size="16" /> {{ error }}
          </p>
        </div>

        <footer class="restock-dialog-footer">
          <span class="restock-footer-hint">No new product will be created.</span>
          <div class="restock-footer-actions">
            <button
              type="button"
              class="restock-btn secondary"
              :disabled="submitting"
              @click="emit('close')"
            >
              Cancel
            </button>
            <button
              type="button"
              class="restock-btn primary"
              :disabled="!canSubmit"
              @click="submit"
            >
              <Icon v-if="submitting" name="loader-2" :size="16" class="restock-spin" />
              <Icon v-else name="file-plus" :size="16" />
              {{ submitting ? "Transferring…" : "Confirm restock" }}
            </button>
          </div>
        </footer>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.restock-dialog-backdrop {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: grid;
  place-items: center;
  padding: 22px;
  background: rgba(15, 23, 42, 0.58);
  backdrop-filter: blur(7px);
  -webkit-backdrop-filter: blur(7px);
}
.restock-dialog {
  width: min(880px, 100%);
  max-height: min(92vh, 900px);
  overflow: auto;
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 22px;
  background: #fff;
  color: #111827;
  box-shadow: 0 32px 100px rgba(15, 23, 42, 0.3);
  animation: restock-enter 0.18s ease-out;
}
.restock-dialog-header {
  position: sticky;
  top: 0;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 23px 26px;
  border-bottom: 1px solid #e8edf4;
  background: rgba(255, 255, 255, 0.97);
  backdrop-filter: blur(10px);
}
.restock-dialog-icon {
  display: grid;
  place-items: center;
  flex: 0 0 48px;
  height: 48px;
  border-radius: 15px;
  background: linear-gradient(145deg, #e0f2fe, #dbeafe);
  color: #1d4ed8;
}
.restock-dialog-heading {
  min-width: 0;
  flex: 1;
}
.restock-eyebrow {
  display: block;
  margin-bottom: 5px;
  color: #64748b;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
}
.restock-dialog-heading h2 {
  margin: 0;
  color: #111827;
  font-size: 23px;
  font-weight: 800;
  letter-spacing: -0.04em;
}
.restock-dialog-heading p {
  margin: 5px 0 0;
  color: #64748b;
  font-size: 12px;
}
.restock-dot {
  padding: 0 3px;
  color: #cbd5e1;
}
.restock-close {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  color: #64748b;
  cursor: pointer;
}
.restock-close:hover {
  background: #f8fafc;
  color: #0f172a;
}
.restock-close:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.restock-dialog-body {
  padding: 22px 26px 24px;
}
.restock-route {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 32px minmax(0, 1fr);
  gap: 10px;
  align-items: stretch;
}
.restock-route-card {
  min-width: 0;
  padding: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  background: #f8fafc;
}
.restock-route-card.destination {
  border-color: #bfdbfe;
  background: linear-gradient(145deg, #eff6ff, #f8fbff);
}
.restock-route-label {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-bottom: 12px;
  color: #64748b;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.09em;
}
.restock-route-label svg {
  color: #2563eb;
}
.restock-route-card select {
  width: 100%;
  min-height: 42px;
  padding: 0 11px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  background: #fff;
  color: #0f172a;
  font: inherit;
  font-size: 12px;
  outline: none;
}
.restock-route-card select:focus,
.restock-field input:focus {
  border-color: #60a5fa;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.13);
}
.restock-route-balance {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-top: 13px;
  padding-top: 12px;
  border-top: 1px solid #e2e8f0;
}
.restock-route-balance span {
  color: #64748b;
  font-size: 11px;
}
.restock-route-balance strong {
  color: #0f172a;
  font-size: 20px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}
.restock-route-arrow {
  display: grid;
  place-items: center;
  color: #2563eb;
}
.restock-destination-name {
  display: block;
  min-height: 42px;
  padding: 9px 0 2px;
  color: #0f172a;
  font-size: 15px;
  font-weight: 800;
  overflow-wrap: anywhere;
}
.restock-route-card.destination > small {
  display: block;
  min-height: 14px;
  color: #64748b;
  font-size: 10px;
}
.restock-muted {
  margin: 8px 0 0;
  color: #64748b;
  font-size: 10px;
  line-height: 1.5;
}
.restock-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  margin-top: 20px;
}
.restock-field {
  display: grid;
  align-content: start;
  gap: 8px;
  min-width: 0;
}
.restock-field > span {
  color: #334155;
  font-size: 12px;
  font-weight: 750;
}
.restock-field b {
  color: #dc2626;
}
.restock-field > span small {
  color: #94a3b8;
  font-weight: 500;
}
.restock-field input {
  width: 100%;
  height: 44px;
  padding: 0 12px;
  border: 1px solid #dbe3ee;
  border-radius: 11px;
  background: #fff;
  color: #0f172a;
  font: inherit;
  font-size: 13px;
  outline: none;
}
.restock-field input:disabled {
  background: #f8fafc;
}
.restock-field > small {
  color: #94a3b8;
  font-size: 10px;
  line-height: 1.5;
}
.restock-ledger-note {
  display: flex;
  gap: 11px;
  margin-top: 18px;
  padding: 13px 14px;
  border: 1px solid #bbf7d0;
  border-radius: 13px;
  background: #f0fdf4;
  color: #047857;
}
.restock-ledger-note > svg {
  flex: 0 0 auto;
  margin-top: 1px;
}
.restock-ledger-note span {
  display: grid;
  gap: 3px;
}
.restock-ledger-note strong {
  font-size: 11px;
}
.restock-ledger-note small {
  color: #4b7c68;
  font-size: 10px;
  line-height: 1.5;
}
.restock-notice,
.restock-error {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 14px 0 0;
  padding: 11px 12px;
  border-radius: 11px;
  font-size: 11px;
  line-height: 1.5;
}
.restock-notice.warning {
  border: 1px solid #fed7aa;
  background: #fff7ed;
  color: #9a3412;
}
.restock-error {
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}
.restock-error svg,
.restock-notice svg {
  flex: 0 0 auto;
  margin-top: 1px;
}
.restock-dialog-footer {
  position: sticky;
  bottom: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  padding: 17px 26px;
  border-top: 1px solid #e8edf4;
  background: rgba(255, 255, 255, 0.97);
  backdrop-filter: blur(10px);
}
.restock-footer-hint {
  color: #64748b;
  font-size: 10px;
}
.restock-footer-actions {
  display: flex;
  align-items: center;
  gap: 9px;
}
.restock-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 41px;
  padding: 0 15px;
  border: 1px solid transparent;
  border-radius: 11px;
  font: inherit;
  font-size: 12px;
  font-weight: 750;
  cursor: pointer;
  transition: background 0.15s, transform 0.15s, box-shadow 0.15s;
}
.restock-btn.secondary {
  border-color: #dbe3ee;
  background: #fff;
  color: #334155;
}
.restock-btn.secondary:hover {
  background: #f8fafc;
}
.restock-btn.primary {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff;
  box-shadow: 0 5px 14px rgba(37, 99, 235, 0.2);
}
.restock-btn.primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 18px rgba(37, 99, 235, 0.26);
}
.restock-btn:disabled {
  cursor: not-allowed;
  opacity: 0.48;
  box-shadow: none;
}
.restock-spin {
  animation: restock-spin 1s linear infinite;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
@keyframes restock-spin {
  to {
    transform: rotate(360deg);
  }
}
@keyframes restock-enter {
  from {
    opacity: 0;
    transform: translateY(8px) scale(0.99);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
@media (max-width: 680px) {
  .restock-dialog-backdrop {
    padding: 10px;
    align-items: end;
  }
  .restock-dialog {
    max-height: 94vh;
    border-radius: 20px 20px 14px 14px;
  }
  .restock-dialog-header {
    padding: 18px 16px;
    gap: 11px;
  }
  .restock-dialog-icon {
    flex-basis: 42px;
    height: 42px;
  }
  .restock-dialog-heading h2 {
    font-size: 20px;
  }
  .restock-dialog-heading p {
    font-size: 11px;
  }
  .restock-dialog-body {
    padding: 16px;
  }
  .restock-route {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .restock-route-arrow {
    height: 22px;
    transform: rotate(90deg);
  }
  .restock-form-grid {
    grid-template-columns: 1fr;
    gap: 15px;
    margin-top: 16px;
  }
  .restock-dialog-footer {
    align-items: stretch;
    padding: 14px 16px;
    flex-direction: column;
  }
  .restock-footer-hint {
    display: none;
  }
  .restock-footer-actions {
    display: grid;
    grid-template-columns: 0.7fr 1.3fr;
  }
  .restock-btn {
    padding: 0 10px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .restock-dialog {
    animation: none;
  }
  .restock-btn {
    transition: none;
  }
}
</style>
