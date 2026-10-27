<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface Props {
    userName?: string;
    userEmail?: string;
}
interface LocationOption {
    id: number;
    name: string;
    code?: string;
}
interface VariantOption {
    id: number;
    product_id: number;
    name: string;
    sku?: string | null;
    price: string | number;
    available: string | number;
    image_url?: string | null;
    image_alt?: string | null;
}
interface CustomerOption {
    id: number;
    customer_code: string;
    name: string;
    phone?: string | null;
    email?: string | null;
    company_name?: string | null;
    status: 'active' | 'inactive';
}

interface OrderItem {
    id: number;
    product_name: string;
    variant_name?: string | null;
    sku?: string | null;
    product_variant_id?: number;
    quantity: string | number;
    unit_price: string | number;
    line_total: string | number;
    fulfilled_quantity?: string | number;
    remaining_quantity?: string | number;
    created_at?: string;
    updated_at?: string;
}
interface OrderHistory {
    id: number;
    from_status?: string | null;
    to_status?: string | null;
    status?: string | null;
    note?: string | null;
    reason?: string | null;
    created_at?: string;
    actor?: { id?: number; name?: string | null } | null;
}
interface Fulfillment {
    id: number;
    reference?: string | null;
    status?: string | null;
    notes?: string | null;
    fulfilled_at?: string | null;
    created_at?: string;
    actor?: { name?: string | null } | null;
    items?: Array<{
        id: number;
        quantity: string | number;
        order_item?: OrderItem | null;
        orderItem?: OrderItem | null;
    }>;
}
interface SmsLog {
    id: number;
    event?: string | null;
    status?: string | null;
    recipient?: string | null;
    phone?: string | null;
    provider?: string | null;
    message?: string | null;
    error_message?: string | null;
    created_at?: string;
}
interface RiskAssessment {
    id: number;
    score: number;
    level: string;
    decision: string;
    signals?: Array<{ code: string; message: string; points: number }>;
    review_note?: string | null;
}
interface Shipment {
    id: number;
    provider: string;
    tracking_code?: string | null;
    status: string;
    recipient_address: string;
    cod_amount: string | number;
    last_error?: string | null;
}
interface Order {
    id: number;
    order_number: string;
    status:
        | 'pending'
        | 'confirmed'
        | 'processing'
        | 'partially_fulfilled'
        | 'fulfilled'
        | 'cancelled';
    customer_name: string;
    customer_phone?: string | null;
    customer_email?: string | null;
    grand_total: string | number;
    subtotal?: string | number;
    discount_total?: string | number;
    tax_total?: string | number;
    shipping_total?: string | number;
    currency?: string;
    channel?: string | null;
    notes?: string | null;
    cancellation_reason?: string | null;
    created_at: string;
    updated_at?: string;
    confirmed_at?: string | null;
    cancelled_at?: string | null;
    location_id?: number;
    location?: LocationOption;
    creator?: { id?: number; name?: string | null } | null;
    items?: OrderItem[];
    status_history?: OrderHistory[];
    statusHistory?: OrderHistory[];
    fulfillments?: Fulfillment[];
    risk_assessments?: RiskAssessment[];
    riskAssessments?: RiskAssessment[];
    shipments?: Shipment[];
    sms_logs?: SmsLog[];
    smsLogs?: SmsLog[];
}
const props = withDefaults(defineProps<Props>(), {
    userName: '',
    userEmail: '',
});
const rows = ref<Order[]>([]);
const locations = ref<LocationOption[]>([]);
const variants = ref<VariantOption[]>([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const search = ref('');
const status = ref('all');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const createOpen = ref(false);
const detail = ref<Order | null>(null);
const detailLoading = ref(false);
const cancelTarget = ref<Order | null>(null);
const cancelReason = ref('');
const fulfillmentQuantities = ref<Record<number, string>>({});
const fulfillmentNotes = ref('');
const courierProvider = ref('steadfast');
const courierAddress = ref('');
const courierCod = ref('');
const smsEvent = ref('confirmed');
const smsDraft = ref('');
const smsPreview = computed(() => {
    const order = detail.value;
    if (!order) return '';
    const name = order.customer_name || 'Customer';
    const orderNo = order.order_number || `#${order.id}`;
    const templates: Record<string, string> = {
        confirmed: `Dear ${name}, your order ${orderNo} has been confirmed. Total: ${currency(
            order.grand_total,
        )}. Thank you for shopping with Madina Scale.`,
        processing: `Dear ${name}, your order ${orderNo} is now being prepared. We will update you when it is ready for delivery.`,
        delivered: `Dear ${name}, your order ${orderNo} has been marked delivered. Thank you for shopping with Madina Scale.`,
        cancelled: `Dear ${name}, your order ${orderNo} has been cancelled. Please contact our support team if you need assistance.`,
    };
    return smsDraft.value || templates[smsEvent.value] || templates.confirmed;
});
const riskLoading = ref(false);
const locationId = ref('');
const customerName = ref('');
const customerId = ref<number | null>(null);
const customerSearch = ref('');
const customerOptions = ref<CustomerOption[]>([]);
const customerLoading = ref(false);
const customerPickerOpen = ref(false);
const customerLookupError = ref('');
const customerPhone = ref('');
const customerEmail = ref('');
const notes = ref('');
const lineVariantId = ref('');
const lineQuantity = ref('1');
const cart = ref<
    Array<{
        product_variant_id: number;
        quantity: number;
        name: string;
        sku?: string | null;
        price: number;
        available: number;
    }>
>([]);
const selectedVariant = computed(() =>
    variants.value.find((v) => String(v.id) === lineVariantId.value),
);
const productDropdownOpen = ref(false);
const subtotal = computed(() =>
    cart.value.reduce((sum, line) => sum + line.price * line.quantity, 0),
);
const stats = ref({ total: 0, active: 0, pending: 0, fulfilled: 0 });
const currency = (value: string | number) =>
    new Intl.NumberFormat('en-BD', {
        style: 'currency',
        currency: 'BDT',
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);
const date = (value: string) =>
    new Intl.DateTimeFormat('en-BD', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
function csrf(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}
async function api(url: string, init: RequestInit = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...init,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(init.body
                ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }
                : {}),
            ...init.headers,
        },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const first = payload.errors
            ? Object.values(
                  payload.errors as Record<string, string[]>,
              ).flat()[0]
            : null;
        throw new Error(
            String(
                first ||
                    payload.message ||
                    `Request failed (${response.status}).`,
            ),
        );
    }
    return payload;
}
async function load(target = page.value) {
    loading.value = true;
    error.value = '';
    try {
        const params = new URLSearchParams({
            page: String(target),
            per_page: '15',
            status: status.value,
            search: search.value.trim(),
        });
        const payload = await api(`/api/orders?${params}`);
        rows.value = payload.data || [];
        page.value = payload.current_page || target;
        lastPage.value = payload.last_page || 1;
        total.value = payload.total || 0;
        stats.value = payload.stats || {
            total: total.value,
            active: 0,
            pending: 0,
            fulfilled: 0,
        };
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Unable to load orders.';
    } finally {
        loading.value = false;
    }
}
async function loadOptions() {
    try {
        const payload = await api(
            `/api/orders/options${locationId.value ? `?location_id=${locationId.value}` : ''}`,
        );
        locations.value = payload.locations || [];
        variants.value = payload.variants || [];
    } catch (e) {
        // Never leave a previous location's catalogue visible after a failed refresh.
        locations.value = [];
        variants.value = [];
        lineVariantId.value = '';
        error.value =
            e instanceof Error ? e.message : 'Unable to load order options.';
    }
}
async function searchCustomers() {
    customerLoading.value = true;
    customerLookupError.value = '';
    try {
        const term = customerSearch.value.trim();
        const query = new URLSearchParams({ status: 'active', per_page: '10' });
        if (term) query.set('search', term);
        const payload = await api(`/api/customers?${query.toString()}`);
        customerOptions.value = Array.isArray(payload.data) ? payload.data : [];
        customerPickerOpen.value = true;
        if (!customerOptions.value.length) {
            customerLookupError.value = term
                ? 'No active customer found. You can enter a one-time customer instead.'
                : 'No active customers found yet.';
        }
    } catch (e) {
        customerLookupError.value =
            e instanceof Error ? e.message : 'Unable to search customers.';
    } finally {
        customerLoading.value = false;
    }
}
function chooseCustomer(customer: CustomerOption) {
    customerId.value = customer.id;
    customerName.value = customer.name;
    customerPhone.value = customer.phone || '';
    customerEmail.value = customer.email || '';
    customerSearch.value = `${customer.name}${customer.phone ? ` · ${customer.phone}` : ''}`;
    customerPickerOpen.value = false;
    customerLookupError.value = '';
}
function useOneTimeCustomer() {
    customerId.value = null;
    customerSearch.value = '';
    customerPickerOpen.value = false;
    customerOptions.value = [];
    customerLookupError.value = '';
    customerName.value = '';
    customerId.value = null;
    customerSearch.value = '';
    customerOptions.value = [];
    customerPickerOpen.value = false;
    customerLookupError.value = '';
    customerPhone.value = '';
    customerEmail.value = '';
}
function openCreate() {
    error.value = '';
    notice.value = '';
    customerName.value = '';
    customerPhone.value = '';
    customerEmail.value = '';
    notes.value = '';
    cart.value = [];
    lineVariantId.value = '';
    lineQuantity.value = '1';
    createOpen.value = true;
    void loadOptions();
}
watch(locationId, () => {
    variants.value = [];
    lineVariantId.value = '';
    void loadOptions();
});
function addLine() {
    const v = selectedVariant.value;
    const qty = Number(lineQuantity.value);
    if (!v) {
        error.value = 'Choose a product variant.';
        return;
    }
    if (!Number.isFinite(qty) || qty <= 0) {
        error.value = 'Quantity must be greater than zero.';
        return;
    }
    const available = Number(v.available);
    const existing = cart.value.find((l) => l.product_variant_id === v.id);
    const nextQty = qty + (existing?.quantity || 0);
    if (nextQty > available) {
        error.value = `Only ${available} units are available for ${v.name}.`;
        return;
    }
    if (existing) existing.quantity = nextQty;
    else
        cart.value.push({
            product_variant_id: v.id,
            quantity: qty,
            name: v.name,
            sku: v.sku,
            price: Number(v.price),
            available,
        });
    lineVariantId.value = '';
    lineQuantity.value = '1';
    error.value = '';
}
function removeLine(id: number) {
    cart.value = cart.value.filter((line) => line.product_variant_id !== id);
}
async function createOrder() {
    if (saving.value) return;
    if (!locationId.value || !customerName.value.trim() || !cart.value.length) {
        error.value =
            'Choose a location, select or enter a customer, and add at least one item.';
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        const key = crypto.randomUUID();
        const payload = await api('/api/orders', {
            method: 'POST',
            body: JSON.stringify({
                idempotency_key: key,
                location_id: Number(locationId.value),
                customer_id: customerId.value,
                customer_name: customerName.value.trim(),
                customer_phone: customerPhone.value.trim() || null,
                customer_email: customerEmail.value.trim() || null,
                notes: notes.value.trim() || null,
                items: cart.value.map((line) => ({
                    product_variant_id: line.product_variant_id,
                    quantity: line.quantity,
                })),
            }),
        });
        createOpen.value = false;
        notice.value = `${payload.data.order_number} created and stock reserved.`;
        await load(1);
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Unable to create order.';
    } finally {
        saving.value = false;
    }
}
async function openDetail(order: Order) {
    detailLoading.value = true;
    error.value = '';
    try {
        const payload = await api(`/api/orders/${order.id}`);
        detail.value = payload.data;
        courierAddress.value =
            payload.data.shipments?.[0]?.recipient_address || '';
        courierCod.value = String(payload.data.grand_total ?? '0');
        smsEvent.value =
            payload.data.status === 'fulfilled'
                ? 'delivered'
                : payload.data.status === 'cancelled'
                  ? 'cancelled'
                  : 'confirmed';
        smsDraft.value = '';
        fulfillmentQuantities.value = {};
        fulfillmentNotes.value = '';
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Unable to load order.';
    } finally {
        detailLoading.value = false;
    }
}
async function confirmOrder(order: Order) {
    if (!window.confirm(`Confirm ${order.order_number}?`)) return;
    try {
        const payload = await api(`/api/orders/${order.id}/confirm`, {
            method: 'POST',
            body: '{}',
        });
        notice.value = payload.message;
        await load(page.value);
        if (detail.value?.id === order.id) await openDetail(order);
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Unable to confirm order.';
    }
}
async function fulfillOrder() {
    const order = detail.value;
    if (!order || saving.value) return;
    const items = (order.items || [])
        .map((item) => ({
            sales_order_item_id: item.id,
            quantity: Number(fulfillmentQuantities.value[item.id] || 0),
        }))
        .filter((item) => Number.isFinite(item.quantity) && item.quantity > 0);
    if (!items.length) {
        error.value = 'Enter a fulfillment quantity for at least one item.';
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        const payload = await api(`/api/orders/${order.id}/fulfill`, {
            method: 'POST',
            body: JSON.stringify({
                idempotency_key: crypto.randomUUID(),
                notes: fulfillmentNotes.value.trim() || null,
                items,
            }),
        });
        notice.value = payload.message;
        fulfillmentNotes.value = '';
        fulfillmentQuantities.value = {};
        await load(page.value);
        await openDetail(order);
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Unable to record fulfillment.';
    } finally {
        saving.value = false;
    }
}
async function assessRisk() {
    const order = detail.value;
    if (!order || riskLoading.value) return;
    riskLoading.value = true;
    error.value = '';
    try {
        const payload = await api(`/api/orders/${order.id}/risk-assessment`, {
            method: 'POST',
            body: '{}',
        });
        notice.value = `Risk assessment: ${payload.data.level} (${payload.data.score}/100).`;
        await openDetail(order);
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Unable to assess order risk.';
    } finally {
        riskLoading.value = false;
    }
}
async function reviewRisk(assessment: RiskAssessment, decision: string) {
    const order = detail.value;
    if (!order) return;
    const note = window.prompt(
        'Review note (required):',
        decision === 'allow'
            ? 'Reviewed and approved'
            : 'Requires additional verification',
    );
    if (!note || note.trim().length < 3) return;
    try {
        await api(`/api/orders/risk-assessments/${assessment.id}/review`, {
            method: 'POST',
            body: JSON.stringify({ decision, review_note: note.trim() }),
        });
        notice.value = 'Risk review saved.';
        await openDetail(order);
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Unable to save risk review.';
    }
}
async function requestPhoneOtp() {
    const order = detail.value;
    if (!order) return;
    try {
        const payload = await api(
            `/api/orders/${order.id}/phone-verification/request`,
            {
                method: 'POST',
                body: '{}',
            },
        );
        notice.value = payload.message;
        const code = window.prompt(
            'Enter the 6-digit code sent to the customer:',
        );
        if (code && /^\d{6}$/.test(code)) {
            await api(`/api/orders/${order.id}/phone-verification/verify`, {
                method: 'POST',
                body: JSON.stringify({ code }),
            });
            notice.value = 'Customer phone verified.';
            await assessRisk();
        }
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Unable to verify customer phone.';
    }
}
async function bookCourier() {
    const order = detail.value;
    if (!order || saving.value) return;
    if (courierAddress.value.trim().length < 8) {
        error.value = 'Enter the complete recipient address.';
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        const payload = await api(`/api/orders/${order.id}/shipments`, {
            method: 'POST',
            body: JSON.stringify({
                provider: courierProvider.value,
                idempotency_key: crypto.randomUUID(),
                recipient_address: courierAddress.value.trim(),
                cod_amount: Number(courierCod.value || 0),
            }),
        });
        notice.value = payload.message;
        await openDetail(order);
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Unable to book courier shipment.';
    } finally {
        saving.value = false;
    }
}
async function sendOrderSms() {
    const order = detail.value;
    if (!order) return;
    try {
        const payload = await api(`/api/orders/${order.id}/sms`, {
            method: 'POST',
            body: JSON.stringify({ event: smsEvent.value }),
        });
        notice.value = payload.message;
        smsDraft.value = '';
        await openDetail(order);
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Unable to send order SMS.';
    }
}
async function cancelOrder() {
    const target = cancelTarget.value;
    if (!target || cancelReason.value.trim().length < 3) {
        error.value =
            'Please provide a cancellation reason (at least 3 characters).';
        return;
    }
    saving.value = true;
    try {
        const payload = await api(`/api/orders/${target.id}/cancel`, {
            method: 'POST',
            body: JSON.stringify({ reason: cancelReason.value.trim() }),
        });
        notice.value = payload.message;
        cancelTarget.value = null;
        cancelReason.value = '';
        await load(page.value);
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Unable to cancel order.';
    } finally {
        saving.value = false;
    }
}
watch([status, search], () => {
    page.value = 1;
    void load(1);
});
onMounted(() => {
    void load(1);
});
</script>

<template>
    <Head title="Orders Management" />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <main class="orders-page">
            <header class="orders-hero">
                <div>
                    <div class="orders-breadcrumb">
                        <a href="/dashboard">Workspace</a
                        ><Icon name="chevron-right" :size="13" /><strong
                            >Orders</strong
                        >
                    </div>
                    <div class="orders-title">
                        <span class="orders-title-icon"
                            ><Icon name="shopping-bag" :size="24"
                        /></span>
                        <div>
                            <h1>Orders Management</h1>
                            <p>
                                Manage customer orders, inventory reservations
                                and order lifecycle.
                            </p>
                        </div>
                    </div>
                </div>
                <button class="orders-primary" @click="openCreate">
                    <Icon name="plus" :size="17" /> Create order
                </button>
            </header>
            <div v-if="notice" class="orders-notice" role="status">
                <Icon name="check-circle" :size="18" /><span>{{ notice }}</span
                ><button @click="notice = ''" aria-label="Dismiss notice">
                    ×
                </button>
            </div>
            <div v-if="error" class="orders-error" role="alert">
                <Icon name="alert-circle" :size="18" /><span>{{ error }}</span
                ><button @click="error = ''" aria-label="Dismiss error">
                    ×
                </button>
            </div>
            <section
                class="orders-metrics"
                aria-label="Order performance overview"
            >
                <article class="orders-metric-card metric-total">
                    <div class="metric-card-top">
                        <span class="metric-label">Total orders</span
                        ><span class="metric-icon"
                            ><Icon name="shopping-bag" :size="19"
                        /></span>
                    </div>
                    <strong>{{ stats.total.toLocaleString('en-BD') }}</strong>
                    <span class="metric-foot"
                        >Across your accessible locations</span
                    >
                </article>
                <article class="orders-metric-card metric-active">
                    <div class="metric-card-top">
                        <span class="metric-label">Active orders</span
                        ><span class="metric-icon"
                            ><Icon name="check-circle" :size="19"
                        /></span>
                    </div>
                    <strong>{{ stats.active.toLocaleString('en-BD') }}</strong>
                    <span class="metric-foot"
                        >Pending, confirmed or in fulfillment</span
                    >
                </article>
                <article class="orders-metric-card metric-pending">
                    <div class="metric-card-top">
                        <span class="metric-label">Pending orders</span
                        ><span class="metric-icon"
                            ><Icon name="clock" :size="19"
                        /></span>
                    </div>
                    <strong>{{ stats.pending.toLocaleString('en-BD') }}</strong>
                    <span class="metric-foot">Waiting for confirmation</span>
                </article>
                <article class="orders-metric-card metric-fulfilled">
                    <div class="metric-card-top">
                        <span class="metric-label">Fulfilled orders</span
                        ><span class="metric-icon"
                            ><Icon name="file-check" :size="19"
                        /></span>
                    </div>
                    <strong>{{
                        stats.fulfilled.toLocaleString('en-BD')
                    }}</strong>
                    <span class="metric-foot">Completed order workflows</span>
                </article>
            </section>
            <section class="orders-panel">
                <div class="orders-panel-head">
                    <div>
                        <h2>Order register</h2>
                        <p>Search by order number, customer name or phone.</p>
                    </div>
                    <span class="orders-count"
                        >{{ total.toLocaleString('en-BD') }} matching
                        records</span
                    >
                </div>
                <div class="orders-toolbar">
                    <label class="orders-search"
                        ><Icon name="search" :size="18" /><input
                            v-model="search"
                            type="search"
                            placeholder="Search orders, customers or phone…"
                    /></label>
                    <label class="orders-filter"
                        ><span>Status</span
                        ><select v-model="status">
                            <option value="all">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="partially_fulfilled">
                                Partially fulfilled
                            </option>
                            <option value="fulfilled">Fulfilled</option>
                            <option value="cancelled">Cancelled</option>
                        </select></label
                    >
                </div>
                <div class="orders-table-wrap">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Location</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="8" class="orders-empty">
                                    Loading orders…
                                </td>
                            </tr>
                            <tr v-else-if="!rows.length">
                                <td colspan="8" class="orders-empty">
                                    <Icon
                                        name="package-open"
                                        :size="26"
                                    /><strong>No orders found</strong
                                    ><span
                                        >Create your first order or change the
                                        filters.</span
                                    >
                                </td>
                            </tr>
                            <tr
                                v-for="order in rows"
                                :key="order.id"
                                class="orders-clickable-row"
                                tabindex="0"
                                role="button"
                                :aria-label="`View order ${order.order_number}`"
                                @click="openDetail(order)"
                                @keydown.enter.prevent="openDetail(order)"
                                @keydown.space.prevent="openDetail(order)"
                            >
                                <td>
                                    <button
                                        class="orders-number"
                                        @click.stop="openDetail(order)"
                                    >
                                        {{ order.order_number }}</button
                                    ><small>#{{ order.id }}</small>
                                </td>
                                <td>
                                    <strong>{{ order.customer_name }}</strong
                                    ><small>{{
                                        order.customer_phone ||
                                        'No phone provided'
                                    }}</small>
                                </td>
                                <td>{{ order.location?.name || '—' }}</td>
                                <td>
                                    {{ order.items?.length || 0 }}
                                    {{
                                        (order.items?.length || 0) === 1
                                            ? 'line'
                                            : 'lines'
                                    }}
                                </td>
                                <td class="orders-money">
                                    {{ currency(order.grand_total) }}
                                </td>
                                <td>
                                    <span
                                        class="orders-status"
                                        :class="`status-${order.status}`"
                                        >{{ order.status }}</span
                                    >
                                </td>
                                <td>{{ date(order.created_at) }}</td>
                                <td>
                                    <div class="orders-row-actions">
                                        <button
                                            title="View order"
                                            @click.stop="openDetail(order)"
                                        >
                                            <Icon
                                                name="eye"
                                                :size="16"
                                            /></button
                                        ><button
                                            v-if="order.status === 'pending'"
                                            title="Confirm order"
                                            @click.stop="confirmOrder(order)"
                                        >
                                            <Icon
                                                name="check"
                                                :size="16"
                                            /></button
                                        ><button
                                            v-if="
                                                order.status === 'pending' ||
                                                order.status === 'confirmed'
                                            "
                                            title="Cancel order"
                                            @click.stop="
                                                cancelTarget = order;
                                                cancelReason = '';
                                            "
                                        >
                                            <Icon name="x" :size="16" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer class="orders-pagination">
                    <span>Page {{ page }} of {{ lastPage }}</span>
                    <div>
                        <button
                            :disabled="page <= 1 || loading"
                            @click="load(page - 1)"
                        >
                            Previous</button
                        ><button
                            :disabled="page >= lastPage || loading"
                            @click="load(page + 1)"
                        >
                            Next
                        </button>
                    </div>
                </footer>
            </section>
            <Teleport to="body">
                <div
                    v-if="createOpen"
                    class="orders-overlay orders-create-overlay"
                    @click.self="createOpen = false"
                    @keydown.esc="createOpen = false"
                >
                    <section
                        class="orders-dialog orders-create-dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="create-order-title"
                    >
                        <header class="orders-dialog-head orders-create-head">
                            <div class="orders-create-heading">
                                <span class="orders-create-icon"
                                    ><Icon name="shopping-bag" :size="21"
                                /></span>
                                <div>
                                    <span class="orders-eyebrow"
                                        >NEW TRANSACTION</span
                                    >
                                    <h2 id="create-order-title">
                                        Create order
                                    </h2>
                                    <p>
                                        Set up the customer, choose products,
                                        and reserve stock in one place.
                                    </p>
                                </div>
                            </div>
                            <button
                                class="orders-close"
                                @click="createOpen = false"
                                aria-label="Close create order"
                            >
                                <Icon name="x" :size="19" />
                            </button>
                        </header>

                        <div class="orders-dialog-body orders-create-body">
                            <section class="orders-create-section">
                                <div class="orders-create-section-title">
                                    <span class="orders-create-section-icon"
                                        ><Icon name="user-round" :size="17"
                                    /></span>
                                    <div>
                                        <h3>Customer & destination</h3>
                                        <p>
                                            Where the order will be fulfilled
                                            and who it belongs to.
                                        </p>
                                    </div>
                                </div>
                                <div class="orders-form-grid">
                                    <label>
                                        <span
                                            >Fulfillment location <i>*</i></span
                                        >
                                        <select v-model="locationId" required>
                                            <option value="">
                                                Select location
                                            </option>
                                            <option
                                                v-for="loc in locations"
                                                :key="loc.id"
                                                :value="String(loc.id)"
                                            >
                                                {{ loc.name
                                                }}{{
                                                    loc.code
                                                        ? ` · ${loc.code}`
                                                        : ''
                                                }}
                                            </option>
                                        </select>
                                    </label>
                                    <div
                                        class="orders-form-wide customer-picker"
                                    >
                                        <label>
                                            <span
                                                ><Icon
                                                    name="search"
                                                    :size="13"
                                                />
                                                Find existing customer</span
                                            >
                                            <div class="customer-search-row">
                                                <input
                                                    v-model="customerSearch"
                                                    type="search"
                                                    placeholder="Search by name, phone, email or customer code"
                                                    @keydown.enter.prevent="
                                                        searchCustomers
                                                    "
                                                />
                                                <button
                                                    type="button"
                                                    class="customer-search-button"
                                                    :disabled="customerLoading"
                                                    @click="searchCustomers"
                                                >
                                                    <Icon
                                                        :name="
                                                            customerLoading
                                                                ? 'loader-circle'
                                                                : 'search'
                                                        "
                                                        :size="15"
                                                    />
                                                    {{
                                                        customerLoading
                                                            ? 'Searching…'
                                                            : 'Search'
                                                    }}
                                                </button>
                                            </div>
                                        </label>
                                        <div
                                            v-if="
                                                customerPickerOpen &&
                                                customerOptions.length
                                            "
                                            class="customer-results"
                                        >
                                            <button
                                                v-for="customer in customerOptions"
                                                :key="customer.id"
                                                type="button"
                                                class="customer-result"
                                                @click="
                                                    chooseCustomer(customer)
                                                "
                                            >
                                                <span class="customer-avatar"
                                                    ><Icon
                                                        name="user-round"
                                                        :size="17"
                                                /></span>
                                                <span
                                                    class="customer-result-main"
                                                >
                                                    <strong>{{
                                                        customer.name
                                                    }}</strong>
                                                    <small
                                                        >{{
                                                            customer.customer_code
                                                        }}<template
                                                            v-if="
                                                                customer.phone
                                                            "
                                                        >
                                                            ·
                                                            {{
                                                                customer.phone
                                                            }}</template
                                                        ></small
                                                    >
                                                </span>
                                                <span
                                                    class="customer-result-meta"
                                                >
                                                    <small>{{
                                                        customer.email ||
                                                        'No email'
                                                    }}</small>
                                                    <Icon
                                                        name="arrow-up-left"
                                                        :size="15"
                                                    />
                                                </span>
                                            </button>
                                        </div>
                                        <p
                                            v-if="customerLookupError"
                                            class="customer-lookup-message"
                                        >
                                            {{ customerLookupError }}
                                        </p>
                                        <div
                                            v-if="customerId"
                                            class="customer-selected"
                                        >
                                            <Icon
                                                name="check-circle-2"
                                                :size="15"
                                            />
                                            <span
                                                >Linked to customer record</span
                                            >
                                            <button
                                                type="button"
                                                @click="useOneTimeCustomer"
                                            >
                                                Use one-time customer
                                            </button>
                                        </div>
                                        <button
                                            v-else
                                            type="button"
                                            class="customer-manual-toggle"
                                            @click="customerPickerOpen = false"
                                        >
                                            <Icon
                                                name="user-round-pen"
                                                :size="14"
                                            />
                                            Enter a one-time customer instead
                                        </button>
                                    </div>
                                    <label>
                                        <span>Customer name <i>*</i></span>
                                        <input
                                            v-model="customerName"
                                            required
                                            maxlength="160"
                                            placeholder="Customer or company name"
                                        />
                                    </label>
                                    <label>
                                        <span
                                            ><Icon name="phone" :size="13" />
                                            Phone</span
                                        >
                                        <input
                                            v-model="customerPhone"
                                            maxlength="40"
                                            inputmode="tel"
                                            placeholder="+880..."
                                        />
                                    </label>
                                    <label>
                                        <span
                                            ><Icon name="mail" :size="13" />
                                            Email</span
                                        >
                                        <input
                                            v-model="customerEmail"
                                            type="email"
                                            maxlength="190"
                                            placeholder="name@example.com"
                                        />
                                    </label>
                                    <label class="orders-form-wide">
                                        <span
                                            ><Icon
                                                name="notebook-pen"
                                                :size="13"
                                            />
                                            Internal notes</span
                                        >
                                        <textarea
                                            v-model="notes"
                                            rows="2"
                                            maxlength="5000"
                                            placeholder="Delivery instructions or internal context..."
                                        />
                                    </label>
                                </div>
                            </section>

                            <section
                                class="orders-create-section orders-create-products"
                            >
                                <div class="orders-create-section-title">
                                    <span class="orders-create-section-icon"
                                        ><Icon name="package" :size="17"
                                    /></span>
                                    <div>
                                        <h3>Order items</h3>
                                        <p>
                                            Browse products in this location inventory, including out-of-stock items.
                                        </p>
                                    </div>
                                    <span class="orders-create-count"
                                        >{{ cart.length }}
                                        {{
                                            cart.length === 1 ? 'item' : 'items'
                                        }}</span
                                    >
                                </div>
                                <div class="orders-add-line">
                                    <label
                                        class="orders-form-wide orders-product-picker"
                                    >
                                        <span>Product / variant <i>*</i></span>
                                        <button
                                            type="button"
                                            class="orders-product-picker-trigger"
                                            :disabled="!locationId"
                                            :aria-expanded="productDropdownOpen"
                                            @click="
                                                productDropdownOpen =
                                                    !productDropdownOpen
                                            "
                                        >
                                            <span class="orders-product-thumb">
                                                <img
                                                    v-if="selectedVariant?.image_url"
                                                    :src="selectedVariant.image_url"
                                                    :alt="selectedVariant.image_alt || selectedVariant.name"
                                                    loading="lazy"
                                                />
                                                <Icon v-else name="package" :size="22" />
                                            </span>
                                            <span
                                                class="orders-product-trigger-copy"
                                            >
                                                <strong>{{
                                                    selectedVariant?.name ||
                                                    (locationId
                                                        ? 'Choose a product variant'
                                                        : 'Select a location first')
                                                }}</strong>
                                                <small v-if="selectedVariant">{{
                                                    selectedVariant.sku ||
                                                    'SKU not set'
                                                }}</small>
                                                <small v-else
                                                    >Browse products with price
                                                    and stock details</small
                                                >
                                            </span>
                                            <span
                                                v-if="selectedVariant"
                                                class="orders-product-trigger-price"
                                            >
                                                {{
                                                    currency(
                                                        selectedVariant.price,
                                                    )
                                                }}
                                                <small
                                                    >{{
                                                        Number(
                                                            selectedVariant.available,
                                                        ) || 0
                                                    }}
                                                    in stock</small
                                                >
                                            </span>
                                            <Icon
                                                :name="
                                                    productDropdownOpen
                                                        ? 'chevron-up'
                                                        : 'chevron-down'
                                                "
                                                :size="18"
                                            />
                                        </button>
                                        <div
                                            v-if="
                                                productDropdownOpen &&
                                                locationId
                                            "
                                            class="orders-product-options"
                                        >
                                            <button
                                                v-for="variant in variants"
                                                :key="variant.id"
                                                type="button"
                                                class="orders-product-option"
                                                :class="{
                                                    'is-selected':
                                                        String(variant.id) ===
                                                        lineVariantId,
                                                }"
                                                @click="
                                                    lineVariantId = String(
                                                        variant.id,
                                                    );
                                                    productDropdownOpen = false;
                                                "
                                            >
                                                <span class="orders-product-thumb">
                                                    <img
                                                        v-if="variant.image_url"
                                                        :src="variant.image_url"
                                                        :alt="variant.image_alt || variant.name"
                                                        loading="lazy"
                                                    />
                                                    <Icon v-else name="package" :size="22" />
                                                </span>
                                                <span
                                                    class="orders-product-option-copy"
                                                >
                                                    <strong>{{
                                                        variant.name
                                                    }}</strong>
                                                    <small>{{
                                                        variant.sku ||
                                                        'SKU not set'
                                                    }}</small>
                                                    <span
                                                        class="orders-product-stock"
                                                        :class="{
                                                            'is-low':
                                                                Number(
                                                                    variant.available,
                                                                ) <= 5,
                                                            'is-empty':
                                                                Number(
                                                                    variant.available,
                                                                ) <= 0,
                                                        }"
                                                    >
                                                        <Icon
                                                            name="boxes"
                                                            :size="13"
                                                        />
                                                        {{
                                                            Number(
                                                                variant.available,
                                                            ) || 0
                                                        }}
                                                        available
                                                    </span>
                                                </span>
                                                <span
                                                    class="orders-product-option-price"
                                                >
                                                    <strong>{{
                                                        currency(variant.price)
                                                    }}</strong>
                                                    <small>Unit price</small>
                                                    <Icon
                                                        v-if="
                                                            String(
                                                                variant.id,
                                                            ) === lineVariantId
                                                        "
                                                        name="check-circle-2"
                                                        :size="17"
                                                    />
                                                </span>
                                            </button>
                                            <div
                                                v-if="!variants.length"
                                                class="orders-product-no-options"
                                            >
                                                <Icon
                                                    name="package-x"
                                                    :size="20"
                                                />
                                                No products are recorded in this location’s inventory.
                                            </div>
                                        </div>
                                    </label>
                                    <label>
                                        <span>Quantity <i>*</i></span>
                                        <input
                                            v-model="lineQuantity"
                                            type="number"
                                            min="0.0001"
                                            step="0.0001"
                                            inputmode="decimal"
                                            :disabled="!selectedVariant"
                                        />
                                    </label>
                                    <button
                                        class="orders-primary orders-add-item-button"
                                        @click="addLine"
                                        :disabled="!selectedVariant"
                                    >
                                        <Icon name="plus" :size="16" /> Add item
                                    </button>
                                </div>

                                <div class="orders-cart">
                                    <div
                                        v-if="!cart.length"
                                        class="orders-cart-empty"
                                    >
                                        <span class="orders-cart-empty-icon"
                                            ><Icon
                                                name="package-open"
                                                :size="24"
                                        /></span>
                                        <strong>Your order is empty</strong>
                                        <span
                                            >Select a product variant above, set
                                            the quantity, then click “Add
                                            item”.</span
                                        >
                                    </div>
                                    <div
                                        v-for="line in cart"
                                        :key="line.product_variant_id"
                                        class="orders-cart-line"
                                    >
                                        <span class="orders-cart-product-icon"
                                            ><Icon name="package" :size="17"
                                        /></span>
                                        <div class="orders-cart-product">
                                            <strong>{{ line.name }}</strong>
                                            <small
                                                >{{ line.sku || 'No SKU' }} ·
                                                {{ line.quantity }} ×
                                                {{
                                                    currency(line.price)
                                                }}</small
                                            >
                                        </div>
                                        <strong
                                            class="orders-cart-line-total"
                                            >{{
                                                currency(
                                                    line.quantity * line.price,
                                                )
                                            }}</strong
                                        >
                                        <button
                                            class="orders-remove-item"
                                            @click="
                                                removeLine(
                                                    line.product_variant_id,
                                                )
                                            "
                                            aria-label="Remove item"
                                            title="Remove item"
                                        >
                                            <Icon name="trash-2" :size="16" />
                                        </button>
                                    </div>
                                </div>

                                <div class="orders-total">
                                    <div>
                                        <span
                                            ><Icon name="receipt" :size="15" />
                                            Order subtotal</span
                                        >
                                        <small
                                            >Prices use the current product
                                            catalog.</small
                                        >
                                    </div>
                                    <strong>{{ currency(subtotal) }}</strong>
                                </div>
                            </section>
                        </div>

                        <footer
                            class="orders-dialog-footer orders-create-footer"
                        >
                            <div class="orders-create-footer-note">
                                <Icon name="shield-check" :size="16" />
                                <span
                                    >Stock is reserved automatically when the
                                    order is saved.</span
                                >
                            </div>
                            <div>
                                <button
                                    class="orders-secondary"
                                    @click="createOpen = false"
                                >
                                    <Icon name="x" :size="15" /> Cancel
                                </button>
                                <button
                                    class="orders-primary"
                                    :disabled="
                                        saving ||
                                        !cart.length ||
                                        !locationId ||
                                        !customerName.trim()
                                    "
                                    @click="createOrder"
                                >
                                    <Icon
                                        :name="
                                            saving
                                                ? 'loader-circle'
                                                : 'shield-check'
                                        "
                                        :size="16"
                                    />
                                    {{
                                        saving
                                            ? 'Creating order...'
                                            : 'Create order & reserve stock'
                                    }}
                                </button>
                            </div>
                        </footer>
                    </section>
                </div>
            </Teleport>
            <Teleport to="body">
                <div
                    v-if="detail || detailLoading"
                    class="orders-overlay orders-detail-overlay"
                    @click.self="detail = null"
                >
                    <section
                        class="orders-dialog orders-detail-dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="order-detail-title"
                    >
                        <header class="orders-detail-head">
                            <div class="orders-detail-heading">
                                <span class="orders-eyebrow"
                                    >ORDER WORKSPACE · #{{
                                        detail?.id ?? '—'
                                    }}</span
                                >
                                <h2 id="order-detail-title">
                                    {{
                                        detail?.order_number || 'Loading order…'
                                    }}
                                </h2>
                                <p>
                                    Complete order record, fulfillment, delivery
                                    and customer activity in one place.
                                </p>
                            </div>
                            <div
                                v-if="detail"
                                class="orders-detail-head-actions"
                            >
                                <span
                                    class="orders-status orders-detail-status"
                                    :class="`status-${detail.status}`"
                                    ><span class="orders-status-dot" />{{
                                        detail.status.replaceAll('_', ' ')
                                    }}</span
                                >
                                <button
                                    class="orders-close"
                                    @click="detail = null"
                                    aria-label="Close order details"
                                >
                                    ×
                                </button>
                            </div>
                            <button
                                v-else
                                class="orders-close"
                                @click="detail = null"
                                aria-label="Close order details"
                            >
                                ×
                            </button>
                        </header>
                        <div v-if="detailLoading" class="orders-detail-loading">
                            <span class="orders-spinner" /> Loading full order
                            record…
                        </div>
                        <div
                            v-else-if="detail"
                            class="orders-dialog-body orders-detail-body"
                        >
                            <section class="orders-detail-summary">
                                <div class="orders-detail-total">
                                    <span class="orders-summary-label"
                                        ><Icon name="wallet" :size="14" /> Order
                                        total</span
                                    ><strong>{{
                                        currency(detail.grand_total)
                                    }}</strong
                                    ><small
                                        >BDT ·
                                        {{ detail.items?.length || 0 }} line
                                        items</small
                                    >
                                </div>
                                <div class="orders-summary-stat">
                                    <span class="orders-summary-label"
                                        ><Icon name="user" :size="14" />
                                        Customer</span
                                    ><strong>{{ detail.customer_name }}</strong
                                    ><small>{{
                                        detail.customer_phone || 'No phone'
                                    }}</small>
                                </div>
                                <div class="orders-summary-stat">
                                    <span class="orders-summary-label"
                                        ><Icon name="file-check" :size="14" />
                                        Items fulfilled</span
                                    ><strong
                                        >{{
                                            (detail.items || [])
                                                .reduce(
                                                    (sum, item) =>
                                                        sum +
                                                        Number(
                                                            item.fulfilled_quantity ||
                                                                0,
                                                        ),
                                                    0,
                                                )
                                                .toLocaleString('en-BD', {
                                                    maximumFractionDigits: 4,
                                                })
                                        }}
                                        /
                                        {{
                                            (detail.items || [])
                                                .reduce(
                                                    (sum, item) =>
                                                        sum +
                                                        Number(
                                                            item.quantity || 0,
                                                        ),
                                                    0,
                                                )
                                                .toLocaleString('en-BD', {
                                                    maximumFractionDigits: 4,
                                                })
                                        }}</strong
                                    ><small
                                        >Prepared / handed over · ordered
                                        units</small
                                    >
                                </div>
                                <div class="orders-summary-stat">
                                    <span class="orders-summary-label"
                                        ><Icon name="clock" :size="14" />
                                        Created</span
                                    ><strong>{{
                                        date(detail.created_at)
                                    }}</strong
                                    ><small>{{
                                        detail.creator?.name
                                            ? `By ${detail.creator.name}`
                                            : 'Order creator'
                                    }}</small>
                                </div>
                            </section>

                            <section
                                class="orders-lifecycle-card"
                                aria-label="Order lifecycle overview"
                            >
                                <div class="orders-lifecycle-top">
                                    <div>
                                        <span class="orders-lifecycle-kicker"
                                            >LIFECYCLE OVERVIEW</span
                                        ><strong>Order progress</strong>
                                    </div>
                                    <span class="orders-lifecycle-current">{{
                                        detail.status.replaceAll('_', ' ')
                                    }}</span>
                                </div>
                                <div
                                    class="orders-lifecycle-steps orders-lifecycle-badges"
                                    :class="{
                                        'is-cancelled':
                                            detail.status === 'cancelled',
                                    }"
                                    aria-label="Order stages"
                                >
                                    <span
                                        v-for="(stage, index) in [
                                            {
                                                key: 'pending',
                                                label: 'Placed',
                                                icon: 'clipboard-check',
                                            },
                                            {
                                                key: 'confirmed',
                                                label: 'Confirmed',
                                                icon: 'badge-check',
                                            },
                                            {
                                                key: 'processing',
                                                label: 'Processing',
                                                icon: 'package',
                                            },
                                            {
                                                key: 'fulfilled',
                                                label: 'Fulfilled',
                                                icon: 'check-circle',
                                            },
                                        ]"
                                        :key="stage.key"
                                        class="orders-stage-badge"
                                        :class="{
                                            'is-reached':
                                                detail.status === stage.key ||
                                                (detail.status ===
                                                    'partially_fulfilled' &&
                                                    index <= 2) ||
                                                (detail.status ===
                                                    'processing' &&
                                                    index <= 2) ||
                                                (detail.status ===
                                                    'confirmed' &&
                                                    index <= 1) ||
                                                (detail.status ===
                                                    'fulfilled' &&
                                                    index <= 3),
                                            'is-current':
                                                detail.status === stage.key ||
                                                (detail.status ===
                                                    'partially_fulfilled' &&
                                                    stage.key === 'processing'),
                                        }"
                                    >
                                        <Icon
                                            :name="stage.icon"
                                            :size="14"
                                        /><span>{{ stage.label }}</span
                                        ><Icon
                                            v-if="
                                                detail.status === stage.key ||
                                                (detail.status ===
                                                    'partially_fulfilled' &&
                                                    stage.key === 'processing')
                                            "
                                            name="check"
                                            :size="12"
                                            class="orders-stage-active-check"
                                        />
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        detail.status === 'partially_fulfilled'
                                    "
                                    class="orders-lifecycle-alert is-info"
                                >
                                    <Icon name="truck" :size="15" /> Partially
                                    prepared: some units have been handed over;
                                    check the item quantities below.
                                </div>
                                <div
                                    v-if="detail.status === 'fulfilled'"
                                    class="orders-lifecycle-alert is-success"
                                >
                                    <Icon name="package-check" :size="15" /> All
                                    order quantities are recorded as fulfilled.
                                    This does not by itself confirm courier
                                    delivery to the customer.
                                </div>
                                <div class="orders-lifecycle-bottom">
                                    <span
                                        ><Icon name="clock" :size="14" />
                                        Created
                                        {{ date(detail.created_at) }}</span
                                    >
                                    <span
                                        ><Icon name="refresh-cw" :size="14" />
                                        Updated
                                        {{
                                            detail.updated_at
                                                ? date(detail.updated_at)
                                                : 'Not available'
                                        }}</span
                                    >
                                    <span v-if="detail.confirmed_at"
                                        ><Icon name="check-circle" :size="14" />
                                        Confirmed
                                        {{ date(detail.confirmed_at) }}</span
                                    >
                                    <span v-if="detail.cancelled_at"
                                        ><Icon name="x-circle" :size="14" />
                                        Cancelled
                                        {{ date(detail.cancelled_at) }}</span
                                    >
                                </div>
                                <div
                                    v-if="detail.status === 'cancelled'"
                                    class="orders-lifecycle-alert"
                                >
                                    <Icon name="alert-triangle" :size="15" />
                                    This order is cancelled. Review the
                                    cancellation reason and audit timeline
                                    below.
                                </div>
                                <div
                                    v-else-if="
                                        detail.status === 'partially_fulfilled'
                                    "
                                    class="orders-lifecycle-alert is-info"
                                >
                                    <Icon name="package-check" :size="15" />
                                    Partial fulfillment recorded. Check
                                    item-level remaining quantities before the
                                    next handover.
                                </div>
                            </section>

                            <div class="orders-detail-grid">
                                <section
                                    class="orders-detail-card orders-detail-customer"
                                >
                                    <div class="orders-section-heading">
                                        <span class="orders-section-icon"
                                            ><Icon name="user" :size="17"
                                        /></span>
                                        <div>
                                            <h3>Customer & Destination</h3>
                                            <p>
                                                Contact details captured on this
                                                order
                                            </p>
                                        </div>
                                    </div>
                                    <dl
                                        class="orders-info-grid orders-customer-info-grid"
                                    >
                                        <div class="orders-customer-primary">
                                            <dt>
                                                <Icon name="user" :size="12" />
                                                Customer
                                            </dt>
                                            <dd>
                                                {{
                                                    detail.customer_name ||
                                                    'Not recorded'
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>
                                                <Icon name="phone" :size="12" />
                                                Phone
                                            </dt>
                                            <dd>
                                                <a
                                                    v-if="detail.customer_phone"
                                                    :href="`tel:${detail.customer_phone}`"
                                                    >{{
                                                        detail.customer_phone
                                                    }}</a
                                                ><span
                                                    v-else
                                                    class="orders-field-missing"
                                                    >Not provided</span
                                                >
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>
                                                <Icon name="mail" :size="12" />
                                                Email
                                            </dt>
                                            <dd>
                                                <a
                                                    v-if="detail.customer_email"
                                                    :href="`mailto:${detail.customer_email}`"
                                                    >{{
                                                        detail.customer_email
                                                    }}</a
                                                ><span
                                                    v-else
                                                    class="orders-field-missing"
                                                    >Not provided</span
                                                >
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>
                                                <Icon
                                                    name="map-pin"
                                                    :size="12"
                                                />
                                                Fulfillment location
                                            </dt>
                                            <dd>
                                                {{
                                                    detail.location?.name ||
                                                    'Location #' +
                                                        (detail.location_id ??
                                                            '—')
                                                }}
                                                <small
                                                    v-if="detail.location?.code"
                                                    >·
                                                    {{
                                                        detail.location.code
                                                    }}</small
                                                >
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>
                                                <Icon
                                                    name="shopping-bag"
                                                    :size="12"
                                                />
                                                Order channel
                                            </dt>
                                            <dd>
                                                <span
                                                    class="orders-channel-chip"
                                                    >{{
                                                        (
                                                            detail.channel ||
                                                            'backoffice'
                                                        ).replaceAll('_', ' ')
                                                    }}</span
                                                >
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>
                                                <Icon name="clock" :size="12" />
                                                Last updated
                                            </dt>
                                            <dd>
                                                {{
                                                    detail.updated_at
                                                        ? date(
                                                              detail.updated_at,
                                                          )
                                                        : 'Not available'
                                                }}
                                            </dd>
                                        </div>
                                    </dl>
                                    <div
                                        v-if="detail.notes"
                                        class="orders-note-block"
                                    >
                                        <span
                                            ><Icon name="bookmark" :size="13" />
                                            Order notes / delivery
                                            instructions</span
                                        >
                                        <p>{{ detail.notes }}</p>
                                    </div>
                                    <div
                                        v-if="detail.cancellation_reason"
                                        class="orders-note-block orders-note-danger"
                                    >
                                        <span
                                            ><Icon
                                                name="alert-triangle"
                                                :size="13"
                                            />
                                            Cancellation reason</span
                                        >
                                        <p>{{ detail.cancellation_reason }}</p>
                                    </div>
                                </section>
                                <section
                                    class="orders-detail-card orders-detail-pricing"
                                >
                                    <div class="orders-section-heading">
                                        <span class="orders-section-icon"
                                            ><Icon name="receipt" :size="17"
                                        /></span>
                                        <div>
                                            <h3>Financial summary</h3>
                                            <p>Recorded order amounts</p>
                                        </div>
                                    </div>
                                    <dl class="orders-price-list">
                                        <div>
                                            <dt>Subtotal</dt>
                                            <dd>
                                                {{
                                                    currency(
                                                        detail.subtotal ??
                                                            (
                                                                detail.items ||
                                                                []
                                                            ).reduce(
                                                                (sum, item) =>
                                                                    sum +
                                                                    Number(
                                                                        item.line_total ||
                                                                            0,
                                                                    ),
                                                                0,
                                                            ),
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            v-if="
                                                Number(
                                                    detail.discount_total || 0,
                                                ) !== 0
                                            "
                                        >
                                            <dt>Discount</dt>
                                            <dd>
                                                −{{
                                                    currency(
                                                        detail.discount_total ||
                                                            0,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            v-if="
                                                Number(
                                                    detail.tax_total || 0,
                                                ) !== 0
                                            "
                                        >
                                            <dt>Tax</dt>
                                            <dd>
                                                {{
                                                    currency(
                                                        detail.tax_total || 0,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div
                                            v-if="
                                                Number(
                                                    detail.shipping_total || 0,
                                                ) !== 0
                                            "
                                        >
                                            <dt>Shipping</dt>
                                            <dd>
                                                {{
                                                    currency(
                                                        detail.shipping_total ||
                                                            0,
                                                    )
                                                }}
                                            </dd>
                                        </div>
                                        <div class="orders-price-grand">
                                            <dt>Grand total</dt>
                                            <dd>
                                                {{
                                                    currency(detail.grand_total)
                                                }}
                                            </dd>
                                        </div>
                                    </dl>
                                </section>
                            </div>

                            <section
                                class="orders-detail-card orders-detail-items"
                            >
                                <div
                                    class="orders-section-heading orders-items-heading"
                                >
                                    <span class="orders-section-icon"
                                        ><Icon name="package" :size="17"
                                    /></span>
                                    <div>
                                        <h3>Ordered items</h3>
                                        <p>
                                            Snapshot pricing and per-item
                                            fulfillment quantities
                                        </p>
                                    </div>
                                    <span class="orders-items-count"
                                        >{{
                                            detail.items?.length || 0
                                        }}
                                        items</span
                                    >
                                </div>
                                <div class="orders-detail-table-wrap">
                                    <table class="orders-detail-table">
                                        <thead>
                                            <tr>
                                                <th>Product / variant</th>
                                                <th>SKU</th>
                                                <th>Unit price</th>
                                                <th>Ordered</th>
                                                <th>Fulfilled</th>
                                                <th>Remaining</th>
                                                <th class="orders-align-right">
                                                    Line total
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="item in detail.items ||
                                                []"
                                                :key="item.id"
                                            >
                                                <td>
                                                    <strong
                                                        >{{ item.product_name
                                                        }}{{
                                                            item.variant_name
                                                                ? ` · ${item.variant_name}`
                                                                : ''
                                                        }}</strong
                                                    ><small
                                                        >Order line #{{
                                                            item.id
                                                        }}</small
                                                    >
                                                </td>
                                                <td>{{ item.sku || '—' }}</td>
                                                <td>
                                                    {{
                                                        currency(
                                                            item.unit_price,
                                                        )
                                                    }}
                                                </td>
                                                <td>
                                                    {{
                                                        Number(
                                                            item.quantity,
                                                        ).toLocaleString(
                                                            'en-BD',
                                                            {
                                                                maximumFractionDigits: 4,
                                                            },
                                                        )
                                                    }}
                                                </td>
                                                <td>
                                                    <span
                                                        class="orders-fulfilled-value"
                                                        >{{
                                                            Number(
                                                                item.fulfilled_quantity ||
                                                                    0,
                                                            ).toLocaleString(
                                                                'en-BD',
                                                                {
                                                                    maximumFractionDigits: 4,
                                                                },
                                                            )
                                                        }}</span
                                                    >
                                                </td>
                                                <td>
                                                    {{
                                                        Number(
                                                            item.remaining_quantity ??
                                                                Number(
                                                                    item.quantity,
                                                                ) -
                                                                    Number(
                                                                        item.fulfilled_quantity ||
                                                                            0,
                                                                    ),
                                                        ).toLocaleString(
                                                            'en-BD',
                                                            {
                                                                maximumFractionDigits: 4,
                                                            },
                                                        )
                                                    }}
                                                </td>
                                                <td class="orders-align-right">
                                                    <strong>{{
                                                        currency(
                                                            item.line_total,
                                                        )
                                                    }}</strong>
                                                </td>
                                            </tr>
                                            <tr v-if="!detail.items?.length">
                                                <td
                                                    colspan="7"
                                                    class="orders-empty"
                                                >
                                                    No item details returned for
                                                    this order.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section
                                class="orders-operations-insights"
                                aria-label="Operational order insights"
                            >
                                <article>
                                    <span class="orders-insight-icon"
                                        ><Icon name="package" :size="16"
                                    /></span>
                                    <div>
                                        <small>Units ordered</small
                                        ><strong>{{
                                            (detail.items || [])
                                                .reduce(
                                                    (sum, item) =>
                                                        sum +
                                                        Number(
                                                            item.quantity || 0,
                                                        ),
                                                    0,
                                                )
                                                .toLocaleString('en-BD', {
                                                    maximumFractionDigits: 4,
                                                })
                                        }}</strong
                                        ><span
                                            >Across
                                            {{ detail.items?.length || 0 }}
                                            order lines</span
                                        >
                                    </div>
                                </article>
                                <article>
                                    <span class="orders-insight-icon is-green"
                                        ><Icon name="file-check" :size="16"
                                    /></span>
                                    <div>
                                        <small>Units fulfilled</small
                                        ><strong>{{
                                            (detail.items || [])
                                                .reduce(
                                                    (sum, item) =>
                                                        sum +
                                                        Number(
                                                            item.fulfilled_quantity ||
                                                                0,
                                                        ),
                                                    0,
                                                )
                                                .toLocaleString('en-BD', {
                                                    maximumFractionDigits: 4,
                                                })
                                        }}</strong
                                        ><span
                                            >{{
                                                (detail.items || [])
                                                    .reduce(
                                                        (sum, item) =>
                                                            sum +
                                                            Number(
                                                                item.remaining_quantity ??
                                                                    Number(
                                                                        item.quantity ||
                                                                            0,
                                                                    ) -
                                                                        Number(
                                                                            item.fulfilled_quantity ||
                                                                                0,
                                                                        ),
                                                            ),
                                                        0,
                                                    )
                                                    .toLocaleString('en-BD', {
                                                        maximumFractionDigits: 4,
                                                    })
                                            }}
                                            units remaining</span
                                        >
                                    </div>
                                </article>
                                <article>
                                    <span class="orders-insight-icon is-blue"
                                        ><Icon name="truck" :size="16"
                                    /></span>
                                    <div>
                                        <small>Shipment records</small
                                        ><strong>{{
                                            detail.shipments?.length || 0
                                        }}</strong
                                        ><span>{{
                                            detail.shipments?.some(
                                                (shipment) =>
                                                    shipment.tracking_code,
                                            )
                                                ? 'Tracking information available'
                                                : 'No tracking code recorded'
                                        }}</span>
                                    </div>
                                </article>
                                <article>
                                    <span class="orders-insight-icon is-violet"
                                        ><Icon name="message-circle" :size="16"
                                    /></span>
                                    <div>
                                        <small>SMS activity</small
                                        ><strong>{{
                                            (
                                                detail.sms_logs ||
                                                detail.smsLogs ||
                                                []
                                            ).length
                                        }}</strong
                                        ><span
                                            >{{
                                                (
                                                    detail.sms_logs ||
                                                    detail.smsLogs ||
                                                    []
                                                ).filter(
                                                    (log) =>
                                                        (
                                                            log.status || ''
                                                        ).toLowerCase() ===
                                                            'sent' ||
                                                        (
                                                            log.status || ''
                                                        ).toLowerCase() ===
                                                            'delivered',
                                                ).length
                                            }}
                                            successful log(s)</span
                                        >
                                    </div>
                                </article>
                            </section>

                            <section
                                v-if="
                                    [
                                        'confirmed',
                                        'processing',
                                        'partially_fulfilled',
                                    ].includes(detail.status)
                                "
                                class="orders-detail-card orders-fulfillment orders-detail-action-card"
                            >
                                <div class="orders-section-heading">
                                    <span class="orders-section-icon"
                                        ><Icon name="truck" :size="17"
                                    /></span>
                                    <div>
                                        <h3>Prepare & hand over items</h3>
                                        <p>
                                            Records quantities packed/handed
                                            over and updates stock. Courier
                                            delivery is tracked separately.
                                        </p>
                                    </div>
                                </div>
                                <div
                                    v-for="item in detail.items || []"
                                    :key="`fulfill-${item.id}`"
                                    class="orders-fulfillment-line"
                                >
                                    <div>
                                        <strong
                                            >{{ item.product_name
                                            }}{{
                                                item.variant_name
                                                    ? ` · ${item.variant_name}`
                                                    : ''
                                            }}</strong
                                        ><small
                                            >Remaining:
                                            {{
                                                item.remaining_quantity ??
                                                item.quantity
                                            }}</small
                                        >
                                    </div>
                                    <label
                                        >Fulfill now<input
                                            v-model="
                                                fulfillmentQuantities[item.id]
                                            "
                                            type="number"
                                            min="0"
                                            :max="
                                                Number(
                                                    item.remaining_quantity ??
                                                        item.quantity,
                                                )
                                            "
                                            step="0.0001"
                                            :placeholder="
                                                String(
                                                    item.remaining_quantity ??
                                                        item.quantity,
                                                )
                                            "
                                            :disabled="
                                                Number(
                                                    item.remaining_quantity ??
                                                        item.quantity,
                                                ) <= 0
                                            "
                                    /></label>
                                </div>
                                <label class="orders-fulfillment-notes"
                                    >Fulfillment notes<textarea
                                        v-model="fulfillmentNotes"
                                        rows="2"
                                        maxlength="2000"
                                        placeholder="Packing reference, handover details, or notes…"
                                    /></label
                                ><button
                                    class="orders-primary"
                                    :disabled="saving"
                                    @click="fulfillOrder"
                                >
                                    <Icon name="check-circle" :size="16" />{{
                                        saving
                                            ? 'Recording…'
                                            : 'Record handover & update stock'
                                    }}
                                </button>
                            </section>

                            <div class="orders-detail-grid">
                                <section class="orders-detail-card">
                                    <div class="orders-section-heading">
                                        <span class="orders-section-icon"
                                            ><Icon name="activity" :size="17"
                                        /></span>
                                        <div>
                                            <h3>Order timeline</h3>
                                            <p>
                                                Status changes and audit trail
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        v-if="
                                            (
                                                detail.status_history ||
                                                detail.statusHistory ||
                                                []
                                            ).length
                                        "
                                        class="orders-timeline"
                                    >
                                        <div
                                            v-for="event in detail.status_history ||
                                            detail.statusHistory ||
                                            []"
                                            :key="event.id"
                                            class="orders-timeline-item"
                                        >
                                            <span class="orders-timeline-dot" />
                                            <div>
                                                <strong>{{
                                                    (
                                                        event.to_status ||
                                                        event.status ||
                                                        'Order updated'
                                                    ).replaceAll('_', ' ')
                                                }}</strong>
                                                <p v-if="event.from_status">
                                                    {{
                                                        event.from_status.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )
                                                    }}
                                                    →
                                                    {{
                                                        (
                                                            event.to_status ||
                                                            event.status ||
                                                            'updated'
                                                        ).replaceAll('_', ' ')
                                                    }}
                                                </p>
                                                <p
                                                    v-if="
                                                        event.note ||
                                                        event.reason
                                                    "
                                                >
                                                    {{
                                                        event.note ||
                                                        event.reason
                                                    }}
                                                </p>
                                                <small
                                                    >{{
                                                        event.actor?.name ||
                                                        'System'
                                                    }}<template
                                                        v-if="event.created_at"
                                                    >
                                                        ·
                                                        {{
                                                            date(
                                                                event.created_at,
                                                            )
                                                        }}</template
                                                    ></small
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="orders-detail-empty">
                                        No status history is available for this
                                        order.
                                    </div>
                                </section>
                                <section class="orders-detail-card">
                                    <div class="orders-section-heading">
                                        <span class="orders-section-icon"
                                            ><Icon name="layers" :size="17"
                                        /></span>
                                        <div>
                                            <h3>
                                                Preparation & handover history
                                            </h3>
                                            <p>
                                                Recorded item packing and stock
                                                handover events—not proof of
                                                customer delivery
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        v-if="
                                            (detail.fulfillments || []).length
                                        "
                                        class="orders-record-list"
                                    >
                                        <article
                                            v-for="fulfillment in detail.fulfillments ||
                                            []"
                                            :key="fulfillment.id"
                                        >
                                            <div>
                                                <strong>{{
                                                    fulfillment.reference ||
                                                    `Fulfillment #${fulfillment.id}`
                                                }}</strong
                                                ><span
                                                    class="orders-mini-status"
                                                    >{{
                                                        fulfillment.status ||
                                                        'recorded'
                                                    }}</span
                                                >
                                            </div>
                                            <p v-if="fulfillment.notes">
                                                {{ fulfillment.notes }}
                                            </p>
                                            <small
                                                >{{
                                                    fulfillment.actor?.name ||
                                                    'Operator'
                                                }}<template
                                                    v-if="
                                                        fulfillment.fulfilled_at ||
                                                        fulfillment.created_at
                                                    "
                                                >
                                                    ·
                                                    {{
                                                        date(
                                                            fulfillment.fulfilled_at ||
                                                                fulfillment.created_at ||
                                                                detail.created_at,
                                                        )
                                                    }}</template
                                                ></small
                                            >
                                            <ul
                                                v-if="fulfillment.items?.length"
                                            >
                                                <li
                                                    v-for="line in fulfillment.items"
                                                    :key="line.id"
                                                >
                                                    {{
                                                        line.order_item
                                                            ?.product_name ||
                                                        line.orderItem
                                                            ?.product_name ||
                                                        'Order item'
                                                    }}
                                                    · {{ line.quantity }} units
                                                </li>
                                            </ul>
                                        </article>
                                    </div>
                                    <div v-else class="orders-detail-empty">
                                        No fulfillment events recorded yet.
                                    </div>
                                </section>
                            </div>

                            <section class="orders-detail-card">
                                <div class="orders-section-heading">
                                    <span class="orders-section-icon"
                                        ><Icon name="shield-check" :size="17"
                                    /></span>
                                    <div>
                                        <h3>Fraud & customer verification</h3>
                                        <p>
                                            Risk signals are advisory; review
                                            decisions are preserved
                                        </p>
                                    </div>
                                    <button
                                        class="orders-secondary orders-section-action"
                                        :disabled="riskLoading"
                                        @click="assessRisk"
                                    >
                                        {{
                                            riskLoading
                                                ? 'Assessing…'
                                                : 'Run assessment'
                                        }}
                                    </button>
                                </div>
                                <div
                                    v-if="
                                        (
                                            detail.risk_assessments ||
                                            detail.riskAssessments ||
                                            []
                                        ).length
                                    "
                                    class="orders-record-grid"
                                >
                                    <article
                                        v-for="risk in detail.risk_assessments ||
                                        detail.riskAssessments ||
                                        []"
                                        :key="risk.id"
                                        class="orders-risk-card"
                                    >
                                        <div class="orders-record-top">
                                            <strong
                                                >Risk {{ risk.level }} ·
                                                {{ risk.score }}/100</strong
                                            ><span class="orders-mini-status">{{
                                                risk.decision
                                            }}</span>
                                        </div>
                                        <ul>
                                            <li
                                                v-for="signal in risk.signals ||
                                                []"
                                                :key="signal.code"
                                            >
                                                {{ signal.message }}
                                                <small
                                                    >(+{{
                                                        signal.points
                                                    }})</small
                                                >
                                            </li>
                                        </ul>
                                        <p
                                            v-if="risk.review_note"
                                            class="orders-review-note"
                                        >
                                            Review note: {{ risk.review_note }}
                                        </p>
                                        <div
                                            v-if="
                                                [
                                                    'manual_review',
                                                    'reject',
                                                ].includes(risk.decision) ||
                                                risk.decision === 'verify'
                                            "
                                            class="orders-risk-actions"
                                        >
                                            <button
                                                class="orders-secondary"
                                                @click="
                                                    reviewRisk(risk, 'allow')
                                                "
                                            >
                                                Approve order</button
                                            ><button
                                                class="orders-secondary"
                                                :disabled="
                                                    !detail.customer_phone
                                                "
                                                @click="requestPhoneOtp"
                                            >
                                                Verify phone</button
                                            ><button
                                                class="orders-danger"
                                                @click="
                                                    reviewRisk(
                                                        risk,
                                                        'manual_review',
                                                    )
                                                "
                                            >
                                                Keep on hold
                                            </button>
                                        </div>
                                    </article>
                                </div>
                                <div v-else class="orders-detail-empty">
                                    Risk assessment has not been run.
                                </div>
                                <div class="orders-detail-inline-actions">
                                    <button
                                        class="orders-secondary"
                                        :disabled="!detail.customer_phone"
                                        @click="sendOrderSms"
                                    >
                                        <Icon
                                            name="message-circle"
                                            :size="15"
                                        />
                                        Send customer SMS</button
                                    ><span
                                        v-if="!detail.customer_phone"
                                        class="orders-inline-hint"
                                        >Add a customer phone number to enable
                                        SMS.</span
                                    >
                                </div>
                            </section>

                            <section
                                v-if="
                                    [
                                        'confirmed',
                                        'processing',
                                        'partially_fulfilled',
                                        'fulfilled',
                                    ].includes(detail.status) ||
                                    (detail.shipments || []).length
                                "
                                class="orders-detail-card orders-fulfillment orders-detail-action-card"
                            >
                                <div class="orders-section-heading">
                                    <span class="orders-section-icon"
                                        ><Icon name="truck" :size="17"
                                    /></span>
                                    <div>
                                        <h3>
                                            Courier integration & delivery
                                            tracking
                                        </h3>
                                        <p>
                                            Prepare a shipment request, record
                                            provider response, and follow
                                            tracking status. Live actions
                                            require configured API credentials.
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="orders-detail-grid orders-shipment-grid"
                                >
                                    <div class="orders-shipment-form">
                                        <label
                                            ><span
                                                ><Icon
                                                    name="truck"
                                                    :size="12"
                                                />
                                                Courier provider</span
                                            ><select v-model="courierProvider">
                                                <option value="steadfast">
                                                    Steadfast
                                                </option>
                                                <option value="redx">
                                                    REDX
                                                </option>
                                                <option value="pathao">
                                                    Pathao
                                                </option>
                                                <option value="carrybee">
                                                    Carrybee (configured API)
                                                </option>
                                                <option value="custom">
                                                    Other configured courier
                                                </option>
                                            </select></label
                                        ><label
                                            ><span
                                                ><Icon
                                                    name="map-pin"
                                                    :size="12"
                                                />
                                                Recipient delivery address</span
                                            ><textarea
                                                v-model="courierAddress"
                                                rows="2"
                                                maxlength="500"
                                                placeholder="House, road, area, district"
                                            /></label
                                        ><label
                                            ><span
                                                ><Icon
                                                    name="wallet"
                                                    :size="12"
                                                />
                                                Cash on delivery amount
                                                (BDT)</span
                                            ><input
                                                v-model="courierCod"
                                                type="number"
                                                min="0"
                                                :max="
                                                    Number(detail.grand_total)
                                                "
                                                step="0.01"
                                        /></label>
                                        <div class="orders-courier-checklist">
                                            <span
                                                ><Icon
                                                    name="check-circle-2"
                                                    :size="13"
                                                />
                                                Recipient address required</span
                                            ><span
                                                ><Icon
                                                    name="banknote"
                                                    :size="13"
                                                />
                                                COD:
                                                {{
                                                    currency(courierCod || 0)
                                                }}</span
                                            ><span
                                                ><Icon
                                                    name="key-round"
                                                    :size="13"
                                                />
                                                Provider credentials
                                                required</span
                                            >
                                        </div>
                                        <button
                                            class="orders-primary"
                                            :disabled="
                                                saving ||
                                                courierAddress.trim().length <
                                                    8 ||
                                                !detail.customer_phone
                                            "
                                            @click="bookCourier"
                                        >
                                            <Icon name="send" :size="15" />
                                            {{
                                                saving
                                                    ? 'Submitting booking…'
                                                    : 'Submit shipment booking'
                                            }}</button
                                        ><small
                                            >Booking response, tracking ID and
                                            provider errors appear in shipment
                                            records below. This UI does not
                                            imply an API is live until
                                            credentials and provider integration
                                            are configured.</small
                                        >
                                    </div>
                                    <div class="orders-shipment-list">
                                        <article
                                            v-for="shipment in detail.shipments ||
                                            []"
                                            :key="shipment.id"
                                            class="orders-shipment-card"
                                        >
                                            <div class="orders-record-top">
                                                <strong
                                                    >{{
                                                        shipment.provider
                                                    }}
                                                    shipment</strong
                                                ><span
                                                    class="orders-mini-status"
                                                    >{{ shipment.status }}</span
                                                >
                                            </div>
                                            <dl class="orders-info-grid">
                                                <div>
                                                    <dt>Tracking code</dt>
                                                    <dd>
                                                        {{
                                                            shipment.tracking_code ||
                                                            'Pending provider response'
                                                        }}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt>COD amount</dt>
                                                    <dd>
                                                        {{
                                                            currency(
                                                                shipment.cod_amount,
                                                            )
                                                        }}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt>Recipient address</dt>
                                                    <dd>
                                                        {{
                                                            shipment.recipient_address ||
                                                            '—'
                                                        }}
                                                    </dd>
                                                </div>
                                            </dl>
                                            <p
                                                v-if="shipment.last_error"
                                                class="orders-shipment-error"
                                            >
                                                {{ shipment.last_error }}
                                            </p>
                                        </article>
                                        <div
                                            v-if="!detail.shipments?.length"
                                            class="orders-detail-empty"
                                        >
                                            No shipment has been booked yet.
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section
                                class="orders-detail-card orders-sms-workspace"
                            >
                                <div class="orders-section-heading">
                                    <span class="orders-section-icon"
                                        ><Icon name="history" :size="17"
                                    /></span>
                                    <div>
                                        <h3>Customer SMS & message history</h3>
                                        <p>
                                            Choose a notification event, review
                                            its preview, then send using the
                                            configured SMS provider template.
                                        </p>
                                    </div>
                                    <span class="orders-items-count"
                                        >{{
                                            (
                                                detail.sms_logs ||
                                                detail.smsLogs ||
                                                []
                                            ).length
                                        }}
                                        log entries</span
                                    >
                                </div>
                                <div class="orders-sms-composer">
                                    <div class="orders-sms-composer-top">
                                        <div class="orders-sms-recipient">
                                            <span class="orders-sms-avatar"
                                                ><Icon name="user" :size="16"
                                            /></span>
                                            <div>
                                                <strong>{{
                                                    detail.customer_name
                                                }}</strong
                                                ><small>{{
                                                    detail.customer_phone ||
                                                    'No phone number on this order'
                                                }}</small>
                                            </div>
                                        </div>
                                        <span class="orders-sms-provider"
                                            ><Icon
                                                name="shield-check"
                                                :size="13"
                                            />
                                            Server-side template</span
                                        >
                                    </div>
                                    <div class="orders-sms-compose-fields">
                                        <label
                                            >Notification type<select
                                                v-model="smsEvent"
                                            >
                                                <option value="confirmed">
                                                    Order confirmed
                                                </option>
                                                <option value="processing">
                                                    Order is being prepared
                                                </option>
                                                <option value="delivered">
                                                    Order delivered
                                                </option>
                                                <option value="cancelled">
                                                    Order cancelled
                                                </option>
                                            </select></label
                                        ><label
                                            >Message preview<textarea
                                                v-model="smsDraft"
                                                rows="3"
                                                maxlength="500"
                                                :placeholder="smsPreview"
                                            />
                                        </label>
                                    </div>
                                    <div class="orders-sms-preview-note">
                                        <Icon
                                            name="info-circle"
                                            :size="14"
                                        /><span>{{
                                            smsDraft.trim()
                                                ? 'This is a local draft preview. The current SMS API accepts an event key and may send its configured server-side template; this draft is not transmitted as custom message text.'
                                                : smsPreview
                                        }}</span>
                                    </div>
                                    <div class="orders-sms-compose-actions">
                                        <span
                                            ><Icon name="phone" :size="13" />
                                            Recipient:
                                            {{
                                                detail.customer_phone ||
                                                'Unavailable'
                                            }}</span
                                        ><button
                                            class="orders-primary"
                                            :disabled="
                                                !detail.customer_phone || saving
                                            "
                                            @click="sendOrderSms"
                                        >
                                            <Icon name="send" :size="15" />
                                            {{
                                                saving
                                                    ? 'Sending…'
                                                    : 'Send selected SMS event'
                                            }}
                                        </button>
                                    </div>
                                </div>
                                <div class="orders-sms-history-head">
                                    <strong
                                        ><Icon name="history" :size="15" />
                                        Delivery activity</strong
                                    ><span>Most recent service records</span>
                                </div>
                                <div
                                    v-if="
                                        (
                                            detail.sms_logs ||
                                            detail.smsLogs ||
                                            []
                                        ).length
                                    "
                                    class="orders-sms-log-list"
                                >
                                    <article
                                        v-for="log in [
                                            ...(detail.sms_logs ||
                                                detail.smsLogs ||
                                                []),
                                        ].reverse()"
                                        :key="log.id"
                                        class="orders-sms-log-card"
                                    >
                                        <span
                                            class="orders-sms-log-icon"
                                            :class="{
                                                'is-error':
                                                    /fail|error|reject/i.test(
                                                        log.status || '',
                                                    ) || !!log.error_message,
                                                'is-success':
                                                    /sent|success|deliver/i.test(
                                                        log.status || '',
                                                    ) && !log.error_message,
                                            }"
                                            ><Icon
                                                :name="
                                                    /fail|error|reject/i.test(
                                                        log.status || '',
                                                    ) || !!log.error_message
                                                        ? 'alert-circle'
                                                        : /sent|success|deliver/i.test(
                                                                log.status ||
                                                                    '',
                                                            )
                                                          ? 'check-circle'
                                                          : 'message-circle'
                                                "
                                                :size="16"
                                        /></span>
                                        <div class="orders-sms-log-content">
                                            <div class="orders-sms-log-title">
                                                <strong>{{
                                                    (
                                                        log.event || 'SMS event'
                                                    ).replaceAll('_', ' ')
                                                }}</strong
                                                ><span
                                                    class="orders-mini-status"
                                                    >{{
                                                        log.status ||
                                                        'Unknown status'
                                                    }}</span
                                                >
                                            </div>
                                            <p>
                                                {{
                                                    log.error_message ||
                                                    log.message ||
                                                    'No message body returned by provider.'
                                                }}
                                            </p>
                                            <div class="orders-sms-log-meta">
                                                <span
                                                    ><Icon
                                                        name="phone"
                                                        :size="11"
                                                    />
                                                    {{
                                                        log.recipient ||
                                                        log.phone ||
                                                        detail.customer_phone ||
                                                        'Unknown recipient'
                                                    }}</span
                                                ><span
                                                    ><Icon
                                                        name="radio-tower"
                                                        :size="11"
                                                    />
                                                    {{
                                                        log.provider ||
                                                        'Provider not reported'
                                                    }}</span
                                                ><span
                                                    ><Icon
                                                        name="clock-3"
                                                        :size="11"
                                                    />
                                                    {{
                                                        log.created_at
                                                            ? date(
                                                                  log.created_at,
                                                              )
                                                            : 'Time unavailable'
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </article>
                                </div>
                                <div v-else class="orders-detail-empty">
                                    <Icon
                                        name="messages-square"
                                        :size="22"
                                    /><strong>No messages sent yet</strong
                                    ><span
                                        >Choose a notification type above to
                                        start a message event.</span
                                    >
                                </div>
                            </section>
                        </div>
                        <footer
                            class="orders-dialog-footer orders-detail-footer"
                        >
                            <span
                                >Order #{{ detail?.id ?? '—' }} · Data is loaded
                                from the secured order record.</span
                            >
                            <div>
                                <button
                                    v-if="detail?.status === 'pending'"
                                    class="orders-primary"
                                    :disabled="saving"
                                    @click="confirmOrder(detail)"
                                >
                                    <Icon name="check" :size="15" /> Confirm
                                    order</button
                                ><button
                                    v-if="
                                        detail &&
                                        ['pending', 'confirmed'].includes(
                                            detail.status,
                                        )
                                    "
                                    class="orders-danger"
                                    @click="
                                        cancelTarget = detail;
                                        cancelReason = '';
                                    "
                                >
                                    <Icon name="x" :size="15" /> Cancel order</button
                                ><button
                                    class="orders-secondary"
                                    @click="detail = null"
                                >
                                    Close details
                                </button>
                            </div>
                        </footer>
                    </section>
                </div>
            </Teleport>
            <div
                v-if="cancelTarget"
                class="orders-overlay"
                @click.self="cancelTarget = null"
            >
                <section
                    class="orders-dialog orders-cancel-dialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="cancel-order-title"
                >
                    <header class="orders-dialog-head">
                        <div>
                            <span class="orders-eyebrow"
                                >CONTROLLED ACTION</span
                            >
                            <h2 id="cancel-order-title">
                                Cancel {{ cancelTarget.order_number }}?
                            </h2>
                            <p>Active stock reservations will be released.</p>
                        </div>
                        <button
                            class="orders-close"
                            @click="cancelTarget = null"
                            aria-label="Close"
                        >
                            ×
                        </button>
                    </header>
                    <div class="orders-dialog-body">
                        <label
                            >Cancellation reason
                            <textarea
                                v-model="cancelReason"
                                rows="4"
                                minlength="3"
                                maxlength="1000"
                                placeholder="Explain why this order is being cancelled…"
                            />
                        </label>
                    </div>
                    <footer class="orders-dialog-footer">
                        <span>This action is recorded in order history.</span>
                        <div>
                            <button
                                class="orders-secondary"
                                @click="cancelTarget = null"
                            >
                                Keep order</button
                            ><button
                                class="orders-danger"
                                :disabled="
                                    saving || cancelReason.trim().length < 3
                                "
                                @click="cancelOrder"
                            >
                                {{
                                    saving
                                        ? 'Cancelling…'
                                        : 'Confirm cancellation'
                                }}
                            </button>
                        </div>
                    </footer>
                </section>
            </div>
        </main>
    </ErpLayout>
</template>

<style scoped>
.orders-page {
    max-width: 1600px;
    margin: 0 auto;
    padding: clamp(18px, 2.2vw, 32px);
    color: var(--foreground, #172033);
    display: grid;
    gap: 20px;
}
.orders-hero {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}
.orders-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--muted-foreground, #788397);
    font-size: 12px;
    margin-bottom: 14px;
}
.orders-breadcrumb a {
    color: inherit;
    text-decoration: none;
}
.orders-title {
    display: flex;
    align-items: center;
    gap: 14px;
}
.orders-title-icon {
    width: 50px;
    height: 50px;
    display: grid;
    place-items: center;
    border: 1px solid #d8e4ff;
    border-radius: 16px;
    background: linear-gradient(145deg, #f0f5ff, #e7eeff);
    color: #3159c9;
}
.orders-title h1 {
    font-size: clamp(24px, 2.2vw, 32px);
    font-weight: 750;
    letter-spacing: -0.04em;
    margin: 0;
}
.orders-title p,
.orders-panel-head p,
.orders-dialog-head p {
    color: var(--muted-foreground, #788397);
    font-size: 13px;
    margin: 5px 0 0;
}
.orders-primary,
.orders-secondary,
.orders-danger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-radius: 11px;
    min-height: 42px;
    padding: 0 15px;
    font-size: 13px;
    font-weight: 650;
    border: 1px solid transparent;
    cursor: pointer;
    transition: 0.15s ease;
}
.orders-primary {
    background: linear-gradient(135deg, #3159c9, #4777e8);
    color: white;
    box-shadow: 0 6px 16px #3159c926;
}
.orders-primary:hover:not(:disabled) {
    filter: brightness(1.05);
    transform: translateY(-1px);
}
.orders-secondary {
    background: var(--card, #fff);
    border-color: var(--border, #dfe5ef);
    color: var(--foreground, #172033);
}
.orders-danger {
    background: #b42318;
    color: white;
}
.orders-primary:disabled,
.orders-secondary:disabled,
.orders-danger:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.orders-notice,
.orders-error {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 13px;
}
.orders-notice {
    background: #ecfdf3;
    color: #067647;
    border: 1px solid #abefc6;
}
.orders-error {
    background: #fff1f0;
    color: #b42318;
    border: 1px solid #fecdca;
}
.orders-notice span,
.orders-error span {
    flex: 1;
}
.orders-notice button,
.orders-error button {
    border: 0;
    background: transparent;
    color: inherit;
    font-size: 20px;
    cursor: pointer;
}
.orders-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}
.orders-metrics article {
    display: grid;
    gap: 8px;
    padding: 18px 20px;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 16px;
    background: var(--card, #fff);
    box-shadow: 0 2px 8px #10204005;
}
.metric-label,
.metric-foot {
    color: var(--muted-foreground, #788397);
    font-size: 12px;
}
.orders-metrics article > strong {
    font-size: 27px;
    letter-spacing: -0.04em;
}
.metric-workflow {
    font-size: 18px !important;
}
.metric-foot {
    font-size: 11px;
}
.orders-panel {
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 18px;
    background: var(--card, #fff);
    overflow: hidden;
    box-shadow: 0 8px 24px #10204006;
}
.orders-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 20px 22px;
    border-bottom: 1px solid var(--border, #e8edf4);
}
.orders-panel-head h2 {
    font-size: 17px;
    font-weight: 700;
    margin: 0;
}
.orders-count {
    font-size: 11px;
    padding: 6px 9px;
    border-radius: 999px;
    background: var(--muted, #f3f5f9);
    color: var(--muted-foreground, #68758b);
}
.orders-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 22px;
    border-bottom: 1px solid var(--border, #e8edf4);
}
.orders-search {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 180px;
    height: 42px;
    padding: 0 12px;
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 11px;
    color: #788397;
}
.orders-search input {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--foreground, #172033);
    font-size: 13px;
}
.orders-filter {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 12px;
    color: var(--muted-foreground, #788397);
}
.orders-filter select,
.orders-form-grid input,
.orders-form-grid select,
.orders-form-grid textarea,
.orders-add-line select,
.orders-add-line input,
.orders-dialog-body textarea {
    width: 100%;
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 10px;
    background: var(--background, #fff);
    color: var(--foreground, #172033);
    padding: 10px 11px;
    font: inherit;
    font-size: 13px;
    outline: 0;
}
.orders-filter select {
    min-width: 145px;
}
.orders-table-wrap {
    overflow: auto;
}
.orders-table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}
.orders-table th {
    text-align: left;
    padding: 12px 16px;
    background: var(--muted, #f8fafc);
    color: var(--muted-foreground, #788397);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
}
.orders-table td {
    padding: 14px 16px;
    border-top: 1px solid var(--border, #edf0f5);
    font-size: 12px;
}
.orders-table tbody tr:hover {
    background: color-mix(in srgb, var(--muted, #f8fafc) 65%, transparent);
}
.orders-table td:first-child small,
.orders-table td:nth-child(2) small {
    display: block;
    color: var(--muted-foreground, #788397);
    font-size: 10px;
    margin-top: 4px;
}
.orders-number {
    padding: 0;
    border: 0;
    background: transparent;
    color: #3159c9;
    font-weight: 750;
    cursor: pointer;
}
.orders-money {
    font-weight: 750;
    font-variant-numeric: tabular-nums;
}
.orders-status {
    display: inline-flex;
    padding: 5px 8px;
    border-radius: 999px;
    font-size: 10px;
    text-transform: capitalize;
    font-weight: 750;
}
.status-pending {
    background: #fff4d6;
    color: #935c00;
}
.status-confirmed {
    background: #e8f2ff;
    color: #2458b8;
}
.status-processing {
    background: #e8f2ff;
    color: #2458b8;
}
.status-partially_fulfilled {
    background: #f3e8ff;
    color: #6b21a8;
}
.status-fulfilled {
    background: #dcfce7;
    color: #166534;
}
.status-cancelled {
    background: #feeceb;
    color: #b42318;
}
.orders-row-actions {
    display: flex;
    gap: 5px;
}
.orders-row-actions button {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border: 1px solid var(--border, #e1e6ef);
    border-radius: 8px;
    background: var(--card, #fff);
    color: var(--muted-foreground, #68758b);
    cursor: pointer;
}
.orders-row-actions button:hover {
    color: #3159c9;
    border-color: #b8caff;
}
.orders-empty {
    height: 140px;
    text-align: center;
    color: var(--muted-foreground, #788397);
}
.orders-empty > * {
    display: block;
    margin: 5px auto;
}
.orders-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-top: 1px solid var(--border, #e8edf4);
    font-size: 12px;
    color: var(--muted-foreground, #788397);
}
.orders-pagination div {
    display: flex;
    gap: 8px;
}
.orders-pagination button {
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 9px;
    background: var(--card, #fff);
    padding: 8px 11px;
    color: var(--foreground, #172033);
    cursor: pointer;
}
.orders-pagination button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.orders-overlay {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: #07101f9c;
    backdrop-filter: blur(8px);
}
.orders-dialog {
    width: min(620px, 100%);
    max-height: min(90dvh, 900px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 20px;
    background: var(--card, #fff);
    box-shadow: 0 30px 100px #02081750;
}
.orders-create-dialog {
    width: min(860px, 100%);
}
.orders-dialog-head {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 22px 24px;
    border-bottom: 1px solid var(--border, #e8edf4);
    background: linear-gradient(
        125deg,
        color-mix(in srgb, var(--card, #fff) 92%, #dce7ff),
        var(--card, #fff) 65%
    );
}
.orders-eyebrow {
    font-size: 9px;
    letter-spacing: 0.14em;
    color: #5271bd;
    font-weight: 800;
}
.orders-dialog-head h2 {
    font-size: 23px;
    letter-spacing: -0.035em;
    margin: 5px 0 0;
}
.orders-close {
    width: 34px;
    height: 34px;
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 10px;
    background: var(--card, #fff);
    font-size: 22px;
    cursor: pointer;
}
.orders-dialog-body {
    padding: 22px 24px;
    overflow: auto;
    min-height: 0;
}
.orders-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}
.orders-form-grid label,
.orders-add-line label,
.orders-dialog-body > label {
    display: grid;
    gap: 7px;
    font-size: 12px;
    font-weight: 650;
}
.orders-form-wide {
    grid-column: 1/-1;
}
.orders-form-grid textarea,
.orders-dialog-body textarea {
    resize: vertical;
}
.orders-add-line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 100px auto;
    align-items: end;
    gap: 10px;
    padding: 17px 0;
    border-top: 1px solid var(--border, #e8edf4);
    margin-top: 18px;
}
.orders-cart {
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 12px;
    overflow: hidden;
}
.orders-cart-empty {
    padding: 20px;
    text-align: center;
    color: var(--muted-foreground, #788397);
    font-size: 12px;
}
.orders-cart-line {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--border, #edf0f5);
}
.orders-cart-line:last-child {
    border-bottom: 0;
}
.orders-cart-line > div {
    flex: 1;
    min-width: 0;
}
.orders-cart-line strong,
.orders-cart-line small {
    display: block;
}
.orders-cart-line small {
    font-size: 11px;
    color: var(--muted-foreground, #788397);
    margin-top: 4px;
}
.orders-cart-line > button {
    border: 0;
    background: transparent;
    color: #b42318;
    font-size: 20px;
    cursor: pointer;
}
.orders-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 2px;
    font-size: 13px;
}
.orders-total strong,
.orders-detail-meta strong {
    font-size: 22px;
    letter-spacing: -0.03em;
}
.orders-dialog-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 15px 24px;
    border-top: 1px solid var(--border, #e8edf4);
    background: color-mix(in srgb, var(--card, #fff) 94%, transparent);
}
.orders-dialog-footer > span {
    font-size: 10px;
    color: var(--muted-foreground, #788397);
}
.orders-dialog-footer > div {
    display: flex;
    gap: 8px;
}
.orders-detail-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.orders-fulfillment {
    display: grid;
    gap: 10px;
    margin-top: 20px;
    padding: 16px;
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 14px;
    background: var(--muted, #f8fafc);
}
.orders-fulfillment h3 {
    margin: 0;
    font-size: 15px;
}
.orders-fulfillment p {
    margin: 0;
    color: var(--muted-foreground, #788397);
    font-size: 12px;
}
.orders-fulfillment-line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 130px;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-top: 1px solid var(--border, #e2e8f0);
}
.orders-fulfillment-line strong,
.orders-fulfillment-line small {
    display: block;
}
.orders-fulfillment-line small {
    margin-top: 4px;
    color: var(--muted-foreground, #788397);
    font-size: 11px;
}
.orders-fulfillment-line label,
.orders-fulfillment-notes {
    display: grid;
    gap: 6px;
    font-size: 11px;
    font-weight: 650;
}
.orders-fulfillment input,
.orders-fulfillment textarea {
    width: 100%;
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 9px;
    padding: 9px;
    background: var(--background, #fff);
    color: var(--foreground, #172033);
    font: inherit;
}
.orders-fulfillment .orders-primary {
    justify-self: start;
}
.orders-risk-card,
.orders-shipment-card {
    display: grid;
    gap: 7px;
    padding: 12px;
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 11px;
    background: var(--card, #fff);
    font-size: 12px;
}
.orders-risk-card ul {
    margin: 0;
    padding-left: 18px;
    color: var(--muted-foreground, #788397);
}
.orders-risk-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}
.orders-fulfillment > label {
    display: grid;
    gap: 6px;
    font-size: 12px;
    font-weight: 650;
}
.orders-fulfillment select,
.orders-fulfillment input,
.orders-fulfillment textarea {
    width: 100%;
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 9px;
    padding: 9px;
    background: var(--background, #fff);
    color: var(--foreground, #172033);
    font: inherit;
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
@media (max-width: 760px) {
    .orders-page {
        padding: 15px;
        gap: 14px;
    }
    .orders-metrics {
        grid-template-columns: 1fr;
    }
    .orders-metrics article {
        padding: 14px 16px;
    }
    .orders-toolbar {
        align-items: stretch;
        flex-direction: column;
        padding: 13px;
    }
    .orders-filter {
        justify-content: space-between;
    }
    .orders-filter select {
        flex: 1;
    }
    .orders-panel-head {
        padding: 16px;
    }
    .orders-dialog-head,
    .orders-dialog-body {
        padding: 17px;
    }
    .orders-form-grid {
        grid-template-columns: 1fr;
    }
    .orders-form-wide {
        grid-column: auto;
    }
    .orders-add-line {
        grid-template-columns: minmax(0, 1fr) 85px;
    }
    .orders-add-line .orders-form-wide {
        grid-column: 1/-1;
    }
    .orders-add-line button {
        grid-column: 1/-1;
    }
    .orders-dialog-footer {
        align-items: stretch;
        flex-direction: column;
        padding: 13px 17px;
    }
    .orders-dialog-footer > div {
        justify-content: flex-end;
    }
    .orders-overlay {
        padding: 8px;
        align-items: flex-end;
    }
    .orders-dialog {
        max-height: 94dvh;
        border-radius: 18px 18px 10px 10px;
    }
    .orders-title h1 {
        font-size: 23px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .orders-primary,
    .orders-secondary,
    .orders-danger {
        transition: none;
    }
}

/* Madina Scale design-system alignment: Inventory/Index.vue is the source of truth. */
.orders-page {
    --orders-page-bg: var(--background, #f6f8fb);
    --orders-surface: var(--surface, #fff);
    --orders-border: var(--border, #e5e7eb);
    --orders-text: var(--text-primary, #111827);
    --orders-muted: var(--text-secondary, #667085);
    --orders-tertiary: var(--text-tertiary, #98a2b3);
    --orders-primary-color: var(--primary, #059669);
    width: 100%;
    min-width: 0;
    max-width: 1480px;
    margin: 0 auto;
    padding: 0;
    display: grid;
    align-content: start;
    gap: 20px;
    color: var(--orders-text);
    background: transparent;
    font-size: 12px;
}
.orders-hero {
    align-items: flex-start;
    margin: 0;
    gap: 24px;
}
.orders-breadcrumb {
    gap: 7px;
    margin-bottom: 9px;
    font-size: 10px;
}
.orders-breadcrumb a {
    text-decoration: none;
}
.orders-breadcrumb strong {
    color: var(--orders-muted);
    font-weight: 600;
}
.orders-title {
    align-items: flex-start;
    gap: 12px;
}
.orders-title-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 auto;
    margin-top: 2px;
    border: 1px solid rgb(37 99 235 / 11%);
    border-radius: 12px;
    background: rgb(37 99 235 / 8%);
    color: #2563eb;
}
.orders-title h1 {
    color: var(--orders-text);
    font-size: 20px;
    line-height: 1.2;
    font-weight: 750;
    letter-spacing: -0.03em;
}
.orders-title p {
    max-width: 680px;
    margin-top: 6px;
    color: var(--orders-muted);
    font-size: 11px;
    line-height: 1.5;
}
.orders-primary,
.orders-secondary,
.orders-danger {
    min-height: 38px;
    gap: 7px;
    padding: 0 13px;
    border-radius: 9px;
    font-size: 11px;
    font-weight: 650;
    box-shadow: none;
    transition:
        border-color 0.18s ease,
        background 0.18s ease,
        color 0.18s ease,
        transform 0.18s ease;
}
.orders-primary {
    border: 1px solid var(--orders-primary-color);
    background: var(--orders-primary-color);
    color: var(--primary-foreground, #fff);
}
.orders-primary:hover:not(:disabled) {
    filter: brightness(0.94);
    transform: translateY(-1px);
}
.orders-secondary {
    border-color: var(--orders-border);
    background: var(--orders-surface);
    color: var(--orders-muted);
}
.orders-secondary:hover:not(:disabled) {
    border-color: var(--orders-primary-color);
    color: var(--orders-primary-color);
    background: var(--orders-surface);
}
.orders-danger {
    background: #dc2626;
    color: #fff;
}
.orders-notice,
.orders-error {
    gap: 9px;
    padding: 11px 13px;
    border-radius: 10px;
    font-size: 11px;
}
.orders-metrics {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin: 0;
}
.orders-metrics article {
    min-width: 0;
    gap: 8px;
    padding: 17px 18px;
    border: 1px solid var(--orders-border);
    border-radius: 14px;
    background: var(--orders-surface);
    box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
}
.metric-label {
    color: var(--orders-tertiary);
    font-size: 10px;
    font-weight: 600;
}
.orders-metrics article > strong {
    color: var(--orders-text);
    font-size: 21px;
    line-height: 1.2;
    font-weight: 750;
    letter-spacing: -0.035em;
    font-variant-numeric: tabular-nums;
}
.metric-workflow {
    font-size: 16px !important;
}
.metric-foot {
    color: var(--orders-tertiary);
    font-size: 10px;
    line-height: 1.5;
}
.orders-panel {
    min-width: 0;
    border: 1px solid var(--orders-border);
    border-radius: 14px;
    background: var(--orders-surface);
    box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
}
.orders-panel-head {
    align-items: flex-start;
    gap: 11px;
    padding: 21px 21px 0;
    border-bottom: 0;
}
.orders-panel-head h2 {
    margin: 1px 0 3px;
    color: var(--orders-text);
    font-size: 13px;
    font-weight: 700;
}
.orders-panel-head p {
    margin: 0;
    color: var(--orders-muted);
    font-size: 10px;
    line-height: 1.5;
}
.orders-count {
    flex: 0 0 auto;
    margin-left: auto;
    padding: 6px 9px;
    border: 1px solid var(--orders-border);
    border-radius: 7px;
    background: #fafbfc;
    color: var(--orders-muted);
    font-size: 10px;
    font-weight: 650;
}
.orders-toolbar {
    gap: 14px;
    padding: 18px 21px;
    border-bottom: 0;
}
.orders-search {
    width: min(100%, 520px);
    min-width: 0;
    height: 39px;
    gap: 9px;
    padding: 0 11px;
    border-color: var(--orders-border);
    border-radius: 9px;
    background: var(--orders-surface);
    color: var(--orders-tertiary);
}
.orders-search:focus-within {
    border-color: var(--orders-primary-color);
    box-shadow: 0 0 0 3px rgb(5 150 105 / 8%);
}
.orders-search input {
    height: 100%;
    font-size: 11px;
}
.orders-search input::placeholder {
    color: var(--orders-tertiary);
}
.orders-filter {
    gap: 8px;
    color: var(--orders-muted);
    font-size: 11px;
    font-weight: 600;
}
.orders-filter select {
    min-width: 145px;
    height: 39px;
    padding: 0 10px;
    border-color: var(--orders-border);
    border-radius: 9px;
    background: var(--orders-surface);
    color: var(--orders-text);
    font-size: 11px;
}
.orders-table-wrap {
    border-top: 1px solid var(--orders-border);
}
.orders-table {
    border-collapse: separate;
    border-spacing: 0;
    text-align: left;
}
.orders-table thead {
    background: #f8fafc;
}
.orders-table th {
    padding: 12px 15px;
    border-bottom: 1px solid var(--orders-border);
    background: #f8fafc;
    color: var(--orders-muted);
    font-size: 9px;
    letter-spacing: 0.025em;
    text-transform: uppercase;
    font-weight: 700;
}
.orders-table th:first-child,
.orders-table td:first-child {
    padding-left: 21px;
}
.orders-table th:last-child,
.orders-table td:last-child {
    padding-right: 21px;
}
.orders-table td {
    padding: 13px 15px;
    border-top: 0;
    border-bottom: 1px solid #eef0f4;
    color: var(--orders-muted);
    font-size: 11px;
    vertical-align: middle;
}
.orders-table tbody tr {
    transition: background 0.16s ease;
}
.orders-table tbody tr {
    animation: order-row-enter 220ms ease both;
}
@keyframes order-row-enter {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.orders-table tbody tr:hover {
    background: #fafcff;
}
.orders-table tbody tr:last-child td {
    border-bottom: 0;
}
.orders-table td:first-child small,
.orders-table td:nth-child(2) small {
    margin-top: 4px;
    color: var(--orders-tertiary);
    font-size: 10px;
}
.orders-table td:nth-child(2) strong {
    color: var(--orders-text);
    font-size: 11px;
    font-weight: 650;
}
.orders-number {
    color: #2563eb;
    font-size: 11px;
    font-weight: 700;
}
.orders-money {
    color: var(--orders-text);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.orders-status {
    gap: 6px;
    padding: 5px 8px;
    border: 1px solid transparent;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 650;
}
.status-pending {
    border-color: #fde68a;
    background: #fffbeb;
    color: #a16207;
}
.status-confirmed,
.status-processing {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
}
.status-partially_fulfilled {
    border-color: #ddd6fe;
    background: #f5f3ff;
    color: #6d28d9;
}
.status-fulfilled {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
}
.status-cancelled {
    border-color: #fecaca;
    background: #fef2f2;
    color: #b91c1c;
}
.orders-row-actions {
    gap: 5px;
}
.orders-row-actions button {
    width: 30px;
    height: 30px;
    border-color: var(--orders-border);
    border-radius: 8px;
    background: var(--orders-surface);
}
.orders-row-actions button:hover {
    border-color: var(--orders-primary-color);
    color: var(--orders-primary-color);
}
.orders-empty {
    height: 190px;
    padding: 24px;
    text-align: center;
    color: var(--orders-muted);
}
.orders-empty > * {
    display: block;
    margin: 6px auto;
}
.orders-empty strong {
    color: var(--orders-text);
    font-size: 12px;
}
.orders-empty span {
    color: var(--orders-tertiary);
    font-size: 10px;
}
.orders-pagination {
    gap: 15px;
    padding: 15px 21px;
    border-top: 1px solid var(--orders-border);
    color: var(--orders-muted);
    font-size: 10px;
}
.orders-pagination button {
    min-height: 32px;
    padding: 0 11px;
    border-color: var(--orders-border);
    border-radius: 8px;
    background: var(--orders-surface);
    font-size: 10px;
}
.orders-pagination button:hover:not(:disabled) {
    border-color: var(--orders-primary-color);
    color: var(--orders-primary-color);
}
.orders-overlay {
    z-index: 1200;
    padding: 22px;
    background: rgb(15 23 42 / 58%);
    backdrop-filter: blur(7px);
}
.orders-dialog {
    border-color: var(--orders-border);
    border-radius: 18px;
    background: var(--orders-surface);
    box-shadow: 0 30px 90px rgb(15 23 42 / 28%);
}
.orders-create-dialog {
    width: min(760px, 100%);
}
.orders-dialog-head {
    align-items: center;
    padding: 22px 24px;
    border-color: var(--orders-border);
    background: linear-gradient(125deg, #fff 0%, #fbfcff 100%);
}
.orders-eyebrow {
    color: var(--orders-primary-color);
    font-size: 9px;
    letter-spacing: 0.12em;
}
.orders-dialog-head h2 {
    color: var(--orders-text);
    font-size: 21px;
    line-height: 1.25;
    font-weight: 760;
}
.orders-dialog-head p {
    font-size: 11px;
    line-height: 1.5;
}
.orders-close {
    flex: 0 0 auto;
    border-color: var(--orders-border);
    border-radius: 10px;
    color: var(--orders-muted);
}
.orders-close:hover {
    border-color: var(--orders-primary-color);
    color: var(--orders-primary-color);
}
.orders-dialog-body {
    padding: 20px 24px;
}
.orders-form-grid {
    gap: 15px 13px;
}
.orders-form-grid label,
.orders-add-line label,
.orders-dialog-body > label,
.orders-fulfillment > label {
    gap: 7px;
    color: #344054;
    font-size: 11px;
    font-weight: 700;
}
.orders-form-grid input,
.orders-form-grid select,
.orders-form-grid textarea,
.orders-add-line select,
.orders-add-line input,
.orders-dialog-body textarea,
.orders-fulfillment select,
.orders-fulfillment input,
.orders-fulfillment textarea {
    min-height: 40px;
    padding: 10px 11px;
    border-color: var(--input, #d6deea);
    border-radius: 9px;
    background: var(--background, #fff);
    color: var(--foreground, #101828);
    font-size: 11px;
}
.orders-form-grid input:focus,
.orders-form-grid select:focus,
.orders-form-grid textarea:focus,
.orders-add-line select:focus,
.orders-add-line input:focus,
.orders-fulfillment select:focus,
.orders-fulfillment input:focus,
.orders-fulfillment textarea:focus {
    outline: none;
    border-color: var(--orders-primary-color);
    box-shadow: 0 0 0 3px rgb(5 150 105 / 10%);
}
.orders-add-line {
    gap: 10px;
    margin-top: 18px;
    padding: 17px 0;
    border-color: var(--orders-border);
}
.orders-cart {
    border-color: var(--orders-border);
    border-radius: 11px;
}
.orders-cart-line {
    border-color: #eef0f4;
    font-size: 11px;
}
.orders-cart-line strong {
    color: var(--orders-text);
    font-size: 11px;
}
.orders-cart-line small {
    color: var(--orders-tertiary);
    font-size: 10px;
}
.orders-cart-empty {
    color: var(--orders-tertiary);
    font-size: 11px;
}
.orders-total {
    font-size: 11px;
}
.orders-total strong,
.orders-detail-meta strong {
    color: var(--orders-text);
    font-size: 21px;
}
.orders-dialog-footer {
    gap: 12px;
    padding: 14px 24px;
    border-color: var(--orders-border);
    background: #fbfcfe;
}
.orders-dialog-footer > span {
    color: var(--orders-tertiary);
    font-size: 10px;
    line-height: 1.5;
}
.orders-fulfillment {
    gap: 10px;
    padding: 15px;
    border-color: var(--orders-border);
    border-radius: 12px;
    background: #f8fafc;
}
.orders-fulfillment h3 {
    color: var(--orders-text);
    font-size: 13px;
    font-weight: 750;
}
.orders-fulfillment p {
    color: var(--orders-muted);
    font-size: 10px;
    line-height: 1.5;
}
.orders-fulfillment-line {
    border-color: #e8edf4;
}
.orders-risk-card,
.orders-shipment-card {
    border-color: var(--orders-border);
    border-radius: 10px;
    font-size: 11px;
}
.orders-risk-card strong,
.orders-shipment-card strong {
    color: var(--orders-text);
}
@media (max-width: 1100px) {
    .orders-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 760px) {
    .orders-page {
        padding: 0;
        gap: 14px;
    }
    .orders-hero {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    .orders-hero > .orders-primary {
        align-self: flex-start;
    }
    .orders-title h1 {
        font-size: 18px;
    }
    .orders-title p {
        font-size: 11px;
    }
    .orders-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 11px;
    }
    .orders-metrics article {
        padding: 14px;
    }
    .orders-metrics article > strong {
        font-size: 19px;
    }
    .orders-toolbar {
        align-items: stretch;
        flex-direction: column;
        padding: 15px;
    }
    .orders-search {
        width: 100%;
    }
    .orders-filter {
        justify-content: space-between;
    }
    .orders-filter select {
        flex: 1;
    }
    .orders-panel-head {
        padding: 16px 15px 0;
    }
    .orders-table th:first-child,
    .orders-table td:first-child {
        padding-left: 15px;
    }
    .orders-table th:last-child,
    .orders-table td:last-child {
        padding-right: 15px;
    }
    .orders-pagination {
        align-items: flex-start;
        flex-direction: column;
        padding: 14px 15px;
    }
    .orders-pagination div {
        align-self: flex-end;
    }
    .orders-dialog-head,
    .orders-dialog-body {
        padding: 16px;
    }
    .orders-form-grid {
        grid-template-columns: minmax(0, 1fr);
    }
    .orders-form-wide {
        grid-column: auto;
    }
    .orders-add-line {
        grid-template-columns: minmax(0, 1fr) 85px;
    }
    .orders-add-line .orders-form-wide,
    .orders-add-line button {
        grid-column: 1 / -1;
    }
    .orders-dialog-footer {
        align-items: stretch;
        flex-direction: column;
        padding: 13px 16px;
    }
    .orders-dialog-footer > div {
        justify-content: flex-end;
    }
    .orders-overlay {
        align-items: flex-end;
        padding: 7px;
    }
    .orders-dialog {
        max-height: 96dvh;
        border-radius: 17px 17px 12px 12px;
    }
}
@media (max-width: 430px) {
    .orders-metrics {
        grid-template-columns: minmax(0, 1fr);
    }
}
@media (prefers-reduced-motion: reduce) {
    .orders-page *,
    .orders-page *::before,
    .orders-page *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Advanced order details workspace */
.orders-clickable-row {
    cursor: pointer;
}
.orders-clickable-row:focus-visible {
    outline: 2px solid var(--orders-primary-color, #059669);
    outline-offset: -2px;
}
.orders-clickable-row:hover td {
    background: color-mix(
        in srgb,
        var(--orders-primary-color, #059669) 4%,
        var(--orders-surface, #fff)
    );
}
.orders-detail-overlay {
    align-items: center;
    padding: clamp(8px, 2vw, 26px);
}
.orders-detail-dialog {
    width: min(1320px, 100%);
    max-width: 1320px;
    max-height: min(94dvh, 1120px);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 20px;
}
.orders-detail-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding: 22px 26px 20px;
    border-bottom: 1px solid var(--orders-border);
    background:
        radial-gradient(circle at 92% 0%, rgb(5 150 105 / 8%), transparent 35%),
        linear-gradient(
            120deg,
            var(--orders-surface) 0%,
            color-mix(in srgb, var(--orders-surface) 94%, #dbeafe) 100%
        );
}
.orders-detail-heading h2 {
    margin: 5px 0 0;
    color: var(--orders-text);
    font-size: clamp(20px, 2vw, 27px);
    font-weight: 800;
    letter-spacing: -0.04em;
}
.orders-detail-heading p {
    margin-top: 6px;
    color: var(--orders-muted);
    font-size: 11px;
}
.orders-detail-head-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}
.orders-detail-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 10px;
    text-transform: capitalize;
    white-space: nowrap;
}
.orders-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}
.orders-detail-body {
    overflow-y: auto;
    min-height: 0;
    padding: 22px 26px 28px;
    display: grid;
    gap: 16px;
    overscroll-behavior: contain;
}
.orders-detail-summary {
    display: grid;
    grid-template-columns: 1.15fr repeat(3, minmax(0, 1fr));
    gap: 10px;
}
.orders-detail-total,
.orders-summary-stat {
    min-width: 0;
    display: grid;
    align-content: center;
    gap: 5px;
    padding: 15px 16px;
    border: 1px solid var(--orders-border);
    border-radius: 12px;
    background: var(--orders-surface);
}
.orders-detail-total {
    border-color: color-mix(
        in srgb,
        var(--orders-primary-color) 30%,
        var(--orders-border)
    );
    background: color-mix(
        in srgb,
        var(--orders-primary-color) 5%,
        var(--orders-surface)
    );
}
.orders-detail-total span,
.orders-summary-stat span {
    color: var(--orders-muted);
    font-size: 10px;
    font-weight: 650;
}
.orders-detail-total strong {
    color: var(--orders-primary-color);
    font-size: 25px;
    font-weight: 800;
    letter-spacing: -0.04em;
    font-variant-numeric: tabular-nums;
}
.orders-summary-stat strong {
    overflow-wrap: anywhere;
    color: var(--orders-text);
    font-size: 12px;
    font-weight: 750;
}
.orders-detail-total small,
.orders-summary-stat small {
    color: var(--orders-tertiary);
    font-size: 10px;
}
.orders-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}
.orders-detail-card {
    min-width: 0;
    padding: 17px;
    border: 1px solid var(--orders-border);
    border-radius: 13px;
    background: var(--orders-surface);
}
.orders-section-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}
.orders-section-icon {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    width: 33px;
    height: 33px;
    border: 1px solid
        color-mix(
            in srgb,
            var(--orders-primary-color) 17%,
            var(--orders-border)
        );
    border-radius: 10px;
    background: color-mix(
        in srgb,
        var(--orders-primary-color) 7%,
        var(--orders-surface)
    );
    color: var(--orders-primary-color);
}
.orders-section-heading h3 {
    margin: 0;
    color: var(--orders-text);
    font-size: 12px;
    font-weight: 750;
}
.orders-section-heading p {
    margin: 4px 0 0;
    color: var(--orders-muted);
    font-size: 10px;
    line-height: 1.45;
}
.orders-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px 18px;
    margin: 0;
}
.orders-info-grid div {
    min-width: 0;
}
.orders-info-grid dt,
.orders-price-list dt {
    margin-bottom: 4px;
    color: var(--orders-tertiary);
    font-size: 9px;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.orders-info-grid dd,
.orders-price-list dd {
    margin: 0;
    color: var(--orders-text);
    font-size: 11px;
    font-weight: 650;
    overflow-wrap: anywhere;
}
.orders-info-grid dd small {
    color: var(--orders-muted);
    font-weight: 500;
}
.orders-note-block {
    margin-top: 15px;
    padding: 11px 12px;
    border: 1px solid var(--orders-border);
    border-radius: 9px;
    background: color-mix(
        in srgb,
        var(--orders-muted) 4%,
        var(--orders-surface)
    );
}
.orders-note-block span {
    color: var(--orders-muted);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
}
.orders-note-block p {
    margin: 5px 0 0;
    color: var(--orders-text);
    font-size: 11px;
    line-height: 1.6;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
.orders-note-danger {
    border-color: #fecaca;
    background: #fff7f7;
}
.orders-note-danger span {
    color: #b42318;
}
.orders-price-list {
    display: grid;
    gap: 13px;
    margin: 0;
}
.orders-price-list div {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}
.orders-price-list dt {
    margin: 0;
    text-transform: none;
    letter-spacing: 0;
    font-size: 11px;
    font-weight: 500;
}
.orders-price-list dd {
    font-variant-numeric: tabular-nums;
}
.orders-price-grand {
    margin-top: 2px;
    padding-top: 13px;
    border-top: 1px dashed var(--orders-border);
}
.orders-price-grand dt,
.orders-price-grand dd {
    color: var(--orders-text);
    font-size: 13px;
    font-weight: 800;
}
.orders-items-heading {
    margin-bottom: 12px;
}
.orders-items-count {
    margin-left: auto;
    padding: 5px 8px;
    border: 1px solid var(--orders-border);
    border-radius: 7px;
    color: var(--orders-muted);
    font-size: 10px;
    font-weight: 650;
    white-space: nowrap;
}
.orders-detail-table-wrap {
    max-width: 100%;
    overflow: auto;
    border: 1px solid var(--orders-border);
    border-radius: 9px;
}
.orders-detail-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    text-align: left;
}
.orders-detail-table th {
    padding: 10px 11px;
    border-bottom: 1px solid var(--orders-border);
    background: color-mix(
        in srgb,
        var(--orders-muted) 5%,
        var(--orders-surface)
    );
    color: var(--orders-muted);
    font-size: 9px;
    font-weight: 750;
    text-transform: uppercase;
    white-space: nowrap;
}
.orders-detail-table td {
    padding: 12px 11px;
    border-bottom: 1px solid
        color-mix(in srgb, var(--orders-border) 70%, transparent);
    color: var(--orders-muted);
    font-size: 10px;
    white-space: nowrap;
}
.orders-detail-table tbody tr:last-child td {
    border-bottom: 0;
}
.orders-detail-table td strong {
    color: var(--orders-text);
    font-size: 10px;
}
.orders-detail-table td small {
    display: block;
    margin-top: 4px;
    color: var(--orders-tertiary);
    font-size: 9px;
}
.orders-detail-table .orders-align-right {
    text-align: right;
}
.orders-fulfilled-value {
    color: var(--orders-primary-color);
    font-weight: 750;
}
.orders-detail-action-card {
    margin: 0;
    background: color-mix(
        in srgb,
        var(--orders-muted) 3%,
        var(--orders-surface)
    );
}
.orders-detail-action-card > .orders-primary {
    justify-self: start;
}
.orders-timeline {
    display: grid;
    gap: 0;
}
.orders-timeline-item {
    position: relative;
    display: grid;
    grid-template-columns: 10px minmax(0, 1fr);
    gap: 10px;
    padding-bottom: 16px;
}
.orders-timeline-item:not(:last-child)::before {
    position: absolute;
    top: 10px;
    bottom: -1px;
    left: 4px;
    width: 1px;
    background: var(--orders-border);
    content: '';
}
.orders-timeline-dot {
    z-index: 1;
    width: 9px;
    height: 9px;
    margin-top: 3px;
    border: 2px solid var(--orders-primary-color);
    border-radius: 50%;
    background: var(--orders-surface);
}
.orders-timeline-item strong {
    color: var(--orders-text);
    font-size: 11px;
    text-transform: capitalize;
}
.orders-timeline-item p {
    margin: 4px 0 0;
    color: var(--orders-muted);
    font-size: 10px;
    line-height: 1.5;
}
.orders-timeline-item small,
.orders-record-list small {
    display: block;
    margin-top: 5px;
    color: var(--orders-tertiary);
    font-size: 9px;
}
.orders-record-list {
    display: grid;
    gap: 10px;
}
.orders-record-list article,
.orders-record-grid article {
    min-width: 0;
    padding: 12px;
    border: 1px solid var(--orders-border);
    border-radius: 10px;
    background: var(--orders-surface);
}
.orders-record-list article > div,
.orders-record-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
.orders-record-list article strong,
.orders-record-grid article > strong {
    color: var(--orders-text);
    font-size: 11px;
}
.orders-record-list article p,
.orders-review-note {
    margin: 7px 0 0;
    color: var(--orders-muted);
    font-size: 10px;
    line-height: 1.5;
}
.orders-record-list ul,
.orders-record-grid ul {
    margin: 8px 0 0;
    padding-left: 17px;
    color: var(--orders-muted);
    font-size: 10px;
    line-height: 1.7;
}
.orders-mini-status {
    display: inline-flex;
    padding: 4px 7px;
    border-radius: 6px;
    background: color-mix(
        in srgb,
        var(--orders-muted) 8%,
        var(--orders-surface)
    );
    color: var(--orders-muted);
    font-size: 9px;
    font-weight: 700;
    text-transform: capitalize;
    overflow-wrap: anywhere;
}
.orders-record-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
.orders-section-action {
    margin-left: auto;
    min-height: 31px;
    padding: 0 9px;
    font-size: 10px;
}
.orders-detail-inline-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 14px;
}
.orders-inline-hint {
    color: var(--orders-tertiary);
    font-size: 10px;
}
.orders-shipment-grid {
    grid-template-columns: minmax(240px, 0.8fr) minmax(0, 1.2fr);
}
.orders-shipment-form {
    display: grid;
    align-content: start;
    gap: 11px;
}
.orders-shipment-form label {
    display: grid;
    gap: 6px;
    color: var(--orders-muted);
    font-size: 10px;
    font-weight: 650;
}
.orders-shipment-form select,
.orders-shipment-form input,
.orders-shipment-form textarea {
    width: 100%;
    min-height: 36px;
    padding: 9px 10px;
    border: 1px solid var(--orders-border);
    border-radius: 8px;
    background: var(--orders-surface);
    color: var(--orders-text);
    font: inherit;
}
.orders-shipment-form small {
    color: var(--orders-tertiary);
    font-size: 9px;
    line-height: 1.5;
}
.orders-shipment-list {
    display: grid;
    align-content: start;
    gap: 10px;
}
.orders-shipment-error {
    margin: 8px 0 0;
    color: #b42318;
    font-size: 10px;
}
.orders-sms-table td {
    white-space: normal;
    min-width: 90px;
}
.orders-sms-table .orders-sms-message {
    min-width: 180px;
    max-width: 300px;
    overflow-wrap: anywhere;
}
.orders-detail-empty {
    padding: 18px 10px;
    color: var(--orders-tertiary);
    font-size: 10px;
    text-align: center;
}
.orders-detail-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 11px;
    min-height: 240px;
    color: var(--orders-muted);
    font-size: 12px;
}
.orders-spinner {
    width: 18px;
    height: 18px;
    border: 2px solid var(--orders-border);
    border-top-color: var(--orders-primary-color);
    border-radius: 50%;
    animation: orders-spin 0.7s linear infinite;
}
@keyframes orders-spin {
    to {
        transform: rotate(360deg);
    }
}
.orders-detail-footer {
    flex-shrink: 0;
    gap: 12px;
}
.orders-detail-footer > div {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
}
@media (max-width: 900px) {
    .orders-detail-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .orders-detail-grid {
        grid-template-columns: 1fr;
    }
    .orders-detail-dialog {
        max-height: 96dvh;
    }
}
@media (max-width: 600px) {
    .orders-detail-overlay {
        align-items: flex-end;
        padding: 0;
    }
    .orders-detail-dialog {
        width: 100%;
        max-height: 96dvh;
        border-radius: 18px 18px 0 0;
    }
    .orders-detail-head {
        padding: 17px;
    }
    .orders-detail-body {
        padding: 13px;
        gap: 11px;
    }
    .orders-detail-summary {
        grid-template-columns: 1fr 1fr;
        gap: 7px;
    }
    .orders-detail-total,
    .orders-summary-stat {
        padding: 11px;
    }
    .orders-detail-total strong {
        font-size: 20px;
    }
    .orders-detail-card {
        padding: 12px;
    }
    .orders-info-grid {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .orders-record-grid,
    .orders-shipment-grid {
        grid-template-columns: 1fr;
    }
    .orders-detail-footer {
        padding: 12px 14px;
    }
    .orders-detail-footer > div {
        justify-content: stretch;
    }
    .orders-detail-footer button {
        flex: 1 1 auto;
    }
    .orders-detail-heading h2 {
        font-size: 19px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .orders-spinner {
        animation: none;
    }
    .orders-clickable-row {
        animation: none !important;
    }
}

/* Order index polish: icon-led KPI cards and a restrained page-entry animation. */
.orders-page {
    animation: orders-page-enter 420ms cubic-bezier(0.2, 0.7, 0.25, 1) both;
}
.orders-metrics {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}
.orders-metric-card {
    position: relative;
    min-height: 132px;
    overflow: hidden;
    animation: orders-metric-enter 480ms cubic-bezier(0.2, 0.7, 0.25, 1) both;
    transition:
        transform 0.18s ease,
        border-color 0.18s ease,
        box-shadow 0.18s ease;
}
.orders-metric-card:nth-child(2) {
    animation-delay: 55ms;
}
.orders-metric-card:nth-child(3) {
    animation-delay: 110ms;
}
.orders-metric-card:nth-child(4) {
    animation-delay: 165ms;
}
.metric-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.metric-icon {
    display: inline-grid;
    place-items: center;
    flex: 0 0 auto;
    width: 38px;
    height: 38px;
    border: 1px solid transparent;
    border-radius: 12px;
}
.metric-total .metric-icon {
    color: #2563eb;
    background: #eff6ff;
    border-color: #dbeafe;
}
.metric-active .metric-icon {
    color: #0d9488;
    background: #f0fdfa;
    border-color: #ccfbf1;
}
.metric-pending .metric-icon {
    color: #b7791f;
    background: #fffbeb;
    border-color: #fef3c7;
}
.metric-fulfilled .metric-icon {
    color: #15803d;
    background: #f0fdf4;
    border-color: #dcfce7;
}
.orders-metric-card > strong {
    display: block;
    margin-top: 3px;
}
.orders-metric-card:hover {
    transform: translateY(-2px);
    border-color: color-mix(
        in srgb,
        var(--orders-primary-color) 28%,
        var(--orders-border)
    );
    box-shadow: 0 8px 22px rgb(16 24 40 / 5%);
}
.orders-table tbody tr:nth-child(1) {
    animation-delay: 20ms;
}
.orders-table tbody tr:nth-child(2) {
    animation-delay: 40ms;
}
.orders-table tbody tr:nth-child(3) {
    animation-delay: 60ms;
}
.orders-table tbody tr:nth-child(4) {
    animation-delay: 80ms;
}
.orders-table tbody tr:nth-child(5) {
    animation-delay: 100ms;
}
.orders-table tbody tr:nth-child(n + 6) {
    animation-delay: 120ms;
}
@keyframes orders-page-enter {
    from {
        opacity: 0;
        transform: translateY(7px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes orders-metric-enter {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.99);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
@media (max-width: 1100px) {
    .orders-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 430px) {
    .orders-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 9px;
    }
    .orders-metric-card {
        min-height: 122px;
        padding: 12px;
    }
    .metric-icon {
        width: 32px;
        height: 32px;
        border-radius: 10px;
    }
    .orders-metric-card > strong {
        font-size: 18px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .orders-page,
    .orders-metric-card,
    .orders-table tbody tr {
        animation: none !important;
        transition: none !important;
    }
    .orders-metric-card:hover {
        transform: none;
    }
}

/* Corrected modal layering and opaque surface overrides */

/* Order detail workspace: richer hierarchy, lifecycle and operational signals */
.orders-detail-dialog {
    width: min(1440px, 100%);
    max-width: 1440px;
    box-shadow: 0 36px 110px rgb(2 8 23 / 32%);
}
.orders-detail-head {
    position: relative;
    flex: 0 0 auto;
    padding: 22px 28px;
}
.orders-detail-head::after {
    position: absolute;
    inset: auto 0 0;
    height: 2px;
    content: '';
    background: linear-gradient(
        90deg,
        var(--orders-primary-color),
        color-mix(in srgb, var(--orders-primary-color) 12%, transparent) 75%,
        transparent
    );
}
.orders-detail-body {
    gap: 18px;
    padding: 22px 28px 30px;
    background: color-mix(
        in srgb,
        var(--orders-page-bg, #f7f9fc) 52%,
        var(--orders-surface, #fff)
    );
}
.orders-detail-total,
.orders-summary-stat {
    box-shadow: 0 2px 8px rgb(16 24 40 / 3%);
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease;
}
.orders-detail-total:hover,
.orders-summary-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgb(16 24 40 / 7%);
}
.orders-lifecycle-card {
    display: grid;
    gap: 15px;
    padding: 17px 19px;
    border: 1px solid var(--orders-border);
    border-radius: 14px;
    background: var(--orders-surface);
    box-shadow: 0 2px 8px rgb(16 24 40 / 3%);
}
.orders-lifecycle-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.orders-lifecycle-top > div {
    display: grid;
    gap: 4px;
}
.orders-lifecycle-kicker {
    color: var(--orders-tertiary);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.13em;
}
.orders-lifecycle-top strong {
    color: var(--orders-text);
    font-size: 13px;
}
.orders-lifecycle-current {
    padding: 6px 10px;
    border: 1px solid
        color-mix(
            in srgb,
            var(--orders-primary-color) 22%,
            var(--orders-border)
        );
    border-radius: 999px;
    background: color-mix(
        in srgb,
        var(--orders-primary-color) 7%,
        var(--orders-surface)
    );
    color: var(--orders-primary-color);
    font-size: 10px;
    font-weight: 750;
    text-transform: capitalize;
}
.orders-lifecycle-steps {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
}
.orders-lifecycle-step {
    position: relative;
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--orders-tertiary);
    font-size: 10px;
    font-weight: 650;
}
.orders-lifecycle-step:not(:last-child)::after {
    position: absolute;
    top: 14px;
    left: 35px;
    right: 0;
    height: 2px;
    background: var(--orders-border);
    content: '';
    transform: translateX(8px);
}
.orders-lifecycle-step.is-reached {
    color: var(--orders-text);
}
.orders-lifecycle-step.is-reached:not(:last-child)::after {
    background: color-mix(
        in srgb,
        var(--orders-primary-color) 45%,
        var(--orders-border)
    );
}
.orders-lifecycle-marker {
    z-index: 1;
    display: grid;
    place-items: center;
    flex: 0 0 29px;
    width: 29px;
    height: 29px;
    border: 1px solid var(--orders-border);
    border-radius: 50%;
    background: var(--orders-surface);
    color: var(--orders-tertiary);
    font-size: 10px;
}
.orders-lifecycle-step.is-reached .orders-lifecycle-marker {
    border-color: var(--orders-primary-color);
    background: var(--orders-primary-color);
    color: #fff;
}
.orders-lifecycle-steps.is-cancelled .orders-lifecycle-step {
    color: var(--orders-tertiary);
}
.orders-lifecycle-steps.is-cancelled .orders-lifecycle-marker {
    border-color: #fda29b;
    background: #fff1f0;
    color: #b42318;
}
.orders-lifecycle-bottom {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    padding-top: 12px;
    border-top: 1px solid var(--orders-border);
}
.orders-lifecycle-bottom span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--orders-muted);
    font-size: 10px;
}
.orders-lifecycle-bottom svg {
    color: var(--orders-tertiary);
}
.orders-lifecycle-alert {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 10px 12px;
    border: 1px solid #fecaca;
    border-radius: 9px;
    background: #fff7f7;
    color: #b42318;
    font-size: 10px;
    line-height: 1.5;
}
.orders-lifecycle-alert.is-info {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
}
.orders-operations-insights {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}
.orders-operations-insights article {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    min-width: 0;
    padding: 14px;
    border: 1px solid var(--orders-border);
    border-radius: 12px;
    background: var(--orders-surface);
}
.orders-insight-icon {
    display: grid;
    place-items: center;
    flex: 0 0 34px;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #fff4d6;
    color: #9a6700;
}
.orders-insight-icon.is-green {
    background: #dcfce7;
    color: #15803d;
}
.orders-insight-icon.is-blue {
    background: #dbeafe;
    color: #1d4ed8;
}
.orders-insight-icon.is-violet {
    background: #ede9fe;
    color: #6d28d9;
}
.orders-operations-insights article > div {
    display: grid;
    gap: 4px;
    min-width: 0;
}
.orders-operations-insights small {
    color: var(--orders-muted);
    font-size: 9px;
    font-weight: 650;
}
.orders-operations-insights strong {
    color: var(--orders-text);
    font-size: 18px;
    line-height: 1.1;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
}
.orders-operations-insights article span:last-child {
    color: var(--orders-tertiary);
    font-size: 9px;
    line-height: 1.4;
    overflow-wrap: anywhere;
}
.orders-detail-card {
    box-shadow: 0 2px 8px rgb(16 24 40 / 3%);
}
.orders-section-heading {
    padding-bottom: 11px;
    border-bottom: 1px solid
        color-mix(in srgb, var(--orders-border) 72%, transparent);
}
.orders-detail-footer {
    position: relative;
    z-index: 2;
    flex: 0 0 auto;
    box-shadow: 0 -5px 20px rgb(16 24 40 / 4%);
}
.orders-detail-footer > span {
    line-height: 1.5;
}
@media (max-width: 900px) {
    .orders-operations-insights {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .orders-detail-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 600px) {
    .orders-detail-head {
        padding: 17px;
    }
    .orders-detail-body {
        padding: 14px;
        gap: 12px;
    }
    .orders-lifecycle-card {
        padding: 13px;
    }
    .orders-lifecycle-steps {
        gap: 3px;
    }
    .orders-lifecycle-step {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
        font-size: 9px;
    }
    .orders-lifecycle-step:not(:last-child)::after {
        top: 14px;
        left: 27px;
        transform: none;
    }
    .orders-lifecycle-bottom {
        display: grid;
        gap: 8px;
    }
    .orders-operations-insights {
        gap: 7px;
    }
    .orders-operations-insights article {
        padding: 10px;
        gap: 8px;
    }
    .orders-insight-icon {
        flex-basis: 28px;
        width: 28px;
        height: 28px;
    }
    .orders-operations-insights strong {
        font-size: 16px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .orders-detail-total,
    .orders-summary-stat {
        transition: none;
    }
}

/* Order details is teleported to body so transformed ERP shells cannot trap fixed positioning. */
body:has(.orders-detail-overlay) {
    overflow: hidden !important;
}
.orders-overlay.orders-detail-overlay {
    position: fixed !important;
    inset: 0 !important;
    z-index: 2147483647 !important;
    isolation: isolate;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100vw !important;
    height: 100vh !important;
    height: 100dvh !important;
    max-width: none !important;
    max-height: none !important;
    margin: 0 !important;
    padding: clamp(16px, 3vw, 44px) !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
    background: rgb(8 15 30 / 58%) !important;
    -webkit-backdrop-filter: blur(12px) saturate(0.78) !important;
    backdrop-filter: blur(12px) saturate(0.78) !important;
    animation: orderBackdropIn 0.18s ease-out both;
}
.orders-overlay.orders-detail-overlay > .orders-dialog.orders-detail-dialog {
    position: relative !important;
    z-index: 1 !important;
    flex: 0 1 auto !important;
    width: min(1240px, calc(100vw - 88px)) !important;
    max-width: 1240px !important;
    height: auto !important;
    max-height: min(92vh, 1120px) !important;
    max-height: min(92dvh, 1120px) !important;
    margin: auto !important;
    border-radius: 22px !important;
    overflow: hidden !important;
    background: #ffffff !important;
    background-color: #ffffff !important;
    color: #101828 !important;
    opacity: 1 !important;
    box-shadow:
        0 32px 100px rgb(2 8 23 / 42%),
        0 0 0 1px rgb(255 255 255 / 18%) !important;
    animation: orderDialogIn 0.22s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
@keyframes orderBackdropIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
@keyframes orderDialogIn {
    from {
        opacity: 0;
        transform: translateY(12px) scale(0.985);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
@media (max-width: 900px) {
    .orders-overlay.orders-detail-overlay
        > .orders-dialog.orders-detail-dialog {
        width: calc(100vw - 32px) !important;
        max-width: calc(100vw - 32px) !important;
    }
}
@media (max-width: 760px) {
    .orders-overlay.orders-detail-overlay {
        padding: 10px !important;
    }
    .orders-overlay.orders-detail-overlay
        > .orders-dialog.orders-detail-dialog {
        width: 100% !important;
        max-width: 100% !important;
    }
    .orders-overlay.orders-detail-overlay
        > .orders-dialog.orders-detail-dialog {
        max-height: 96vh !important;
        max-height: 96dvh !important;
        border-radius: 18px !important;
    }
}
@media (prefers-reduced-motion: reduce) {
    .orders-overlay.orders-detail-overlay,
    .orders-overlay.orders-detail-overlay
        > .orders-dialog.orders-detail-dialog {
        animation: none !important;
    }
}

/* Keep the dialog surface fully opaque regardless of the ERP theme variables. */
.orders-overlay.orders-detail-overlay .orders-detail-dialog,
.orders-overlay.orders-detail-overlay .orders-detail-head,
.orders-overlay.orders-detail-overlay .orders-detail-footer {
    background-color: #ffffff !important;
    opacity: 1 !important;
}
.orders-overlay.orders-detail-overlay .orders-detail-body {
    background-color: #f7f9fc !important;
    opacity: 1 !important;
}
.orders-overlay.orders-detail-overlay .orders-detail-card,
.orders-overlay.orders-detail-overlay .orders-detail-total,
.orders-overlay.orders-detail-overlay .orders-summary-stat,
.orders-overlay.orders-detail-overlay .orders-lifecycle-card,
.orders-overlay.orders-detail-overlay .orders-operations-insights article,
.orders-overlay.orders-detail-overlay .orders-record-list article,
.orders-overlay.orders-detail-overlay .orders-record-grid article {
    background-color: #ffffff !important;
    opacity: 1 !important;
}
.orders-overlay.orders-detail-overlay .orders-detail-dialog button {
    opacity: 1;
}

/* Final modal surface contract: backdrop is translucent; dialog is fully opaque. */
.orders-overlay.orders-detail-overlay > .orders-dialog.orders-detail-dialog {
    background: #fff !important;
    background-color: #fff !important;
    opacity: 1 !important;
    isolation: isolate !important;
}
.orders-overlay.orders-detail-overlay
    > .orders-dialog.orders-detail-dialog
    > .orders-detail-head,
.orders-overlay.orders-detail-overlay
    > .orders-dialog.orders-detail-dialog
    > .orders-detail-footer {
    background: #fff !important;
    background-color: #fff !important;
    opacity: 1 !important;
}
.orders-overlay.orders-detail-overlay
    > .orders-dialog.orders-detail-dialog
    > .orders-detail-body {
    background: #f7f9fc !important;
    background-color: #f7f9fc !important;
    opacity: 1 !important;
}
.orders-overlay.orders-detail-overlay .orders-detail-card,
.orders-overlay.orders-detail-overlay .orders-detail-total,
.orders-overlay.orders-detail-overlay .orders-summary-stat,
.orders-overlay.orders-detail-overlay .orders-lifecycle-card,
.orders-overlay.orders-detail-overlay .orders-operations-insights article,
.orders-overlay.orders-detail-overlay .orders-record-list article,
.orders-overlay.orders-detail-overlay .orders-record-grid article {
    background: #fff !important;
    background-color: #fff !important;
    opacity: 1 !important;
}

/* Refined order workspace: narrower dialog, clearer icon-led metadata. */
.orders-overlay.orders-detail-overlay .orders-summary-label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #64748b;
    font-size: 11px;
    font-weight: 750;
    letter-spacing: 0.01em;
}
.orders-overlay.orders-detail-overlay .orders-summary-label :deep(svg) {
    flex: 0 0 auto;
    color: #0f9f6e;
}
.orders-overlay.orders-detail-overlay .orders-detail-total,
.orders-overlay.orders-detail-overlay .orders-summary-stat {
    border-radius: 16px;
    border: 1px solid #e5ebf3;
    box-shadow: 0 4px 14px rgb(15 23 42 / 3%);
}
.orders-overlay.orders-detail-overlay .orders-detail-total {
    border-color: #b8ead8;
    background: linear-gradient(145deg, #f0fbf6, #ffffff) !important;
}
.orders-overlay.orders-detail-overlay .orders-section-icon {
    box-shadow: inset 0 0 0 1px rgb(15 159 110 / 7%);
}
.orders-overlay.orders-detail-overlay .orders-detail-head {
    background: linear-gradient(115deg, #ffffff 58%, #f0faf6 100%) !important;
}
.orders-overlay.orders-detail-overlay .orders-detail-body {
    padding: 22px 24px !important;
    gap: 16px;
}
.orders-overlay.orders-detail-overlay .orders-detail-card {
    border-radius: 16px;
    border-color: #e6ebf2;
    box-shadow: 0 5px 18px rgb(15 23 42 / 3%);
}
@media (max-width: 760px) {
    .orders-overlay.orders-detail-overlay .orders-detail-body {
        padding: 14px !important;
    }
}

/* Order details UX upgrades: status chips, customer contact clarity, SMS composer and courier workflow. */
.orders-lifecycle-badges {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 9px;
}
.orders-stage-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-width: 0;
    padding: 11px 10px;
    border: 1px solid #e1e7ef;
    border-radius: 12px;
    background: #fff;
    color: #667085;
    font-size: 11px;
    font-weight: 750;
    text-align: center;
}
.orders-stage-badge.is-reached {
    border-color: #a7f3d0;
    background: #ecfdf5;
    color: #047857;
}
.orders-stage-badge.is-current {
    border-color: #059669;
    background: #059669;
    color: #fff;
    box-shadow: 0 5px 14px rgb(5 150 105 / 18%);
}
.orders-stage-badge.is-current .orders-stage-active-check {
    margin-left: auto;
}
.orders-lifecycle-steps.is-cancelled .orders-stage-badge {
    border-color: #fecaca;
    background: #fff1f2;
    color: #b42318;
}
.orders-lifecycle-alert.is-success {
    border-color: #a7f3d0;
    background: #ecfdf5;
    color: #047857;
}
.orders-customer-info-grid > div {
    min-width: 0;
    padding: 10px 11px;
    border: 1px solid #edf0f5;
    border-radius: 10px;
    background: #fbfcfe;
}
.orders-customer-info-grid dt {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #667085;
}
.orders-customer-info-grid dd {
    overflow-wrap: anywhere;
    line-height: 1.55;
}
.orders-customer-info-grid dd a {
    color: #175cd3;
    text-decoration: none;
}
.orders-customer-info-grid dd a:hover {
    text-decoration: underline;
}
.orders-field-missing {
    color: #98a2b3;
    font-weight: 500;
}
.orders-channel-chip {
    display: inline-flex;
    padding: 4px 7px;
    border-radius: 6px;
    background: #eef4ff;
    color: #3538cd;
    font-size: 10px;
    font-weight: 750;
    text-transform: capitalize;
}
.orders-note-block > span {
    display: flex;
    align-items: center;
    gap: 6px;
}
.orders-sms-workspace {
    display: grid;
    gap: 15px;
}
.orders-sms-composer {
    display: grid;
    gap: 13px;
    padding: 16px;
    border: 1px solid #dbe5f0;
    border-radius: 14px;
    background: linear-gradient(145deg, #f8fbff, #fff 72%);
}
.orders-sms-composer-top,
.orders-sms-recipient,
.orders-sms-compose-actions,
.orders-sms-history-head,
.orders-sms-log-title,
.orders-sms-log-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.orders-sms-recipient {
    justify-content: flex-start;
}
.orders-sms-avatar {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border-radius: 11px;
    background: #dbeafe;
    color: #1d4ed8;
}
.orders-sms-recipient strong,
.orders-sms-recipient small {
    display: block;
}
.orders-sms-recipient strong {
    color: #101828;
    font-size: 12px;
}
.orders-sms-recipient small {
    margin-top: 3px;
    color: #667085;
    font-size: 10px;
}
.orders-sms-provider {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 8px;
    border-radius: 999px;
    background: #ecfdf3;
    color: #027a48;
    font-size: 9px;
    font-weight: 750;
}
.orders-sms-compose-fields {
    display: grid;
    grid-template-columns: minmax(180px, 0.7fr) minmax(0, 1.5fr);
    gap: 12px;
}
.orders-sms-compose-fields label {
    display: grid;
    align-content: start;
    gap: 6px;
    color: #344054;
    font-size: 11px;
    font-weight: 750;
}
.orders-sms-compose-fields select,
.orders-sms-compose-fields textarea {
    width: 100%;
    min-width: 0;
    border: 1px solid #d0d5dd;
    border-radius: 9px;
    padding: 10px 11px;
    background: #fff;
    color: #101828;
    font: inherit;
    font-size: 11px;
}
.orders-sms-compose-fields textarea {
    resize: vertical;
    line-height: 1.6;
}
.orders-sms-preview-note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 9px 10px;
    border-radius: 9px;
    background: #eff8ff;
    color: #175cd3;
    font-size: 10px;
    line-height: 1.55;
}
.orders-sms-preview-note svg {
    flex: 0 0 auto;
    margin-top: 1px;
}
.orders-sms-compose-actions {
    padding-top: 11px;
    border-top: 1px solid #e4e7ec;
}
.orders-sms-compose-actions > span {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #667085;
    font-size: 10px;
}
.orders-sms-history-head {
    justify-content: flex-start;
    padding-top: 3px;
}
.orders-sms-history-head strong {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #344054;
    font-size: 12px;
}
.orders-sms-history-head > span {
    color: #98a2b3;
    font-size: 10px;
}
.orders-sms-log-list {
    display: grid;
    gap: 9px;
}
.orders-sms-log-card {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 13px;
    border: 1px solid #eaecf0;
    border-radius: 12px;
    background: #fff;
}
.orders-sms-log-icon {
    display: grid;
    place-items: center;
    flex: 0 0 32px;
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: #eff8ff;
    color: #1570ef;
}
.orders-sms-log-icon.is-success {
    background: #ecfdf3;
    color: #039855;
}
.orders-sms-log-icon.is-error {
    background: #fef3f2;
    color: #d92d20;
}
.orders-sms-log-content {
    flex: 1;
    min-width: 0;
}
.orders-sms-log-title {
    justify-content: flex-start;
    flex-wrap: wrap;
}
.orders-sms-log-title strong {
    color: #344054;
    font-size: 11px;
    text-transform: capitalize;
}
.orders-sms-log-content > p {
    margin: 7px 0;
    color: #475467;
    font-size: 11px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}
.orders-sms-log-meta {
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: 7px 15px;
    color: #98a2b3;
    font-size: 9px;
}
.orders-sms-log-meta span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.orders-detail-empty {
    display: grid;
    justify-items: center;
    gap: 6px;
    padding: 25px 15px;
    color: #98a2b3;
    text-align: center;
}
.orders-detail-empty strong {
    color: #475467;
    font-size: 12px;
}
.orders-detail-empty span {
    font-size: 10px;
}
.orders-courier-checklist {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}
.orders-courier-checklist span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 8px;
    border: 1px solid #e4e7ec;
    border-radius: 8px;
    background: #fff;
    color: #667085;
    font-size: 9px;
    font-weight: 650;
}
.orders-shipment-form > label > span {
    display: flex;
    align-items: center;
    gap: 5px;
}
.orders-shipment-card {
    padding: 15px;
}
@media (max-width: 700px) {
    .orders-lifecycle-badges {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .orders-sms-compose-fields {
        grid-template-columns: 1fr;
    }
    .orders-sms-compose-actions {
        align-items: stretch;
        flex-direction: column;
    }
    .orders-sms-compose-actions .orders-primary {
        justify-content: center;
    }
}

/* Create Order modal refinements */
.orders-create-dialog {
    width: min(920px, 100%);
    max-height: min(92dvh, 940px);
    border-radius: 22px;
    box-shadow: 0 34px 110px rgb(2 8 23 / 32%);
}

.orders-create-dialog .orders-dialog-head {
    position: relative;
    flex: 0 0 auto;
    padding: 23px 28px;
    background:
        radial-gradient(
            ellipse at top right,
            rgb(219 231 255 / 72%),
            transparent 48%
        ),
        var(--card, #fff);
}

.orders-create-dialog .orders-dialog-head h2 {
    font-size: clamp(22px, 2.3vw, 27px);
    font-weight: 800;
    letter-spacing: -0.045em;
}

.orders-create-dialog .orders-dialog-head p {
    max-width: 560px;
    line-height: 1.55;
}

.orders-create-dialog .orders-dialog-body {
    flex: 1 1 auto;
    overscroll-behavior: contain;
    padding: 24px 28px;
    background: var(--background, #fff);
}

.orders-create-dialog .orders-form-grid {
    gap: 18px 16px;
}

.orders-create-dialog .orders-form-grid label,
.orders-create-dialog .orders-add-line label {
    color: var(--foreground, #263247);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.005em;
}

.orders-create-dialog .orders-form-grid input,
.orders-create-dialog .orders-form-grid select,
.orders-create-dialog .orders-form-grid textarea,
.orders-create-dialog .orders-add-line select,
.orders-create-dialog .orders-add-line input {
    min-height: 42px;
    border-color: var(--border, #dce3ed);
    border-radius: 11px;
    background: var(--card, #fff);
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        background 0.18s ease;
}

.orders-create-dialog .orders-form-grid textarea {
    min-height: 76px;
}

.orders-create-dialog .orders-form-grid input:focus,
.orders-create-dialog .orders-form-grid select:focus,
.orders-create-dialog .orders-form-grid textarea:focus,
.orders-create-dialog .orders-add-line select:focus,
.orders-create-dialog .orders-add-line input:focus {
    border-color: #7898f5;
    box-shadow: 0 0 0 3px rgb(76 110 245 / 12%);
    outline: none;
}

.orders-create-dialog .orders-add-line {
    grid-template-columns: minmax(0, 1fr) 112px auto;
    gap: 12px;
    margin-top: 24px;
    padding: 22px 0 18px;
    border-top: 1px solid var(--border, #e8edf4);
}

.orders-create-dialog .orders-secondary,
.orders-create-dialog .orders-primary {
    min-height: 42px;
    border-radius: 11px;
    white-space: nowrap;
}

.orders-create-dialog .orders-cart {
    border-color: var(--border, #e0e7f0);
    border-radius: 14px;
    background: var(--card, #fff);
}

.orders-create-dialog .orders-cart-empty {
    padding: 28px 18px;
    line-height: 1.6;
}

.orders-create-dialog .orders-cart-line {
    gap: 14px;
    padding: 14px 16px;
}

.orders-create-dialog .orders-cart-line > div strong {
    overflow-wrap: anywhere;
    line-height: 1.45;
}

.orders-create-dialog .orders-cart-line > strong {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.orders-create-dialog .orders-cart-line > button {
    flex: 0 0 30px;
    width: 30px;
    height: 30px;
    border: 1px solid #fee2e2;
    border-radius: 9px;
    background: #fff7f7;
    line-height: 1;
}

.orders-create-dialog .orders-total {
    margin-top: 12px;
    padding: 17px 18px;
    border: 1px solid #dbe5ff;
    border-radius: 14px;
    background: linear-gradient(120deg, #f5f8ff, #fff);
}

.orders-create-dialog .orders-total span {
    color: #52617a;
    font-size: 12px;
    font-weight: 700;
}

.orders-create-dialog .orders-total strong {
    color: #254bb5;
    font-size: clamp(21px, 2.5vw, 27px);
    font-variant-numeric: tabular-nums;
}

.orders-create-dialog .orders-dialog-footer {
    position: relative;
    flex: 0 0 auto;
    padding: 16px 28px;
    background: var(--card, #fff);
}

.orders-create-dialog .orders-dialog-footer > span {
    max-width: 250px;
    line-height: 1.5;
}

@media (max-width: 640px) {
    .orders-overlay {
        align-items: flex-end;
        padding: 8px;
    }

    .orders-create-dialog {
        width: 100%;
        max-height: 94dvh;
        border-radius: 20px 20px 14px 14px;
    }

    .orders-create-dialog .orders-dialog-head {
        padding: 18px 18px;
    }

    .orders-create-dialog .orders-dialog-body {
        padding: 18px;
    }

    .orders-create-dialog .orders-form-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
    }

    .orders-create-dialog .orders-form-wide {
        grid-column: auto;
    }

    .orders-create-dialog .orders-add-line {
        grid-template-columns: minmax(0, 1fr) 88px;
        align-items: end;
        gap: 10px;
    }

    .orders-create-dialog .orders-add-line .orders-form-wide {
        grid-column: 1 / -1;
    }

    .orders-create-dialog .orders-add-line > button {
        grid-column: 1 / -1;
        width: 100%;
    }

    .orders-create-dialog .orders-dialog-footer {
        align-items: stretch;
        flex-direction: column;
        padding: 14px 18px;
    }

    .orders-create-dialog .orders-dialog-footer > span {
        max-width: none;
    }

    .orders-create-dialog .orders-dialog-footer > div {
        display: grid;
        grid-template-columns: 1fr;
    }

    .orders-create-dialog .orders-dialog-footer button {
        width: 100%;
    }
}

/* Create Order modal — aligned with the Order Details visual system */
.orders-create-overlay {
    z-index: 1250;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: clamp(10px, 2.2vw, 28px);
}
.orders-create-dialog {
    display: flex;
    flex-direction: column;
    width: min(1100px, 100%);
    max-width: 1100px;
    max-height: min(88dvh, 920px);
    min-height: 0;
    overflow: hidden;
    border: 1px solid rgb(226 232 240 / 90%);
    border-radius: 24px;
    background: #f5f8fc;
    box-shadow: 0 32px 100px rgb(15 23 42 / 32%);
    animation: orders-create-enter 180ms ease-out;
}
@keyframes orders-create-enter {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.99);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
.orders-create-head {
    position: relative;
    z-index: 1;
    flex: 0 0 auto;
    display: flex;
    justify-content: space-between;
    gap: 20px;
    padding: 24px 30px;
    background: #fff;
    border-bottom: 1px solid #e8edf4;
}
.orders-create-heading {
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 0;
}
.orders-create-icon {
    display: grid;
    place-items: center;
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    border: 1px solid #ccefe2;
    border-radius: 15px;
    background: #ecfdf5;
    color: #059669;
}
.orders-create-head h2 {
    margin: 4px 0 5px;
    font-size: clamp(22px, 2.2vw, 28px);
    font-weight: 780;
    letter-spacing: -0.035em;
}
.orders-create-head p {
    margin: 0;
    color: #64748b;
    font-size: 12px;
}
.orders-create-head .orders-close {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border: 1px solid #d7e0eb;
    border-radius: 12px;
    background: #fff;
}
.orders-create-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 22px 28px 26px;
    background: #f5f8fc;
    scrollbar-gutter: stable;
}
.orders-create-section {
    padding: 22px;
    border: 1px solid #e5ebf3;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 3px 12px rgb(15 23 42 / 3%);
}
.orders-create-section + .orders-create-section {
    margin-top: 18px;
}
.orders-create-section-title {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 20px;
}
.orders-create-section-icon {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    border: 1px solid #e0f2eb;
    border-radius: 12px;
    background: #f0fdf7;
    color: #059669;
}
.orders-create-section-title h3 {
    margin: 0 0 4px;
    color: #101828;
    font-size: 14px;
    font-weight: 750;
}
.orders-create-section-title p {
    margin: 0;
    color: #667085;
    font-size: 11px;
    line-height: 1.5;
}
.orders-create-count {
    margin-left: auto;
    padding: 6px 10px;
    border: 1px solid #d1fae5;
    border-radius: 999px;
    background: #ecfdf5;
    color: #047857;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}
.orders-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px 18px;
}
.orders-form-grid label,
.orders-add-line label {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 8px;
    color: #344054;
    font-size: 12px;
    font-weight: 650;
}
.orders-form-grid label > span,
.orders-add-line label > span {
    display: flex;
    align-items: center;
    gap: 6px;
}
.orders-form-grid label > span svg,
.orders-add-line label > span svg {
    color: #667085;
}
.orders-form-grid label i,
.orders-add-line label i {
    color: #dc2626;
    font-style: normal;
}
.orders-form-grid input,
.orders-form-grid select,
.orders-form-grid textarea,
.orders-add-line select,
.orders-add-line input {
    width: 100%;
    min-width: 0;
    min-height: 46px;
    padding: 11px 13px;
    border: 1px solid #dce4ee;
    border-radius: 11px;
    background: #fff;
    color: #101828;
    font-size: 12px;
    transition:
        border-color 0.16s ease,
        box-shadow 0.16s ease;
}
.orders-form-grid textarea {
    min-height: 88px;
    resize: vertical;
}
.orders-form-grid input::placeholder,
.orders-form-grid textarea::placeholder {
    color: #98a2b3;
}
.orders-form-grid input:focus,
.orders-form-grid select:focus,
.orders-form-grid textarea:focus,
.orders-add-line select:focus,
.orders-add-line input:focus {
    outline: none;
    border-color: #10a879;
    box-shadow: 0 0 0 4px rgb(16 168 121 / 10%);
}
.orders-form-grid input:disabled,
.orders-add-line select:disabled,
.orders-add-line input:disabled {
    cursor: not-allowed;
    background: #f2f4f7;
    color: #98a2b3;
}
.orders-form-wide {
    grid-column: 1 / -1;
}
.orders-create-products .orders-create-section-title {
    margin-bottom: 18px;
}
.orders-add-line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 140px auto;
    align-items: end;
    gap: 12px;
    margin: 0;
    padding: 0 0 20px;
    border: 0;
    border-bottom: 1px solid #edf1f6;
}
.orders-add-item-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 46px;
    padding: 0 18px;
    white-space: nowrap;
}
.orders-cart {
    margin-top: 16px;
    overflow: hidden;
    border: 1px solid #e6ecf3;
    border-radius: 14px;
    background: #fff;
}
.orders-cart-empty {
    display: flex;
    align-items: center;
    flex-direction: column;
    gap: 7px;
    padding: 30px 18px;
    color: #667085;
    text-align: center;
    font-size: 12px;
}
.orders-cart-empty strong {
    color: #344054;
    font-size: 13px;
}
.orders-cart-empty-icon {
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    margin-bottom: 3px;
    border-radius: 14px;
    background: #f2f6fb;
    color: #667085;
}
.orders-cart-line {
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr) auto 36px;
    align-items: center;
    gap: 12px;
    padding: 13px 15px;
    border-bottom: 1px solid #edf1f6;
}
.orders-cart-line:last-child {
    border-bottom: 0;
}
.orders-cart-product-icon {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #f0fdf7;
    color: #059669;
}
.orders-cart-product {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 0;
}
.orders-cart-product strong {
    overflow-wrap: anywhere;
    color: #101828;
    font-size: 12px;
    font-weight: 700;
}
.orders-cart-product small {
    color: #667085;
    font-size: 10px;
}
.orders-cart-line-total {
    color: #101828;
    font-size: 12px;
    white-space: nowrap;
}
.orders-remove-item {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border: 1px solid #fee2e2;
    border-radius: 10px;
    background: #fff7f7;
    color: #dc2626;
    transition:
        background 0.15s ease,
        border-color 0.15s ease;
}
.orders-remove-item:hover {
    border-color: #fca5a5;
    background: #fee2e2;
}
.orders-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-top: 16px;
    padding: 17px 19px;
    border: 1px solid #ccefe2;
    border-radius: 14px;
    background: linear-gradient(110deg, #f0fdf7, #fff);
}
.orders-total > div {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.orders-total span {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #344054;
    font-size: 12px;
    font-weight: 700;
}
.orders-total span svg {
    color: #059669;
}
.orders-total small {
    color: #667085;
    font-size: 10px;
}
.orders-total strong {
    color: #047857;
    font-size: 23px;
    font-weight: 800;
    letter-spacing: -0.03em;
    white-space: nowrap;
}
.orders-create-footer {
    position: relative;
    z-index: 1;
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 15px 28px;
    border-top: 1px solid #e5ebf3;
    background: #fff;
}
.orders-create-footer-note {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #667085;
    font-size: 11px;
    line-height: 1.5;
}
.orders-create-footer-note svg {
    flex: 0 0 auto;
    color: #059669;
}
.orders-create-footer > div:last-child {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 9px;
}
.orders-create-footer button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 42px;
    border-radius: 11px;
    padding: 0 15px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}
.orders-create-footer .orders-primary {
    box-shadow: 0 5px 14px rgb(5 150 105 / 16%);
}
.orders-create-footer button:disabled {
    cursor: not-allowed;
    opacity: 0.55;
    box-shadow: none;
}
@media (max-width: 760px) {
    .orders-create-overlay {
        padding: 8px;
    }
    .orders-create-dialog {
        max-height: 94dvh;
        border-radius: 18px;
    }
    .orders-create-head {
        padding: 18px;
    }
    .orders-create-heading {
        gap: 11px;
    }
    .orders-create-icon {
        width: 42px;
        height: 42px;
        flex-basis: 42px;
    }
    .orders-create-head p {
        max-width: 42ch;
    }
    .orders-create-body {
        padding: 14px;
    }
    .orders-create-section {
        padding: 15px;
        border-radius: 15px;
    }
    .orders-form-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
    }
    .orders-form-wide {
        grid-column: auto;
    }
    .orders-add-line {
        grid-template-columns: minmax(0, 1fr) 100px;
    }
    .orders-add-line .orders-form-wide {
        grid-column: 1 / -1;
    }
    .orders-add-item-button {
        grid-column: 1 / -1;
        width: 100%;
    }
    .orders-create-footer {
        align-items: stretch;
        flex-direction: column;
        padding: 13px 16px;
    }
    .orders-create-footer > div:last-child {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
    }
    .orders-create-footer button {
        padding: 0 11px;
    }
}
@media (max-width: 420px) {
    .orders-create-head h2 {
        font-size: 21px;
    }
    .orders-create-head p {
        font-size: 11px;
    }
    .orders-create-section-title {
        align-items: flex-start;
    }
    .orders-create-count {
        font-size: 10px;
    }
    .orders-cart-line {
        grid-template-columns: 32px minmax(0, 1fr) auto;
        gap: 8px;
        padding: 11px;
    }
    .orders-cart-product-icon {
        width: 32px;
        height: 32px;
    }
    .orders-cart-line-total {
        grid-column: 2;
    }
    .orders-remove-item {
        grid-column: 3;
        grid-row: 1 / span 2;
    }
    .orders-total strong {
        font-size: 19px;
    }
}

/* Focused Create Order fixes: product picker and visible submit action */
.orders-product-picker {
    position: relative;
}
.orders-product-picker-trigger {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 68px;
    padding: 10px 13px;
    border: 1px solid #dce4ee;
    border-radius: 13px;
    background: #fff;
    color: #101828;
    text-align: left;
    transition:
        border-color 0.16s ease,
        box-shadow 0.16s ease,
        background 0.16s ease;
}
.orders-product-picker-trigger:hover:not(:disabled),
.orders-product-picker-trigger[aria-expanded='true'] {
    border-color: #10a879;
    box-shadow: 0 0 0 3px rgb(16 168 121 / 8%);
}
.orders-product-picker-trigger:disabled {
    cursor: not-allowed;
    background: #f2f4f7;
    color: #98a2b3;
}
.orders-product-thumb {
    overflow: hidden;
    display: grid;
    place-items: center;
    flex: 0 0 46px;
    width: 46px;
    height: 46px;
    border: 1px solid #d1fae5;
    border-radius: 12px;
    background: linear-gradient(145deg, #ecfdf5, #f8fffb);
    color: #059669;
}
.orders-product-thumb img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.orders-product-trigger-copy,
.orders-product-option-copy {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}
.orders-product-trigger-copy strong,
.orders-product-option-copy strong {
    overflow-wrap: anywhere;
    color: #101828;
    font-size: 12px;
    font-weight: 750;
}
.orders-product-trigger-copy small,
.orders-product-option-copy small,
.orders-product-option-price small {
    color: #667085;
    font-size: 10px;
    font-weight: 500;
}
.orders-product-trigger-price {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    gap: 4px;
    color: #047857;
    font-size: 13px;
    font-weight: 800;
    text-align: right;
}
.orders-product-trigger-price small {
    color: #667085;
    font-size: 10px;
    font-weight: 600;
}
.orders-product-options {
    position: absolute;
    z-index: 20;
    top: calc(100% + 7px);
    left: 0;
    right: 0;
    max-height: 290px;
    overflow-y: auto;
    padding: 6px;
    border: 1px solid #e1e8f0;
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 18px 42px rgb(15 23 42 / 16%);
}
.orders-product-option {
    display: flex;
    align-items: center;
    gap: 11px;
    width: 100%;
    padding: 10px;
    border: 1px solid transparent;
    border-radius: 11px;
    background: #fff;
    color: #101828;
    text-align: left;
    transition:
        background 0.15s ease,
        border-color 0.15s ease;
}
.orders-product-option + .orders-product-option {
    margin-top: 3px;
}
.orders-product-option:hover,
.orders-product-option.is-selected {
    border-color: #ccefe2;
    background: #f4fcf8;
}
.orders-product-option-price {
    display: flex;
    align-items: flex-end;
    flex: 0 0 110px;
    flex-direction: column;
    gap: 4px;
    text-align: right;
}
.orders-product-option-price strong {
    color: #047857;
    font-size: 13px;
    font-weight: 800;
    white-space: nowrap;
}
.orders-product-option-price svg {
    color: #059669;
}
.orders-product-stock {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    align-self: flex-start;
    margin-top: 3px;
    color: #047857;
    font-size: 10px;
    font-weight: 700;
}
.orders-product-stock.is-low {
    color: #b45309;
}
.orders-product-stock.is-empty {
    color: #dc2626;
}
.orders-product-no-options {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 22px 12px;
    color: #667085;
    font-size: 12px;
}
.orders-create-footer {
    min-width: 0;
}
.orders-create-footer > div:last-child {
    flex: 0 0 auto;
    min-width: max-content;
}
.orders-create-footer .orders-primary {
    min-width: 225px;
    min-height: 46px;
    padding: 0 18px;
    border: 1px solid #059669;
    background: #059669;
    color: #fff;
    opacity: 1;
}
.orders-create-footer .orders-primary:disabled {
    border-color: #9bd8c2;
    background: #9bd8c2;
    color: #fff;
    opacity: 1;
}
.orders-create-footer .orders-primary:not(:disabled):hover {
    border-color: #047857;
    background: #047857;
}
@media (max-width: 760px) {
    .orders-create-footer > div:last-child {
        min-width: 0;
        width: 100%;
    }
    .orders-create-footer .orders-primary {
        min-width: 0;
    }
    .orders-product-trigger-price {
        display: none;
    }
    .orders-product-option-price {
        flex-basis: 88px;
    }
}

/* Customer directory lookup in Create Order; scoped to this form only. */
.customer-picker {
    min-width: 0;
    position: relative;
}
.customer-search-row {
    display: flex;
    gap: 8px;
    align-items: stretch;
}
.customer-search-row input {
    flex: 1;
    min-width: 0;
}
.customer-search-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-width: 100px;
    padding: 0 14px;
    border: 1px solid #dbe3ee;
    border-radius: 11px;
    background: #fff;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}
.customer-search-button:disabled {
    opacity: 0.6;
    cursor: wait;
}
.customer-results {
    display: grid;
    gap: 5px;
    margin-top: 8px;
    padding: 6px;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
    background: #fff;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
    max-height: 220px;
    overflow: auto;
}
.customer-result {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 9px;
    border: 0;
    border-radius: 9px;
    text-align: left;
    background: transparent;
    cursor: pointer;
}
.customer-result:hover,
.customer-result:focus-visible {
    background: #f1f5f9;
    outline: none;
}
.customer-avatar {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    border-radius: 10px;
    background: #e0f2fe;
    color: #0369a1;
}
.customer-result-main,
.customer-result-meta {
    display: grid;
    gap: 3px;
    min-width: 0;
}
.customer-result-main {
    flex: 1;
}
.customer-result strong {
    color: #0f172a;
    font-size: 12px;
}
.customer-result small {
    color: #64748b;
    font-size: 10px;
    overflow-wrap: anywhere;
}
.customer-result-meta {
    justify-items: end;
}
.customer-lookup-message {
    margin: 7px 2px 0;
    color: #64748b;
    font-size: 11px;
}
.customer-selected {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 8px;
    padding: 9px 11px;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    background: #f0fdf4;
    color: #166534;
    font-size: 11px;
    font-weight: 700;
}
.customer-selected button,
.customer-manual-toggle {
    border: 0;
    background: transparent;
    color: #0369a1;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}
.customer-selected button {
    margin-left: auto;
}
.customer-manual-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    padding: 2px 0;
}
@media (max-width: 560px) {
    .customer-search-row {
        flex-direction: column;
    }
    .customer-search-button {
        min-height: 38px;
    }
    .customer-result-meta {
        display: none;
    }
}
</style>
