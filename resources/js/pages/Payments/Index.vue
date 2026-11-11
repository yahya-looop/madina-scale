<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface Props { userName?: string; userEmail?: string }
interface Location { id: number; name: string; access_level?: string }
interface CashAccount { id: number; code: string; name: string }
interface Order { id: number; order_number: string; customer_id: number | null; customer_name: string; grand_total: string; paid_amount: string; due_amount: string; location_id: number }
interface Payment { id: number; transaction_number: string; transaction_date: string; amount: string; allocated_amount: string; unallocated_amount: string; payment_method: string; reference?: string | null; description: string; customer?: { name: string } | null; cash_account?: CashAccount; location?: Location }
const props = withDefaults(defineProps<Props>(), { userName: '', userEmail: '' });
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const payments = ref<Payment[]>([]);
const locations = ref<Location[]>([]);
const cashAccounts = ref<CashAccount[]>([]);
const orders = ref<Order[]>([]);
const search = ref('');
const filterLocation = ref('');
const showModal = ref(false);
const form = reactive({
    order_id: '', amount: '', payment_method: 'cash', cash_account_id: '',
    transaction_date: new Date().toISOString().slice(0, 10), reference: '', description: 'Customer payment received',
});
const selectedOrder = computed(() => orders.value.find((o) => String(o.id) === form.order_id) ?? null);
const money = (value: string | number) => new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT' }).format(Number(value || 0));
const csrf = () => decodeURIComponent(document.cookie.split('; ').find((v) => v.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
async function api<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': csrf(), ...(options.headers ?? {}) },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const validation = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
        throw new Error(validation || payload.message || 'Request failed.');
    }
    return payload as T;
}
async function load(): Promise<void> {
    loading.value = true; error.value = '';
    try {
        const query = new URLSearchParams();
        if (filterLocation.value) query.set('location_id', filterLocation.value);
        if (search.value.trim()) query.set('search', search.value.trim());
        const [list, options] = await Promise.all([
            api<{ data: Payment[] }>(`/api/v1/backend/payments?${query.toString()}`),
            api<{ data: { locations: Location[]; cash_accounts: CashAccount[]; orders: Order[] } }>(`/api/v1/backend/payments/options${filterLocation.value ? `?location_id=${filterLocation.value}` : ''}`),
        ]);
        payments.value = list.data;
        locations.value = options.data.locations;
        cashAccounts.value = options.data.cash_accounts;
        orders.value = options.data.orders;
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to load payments.'; }
    finally { loading.value = false; }
}
function openReceipt(): void {
    Object.assign(form, { order_id: '', amount: '', payment_method: 'cash', cash_account_id: cashAccounts.value[0] ? String(cashAccounts.value[0].id) : '', transaction_date: new Date().toISOString().slice(0, 10), reference: '', description: 'Customer payment received' });
    showModal.value = true;
}
async function saveReceipt(): Promise<void> {
    if (!selectedOrder.value) { error.value = 'Select a sales order first.'; return; }
    if (Number(form.amount) <= 0) { error.value = 'Enter a payment amount greater than zero.'; return; }
    saving.value = true; error.value = ''; success.value = '';
    try {
        const idempotencyKey = `PAY-${crypto.randomUUID()}`;
        const receipt = await api<{ data: { id: number } }>('/api/v1/backend/payments/receipts', {
            method: 'POST',
            body: JSON.stringify({
                idempotency_key: idempotencyKey,
                transaction_date: form.transaction_date,
                amount: form.amount,
                payment_method: form.payment_method,
                cash_account_id: Number(form.cash_account_id),
                customer_id: selectedOrder.value.customer_id,
                reference: form.reference || null,
                description: form.description,
                location_id: selectedOrder.value.location_id,
            }),
        });
        const allocationAmount = Math.min(Number(form.amount), Number(selectedOrder.value.due_amount));
        if (allocationAmount > 0) {
            await api(`/api/v1/backend/payments/${receipt.data.id}/allocations`, {
                method: 'POST',
                body: JSON.stringify({ sales_order_id: selectedOrder.value.id, amount: allocationAmount.toFixed(2) }),
            });
        }
        showModal.value = false;
        success.value = allocationAmount < Number(form.amount)
            ? 'Payment recorded. The excess remains as an unallocated customer advance.'
            : 'Payment recorded and allocated to the selected sales order.';
        await load();
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to record payment.'; }
    finally { saving.value = false; }
}
const filteredPayments = computed(() => payments.value);
onMounted(load);
</script>

<template>
    <Head title="Payments" />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <div class="payments-page space-y-5">
            <div class="erp-page-header flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div class="erp-page-heading">
                    <nav class="erp-page-breadcrumb" aria-label="Breadcrumb"><span>Workspace</span><span aria-hidden="true">›</span><span>Payments</span></nav>
                    <div class="erp-page-title-line"><span class="erp-page-title-icon"><Icon name="payments" :size="20" /></span><div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600">RECEIVABLES WORKSPACE</p>
                    <h1>Payments</h1>
                    <p class="text-sm text-slate-500">Receive customer payments, allocate them to sales orders and track outstanding advances.</p>
                    </div></div>
                </div>
                <div class="flex gap-2">
                    <button class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="load">Refresh</button>
                    <button class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700" @click="openReceipt">Receive payment</button>
                </div>
            </div>

            <div v-if="error" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</div>
            <div v-if="success" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ success }}</div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Payment records</p><p class="mt-2 text-3xl font-bold text-slate-900">{{ filteredPayments.length }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Received amount</p><p class="mt-2 text-2xl font-bold text-slate-900">{{ money(payments.reduce((sum, p) => sum + Number(p.amount), 0)) }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Unallocated advances</p><p class="mt-2 text-2xl font-bold text-slate-900">{{ money(payments.reduce((sum, p) => sum + Number(p.unallocated_amount), 0)) }}</p></div>
            </div>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><h2 class="font-semibold text-slate-900">Customer payment register</h2><p class="mt-1 text-xs text-slate-500">Receipts are posted to the general ledger as customer advances.</p></div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input v-model="search" @keyup.enter="load" placeholder="Search payment or customer..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                        <select v-model="filterLocation" @change="load" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">All accessible locations</option><option v-for="location in locations" :key="location.id" :value="String(location.id)">{{ location.name }}</option></select>
                        <button class="rounded-lg border border-slate-200 px-3 py-2 text-sm" @click="load">Apply</button>
                    </div>
                </div>
                <div v-if="loading" class="p-8 text-center text-sm text-slate-500">Loading payments…</div>
                <div v-else-if="!payments.length" class="p-10 text-center"><p class="font-semibold text-slate-700">No payment records found</p><p class="mt-1 text-sm text-slate-500">Record the first customer payment to get started.</p></div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Payment</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Method / Account</th><th class="px-4 py-3 text-right">Received</th><th class="px-4 py-3 text-right">Allocated</th><th class="px-4 py-3 text-right">Unallocated</th><th class="px-4 py-3">Date</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="payment in payments" :key="payment.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3"><p class="font-semibold text-slate-800">{{ payment.transaction_number }}</p><p class="mt-1 text-xs text-slate-500">{{ payment.reference || payment.description }}</p></td>
                                <td class="px-4 py-3 text-slate-700">{{ payment.customer?.name || '—' }}</td>
                                <td class="px-4 py-3"><p class="capitalize text-slate-700">{{ payment.payment_method.replace('_', ' ') }}</p><p class="text-xs text-slate-500">{{ payment.cash_account?.name || '—' }}</p></td>
                                <td class="px-4 py-3 text-right font-semibold">{{ money(payment.amount) }}</td>
                                <td class="px-4 py-3 text-right text-emerald-700">{{ money(payment.allocated_amount) }}</td>
                                <td class="px-4 py-3 text-right text-amber-700">{{ money(payment.unallocated_amount) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ payment.transaction_date }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <p class="text-xs leading-5 text-slate-500">Accounting note: customer receipts are recorded as liabilities under Customer Advances. Allocation links the receipt to an order for operational tracking; it does not recognize sales revenue or alter the posted ledger entry. Refunds and payment reversals remain controlled through the Sales Returns / Finance workflows.</p>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-4">
            <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between"><div><h2 class="text-xl font-bold text-slate-900">Receive customer payment</h2><p class="mt-1 text-sm text-slate-500">Select an order to link the receipt to its customer and location.</p></div><button class="text-2xl text-slate-400" @click="showModal = false">×</button></div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="sm:col-span-2"><span class="mb-1 block text-sm font-medium text-slate-700">Sales order</span><select v-model="form.order_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5"><option value="">Select order</option><option v-for="order in orders" :key="order.id" :value="String(order.id)">{{ order.order_number }} · {{ order.customer_name }} · Due {{ money(order.due_amount) }}</option></select></label>
                    <div v-if="selectedOrder" class="sm:col-span-2 rounded-lg bg-slate-50 p-3 text-sm"><div class="flex justify-between"><span class="text-slate-500">Order total</span><strong>{{ money(selectedOrder.grand_total) }}</strong></div><div class="mt-1 flex justify-between"><span class="text-slate-500">Previously paid</span><strong>{{ money(selectedOrder.paid_amount) }}</strong></div><div class="mt-1 flex justify-between"><span class="text-slate-500">Outstanding</span><strong class="text-emerald-700">{{ money(selectedOrder.due_amount) }}</strong></div></div>
                    <label><span class="mb-1 block text-sm font-medium text-slate-700">Amount (BDT)</span><input v-model="form.amount" type="number" min="0.01" step="0.01" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5" /></label>
                    <label><span class="mb-1 block text-sm font-medium text-slate-700">Payment date</span><input v-model="form.transaction_date" type="date" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5" /></label>
                    <label><span class="mb-1 block text-sm font-medium text-slate-700">Payment method</span><select v-model="form.payment_method" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"><option value="cash">Cash</option><option value="bank">Bank transfer</option><option value="mobile_banking">Mobile banking</option><option value="card">Card</option><option value="other">Other</option></select></label>
                    <label><span class="mb-1 block text-sm font-medium text-slate-700">Receive into</span><select v-model="form.cash_account_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5"><option value="">Select account</option><option v-for="account in cashAccounts" :key="account.id" :value="String(account.id)">{{ account.code }} · {{ account.name }}</option></select></label>
                    <label><span class="mb-1 block text-sm font-medium text-slate-700">Reference</span><input v-model="form.reference" placeholder="Transaction / cheque reference" class="w-full rounded-lg border border-slate-300 px-3 py-2.5" /></label>
                    <label><span class="mb-1 block text-sm font-medium text-slate-700">Description</span><input v-model="form.description" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5" /></label>
                </div>
                <div class="mt-6 flex justify-end gap-2"><button class="rounded-lg border border-slate-200 px-4 py-2 text-sm" @click="showModal = false">Cancel</button><button :disabled="saving || !selectedOrder || !form.cash_account_id" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="saveReceipt">{{ saving ? 'Saving…' : 'Record payment' }}</button></div>
            </div>
        </div>
    </ErpLayout>
</template>
