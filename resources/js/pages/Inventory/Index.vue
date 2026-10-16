<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Props {
  userName?: string;
  userEmail?: string;
}

interface InventoryStock {
  id: number;
  product_id?: number;
  variant_id?: number | null;
  location_id?: number;
  quantity: string | number;
  reserved_quantity: string | number;
  available_quantity?: string | number;
  low_stock_threshold: string | number;
  is_low_stock?: boolean;
  product?: {
    id: number;
    name: string;
    sku?: string | null;
    product_type?: string;
  } | null;
  variant?: {
    id: number;
    product_id: number;
    name: string;
    sku?: string | null;
    status?: string;
  } | null;
  location?: {
    id: number;
    name: string;
    code?: string | null;
    type?: string;
  } | null;
}

interface StockResponse {
  data?: InventoryStock[];
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  from?: number | null;
  to?: number | null;
  message?: string;
}

const props = withDefaults(defineProps<Props>(), {
  userName: "",
  userEmail: "",
});

const stocks = ref<InventoryStock[]>([]);
const isLoading = ref(false);
const errorMessage = ref("");
const search = ref("");
const currentPage = ref(1);
const perPage = ref(10);
const lastPage = ref(1);
const total = ref(0);
const from = ref<number | null>(null);
const to = ref<number | null>(null);

let activeRequest = 0;

const visibleStocks = computed(() => {
  const query = search.value.trim().toLowerCase();

  if (!query) return stocks.value;

  return stocks.value.filter((stock) => {
    const values = [
      stock.product?.name,
      stock.product?.sku,
      stock.variant?.name,
      stock.variant?.sku,
      stock.location?.name,
      stock.location?.code,
    ];

    return values.some((value) =>
      String(value ?? "")
        .toLowerCase()
        .includes(query)
    );
  });
});

const pageAvailableQuantity = computed(() =>
  stocks.value.reduce((sum, stock) => sum + availableQuantity(stock), 0)
);

const pageLowStockCount = computed(
  () => stocks.value.filter((stock) => isLowStock(stock)).length
);

const pageReservedQuantity = computed(() =>
  stocks.value.reduce((sum, stock) => sum + numeric(stock.reserved_quantity), 0)
);

const showingFrom = computed(() =>
  total.value === 0 ? 0 : from.value ?? (currentPage.value - 1) * perPage.value + 1
);

const showingTo = computed(() =>
  total.value === 0
    ? 0
    : to.value ?? Math.min(currentPage.value * perPage.value, total.value)
);

function numeric(value: string | number | null | undefined): number {
  const parsed = Number(value ?? 0);
  return Number.isFinite(parsed) ? parsed : 0;
}

function availableQuantity(stock: InventoryStock): number {
  if (stock.available_quantity !== undefined) {
    return numeric(stock.available_quantity);
  }

  return numeric(stock.quantity) - numeric(stock.reserved_quantity);
}

function isLowStock(stock: InventoryStock): boolean {
  if (stock.is_low_stock !== undefined) return stock.is_low_stock;

  const threshold = numeric(stock.low_stock_threshold);

  return threshold > 0 && availableQuantity(stock) <= threshold;
}

function formatQuantity(value: number | string): string {
  return new Intl.NumberFormat("en-BD", {
    maximumFractionDigits: 4,
  }).format(numeric(value));
}

function productName(stock: InventoryStock): string {
  return stock.product?.name ?? "Unknown product";
}

function productSku(stock: InventoryStock): string {
  return stock.variant?.sku || stock.product?.sku || "No SKU";
}

function variantName(stock: InventoryStock): string {
  return stock.variant?.name || "";
}

function locationName(stock: InventoryStock): string {
  return stock.location?.name ?? "Unknown location";
}

function locationCode(stock: InventoryStock): string {
  return stock.location?.code || "";
}

function getErrorMessage(error: unknown): string {
  return error instanceof Error
    ? error.message
    : "Unable to load inventory stocks. Please try again.";
}

async function loadStocks(page = currentPage.value): Promise<void> {
  const requestId = ++activeRequest;
  isLoading.value = true;
  errorMessage.value = "";

  try {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(perPage.value),
    });

    const response = await fetch(`/api/inventory/stocks?${params.toString()}`, {
      method: "GET",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    const payload = (await response.json()) as StockResponse;

    if (!response.ok) {
      throw new Error(
        payload.message || `Unable to load stocks (HTTP ${response.status}).`
      );
    }

    if (requestId !== activeRequest) return;

    stocks.value = Array.isArray(payload.data) ? payload.data : [];
    currentPage.value = payload.current_page ?? page;
    lastPage.value = Math.max(1, payload.last_page ?? 1);
    perPage.value = payload.per_page ?? perPage.value;
    total.value = payload.total ?? stocks.value.length;
    from.value = payload.from ?? null;
    to.value = payload.to ?? null;
  } catch (error) {
    if (requestId !== activeRequest) return;

    errorMessage.value = getErrorMessage(error);
    stocks.value = [];
    from.value = null;
    to.value = null;
  } finally {
    if (requestId === activeRequest) isLoading.value = false;
  }
}

function refresh(): void {
  void loadStocks(currentPage.value);
}

function goToPage(page: number): void {
  const target = Math.max(1, Math.min(page, lastPage.value));

  if (target === currentPage.value || isLoading.value) return;

  currentPage.value = target;
  void loadStocks(target);
}

watch(perPage, () => {
  currentPage.value = 1;
  void loadStocks(1);
});

onMounted(() => {
  void loadStocks(1);
});
</script>

<template>
  <Head title="Stock Overview" />

  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <div class="inventory-page">
      <!-- Page header -->
      <header class="page-header">
        <div class="page-heading">
          <div class="breadcrumb">
            <span>Inventory</span>
            <Icon name="chevron-right" :size="13" />
            <strong>Stock Overview</strong>
          </div>

          <div class="title-row">
            <div class="page-title-icon">
              <Icon name="boxes" :size="21" />
            </div>

            <div class="title-copy">
              <h1>Stock Overview</h1>
              <p>
                Monitor stock levels, available quantities and inventory across your
                locations.
              </p>
            </div>
          </div>
        </div>

        <div class="header-actions">
          <a class="button button-secondary" href="/inventory/operations">
            <Icon name="arrow-left-right" :size="15" />
            Stock Operations
          </a>
          <button
            type="button"
            class="button button-secondary"
            :disabled="isLoading"
            @click="refresh"
          >
            <Icon name="refresh-cw" :size="15" :class="{ spin: isLoading }" />
            Refresh
          </button>
        </div>
      </header>

      <!-- Context strip -->
      <section class="context-strip" aria-label="Inventory information">
        <div class="context-item">
          <span class="context-dot active"></span>
          <div>
            <strong>Stock control</strong>
            <span>Current inventory balances</span>
          </div>
        </div>

        <div class="context-divider"></div>

        <div class="context-item">
          <span class="context-number">02</span>
          <div>
            <strong>Stock movements</strong>
            <span>Receive, issue and transfer</span>
          </div>
        </div>

        <div class="context-divider"></div>

        <div class="context-item">
          <span class="context-number">03</span>
          <div>
            <strong>Low stock monitoring</strong>
            <span>Threshold-based alerts</span>
          </div>
        </div>
      </section>

      <!-- Summary cards -->
      <section class="stats-grid" aria-label="Stock summary">
        <article class="stat-card">
          <div class="stat-top">
            <span class="stat-icon blue">
              <Icon name="boxes" :size="19" />
            </span>
            <span class="stat-caption">All pages</span>
          </div>

          <div class="stat-value">{{ formatQuantity(total) }}</div>
          <div class="stat-label">Stock records</div>
          <p class="stat-description">Total records matching the API result.</p>
        </article>

        <article class="stat-card">
          <div class="stat-top">
            <span class="stat-icon green">
              <Icon name="circle-check" :size="19" />
            </span>
            <span class="stat-caption">Current page</span>
          </div>

          <div class="stat-value">
            {{ formatQuantity(pageAvailableQuantity) }}
          </div>
          <div class="stat-label">Available quantity</div>
          <p class="stat-description">Sum of available stock on this page.</p>
        </article>

        <article class="stat-card">
          <div class="stat-top">
            <span class="stat-icon amber">
              <Icon name="alert-triangle" :size="19" />
            </span>
            <span class="stat-caption">Current page</span>
          </div>

          <div class="stat-value">{{ formatQuantity(pageLowStockCount) }}</div>
          <div class="stat-label">Low stock records</div>
          <p class="stat-description">Records at or below their threshold.</p>
        </article>

        <article class="stat-card">
          <div class="stat-top">
            <span class="stat-icon violet">
              <Icon name="calendar-check" :size="19" />
            </span>
            <span class="stat-caption">Current page</span>
          </div>

          <div class="stat-value">
            {{ formatQuantity(pageReservedQuantity) }}
          </div>
          <div class="stat-label">Reserved quantity</div>
          <p class="stat-description">Quantity reserved on this page.</p>
        </article>
      </section>

      <!-- Stock table -->
      <section class="card stock-card">
        <div class="section-heading">
          <div class="section-icon">
            <Icon name="package" :size="18" />
          </div>

          <div class="section-heading-copy">
            <h2>Inventory records</h2>
            <p>Review product stock, reservations and location balances.</p>
          </div>

          <span class="record-badge"> {{ formatQuantity(total) }} records </span>
        </div>

        <div class="table-toolbar">
          <div class="search-box">
            <Icon name="search" :size="16" />
            <input
              v-model="search"
              type="search"
              aria-label="Search visible stock records"
              placeholder="Search product, SKU or location..."
            />
            <button
              v-if="search"
              type="button"
              class="clear-search"
              aria-label="Clear search"
              @click="search = ''"
            >
              <Icon name="x" :size="14" />
            </button>
          </div>

          <div class="toolbar-right">
            <label class="rows-label" for="inventory-per-page">Rows</label>
            <div class="select-wrap">
              <select id="inventory-per-page" v-model.number="perPage">
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
              </select>
              <Icon name="chevron-down" :size="14" />
            </div>
          </div>
        </div>

        <div v-if="errorMessage" class="state-panel error-panel" role="alert">
          <div class="state-icon error-icon">
            <Icon name="alert-circle" :size="22" />
          </div>
          <h3>Unable to load inventory</h3>
          <p>{{ errorMessage }}</p>
          <button type="button" class="button button-secondary" @click="refresh">
            <Icon name="refresh-cw" :size="14" />
            Try again
          </button>
        </div>

        <div v-else-if="isLoading && stocks.length === 0" class="state-panel">
          <div class="loading-icon">
            <Icon name="loader-2" :size="25" class="spin" />
          </div>
          <h3>Loading inventory</h3>
          <p>Retrieving your current stock records...</p>
        </div>

        <div v-else-if="!isLoading && stocks.length === 0" class="state-panel">
          <div class="state-icon">
            <Icon name="package-open" :size="23" />
          </div>
          <h3>No stock records yet</h3>
          <p>Inventory records will appear here when stock exists in your locations.</p>
          <button type="button" class="button button-secondary" @click="refresh">
            <Icon name="refresh-cw" :size="14" />
            Refresh records
          </button>
        </div>

        <div v-else-if="visibleStocks.length === 0" class="state-panel compact-state">
          <div class="state-icon">
            <Icon name="search" :size="21" />
          </div>
          <h3>No matching records</h3>
          <p>Try another product name, SKU or location.</p>
          <button type="button" class="text-button" @click="search = ''">
            Clear search
          </button>
        </div>

        <div v-else class="table-container" :class="{ 'is-refreshing': isLoading }">
          <table>
            <thead>
              <tr>
                <th scope="col">Product</th>
                <th scope="col">Location</th>
                <th scope="col" class="numeric-cell">On hand</th>
                <th scope="col" class="numeric-cell">Reserved</th>
                <th scope="col" class="numeric-cell">Available</th>
                <th scope="col" class="numeric-cell">Threshold</th>
                <th scope="col">Stock status</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="stock in visibleStocks" :key="stock.id">
                <td>
                  <div class="product-cell">
                    <div class="product-avatar">
                      <Icon name="package" :size="17" />
                    </div>

                    <div class="product-details">
                      <strong>{{ productName(stock) }}</strong>
                      <span>{{ productSku(stock) }}</span>
                      <small v-if="variantName(stock)">
                        {{ variantName(stock) }}
                      </small>
                    </div>
                  </div>
                </td>

                <td>
                  <div class="location-cell">
                    <strong>{{ locationName(stock) }}</strong>
                    <span v-if="locationCode(stock)">
                      {{ locationCode(stock) }}
                    </span>
                  </div>
                </td>

                <td class="numeric-cell quantity-cell">
                  {{ formatQuantity(stock.quantity) }}
                </td>

                <td class="numeric-cell muted-number">
                  {{ formatQuantity(stock.reserved_quantity) }}
                </td>

                <td class="numeric-cell available-cell">
                  {{ formatQuantity(availableQuantity(stock)) }}
                </td>

                <td class="numeric-cell muted-number">
                  {{
                    numeric(stock.low_stock_threshold) > 0
                      ? formatQuantity(stock.low_stock_threshold)
                      : "—"
                  }}
                </td>

                <td>
                  <span
                    class="status-badge"
                    :class="isLowStock(stock) ? 'status-low' : 'status-normal'"
                  >
                    <span class="status-dot"></span>
                    {{ isLowStock(stock) ? "Low stock" : "In stock" }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <footer v-if="total > 0" class="table-footer">
          <p class="pagination-summary">
            Showing <strong>{{ showingFrom }}</strong
            >–<strong>{{ showingTo }}</strong> of
            <strong>{{ formatQuantity(total) }}</strong> records
            <span v-if="search" class="filtered-note">
              · {{ visibleStocks.length }} visible on this page
            </span>
          </p>

          <nav class="pagination" aria-label="Inventory pagination">
            <button
              type="button"
              class="pagination-button"
              :disabled="currentPage <= 1 || isLoading"
              aria-label="Previous page"
              @click="goToPage(currentPage - 1)"
            >
              <Icon name="chevron-left" :size="16" />
            </button>

            <span class="page-indicator">
              Page <strong>{{ currentPage }}</strong> of
              <strong>{{ lastPage }}</strong>
            </span>

            <button
              type="button"
              class="pagination-button"
              :disabled="currentPage >= lastPage || isLoading"
              aria-label="Next page"
              @click="goToPage(currentPage + 1)"
            >
              <Icon name="chevron-right" :size="16" />
            </button>
          </nav>
        </footer>
      </section>

      <p class="page-footnote">
        <Icon name="info-circle" :size="14" />
        Summary quantities are calculated from the current page. Total records come from
        the server paginator.
      </p>
    </div>
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
</style>
