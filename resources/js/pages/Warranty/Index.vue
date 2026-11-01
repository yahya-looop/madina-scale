<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface Props { userName?: string; userEmail?: string; canCreate?: boolean; canUpdate?: boolean; canProcess?: boolean }
interface Location { id: number; name: string }
interface OrderItemOption { id: number; label: string; customer_id: number | null; customer_name: string; location_id: number; sales_order_id: number }
interface Technician { id: number; name: string }
interface PartOption { id: number; label: string }
interface CustomerOption { id: number; name: string; phone: string | null }
interface Warranty { id: number; customer_id: number; location_id: number; warranty_number: string; serial_number: string | null; warranty_start: string; warranty_end: string; status: string; display_status?: string; customer_name: string; customer_phone: string | null; location_name: string; order_number: string; product_name: string; variant_name: string | null; sku: string }
interface Ticket { id: number; assigned_to: number | null; ticket_number: string; status: string; priority: string; issue: string; diagnosis: string | null; resolution: string | null; customer_name: string; customer_phone: string | null; location_name: string; technician_name: string | null; warranty_number: string | null; received_at: string }
const props = withDefaults(defineProps<Props>(), { userName: '', userEmail: '', canCreate: false, canUpdate: false, canProcess: false });
const loading = ref(false), saving = ref(false), error = ref(''), success = ref('');
const tab = ref<'warranties' | 'tickets'>('warranties');
const warranties = ref<Warranty[]>([]), tickets = ref<Ticket[]>([]);
const locations = ref<Location[]>([]), orderItems = ref<OrderItemOption[]>([]), technicians = ref<Technician[]>([]), parts = ref<PartOption[]>([]), customers = ref<CustomerOption[]>([]);
const search = ref(''), locationId = ref('');
const showWarranty = ref(false), showTicket = ref(false), editingTicket = ref<Ticket | null>(null), partsTicket = ref<Ticket | null>(null);
const warrantyForm = reactive({ sales_order_item_id: '', serial_number: '', warranty_months: '12', warranty_start: new Date().toISOString().slice(0,10), notes: '' });
const ticketForm = reactive({ warranty_id: '', customer_id: '', location_id: '', issue: '', priority: 'normal', assigned_to: '', received_at: new Date().toISOString().slice(0,16) });
const ticketEdit = reactive({ status: 'open', priority: 'normal', assigned_to: '', diagnosis: '', resolution: '' });
const partForm = reactive({ product_variant_id: '', quantity: '1', notes: '' });
const selectedItem = computed(() => orderItems.value.find(x => String(x.id) === warrantyForm.sales_order_item_id) ?? null);
const selectedWarranty = computed(() => warranties.value.find(x => String(x.id) === ticketForm.warranty_id) ?? null);
watch(() => ticketForm.warranty_id, () => {
    if (selectedWarranty.value) {
        ticketForm.customer_id = String(selectedWarranty.value.customer_id);
        ticketForm.location_id = String(selectedWarranty.value.location_id);
    }
});
const moneyDate = (v: string) => v ? new Date(v).toLocaleDateString() : '—';
const csrf = () => decodeURIComponent(document.cookie.split('; ').find(v => v.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
async function api<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, { credentials: 'same-origin', ...options, headers: {
        Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': csrf(), ...(options.headers ?? {}),
    }});
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const message = payload.errors ? Object.values(payload.errors).flat().join(' ') : payload.message;
        throw new Error(message || 'Request failed.');
    }
    return payload as T;
}
async function load(): Promise<void> {
    loading.value = true; error.value = '';
    try {
        const q = new URLSearchParams();
        q.set('per_page', '100');
        if (search.value.trim()) q.set('search', search.value.trim());
        if (locationId.value) q.set('location_id', locationId.value);
        const [records, options] = await Promise.all([
            api<{ warranties: { data: Warranty[] }; tickets: { data: Ticket[] } }>(`/api/warranty-service?${q}`),
            api<{ locations: Location[]; order_items: OrderItemOption[]; technicians: Technician[]; parts: PartOption[]; customers: CustomerOption[] }>(`/api/warranty-service/options`),
        ]);
        warranties.value = records.warranties.data; tickets.value = records.tickets.data;
        locations.value = options.locations; orderItems.value = options.order_items; technicians.value = options.technicians; parts.value = options.parts; customers.value = options.customers;
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to load warranty records.'; }
    finally { loading.value = false; }
}
function openWarranty(): void {
    Object.assign(warrantyForm, { sales_order_item_id: '', serial_number: '', warranty_months: '12', warranty_start: new Date().toISOString().slice(0,10), notes: '' });
    showWarranty.value = true;
}
async function saveWarranty(): Promise<void> {
    if (!selectedItem.value) { error.value = 'Select a fulfilled sales order item.'; return; }
    saving.value = true; error.value = '';
    try {
        await api('/api/warranty-service/warranties', { method: 'POST', body: JSON.stringify({ ...warrantyForm, sales_order_item_id: Number(warrantyForm.sales_order_item_id), warranty_months: Number(warrantyForm.warranty_months), serial_number: warrantyForm.serial_number || null, notes: warrantyForm.notes || null }) });
        showWarranty.value = false; success.value = 'Warranty registered successfully.'; await load();
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to register warranty.'; }
    finally { saving.value = false; }
}
function openTicket(): void {
    Object.assign(ticketForm, { warranty_id: '', customer_id: '', location_id: '', issue: '', priority: 'normal', assigned_to: '', received_at: new Date().toISOString().slice(0,16) });
    showTicket.value = true;
}
async function saveTicket(): Promise<void> {
    saving.value = true; error.value = '';
    try {
        await api('/api/warranty-service/tickets', { method: 'POST', body: JSON.stringify({ ...ticketForm, warranty_id: ticketForm.warranty_id ? Number(ticketForm.warranty_id) : null, customer_id: Number(ticketForm.customer_id), location_id: Number(ticketForm.location_id), assigned_to: ticketForm.assigned_to ? Number(ticketForm.assigned_to) : null, received_at: new Date(ticketForm.received_at).toISOString() }) });
        showTicket.value = false; success.value = 'Service ticket created.'; tab.value = 'tickets'; await load();
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to create service ticket.'; }
    finally { saving.value = false; }
}
function editTicket(ticket: Ticket): void {
    editingTicket.value = ticket;
    Object.assign(ticketEdit, { status: ticket.status, priority: ticket.priority, assigned_to: ticket.assigned_to ? String(ticket.assigned_to) : '', diagnosis: ticket.diagnosis ?? '', resolution: ticket.resolution ?? '' });
}
function openParts(ticket: Ticket): void {
    partsTicket.value = ticket;
    Object.assign(partForm, { product_variant_id: '', quantity: '1', notes: '' });
}
async function issuePart(): Promise<void> {
    if (!partsTicket.value) return;
    saving.value = true; error.value = '';
    try {
        await api(`/api/warranty-service/tickets/${partsTicket.value.id}/parts`, { method: 'POST', body: JSON.stringify({
            product_variant_id: Number(partForm.product_variant_id), quantity: partForm.quantity,
            notes: partForm.notes || null, idempotency_key: crypto.randomUUID(),
        }) });
        partsTicket.value = null; success.value = 'Spare part issued and inventory movement recorded.'; await load();
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to issue spare part.'; }
    finally { saving.value = false; }
}
async function updateTicket(): Promise<void> {
    if (!editingTicket.value) return;
    saving.value = true; error.value = '';
    try {
        await api(`/api/warranty-service/tickets/${editingTicket.value.id}`, { method: 'PUT', body: JSON.stringify({ ...ticketEdit, assigned_to: ticketEdit.assigned_to ? Number(ticketEdit.assigned_to) : null }) });
        editingTicket.value = null; success.value = 'Service ticket updated.'; await load();
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to update ticket.'; }
    finally { saving.value = false; }
}
onMounted(load);
</script>

<template>
    <Head title="Warranty & Service" />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <div class="warranty-page space-y-5">
            <div class="erp-page-header flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div class="erp-page-heading"><nav class="erp-page-breadcrumb" aria-label="Breadcrumb"><span>Workspace</span><span aria-hidden="true">›</span><span>Warranty & Service</span></nav><div class="erp-page-title-line"><span class="erp-page-title-icon"><Icon name="service" :size="20" /></span><div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-600">AFTER-SALES OPERATIONS</p><h1>Warranty & Service</h1><p class="text-sm text-slate-500">Register product warranties and manage repair/service tickets.</p></div></div></div>
                <div class="flex flex-wrap gap-2"><button v-if="props.canCreate" class="rounded-xl border border-emerald-200 px-4 py-2.5 text-sm font-semibold text-emerald-700" @click="openWarranty">Register warranty</button><button v-if="props.canCreate" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white" @click="openTicket">Create service ticket</button></div>
            </div>
            <div v-if="error" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</div>
            <div v-if="success" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ success }}</div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border bg-white p-5"><p class="text-sm text-slate-500">Warranty records</p><p class="mt-2 text-3xl font-bold">{{ warranties.length }}</p></div>
                <div class="rounded-2xl border bg-white p-5"><p class="text-sm text-slate-500">Service tickets</p><p class="mt-2 text-3xl font-bold">{{ tickets.length }}</p></div>
                <div class="rounded-2xl border bg-white p-5"><p class="text-sm text-slate-500">Active repairs</p><p class="mt-2 text-3xl font-bold">{{ tickets.filter(t => ['diagnosing','waiting_parts','in_repair'].includes(t.status)).length }}</p></div>
            </div>
            <section class="overflow-hidden rounded-2xl border bg-white">
                <div class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex gap-2"><button class="rounded-lg px-4 py-2 text-sm font-semibold" :class="tab === 'warranties' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'" @click="tab='warranties'">Warranties</button><button class="rounded-lg px-4 py-2 text-sm font-semibold" :class="tab === 'tickets' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'" @click="tab='tickets'">Service tickets</button></div>
                    <div class="flex flex-col gap-2 sm:flex-row"><input v-model="search" placeholder="Search serial, customer, ticket..." class="rounded-lg border px-3 py-2 text-sm" @keyup.enter="load"><select v-model="locationId" class="rounded-lg border px-3 py-2 text-sm" @change="load"><option value="">All accessible locations</option><option v-for="loc in locations" :key="loc.id" :value="String(loc.id)">{{ loc.name }}</option></select><button class="rounded-lg border px-3 py-2 text-sm" @click="load">Refresh</button></div>
                </div>
                <div v-if="loading" class="p-10 text-center text-sm text-slate-500">Loading records…</div>
                <div v-else-if="tab==='warranties'" class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Warranty</th><th class="px-4 py-3">Product / Serial</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Location</th><th class="px-4 py-3">Coverage</th><th class="px-4 py-3">Status</th></tr></thead><tbody class="divide-y"><tr v-for="w in warranties" :key="w.id"><td class="px-4 py-3 font-semibold">{{ w.warranty_number }}<p class="text-xs font-normal text-slate-500">{{ w.order_number }}</p></td><td class="px-4 py-3">{{ w.product_name }} {{ w.variant_name }}<p class="text-xs text-slate-500">{{ w.serial_number || 'Serial not recorded' }} · {{ w.sku }}</p></td><td class="px-4 py-3">{{ w.customer_name }}<p class="text-xs text-slate-500">{{ w.customer_phone }}</p></td><td class="px-4 py-3">{{ w.location_name }}</td><td class="px-4 py-3">{{ moneyDate(w.warranty_start) }} – {{ moneyDate(w.warranty_end) }}</td><td class="px-4 py-3"><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">{{ w.display_status || w.status }}</span></td></tr><tr v-if="!warranties.length"><td colspan="6" class="p-10 text-center text-slate-500">No warranty records found.</td></tr></tbody></table>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Ticket</th><th class="px-4 py-3">Customer / Warranty</th><th class="px-4 py-3">Issue</th><th class="px-4 py-3">Technician</th><th class="px-4 py-3">Priority</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Action</th></tr></thead><tbody class="divide-y"><tr v-for="t in tickets" :key="t.id"><td class="px-4 py-3 font-semibold">{{ t.ticket_number }}<p class="text-xs font-normal text-slate-500">{{ moneyDate(t.received_at) }}</p></td><td class="px-4 py-3">{{ t.customer_name }}<p class="text-xs text-slate-500">{{ t.warranty_number || 'No warranty linked' }}</p></td><td class="max-w-sm px-4 py-3">{{ t.issue }}</td><td class="px-4 py-3">{{ t.technician_name || 'Unassigned' }}</td><td class="px-4 py-3 capitalize">{{ t.priority }}</td><td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs">{{ t.status.replaceAll('_',' ') }}</span></td><td class="px-4 py-3"><div class="flex gap-1"><button v-if="props.canUpdate || props.canProcess" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" @click="editTicket(t)">Manage</button><button v-if="props.canProcess && !['closed','rejected'].includes(t.status)" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" @click="openParts(t)">Parts</button></div></td></tr><tr v-if="!tickets.length"><td colspan="7" class="p-10 text-center text-slate-500">No service tickets found.</td></tr></tbody></table>
                </div>
            </section>
            <div v-if="showWarranty" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"><form class="w-full max-w-xl space-y-4 rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="saveWarranty"><div><h2 class="text-xl font-bold">Register product warranty</h2><p class="text-sm text-slate-500">Only fulfilled/completed sales order items are eligible.</p></div><label class="block text-sm font-medium">Sales order item<select v-model="warrantyForm.sales_order_item_id" required class="mt-1 w-full rounded-lg border p-2"><option value="">Select item</option><option v-for="item in orderItems" :key="item.id" :value="String(item.id)">{{ item.label }}</option></select></label><div class="grid gap-3 sm:grid-cols-2"><label class="block text-sm">Serial number<input v-model="warrantyForm.serial_number" class="mt-1 w-full rounded-lg border p-2" maxlength="120"></label><label class="block text-sm">Warranty months<input v-model="warrantyForm.warranty_months" type="number" min="1" max="120" required class="mt-1 w-full rounded-lg border p-2"></label><label class="block text-sm">Start date<input v-model="warrantyForm.warranty_start" type="date" required class="mt-1 w-full rounded-lg border p-2"></label></div><label class="block text-sm">Notes<textarea v-model="warrantyForm.notes" rows="2" class="mt-1 w-full rounded-lg border p-2"></textarea></label><div class="flex justify-end gap-2"><button type="button" class="rounded-lg border px-4 py-2" @click="showWarranty=false">Cancel</button><button :disabled="saving" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white">Save warranty</button></div></form></div>
            <div v-if="showTicket" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"><form class="w-full max-w-xl space-y-4 rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="saveTicket"><h2 class="text-xl font-bold">Create service ticket</h2><label class="block text-sm">Warranty (optional)<select v-model="ticketForm.warranty_id" class="mt-1 w-full rounded-lg border p-2"><option value="">No warranty linked</option><option v-for="w in warranties" :key="w.id" :value="String(w.id)">{{ w.warranty_number }} — {{ w.customer_name }}</option></select></label><div class="grid gap-3 sm:grid-cols-2"><label class="block text-sm">Customer<select v-model="ticketForm.customer_id" required class="mt-1 w-full rounded-lg border p-2"><option value="">Select customer</option><option v-for="customer in customers" :key="customer.id" :value="String(customer.id)">{{ customer.name }} · {{ customer.phone || 'No phone' }}</option></select></label><label class="block text-sm">Location<select v-model="ticketForm.location_id" required class="mt-1 w-full rounded-lg border p-2"><option value="">Select location</option><option v-for="loc in locations" :key="loc.id" :value="String(loc.id)">{{ loc.name }}</option></select></label><label class="block text-sm">Priority<select v-model="ticketForm.priority" class="mt-1 w-full rounded-lg border p-2"><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></label><label class="block text-sm">Assign technician<select v-model="ticketForm.assigned_to" class="mt-1 w-full rounded-lg border p-2"><option value="">Unassigned</option><option v-for="tech in technicians" :key="tech.id" :value="String(tech.id)">{{ tech.name }}</option></select></label></div><label class="block text-sm">Received at<input v-model="ticketForm.received_at" type="datetime-local" required class="mt-1 w-full rounded-lg border p-2"></label><label class="block text-sm">Issue description<textarea v-model="ticketForm.issue" required minlength="5" rows="3" class="mt-1 w-full rounded-lg border p-2"></textarea></label><div class="flex justify-end gap-2"><button type="button" class="rounded-lg border px-4 py-2" @click="showTicket=false">Cancel</button><button :disabled="saving" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white">Create ticket</button></div></form></div>
            <div v-if="partsTicket" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"><form class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="issuePart"><h2 class="text-xl font-bold">Issue spare part — {{ partsTicket.ticket_number }}</h2><p class="text-sm text-slate-500">The selected part will be issued from this ticket's location inventory.</p><label class="block text-sm">Product / variant<select v-model="partForm.product_variant_id" required class="mt-1 w-full rounded-lg border p-2"><option value="">Select part</option><option v-for="part in parts" :key="part.id" :value="String(part.id)">{{ part.label }}</option></select></label><label class="block text-sm">Quantity<input v-model="partForm.quantity" type="number" min="0.0001" step="0.0001" required class="mt-1 w-full rounded-lg border p-2"></label><label class="block text-sm">Notes<textarea v-model="partForm.notes" rows="2" class="mt-1 w-full rounded-lg border p-2"></textarea></label><div class="flex justify-end gap-2"><button type="button" class="rounded-lg border px-4 py-2" @click="partsTicket=null">Cancel</button><button :disabled="saving" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white">Issue part</button></div></form></div>
            <div v-if="editingTicket" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"><form class="w-full max-w-xl space-y-4 rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="updateTicket"><h2 class="text-xl font-bold">Manage {{ editingTicket.ticket_number }}</h2><div class="grid gap-3 sm:grid-cols-2"><label class="block text-sm">Status<select v-model="ticketEdit.status" class="mt-1 w-full rounded-lg border p-2"><option v-for="s in ['open','diagnosing','waiting_parts','in_repair','resolved','closed','rejected']" :key="s" :value="s">{{ s.replaceAll('_',' ') }}</option></select></label><label class="block text-sm">Priority<select v-model="ticketEdit.priority" class="mt-1 w-full rounded-lg border p-2"><option v-for="s in ['low','normal','high','urgent']" :key="s" :value="s">{{ s }}</option></select></label><label class="block text-sm">Technician<select v-model="ticketEdit.assigned_to" class="mt-1 w-full rounded-lg border p-2"><option value="">Keep current / unassigned</option><option v-for="tech in technicians" :key="tech.id" :value="String(tech.id)">{{ tech.name }}</option></select></label></div><label class="block text-sm">Diagnosis<textarea v-model="ticketEdit.diagnosis" rows="2" class="mt-1 w-full rounded-lg border p-2"></textarea></label><label class="block text-sm">Resolution (required to resolve/close)<textarea v-model="ticketEdit.resolution" rows="2" class="mt-1 w-full rounded-lg border p-2"></textarea></label><div class="flex justify-end gap-2"><button type="button" class="rounded-lg border px-4 py-2" @click="editingTicket=null">Cancel</button><button :disabled="saving" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white">Save changes</button></div></form></div>
        </div>
    </ErpLayout>
</template>
