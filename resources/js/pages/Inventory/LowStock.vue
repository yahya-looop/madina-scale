<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface Props { userName?: string; userEmail?: string }
interface StockRecord {
    id: number;
    quantity: string | number;
    reserved_quantity: string | number;
    available_quantity?: string | number;
    low_stock_threshold: string | number;
    is_low_stock?: boolean;
    product?: { id: number; name: string; sku?: string | null } | null;
    variant?: { id: number; name: string; sku?: string | null } | null;
    location?: { id: number; name: string; code?: string | null } | null;
}
interface ApiResponse<T> {
    data?: T[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    from?: number | null;
    to?: number | null;
    message?: string;
}
const props = withDefaults(defineProps<Props>(), { userName: '', userEmail: '' });
const records = ref<StockRecord[]>([]);
const loading = ref(false);
const error = ref('');
const search = ref('');
const page = ref(1);
const perPage = ref(10);
const lastPage = ref(1);
const total = ref(0);
let requestSequence = 0;

const filteredRecords = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return records.value;
    return records.value.filter((row) => [
        row.product?.name, row.product?.sku, row.variant?.name, row.variant?.sku,
        row.location?.name, row.location?.code,
    ].some((value) => String(value ?? '').toLowerCase().includes(term)));
});
function num(value: string | number | null | undefined): number {
    const result = Number(value ?? 0);
    return Number.isFinite(result) ? result : 0;
}
function available(row: StockRecord): number {
    return row.available_quantity !== undefined ? num(row.available_quantity) : num(row.quantity) - num(row.reserved_quantity);
}
function low(row: StockRecord): boolean {
    return row.is_low_stock ?? (num(row.low_stock_threshold) > 0 && available(row) <= num(row.low_stock_threshold));
}
function qty(value: number | string): string {
    return new Intl.NumberFormat('en-BD', { maximumFractionDigits: 4 }).format(num(value));
}
function messageOf(errorValue: unknown): string {
    return errorValue instanceof Error ? errorValue.message : 'Unable to load inventory data. Please try again.';
}
async function load(targetPage = page.value): Promise<void> {
    const requestId = ++requestSequence;
    loading.value = true;
    error.value = '';
    try {
        const params = new URLSearchParams({ page: String(targetPage), per_page: String(perPage.value) });
        const response = await fetch(`/api/inventory/low-stock?${params}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json() as ApiResponse<StockRecord>;
        if (!response.ok) throw new Error(payload.message || `Request failed (HTTP ${response.status}).`);
        if (requestId !== requestSequence) return;
        records.value = Array.isArray(payload.data) ? payload.data : [];
        page.value = payload.current_page ?? targetPage;
        lastPage.value = Math.max(1, payload.last_page ?? 1);
        perPage.value = payload.per_page ?? perPage.value;
        total.value = payload.total ?? records.value.length;
    } catch (err) {
        if (requestId !== requestSequence) return;
        error.value = messageOf(err);
        records.value = [];
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
    <Head title="Low Stock" />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <main class="inventory-page">
            <header class="page-header">
                <div class="page-heading">
                    <div class="breadcrumb"><a href="/inventory">Inventory</a><Icon name="chevron-right" :size="13" /><strong>Low Stock</strong></div>
                    <div class="title-row">
                        <div class="page-title-icon warning"><Icon name="alert-triangle" :size="21" /></div>
                        <div class="title-copy"><h1>Low Stock</h1><p>Monitor stock balances at or below their configured threshold.</p></div>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="button" type="button" :disabled="loading" @click="load(page)">
                        <Icon name="refresh-cw" :size="15" :class="{ spin: loading }" /> Refresh
                    </button>
                </div>
            </header>

            <section class="stats-grid" aria-label="Low stock summary">
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-icon amber"><Icon name="alert-triangle" :size="19" /></span><span class="stat-caption">All pages</span></div>
                    <div class="stat-value">{{ qty(total) }}</div><div class="stat-label">Low-stock records</div>
                    <p class="stat-description">Stock balances meeting the threshold rule.</p>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-icon blue"><Icon name="boxes" :size="19" /></span><span class="stat-caption">Current page</span></div>
                    <div class="stat-value">{{ qty(records.length) }}</div><div class="stat-label">Records displayed</div>
                    <p class="stat-description">Matching low-stock rows on this page.</p>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-icon green"><Icon name="package-check" :size="19" /></span><span class="stat-caption">Current page</span></div>
                    <div class="stat-value">{{ qty(records.reduce((sum, row) => sum + available(row), 0)) }}</div><div class="stat-label">Available quantity</div>
                    <p class="stat-description">Available quantity across displayed records.</p>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span class="stat-icon violet"><Icon name="map-pin" :size="19" /></span><span class="stat-caption">Current page</span></div>
                    <div class="stat-value">{{ qty(new Set(records.map(row => row.location?.id).filter(Boolean)).size) }}</div><div class="stat-label">Locations affected</div>
                    <p class="stat-description">Distinct locations in these results.</p>
                </article>
            </section>

            <section class="card">
                <div class="section-heading">
                    <div class="section-icon warning"><Icon name="alert-triangle" :size="18" /></div>
                    <div class="section-heading-copy"><h2>Low-stock records</h2><p>Stock balances at or below their configured threshold.</p></div>
                    <span class="record-badge">{{ qty(total) }} records</span>
                </div>
                <div class="table-toolbar">
                    <label class="search-box"><Icon name="search" :size="16" /><input v-model="search" type="search" placeholder="Search product, SKU or location..." aria-label="Search low-stock records" /><button v-if="search" class="clear-search" type="button" aria-label="Clear search" @click="search = ''"><Icon name="x" :size="14" /></button></label>
                    <div class="toolbar-right"><label for="low-stock-rows" class="rows-label">Rows</label><div class="select-wrap"><select id="low-stock-rows" v-model.number="perPage" @change="page = 1; load(1)"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select><Icon name="chevron-down" :size="14" /></div></div>
                </div>

                <div v-if="error" class="state-panel" role="alert"><div class="state-icon error"><Icon name="alert-circle" :size="22" /></div><h3>Unable to load low-stock records</h3><p>{{ error }}</p><button class="button" type="button" @click="load(page)">Try again</button></div>
                <div v-else-if="loading && records.length === 0" class="state-panel"><div class="state-icon"><Icon name="loader-2" :size="22" class="spin" /></div><h3>Loading low-stock records</h3><p>Retrieving current stock thresholds...</p></div>
                <div v-else-if="filteredRecords.length === 0" class="state-panel compact-state">
                    <div class="state-icon"><Icon :name="search ? 'search' : 'check-circle-2'" :size="22" /></div>
                    <h3>{{ search ? 'No matching records' : 'No low-stock records' }}</h3>
                    <p>{{ search ? 'Try a different product, SKU or location.' : 'When a stock balance reaches its configured threshold, it will appear here.' }}</p>
                    <button v-if="search" class="text-button" type="button" @click="search = ''">Clear search</button>
                    <button v-else class="text-button" type="button" @click="load(page)">Refresh records</button>
                </div>
                <div v-else class="table-container" :class="{ 'is-refreshing': loading }">
                    <table><thead><tr><th>Product</th><th>Location</th><th class="numeric">On hand</th><th class="numeric">Reserved</th><th class="numeric">Available</th><th class="numeric">Threshold</th><th>Status</th></tr></thead>
                        <tbody><tr v-for="row in filteredRecords" :key="row.id">
                            <td><div class="product-cell"><span class="product-avatar"><Icon name="package" :size="17" /></span><span class="product-details"><strong>{{ row.product?.name ?? 'Unknown product' }}</strong><small>{{ row.variant?.sku || row.product?.sku || 'No SKU' }}</small><small v-if="row.variant?.name">{{ row.variant.name }}</small></span></div></td>
                            <td><span class="location-cell"><strong>{{ row.location?.name ?? 'Unknown location' }}</strong><small>{{ row.location?.code || '' }}</small></span></td>
                            <td class="numeric">{{ qty(row.quantity) }}</td><td class="numeric muted">{{ qty(row.reserved_quantity) }}</td><td class="numeric available">{{ qty(available(row)) }}</td><td class="numeric muted">{{ qty(row.low_stock_threshold) }}</td>
                            <td><span class="status-badge"><span class="status-dot"></span>Low stock</span></td>
                        </tr></tbody>
                    </table>
                </div>
                <footer v-if="total > 0" class="table-footer"><p>Showing <strong>{{ (page - 1) * perPage + 1 }}</strong>–<strong>{{ Math.min(page * perPage, total) }}</strong> of <strong>{{ qty(total) }}</strong> records <span v-if="search">· {{ filteredRecords.length }} visible on this page</span></p>
                    <nav class="pagination" aria-label="Low-stock pagination"><button class="pagination-button" type="button" :disabled="page <= 1 || loading" aria-label="Previous page" @click="changePage(page - 1)"><Icon name="chevron-left" :size="16" /></button><span>Page <strong>{{ page }}</strong> of <strong>{{ lastPage }}</strong></span><button class="pagination-button" type="button" :disabled="page >= lastPage || loading" aria-label="Next page" @click="changePage(page + 1)"><Icon name="chevron-right" :size="16" /></button></nav>
                </footer>
            </section>
        </main>
    </ErpLayout>
</template>

<style scoped>
.inventory-page{--bg:var(--background,#f6f8fb);--surface:var(--surface,#fff);--border:var(--border,#e5eaf2);--text:var(--text-primary,#111827);--muted:var(--text-secondary,#667085);--subtle:var(--text-tertiary,#98a2b3);--primary:var(--primary,#2563eb);min-width:0;min-height:100%;padding:0;color:var(--text);background:var(--bg);font-size:12px}
.inventory-page *,.inventory-page *:before,.inventory-page *:after{box-sizing:border-box}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;max-width:1480px;margin:0 auto 20px}.page-heading,.title-copy,.section-heading-copy{min-width:0}
.breadcrumb{display:flex;align-items:center;gap:8px;margin-bottom:12px;color:var(--subtle);font-size:12px}.breadcrumb a{color:inherit;text-decoration:none}.breadcrumb a:hover{color:var(--primary)}.breadcrumb strong{color:var(--muted);font-weight:600}
.title-row{display:flex;align-items:flex-start;gap:14px}.page-title-icon,.section-icon,.stat-icon{display:grid;flex:0 0 auto;place-items:center}.page-title-icon{width:48px;height:48px;margin-top:1px;border:1px solid #fed7aa;border-radius:14px;background:#fff7ed;color:#d97706}
h1{margin:0;color:var(--text);font-size:28px;line-height:1.2;font-weight:750;letter-spacing:-.035em}.page-header p{margin:7px 0 0;color:var(--muted);font-size:14px;line-height:1.5}.header-actions{padding-top:25px}.button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;gap:8px;padding:0 15px;border:1px solid var(--border);border-radius:11px;background:var(--surface);color:var(--muted);font:inherit;font-size:12px;font-weight:650;cursor:pointer;transition:border-color .18s ease,color .18s ease}.button:hover:not(:disabled){border-color:var(--primary);color:var(--primary)}.button:disabled{opacity:.55;cursor:not-allowed}
.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;max-width:1480px;margin:0 auto 20px}.stat-card,.card{border:1px solid var(--border);border-radius:15px;background:var(--surface);box-shadow:0 2px 10px rgb(16 24 40 / 2.5%)}.stat-card{min-width:0;padding:18px 20px;animation:enter .28s ease both}.stat-top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}.stat-icon{width:40px;height:40px;border:1px solid transparent;border-radius:12px}.stat-icon.amber,.section-icon.warning{border-color:#fed7aa;background:#fff7ed;color:#d97706}.stat-icon.blue{border-color:#dbeafe;background:#eff6ff;color:#2563eb}.stat-icon.green{border-color:#d1fae5;background:#ecfdf5;color:#059669}.stat-icon.violet{border-color:#ede9fe;background:#f5f3ff;color:#7c3aed}.stat-caption{color:var(--subtle);font-size:11px;font-weight:600}.stat-value{overflow-wrap:anywhere;color:var(--text);font-size:26px;line-height:1.2;font-weight:750;letter-spacing:-.035em;font-variant-numeric:tabular-nums}.stat-label{margin-top:6px;color:var(--text);font-size:13px;font-weight:650}.stat-description{margin:7px 0 0;color:var(--subtle);font-size:11px;line-height:1.5}
.card{max-width:1480px;margin:0 auto;overflow:hidden}.section-heading{display:flex;align-items:flex-start;gap:12px;padding:22px 22px 0}.section-icon{width:40px;height:40px;border:1px solid #fed7aa;border-radius:12px;background:#fff7ed;color:#d97706}.section-heading h2{margin:2px 0 4px;font-size:15px;font-weight:700}.section-heading p{margin:0;color:var(--muted);font-size:13px;line-height:1.5}.record-badge{flex:0 0 auto;margin-left:auto;padding:8px 10px;border:1px solid var(--border);border-radius:9px;background:#fafbfc;color:var(--muted);font-size:11px;font-weight:650}
.table-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:20px 22px}.search-box{display:flex;width:min(100%,480px);min-width:0;height:42px;align-items:center;gap:10px;padding:0 13px;border:1px solid var(--border);border-radius:11px;background:var(--surface);color:var(--subtle)}.search-box:focus-within{border-color:var(--primary);box-shadow:0 0 0 3px rgb(37 99 235 / 8%)}.search-box input{width:100%;min-width:0;height:100%;padding:0;border:0;outline:0;background:transparent;color:var(--text);font:inherit;font-size:12px}.search-box input::placeholder{color:var(--subtle)}.clear-search{display:grid;width:26px;height:26px;place-items:center;border:0;border-radius:6px;background:transparent;color:var(--subtle);cursor:pointer}.toolbar-right{display:flex;align-items:center;gap:9px}.rows-label{color:var(--muted);font-size:12px;font-weight:600}.select-wrap{position:relative;display:flex;height:40px;min-width:78px;align-items:center}.select-wrap select{width:100%;height:100%;padding:0 30px 0 12px;appearance:none;border:1px solid var(--border);border-radius:10px;background:var(--surface);color:var(--text);font:inherit;cursor:pointer}.select-wrap svg{position:absolute;right:10px;pointer-events:none;color:var(--subtle)}
.table-container{width:100%;overflow-x:auto;border-top:1px solid var(--border);transition:opacity .18s}.table-container.is-refreshing{opacity:.65}table{width:100%;border-collapse:separate;border-spacing:0;text-align:left;white-space:nowrap}thead{background:#f8fafc}th{padding:13px 16px;border-bottom:1px solid var(--border);color:var(--muted);font-size:10px;font-weight:700;letter-spacing:.025em}td{padding:14px 16px;border-bottom:1px solid #eef0f4;color:var(--muted);font-size:12px;vertical-align:middle}tbody tr:hover{background:#fafcff}tbody tr:last-child td{border-bottom:0}th:first-child,td:first-child{padding-left:22px}th:last-child,td:last-child{padding-right:22px}.numeric{text-align:right;font-variant-numeric:tabular-nums}.muted{color:var(--muted)}.available{color:var(--text);font-weight:700}.product-cell{display:flex;min-width:180px;align-items:center;gap:10px}.product-avatar{display:grid;width:36px;height:36px;flex:0 0 auto;place-items:center;border:1px solid var(--border);border-radius:10px;background:#f8fafc;color:#64748b}.product-details,.location-cell{display:flex;min-width:0;flex-direction:column;gap:4px}.product-details strong,.location-cell strong{max-width:240px;overflow:hidden;color:var(--text);font-size:12px;font-weight:650;text-overflow:ellipsis}.product-details small,.location-cell small{color:var(--subtle);font-size:10px}.status-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border:1px solid #fed7aa;border-radius:7px;background:#fff7ed;color:#c2410c;font-size:10px;font-weight:650}.status-dot{width:6px;height:6px;border-radius:50%;background:currentColor}
.state-panel{display:flex;min-height:220px;flex-direction:column;align-items:center;justify-content:center;padding:28px 22px;text-align:center}.compact-state{min-height:210px}.state-panel h3{margin:12px 0 5px;color:var(--text);font-size:14px;font-weight:700}.state-panel p{max-width:440px;margin:0 0 12px;color:var(--muted);font-size:12px;line-height:1.6}.state-icon{display:grid;width:48px;height:48px;place-items:center;border:1px solid var(--border);border-radius:14px;background:#f8fafc;color:#64748b}.state-icon.error{border-color:#fecaca;background:#fef2f2;color:#dc2626}.text-button{padding:5px;border:0;background:transparent;color:var(--primary);font:inherit;font-size:12px;font-weight:650;cursor:pointer}.table-footer{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:15px 22px;border-top:1px solid var(--border)}.table-footer p{margin:0;color:var(--muted);font-size:11px;line-height:1.6}.table-footer strong,.pagination strong{color:var(--text)}.pagination{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:11px;white-space:nowrap}.pagination-button{display:grid;width:34px;height:34px;place-items:center;border:1px solid var(--border);border-radius:9px;background:var(--surface);color:var(--muted);cursor:pointer}.pagination-button:disabled{opacity:.4;cursor:not-allowed}.spin{animation:spin .9s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@keyframes enter{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.page-header{flex-direction:column;gap:12px}.header-actions{padding-top:0}h1{font-size:24px}.page-header p{font-size:12px}.stats-grid{gap:11px}.stat-card{padding:15px}.section-heading{padding:16px 15px 0}.table-toolbar{align-items:stretch;flex-direction:column;padding:15px}.search-box{width:100%}.table-footer{align-items:flex-start;flex-direction:column;padding:14px 15px}.pagination{align-self:flex-end}th:first-child,td:first-child{padding-left:15px}th:last-child,td:last-child{padding-right:15px}}@media(max-width:430px){.stats-grid{grid-template-columns:minmax(0,1fr)}.record-badge{max-width:90px;overflow:hidden;text-overflow:ellipsis}}@media(prefers-reduced-motion:reduce){.inventory-page *{animation-duration:.01ms!important;transition-duration:.01ms!important}}
</style>
