<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface Props {
    userName?: string;
    userEmail?: string;
}
interface Reservation {
    id: number;
    product_id?: number | null;
    product_variant_id?: number | null;
    location_id: number;
    quantity: string | number;
    purpose: string;
    purpose_label?: string | null;
    status: 'active' | 'released' | 'cancelled' | 'fulfilled' | 'expired';
    reference_type?: string | null;
    reference_id?: string | null;
    reason?: string | null;
    expires_at?: string | null;
    resolved_at?: string | null;
    created_at?: string | null;
    product?: { id: number; name: string; sku?: string | null } | null;
    variant?: {
        id: number;
        product_id?: number;
        name: string;
        sku?: string | null;
    } | null;
    location?: { id: number; name: string; code?: string | null } | null;
    creator?: { name?: string | null } | null;
    resolver?: { name?: string | null } | null;
}
interface PageResponse {
    data?: Reservation[];
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    message?: string;
    errors?: Record<string, string[]>;
}
const props = withDefaults(defineProps<Props>(), {
    userName: '',
    userEmail: '',
});
const rows = ref<Reservation[]>([]);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const statusFilter = ref('all');
const purposeFilter = ref('all');
interface ReservationPurpose {
    id: number;
    key: string;
    label: string;
    is_active: boolean;
    sort_order: number;
}
const purposes = ref<ReservationPurpose[]>([]);
const allPurposes = ref<ReservationPurpose[]>([]);
const managePurposesOpen = ref(false);
const purposeLabelDraft = ref('');
const purposeKeyDraft = ref('');
const purposeSaving = ref(false);
const purposeError = ref('');
const editingPurposeId = ref<number | null>(null);
const purposeDeleteTarget = ref<ReservationPurpose | null>(null);
const purposeDeleting = ref(false);
const activePurposes = computed(() =>
    purposes.value.filter((purpose) => purpose.is_active),
);
const search = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const busyId = ref<number | null>(null);
const confirmationReservation = ref<Reservation | null>(null);
const confirmationAction = ref<'cancel' | 'release' | 'fulfill' | null>(null);
const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    return rows.value.filter(
        (r) =>
            !q ||
            [
                r.product?.name,
                r.product?.sku,
                r.variant?.name,
                r.variant?.sku,
                r.location?.name,
                r.reference_type,
                r.reference_id,
                r.reason,
                r.creator?.name,
            ].some((v) =>
                String(v ?? '')
                    .toLowerCase()
                    .includes(q),
            ),
    );
});
function quantity(v: string | number): string {
    const n = Number(v);
    return new Intl.NumberFormat('en-BD', { maximumFractionDigits: 4 }).format(
        Number.isFinite(n) ? n : 0,
    );
}
function date(v?: string | null): string {
    if (!v) return '—';
    const d = new Date(v);
    return Number.isNaN(d.getTime())
        ? v
        : new Intl.DateTimeFormat('en-BD', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(d);
}

function statusLabel(s: Reservation['status']): string {
    return (
        (
            {
                active: 'Active',
                released: 'Stock Freed',
                cancelled: 'Cancelled',
                fulfilled: 'Order Out (Fulfillment)',
                expired: 'Expired',
            } as Record<string, string>
        )[s] ?? s
    );
}

function productLabel(r: Reservation): string {
    return r.variant
        ? `${r.product?.name ?? `Product #${r.variant.product_id}`} · ${r.variant.name}`
        : (r.product?.name ??
              (r.product_id
                  ? `Product #${r.product_id}`
                  : `Variant #${r.product_variant_id}`));
}
async function loadAllPurposes(): Promise<void> {
    try {
        const response = await fetch(
            '/api/inventory/reservation-purposes/manage',
            {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        const payload = await response.json();
        if (response.ok)
            allPurposes.value = Array.isArray(payload.data) ? payload.data : [];
    } catch {
        /* Purpose manager remains usable if this request fails. */
    }
}
async function loadPurposes(): Promise<void> {
    try {
        const response = await fetch('/api/inventory/reservation-purposes', {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();
        if (response.ok)
            purposes.value = Array.isArray(payload.data) ? payload.data : [];
    } catch {
        /* The reservation table can still be read if purposes fail to load. */
    }
}
function makePurposeKey(): void {
    purposeKeyDraft.value = purposeLabelDraft.value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 24);
}
function handlePurposeLabelInput(): void {
    if (editingPurposeId.value === null) makePurposeKey();
}
function togglePurposeManager(): void {
    managePurposesOpen.value = !managePurposesOpen.value;
    if (!managePurposesOpen.value) resetPurposeForm();
}
function resetPurposeForm(): void {
    editingPurposeId.value = null;
    purposeLabelDraft.value = '';
    purposeKeyDraft.value = '';
    purposeError.value = '';
}
function editPurpose(purpose: ReservationPurpose): void {
    editingPurposeId.value = purpose.id;
    purposeLabelDraft.value = purpose.label;
    purposeKeyDraft.value = purpose.key;
    purposeError.value = '';
    managePurposesOpen.value = true;
}
async function createPurpose(): Promise<void> {
    if (purposeSaving.value) return;
    purposeSaving.value = true;
    purposeError.value = '';
    try {
        const token = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((v) => v.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=') ?? '',
        );
        const editing = editingPurposeId.value !== null;
        const response = await fetch(
            editing
                ? `/api/inventory/reservation-purposes/${editingPurposeId.value}`
                : '/api/inventory/reservation-purposes',
            {
                method: editing ? 'PUT' : 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    key: purposeKeyDraft.value,
                    label: purposeLabelDraft.value.trim(),
                }),
            },
        );
        const payload = await response.json();
        if (!response.ok) {
            const first = payload.errors
                ? Object.values(payload.errors).flat()[0]
                : null;
            throw new Error(
                String(first || payload.message || 'Unable to add purpose.'),
            );
        }
        resetPurposeForm();
        await loadPurposes();
        await loadAllPurposes();
        notice.value =
            payload.message ||
            (editing
                ? 'Reservation purpose updated.'
                : 'Reservation purpose added.');
    } catch (e) {
        purposeError.value =
            e instanceof Error ? e.message : 'Unable to add purpose.';
    } finally {
        purposeSaving.value = false;
    }
}
async function deletePurpose(): Promise<void> {
    const target = purposeDeleteTarget.value;
    if (!target || purposeDeleting.value) return;
    purposeDeleting.value = true;
    purposeError.value = '';
    try {
        const token = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((v) => v.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=') ?? '',
        );
        const response = await fetch(
            `/api/inventory/reservation-purposes/${target.id}`,
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': token,
                },
            },
        );
        const payload = await response.json();
        if (!response.ok) {
            const first = payload.errors
                ? Object.values(payload.errors).flat()[0]
                : null;
            throw new Error(
                String(first || payload.message || 'Unable to delete purpose.'),
            );
        }
        notice.value = payload.message || 'Reservation purpose removed.';
        purposeDeleteTarget.value = null;
        await loadPurposes();
        await loadAllPurposes();
    } catch (e) {
        purposeError.value =
            e instanceof Error ? e.message : 'Unable to delete purpose.';
    } finally {
        purposeDeleting.value = false;
    }
}
async function load(target = page.value): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const p = new URLSearchParams({ page: String(target), per_page: '25' });
        if (statusFilter.value !== 'all') p.set('status', statusFilter.value);
        if (purposeFilter.value !== 'all')
            p.set('purpose', purposeFilter.value);
        const response = await fetch(`/api/inventory/reservations?${p}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = (await response.json()) as PageResponse;
        if (!response.ok)
            throw new Error(
                payload.message ||
                    `Unable to load reservations (HTTP ${response.status}).`,
            );
        rows.value = Array.isArray(payload.data) ? payload.data : [];
        page.value = payload.current_page ?? target;
        lastPage.value = Math.max(1, payload.last_page ?? 1);
        total.value = payload.total ?? rows.value.length;
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Unable to load reservations.';
        rows.value = [];
        total.value = 0;
    } finally {
        loading.value = false;
    }
}
function resolve(
    r: Reservation,
    action: 'cancel' | 'release' | 'fulfill',
): void {
    confirmationReservation.value = r;
    confirmationAction.value = action;
}
function closeConfirmation(): void {
    if (busyId.value !== null) return;
    confirmationReservation.value = null;
    confirmationAction.value = null;
}
async function confirmResolution(): Promise<void> {
    const r = confirmationReservation.value;
    const action = confirmationAction.value;
    if (!r || !action || busyId.value !== null) return;
    const label =
        action === 'fulfill'
            ? 'complete Order Out'
            : action === 'cancel'
              ? 'cancel'
              : 'free reserved stock';
    busyId.value = r.id;
    error.value = '';
    notice.value = '';
    try {
        const response = await fetch(
            `/api/inventory/reservations/${r.id}/${action}`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? '',
                },
                body: '{}',
            },
        );
        const payload = (await response.json()) as PageResponse;
        if (!response.ok) {
            const first = payload.errors
                ? Object.values(payload.errors).flat()[0]
                : null;
            throw new Error(
                String(
                    first ||
                        payload.message ||
                        `Unable to ${label} reservation.`,
                ),
            );
        }
        notice.value = payload.message || `Reservation ${label}d successfully.`;
        confirmationReservation.value = null;
        confirmationAction.value = null;
        await load(page.value);
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : `Unable to ${label} reservation.`;
        confirmationReservation.value = null;
        confirmationAction.value = null;
    } finally {
        busyId.value = null;
    }
}
function changeFilter(): void {
    page.value = 1;
    void load(1);
}
onMounted(() => {
    void load(1);
    void loadPurposes();
    void loadAllPurposes();
});
</script>

<template>
    <Head title="Inventory Reservations" />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <main class="reservations-page">
            <header class="reservation-page-header">
                <div>
                    <div class="reservation-breadcrumb">
                        <a href="/inventory">Inventory</a
                        ><Icon name="chevron-right" :size="13" /><strong
                            >Reservations</strong
                        >
                    </div>
                    <div class="reservation-title-row">
                        <span class="reservation-page-icon"
                            ><Icon name="calendar-check" :size="23"
                        /></span>
                        <div>
                            <h1>Inventory Reservations</h1>
                            <p>
                                Track reserved stock, fulfill customer orders,
                                or release stock back to availability.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="reservation-page-actions">
                    <a
                        class="reservation-btn reservation-btn-primary"
                        href="/inventory"
                        ><Icon name="boxes" :size="15" /> Back to inventory</a
                    ><button
                        class="reservation-btn"
                        type="button"
                        :disabled="loading"
                        @click="load(page)"
                    >
                        <Icon
                            name="refresh-cw"
                            :size="15"
                            :class="{ spin: loading }"
                        />
                        Refresh
                    </button>
                </div>
            </header>
            <div v-if="notice" class="reservation-notice" role="status">
                <Icon name="check-circle" :size="17" />{{ notice
                }}<button
                    type="button"
                    @click="notice = ''"
                    aria-label="Dismiss"
                >
                    <Icon name="x" :size="15" />
                </button>
            </div>
            <section class="reservation-stats">
                <article>
                    <span class="stat-icon active-icon"
                        ><Icon name="clipboard-check" :size="18"
                    /></span>
                    <div>
                        <strong>{{
                            rows.filter((r) => r.status === 'active').length
                        }}</strong
                        ><span>Active on this page</span>
                    </div>
                </article>
                <article>
                    <span class="stat-icon fulfilled-icon"
                        ><Icon name="circle-check" :size="18"
                    /></span>
                    <div>
                        <strong>{{
                            rows.filter((r) => r.status === 'fulfilled').length
                        }}</strong
                        ><span>Order Out on this page</span>
                    </div>
                </article>
                <article>
                    <span class="stat-icon total-icon"
                        ><Icon name="list-checks" :size="18"
                    /></span>
                    <div>
                        <strong>{{ quantity(total) }}</strong
                        ><span>Total matching reservations</span>
                    </div>
                </article>
            </section>
            <section class="purpose-section">
                <div class="purpose-section-heading">
                    <div>
                        <h2>Reservation purposes</h2>
                        <p>
                            Keep reservation reasons organized. Changes apply to
                            new reservations.
                        </p>
                    </div>
                    <button
                        class="reservation-btn reservation-btn-primary purpose-toggle"
                        type="button"
                        @click="togglePurposeManager"
                    >
                        <Icon
                            :name="managePurposesOpen ? 'x' : 'plus'"
                            :size="14"
                        />{{ managePurposesOpen ? 'Close' : 'Add purpose' }}
                    </button>
                </div>
                <div v-if="managePurposesOpen" class="purpose-editor">
                    <form
                        class="purpose-manager-form"
                        @submit.prevent="createPurpose"
                    >
                        <label
                            >Purpose label<input
                                v-model="purposeLabelDraft"
                                required
                                maxlength="80"
                                placeholder="e.g. Quality inspection"
                                @input="handlePurposeLabelInput"
                        /></label>
                        <label
                            >Purpose key<input
                                v-model="purposeKeyDraft"
                                required
                                maxlength="24"
                                pattern="[a-z][a-z0-9_]*"
                                placeholder="quality_inspection"
                        /></label>
                        <div class="purpose-form-actions">
                            <button
                                class="reservation-btn reservation-btn-primary"
                                type="submit"
                                :disabled="purposeSaving"
                            >
                                <Icon
                                    :name="
                                        editingPurposeId !== null
                                            ? 'check'
                                            : 'plus'
                                    "
                                    :size="14"
                                />{{
                                    purposeSaving
                                        ? 'Saving…'
                                        : editingPurposeId !== null
                                          ? 'Save changes'
                                          : 'Add purpose'
                                }}
                            </button>
                            <button
                                v-if="editingPurposeId !== null"
                                class="reservation-btn"
                                type="button"
                                @click="resetPurposeForm"
                            >
                                Cancel edit
                            </button>
                        </div>
                    </form>
                    <p
                        v-if="purposeError"
                        class="purpose-manager-error"
                        role="alert"
                    >
                        {{ purposeError }}
                    </p>
                </div>
                <div class="purpose-manager-list">
                    <div
                        v-for="purpose in allPurposes"
                        :key="purpose.id"
                        class="purpose-chip"
                    >
                        <span class="purpose-chip-copy"
                            ><strong>{{ purpose.label }}</strong
                            ><small>{{ purpose.key }}</small></span
                        >

                        <button
                            class="purpose-icon-action"
                            type="button"
                            :aria-label="`Edit ${purpose.label}`"
                            title="Edit purpose"
                            @click="editPurpose(purpose)"
                        >
                            <Icon name="edit" :size="13" />
                        </button>
                        <button
                            class="purpose-icon-action purpose-delete-action"
                            type="button"
                            :aria-label="`Delete ${purpose.label}`"
                            title="Delete purpose"
                            :disabled="
                                purposeDeleting || allPurposes.length <= 2
                            "
                            :aria-disabled="allPurposes.length <= 2"
                            @click="
                                purposeDeleteTarget = purpose;
                                purposeError = '';
                            "
                        >
                            <Icon name="trash-2" :size="13" />
                        </button>
                    </div>
                    <p v-if="allPurposes.length === 0" class="purpose-empty">
                        No purposes configured yet. Add your first purpose.
                    </p>
                </div>
            </section>
            <section class="reservation-table-card">
                <div class="reservation-table-heading">
                    <div>
                        <h2>Reservation records</h2>
                        <p>
                            Actions are available for active reservations only.
                        </p>
                    </div>
                    <div class="reservation-heading-actions">
                        <span class="reservation-count"
                            >{{ quantity(total) }} records</span
                        >
                        <button
                            class="reservation-btn"
                            type="button"
                            :disabled="loading"
                            @click="load(page)"
                        >
                            <Icon name="refresh-cw" :size="13" />Refresh
                        </button>
                    </div>
                </div>
                <div class="reservation-toolbar">
                    <label class="reservation-search"
                        ><Icon name="search" :size="16" /><input
                            v-model="search"
                            type="search"
                            placeholder="Search product, reference or location…"
                            aria-label="Search reservations"
                    /></label>
                    <div class="reservation-filter-group">
                        <label class="filter-label"
                            >Purpose<select
                                v-model="purposeFilter"
                                @change="changeFilter"
                            >
                                <option value="all">All purposes</option>
                                <option
                                    v-for="purpose in activePurposes"
                                    :key="purpose.key"
                                    :value="purpose.key"
                                >
                                    {{ purpose.label }}
                                </option>
                            </select></label
                        >
                        <label class="filter-label"
                            >Status<select
                                v-model="statusFilter"
                                @change="changeFilter"
                            >
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <option value="fulfilled">
                                    Order Out (Fulfillment)
                                </option>
                                <option value="released">Stock Freed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="expired">Expired</option>
                            </select></label
                        >
                    </div>
                </div>
                <div
                    v-if="error"
                    class="reservation-state reservation-state-error"
                    role="alert"
                >
                    <Icon name="alert-circle" :size="22" /><strong
                        >Could not complete request</strong
                    >
                    <p>{{ error }}</p>
                    <button
                        class="reservation-btn"
                        type="button"
                        @click="load(page)"
                    >
                        Try again
                    </button>
                </div>
                <div
                    v-else-if="loading && rows.length === 0"
                    class="reservation-state"
                >
                    <Icon name="loader-2" :size="24" class="spin" /><strong
                        >Loading reservations…</strong
                    >
                </div>
                <div
                    v-else-if="filteredRows.length === 0"
                    class="reservation-state"
                >
                    <Icon name="package-open" :size="25" /><strong
                        >No reservations found</strong
                    >
                    <p>
                        Change the filters or create a reservation from the
                        Inventory page.
                    </p>
                    <a
                        href="/inventory"
                        class="reservation-btn reservation-btn-primary"
                        >Open inventory</a
                    >
                </div>
                <div v-else class="reservation-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Reservation</th>
                                <th>Product / location</th>
                                <th>Purpose & reference</th>
                                <th class="number-cell">Quantity</th>
                                <th>Status</th>
                                <th>Expiry / created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in filteredRows" :key="r.id">
                                <td>
                                    <strong class="reservation-id"
                                        >#{{ r.id }}</strong
                                    ><small>{{
                                        r.creator?.name
                                            ? `By ${r.creator.name}`
                                            : 'Creator not recorded'
                                    }}</small>
                                </td>
                                <td>
                                    <strong>{{ productLabel(r) }}</strong
                                    ><small
                                        >{{
                                            r.variant?.sku ||
                                            r.product?.sku ||
                                            'No SKU'
                                        }}
                                        ·
                                        {{
                                            r.location?.name ||
                                            `Location #${r.location_id}`
                                        }}</small
                                    >
                                </td>
                                <td>
                                    <span class="purpose-pill">{{
                                        purposes.find(
                                            (p) => p.key === r.purpose,
                                        )?.label || r.purpose
                                    }}</span
                                    ><small>{{
                                        [r.reference_type, r.reference_id]
                                            .filter(Boolean)
                                            .join(' · ') ||
                                        r.reason ||
                                        'No reference'
                                    }}</small>
                                </td>
                                <td class="number-cell quantity-strong">
                                    {{ quantity(r.quantity) }}
                                </td>
                                <td>
                                    <span
                                        class="reservation-status"
                                        :class="`status-${r.status}`"
                                        ><i></i
                                        >{{ statusLabel(r.status) }}</span
                                    >
                                </td>
                                <td>
                                    <strong>{{
                                        r.expires_at
                                            ? date(r.expires_at)
                                            : 'No expiry'
                                    }}</strong
                                    ><small
                                        >Created {{ date(r.created_at) }}</small
                                    >
                                </td>
                                <td>
                                    <div
                                        v-if="r.status === 'active'"
                                        class="reservation-row-actions"
                                    >
                                        <button
                                            type="button"
                                            class="action-fulfill"
                                            :disabled="busyId === r.id"
                                            @click="resolve(r, 'fulfill')"
                                        >
                                            <Icon name="check" :size="13" />
                                            Order Out</button
                                        ><button
                                            type="button"
                                            class="action-release"
                                            :disabled="busyId === r.id"
                                            @click="resolve(r, 'release')"
                                        >
                                            Free Stock</button
                                        ><button
                                            type="button"
                                            class="action-cancel"
                                            :disabled="busyId === r.id"
                                            @click="resolve(r, 'cancel')"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                    <small v-else>{{
                                        r.resolver?.name
                                            ? `Resolved by ${r.resolver.name}`
                                            : r.resolved_at
                                              ? date(r.resolved_at)
                                              : '—'
                                    }}</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer v-if="total > 0" class="reservation-pagination">
                    <span
                        >Page <strong>{{ page }}</strong> of
                        <strong>{{ lastPage }}</strong> ·
                        {{ quantity(total) }} records</span
                    >
                    <div>
                        <button
                            type="button"
                            class="reservation-btn"
                            :disabled="page <= 1 || loading"
                            @click="load(page - 1)"
                        >
                            <Icon name="chevron-left" :size="15" />
                            Previous</button
                        ><button
                            type="button"
                            class="reservation-btn"
                            :disabled="page >= lastPage || loading"
                            @click="load(page + 1)"
                        >
                            Next <Icon name="chevron-right" :size="15" />
                        </button>
                    </div>
                </footer>
            </section>
            <p class="reservation-footnote">
                Order Out (Fulfillment) reduces on-hand stock and records a
                stock movement. Free Stock or Cancel releases the reservation
                hold; on-hand stock does not change.
            </p>

            <Teleport to="body">
                <div
                    v-if="confirmationReservation && confirmationAction"
                    class="reservation-confirm-backdrop"
                    @click.self="closeConfirmation"
                    @keydown.esc="closeConfirmation"
                >
                    <section
                        class="reservation-confirm-dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="reservation-confirm-title"
                        aria-describedby="reservation-confirm-description"
                    >
                        <div
                            class="reservation-confirm-icon"
                            :class="`confirm-${confirmationAction}`"
                        >
                            <Icon
                                :name="
                                    confirmationAction === 'fulfill'
                                        ? 'circle-check'
                                        : confirmationAction === 'release'
                                          ? 'repeat-once'
                                          : 'x'
                                "
                                :size="22"
                            />
                        </div>
                        <h2 id="reservation-confirm-title">
                            {{
                                confirmationAction === 'fulfill'
                                    ? 'Order Out (Fulfillment)?'
                                    : confirmationAction === 'release'
                                      ? 'Free reserved stock?'
                                      : 'Cancel reservation?'
                            }}
                        </h2>
                        <p id="reservation-confirm-description">
                            {{
                                confirmationAction === 'fulfill'
                                    ? `This will reduce on-hand stock by ${quantity(
                                          confirmationReservation.quantity,
                                      )} unit(s) of ${productLabel(
                                          confirmationReservation,
                                      )}, record an Order Out (Fulfillment) stock movement, and mark reservation #${
                                          confirmationReservation.id
                                      } as Fulfilled. Use this when the reserved goods are actually leaving stock.`
                                    : confirmationAction === 'release'
                                      ? `This will free ${quantity(
                                            confirmationReservation.quantity,
                                        )} reserved unit(s) of ${productLabel(
                                            confirmationReservation,
                                        )} so they are available again. Reservation #${
                                            confirmationReservation.id
                                        } will be marked Released. On-hand stock will not change.`
                                      : `This will permanently mark reservation #${
                                            confirmationReservation.id
                                        } as Cancelled and return its ${quantity(
                                            confirmationReservation.quantity,
                                        )} reserved unit(s) to available stock. No stock will be issued.`
                            }}
                        </p>
                        <div class="reservation-confirm-summary">
                            <span
                                >Reservation
                                <strong
                                    >#{{ confirmationReservation.id }}</strong
                                ></span
                            >
                            <span
                                >Quantity
                                <strong>{{
                                    quantity(confirmationReservation.quantity)
                                }}</strong></span
                            >
                            <span
                                >Product
                                <strong>{{
                                    productLabel(confirmationReservation)
                                }}</strong></span
                            >
                        </div>
                        <div class="reservation-confirm-actions">
                            <button
                                type="button"
                                class="reservation-btn"
                                :disabled="busyId !== null"
                                @click="closeConfirmation"
                            >
                                Keep reservation
                            </button>
                            <button
                                type="button"
                                class="reservation-btn reservation-confirm-submit"
                                :class="`submit-${confirmationAction}`"
                                :disabled="busyId !== null"
                                @click="confirmResolution"
                            >
                                <Icon
                                    v-if="busyId === confirmationReservation.id"
                                    name="loader-2"
                                    :size="15"
                                    class="spin"
                                />
                                {{
                                    busyId === confirmationReservation.id
                                        ? 'Processing…'
                                        : confirmationAction === 'fulfill'
                                          ? 'Confirm Order Out'
                                          : confirmationAction === 'release'
                                            ? 'Free Reserved Stock'
                                            : 'Confirm cancellation'
                                }}
                            </button>
                        </div>
                    </section>
                </div>
            </Teleport>
            <Teleport to="body">
                <div
                    v-if="purposeDeleteTarget"
                    class="reservation-confirm-backdrop"
                    @click.self="purposeDeleteTarget = null"
                >
                    <section
                        class="reservation-confirm-dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="purpose-delete-title"
                    >
                        <span class="confirm-icon confirm-icon-danger"
                            ><Icon name="trash-2" :size="22"
                        /></span>
                        <h2 id="purpose-delete-title">
                            Remove reservation purpose?
                        </h2>
                        <p>
                            <strong>{{ purposeDeleteTarget.label }}</strong> ({{
                                purposeDeleteTarget.key
                            }}) will be permanently removed from purpose
                            choices. Existing reservations will retain their
                            saved purpose label and history.
                        </p>
                        <p
                            v-if="allPurposes.length <= 2"
                            class="purpose-manager-error"
                            role="alert"
                        >
                            At least two purposes must remain. Add another
                            purpose before deleting one.
                        </p>
                        <p
                            v-if="purposeError"
                            class="purpose-manager-error"
                            role="alert"
                        >
                            {{ purposeError }}
                        </p>
                        <div class="reservation-confirm-actions">
                            <button
                                class="reservation-btn"
                                type="button"
                                :disabled="purposeDeleting"
                                @click="purposeDeleteTarget = null"
                            >
                                Keep purpose
                            </button>
                            <button
                                class="reservation-btn reservation-btn-danger"
                                type="button"
                                :disabled="
                                    purposeDeleting || allPurposes.length <= 2
                                "
                                @click="deletePurpose"
                            >
                                {{
                                    purposeDeleting
                                        ? 'Removing…'
                                        : 'Remove purpose'
                                }}
                            </button>
                        </div>
                    </section>
                </div>
            </Teleport>
        </main>
    </ErpLayout>
</template>

<style scoped>
/* Shared visual language: Inventory/Index.vue is the design source of truth. */
.reservations-page {
    --page-bg: var(--background, #f6f8fb);
    --surface-color: var(--surface, #fff);
    --border-color: var(--border, #e5e7eb);
    --text-primary-color: var(--text-primary, #111827);
    --text-secondary-color: var(--text-secondary, #667085);
    --text-tertiary-color: var(--text-tertiary, #98a2b3);
    --primary-color: var(--primary, #059669);
    min-width: 0;
    min-height: 100%;
    padding: 0;
    color: var(--text-primary-color);
    background: var(--page-bg);
    font-size: 12px;
}
.reservations-page *,
.reservations-page *::before,
.reservations-page *::after {
    box-sizing: border-box;
}
.reservation-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    max-width: 1480px;
    margin: 0 auto 18px;
}
.reservation-breadcrumb {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 9px;
    color: var(--text-tertiary-color);
    font-size: 10px;
}
.reservation-breadcrumb a {
    color: var(--text-secondary-color);
    text-decoration: none;
}
.reservation-breadcrumb a:hover {
    color: var(--primary-color);
}
.reservation-breadcrumb strong {
    color: var(--text-secondary-color);
    font-weight: 600;
}
.reservation-title-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.reservation-page-icon {
    display: grid;
    flex: 0 0 auto;
    width: 42px;
    height: 42px;
    margin-top: 2px;
    place-items: center;
    border: 1px solid rgb(5 150 105 / 12%);
    border-radius: 12px;
    background: rgb(5 150 105 / 8%);
    color: var(--primary-color);
}
.reservation-title-row h1 {
    margin: 0;
    color: var(--text-primary-color);
    font-size: 20px;
    line-height: 1.2;
    font-weight: 750;
    letter-spacing: -0.03em;
}
.reservation-title-row p {
    max-width: 680px;
    margin: 6px 0 0;
    color: var(--text-secondary-color);
    font-size: 11px;
    line-height: 1.5;
}
.reservation-page-actions {
    display: flex;
    align-items: center;
    gap: 9px;
    padding-top: 25px;
}
.reservation-btn {
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
    font-size: 11px;
    font-weight: 650;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    transition:
        border-color 0.2s ease,
        background 0.2s ease,
        color 0.2s ease,
        box-shadow 0.2s ease;
}
.reservation-btn:hover:not(:disabled) {
    border-color: var(--primary-color);
    color: var(--primary-color);
}
.reservation-btn:focus-visible,
.reservation-search:focus-within,
.filter-label select:focus-visible {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgb(5 150 105 / 9%);
}
.reservation-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.reservation-btn-primary {
    border-color: var(--primary-color);
    background: var(--primary-color);
    color: var(--primary-foreground, #fff);
}
.reservation-btn-primary:hover:not(:disabled) {
    filter: brightness(0.94);
    color: var(--primary-foreground, #fff);
}
.reservation-notice {
    display: flex;
    align-items: center;
    gap: 9px;
    max-width: 1480px;
    margin: 0 auto 16px;
    padding: 12px 14px;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    background: #f0fdf4;
    color: #166534;
    font-size: 11px;
}
.reservation-notice button {
    display: grid;
    margin-left: auto;
    place-items: center;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
}
.reservation-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    max-width: 1480px;
    margin: 0 auto 20px;
}
.reservation-stats article {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 12px;
    padding: 17px 18px;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    background: var(--surface-color);
    box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
}
.stat-icon {
    display: grid;
    flex: 0 0 auto;
    width: 38px;
    height: 38px;
    place-items: center;
    border: 1px solid transparent;
    border-radius: 11px;
}
.active-icon {
    border-color: rgb(217 119 6 / 12%);
    background: rgb(217 119 6 / 9%);
    color: #b7791f;
}
.fulfilled-icon {
    border-color: rgb(22 163 74 / 10%);
    background: rgb(22 163 74 / 8%);
    color: #16a34a;
}
.total-icon {
    border-color: rgb(5 150 105 / 10%);
    background: rgb(5 150 105 / 8%);
    color: var(--primary-color);
}
.reservation-stats article div {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 4px;
}
.reservation-stats article strong {
    color: var(--text-primary-color);
    font-size: 21px;
    line-height: 1.2;
    font-weight: 750;
    letter-spacing: -0.035em;
    font-variant-numeric: tabular-nums;
}
.reservation-stats article span:not(.stat-icon) {
    color: var(--text-secondary-color);
    font-size: 10px;
}
.reservation-table-card {
    max-width: 1480px;
    margin: 0 auto;
    overflow: hidden;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    background: var(--surface-color);
    box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
}
.reservation-table-heading {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 21px 21px 0;
}
.reservation-table-heading h2 {
    margin: 1px 0 3px;
    color: var(--text-primary-color);
    font-size: 13px;
    font-weight: 700;
}
.reservation-table-heading p {
    margin: 0;
    color: var(--text-secondary-color);
    font-size: 10px;
    line-height: 1.5;
}
.reservation-count {
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
.purpose-section {
    max-width: 1480px;
    margin: 0 auto 14px;
    padding: 13px 16px;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    background: var(--surface-color);
    box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
}
.purpose-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}
.purpose-section-heading h2 {
    margin: 0 0 3px;
    font-size: 12px;
    font-weight: 750;
}
.purpose-section-heading p {
    margin: 0;
    color: var(--text-secondary-color);
    font-size: 10px;
}
.purpose-toggle {
    min-height: 32px;
    padding: 0 10px;
}
.purpose-editor {
    padding: 10px;
    margin-bottom: 10px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--page-bg);
}
.purpose-manager-form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
    align-items: end;
    gap: 10px;
}
.purpose-manager-form label {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 5px;
    color: var(--text-secondary-color);
    font-size: 10px;
    font-weight: 650;
}
.purpose-manager-form input {
    width: 100%;
    height: 34px;
    padding: 0 10px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    outline: none;
    background: var(--surface-color);
    color: var(--text-primary-color);
    font: inherit;
}
.purpose-form-actions {
    display: flex;
    gap: 6px;
}
.purpose-manager-error {
    margin: 8px 0 0;
    color: #b91c1c;
    font-size: 10px;
}
.purpose-manager-list {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}
.purpose-chip {
    display: inline-flex;
    min-width: 0;
    max-width: 100%;
    align-items: center;
    gap: 7px;
    padding: 5px 6px 5px 9px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--page-bg);
}
.purpose-chip-copy {
    display: flex;
    min-width: 0;
    align-items: baseline;
    gap: 6px;
}
.purpose-chip-copy strong {
    overflow: hidden;
    color: var(--text-primary-color);
    font-size: 10px;
    font-weight: 650;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.purpose-chip-copy small {
    color: var(--text-tertiary-color);
    font-size: 9px;
    white-space: nowrap;
}
.purpose-icon-action {
    display: grid;
    width: 24px;
    height: 24px;
    flex: 0 0 auto;
    place-items: center;
    border: 1px solid transparent;
    border-radius: 6px;
    background: transparent;
    color: var(--text-secondary-color);
    cursor: pointer;
}
.purpose-icon-action:hover {
    border-color: var(--border-color);
    background: var(--surface-color);
    color: var(--primary-color);
}
.purpose-icon-action:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
.purpose-delete-action:hover {
    color: #b91c1c;
}
.purpose-chip-inactive {
    opacity: 0.72;
}
.purpose-inactive-label {
    color: #b45309;
    font-size: 9px;
}
.purpose-empty {
    margin: 0;
    color: var(--text-tertiary-color);
    font-size: 10px;
}
.reservation-heading-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
}
.reservation-heading-actions .reservation-btn {
    min-height: 30px;
}
.reservation-toolbar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 21px;
}
.reservation-search {
    display: flex;
    flex: 0 0 60%;
    width: 60%;
    min-width: 0;
    height: 39px;
    align-items: center;
    gap: 9px;
    padding: 0 11px;
    border: 1px solid var(--border-color);
    border-radius: 9px;
    background: var(--surface-color);
    color: var(--text-tertiary-color);
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease;
}
.reservation-search input {
    width: 100%;
    min-width: 0;
    height: 100%;
    padding: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text-primary-color);
    font: inherit;
    font-size: 10px;
}
.reservation-search input::placeholder {
    color: var(--text-tertiary-color);
}
.reservation-filter-group {
    display: flex;
    flex: 1 1 0;
    min-width: 0;
    justify-content: flex-end;
    gap: 12px;
}
.reservation-filter-group .filter-label {
    flex: 1 1 0;
    min-width: 0;
}
.reservation-filter-group .filter-label select {
    width: 100%;
    min-width: 0;
    max-width: none;
}
.filter-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary-color);
    font-size: 10px;
    font-weight: 600;
    white-space: nowrap;
}
.filter-label select {
    max-width: 170px;
    height: 35px;
    padding: 0 28px 0 10px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    outline: none;
    background: var(--surface-color);
    color: var(--text-primary-color);
    font: inherit;
    font-size: 10px;
    cursor: pointer;
}
.reservation-table-wrap {
    width: 100%;
    overflow-x: auto;
    border-top: 1px solid var(--border-color);
}
.reservation-table-wrap table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    text-align: left;
    white-space: nowrap;
    font-size: 11px;
}
.reservation-table-wrap thead {
    background: #f8fafc;
}
.reservation-table-wrap th {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-secondary-color);
    font-size: 9px;
    font-weight: 700;
}
.reservation-table-wrap td {
    padding: 13px 14px;
    border-bottom: 1px solid #f0f2f5;
    color: var(--text-secondary-color);
    font-size: 10px;
    vertical-align: middle;
}
.reservation-table-wrap tbody tr:last-child td {
    border-bottom: 0;
}
.reservation-table-wrap tbody tr:hover {
    background: rgb(5 150 105 / 2.5%);
}
.reservation-table-wrap td > strong,
.reservation-table-wrap td > small {
    display: block;
}
.reservation-table-wrap td > strong {
    max-width: 230px;
    overflow: hidden;
    color: var(--text-primary-color);
    font-size: 11px;
    font-weight: 650;
    text-overflow: ellipsis;
}
.reservation-table-wrap td > small {
    margin-top: 5px;
    color: var(--text-tertiary-color);
    font-size: 9px;
}
.reservation-id {
    color: var(--primary-color) !important;
}
.number-cell {
    text-align: right;
    font-variant-numeric: tabular-nums;
}
.quantity-strong {
    color: var(--text-primary-color) !important;
    font-weight: 750;
}
.purpose-pill {
    display: inline-flex;
    padding: 5px 7px;
    border-radius: 6px;
    background: #f2f4f7;
    color: #475467;
    font-size: 9px;
    font-weight: 650;
}
.reservation-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 8px;
    border: 1px solid transparent;
    border-radius: 6px;
    font-size: 9px;
    font-weight: 700;
}
.reservation-status i {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}
.status-active {
    border-color: #fed7aa;
    background: #fff7ed;
    color: #c2410c;
}
.status-fulfilled {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
}
.status-released {
    border-color: #dbeafe;
    background: #eff6ff;
    color: #1d4ed8;
}
.status-cancelled {
    border-color: #e5e7eb;
    background: #f9fafb;
    color: #667085;
}
.status-expired {
    border-color: #fecaca;
    background: #fef2f2;
    color: #b91c1c;
}
.reservation-row-actions {
    display: flex;
    align-items: center;
    gap: 5px;
}
.reservation-row-actions button {
    display: inline-flex;
    min-height: 29px;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 0 8px;
    border: 1px solid var(--border-color);
    border-radius: 7px;
    background: var(--surface-color);
    color: var(--text-secondary-color);
    font: inherit;
    font-size: 10px;
    font-weight: 650;
    cursor: pointer;
    transition:
        background 0.18s ease,
        border-color 0.18s ease,
        color 0.18s ease;
}
.reservation-row-actions button:hover:not(:disabled) {
    border-color: var(--primary-color);
    color: var(--primary-color);
}
.reservation-row-actions button:disabled {
    opacity: 0.5;
    cursor: wait;
}
.reservation-row-actions .action-fulfill {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
}
.reservation-row-actions .action-release {
    color: #1d4ed8;
}
.reservation-row-actions .action-cancel {
    color: #b91c1c;
}
.reservation-state {
    display: flex;
    min-height: 220px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 24px;
    color: var(--text-secondary-color);
    text-align: center;
}
.reservation-state strong {
    color: var(--text-primary-color);
    font-size: 12px;
}
.reservation-state p {
    max-width: 400px;
    margin: 0;
    color: var(--text-secondary-color);
    font-size: 10px;
    line-height: 1.6;
}
.reservation-state-error {
    color: #b91c1c;
}
.reservation-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 20px;
    border-top: 1px solid var(--border-color);
    color: var(--text-secondary-color);
    font-size: 10px;
}
.reservation-pagination strong {
    color: var(--text-primary-color);
}
.reservation-pagination > div {
    display: flex;
    gap: 7px;
}
.reservation-footnote {
    display: flex;
    max-width: 1480px;
    align-items: flex-start;
    gap: 7px;
    margin: 14px auto 0;
    color: var(--text-tertiary-color);
    font-size: 10px;
    line-height: 1.6;
}
.reservation-footnote svg {
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
@media (max-width: 900px) {
    .reservation-page-header {
        flex-direction: column;
    }
    .reservation-page-actions {
        padding-top: 0;
    }
    .reservation-toolbar {
        flex-wrap: wrap;
    }
    .reservation-search {
        flex: 1 1 100%;
        width: 100%;
    }
    .reservation-filter-group {
        flex: 1 1 100%;
        justify-content: stretch;
    }
    .filter-label {
        flex: 1;
        justify-content: space-between;
    }
    .filter-label select {
        flex: 1;
        max-width: none;
    }
}
@media (max-width: 720px) {
    .reservation-title-row h1 {
        font-size: 18px;
    }
    .reservation-stats article strong {
        font-size: 19px;
    }
}
@media (max-width: 600px) {
    .reservations-page {
        padding: 0;
    }
    .reservation-title-row p {
        font-size: 10px;
    }
    .reservation-page-actions {
        width: 100%;
    }
    .reservation-page-actions > * {
        flex: 1;
    }
    .reservation-stats {
        grid-template-columns: 1fr;
        gap: 8px;
        margin-bottom: 14px;
    }
    .reservation-stats article {
        padding: 12px;
    }
    .reservation-table-heading {
        padding: 16px 14px 0;
    }
    .reservation-toolbar {
        padding: 14px;
    }
    .reservation-filter-group {
        gap: 8px;
    }
    .filter-label {
        align-items: stretch;
        flex-direction: column;
        gap: 5px;
    }
    .filter-label select {
        width: 100%;
    }
    .reservation-pagination {
        align-items: flex-start;
        flex-direction: column;
    }
    .reservation-pagination > div {
        width: 100%;
    }
    .reservation-pagination > div button {
        flex: 1;
    }
}
@media (prefers-reduced-motion: reduce) {
    .reservations-page *,
    .reservations-page *::before,
    .reservations-page *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}

.reservation-confirm-backdrop {
    --page-bg: var(--background, #f6f8fb);
    --surface-color: var(--surface, #fff);
    --border-color: var(--border, #e5e7eb);
    --text-primary-color: var(--text-primary, #111827);
    --text-secondary-color: var(--text-secondary, #667085);
    --text-tertiary-color: var(--text-tertiary, #98a2b3);
    --primary-color: var(--primary, #059669);
    position: fixed;
    z-index: 10000;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgb(15 23 42 / 56%);
    backdrop-filter: blur(2px);
}
.reservation-confirm-dialog {
    width: min(100%, 460px);
    padding: 24px;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 24px 70px rgb(15 23 42 / 24%);
    color: #111827;
    animation: confirm-enter 0.16s ease-out;
    opacity: 1;
    backdrop-filter: none;
}
.reservation-confirm-dialog h2 {
    color: #111827;
}
.reservation-confirm-dialog > p {
    color: #475467;
}
.reservation-confirm-summary {
    background: #f8fafc;
    border-color: #e5e7eb;
}
.reservation-confirm-summary span {
    color: #667085;
}
.reservation-confirm-summary strong {
    color: #111827;
}
.reservation-confirm-actions .reservation-btn {
    border-color: #d0d5dd;
    background: #fff;
    color: #344054;
}
.reservation-confirm-actions .reservation-btn:hover:not(:disabled) {
    border-color: #98a2b3;
    background: #f9fafb;
    color: #101828;
}
.reservation-confirm-actions .reservation-confirm-submit {
    border-color: #059669;
    background: #059669;
    color: #fff;
}
.reservation-confirm-actions .reservation-confirm-submit:hover:not(:disabled) {
    background: #047857;
    color: #fff;
}
.reservation-confirm-actions .reservation-confirm-submit.submit-release {
    border-color: #2563eb;
    background: #2563eb;
    color: #fff;
}
.reservation-confirm-actions .reservation-confirm-submit.submit-cancel {
    border-color: #dc2626;
    background: #dc2626;
    color: #fff;
}
.reservation-confirm-actions button:disabled {
    opacity: 0.65;
    cursor: wait;
}
.reservation-confirm-icon {
    display: grid;
    width: 46px;
    height: 46px;
    margin-bottom: 16px;
    place-items: center;
    border: 1px solid transparent;
    border-radius: 13px;
}
.confirm-fulfill {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
}
.confirm-release {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
}
.confirm-cancel {
    border-color: #fecaca;
    background: #fef2f2;
    color: #b91c1c;
}
.reservation-confirm-dialog h2 {
    margin: 0;
    color: var(--text-primary-color);
    font-size: 18px;
    font-weight: 750;
    letter-spacing: -0.025em;
}
.reservation-confirm-dialog > p {
    margin: 9px 0 16px;
    color: var(--text-secondary-color);
    font-size: 12px;
    line-height: 1.65;
}
.reservation-confirm-summary {
    display: grid;
    gap: 9px;
    padding: 13px 14px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    background: var(--page-bg);
}
.reservation-confirm-summary span {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    color: var(--text-secondary-color);
    font-size: 11px;
}
.reservation-confirm-summary strong {
    max-width: 68%;
    color: var(--text-primary-color);
    font-weight: 650;
    text-align: right;
    overflow-wrap: anywhere;
}
.reservation-confirm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 20px;
}
.reservation-confirm-submit {
    border-color: var(--primary-color);
    background: var(--primary-color);
    color: var(--primary-foreground, #fff);
}
.reservation-confirm-submit:hover:not(:disabled) {
    filter: brightness(0.94);
    color: var(--primary-foreground, #fff);
}
.reservation-confirm-submit.submit-cancel {
    border-color: #dc2626;
    background: #dc2626;
    color: #fff;
}
.reservation-confirm-submit.submit-release {
    border-color: #2563eb;
    background: #2563eb;
    color: #fff;
}
@keyframes confirm-enter {
    from {
        opacity: 0;
        transform: translateY(5px) scale(0.985);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
@media (max-width: 480px) {
    .reservation-confirm-dialog {
        padding: 19px;
    }
    .reservation-confirm-actions {
        flex-direction: column-reverse;
    }
    .reservation-confirm-actions button {
        width: 100%;
    }
}
@media (prefers-reduced-motion: reduce) {
    .reservation-confirm-dialog {
        animation: none;
    }
}
@media (max-width: 600px) {
    .purpose-manager-form {
        grid-template-columns: 1fr;
    }
    .purpose-form-actions {
        flex-wrap: wrap;
    }
    .purpose-section {
        padding: 12px;
    }
    .purpose-section-heading {
        align-items: flex-start;
    }
    .purpose-chip-copy {
        align-items: flex-start;
        flex-direction: column;
        gap: 1px;
    }
    .reservation-table-heading {
        flex-wrap: wrap;
    }
    .reservation-heading-actions {
        width: 100%;
        margin-left: 0;
    }
}
</style>
