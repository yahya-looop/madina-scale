<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface Props { userName?: string; userEmail?: string }
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
interface ApiResponse { data?: Movement[]; current_page?: number; last_page?: number; per_page?: number; total?: number; message?: string }
const props = withDefaults(defineProps<Props>(), { userName: '', userEmail: '' });
const rows = ref<Movement[]>([]);
const loading = ref(false);
const error = ref('');
const search = ref('');
const typeFilter = ref('all');
const page = ref(1);
const perPage = ref(10);
const lastPage = ref(1);
const total = ref(0);
let requestSequence = 0;
const typeOptions = [
    { value: 'all', label: 'All types' }, { value: 'receive', label: 'Receive' },
    { value: 'issue', label: 'Issue' }, { value: 'adjust', label: 'Adjustment' },
    { value: 'transfer', label: 'Transfer' },
];
const filteredRows = computed(() => {
    const term = search.value.trim().toLowerCase();
    return rows.value.filter((row) => {
        const kind = movementType(row);
        const typeMatches = typeFilter.value === 'all' || kind === typeFilter.value;
        const textMatches = !term || [
            row.product?.name, row.product?.sku, row.variant?.name, row.variant?.sku,
            row.location?.name, row.location?.code, row.from_location?.name, row.to_location?.name,
            row.reference, row.reference_type, row.reference_id, row.notes, row.reason, row.created_by?.name, row.creator?.name,
        ].some((value) => String(value ?? '').toLowerCase().includes(term));
        return typeMatches && textMatches;
    });
});
function num(value: string | number | undefined): number {
    const n = Number(value ?? 0); return Number.isFinite(n) ? n : 0;
}
function qty(value: string | number | undefined): string {
    return new Intl.NumberFormat('en-BD', { maximumFractionDigits: 4 }).format(num(value));
}
function movementType(row: Movement): string {
    const raw = String(row.type ?? row.movement_type ?? '').toLowerCase();
    if (raw.includes('receiv') || raw.includes('receipt') || raw.includes('inbound') || raw === 'in') return 'receive';
    if (raw.includes('issu') || raw.includes('outbound') || raw === 'out') return 'issue';
    if (raw.includes('transfer')) return 'transfer';
    if (raw.includes('adjust')) return 'adjust';
    return raw || 'other';
}
function typeLabel(row: Movement): string {
    const kind = movementType(row);
    return ({ receive: 'Receive', issue: 'Issue', transfer: 'Transfer', adjust: 'Adjustment' } as Record<string, string>)[kind] ?? kind.replace(/[_-]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
function typeIcon(row: Movement): string {
    return ({ receive: 'arrow-down-left', issue: 'arrow-up-right', transfer: 'arrow-left-right', adjust: 'sliders-horizontal' } as Record<string, string>)[movementType(row)] ?? 'history';
}
function dateLabel(value?: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('en-BD', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}
function errorText(value: unknown): string { return value instanceof Error ? value.message : 'Unable to load stock movements. Please try again.'; }
async function load(targetPage = page.value): Promise<void> {
    const requestId = ++requestSequence;
    loading.value = true; error.value = '';
    try {
        const params = new URLSearchParams({ page: String(targetPage), per_page: String(perPage.value) });
        const response = await fetch(`/api/inventory/movements?${params}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json() as ApiResponse;
        if (!response.ok) throw new Error(payload.message || `Request failed (HTTP ${response.status}).`);
        if (requestId !== requestSequence) return;
        rows.value = Array.isArray(payload.data) ? payload.data : [];
        page.value = payload.current_page ?? targetPage;
        lastPage.value = Math.max(1, payload.last_page ?? 1);
        perPage.value = payload.per_page ?? perPage.value;
        total.value = payload.total ?? rows.value.length;
    } catch (err) {
        if (requestId !== requestSequence) return;
        error.value = errorText(err); rows.value = []; total.value = 0;
    } finally { if (requestId === requestSequence) loading.value = false; }
}
function changePage(target: number): void {
    const next = Math.max(1, Math.min(target, lastPage.value));
    if (next === page.value || loading.value) return;
    page.value = next; void load(next);
}
onMounted(() => void load(1));
</script>

<template>
    <Head title="Stock Movements" />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <main class="inventory-page">
            <header class="page-header">
                <div class="page-heading">
                    <div class="breadcrumb"><a href="/inventory">Inventory</a><Icon name="chevron-right" :size="13" /><strong>Stock Movements</strong></div>
                    <div class="title-row"><div class="page-title-icon"><Icon name="history" :size="22" /></div>
                        <div class="title-copy"><h1>Stock Movements</h1><p>Review inventory receipts, issues, adjustments and transfers.</p></div>
                    </div>
                </div>
                <div class="header-actions"><button class="button" type="button" :disabled="loading" @click="load(page)"><Icon name="refresh-cw" :size="15" :class="{ spin: loading }" /> Refresh</button></div>
            </header>

            <section class="stats-grid" aria-label="Movement summary">
                <article class="stat-card"><div class="stat-top"><span class="stat-icon blue"><Icon name="history" :size="19" /></span><span class="stat-caption">All pages</span></div><div class="stat-value">{{ qty(total) }}</div><div class="stat-label">Movement records</div><p class="stat-description">Recorded inventory ledger entries.</p></article>
                <article class="stat-card"><div class="stat-top"><span class="stat-icon green"><Icon name="arrow-down-left" :size="19" /></span><span class="stat-caption">Current page</span></div><div class="stat-value">{{ qty(rows.filter(row => movementType(row) === 'receive').length) }}</div><div class="stat-label">Receipts</div><p class="stat-description">Stock entries received on this page.</p></article>
                <article class="stat-card"><div class="stat-top"><span class="stat-icon amber"><Icon name="arrow-up-right" :size="19" /></span><span class="stat-caption">Current page</span></div><div class="stat-value">{{ qty(rows.filter(row => movementType(row) === 'issue').length) }}</div><div class="stat-label">Issues</div><p class="stat-description">Stock issues on this page.</p></article>
                <article class="stat-card"><div class="stat-top"><span class="stat-icon violet"><Icon name="arrow-left-right" :size="19" /></span><span class="stat-caption">Current page</span></div><div class="stat-value">{{ qty(rows.filter(row => ['transfer', 'adjust'].includes(movementType(row))).length) }}</div><div class="stat-label">Other movements</div><p class="stat-description">Transfers and adjustments on this page.</p></article>
            </section>

            <section class="card">
                <div class="section-heading"><div class="section-icon"><Icon name="history" :size="19" /></div><div class="section-heading-copy"><h2>Movement history</h2><p>Recorded stock transactions and their references.</p></div><span class="record-badge">{{ qty(total) }} records</span></div>
                <div class="table-toolbar">
                    <label class="search-box"><Icon name="search" :size="16" /><input v-model="search" type="search" placeholder="Search product, reference or location..." aria-label="Search stock movements" /><button v-if="search" class="clear-search" type="button" aria-label="Clear search" @click="search = ''"><Icon name="x" :size="14" /></button></label>
                    <div class="toolbar-right"><label for="movement-type" class="rows-label">Type</label><div class="select-wrap wide"><select id="movement-type" v-model="typeFilter"><option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select><Icon name="chevron-down" :size="14" /></div><label for="movement-rows" class="rows-label rows-label-gap">Rows</label><div class="select-wrap"><select id="movement-rows" v-model.number="perPage" @change="page = 1; load(1)"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select><Icon name="chevron-down" :size="14" /></div></div>
                </div>
                <div v-if="error" class="state-panel" role="alert"><div class="state-icon error"><Icon name="alert-circle" :size="22" /></div><h3>Unable to load stock movements</h3><p>{{ error }}</p><button class="button" type="button" @click="load(page)">Try again</button></div>
                <div v-else-if="loading && rows.length === 0" class="state-panel"><div class="state-icon"><Icon name="loader-2" :size="22" class="spin" /></div><h3>Loading movement history</h3><p>Retrieving stock ledger entries...</p></div>
                <div v-else-if="filteredRows.length === 0" class="state-panel compact-state"><div class="state-icon"><Icon :name="search || typeFilter !== 'all' ? 'search' : 'history'" :size="22" /></div><h3>{{ search || typeFilter !== 'all' ? 'No matching movements' : 'No stock movements yet' }}</h3><p>{{ search || typeFilter !== 'all' ? 'Try changing the search term or movement type.' : 'Stock receipt, issue, adjustment and transfer records will appear here when transactions are recorded.' }}</p><button v-if="search || typeFilter !== 'all'" class="text-button" type="button" @click="search = ''; typeFilter = 'all'">Clear filters</button></div>
                <div v-else class="table-container" :class="{ 'is-refreshing': loading }">
                    <table><thead><tr><th>Movement</th><th>Product</th><th>Location / route</th><th class="numeric">Quantity</th><th>Reference</th><th>Date</th></tr></thead>
                        <tbody><tr v-for="row in filteredRows" :key="row.id">
                            <td><span class="movement-kind" :class="`kind-${movementType(row)}`"><Icon :name="typeIcon(row)" :size="14" />{{ typeLabel(row) }}</span></td>
                            <td><div class="product-cell"><span class="product-avatar"><Icon name="package" :size="17" /></span><span class="product-details"><strong>{{ row.product?.name ?? 'Unknown product' }}</strong><small>{{ row.variant?.sku || row.product?.sku || 'No SKU' }}</small></span></div></td>
                            <td><span class="location-cell"><strong>{{ row.from_location?.name && row.to_location?.name ? `${row.from_location.name} → ${row.to_location.name}` : row.location?.name ?? '—' }}</strong><small>{{ row.location?.code || '' }}</small></span></td>
                            <td class="numeric quantity">{{ qty(row.quantity ?? row.quantity_change ?? row.quantity_delta) }}</td>
                            <td><span class="reference-cell"><strong>{{ row.reference || (row.reference_type && row.reference_id ? `${row.reference_type} #${row.reference_id}` : '—') }}</strong><small>{{ row.notes || row.reason || '' }}</small></span></td>
                            <td class="date-cell">{{ dateLabel(row.created_at) }}</td>
                        </tr></tbody>
                    </table>
                </div>
                <footer v-if="total > 0" class="table-footer"><p>Showing <strong>{{ (page - 1) * perPage + 1 }}</strong>–<strong>{{ Math.min(page * perPage, total) }}</strong> of <strong>{{ qty(total) }}</strong> records <span v-if="search || typeFilter !== 'all'">· {{ filteredRows.length }} visible on this page</span></p><nav class="pagination" aria-label="Movement pagination"><button class="pagination-button" type="button" :disabled="page <= 1 || loading" aria-label="Previous page" @click="changePage(page - 1)"><Icon name="chevron-left" :size="16" /></button><span>Page <strong>{{ page }}</strong> of <strong>{{ lastPage }}</strong></span><button class="pagination-button" type="button" :disabled="page >= lastPage || loading" aria-label="Next page" @click="changePage(page + 1)"><Icon name="chevron-right" :size="16" /></button></nav></footer>
            </section>
            <p class="page-footnote"><Icon name="info-circle" :size="14" />Movement history is an audit trail. Entries reflect transactions recorded by the inventory service.</p>
        </main>
    </ErpLayout>
</template>

<style scoped>
.inventory-page{--bg:var(--background,#f6f8fb);--surface:var(--surface,#fff);--border:var(--border,#e5eaf2);--text:var(--text-primary,#111827);--muted:var(--text-secondary,#667085);--subtle:var(--text-tertiary,#98a2b3);--primary:var(--primary,#2563eb);min-width:0;min-height:100%;padding:0;color:var(--text);background:var(--bg);font-size:12px}.inventory-page *,.inventory-page *:before,.inventory-page *:after{box-sizing:border-box}
.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;max-width:1480px;margin:0 auto 20px}.page-heading,.title-copy,.section-heading-copy{min-width:0}.breadcrumb{display:flex;align-items:center;gap:8px;margin-bottom:12px;color:var(--subtle);font-size:12px}.breadcrumb a{color:inherit;text-decoration:none}.breadcrumb a:hover{color:var(--primary)}.breadcrumb strong{color:var(--muted);font-weight:600}.title-row{display:flex;align-items:flex-start;gap:14px}.page-title-icon,.section-icon,.stat-icon{display:grid;flex:0 0 auto;place-items:center}.page-title-icon{width:48px;height:48px;margin-top:1px;border:1px solid #dbeafe;border-radius:14px;background:#eff6ff;color:#2563eb}h1{margin:0;color:var(--text);font-size:28px;line-height:1.2;font-weight:750;letter-spacing:-.035em}.page-header p{margin:7px 0 0;color:var(--muted);font-size:14px;line-height:1.5}.header-actions{padding-top:25px}.button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;gap:8px;padding:0 15px;border:1px solid var(--border);border-radius:11px;background:var(--surface);color:var(--muted);font:inherit;font-size:12px;font-weight:650;cursor:pointer}.button:hover:not(:disabled){border-color:var(--primary);color:var(--primary)}.button:disabled{opacity:.55;cursor:not-allowed}
.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;max-width:1480px;margin:0 auto 20px}.stat-card,.card{border:1px solid var(--border);border-radius:15px;background:var(--surface);box-shadow:0 2px 10px rgb(16 24 40 / 2.5%)}.stat-card{min-width:0;padding:18px 20px;animation:enter .28s ease both}.stat-top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}.stat-icon{width:40px;height:40px;border:1px solid transparent;border-radius:12px}.stat-icon.blue{border-color:#dbeafe;background:#eff6ff;color:#2563eb}.stat-icon.green{border-color:#d1fae5;background:#ecfdf5;color:#059669}.stat-icon.amber{border-color:#fed7aa;background:#fff7ed;color:#d97706}.stat-icon.violet{border-color:#ede9fe;background:#f5f3ff;color:#7c3aed}.stat-caption{color:var(--subtle);font-size:11px;font-weight:600}.stat-value{overflow-wrap:anywhere;color:var(--text);font-size:26px;line-height:1.2;font-weight:750;letter-spacing:-.035em;font-variant-numeric:tabular-nums}.stat-label{margin-top:6px;color:var(--text);font-size:13px;font-weight:650}.stat-description{margin:7px 0 0;color:var(--subtle);font-size:11px;line-height:1.5}.card{max-width:1480px;margin:0 auto;overflow:hidden}.section-heading{display:flex;align-items:flex-start;gap:12px;padding:22px 22px 0}.section-icon{width:40px;height:40px;border:1px solid #dbeafe;border-radius:12px;background:#eff6ff;color:#2563eb}.section-heading h2{margin:2px 0 4px;font-size:15px;font-weight:700}.section-heading p{margin:0;color:var(--muted);font-size:13px;line-height:1.5}.record-badge{flex:0 0 auto;margin-left:auto;padding:8px 10px;border:1px solid var(--border);border-radius:9px;background:#fafbfc;color:var(--muted);font-size:11px;font-weight:650}
.table-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:20px 22px}.search-box{display:flex;width:min(100%,650px);min-width:0;height:42px;align-items:center;gap:10px;padding:0 13px;border:1px solid var(--border);border-radius:11px;background:var(--surface);color:var(--subtle)}.search-box:focus-within{border-color:var(--primary);box-shadow:0 0 0 3px rgb(37 99 235 / 8%)}.search-box input{width:100%;min-width:0;height:100%;padding:0;border:0;outline:0;background:transparent;color:var(--text);font:inherit;font-size:12px}.search-box input::placeholder{color:var(--subtle)}.clear-search{display:grid;width:26px;height:26px;place-items:center;border:0;border-radius:6px;background:transparent;color:var(--subtle);cursor:pointer}.toolbar-right{display:flex;align-items:center;gap:9px}.rows-label{color:var(--muted);font-size:12px;font-weight:600}.rows-label-gap{margin-left:3px}.select-wrap{position:relative;display:flex;height:40px;min-width:74px;align-items:center}.select-wrap.wide{min-width:120px}.select-wrap select{width:100%;height:100%;padding:0 30px 0 12px;appearance:none;border:1px solid var(--border);border-radius:10px;background:var(--surface);color:var(--text);font:inherit;cursor:pointer}.select-wrap svg{position:absolute;right:10px;pointer-events:none;color:var(--subtle)}
.table-container{width:100%;overflow-x:auto;border-top:1px solid var(--border);transition:opacity .18s}.table-container.is-refreshing{opacity:.65}table{width:100%;border-collapse:separate;border-spacing:0;text-align:left;white-space:nowrap}thead{background:#f8fafc}th{padding:13px 16px;border-bottom:1px solid var(--border);color:var(--muted);font-size:10px;font-weight:700;letter-spacing:.025em}td{padding:14px 16px;border-bottom:1px solid #eef0f4;color:var(--muted);font-size:12px;vertical-align:middle}tbody tr:hover{background:#fafcff}tbody tr:last-child td{border-bottom:0}th:first-child,td:first-child{padding-left:22px}th:last-child,td:last-child{padding-right:22px}.numeric{text-align:right;font-variant-numeric:tabular-nums}.quantity{color:var(--text);font-weight:700}.product-cell{display:flex;min-width:170px;align-items:center;gap:10px}.product-avatar{display:grid;width:36px;height:36px;flex:0 0 auto;place-items:center;border:1px solid var(--border);border-radius:10px;background:#f8fafc;color:#64748b}.product-details,.location-cell,.reference-cell{display:flex;min-width:0;flex-direction:column;gap:4px}.product-details strong,.location-cell strong,.reference-cell strong{max-width:240px;overflow:hidden;color:var(--text);font-size:12px;font-weight:650;text-overflow:ellipsis}.product-details small,.location-cell small,.reference-cell small{max-width:250px;overflow:hidden;color:var(--subtle);font-size:10px;text-overflow:ellipsis}.movement-kind{display:inline-flex;align-items:center;gap:7px;padding:6px 9px;border:1px solid var(--border);border-radius:8px;background:#f8fafc;color:#475569;font-size:10px;font-weight:650}.kind-receive{border-color:#d1fae5;background:#ecfdf5;color:#047857}.kind-issue{border-color:#fed7aa;background:#fff7ed;color:#c2410c}.kind-transfer{border-color:#dbeafe;background:#eff6ff;color:#1d4ed8}.kind-adjust{border-color:#ede9fe;background:#f5f3ff;color:#6d28d9}.date-cell{color:var(--muted);font-size:11px}.state-panel{display:flex;min-height:220px;flex-direction:column;align-items:center;justify-content:center;padding:28px 22px;text-align:center}.compact-state{min-height:210px}.state-panel h3{margin:12px 0 5px;color:var(--text);font-size:14px;font-weight:700}.state-panel p{max-width:460px;margin:0 0 12px;color:var(--muted);font-size:12px;line-height:1.6}.state-icon{display:grid;width:48px;height:48px;place-items:center;border:1px solid var(--border);border-radius:14px;background:#f8fafc;color:#64748b}.state-icon.error{border-color:#fecaca;background:#fef2f2;color:#dc2626}.text-button{padding:5px;border:0;background:transparent;color:var(--primary);font:inherit;font-size:12px;font-weight:650;cursor:pointer}.table-footer{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:15px 22px;border-top:1px solid var(--border)}.table-footer p{margin:0;color:var(--muted);font-size:11px;line-height:1.6}.table-footer strong,.pagination strong{color:var(--text)}.pagination{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:11px;white-space:nowrap}.pagination-button{display:grid;width:34px;height:34px;place-items:center;border:1px solid var(--border);border-radius:9px;background:var(--surface);color:var(--muted);cursor:pointer}.pagination-button:disabled{opacity:.4;cursor:not-allowed}.page-footnote{display:flex;align-items:flex-start;gap:7px;margin:14px 2px 0;color:var(--subtle);font-size:11px;line-height:1.6}.page-footnote svg{flex:0 0 auto;margin-top:1px}.spin{animation:spin .9s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@keyframes enter{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.page-header{flex-direction:column;gap:12px}.header-actions{padding-top:0}h1{font-size:24px}.page-header p{font-size:12px}.stats-grid{gap:11px}.stat-card{padding:15px}.section-heading{padding:16px 15px 0}.table-toolbar{align-items:stretch;flex-direction:column;padding:15px}.search-box{width:100%}.toolbar-right{flex-wrap:wrap;justify-content:flex-end}.table-footer{align-items:flex-start;flex-direction:column;padding:14px 15px}.pagination{align-self:flex-end}th:first-child,td:first-child{padding-left:15px}th:last-child,td:last-child{padding-right:15px}}@media(max-width:430px){.stats-grid{grid-template-columns:minmax(0,1fr)}.record-badge{max-width:90px;overflow:hidden;text-overflow:ellipsis}}@media(prefers-reduced-motion:reduce){.inventory-page *{animation-duration:.01ms!important;transition-duration:.01ms!important}}

/* Compact typography refinement aligned with Settings > Activity Logs. */
.inventory-page{font-size:12px}
.breadcrumb{font-size:10px}
.title-copy h1{font-size:20px}
.title-copy p{font-size:11px}
.stat-value{font-size:21px}
.stat-label{font-size:11px}
.stat-description{font-size:10px}
.section-heading-copy h2{font-size:13px}
.section-heading-copy p{font-size:10px}
.button,.text-button{font-size:11px}
.table-toolbar input,.table-toolbar select,.rows-label{font-size:10px}
table{font-size:11px}
table th{font-size:9px}
.product-details strong,.location-cell strong{font-size:11px}
.product-details small,.location-cell small{font-size:9px}
.table-footer,.pagination{font-size:10px}
.state-panel h3{font-size:12px}
.state-panel p{font-size:10px}
@media(max-width:720px){.title-copy h1{font-size:18px}.stat-value{font-size:19px}}

</style>
