<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Props {
  userName?: string;
  userEmail?: string;
}
type Operation = "receive" | "issue" | "adjust" | "transfer" | "restock";
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
  access_level: "view" | "receive" | "manage";
}
interface LowStockRow {
  id: number;
  product_id?: number | null;
  product_variant_id?: number | null;
  variant_id?: number | null;
  location_id: number;
  quantity: string | number;
  reserved_quantity: string | number;
  available_quantity?: string | number;
  low_stock_threshold: string | number;
  product?: {
    id: number;
    name: string;
    sku?: string | null;
    product_type?: string;
  } | null;
  variant?: { id: number; name?: string | null; sku?: string | null } | null;
  location?: { id: number; name: string; code?: string | null } | null;
}
interface TransferRow {
  id: number;
  transfer_uuid: string;
  status: string;
  quantity_sent: string | number;
  quantity_received: string | number;
  reason?: string | null;
  dispatched_at?: string | null;
  received_at?: string | null;
  product?: { name?: string; sku?: string | null } | null;
  variant?: { name?: string | null; sku?: string | null } | null;
  source_location?: { name?: string; code?: string | null } | null;
  destination_location?: { name?: string; code?: string | null } | null;
  dispatcher?: { name?: string } | null;
  receiver?: { name?: string } | null;
}
interface ApiPayload<T> {
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
  current_page?: number;
  last_page?: number;
  total?: number;
}
const props = withDefaults(defineProps<Props>(), {
  userName: "",
  userEmail: "",
});

const operation = ref<Operation>("receive");
const products = ref<Product[]>([]);
const locations = ref<Location[]>([]);
const receivableLocations = computed(() =>
  locations.value.filter((location) =>
    ["receive", "manage"].includes(location.access_level)
  )
);
const manageableLocations = computed(() =>
  locations.value.filter((location) => location.access_level === "manage")
);
const operationLocations = computed(() =>
  operation.value === "receive"
    ? receivableLocations.value
    : manageableLocations.value
);
const variants = ref<Variant[]>([]);
const lowStockRows = ref<LowStockRow[]>([]);
const transferRows = ref<TransferRow[]>([]);
const loadingTransfers = ref(false);
const transferActionId = ref<number | null>(null);
const transferDialogRow = ref<TransferRow | null>(null);
const transferDialogAction = ref<"accept" | "reject" | "resolve" | null>(null);
let transferReceiptIdempotencyKey: string | null = null;
const transferReceiptKeyStoragePrefix = "madina-scale:inventory-transfer-receipt:";

function getOrCreateTransferReceiptKey(transferId: number): string {
  const storageKey = `${transferReceiptKeyStoragePrefix}${transferId}`;
  const existingKey = window.sessionStorage.getItem(storageKey);
  if (existingKey) return existingKey;

  const newKey = crypto.randomUUID();
  window.sessionStorage.setItem(storageKey, newKey);
  return newKey;
}

function clearTransferReceiptKey(transferId: number): void {
  window.sessionStorage.removeItem(`${transferReceiptKeyStoragePrefix}${transferId}`);
}
const discrepancyReason = ref("");
const discrepancyResolution = ref<"receive_remaining" | "return_to_source" | "write_off">("receive_remaining");
const incomingPendingCount = ref(0);
const outgoingPendingCount = ref(0);
const visibleTransferRows = computed(() =>
  transferRows.value.filter((row) =>
    ["in_transit", "partially_received"].includes(row.status) ||
    (operation.value === "transfer" && row.status === "discrepancy")
  )
);
const restockStockId = ref("");
const loadingLowStock = ref(false);
const sourceStockRow = ref<LowStockRow | null>(null);
const loadingSourceStock = ref(false);
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
const adjustmentConfirmOpen = ref(false);
const adjustmentConfirmed = ref(false);
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
const selectedRestockStock = computed(
  () => lowStockRows.value.find((row) => String(row.id) === restockStockId.value) ?? null
);
const sourceAvailableQuantity = computed(() =>
  sourceStockRow.value
    ? numeric(
        sourceStockRow.value.available_quantity ??
          numeric(sourceStockRow.value.quantity) -
            numeric(sourceStockRow.value.reserved_quantity)
      )
    : 0
);
const quantityExceedsSource = computed(
  () =>
    operation.value === "restock" &&
    !!sourceLocationId.value &&
    !!quantity.value &&
    numeric(quantity.value) > sourceAvailableQuantity.value
);
const destinationLocation = computed(
  () =>
    locations.value.find(
      (location) =>
        String(location.id) === String(selectedRestockStock.value?.location_id ?? "")
    ) ??
    selectedRestockStock.value?.location ??
    null
);
const lowStockProductLabel = (row: LowStockRow): string => {
  const name =
    row.product?.name ??
    `Product #${row.product_id ?? row.variant?.id ?? row.product_variant_id}`;
  const variant = row.variant?.name ? ` — ${row.variant.name}` : "";
  return `${name}${variant} · Available ${qty(
    row.available_quantity ?? numeric(row.quantity) - numeric(row.reserved_quantity)
  )} · Threshold ${qty(row.low_stock_threshold)}`;
};
const restockCardSpanClass = (index: number): string => {
  const count = lowStockRows.value.length;
  if (count <= 1) return "restock-span-full";
  if (count === 2) return "restock-span-half";
  const remainder = count % 3;
  if (remainder === 1 && index === count - 1) return "restock-span-full";
  if (remainder === 2 && index >= count - 2) return "restock-span-half";
  return "restock-span-third";
};
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
        endpoint: "/api/v1/backend/inventory/receive",
        permission: "system.inventory.receive",
      },
      issue: {
        title: "Stock Out",
        description:
          "Record stock leaving a location for internal use or other approved reasons.",
        icon: "stock-out",
        endpoint: "/api/v1/backend/inventory/issue",
        permission: "system.inventory.issue",
      },
      adjust: {
        title: "Adjust Stock",
        description: "Apply a signed correction to the stock balance.",
        icon: "sliders-horizontal",
        endpoint: "/api/v1/backend/inventory/adjust",
        permission: "system.inventory.adjust",
      },
      transfer: {
        title: "Transfer Stock",
        description: "Move inventory atomically between two locations.",
        icon: "arrows-left-right",
        endpoint: "/api/v1/backend/inventory/transfers",
        permission: "system.inventory.transfer",
      },
      restock: {
        title: "Restock Low-Stock Item",
        description:
          "Transfer an item from a source warehouse to a location where it is low in stock.",
        icon: "repeat-once",
        endpoint: "/api/v1/backend/inventory/restock",
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
  restockStockId.value = "";
  sourceStockRow.value = null;
  loadingSourceStock.value = false;
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
async function loadTransferCounts(): Promise<void> {
  try {
    const response = await fetch("/api/v1/backend/inventory/transfers/counts", {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });
    if (!response.ok) return;
    const counts = (await response.json()) as {
      incoming_pending?: number;
      outgoing_pending?: number;
    };
    incomingPendingCount.value = Number(counts.incoming_pending ?? 0);
    outgoingPendingCount.value = Number(counts.outgoing_pending ?? 0);
  } catch {
    /* Counts are supplementary; keep the operations page usable if unavailable. */
  }
}

async function loadTransfers(): Promise<void> {
  if (!["receive", "transfer", "restock"].includes(operation.value)) {
    transferRows.value = [];
    return;
  }
  loadingTransfers.value = true;
  try {
    const direction = operation.value === "receive" ? "incoming" : "outgoing";
    const params = new URLSearchParams({ direction, per_page: "50" });
    const response = await fetch(`/api/v1/backend/inventory/transfers?${params}`, {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });
    const payload = await readApiPayload<TransferRow[]>(response);
    if (!response.ok)
      throw new Error(payload.message || `Unable to load ${direction} transfers.`);
    transferRows.value = Array.isArray(payload.data) ? payload.data : [];
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Unable to load transfer requests.";
    transferRows.value = [];
  } finally {
    loadingTransfers.value = false;
  }
}

function openTransferDialog(row: TransferRow, action: "accept" | "reject" | "resolve"): void {
  clearNotices();
  transferDialogRow.value = row;
  transferDialogAction.value = action;
  transferReceiptIdempotencyKey =
    action === "accept" ? getOrCreateTransferReceiptKey(row.id) : null;
  discrepancyReason.value = "";
  discrepancyResolution.value = "receive_remaining";
}

function closeTransferDialog(): void {
  if (transferActionId.value !== null) return;
  transferDialogRow.value = null;
  transferDialogAction.value = null;
  // Keep the receipt key so a retry after closing/reopening the dialog or refreshing
  // this tab reuses the same idempotency key if the previous response was lost.
  transferReceiptIdempotencyKey = null;
  discrepancyReason.value = "";
  discrepancyResolution.value = "receive_remaining";
}

async function confirmTransferAction(): Promise<void> {
  const row = transferDialogRow.value;
  const action = transferDialogAction.value;
  if (!row || !action || transferActionId.value !== null) return;

  const reasonText = discrepancyReason.value.trim();
  if (action !== "accept" && reasonText.length < 3) {
    error.value = action === "resolve"
      ? "Please provide a reconciliation note of at least 3 characters."
      : "Please describe the issue in at least 3 characters.";
    return;
  }

  if (action === "accept" && !transferReceiptIdempotencyKey) {
    error.value =
      "Receipt request key is missing. Close and reopen the dialog, then try again.";
    return;
  }

  transferActionId.value = row.id;
  clearNotices();
  try {
    const endpointAction = action === "resolve" ? "resolve-discrepancy" : action;
    const response = await fetch(`/api/v1/backend/inventory/transfers/${row.id}/${endpointAction}`, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": getXsrfToken(),
      },
      body: JSON.stringify(
        action === "accept"
          ? {
              quantity_received: String(
                numeric(row.quantity_sent) - numeric(row.quantity_received)
              ),
              idempotency_key: transferReceiptIdempotencyKey,
            }
          : action === "resolve"
            ? { resolution_action: discrepancyResolution.value, resolution_note: reasonText }
            : { reason: reasonText }
      ),
    });
    const payload = await readApiPayload<TransferRow>(response);
    if (!response.ok) throw new Error(payload.message || `Transfer ${action} failed.`);
    success.value =
      payload.message ||
      (action === "accept" ? "Transfer received." : action === "resolve" ? "Transfer discrepancy reconciled." : "Transfer flagged for review.");
    transferDialogRow.value = null;
    transferDialogAction.value = null;
    if (action === "accept") clearTransferReceiptKey(row.id);
    transferReceiptIdempotencyKey = null;
    discrepancyReason.value = "";
    discrepancyResolution.value = "receive_remaining";
    await Promise.all([loadTransfers(), loadTransferCounts()]);
    window.dispatchEvent(
      new CustomEvent("inventory:operation-complete", {
        detail: { operation: "transfer", action },
      })
    );
  } catch (e) {
    error.value = e instanceof Error ? e.message : `Unable to ${action} transfer.`;
  } finally {
    transferActionId.value = null;
  }
}

async function loadOptions(): Promise<void> {
  loadingOptions.value = true;
  error.value = "";

  // Do not leave a previously loaded catalogue visible if a refresh fails.
  // Product and location eligibility must always come from the current API response.
  products.value = [];
  locations.value = [];
  productId.value = "";
  variantId.value = "";
  variants.value = [];

  try {
    const [productResponse, locationResponse] = await Promise.all([
      fetch(`/api/v1/backend/products?status=active&_=${Date.now()}`, {
        method: "GET",
        cache: "no-store",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Cache-Control": "no-cache",
          Pragma: "no-cache",
          "X-Requested-With": "XMLHttpRequest",
        },
      }),
      fetch(`/api/v1/backend/inventory/locations?_=${Date.now()}`, {
        method: "GET",
        cache: "no-store",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "Cache-Control": "no-cache",
          Pragma: "no-cache",
          "X-Requested-With": "XMLHttpRequest",
        },
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
          `Inventory locations request failed (HTTP ${locationResponse.status}).`
      );
    products.value = Array.isArray(productPayload.data)
      ? Array.from(
          new Map(
            productPayload.data
              .filter((p) => p && Number.isFinite(Number(p.id)) && Number(p.id) > 0)
              .filter((p) => !p.status || p.status === "active")
              .map((p) => [Number(p.id), { ...p, id: Number(p.id) }])
          ).values()
        )
      : [];
    locations.value = Array.isArray(locationPayload.data)
      ? locationPayload.data.filter((l) => l.is_active !== false)
      : [];
    if (!products.value.length)
      error.value =
        "No active products are available, or your account cannot view products.";
    else if (!locations.value.length)
      error.value =
        "No active locations are assigned to your account for inventory access.";
  } catch (e) {
    products.value = [];
    locations.value = [];
    productId.value = "";
    variantId.value = "";
    variants.value = [];
    error.value =
      e instanceof Error ? e.message : "Unable to load products and locations.";
  } finally {
    loadingOptions.value = false;
  }
}
async function loadLowStock(): Promise<void> {
  if (operation.value !== "restock") {
    lowStockRows.value = [];
    restockStockId.value = "";
    sourceStockRow.value = null;
    return;
  }
  loadingLowStock.value = true;
  error.value = "";
  try {
    const allRows: LowStockRow[] = [];
    let page = 1;
    let lastPage = 1;
    do {
      const params = new URLSearchParams({
        per_page: "100",
        page: String(page),
      });
      const response = await fetch(`/api/v1/backend/inventory/low-stock?${params}`, {
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const payload = await readApiPayload<LowStockRow[]>(response);
      if (!response.ok)
        throw new Error(
          payload.message || `Unable to load low-stock items (HTTP ${response.status}).`
        );
      if (Array.isArray(payload.data)) allRows.push(...payload.data);
      lastPage = Math.max(1, payload.last_page ?? 1);
      page += 1;
    } while (page <= lastPage);
    lowStockRows.value = allRows;
    if (!lowStockRows.value.some((row) => String(row.id) === restockStockId.value)) {
      restockStockId.value = "";
      destinationLocationId.value = "";
      sourceStockRow.value = null;
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Unable to load low-stock items.";
    lowStockRows.value = [];
  } finally {
    loadingLowStock.value = false;
  }
}
function selectRestockStock(row: LowStockRow): void {
  clearNotices();
  restockStockId.value = String(row.id);
  destinationLocationId.value = String(row.location_id);
  sourceLocationId.value = "";
  sourceStockRow.value = null;
  quantity.value = "";
  reason.value = "";
  referenceType.value = "";
  referenceId.value = "";
  fieldErrors.value = {};
}
async function loadSourceStock(): Promise<void> {
  const stock = selectedRestockStock.value;
  const sourceId = sourceLocationId.value;
  sourceStockRow.value = null;
  if (
    operation.value !== "restock" ||
    !stock ||
    !sourceId ||
    sourceId === String(stock.location_id)
  )
    return;
  loadingSourceStock.value = true;
  try {
    const params = new URLSearchParams({
      location_id: sourceId,
      per_page: "100",
      page: "1",
    });
    if (stock.product_variant_id ?? stock.variant_id)
      params.set(
        "product_variant_id",
        String(stock.product_variant_id ?? stock.variant_id)
      );
    else if (stock.product_id) params.set("product_id", String(stock.product_id));
    else throw new Error("This stock row is not linked to a product or variant.");
    const response = await fetch(`/api/v1/backend/inventory?${params}`, {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });
    const payload = await readApiPayload<LowStockRow[]>(response);
    if (!response.ok)
      throw new Error(
        payload.message || `Unable to check source stock (HTTP ${response.status}).`
      );
    if (
      sourceLocationId.value !== sourceId ||
      String(selectedRestockStock.value?.id ?? "") !== String(stock.id)
    )
      return;
    const rows = Array.isArray(payload.data) ? payload.data : [];
    sourceStockRow.value =
      rows.find(
        (row) =>
          String(row.location_id) === sourceId &&
          (stock.product_variant_id ?? stock.variant_id
            ? String(row.product_variant_id ?? row.variant_id ?? "") ===
              String(stock.product_variant_id ?? stock.variant_id)
            : String(row.product_id ?? "") === String(stock.product_id ?? ""))
      ) ?? null;
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Unable to check source stock.";
    sourceStockRow.value = null;
  } finally {
    loadingSourceStock.value = false;
  }
}
watch(sourceLocationId, () => {
  if (operation.value === "restock") void loadSourceStock();
});
async function loadVariants(): Promise<void> {
  variants.value = [];
  variantId.value = "";
  if (!productId.value || currentProduct.value?.product_type !== "variable") return;
  loadingVariants.value = true;
  try {
    const response = await fetch(
      `/api/v1/backend/products/${encodeURIComponent(productId.value)}/variants`,
      {
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
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
  void loadTransfers();
  success.value = "";
  if (operation.value === "restock") {
    restockStockId.value = "";
    destinationLocationId.value = "";
    sourceLocationId.value = "";
    sourceStockRow.value = null;
    void loadLowStock();
  } else {
    lowStockRows.value = [];
    restockStockId.value = "";
    sourceStockRow.value = null;
  }
});
function validateForm(): boolean {
  fieldErrors.value = {};
  if (operation.value === "restock") {
    if (!restockStockId.value || !selectedRestockStock.value)
      fieldErrors.value.stock_id = "Select a low-stock item.";
  } else {
    if (!productId.value) fieldErrors.value.product_id = "Select a product.";
    if (isVariable.value && !variantId.value)
      fieldErrors.value.product_variant_id = "Select an active variant.";
  }
  if (operation.value === "transfer" || operation.value === "restock") {
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
  else if (
    operation.value === "restock" &&
    sourceLocationId.value &&
    (!sourceStockRow.value || n > sourceAvailableQuantity.value)
  )
    fieldErrors.value.quantity = sourceStockRow.value
      ? `Quantity cannot exceed the source available stock (${qty(
          sourceAvailableQuantity.value
        )}).`
      : "The selected source location has no available stock for this product.";
  if (operation.value === "adjust" && !reason.value.trim())
    fieldErrors.value.reason = "A reason is required for stock adjustments.";
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
  if (operation.value === "adjust" && !adjustmentConfirmed.value) {
    adjustmentConfirmOpen.value = true;
    return;
  }
  if (operation.value === "adjust") adjustmentConfirmed.value = false;
  const thisRequest = ++requestId.value;
  submitting.value = true;
  const payload: Record<string, unknown> = {
    quantity: String(quantity.value ?? "").trim(),
    reason: reason.value.trim() || null,
    reference_type: referenceType.value.trim() || null,
    reference_id: referenceId.value.trim() || null,
  };
  if (operation.value === "restock" && selectedRestockStock.value) {
    const stock = selectedRestockStock.value;
    if (stock.product_variant_id ?? stock.variant_id)
      payload.product_variant_id = Number(stock.product_variant_id ?? stock.variant_id);
    else payload.product_id = Number(stock.product_id);
    payload.reason = reason.value.trim() || "Low-stock replenishment transfer";
    payload.reference_type = referenceType.value.trim() || "low_stock_restock";
    payload.reference_id = referenceId.value.trim() || String(stock.id);
  } else {
    if (isVariable.value) payload.product_variant_id = Number(variantId.value);
    else payload.product_id = Number(productId.value);
  }
  if (operation.value === "transfer" || operation.value === "restock") {
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
    if (operation.value === "transfer" || operation.value === "restock")
      await Promise.all([loadTransfers(), loadTransferCounts()]);
    lastPayloadFingerprint = "";
    lastIdempotencyKey = "";
    quantity.value = "";
    if (operation.value === "restock") {
      restockStockId.value = "";
      sourceLocationId.value = "";
      destinationLocationId.value = "";
      sourceStockRow.value = null;
      await loadLowStock();
    }
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
onMounted(() => {
  void loadOptions();
  void loadTransfers();
  void loadTransferCounts();
});
</script>

<template>
  <Head title="Stock Operations" />
  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <main class="inventory-page">
      <header class="page-header">
        <div class="page-heading">
          <div class="breadcrumb">
            <a href="/backend/inventory">Inventory</a
            ><Icon name="chevron-right" :size="12" /><strong>Stock Operations</strong>
          </div>
          <div class="title-row">
            <div class="page-title-icon">
              <Icon :name="operationMeta.icon" :size="20" />
            </div>
            <div class="title-copy">
              <h1>Stock Operations</h1>
              <p>
                Receive, move out, adjust, transfer or restock inventory with a recorded
                stock ledger.
              </p>
            </div>
          </div>
        </div>
        <div class="header-actions">
          <a class="button secondary" href="/backend/inventory/movements"
            ><Icon name="history" :size="14" /> Movement history</a
          >
        </div>
      </header>

      <section class="operation-tabs" aria-label="Stock operation type">
        <button
          v-for="item in [
            {
              value: 'receive',
              label: 'Receive',
              icon: 'stock-in',
              tone: 'receive',
            },
            {
              value: 'issue',
              label: 'Stock Out',
              icon: 'stock-out',
              tone: 'stock-out',
            },
            {
              value: 'adjust',
              label: 'Adjust',
              icon: 'sliders-horizontal',
              tone: 'adjust',
            },
            {
              value: 'transfer',
              label: 'Transfer',
              icon: 'arrows-left-right',
              tone: 'transfer',
            },
            {
              value: 'restock',
              label: 'Restock',
              icon: 'repeat-once',
              tone: 'restock',
            },
          ]"
          :key="item.value"
          type="button"
          class="operation-tab"
          :class="[item.tone, { active: operation === item.value }]"
          :aria-pressed="operation === item.value"
          @click="operation = item.value as Operation"
        >
          <Icon :name="item.icon" :size="16" /><span>{{ item.label }}</span>
          <span
            v-if="item.value === 'receive' && incomingPendingCount > 0"
            class="operation-count-badge"
            >{{ incomingPendingCount > 99 ? "99+" : incomingPendingCount }}</span
          >
          <span
            v-if="item.value === 'transfer' && outgoingPendingCount > 0"
            class="operation-count-badge"
            >{{ outgoingPendingCount > 99 ? "99+" : outgoingPendingCount }}</span
          >
        </button>
      </section>

      <section class="form-card">
        <div class="section-heading">
          <div :class="['section-icon', `tone-${operation}`]">
            <Icon :name="operationMeta.icon" :size="17" />
          </div>
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
          <template v-if="operation === 'restock'">
            <div v-if="error" class="notice error" role="alert">
              <Icon name="alert-circle" :size="17" /><span>{{ error }}</span>
              <button
                type="button"
                class="notice-close"
                aria-label="Dismiss error"
                @click="error = ''"
              >
                <Icon name="x" :size="14" />
              </button>
            </div>
            <div v-if="success" class="notice success" role="status">
              <Icon name="check-circle-2" :size="17" /><span>{{ success }}</span>
              <button
                type="button"
                class="notice-close"
                aria-label="Dismiss success"
                @click="success = ''"
              >
                <Icon name="x" :size="14" />
              </button>
            </div>

            <section class="restock-queue" aria-labelledby="restock-queue-title">
              <div class="restock-queue-heading">
                <div>
                  <span class="restock-eyebrow">INVENTORY REPLENISHMENT</span>
                  <h3 id="restock-queue-title">Choose a low-stock item</h3>
                  <p>
                    Select a product below to see its destination stock and choose a
                    source warehouse.
                  </p>
                </div>
                <div class="restock-count">
                  <strong>{{ lowStockRows.length }}</strong
                  ><span>low-stock items</span>
                </div>
              </div>
              <div v-if="loadingLowStock" class="restock-state">
                <Icon name="loader-2" :size="20" class="spin" /><span
                  >Loading low-stock items…</span
                >
              </div>
              <div v-else-if="lowStockRows.length === 0" class="restock-empty">
                <Icon name="check-circle-2" :size="26" />
                <strong>Everything looks stocked</strong>
                <span>No low-stock items currently need replenishment.</span>
              </div>
              <div v-else class="restock-product-grid">
                <button
                  v-for="(stock, index) in lowStockRows"
                  :key="stock.id"
                  type="button"
                  class="restock-product-card"
                  :class="[
                    restockCardSpanClass(index),
                    {
                      selected: restockStockId === String(stock.id),
                    },
                  ]"
                  :aria-pressed="restockStockId === String(stock.id)"
                  :disabled="submitting"
                  @click="selectRestockStock(stock)"
                >
                  <span class="restock-product-icon"
                    ><Icon name="package" :size="18"
                  /></span>
                  <span class="restock-product-main">
                    <strong>{{
                      stock.product?.name ??
                      `Product #${
                        stock.product_id ??
                        stock.product_variant_id ??
                        stock.variant_id ??
                        stock.id
                      }`
                    }}</strong>
                    <small v-if="stock.variant?.name">{{ stock.variant.name }}</small>
                    <small>{{
                      stock.product?.sku ?? stock.variant?.sku ?? "No SKU"
                    }}</small>
                    <span class="restock-product-location"
                      ><Icon name="map-pin" :size="12" />
                      {{
                        stock.location?.name ??
                        locations.find((location) => location.id === stock.location_id)
                          ?.name ??
                        `Location #${stock.location_id}`
                      }}</span
                    >
                  </span>
                  <span class="restock-product-stock">
                    <strong>{{
                      qty(
                        stock.available_quantity ??
                          numeric(stock.quantity) - numeric(stock.reserved_quantity)
                      )
                    }}</strong>
                    <small>available</small>
                    <span>Threshold {{ qty(stock.low_stock_threshold) }}</span>
                  </span>
                  <Icon name="chevron-right" :size="16" class="restock-card-chevron" />
                </button>
              </div>
            </section>

            <Transition name="restock-reveal">
              <section
                v-if="selectedRestockStock"
                class="restock-detail-panel"
                aria-labelledby="restock-detail-title"
              >
                <div class="restock-detail-heading">
                  <div class="restock-detail-icon">
                    <Icon name="product" :size="19" />
                  </div>
                  <div class="restock-detail-title">
                    <span class="restock-eyebrow">TRANSFER DETAILS</span>
                    <h3 id="restock-detail-title">
                      {{
                        selectedRestockStock.product?.name ??
                        `Product #${
                          selectedRestockStock.product_id ?? selectedRestockStock.id
                        }`
                      }}<span v-if="selectedRestockStock.variant?.name">
                        —
                        {{ selectedRestockStock.variant.name }}</span
                      >
                    </h3>
                    <p>
                      Destination is fixed to the selected low-stock record to prevent
                      stock being added to the wrong location.
                    </p>
                  </div>
                  <button
                    type="button"
                    class="restock-change-button"
                    :disabled="submitting"
                    @click="
                      restockStockId = '';
                      sourceLocationId = '';
                      sourceStockRow = null;
                      quantity = '';
                    "
                  >
                    Change item
                  </button>
                </div>

                <div class="restock-location-summary">
                  <div class="restock-location-card source">
                    <span class="restock-location-label"
                      ><Icon name="warehouse" :size="13" /> SOURCE · SELECT LOCATION</span
                    >
                    <label class="restock-source-select">
                      <span class="sr-only">Source warehouse</span>
                      <select v-model="sourceLocationId" required :disabled="submitting">
                        <option value="">Choose source warehouse…</option>
                        <option
                          v-for="location in manageableLocations.filter(
                            (location) =>
                              String(location.id) !==
                              String(selectedRestockStock?.location_id ?? '')
                          )"
                          :key="location.id"
                          :value="String(location.id)"
                        >
                          {{ location.name
                          }}{{ location.code ? ` · ${location.code}` : "" }}
                        </option>
                      </select>
                    </label>
                    <div v-if="!sourceLocationId" class="restock-source-placeholder">
                      Select a source to check its stock.
                    </div>
                    <div
                      v-else-if="loadingSourceStock"
                      class="restock-source-placeholder"
                    >
                      <Icon name="loader-2" :size="14" class="spin" />
                      Checking available stock…
                    </div>
                    <div v-else-if="sourceStockRow" class="restock-stock-metrics">
                      <span
                        ><small>On hand</small
                        ><b>{{ qty(sourceStockRow.quantity) }}</b></span
                      >
                      <span
                        ><small>Reserved</small
                        ><b>{{ qty(sourceStockRow.reserved_quantity) }}</b></span
                      >
                      <span
                        ><small>Available</small
                        ><b class="source-available-value">{{
                          qty(sourceAvailableQuantity)
                        }}</b></span
                      >
                    </div>
                    <div v-else-if="sourceLocationId" class="restock-source-empty">
                      <Icon name="alert-triangle" :size="14" />
                      No stock record for this item at the selected source.
                    </div>
                  </div>
                  <div class="restock-transfer-arrow">
                    <Icon name="arrow-right" :size="20" />
                  </div>
                  <div class="restock-location-card destination">
                    <span class="restock-location-label"
                      ><Icon name="map-pin" :size="13" /> DESTINATION · LOCKED</span
                    >
                    <strong>{{
                      destinationLocation?.name ??
                      selectedRestockStock.location?.name ??
                      `Location #${selectedRestockStock.location_id}`
                    }}</strong>
                    <small>{{
                      destinationLocation?.code ??
                      selectedRestockStock.location?.code ??
                      "Destination location"
                    }}</small>
                    <div class="restock-stock-metrics">
                      <span
                        ><small>On hand</small
                        ><b>{{ qty(selectedRestockStock.quantity) }}</b></span
                      >
                      <span
                        ><small>Reserved</small
                        ><b>{{ qty(selectedRestockStock.reserved_quantity) }}</b></span
                      >
                      <span
                        ><small>Available</small
                        ><b>{{
                          qty(
                            selectedRestockStock.available_quantity ??
                              numeric(selectedRestockStock.quantity) -
                                numeric(selectedRestockStock.reserved_quantity)
                          )
                        }}</b></span
                      >
                    </div>
                  </div>
                </div>

                <form class="restock-detail-form" @submit.prevent="submit">
                  <div class="restock-quantity-field">
                    <label for="restock-quantity">Quantity to transfer <b>*</b></label>
                    <div class="restock-quantity-input-wrap">
                      <input
                        id="restock-quantity"
                        v-model="quantity"
                        type="number"
                        inputmode="decimal"
                        min="0.0001"
                        step="0.0001"
                        placeholder="Enter transfer quantity"
                        :disabled="
                          submitting ||
                          !sourceLocationId ||
                          loadingSourceStock ||
                          !sourceStockRow
                        "
                        @input="fieldErrors.quantity = ''"
                      /><span>units</span>
                    </div>
                    <div class="restock-quantity-hint" v-if="sourceStockRow">
                      Maximum available to transfer:
                      <strong>{{ qty(sourceAvailableQuantity) }}</strong>
                    </div>
                    <div class="restock-inline-error" v-if="quantityExceedsSource">
                      Requested quantity exceeds source available stock. Reduce it to
                      {{ qty(sourceAvailableQuantity) }}
                      or less.
                    </div>
                    <div class="restock-inline-error" v-else-if="fieldErrors.quantity">
                      {{ fieldErrors.quantity }}
                    </div>
                  </div>
                  <div class="restock-detail-extra-fields">
                    <label class="field"
                      ><span>Reason <em>Optional</em></span
                      ><input
                        v-model="reason"
                        maxlength="255"
                        placeholder="e.g. Replenish branch stock"
                        :disabled="submitting"
                    /></label>
                    <label class="field"
                      ><span>Reference ID <em>Optional</em></span
                      ><input
                        v-model="referenceId"
                        maxlength="100"
                        placeholder="Transfer note or internal reference"
                        :disabled="submitting"
                    /></label>
                  </div>
                  <div
                    class="restock-validation-note"
                    :class="{
                      error:
                        !!sourceLocationId &&
                        !loadingSourceStock &&
                        (!sourceStockRow ||
                          sourceAvailableQuantity <= 0 ||
                          quantityExceedsSource ||
                          !!fieldErrors.quantity),
                      warning: !!sourceLocationId && loadingSourceStock,
                    }"
                  >
                    <Icon
                      :name="
                        sourceLocationId &&
                        !loadingSourceStock &&
                        (!sourceStockRow ||
                          sourceAvailableQuantity <= 0 ||
                          quantityExceedsSource ||
                          !!fieldErrors.quantity)
                          ? 'alert-circle'
                          : sourceStockRow &&
                            sourceAvailableQuantity > 0 &&
                            !quantityExceedsSource
                          ? 'shield-check'
                          : 'info'
                      "
                      :size="16"
                    />
                    <span v-if="!sourceLocationId"
                      >Choose a source location to check stock availability.</span
                    >
                    <span v-else-if="loadingSourceStock">Checking source stock…</span>
                    <span v-else-if="!sourceStockRow"
                      >Restock unavailable: this item has no stock record at the selected
                      source.</span
                    >
                    <span v-else-if="sourceAvailableQuantity <= 0"
                      >Restock unavailable: the source has no available stock after
                      reservations.</span
                    >
                    <span v-else-if="quantityExceedsSource"
                      >Restock blocked: only
                      {{ qty(sourceAvailableQuantity) }}
                      units are available at the source.</span
                    >
                    <span v-else-if="fieldErrors.quantity"
                      >Restock blocked: {{ fieldErrors.quantity }}</span
                    >
                    <span v-else
                      >Ready to transfer up to
                      {{ qty(sourceAvailableQuantity) }}
                      available units. Stock is revalidated by the server.</span
                    >
                  </div>
                  <div class="restock-detail-actions">
                    <button
                      type="button"
                      class="button secondary"
                      :disabled="submitting"
                      @click="
                        restockStockId = '';
                        sourceLocationId = '';
                        sourceStockRow = null;
                        quantity = '';
                        fieldErrors = {};
                      "
                    >
                      Cancel selection
                    </button>
                    <button
                      type="submit"
                      class="button primary action-restock"
                      :disabled="
                        submitting ||
                        loadingSourceStock ||
                        loadingLowStock ||
                        !sourceStockRow ||
                        sourceAvailableQuantity <= 0 ||
                        !quantity ||
                        numeric(quantity) <= 0
                      "
                    >
                      <Icon
                        v-if="submitting"
                        name="loader-2"
                        :size="15"
                        class="spin"
                      /><Icon v-else name="file-plus" :size="15" />{{
                        submitting ? "Transferring…" : "Confirm restock transfer"
                      }}
                    </button>
                  </div>
                </form>
              </section>
            </Transition>
          </template>
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
                  <small v-if="fieldErrors.product_id">{{
                    fieldErrors.product_id
                  }}</small>
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
                        v-for="location in manageableLocations"
                        :key="location.id"
                        :value="String(location.id)"
                      >
                        {{ location.name
                        }}{{ location.code ? ` · ${location.code}` : "" }}
                      </option>
                    </select>
                    <small v-if="fieldErrors.source_location_id">{{
                      fieldErrors.source_location_id
                    }}</small>
                  </label>
                  <label class="field"
                    ><span>Destination location <b>*</b></span>
                    <select
                      v-model="destinationLocationId"
                      required
                      :disabled="submitting"
                    >
                      <option value="">Select destination location</option>
                      <option
                        v-for="location in receivableLocations.filter(
                          (l) => String(l.id) !== sourceLocationId
                        )"
                        :key="location.id"
                        :value="String(location.id)"
                      >
                        {{ location.name
                        }}{{ location.code ? ` · ${location.code}` : "" }}
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
                      v-for="location in operationLocations"
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
                  ><span
                    >Reason
                    <em>{{ operation === "adjust" ? "Required" : "Optional" }}</em></span
                  >
                  <input
                    v-model="reason"
                    type="text"
                    maxlength="255"
                    :placeholder="
                      operation === 'adjust'
                        ? 'e.g. physical count variance, damaged stock write-off'
                        : 'e.g. supplier delivery, damaged stock'
                    "
                    :required="operation === 'adjust'"
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
                  :class="['button', 'primary', `action-${operation}`]"
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
        </template>
      </section>
      <section
        v-if="operation === 'receive' || operation === 'transfer'"
        class="transfer-queue-card"
        aria-labelledby="transfer-queue-title"
      >
        <div class="transfer-queue-heading">
          <div>
            <span class="transfer-queue-eyebrow">INVENTORY CONTROL</span>
            <h2 id="transfer-queue-title">
              {{
                operation === "receive"
                  ? "Incoming transfers awaiting receipt"
                  : "Outgoing transfer tracking"
              }}
            </h2>
            <p>
              {{
                operation === "receive"
                  ? "Verify the goods before accepting them into available stock."
                  : "Track dispatched stock until the destination confirms receipt."
              }}
            </p>
          </div>
          <button
            type="button"
            class="button secondary"
            :disabled="loadingTransfers"
            @click="loadTransfers"
          >
            <Icon name="refresh-cw" :size="14" /> Refresh
          </button>
        </div>
        <div v-if="loadingTransfers" class="transfer-queue-empty">
          <Icon name="loader-2" :size="18" class="spin" /> Loading transfer records…
        </div>
        <div v-else-if="!visibleTransferRows.length" class="transfer-queue-empty">
          <Icon name="package-check" :size="20" /><span
            >No transfer records to show for this view.</span
          >
        </div>
        <div v-else class="transfer-list">
          <article v-for="row in visibleTransferRows" :key="row.id" class="transfer-row">
            <div class="transfer-row-main">
              <div class="transfer-product-icon">
                <Icon name="package" :size="17" />
              </div>
              <div class="transfer-product-copy">
                <strong>{{
                  row.variant?.name || row.product?.name || "Inventory item"
                }}</strong>
                <span>{{
                  row.variant?.sku || row.product?.sku || row.transfer_uuid
                }}</span>
                <small
                  >{{ row.source_location?.name || "Source" }}
                  →
                  {{ row.destination_location?.name || "Destination" }}</small
                >
              </div>
            </div>
            <div class="transfer-row-quantity">
              <strong
                >{{ qty(row.quantity_received) }} / {{ qty(row.quantity_sent) }}</strong
              ><span>Received / sent</span>
            </div>
            <div class="transfer-row-meta">
              <span :class="['transfer-status', `status-${row.status}`]">{{
                row.status.replaceAll("_", " ")
              }}</span
              ><small>{{
                row.dispatcher?.name
                  ? `Sent by ${row.dispatcher.name}`
                  : "Transfer dispatch"
              }}</small
              ><small>{{
                row.dispatched_at
                  ? new Date(row.dispatched_at).toLocaleString()
                  : "Date unavailable"
              }}</small>
            </div>
            <div
              v-if="
                operation === 'receive' &&
                ['in_transit', 'partially_received'].includes(row.status)
              "
              class="transfer-row-actions"
            >
              <button
                type="button"
                class="button primary transfer-accept"
                :disabled="transferActionId === row.id"
                @click="openTransferDialog(row, 'accept')"
              >
                <Icon name="check-circle-2" :size="14" /> Accept
              </button>
              <button
                type="button"
                class="button secondary transfer-reject"
                :disabled="transferActionId === row.id"
                @click="openTransferDialog(row, 'reject')"
              >
                <Icon name="alert-triangle" :size="14" /> Report issue
              </button>
            </div>
            <div
              v-if="operation === 'transfer' && row.status === 'discrepancy'"
              class="transfer-row-actions"
            >
              <button
                type="button"
                class="button primary transfer-accept"
                :disabled="transferActionId === row.id"
                @click="openTransferDialog(row, 'resolve')"
              >
                <Icon name="clipboard-check" :size="14" /> Reconcile
              </button>
            </div>
          </article>
        </div>
      </section>

      <Teleport to="body">
        <Transition name="transfer-dialog">
          <div
            v-if="transferDialogRow && transferDialogAction"
            class="transfer-dialog-backdrop"
            role="presentation"
            @click.self="closeTransferDialog"
            @keydown.esc="closeTransferDialog"
          >
            <section
              class="transfer-dialog"
              role="dialog"
              aria-modal="true"
              aria-labelledby="transfer-dialog-title"
              @keydown.esc.stop="closeTransferDialog"
            >
              <header class="transfer-dialog-header">
                <div
                  :class="[
                    'transfer-dialog-icon',
                    transferDialogAction === 'accept' || transferDialogAction === 'resolve' ? 'is-accept' : 'is-issue',
                  ]"
                >
                  <Icon
                    :name="
                      transferDialogAction === 'accept'
                        ? 'package-import'
                        : 'alert-triangle'
                    "
                    :size="23"
                  />
                </div>
                <div class="transfer-dialog-heading">
                  <span class="transfer-dialog-eyebrow">{{
                    transferDialogAction === "accept"
                      ? "RECEIVING STOCK"
                      : transferDialogAction === "resolve"
                        ? "DISCREPANCY RECONCILIATION"
                        : "TRANSFER DISCREPANCY"
                  }}</span>
                  <h2 id="transfer-dialog-title">
                    {{
                      transferDialogAction === "accept"
                        ? "Confirm goods receipt"
                        : transferDialogAction === "resolve"
                          ? "Resolve transfer discrepancy"
                          : "Report a transfer issue"
                    }}
                  </h2>
                  <p>
                    {{
                      transferDialogAction === "accept"
                        ? "Confirm only after physically checking the delivered goods."
                        : transferDialogAction === "resolve"
                          ? "Choose what happened to the outstanding quantity. The stock ledger will record the selected outcome."
                          : "Tell the sending team what is wrong so the transfer can be investigated."
                    }}
                  </p>
                </div>
                <button
                  type="button"
                  class="transfer-dialog-close"
                  aria-label="Close dialog"
                  :disabled="transferActionId !== null"
                  @click="closeTransferDialog"
                >
                  <Icon name="x" :size="19" />
                </button>
              </header>
              <div class="transfer-dialog-body">
                <div class="transfer-dialog-product">
                  <div class="transfer-dialog-product-icon">
                    <Icon name="package" :size="19" />
                  </div>
                  <div class="transfer-dialog-product-copy">
                    <strong>{{
                      transferDialogRow.variant?.name ||
                      transferDialogRow.product?.name ||
                      "Inventory item"
                    }}</strong>
                    <span>{{
                      transferDialogRow.variant?.sku ||
                      transferDialogRow.product?.sku ||
                      transferDialogRow.transfer_uuid
                    }}</span>
                  </div>
                  <span class="transfer-dialog-quantity"
                    >{{ qty(transferDialogRow.quantity_sent) }} sent</span
                  >
                </div>
                <div class="transfer-dialog-route">
                  <div>
                    <span>FROM</span
                    ><strong>{{
                      transferDialogRow.source_location?.name || "Source location"
                    }}</strong>
                  </div>
                  <Icon name="arrow-right" :size="17" />
                  <div>
                    <span>TO</span
                    ><strong>{{
                      transferDialogRow.destination_location?.name ||
                      "Destination location"
                    }}</strong>
                  </div>
                </div>
                <template v-if="transferDialogAction === 'accept'">
                  <div class="transfer-dialog-notice is-warning">
                    <Icon name="shield-check" :size="18" />
                    <p>
                      Accepting records the received quantity in this location's stock
                      ledger. Check the product and quantity before confirming.
                    </p>
                  </div>
                  <div class="transfer-dialog-receipt">
                    <span>Quantity to receive</span
                    ><strong>{{
                      qty(
                        Math.max(
                          0,
                          numeric(transferDialogRow.quantity_sent) -
                            numeric(transferDialogRow.quantity_received)
                        )
                      )
                    }}</strong>
                  </div>
                </template>
                <template v-else-if="transferDialogAction === 'resolve'">
                  <label class="transfer-dialog-reason-label" for="transfer-resolution-action">
                    Reconciliation outcome <span>Required</span>
                  </label>
                  <select
                    id="transfer-resolution-action"
                    v-model="discrepancyResolution"
                    :disabled="transferActionId !== null"
                  >
                    <option value="receive_remaining">Receive outstanding quantity at destination</option>
                    <option value="return_to_source">Return outstanding quantity to source</option>
                    <option value="write_off">Write off missing quantity</option>
                  </select>
                  <label class="transfer-dialog-reason-label" for="transfer-discrepancy-reason">
                    Reconciliation note <span>Required</span>
                  </label>
                  <textarea
                    id="transfer-discrepancy-reason"
                    v-model="discrepancyReason"
                    rows="4"
                    maxlength="1000"
                    required
                    placeholder="Record the investigation result and physical stock outcome…"
                    :disabled="transferActionId !== null"
                  />
                  <div class="transfer-dialog-notice is-warning">
                    <Icon name="shield-check" :size="18" />
                    <p>
                      Receiving credits destination stock; returning credits source stock;
                      write-off closes the loss without adding stock to either location.
                    </p>
                  </div>
                </template>
                <template v-else>
                  <label
                    class="transfer-dialog-reason-label"
                    for="transfer-discrepancy-reason"
                    >What is the issue? <span>Required</span></label
                  >
                  <textarea
                    id="transfer-discrepancy-reason"
                    v-model="discrepancyReason"
                    rows="4"
                    maxlength="1000"
                    required
                    placeholder="e.g. 12 units are missing, packaging is damaged, or the wrong product was delivered…"
                    :disabled="transferActionId !== null"
                  />
                  <div class="transfer-dialog-reason-meta">
                    <span
                      >Include the discrepancy and details that help the sender
                      investigate.</span
                    ><span>{{ discrepancyReason.trim().length }}/1000</span>
                  </div>
                  <div class="transfer-dialog-notice is-danger">
                    <Icon name="info" :size="18" />
                    <p>
                      This flags the transfer for review. It does not automatically return
                      stock to the source location.
                    </p>
                  </div>
                </template>
              </div>
              <footer class="transfer-dialog-footer">
                <button
                  type="button"
                  class="button secondary"
                  :disabled="transferActionId !== null"
                  @click="closeTransferDialog"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  :class="[
                    'button',
                    'transfer-dialog-submit',
                    transferDialogAction === 'accept' || transferDialogAction === 'resolve' ? 'is-accept' : 'is-issue',
                  ]"
                  :disabled="
                    transferActionId !== null ||
                    (transferDialogAction !== 'accept' &&
                      discrepancyReason.trim().length < 3)
                  "
                  @click="confirmTransferAction"
                >
                  <Icon
                    v-if="transferActionId === transferDialogRow.id"
                    name="loader-2"
                    :size="16"
                    class="spin"
                  />
                  <Icon
                    v-else
                    :name="
                      transferDialogAction === 'accept'
                        ? 'check-circle-2'
                        : 'alert-triangle'
                    "
                    :size="16"
                  />
                  {{
                    transferActionId === transferDialogRow.id
                      ? "Processing…"
                      : transferDialogAction === "accept"
                      ? "Confirm receipt"
                      : transferDialogAction === "resolve"
                        ? "Confirm reconciliation"
                        : "Submit issue report"
                  }}
                </button>
              </footer>
            </section>
          </div>
        </Transition>
      </Teleport>

      <Teleport to="body">
        <Transition name="transfer-dialog">
          <div
            v-if="adjustmentConfirmOpen"
            class="transfer-dialog-backdrop"
            role="presentation"
            @click.self="adjustmentConfirmOpen = false"
            @keydown.esc="adjustmentConfirmOpen = false"
          >
            <section
              class="transfer-dialog adjustment-confirm-dialog"
              role="alertdialog"
              aria-modal="true"
              aria-labelledby="adjustment-confirm-title"
              aria-describedby="adjustment-confirm-description"
            >
              <header class="transfer-dialog-header">
                <div class="transfer-dialog-icon is-issue">
                  <Icon name="sliders-horizontal" :size="22" />
                </div>
                <div class="transfer-dialog-heading">
                  <span class="transfer-dialog-eyebrow">INVENTORY CONTROL</span>
                  <h2 id="adjustment-confirm-title">Confirm stock adjustment</h2>
                  <p>Review this ledger change before applying it.</p>
                </div>
                <button
                  type="button"
                  class="transfer-dialog-close"
                  aria-label="Close confirmation"
                  @click="adjustmentConfirmOpen = false"
                >
                  <Icon name="x" :size="18" />
                </button>
              </header>
              <div
                class="transfer-dialog-body adjustment-confirm-body"
                id="adjustment-confirm-description"
              >
                <div class="adjustment-summary">
                  <span class="adjustment-summary-label">STOCK CHANGE</span>
                  <strong :class="numeric(quantity) > 0 ? 'is-increase' : 'is-decrease'">
                    {{ numeric(quantity) > 0 ? "+" : "−"
                    }}{{ qty(Math.abs(numeric(quantity))) }}
                  </strong>
                  <span>{{
                    numeric(quantity) > 0 ? "Increase stock" : "Decrease stock"
                  }}</span>
                </div>
                <dl class="adjustment-details">
                  <div>
                    <dt>Product</dt>
                    <dd>{{ chosenItemLabel || "Selected inventory item" }}</dd>
                  </div>
                  <div>
                    <dt>Location</dt>
                    <dd>
                      {{
                        locations.find((item) => String(item.id) === String(locationId))
                          ?.name ?? "Selected location"
                      }}
                    </dd>
                  </div>
                  <div>
                    <dt>Reason</dt>
                    <dd>{{ reason.trim() || "No reason provided" }}</dd>
                  </div>
                </dl>
                <p class="adjustment-ledger-note">
                  <Icon name="shield-check" :size="16" /> This adjustment will be recorded
                  in the stock movement ledger. It cannot be treated as a transfer or
                  receipt.
                </p>
              </div>
              <footer class="transfer-dialog-footer adjustment-confirm-footer">
                <button
                  type="button"
                  class="button adjustment-cancel-button"
                  :disabled="submitting"
                  @click="adjustmentConfirmOpen = false"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  class="button adjustment-confirm-button"
                  :disabled="submitting"
                  @click="
                    adjustmentConfirmOpen = false;
                    adjustmentConfirmed = true;
                    submit();
                  "
                >
                  <Icon name="check-circle-2" :size="16" />
                  {{ submitting ? "Processing…" : "Confirm adjustment" }}
                </button>
              </footer>
            </section>
          </div>
        </Transition>
      </Teleport>

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
  border: 1px solid
    color-mix(in srgb, var(--operation-color, var(--primary-color)) 18%, transparent);
  border-radius: 10px;
  background: color-mix(in srgb, var(--operation-color, var(--primary-color)) 9%, white);
  color: var(--operation-color, var(--primary-color));
}
.section-icon.tone-receive {
  --operation-color: #059669;
}
.section-icon.tone-issue {
  --operation-color: #dc2626;
}
.section-icon.tone-adjust {
  --operation-color: #d97706;
}
.section-icon.tone-transfer {
  --operation-color: #2563eb;
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

/* Stock adjustment confirmation: explicit action colors and balanced layout. */
.adjustment-confirm-dialog {
  width: min(680px, 100%);
}
.adjustment-confirm-body {
  gap: 18px;
}
.adjustment-summary {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 5px 16px;
  padding: 18px 20px;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 14px;
  background: color-mix(in srgb, var(--primary-color, #0f9f6e) 5%, white);
}
.adjustment-summary-label {
  grid-column: 1 / -1;
  color: var(--text-tertiary-color, #667085);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
}
.adjustment-summary strong {
  grid-row: 2 / 4;
  grid-column: 2;
  font-size: clamp(26px, 4vw, 34px);
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}
.adjustment-summary strong.is-increase {
  color: #059669;
}
.adjustment-summary strong.is-decrease {
  color: #dc2626;
}
.adjustment-summary > span:last-child {
  color: var(--text-secondary-color, #667085);
  font-size: 12px;
}
.adjustment-details {
  display: grid;
  gap: 0;
  margin: 0;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 12px;
  overflow: hidden;
}
.adjustment-details > div {
  display: grid;
  grid-template-columns: minmax(90px, 120px) minmax(0, 1fr);
  gap: 14px;
  padding: 12px 15px;
}
.adjustment-details > div + div {
  border-top: 1px solid var(--border-color, #e5e7eb);
}
.adjustment-details dt {
  color: var(--text-secondary-color, #667085);
  font-size: 12px;
  font-weight: 650;
}
.adjustment-details dd {
  margin: 0;
  overflow-wrap: anywhere;
  color: var(--text-primary-color, #101828);
  font-size: 12px;
  font-weight: 650;
}
.adjustment-ledger-note {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  margin: 0;
  padding: 12px 14px;
  border: 1px solid color-mix(in srgb, var(--primary-color, #0f9f6e) 24%, white);
  border-radius: 11px;
  background: color-mix(in srgb, var(--primary-color, #0f9f6e) 6%, white);
  color: var(--text-secondary-color, #475467);
  font-size: 11px;
  line-height: 1.55;
}
.adjustment-ledger-note :deep(svg) {
  flex: 0 0 auto;
  margin-top: 1px;
  color: var(--primary-color, #0f9f6e);
}
.adjustment-confirm-footer .adjustment-cancel-button,
.adjustment-confirm-footer .adjustment-confirm-button {
  min-width: 150px;
  min-height: 44px;
  border: 1px solid transparent;
  border-radius: 11px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 0 18px;
  font-size: 12px;
  font-weight: 750;
  transition: filter 0.15s ease, transform 0.15s ease;
}
.adjustment-confirm-footer .adjustment-cancel-button {
  border-color: #dc2626;
  background: #dc2626;
  color: #fff;
}
.adjustment-confirm-footer .adjustment-confirm-button {
  border-color: var(--primary-color, #0f9f6e);
  background: var(--primary-color, #0f9f6e);
  color: #fff;
}
.adjustment-confirm-footer .adjustment-cancel-button:hover:not(:disabled),
.adjustment-confirm-footer .adjustment-confirm-button:hover:not(:disabled) {
  filter: brightness(0.92);
}
.adjustment-confirm-footer .button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
@media (max-width: 560px) {
  .adjustment-confirm-footer .adjustment-cancel-button,
  .adjustment-confirm-footer .adjustment-confirm-button {
    min-width: 0;
    flex: 1;
    padding-inline: 10px;
  }
  .adjustment-details > div {
    grid-template-columns: 82px minmax(0, 1fr);
    gap: 10px;
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
  border-color: color-mix(
    in srgb,
    var(--operation-color, var(--primary-color)) 25%,
    transparent
  );
  background: color-mix(in srgb, var(--operation-color, var(--primary-color)) 10%, white);
  color: var(--operation-color, var(--primary-color));
}
.operation-tab.receive {
  --operation-color: #059669;
}
.operation-tab.stock-out {
  --operation-color: #dc2626;
}
.operation-tab.adjust {
  --operation-color: #d97706;
}
.operation-tab.transfer {
  --operation-color: #2563eb;
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
  background: var(--action-color, var(--primary-color));
  border-color: var(--action-color, var(--primary-color));
  color: #fff;
  filter: brightness(0.93);
}
.button.primary.action-receive {
  --action-color: #059669;
  border-color: #059669;
  background: #059669;
}
.button.primary.action-issue {
  --action-color: #dc2626;
  border-color: #dc2626;
  background: #dc2626;
}
.button.primary.action-adjust {
  --action-color: #d97706;
  border-color: #d97706;
  background: #d97706;
}
.button.primary.action-transfer {
  --action-color: #2563eb;
  border-color: #2563eb;
  background: #2563eb;
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

/* Restock queue: adaptive 3-column product cards and clear source-to-destination transfer details. */
.restock-queue {
  display: grid;
  gap: 14px;
  padding: 20px 22px 22px;
}
.restock-queue-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding-bottom: 2px;
}
.restock-queue-heading > div:first-child {
  min-width: 0;
}
.restock-queue-heading p {
  max-width: 620px;
}
.restock-queue .restock-product-card {
  width: 100%;
  min-height: 86px;
  padding: 14px 16px;
}
.restock-queue .restock-product-main strong {
  font-size: 13px;
}
.restock-queue .restock-product-main small,
.restock-queue .restock-product-location {
  font-size: 10px;
}
.restock-queue .restock-product-stock {
  min-width: 88px;
  padding-left: 16px;
  border-left: 1px solid var(--border-color);
}
.restock-queue .restock-product-stock strong {
  font-size: 21px;
}
.restock-queue .restock-product-stock small,
.restock-queue .restock-product-stock span {
  font-size: 10px;
}
.restock-eyebrow {
  display: block;
  color: #0b9b72;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.11em;
}
.restock-queue-heading h3,
.restock-detail-title h3 {
  margin: 4px 0 0;
  color: var(--text-primary-color);
  font-size: 17px;
  font-weight: 750;
  letter-spacing: -0.025em;
}
.restock-queue-heading p,
.restock-detail-title p {
  margin: 5px 0 0;
  color: var(--text-secondary-color);
  font-size: 11px;
  line-height: 1.55;
}
.restock-count {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 9px;
  padding: 10px 13px;
  border: 1px solid #b7efdc;
  border-radius: 12px;
  background: #effcf7;
  color: #087f5b;
}
.restock-count strong {
  font-size: 22px;
  line-height: 1;
}
.restock-count span {
  max-width: 66px;
  font-size: 10px;
  line-height: 1.3;
}
.restock-product-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 10px;
}
.restock-span-third {
  grid-column: span 2;
}
.restock-span-half {
  grid-column: span 3;
}
.restock-span-full {
  grid-column: 1 / -1;
}
.restock-product-card {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 11px;
  padding: 13px;
  border: 1px solid var(--border-color);
  border-radius: 13px;
  background: var(--surface-color);
  color: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease,
    background 0.18s ease;
}
.restock-product-card:hover:not(:disabled) {
  border-color: #6edbb8;
  box-shadow: 0 7px 20px rgb(16 185 129 / 8%);
  transform: translateY(-1px);
}
.restock-product-card.selected {
  border-color: #10b981;
  background: #f2fcf8;
  box-shadow: 0 0 0 2px rgb(16 185 129 / 10%);
}
.restock-product-card:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
.restock-product-icon {
  display: grid;
  flex: 0 0 38px;
  width: 38px;
  height: 38px;
  place-items: center;
  border: 1px solid #d5f5e8;
  border-radius: 11px;
  background: #edfcf5;
  color: #079669;
}
.restock-product-main {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
  gap: 3px;
}
.restock-product-main strong {
  overflow: hidden;
  color: var(--text-primary-color);
  font-size: 12px;
  font-weight: 750;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.restock-product-main small {
  overflow: hidden;
  color: var(--text-tertiary-color);
  font-size: 10px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.restock-product-location {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 3px;
  color: var(--text-secondary-color);
  font-size: 10px;
}
.restock-product-stock {
  display: flex;
  flex: 0 0 auto;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}
.restock-product-stock strong {
  color: #d97706;
  font-size: 18px;
  line-height: 1;
}
.restock-product-stock small,
.restock-product-stock span {
  color: var(--text-tertiary-color);
  font-size: 9px;
}
.restock-card-chevron {
  flex: 0 0 auto;
  color: var(--text-tertiary-color);
}
.restock-state,
.restock-empty {
  display: flex;
  min-height: 120px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 9px;
  border: 1px dashed var(--border-color);
  border-radius: 13px;
  color: var(--text-secondary-color);
  text-align: center;
}
.restock-empty > :first-child {
  color: #0b9b72;
}
.restock-empty strong {
  color: var(--text-primary-color);
  font-size: 13px;
}
.restock-empty span {
  font-size: 11px;
}
.restock-detail-panel {
  display: grid;
  gap: 17px;
  margin-top: 22px;
  padding: 20px;
  border: 1px solid #b9ecda;
  border-radius: 16px;
  background: linear-gradient(145deg, #f6fffb 0%, var(--surface-color) 52%);
  box-shadow: 0 12px 34px rgb(16 24 40 / 5%);
}
.restock-detail-heading {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.restock-detail-icon {
  display: grid;
  flex: 0 0 40px;
  width: 40px;
  height: 40px;
  place-items: center;
  border: 1px solid #b7efdc;
  border-radius: 12px;
  background: #eafff5;
  color: #07845f;
}
.restock-detail-title {
  flex: 1;
  min-width: 0;
}
.restock-change-button {
  flex: 0 0 auto;
  padding: 7px 10px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--surface-color);
  color: var(--text-secondary-color);
  font: inherit;
  font-size: 10px;
  font-weight: 700;
  cursor: pointer;
}
.restock-change-button:hover {
  border-color: #10b981;
  color: #087f5b;
}
.restock-location-summary {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 30px minmax(0, 1fr);
  align-items: stretch;
  gap: 10px;
}
.restock-location-card {
  min-width: 0;
  padding: 13px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: var(--surface-color);
}
.restock-location-card.destination {
  border-color: #cbdcff;
  background: #f7f9ff;
}
.restock-location-card.source {
  border-color: #c5f0df;
  background: #f6fffb;
}
.restock-location-label {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 8px;
  color: var(--text-tertiary-color);
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.04em;
}
.restock-location-card.destination .restock-location-label {
  color: #315fc7;
}
.restock-location-card.source .restock-location-label {
  color: #07845f;
}
.restock-location-card > strong {
  display: block;
  overflow: hidden;
  color: var(--text-primary-color);
  font-size: 13px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.restock-location-card > small {
  display: block;
  margin-top: 3px;
  color: var(--text-tertiary-color);
  font-size: 10px;
}
.restock-transfer-arrow {
  display: grid;
  place-items: center;
  color: #079669;
}
.restock-stock-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 5px;
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid rgb(148 163 184 / 18%);
}
.restock-stock-metrics > span {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 3px;
}
.restock-stock-metrics small {
  color: var(--text-tertiary-color);
  font-size: 9px;
}
.restock-stock-metrics b {
  color: var(--text-primary-color);
  font-size: 15px;
  font-weight: 750;
}
.restock-stock-metrics .source-available-value {
  color: #07845f;
}
.restock-source-select select {
  width: 100%;
  min-height: 37px;
  padding: 0 9px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--surface-color);
  color: var(--text-primary-color);
  font: inherit;
  font-size: 11px;
}
.restock-source-select select:focus {
  outline: 2px solid rgb(16 185 129 / 18%);
  border-color: #10b981;
}
.restock-source-placeholder,
.restock-source-empty {
  display: flex;
  min-height: 58px;
  align-items: center;
  gap: 7px;
  color: var(--text-tertiary-color);
  font-size: 10px;
  line-height: 1.45;
}
.restock-source-empty {
  color: #b45309;
}
.restock-detail-form {
  display: grid;
  gap: 15px;
}
.restock-quantity-field {
  display: grid;
  gap: 7px;
}
.restock-quantity-field > label {
  color: var(--text-primary-color);
  font-size: 11px;
  font-weight: 750;
}
.restock-quantity-field > label b {
  color: #dc2626;
}
.restock-quantity-input-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-right: 12px;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  background: var(--surface-color);
}
.restock-quantity-input-wrap:focus-within {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgb(16 185 129 / 10%);
}
.restock-quantity-input-wrap input {
  width: 100%;
  min-width: 0;
  min-height: 43px;
  padding: 0 12px;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--text-primary-color);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
}
.restock-quantity-input-wrap > span {
  color: var(--text-tertiary-color);
  font-size: 10px;
}
.restock-quantity-hint {
  color: var(--text-secondary-color);
  font-size: 10px;
}
.restock-quantity-hint strong {
  color: #07845f;
}
.restock-inline-error {
  color: #b42318;
  font-size: 10px;
  font-weight: 650;
}
.restock-detail-extra-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.restock-validation-note {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  border: 1px solid #b9ecda;
  border-radius: 9px;
  background: #effcf7;
  color: #087f5b;
  font-size: 10px;
  line-height: 1.5;
}
.restock-validation-note > :first-child {
  flex: 0 0 auto;
  margin-top: 1px;
}
.restock-validation-note.warning {
  border-color: #fed7aa;
  background: #fff7ed;
  color: #b45309;
}
.restock-validation-note.error {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}
.restock-detail-actions {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  padding-top: 2px;
}
.restock-reveal-enter-active,
.restock-reveal-leave-active {
  transition: opacity 0.22s ease, transform 0.22s ease, max-height 0.28s ease;
  overflow: hidden;
}
.restock-reveal-enter-from,
.restock-reveal-leave-to {
  opacity: 0;
  transform: translateY(-9px);
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
@media (max-width: 900px) {
  .restock-product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .restock-span-third {
    grid-column: span 1;
  }
  .restock-span-half {
    grid-column: span 1;
  }
  .restock-span-full {
    grid-column: 1 / -1;
  }
}
@media (max-width: 600px) {
  .restock-product-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .restock-span-third,
  .restock-span-half,
  .restock-span-full {
    grid-column: 1 / -1;
  }
}
@media (max-width: 650px) {
  .restock-queue {
    padding: 15px 13px 17px;
  }
  .restock-queue-heading {
    align-items: flex-start;
    gap: 10px;
  }
  .restock-count {
    padding: 8px 9px;
  }
  .restock-queue .restock-product-card {
    gap: 9px;
    padding: 11px;
  }
  .restock-queue .restock-product-stock {
    min-width: 62px;
    padding-left: 9px;
  }
  .restock-location-summary {
    grid-template-columns: minmax(0, 1fr);
  }
  .restock-transfer-arrow {
    min-height: 20px;
    transform: rotate(90deg);
  }
  .restock-detail-extra-fields {
    grid-template-columns: minmax(0, 1fr);
  }
  .restock-detail-panel {
    padding: 13px;
  }
  .restock-detail-heading {
    flex-wrap: wrap;
  }
  .restock-change-button {
    margin-left: auto;
  }
  .restock-detail-actions {
    flex-direction: column-reverse;
  }
  .restock-detail-actions .button {
    width: 100%;
  }
}
/* Stock adjustment confirmation: explicit action colors and balanced layout. */
.adjustment-confirm-dialog {
  width: min(680px, 100%);
}
.adjustment-confirm-body {
  gap: 18px;
}
.adjustment-summary {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 5px 16px;
  padding: 18px 20px;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 14px;
  background: color-mix(in srgb, var(--primary-color, #0f9f6e) 5%, white);
}
.adjustment-summary-label {
  grid-column: 1 / -1;
  color: var(--text-tertiary-color, #667085);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
}
.adjustment-summary strong {
  grid-row: 2 / 4;
  grid-column: 2;
  font-size: clamp(26px, 4vw, 34px);
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}
.adjustment-summary strong.is-increase {
  color: #059669;
}
.adjustment-summary strong.is-decrease {
  color: #dc2626;
}
.adjustment-summary > span:last-child {
  color: var(--text-secondary-color, #667085);
  font-size: 12px;
}
.adjustment-details {
  display: grid;
  gap: 0;
  margin: 0;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 12px;
  overflow: hidden;
}
.adjustment-details > div {
  display: grid;
  grid-template-columns: minmax(90px, 120px) minmax(0, 1fr);
  gap: 14px;
  padding: 12px 15px;
}
.adjustment-details > div + div {
  border-top: 1px solid var(--border-color, #e5e7eb);
}
.adjustment-details dt {
  color: var(--text-secondary-color, #667085);
  font-size: 12px;
  font-weight: 650;
}
.adjustment-details dd {
  margin: 0;
  overflow-wrap: anywhere;
  color: var(--text-primary-color, #101828);
  font-size: 12px;
  font-weight: 650;
}
.adjustment-ledger-note {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  margin: 0;
  padding: 12px 14px;
  border: 1px solid color-mix(in srgb, var(--primary-color, #0f9f6e) 24%, white);
  border-radius: 11px;
  background: color-mix(in srgb, var(--primary-color, #0f9f6e) 6%, white);
  color: var(--text-secondary-color, #475467);
  font-size: 11px;
  line-height: 1.55;
}
.adjustment-ledger-note :deep(svg) {
  flex: 0 0 auto;
  margin-top: 1px;
  color: var(--primary-color, #0f9f6e);
}
.adjustment-confirm-footer .adjustment-cancel-button,
.adjustment-confirm-footer .adjustment-confirm-button {
  min-width: 150px;
  min-height: 44px;
  border: 1px solid transparent;
  border-radius: 11px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 0 18px;
  font-size: 12px;
  font-weight: 750;
  transition: filter 0.15s ease, transform 0.15s ease;
}
.adjustment-confirm-footer .adjustment-cancel-button {
  border-color: #dc2626;
  background: #dc2626;
  color: #fff;
}
.adjustment-confirm-footer .adjustment-confirm-button {
  border-color: var(--primary-color, #0f9f6e);
  background: var(--primary-color, #0f9f6e);
  color: #fff;
}
.adjustment-confirm-footer .adjustment-cancel-button:hover:not(:disabled),
.adjustment-confirm-footer .adjustment-confirm-button:hover:not(:disabled) {
  filter: brightness(0.92);
}
.adjustment-confirm-footer .button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
@media (max-width: 560px) {
  .adjustment-confirm-footer .adjustment-cancel-button,
  .adjustment-confirm-footer .adjustment-confirm-button {
    min-width: 0;
    flex: 1;
    padding-inline: 10px;
  }
  .adjustment-details > div {
    grid-template-columns: 82px minmax(0, 1fr);
    gap: 10px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .restock-reveal-enter-active,
  .restock-reveal-leave-active,
  .restock-product-card {
    transition: none;
  }
}

.operation-count-badge {
  display: inline-grid;
  place-items: center;
  min-width: 19px;
  height: 19px;
  padding: 0 5px;
  border-radius: 999px;
  background: #dc2626;
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  line-height: 1;
}
.operation-tab.active .operation-count-badge {
  background: #b91c1c;
  box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.65);
}

.transfer-queue-card {
  margin-top: 18px;
  border: 1px solid var(--border-color);
  border-radius: 16px;
  background: var(--surface-color);
  overflow: hidden;
}
.transfer-queue-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 22px 24px;
  border-bottom: 1px solid var(--border-color);
}
.transfer-queue-heading h2 {
  margin: 5px 0 4px;
  color: var(--text-primary-color);
  font-size: 17px;
  font-weight: 750;
  letter-spacing: -0.02em;
}
.transfer-queue-heading p {
  margin: 0;
  color: var(--text-secondary-color);
  font-size: 13px;
}
.transfer-queue-eyebrow {
  color: var(--primary-color);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
}
.transfer-queue-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-height: 96px;
  padding: 20px;
  color: var(--text-secondary-color);
  font-size: 13px;
}
.transfer-list {
  display: grid;
}
.transfer-row {
  display: grid;
  grid-template-columns:
    minmax(220px, 1.6fr) minmax(110px, 0.6fr) minmax(170px, 0.9fr)
    auto;
  align-items: center;
  gap: 18px;
  padding: 17px 22px;
  border-bottom: 1px solid var(--border-color);
}
.transfer-row:last-child {
  border-bottom: 0;
}
.transfer-row-main {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}
.transfer-product-icon {
  display: grid;
  place-items: center;
  flex: 0 0 40px;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  color: #047857;
  background: #ecfdf5;
}
.transfer-product-copy {
  display: grid;
  min-width: 0;
  gap: 3px;
}
.transfer-product-copy strong {
  color: var(--text-primary-color);
  font-size: 13px;
  font-weight: 750;
}
.transfer-product-copy span,
.transfer-product-copy small,
.transfer-row-meta small,
.transfer-row-quantity span {
  color: var(--text-secondary-color);
  font-size: 11px;
}
.transfer-product-copy small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.transfer-row-quantity {
  display: grid;
  gap: 4px;
}
.transfer-row-quantity strong {
  color: var(--text-primary-color);
  font-size: 14px;
  font-variant-numeric: tabular-nums;
}
.transfer-row-meta {
  display: grid;
  justify-items: start;
  gap: 4px;
}
.transfer-status {
  display: inline-flex;
  padding: 4px 8px;
  border-radius: 999px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 10px;
  font-weight: 750;
  text-transform: capitalize;
}
.transfer-status.status-received {
  background: #ecfdf3;
  color: #027a48;
}
.transfer-status.status-discrepancy {
  background: #fff1f2;
  color: #be123c;
}
.transfer-status.status-partially_received {
  background: #fff7ed;
  color: #c2410c;
}
.transfer-row-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
}
.transfer-row-actions .button {
  min-height: 34px;
  padding: 0 10px;
  font-size: 11px;
}
.transfer-accept {
  background: #059669 !important;
}
.transfer-reject {
  color: #b42318 !important;
}
@media (max-width: 1100px) {
  .transfer-row {
    grid-template-columns: minmax(200px, 1fr) minmax(100px, 0.5fr);
  }
  .transfer-row-meta,
  .transfer-row-actions {
    grid-column: auto;
  }
}
@media (max-width: 650px) {
  .transfer-queue-heading {
    align-items: flex-start;
    flex-direction: column;
    padding: 18px;
  }
  .transfer-row {
    grid-template-columns: 1fr;
    gap: 12px;
    padding: 16px;
  }
  .transfer-row-actions {
    grid-column: 1;
  }
}

/* Modern transfer receipt / issue dialogs */
.transfer-dialog-backdrop {
  position: fixed;
  z-index: 1200;
  inset: 0;
  display: grid;
  place-items: center;
  overflow-y: auto;
  padding: 24px;
  background: rgb(8 15 30 / 64%);
  backdrop-filter: blur(9px) saturate(120%);
}
.transfer-dialog {
  width: min(560px, 100%);
  max-height: min(90dvh, 820px);
  overflow: auto;
  border: 1px solid color-mix(in srgb, var(--border-color, #e5e7eb) 70%, #a8baff);
  border-radius: 22px;
  background: var(--surface-color, #fff);
  color: var(--text-primary-color, #101828);
  box-shadow: 0 28px 90px rgb(0 0 0 / 32%), 0 1px 0 rgb(255 255 255 / 70%) inset;
}
.transfer-dialog-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 24px 24px 20px;
  border-bottom: 1px solid var(--border-color, #edf0f5);
}
.transfer-dialog-icon {
  display: grid;
  place-items: center;
  flex: 0 0 48px;
  width: 48px;
  height: 48px;
  border: 1px solid #ccebdc;
  border-radius: 15px;
  background: linear-gradient(145deg, #edfcf4, #dcf8e9);
  color: #078455;
}
.transfer-dialog-icon.is-issue {
  border-color: #fed7aa;
  background: linear-gradient(145deg, #fff7ed, #ffedd5);
  color: #c2410c;
}
.transfer-dialog-heading {
  flex: 1;
  min-width: 0;
}
.transfer-dialog-eyebrow {
  color: var(--text-secondary-color, #667085);
  font-size: 9px;
  font-weight: 850;
  letter-spacing: 0.14em;
}
.transfer-dialog-heading h2 {
  margin: 5px 0 6px;
  color: var(--text-primary-color, #101828);
  font-size: 21px;
  font-weight: 800;
  letter-spacing: -0.035em;
  line-height: 1.25;
}
.transfer-dialog-heading p {
  margin: 0;
  color: var(--text-secondary-color, #667085);
  font-size: 12px;
  line-height: 1.55;
}
.transfer-dialog-close {
  display: grid;
  place-items: center;
  flex: 0 0 36px;
  width: 36px;
  height: 36px;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 11px;
  background: var(--surface-color, #fff);
  color: var(--text-secondary-color, #667085);
  cursor: pointer;
}
.transfer-dialog-close:hover {
  background: var(--page-bg, #f8fafc);
}
.transfer-dialog-body {
  display: grid;
  gap: 16px;
  padding: 22px 24px 24px;
}
.transfer-dialog-product {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 13px;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 14px;
  background: var(--page-bg, #f8fafc);
}
.transfer-dialog-product-icon {
  display: grid;
  place-items: center;
  flex: 0 0 40px;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--surface-color, #fff);
  color: var(--primary-color, #059669);
}
.transfer-dialog-product-copy {
  display: grid;
  flex: 1;
  min-width: 0;
  gap: 4px;
}
.transfer-dialog-product-copy strong {
  color: var(--text-primary-color, #101828);
  font-size: 13px;
  font-weight: 800;
}
.transfer-dialog-product-copy span {
  overflow-wrap: anywhere;
  color: var(--text-secondary-color, #667085);
  font-size: 10px;
}
.transfer-dialog-quantity {
  flex-shrink: 0;
  padding: 6px 9px;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 9px;
  background: var(--surface-color, #fff);
  color: var(--text-primary-color, #101828);
  font-size: 11px;
  font-weight: 750;
}
.transfer-dialog-route {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 24px minmax(0, 1fr);
  align-items: center;
  gap: 10px;
  padding: 0 4px;
}
.transfer-dialog-route > div {
  display: grid;
  min-width: 0;
  gap: 5px;
}
.transfer-dialog-route span {
  color: var(--text-secondary-color, #667085);
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.1em;
}
.transfer-dialog-route strong {
  color: var(--text-primary-color, #101828);
  font-size: 12px;
  overflow-wrap: anywhere;
}
.transfer-dialog-route > svg {
  color: var(--text-secondary-color, #667085);
}
.transfer-dialog-notice {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 13px;
  border: 1px solid #fed7aa;
  border-radius: 12px;
  background: #fffaf2;
  color: #9a3412;
}
.transfer-dialog-notice.is-danger {
  border-color: #fecaca;
  background: #fff5f5;
  color: #b42318;
}
.transfer-dialog-notice svg {
  flex: 0 0 auto;
  margin-top: 1px;
}
.transfer-dialog-notice p {
  margin: 0;
  font-size: 11px;
  line-height: 1.55;
}
.transfer-dialog-receipt {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 13px 15px;
  border: 1px solid #ccebdc;
  border-radius: 12px;
  background: #f0fdf6;
}
.transfer-dialog-receipt span {
  color: #166345;
  font-size: 12px;
  font-weight: 650;
}
.transfer-dialog-receipt strong {
  color: #047857;
  font-size: 21px;
  font-weight: 850;
  font-variant-numeric: tabular-nums;
}
.transfer-dialog-reason-label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-primary-color, #101828);
  font-size: 12px;
  font-weight: 750;
}
.transfer-dialog-reason-label span {
  padding: 3px 7px;
  border-radius: 999px;
  background: #fff1f2;
  color: #be123c;
  font-size: 9px;
  font-weight: 800;
}
.transfer-dialog textarea {
  width: 100%;
  min-height: 112px;
  resize: vertical;
  padding: 12px 13px;
  border: 1px solid var(--border-color, #d0d5dd);
  border-radius: 12px;
  outline: none;
  background: var(--surface-color, #fff);
  color: var(--text-primary-color, #101828);
  font: inherit;
  font-size: 12px;
  line-height: 1.6;
  transition: border-color 0.16s ease, box-shadow 0.16s ease;
}
.transfer-dialog textarea:focus {
  border-color: #e58b4d;
  box-shadow: 0 0 0 4px rgb(234 117 35 / 13%);
}
.transfer-dialog textarea::placeholder {
  color: var(--text-secondary-color, #98a2b3);
}
.transfer-dialog-reason-meta {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  color: var(--text-secondary-color, #667085);
  font-size: 10px;
  line-height: 1.5;
}
.transfer-dialog-reason-meta span:last-child {
  flex-shrink: 0;
  font-variant-numeric: tabular-nums;
}
.transfer-dialog-footer {
  position: sticky;
  bottom: 0;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--border-color, #edf0f5);
  background: var(--surface-color, #fff);
}
.transfer-dialog-footer .button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  border-radius: 11px;
  padding: 0 15px;
  font-size: 12px;
  font-weight: 750;
}
.transfer-dialog-submit.is-accept {
  border: 1px solid #059669;
  background: #059669;
  color: #fff;
}
.transfer-dialog-submit.is-accept:hover:not(:disabled) {
  background: #047857;
}
.transfer-dialog-submit.is-issue {
  border: 1px solid #c2410c;
  background: #c2410c;
  color: #fff;
}
.transfer-dialog-submit.is-issue:hover:not(:disabled) {
  background: #9a3412;
}
.transfer-dialog-footer button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}
.transfer-dialog-enter-active,
.transfer-dialog-leave-active {
  transition: opacity 0.18s ease;
}
.transfer-dialog-enter-active .transfer-dialog,
.transfer-dialog-leave-active .transfer-dialog {
  transition: transform 0.18s ease, opacity 0.18s ease;
}
.transfer-dialog-enter-from,
.transfer-dialog-leave-to {
  opacity: 0;
}
.transfer-dialog-enter-from .transfer-dialog,
.transfer-dialog-leave-to .transfer-dialog {
  transform: translateY(10px) scale(0.985);
  opacity: 0.7;
}
@media (max-width: 560px) {
  .transfer-dialog-backdrop {
    align-items: end;
    padding: 8px;
  }
  .transfer-dialog {
    max-height: 94dvh;
    border-radius: 20px 20px 14px 14px;
  }
  .transfer-dialog-header {
    padding: 18px 16px 16px;
    gap: 10px;
  }
  .transfer-dialog-icon {
    flex-basis: 42px;
    width: 42px;
    height: 42px;
    border-radius: 13px;
  }
  .transfer-dialog-heading h2 {
    font-size: 18px;
  }
  .transfer-dialog-body {
    gap: 14px;
    padding: 16px;
  }
  .transfer-dialog-footer {
    padding: 12px 16px max(12px, env(safe-area-inset-bottom));
  }
  .transfer-dialog-footer .button {
    flex: 1;
    padding: 0 10px;
  }
}
/* Stock adjustment confirmation: explicit action colors and balanced layout. */
.adjustment-confirm-dialog {
  width: min(680px, 100%);
}
.adjustment-confirm-body {
  gap: 18px;
}
.adjustment-summary {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 5px 16px;
  padding: 18px 20px;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 14px;
  background: color-mix(in srgb, var(--primary-color, #0f9f6e) 5%, white);
}
.adjustment-summary-label {
  grid-column: 1 / -1;
  color: var(--text-tertiary-color, #667085);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
}
.adjustment-summary strong {
  grid-row: 2 / 4;
  grid-column: 2;
  font-size: clamp(26px, 4vw, 34px);
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}
.adjustment-summary strong.is-increase {
  color: #059669;
}
.adjustment-summary strong.is-decrease {
  color: #dc2626;
}
.adjustment-summary > span:last-child {
  color: var(--text-secondary-color, #667085);
  font-size: 12px;
}
.adjustment-details {
  display: grid;
  gap: 0;
  margin: 0;
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 12px;
  overflow: hidden;
}
.adjustment-details > div {
  display: grid;
  grid-template-columns: minmax(90px, 120px) minmax(0, 1fr);
  gap: 14px;
  padding: 12px 15px;
}
.adjustment-details > div + div {
  border-top: 1px solid var(--border-color, #e5e7eb);
}
.adjustment-details dt {
  color: var(--text-secondary-color, #667085);
  font-size: 12px;
  font-weight: 650;
}
.adjustment-details dd {
  margin: 0;
  overflow-wrap: anywhere;
  color: var(--text-primary-color, #101828);
  font-size: 12px;
  font-weight: 650;
}
.adjustment-ledger-note {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  margin: 0;
  padding: 12px 14px;
  border: 1px solid color-mix(in srgb, var(--primary-color, #0f9f6e) 24%, white);
  border-radius: 11px;
  background: color-mix(in srgb, var(--primary-color, #0f9f6e) 6%, white);
  color: var(--text-secondary-color, #475467);
  font-size: 11px;
  line-height: 1.55;
}
.adjustment-ledger-note :deep(svg) {
  flex: 0 0 auto;
  margin-top: 1px;
  color: var(--primary-color, #0f9f6e);
}
.adjustment-confirm-footer .adjustment-cancel-button,
.adjustment-confirm-footer .adjustment-confirm-button {
  min-width: 150px;
  min-height: 44px;
  border: 1px solid transparent;
  border-radius: 11px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 0 18px;
  font-size: 12px;
  font-weight: 750;
  transition: filter 0.15s ease, transform 0.15s ease;
}
.adjustment-confirm-footer .adjustment-cancel-button {
  border-color: #dc2626;
  background: #dc2626;
  color: #fff;
}
.adjustment-confirm-footer .adjustment-confirm-button {
  border-color: var(--primary-color, #0f9f6e);
  background: var(--primary-color, #0f9f6e);
  color: #fff;
}
.adjustment-confirm-footer .adjustment-cancel-button:hover:not(:disabled),
.adjustment-confirm-footer .adjustment-confirm-button:hover:not(:disabled) {
  filter: brightness(0.92);
}
.adjustment-confirm-footer .button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
@media (max-width: 560px) {
  .adjustment-confirm-footer .adjustment-cancel-button,
  .adjustment-confirm-footer .adjustment-confirm-button {
    min-width: 0;
    flex: 1;
    padding-inline: 10px;
  }
  .adjustment-details > div {
    grid-template-columns: 82px minmax(0, 1fr);
    gap: 10px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .transfer-dialog-enter-active,
  .transfer-dialog-leave-active,
  .transfer-dialog-enter-active .transfer-dialog,
  .transfer-dialog-leave-active .transfer-dialog {
    transition: none;
  }
}
</style>
