<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Props {
  userName?: string;
  userEmail?: string;
}
interface Movement {
  id: number;
  type?: string;
  movement_type?: string;
  quantity?: string | number;
  quantity_change?: string | number;
  quantity_delta?: string | number;
  reference?: string | null;
  reference_type?: string | null;
  reference_id?: number | string | null;
  notes?: string | null;
  reason?: string | null;
  created_at?: string | null;
  created_by?: { name?: string } | null;
  creator?: { name?: string } | null;
  product?: { id?: number; name?: string; sku?: string | null } | null;
  variant?: { name?: string; sku?: string | null } | null;
  location?: { name?: string; code?: string | null } | null;
  from_location?: { name?: string } | null;
  to_location?: { name?: string } | null;
}
interface ApiResponse {
  data?: Movement[];
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  message?: string;
}
const props = withDefaults(defineProps<Props>(), { userName: "", userEmail: "" });
const rows = ref<Movement[]>([]);
const loading = ref(false);
const error = ref("");
const search = ref("");
const typeFilter = ref("all");
const page = ref(1);
const perPage = ref(10);
const lastPage = ref(1);
const total = ref(0);
let requestSequence = 0;
const typeOptions = [
  { value: "all", label: "All types" },
  { value: "receive", label: "Stock In" },
  { value: "issue", label: "Stock Out" },
  { value: "order_out", label: "Order Out (Fulfillment)" },
  { value: "adjust", label: "Stock Adjustment" },
  { value: "transfer", label: "Transfer" },
];
const filteredRows = computed(() => {
  const term = search.value.trim().toLowerCase();
  return rows.value.filter((row) => {
    const kind = movementType(row);
    const typeMatches = typeFilter.value === "all" || kind === typeFilter.value;
    const textMatches =
      !term ||
      [
        row.product?.name,
        row.product?.sku,
        row.variant?.name,
        row.variant?.sku,
        row.location?.name,
        row.location?.code,
        row.from_location?.name,
        row.to_location?.name,
        row.reference,
        row.reference_type,
        row.reference_id,
        row.notes,
        row.reason,
        row.created_by?.name,
        row.creator?.name,
      ].some((value) =>
        String(value ?? "")
          .toLowerCase()
          .includes(term)
      );
    return typeMatches && textMatches;
  });
});
function num(value: string | number | undefined): number {
  const n = Number(value ?? 0);
  return Number.isFinite(n) ? n : 0;
}
function qty(value: string | number | undefined): string {
  return new Intl.NumberFormat("en-BD", { maximumFractionDigits: 4 }).format(num(value));
}
function movementType(row: Movement): string {
  const raw = String(row.type ?? row.movement_type ?? "").toLowerCase();
  if (
    raw.includes("receiv") ||
    raw.includes("receipt") ||
    raw.includes("inbound") ||
    raw === "in"
  )
    return "receive";
  if (raw.includes("order_fulfillment") || raw.includes("order fulfillment"))
    return "order_out";
  if (raw.includes("issu") || raw.includes("outbound") || raw === "out") return "issue";
  if (raw.includes("transfer")) return "transfer";
  if (raw.includes("adjust")) return "adjust";
  return raw || "other";
}
function typeLabel(row: Movement): string {
  const kind = movementType(row);
  return (
    ({
      receive: "Stock In",
      issue: "Stock Out",
      order_out: "Order Out (Fulfillment)",
      transfer: "Stock Transfer",
      adjust: "Stock Adjustment",
    } as Record<string, string>)[kind] ??
    kind.replace(/[_-]/g, " ").replace(/\b\w/g, (c) => c.toUpperCase())
  );
}
function typeIcon(row: Movement): string {
  return (
    ({
      receive: "package-import",
      issue: "stock-out",
      order_out: "shopping-cart",
      transfer: "arrows-left-right",
      adjust: "sliders-horizontal",
    } as Record<string, string>)[movementType(row)] ?? "history"
  );
}
function dateLabel(value?: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : new Intl.DateTimeFormat("en-BD", {
        dateStyle: "medium",
        timeStyle: "short",
      }).format(date);
}
function errorText(value: unknown): string {
  return value instanceof Error
    ? value.message
    : "Unable to load stock movements. Please try again.";
}
async function load(targetPage = page.value): Promise<void> {
  const requestId = ++requestSequence;
  loading.value = true;
  error.value = "";
  try {
    const params = new URLSearchParams({
      page: String(targetPage),
      per_page: String(perPage.value),
    });
    const response = await fetch(`/api/inventory/movements?${params}`, {
      credentials: "same-origin",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const payload = (await response.json()) as ApiResponse;
    if (!response.ok)
      throw new Error(payload.message || `Request failed (HTTP ${response.status}).`);
    if (requestId !== requestSequence) return;
    rows.value = Array.isArray(payload.data) ? payload.data : [];
    page.value = payload.current_page ?? targetPage;
    lastPage.value = Math.max(1, payload.last_page ?? 1);
    perPage.value = payload.per_page ?? perPage.value;
    total.value = payload.total ?? rows.value.length;
  } catch (err) {
    if (requestId !== requestSequence) return;
    error.value = errorText(err);
    rows.value = [];
    total.value = 0;
  } finally {
    if (requestId === requestSequence) loading.value = false;
  }
}
function changePage(target: number): void {
  const next = Math.max(1, Math.min(target, lastPage.value));
  if (next === page.value || loading.value) return;
  page.value = next;
  void load(next);
}
onMounted(() => void load(1));
</script>

<template>
  <Head title="Stock Movements" />
  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <main class="inventory-page">
      <header class="page-header">
        <div class="page-heading">
          <div class="breadcrumb">
            <a href="/inventory">Inventory</a
            ><Icon name="chevron-right" :size="13" /><strong>Stock Movements</strong>
          </div>
          <div class="title-row">
            <div class="page-title-icon"><Icon name="history" :size="22" /></div>
            <div class="title-copy">
              <h1>Stock Movements</h1>
              <p>Review stock in, stock out, order out, adjustments and transfers.</p>
            </div>
          </div>
        </div>
        <div class="header-actions">
          <button class="button" type="button" :disabled="loading" @click="load(page)">
            <Icon name="refresh-cw" :size="15" :class="{ spin: loading }" /> Refresh
          </button>
        </div>
      </header>

      <section class="stats-grid" aria-label="Movement summary">
        <article class="stat-card">
          <div class="stat-top">
            <span class="stat-icon blue"><Icon name="history" :size="19" /></span
            ><span class="stat-caption">All pages</span>
          </div>
          <div class="stat-value">{{ qty(total) }}</div>
          <div class="stat-label">Movement records</div>
          <p class="stat-description">Recorded inventory ledger entries.</p>
        </article>
        <article class="stat-card">
          <div class="stat-top">
            <span class="stat-icon green"><Icon name="receipt" :size="19" /></span
            ><span class="stat-caption">Current page</span>
          </div>
          <div class="stat-value">
            {{ qty(rows.filter((row) => movementType(row) === "receive").length) }}
          </div>
          <div class="stat-label">Receipts</div>
          <p class="stat-description">Stock entries received on this page.</p>
        </article>
        <article class="stat-card">
          <div class="stat-top">
            <span class="stat-icon amber"><Icon name="stock-out" :size="19" /></span
            ><span class="stat-caption">Current page</span>
          </div>
          <div class="stat-value">
            {{ qty(rows.filter((row) => movementType(row) === "issue").length) }}
          </div>
          <div class="stat-label">Stock Out</div>
          <p class="stat-description">Manual stock-out records on this page.</p>
        </article>
        <article class="stat-card">
          <div class="stat-top">
            <span class="stat-icon violet"><Icon name="dots" :size="19" /></span
            ><span class="stat-caption">Current page</span>
          </div>
          <div class="stat-value">
            {{
              qty(
                rows.filter((row) => ["transfer", "adjust"].includes(movementType(row)))
                  .length
              )
            }}
          </div>
          <div class="stat-label">Other movements</div>
          <p class="stat-description">Transfers and adjustments on this page.</p>
        </article>
      </section>

      <section class="card">
        <div class="section-heading">
          <div class="section-icon"><Icon name="history" :size="19" /></div>
          <div class="section-heading-copy">
            <h2>Movement history</h2>
            <p>Recorded stock transactions and their references.</p>
          </div>
          <span class="record-badge">{{ qty(total) }} records</span>
        </div>
        <div class="table-toolbar">
          <label class="search-box"
            ><Icon name="search" :size="16" /><input
              v-model="search"
              type="search"
              placeholder="Search product, reference or location..."
              aria-label="Search stock movements" /><button
              v-if="search"
              class="clear-search"
              type="button"
              aria-label="Clear search"
              @click="search = ''"
            >
              <Icon name="x" :size="14" /></button
          ></label>
          <div class="toolbar-right">
            <label for="movement-type" class="rows-label">Type</label>
            <div class="select-wrap wide">
              <select id="movement-type" v-model="typeFilter">
                <option
                  v-for="option in typeOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option></select
              ><Icon name="chevron-down" :size="14" />
            </div>
            <label for="movement-rows" class="rows-label rows-label-gap">Rows</label>
            <div class="select-wrap">
              <select
                id="movement-rows"
                v-model.number="perPage"
                @change="
                  page = 1;
                  load(1);
                "
              >
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option></select
              ><Icon name="chevron-down" :size="14" />
            </div>
          </div>
        </div>
        <div v-if="error" class="state-panel" role="alert">
          <div class="state-icon error"><Icon name="alert-circle" :size="22" /></div>
          <h3>Unable to load stock movements</h3>
          <p>{{ error }}</p>
          <button class="button" type="button" @click="load(page)">Try again</button>
        </div>
        <div v-else-if="loading && rows.length === 0" class="state-panel">
          <div class="state-icon"><Icon name="loader-2" :size="22" class="spin" /></div>
          <h3>Loading movement history</h3>
          <p>Retrieving stock ledger entries...</p>
        </div>
        <div v-else-if="filteredRows.length === 0" class="state-panel compact-state">
          <div class="state-icon">
            <Icon
              :name="search || typeFilter !== 'all' ? 'search' : 'history'"
              :size="22"
            />
          </div>
          <h3>
            {{
              search || typeFilter !== "all"
                ? "No matching movements"
                : "No stock movements yet"
            }}
          </h3>
          <p>
            {{
              search || typeFilter !== "all"
                ? "Try changing the search term or movement type."
                : "Stock in, stock out, order out, adjustment and transfer records will appear here when transactions are recorded."
            }}
          </p>
          <button
            v-if="search || typeFilter !== 'all'"
            class="text-button"
            type="button"
            @click="
              search = '';
              typeFilter = 'all';
            "
          >
            Clear filters
          </button>
        </div>
        <div v-else class="table-container" :class="{ 'is-refreshing': loading }">
          <table>
            <thead>
              <tr>
                <th>Movement</th>
                <th>Product</th>
                <th>Location / route</th>
                <th class="numeric">Quantity</th>
                <th>Reference</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in filteredRows" :key="row.id">
                <td>
                  <span class="movement-kind" :class="`kind-${movementType(row)}`"
                    ><Icon :name="typeIcon(row)" :size="14" />{{ typeLabel(row) }}</span
                  >
                </td>
                <td>
                  <div class="product-cell">
                    <span class="product-avatar"><Icon name="package" :size="17" /></span
                    ><span class="product-details"
                      ><strong>{{ row.product?.name ?? "Unknown product" }}</strong
                      ><small>{{
                        row.variant?.sku || row.product?.sku || "No SKU"
                      }}</small></span
                    >
                  </div>
                </td>
                <td>
                  <span class="location-cell"
                    ><strong>{{
                      row.from_location?.name && row.to_location?.name
                        ? `${row.from_location.name} → ${row.to_location.name}`
                        : row.location?.name ?? "—"
                    }}</strong
                    ><small>{{ row.location?.code || "" }}</small></span
                  >
                </td>
                <td class="numeric quantity">
                  {{ qty(row.quantity ?? row.quantity_change ?? row.quantity_delta) }}
                </td>
                <td>
                  <span class="reference-cell"
                    ><strong>{{
                      row.reference ||
                      (row.reference_type && row.reference_id
                        ? `${row.reference_type} #${row.reference_id}`
                        : "—")
                    }}</strong
                    ><small>{{ row.notes || row.reason || "" }}</small></span
                  >
                </td>
                <td class="date-cell">{{ dateLabel(row.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer v-if="total > 0" class="table-footer">
          <p>
            Showing <strong>{{ (page - 1) * perPage + 1 }}</strong
            >–<strong>{{ Math.min(page * perPage, total) }}</strong> of
            <strong>{{ qty(total) }}</strong> records
            <span v-if="search || typeFilter !== 'all'"
              >· {{ filteredRows.length }} visible on this page</span
            >
          </p>
          <nav class="pagination" aria-label="Movement pagination">
            <button
              class="pagination-button"
              type="button"
              :disabled="page <= 1 || loading"
              aria-label="Previous page"
              @click="changePage(page - 1)"
            >
              <Icon name="chevron-left" :size="16" /></button
            ><span
              >Page <strong>{{ page }}</strong> of <strong>{{ lastPage }}</strong></span
            ><button
              class="pagination-button"
              type="button"
              :disabled="page >= lastPage || loading"
              aria-label="Next page"
              @click="changePage(page + 1)"
            >
              <Icon name="chevron-right" :size="16" />
            </button>
          </nav>
        </footer>
      </section>
      <p class="page-footnote">
        <Icon name="info-circle" :size="14" />Movement history is an audit trail. Entries
        reflect transactions recorded by the inventory service.
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

/* Shared Inventory table variants */
.numeric {
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.muted {
  color: var(--text-secondary-color);
  font-variant-numeric: tabular-nums;
}
.available {
  color: var(--text-primary-color);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.quantity {
  color: var(--text-primary-color);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.reference-cell {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 4px;
}
.reference-cell strong {
  max-width: 260px;
  overflow: hidden;
  color: var(--text-primary-color);
  font-size: 11px;
  font-weight: 650;
  text-overflow: ellipsis;
}
.reference-cell small {
  max-width: 260px;
  overflow: hidden;
  color: var(--text-tertiary-color);
  font-size: 10px;
  text-overflow: ellipsis;
}
.date-cell {
  color: var(--text-secondary-color);
  font-size: 10px;
  white-space: nowrap;
}
.movement-kind {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  width: fit-content;
  padding: 6px 9px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: #f8fafc;
  color: var(--text-secondary-color);
  font-size: 10px;
  font-weight: 650;
}
.movement-kind.kind-receive {
  border-color: #bbf7d0;
  background: #f0fdf4;
  color: #15803d;
}
.movement-kind.kind-issue {
  border-color: #fecaca;
  background: #fef2f2;
  color: #b91c1c;
}
.movement-kind.kind-transfer {
  border-color: #bfdbfe;
  background: #eff6ff;
  color: #1d4ed8;
}
.movement-kind.kind-adjust {
  border-color: #fed7aa;
  background: #fff7ed;
  color: #b45309;
}
.page-title-icon.warning {
  border-color: #fed7aa;
  background: #fff7ed;
  color: #d97706;
}
.section-icon.warning {
  border-color: #fed7aa;
  background: #fff7ed;
  color: #d97706;
}
</style>
