<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Props { userName?: string; userEmail?: string }
interface LocationOption { id: number; name: string; code?: string }
interface VariantOption { id: number; product_id: number; name: string; sku?: string | null; price: string | number; available: string | number }
interface OrderItem { id: number; product_name: string; variant_name?: string | null; sku?: string | null; quantity: string | number; unit_price: string | number; line_total: string | number }
interface Order { id: number; order_number: string; status: "pending" | "confirmed" | "cancelled"; customer_name: string; customer_phone?: string | null; grand_total: string | number; created_at: string; location?: LocationOption; items?: OrderItem[] }
const props = withDefaults(defineProps<Props>(), { userName: "", userEmail: "" });
const rows = ref<Order[]>([]);
const locations = ref<LocationOption[]>([]);
const variants = ref<VariantOption[]>([]);
const loading = ref(false);
const saving = ref(false);
const error = ref("");
const notice = ref("");
const search = ref("");
const status = ref("all");
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const createOpen = ref(false);
const detail = ref<Order | null>(null);
const cancelTarget = ref<Order | null>(null);
const cancelReason = ref("");
const locationId = ref("");
const customerName = ref("");
const customerPhone = ref("");
const customerEmail = ref("");
const notes = ref("");
const lineVariantId = ref("");
const lineQuantity = ref("1");
const cart = ref<Array<{ product_variant_id: number; quantity: number; name: string; sku?: string | null; price: number; available: number }>>([]);
const selectedVariant = computed(() => variants.value.find(v => String(v.id) === lineVariantId.value));
const subtotal = computed(() => cart.value.reduce((sum, line) => sum + line.price * line.quantity, 0));
const activeCount = computed(() => rows.value.filter(r => r.status === "pending" || r.status === "confirmed").length);
const currency = (value: string | number) => new Intl.NumberFormat("en-BD", { style: "currency", currency: "BDT", maximumFractionDigits: 2 }).format(Number(value) || 0);
const date = (value: string) => new Intl.DateTimeFormat("en-BD", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
function csrf(): string { return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ""; }
async function api(url: string, init: RequestInit = {}) {
  const response = await fetch(url, { credentials: "same-origin", ...init, headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", ...(init.body ? { "Content-Type": "application/json", "X-CSRF-TOKEN": csrf() } : {}), ...(init.headers || {}) } });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const first = payload.errors ? Object.values(payload.errors as Record<string, string[]>).flat()[0] : null;
    throw new Error(String(first || payload.message || `Request failed (${response.status}).`));
  }
  return payload;
}
async function load(target = page.value) {
  loading.value = true; error.value = "";
  try {
    const params = new URLSearchParams({ page: String(target), per_page: "15", status: status.value, search: search.value.trim() });
    const payload = await api(`/api/orders?${params}`);
    rows.value = payload.data || []; page.value = payload.current_page || target; lastPage.value = payload.last_page || 1; total.value = payload.total || 0;
  } catch (e) { error.value = e instanceof Error ? e.message : "Unable to load orders."; }
  finally { loading.value = false; }
}
async function loadOptions() {
  try { const payload = await api(`/api/orders/options${locationId.value ? `?location_id=${locationId.value}` : ""}`); locations.value = payload.locations || []; variants.value = payload.variants || []; }
  catch (e) { error.value = e instanceof Error ? e.message : "Unable to load order options."; }
}
function openCreate() {
  error.value = ""; notice.value = ""; customerName.value = ""; customerPhone.value = ""; customerEmail.value = ""; notes.value = ""; cart.value = []; lineVariantId.value = ""; lineQuantity.value = "1";
  createOpen.value = true; void loadOptions();
}
watch(locationId, () => { variants.value = []; lineVariantId.value = ""; void loadOptions(); });
function addLine() {
  const v = selectedVariant.value; const qty = Number(lineQuantity.value);
  if (!v) { error.value = "Choose a product variant."; return; }
  if (!Number.isFinite(qty) || qty <= 0) { error.value = "Quantity must be greater than zero."; return; }
  const available = Number(v.available);
  const existing = cart.value.find(l => l.product_variant_id === v.id);
  const nextQty = qty + (existing?.quantity || 0);
  if (nextQty > available) { error.value = `Only ${available} units are available for ${v.name}.`; return; }
  if (existing) existing.quantity = nextQty;
  else cart.value.push({ product_variant_id: v.id, quantity: qty, name: v.name, sku: v.sku, price: Number(v.price), available });
  lineVariantId.value = ""; lineQuantity.value = "1"; error.value = "";
}
function removeLine(id: number) { cart.value = cart.value.filter(line => line.product_variant_id !== id); }
async function createOrder() {
  if (saving.value) return;
  if (!locationId.value || !customerName.value.trim() || !cart.value.length) { error.value = "Choose a location, enter the customer name, and add at least one item."; return; }
  saving.value = true; error.value = "";
  try {
    const key = crypto.randomUUID();
    const payload = await api("/api/orders", { method: "POST", body: JSON.stringify({ idempotency_key: key, location_id: Number(locationId.value), customer_name: customerName.value.trim(), customer_phone: customerPhone.value.trim() || null, customer_email: customerEmail.value.trim() || null, notes: notes.value.trim() || null, items: cart.value.map(line => ({ product_variant_id: line.product_variant_id, quantity: line.quantity })) }) });
    createOpen.value = false; notice.value = `${payload.data.order_number} created and stock reserved.`; await load(1);
  } catch (e) { error.value = e instanceof Error ? e.message : "Unable to create order."; }
  finally { saving.value = false; }
}
async function openDetail(order: Order) {
  try { const payload = await api(`/api/orders/${order.id}`); detail.value = payload.data; }
  catch (e) { error.value = e instanceof Error ? e.message : "Unable to load order."; }
}
async function confirmOrder(order: Order) {
  if (!window.confirm(`Confirm ${order.order_number}?`)) return;
  try { const payload = await api(`/api/orders/${order.id}/confirm`, { method: "POST", body: "{}" }); notice.value = payload.message; await load(page.value); }
  catch (e) { error.value = e instanceof Error ? e.message : "Unable to confirm order."; }
}
async function cancelOrder() {
  const target = cancelTarget.value; if (!target || cancelReason.value.trim().length < 3) { error.value = "Please provide a cancellation reason (at least 3 characters)."; return; }
  saving.value = true;
  try { const payload = await api(`/api/orders/${target.id}/cancel`, { method: "POST", body: JSON.stringify({ reason: cancelReason.value.trim() }) }); notice.value = payload.message; cancelTarget.value = null; cancelReason.value = ""; await load(page.value); }
  catch (e) { error.value = e instanceof Error ? e.message : "Unable to cancel order."; }
  finally { saving.value = false; }
}
watch([status, search], () => { page.value = 1; void load(1); });
onMounted(() => { void load(1); });
</script>

<template>
  <Head title="Orders Management" />
  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <main class="orders-page">
      <header class="orders-hero">
        <div>
          <div class="orders-breadcrumb"><a href="/dashboard">Workspace</a><Icon name="chevron-right" :size="13"/><strong>Orders</strong></div>
          <div class="orders-title"><span class="orders-title-icon"><Icon name="shopping-bag" :size="24"/></span><div><h1>Orders Management</h1><p>Manage customer orders, inventory reservations and order lifecycle.</p></div></div>
        </div>
        <button class="orders-primary" @click="openCreate"><Icon name="plus" :size="17"/> Create order</button>
      </header>
      <div v-if="notice" class="orders-notice" role="status"><Icon name="check-circle" :size="18"/><span>{{ notice }}</span><button @click="notice = ''" aria-label="Dismiss notice">×</button></div>
      <div v-if="error" class="orders-error" role="alert"><Icon name="alert-circle" :size="18"/><span>{{ error }}</span><button @click="error = ''" aria-label="Dismiss error">×</button></div>
      <section class="orders-metrics">
        <article><span class="metric-label">Orders in view</span><strong>{{ total }}</strong><span class="metric-foot">All matching records</span></article>
        <article><span class="metric-label">Active on this page</span><strong>{{ activeCount }}</strong><span class="metric-foot">Pending or confirmed</span></article>
        <article><span class="metric-label">Workflow</span><strong class="metric-workflow">Reserve → Confirm</strong><span class="metric-foot">Stock is held at order creation</span></article>
      </section>
      <section class="orders-panel">
        <div class="orders-panel-head"><div><h2>Order register</h2><p>Search by order number, customer name or phone.</p></div><span class="orders-count">{{ total }} records</span></div>
        <div class="orders-toolbar">
          <label class="orders-search"><Icon name="search" :size="18"/><input v-model="search" type="search" placeholder="Search orders, customers or phone…" /></label>
          <label class="orders-filter"><span>Status</span><select v-model="status"><option value="all">All statuses</option><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="cancelled">Cancelled</option></select></label>
        </div>
        <div class="orders-table-wrap">
          <table class="orders-table"><thead><tr><th>Order</th><th>Customer</th><th>Location</th><th>Items</th><th>Total</th><th>Status</th><th>Created</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
              <tr v-if="loading"><td colspan="8" class="orders-empty">Loading orders…</td></tr>
              <tr v-else-if="!rows.length"><td colspan="8" class="orders-empty"><Icon name="package-open" :size="26"/><strong>No orders found</strong><span>Create your first order or change the filters.</span></td></tr>
              <tr v-for="order in rows" :key="order.id">
                <td><button class="orders-number" @click="openDetail(order)">{{ order.order_number }}</button><small>#{{ order.id }}</small></td>
                <td><strong>{{ order.customer_name }}</strong><small>{{ order.customer_phone || "No phone provided" }}</small></td>
                <td>{{ order.location?.name || "—" }}</td><td>{{ order.items?.length || 0 }} line(s)</td><td class="orders-money">{{ currency(order.grand_total) }}</td>
                <td><span class="orders-status" :class="`status-${order.status}`">{{ order.status }}</span></td>
                <td>{{ date(order.created_at) }}</td>
                <td><div class="orders-row-actions"><button title="View order" @click="openDetail(order)"><Icon name="eye" :size="16"/></button><button v-if="order.status === 'pending'" title="Confirm order" @click="confirmOrder(order)"><Icon name="check" :size="16"/></button><button v-if="order.status === 'pending' || order.status === 'confirmed'" title="Cancel order" @click="cancelTarget = order; cancelReason = ''"><Icon name="x" :size="16"/></button></div></td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="orders-pagination"><span>Page {{ page }} of {{ lastPage }}</span><div><button :disabled="page <= 1 || loading" @click="load(page - 1)">Previous</button><button :disabled="page >= lastPage || loading" @click="load(page + 1)">Next</button></div></footer>
      </section>
      <div v-if="createOpen" class="orders-overlay" @click.self="createOpen = false" @keydown.esc="createOpen = false">
        <section class="orders-dialog orders-create-dialog" role="dialog" aria-modal="true" aria-labelledby="create-order-title">
          <header class="orders-dialog-head"><div><span class="orders-eyebrow">NEW TRANSACTION</span><h2 id="create-order-title">Create order</h2><p>Stock is reserved atomically when this order is saved.</p></div><button class="orders-close" @click="createOpen = false" aria-label="Close">×</button></header>
          <div class="orders-dialog-body">
            <div class="orders-form-grid">
              <label>Fulfillment location <select v-model="locationId" required><option value="">Select location</option><option v-for="loc in locations" :key="loc.id" :value="String(loc.id)">{{ loc.name }}{{ loc.code ? ` · ${loc.code}` : "" }}</option></select></label>
              <label>Customer name <input v-model="customerName" required maxlength="160" placeholder="Customer or company name"/></label>
              <label>Phone <input v-model="customerPhone" maxlength="40" inputmode="tel" placeholder="+880…"/></label>
              <label>Email <input v-model="customerEmail" type="email" maxlength="190" placeholder="name@example.com"/></label>
              <label class="orders-form-wide">Internal notes <textarea v-model="notes" rows="2" maxlength="5000" placeholder="Delivery instructions or internal context…"/></label>
            </div>
            <div class="orders-add-line">
              <label class="orders-form-wide">Product / variant<select v-model="lineVariantId" :disabled="!locationId"><option value="">Choose available variant</option><option v-for="variant in variants" :key="variant.id" :value="String(variant.id)">{{ variant.name }} · {{ variant.sku || "No SKU" }} · {{ currency(variant.price) }} · Available {{ variant.available }}</option></select></label>
              <label>Qty<input v-model="lineQuantity" type="number" min="0.0001" step="0.0001" inputmode="decimal"/></label>
              <button class="orders-secondary" @click="addLine" :disabled="!selectedVariant">Add item</button>
            </div>
            <div class="orders-cart">
              <div v-if="!cart.length" class="orders-cart-empty">Add one or more items to continue.</div>
              <div v-for="line in cart" :key="line.product_variant_id" class="orders-cart-line"><div><strong>{{ line.name }}</strong><small>{{ line.sku || "No SKU" }} · {{ line.quantity }} × {{ currency(line.price) }}</small></div><strong>{{ currency(line.quantity * line.price) }}</strong><button @click="removeLine(line.product_variant_id)" aria-label="Remove item">×</button></div>
            </div>
            <div class="orders-total"><span>Subtotal / Total (BDT)</span><strong>{{ currency(subtotal) }}</strong></div>
          </div>
          <footer class="orders-dialog-footer"><span>Price is taken from the current product catalog.</span><div><button class="orders-secondary" @click="createOpen = false">Cancel</button><button class="orders-primary" :disabled="saving || !cart.length" @click="createOrder"><Icon name="shield-check" :size="16"/>{{ saving ? "Creating…" : "Create & reserve stock" }}</button></div></footer>
        </section>
      </div>
      <div v-if="detail" class="orders-overlay" @click.self="detail = null">
        <section class="orders-dialog" role="dialog" aria-modal="true" aria-labelledby="order-detail-title">
          <header class="orders-dialog-head"><div><span class="orders-eyebrow">ORDER DETAILS</span><h2 id="order-detail-title">{{ detail.order_number }}</h2><p>{{ detail.customer_name }} · {{ detail.location?.name }}</p></div><button class="orders-close" @click="detail = null" aria-label="Close">×</button></header>
          <div class="orders-dialog-body"><div class="orders-detail-meta"><span class="orders-status" :class="`status-${detail.status}`">{{ detail.status }}</span><strong>{{ currency(detail.grand_total) }}</strong></div><div v-for="item in detail.items || []" :key="item.id" class="orders-cart-line"><div><strong>{{ item.product_name }}{{ item.variant_name ? ` · ${item.variant_name}` : "" }}</strong><small>{{ item.sku || "No SKU" }} · {{ item.quantity }} × {{ currency(item.unit_price) }}</small></div><strong>{{ currency(item.line_total) }}</strong></div></div>
          <footer class="orders-dialog-footer"><span>Order and price snapshots are preserved.</span><div><button class="orders-secondary" @click="detail = null">Close</button></div></footer>
        </section>
      </div>
      <div v-if="cancelTarget" class="orders-overlay" @click.self="cancelTarget = null">
        <section class="orders-dialog orders-cancel-dialog" role="dialog" aria-modal="true" aria-labelledby="cancel-order-title"><header class="orders-dialog-head"><div><span class="orders-eyebrow">CONTROLLED ACTION</span><h2 id="cancel-order-title">Cancel {{ cancelTarget.order_number }}?</h2><p>Active stock reservations will be released.</p></div><button class="orders-close" @click="cancelTarget = null" aria-label="Close">×</button></header><div class="orders-dialog-body"><label>Cancellation reason <textarea v-model="cancelReason" rows="4" minlength="3" maxlength="1000" placeholder="Explain why this order is being cancelled…"/></label></div><footer class="orders-dialog-footer"><span>This action is recorded in order history.</span><div><button class="orders-secondary" @click="cancelTarget = null">Keep order</button><button class="orders-danger" :disabled="saving || cancelReason.trim().length < 3" @click="cancelOrder">{{ saving ? "Cancelling…" : "Confirm cancellation" }}</button></div></footer></section>
      </div>
    </main>
  </ErpLayout>
</template>

<style scoped>
.orders-page{max-width:1600px;margin:0 auto;padding:clamp(18px,2.2vw,32px);color:var(--foreground,#172033);display:grid;gap:20px}.orders-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap}.orders-breadcrumb{display:flex;align-items:center;gap:8px;color:var(--muted-foreground,#788397);font-size:12px;margin-bottom:14px}.orders-breadcrumb a{color:inherit;text-decoration:none}.orders-title{display:flex;align-items:center;gap:14px}.orders-title-icon{width:50px;height:50px;display:grid;place-items:center;border:1px solid #d8e4ff;border-radius:16px;background:linear-gradient(145deg,#f0f5ff,#e7eeff);color:#3159c9}.orders-title h1{font-size:clamp(24px,2.2vw,32px);font-weight:750;letter-spacing:-.04em;margin:0}.orders-title p,.orders-panel-head p,.orders-dialog-head p{color:var(--muted-foreground,#788397);font-size:13px;margin:5px 0 0}.orders-primary,.orders-secondary,.orders-danger{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:11px;min-height:42px;padding:0 15px;font-size:13px;font-weight:650;border:1px solid transparent;cursor:pointer;transition:.15s ease}.orders-primary{background:linear-gradient(135deg,#3159c9,#4777e8);color:white;box-shadow:0 6px 16px #3159c926}.orders-primary:hover:not(:disabled){filter:brightness(1.05);transform:translateY(-1px)}.orders-secondary{background:var(--card,#fff);border-color:var(--border,#dfe5ef);color:var(--foreground,#172033)}.orders-danger{background:#b42318;color:white}.orders-primary:disabled,.orders-secondary:disabled,.orders-danger:disabled{opacity:.5;cursor:not-allowed}.orders-notice,.orders-error{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:12px;font-size:13px}.orders-notice{background:#ecfdf3;color:#067647;border:1px solid #abefc6}.orders-error{background:#fff1f0;color:#b42318;border:1px solid #fecdca}.orders-notice span,.orders-error span{flex:1}.orders-notice button,.orders-error button{border:0;background:transparent;color:inherit;font-size:20px;cursor:pointer}.orders-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.orders-metrics article{display:grid;gap:8px;padding:18px 20px;border:1px solid var(--border,#e2e8f0);border-radius:16px;background:var(--card,#fff);box-shadow:0 2px 8px #10204005}.metric-label,.metric-foot{color:var(--muted-foreground,#788397);font-size:12px}.orders-metrics article>strong{font-size:27px;letter-spacing:-.04em}.metric-workflow{font-size:18px!important}.metric-foot{font-size:11px}.orders-panel{border:1px solid var(--border,#e2e8f0);border-radius:18px;background:var(--card,#fff);overflow:hidden;box-shadow:0 8px 24px #10204006}.orders-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:20px 22px;border-bottom:1px solid var(--border,#e8edf4)}.orders-panel-head h2{font-size:17px;font-weight:700;margin:0}.orders-count{font-size:11px;padding:6px 9px;border-radius:999px;background:var(--muted,#f3f5f9);color:var(--muted-foreground,#68758b)}.orders-toolbar{display:flex;align-items:center;gap:12px;padding:15px 22px;border-bottom:1px solid var(--border,#e8edf4)}.orders-search{display:flex;align-items:center;gap:10px;flex:1;min-width:180px;height:42px;padding:0 12px;border:1px solid var(--border,#dfe5ef);border-radius:11px;color:#788397}.orders-search input{width:100%;border:0;outline:0;background:transparent;color:var(--foreground,#172033);font-size:13px}.orders-filter{display:flex;align-items:center;gap:9px;font-size:12px;color:var(--muted-foreground,#788397)}.orders-filter select,.orders-form-grid input,.orders-form-grid select,.orders-form-grid textarea,.orders-add-line select,.orders-add-line input,.orders-dialog-body textarea{width:100%;border:1px solid var(--border,#dfe5ef);border-radius:10px;background:var(--background,#fff);color:var(--foreground,#172033);padding:10px 11px;font:inherit;font-size:13px;outline:0}.orders-filter select{min-width:145px}.orders-table-wrap{overflow:auto}.orders-table{width:100%;border-collapse:collapse;white-space:nowrap}.orders-table th{text-align:left;padding:12px 16px;background:var(--muted,#f8fafc);color:var(--muted-foreground,#788397);font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:700}.orders-table td{padding:14px 16px;border-top:1px solid var(--border,#edf0f5);font-size:12px}.orders-table tbody tr:hover{background:color-mix(in srgb,var(--muted,#f8fafc) 65%,transparent)}.orders-table td:first-child small,.orders-table td:nth-child(2) small{display:block;color:var(--muted-foreground,#788397);font-size:10px;margin-top:4px}.orders-number{padding:0;border:0;background:transparent;color:#3159c9;font-weight:750;cursor:pointer}.orders-money{font-weight:750;font-variant-numeric:tabular-nums}.orders-status{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:10px;text-transform:capitalize;font-weight:750}.status-pending{background:#fff4d6;color:#935c00}.status-confirmed{background:#e8f2ff;color:#2458b8}.status-cancelled{background:#feeceb;color:#b42318}.orders-row-actions{display:flex;gap:5px}.orders-row-actions button{display:grid;place-items:center;width:30px;height:30px;border:1px solid var(--border,#e1e6ef);border-radius:8px;background:var(--card,#fff);color:var(--muted-foreground,#68758b);cursor:pointer}.orders-row-actions button:hover{color:#3159c9;border-color:#b8caff}.orders-empty{height:140px;text-align:center;color:var(--muted-foreground,#788397)}.orders-empty>*{display:block;margin:5px auto}.orders-pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--border,#e8edf4);font-size:12px;color:var(--muted-foreground,#788397)}.orders-pagination div{display:flex;gap:8px}.orders-pagination button{border:1px solid var(--border,#dfe5ef);border-radius:9px;background:var(--card,#fff);padding:8px 11px;color:var(--foreground,#172033);cursor:pointer}.orders-pagination button:disabled{opacity:.4;cursor:not-allowed}.orders-overlay{position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;padding:18px;background:#07101f9c;backdrop-filter:blur(8px)}.orders-dialog{width:min(620px,100%);max-height:min(90dvh,900px);display:flex;flex-direction:column;overflow:hidden;border:1px solid var(--border,#dfe5ef);border-radius:20px;background:var(--card,#fff);box-shadow:0 30px 100px #02081750}.orders-create-dialog{width:min(860px,100%)}.orders-dialog-head{display:flex;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--border,#e8edf4);background:linear-gradient(125deg,color-mix(in srgb,var(--card,#fff) 92%,#dce7ff),var(--card,#fff) 65%)}.orders-eyebrow{font-size:9px;letter-spacing:.14em;color:#5271bd;font-weight:800}.orders-dialog-head h2{font-size:23px;letter-spacing:-.035em;margin:5px 0 0}.orders-close{width:34px;height:34px;border:1px solid var(--border,#dfe5ef);border-radius:10px;background:var(--card,#fff);font-size:22px;cursor:pointer}.orders-dialog-body{padding:22px 24px;overflow:auto;min-height:0}.orders-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.orders-form-grid label,.orders-add-line label,.orders-dialog-body>label{display:grid;gap:7px;font-size:12px;font-weight:650}.orders-form-wide{grid-column:1/-1}.orders-form-grid textarea,.orders-dialog-body textarea{resize:vertical}.orders-add-line{display:grid;grid-template-columns:minmax(0,1fr) 100px auto;align-items:end;gap:10px;padding:17px 0;border-top:1px solid var(--border,#e8edf4);margin-top:18px}.orders-cart{border:1px solid var(--border,#e2e8f0);border-radius:12px;overflow:hidden}.orders-cart-empty{padding:20px;text-align:center;color:var(--muted-foreground,#788397);font-size:12px}.orders-cart-line{display:flex;align-items:center;gap:12px;padding:12px 14px;border-bottom:1px solid var(--border,#edf0f5)}.orders-cart-line:last-child{border-bottom:0}.orders-cart-line>div{flex:1;min-width:0}.orders-cart-line strong,.orders-cart-line small{display:block}.orders-cart-line small{font-size:11px;color:var(--muted-foreground,#788397);margin-top:4px}.orders-cart-line>button{border:0;background:transparent;color:#b42318;font-size:20px;cursor:pointer}.orders-total{display:flex;justify-content:space-between;align-items:center;padding:16px 2px;font-size:13px}.orders-total strong,.orders-detail-meta strong{font-size:22px;letter-spacing:-.03em}.orders-dialog-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 24px;border-top:1px solid var(--border,#e8edf4);background:color-mix(in srgb,var(--card,#fff) 94%,transparent)}.orders-dialog-footer>span{font-size:10px;color:var(--muted-foreground,#788397)}.orders-dialog-footer>div{display:flex;gap:8px}.orders-detail-meta{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:760px){.orders-page{padding:15px;gap:14px}.orders-metrics{grid-template-columns:1fr}.orders-metrics article{padding:14px 16px}.orders-toolbar{align-items:stretch;flex-direction:column;padding:13px}.orders-filter{justify-content:space-between}.orders-filter select{flex:1}.orders-panel-head{padding:16px}.orders-dialog-head,.orders-dialog-body{padding:17px}.orders-form-grid{grid-template-columns:1fr}.orders-form-wide{grid-column:auto}.orders-add-line{grid-template-columns:minmax(0,1fr) 85px}.orders-add-line .orders-form-wide{grid-column:1/-1}.orders-add-line button{grid-column:1/-1}.orders-dialog-footer{align-items:stretch;flex-direction:column;padding:13px 17px}.orders-dialog-footer>div{justify-content:flex-end}.orders-overlay{padding:8px;align-items:flex-end}.orders-dialog{max-height:94dvh;border-radius:18px 18px 10px 10px}.orders-title h1{font-size:23px}}
@media(prefers-reduced-motion:reduce){.orders-primary,.orders-secondary,.orders-danger{transition:none}}
</style>
