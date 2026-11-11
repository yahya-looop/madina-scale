<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Props {
  userName?: string;
  userEmail?: string;
}
withDefaults(defineProps<Props>(), { userName: "", userEmail: "" });
type LeadStatus =
  | "new"
  | "contacting"
  | "qualified"
  | "interested"
  | "follow_up"
  | "converted"
  | "lost";
type Lead = {
  id: number;
  lead_code: string;
  name: string;
  phone: string;
  alternate_phone: string | null;
  email: string | null;
  address_line: string | null;
  area: string | null;
  district: string | null;
  division: string | null;
  source: string;
  source_detail: string | null;
  customer_type: "retail" | "b2b" | "undecided";
  interested_product: string | null;
  status: LeadStatus;
  last_contact_outcome: string | null;
  interest_level: "hot" | "warm" | "cold";
  notes: string | null;
  next_follow_up_at: string | null;
  assigned_to: number | null;
  owner_id?: number | null;
  owner?: { id: number; name: string } | null;
  location_id?: number | null;
  assignee?: { id: number; name: string; designation?: string | null } | null;
  assignment_history?: AssignmentHistory[];

  activities_count?: number;
  activities?: Activity[];
  customer?: {
    id: number;
    customer_code: string;
    name: string;
    phone: string | null;
    email?: string | null;
    company_name?: string | null;
  } | null;
  converted_order?: {
    id: number;
    order_number: string;
    status: string;
    grand_total: number | string;
  } | null;
  created_at: string;
  created_by?: number | null;
  creator?: { id: number; name: string } | null;
  updated_by?: number | null;
};
type AssignmentMember = {
  id: number;
  name: string;
  designation: string | null;
  location_ids: number[];
};
type AssignmentHistory = {
  id: number;
  from_user?: { id: number; name: string } | null;
  to_user?: { id: number; name: string } | null;
  actor?: { id: number; name: string } | null;
  reason: string | null;
  created_at: string;
};
type Activity = {
  id: number;
  activity_type: string;
  outcome: string | null;
  summary: string;
  occurred_at: string;
  next_follow_up_at: string | null;
  creator?: { name: string } | null;
};
type Form = {
  name: string;
  phone: string;
  alternate_phone: string;
  email: string;
  address_line: string;
  area: string;
  district: string;
  division: string;
  source: string;
  source_detail: string;
  customer_type: "retail" | "b2b" | "undecided";
  interested_product: string;
  status: LeadStatus;
  interest_level: "hot" | "warm" | "cold";
  notes: string;
  next_follow_up_at: string;
  assigned_to: number | null;
  location_id: number | null;
};
type ActivityForm = {
  activity_type: string;
  outcome: string;
  summary: string;
  occurred_at: string;
  next_follow_up_at: string;
  duration_seconds: string;
};
const leads = ref<Lead[]>([]),
  loading = ref(false),
  saving = ref(false),
  convertConfirmOpen = ref(false),
  orderModalOpen = ref(false),
  orderOptionsLoading = ref(false),
  orderSubmitting = ref(false),
  search = ref(""),
  statusFilter = ref("all"),
  typeFilter = ref("all"),
  followUpOnly = ref(false),
  sortBy = ref("follow_up"),
  sortDirection = ref("asc"),
  showAdvancedFilters = ref(false),
  page = ref(1),
  lastPage = ref(1),
  total = ref(0),
  notice = ref(""),
  error = ref("");
const stats = ref({ total: 0, new: 0, follow_up_due: 0, converted: 0 });
const formOpen = ref(false),
  editing = ref<Lead | null>(null),
  detail = ref<Lead | null>(null),
  activityOpen = ref(false),
  activitySaving = ref(false),
  quickSaving = ref(false),
  assignmentSaving = ref(false);
const assignmentMembers = ref<AssignmentMember[]>([]);
type OrderVariant = { id: number; product_id: number; name: string; sku: string | null; price: string | number; available: string | number; image_url?: string | null };
type OrderCartLine = { product_variant_id: number; name: string; sku: string | null; price: number; quantity: number; available: number };
const orderLocations = ref<{ id: number; name: string; code?: string }[]>([]);
const orderVariants = ref<OrderVariant[]>([]);
const orderLocationId = ref<number | null>(null);
const orderVariantId = ref<number | null>(null);
const orderQuantity = ref(1);
const orderNotes = ref("");
const orderCart = ref<OrderCartLine[]>([]);
const orderIdempotencyKey = ref("");
const orderSubtotal = computed(() => orderCart.value.reduce((sum, item) => sum + item.price * item.quantity, 0));
const selectedOrderVariant = computed(() => orderVariants.value.find(v => v.id === orderVariantId.value) || null);

const assignmentLocations = ref<{ id: number; name: string }[]>([]);
const assignmentCurrentUserId = ref<number | null>(null);
const canManageAssignments = ref(false);
const assignmentTarget = ref<number | null>(null);
const assignmentReason = ref("");
const assignmentLocationId = ref<number | null>(null);
const defaultFollowUpHours = ref(24);
const form = reactive<Form>(emptyForm());
const activity = reactive<ActivityForm>(emptyActivity());
const statuses: { value: LeadStatus; label: string }[] = [
  { value: "new", label: "New" },
  { value: "contacting", label: "Contacting" },
  { value: "qualified", label: "Qualified" },
  { value: "interested", label: "Interested" },
  { value: "follow_up", label: "Follow-up" },
  { value: "converted", label: "Converted" },
  { value: "lost", label: "Lost" },
];
const filteredCount = computed(() => total.value);
const sortPreset = computed({
  get: () => `${sortBy.value}:${sortDirection.value}`,
  set: (value: string) => {
    const [field, direction] = value.split(":");
    sortBy.value = field || "follow_up";
    sortDirection.value = direction === "desc" ? "desc" : "asc";
    page.value = 1;
    loadLeads();
  },
});
const hasActiveFilters = computed(
  () =>
    !!search.value ||
    statusFilter.value !== "all" ||
    typeFilter.value !== "all" ||
    followUpOnly.value ||
    sortBy.value !== "follow_up" ||
    sortDirection.value !== "asc"
);
const editableStatuses = computed(() =>
  editing.value?.status === "converted"
    ? statuses.filter((s) => s.value === "converted")
    : statuses.filter((s) => s.value !== "converted")
);
function defaultFollowUpInput() {
  const d = new Date(Date.now() + defaultFollowUpHours.value * 60 * 60 * 1000);
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
}
function emptyForm(): Form {
  return {
    name: "",
    phone: "",
    alternate_phone: "",
    email: "",
    address_line: "",
    area: "",
    district: "",
    division: "",
    source: "manual",
    source_detail: "",
    customer_type: "undecided",
    interested_product: "",
    status: "new",
    interest_level: "warm",
    notes: "",
    next_follow_up_at: defaultFollowUpInput(),
    assigned_to: null,
    location_id: null,
  };
}
function emptyActivity(): ActivityForm {
  return {
    activity_type: "call",
    outcome: "answered",
    summary: "",
    occurred_at: localDateTime(),
    next_follow_up_at: "",
    duration_seconds: "",
  };
}
function localDateTime() {
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
}
function headers() {
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
    ?.content;
  return {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    ...(token ? { "X-CSRF-TOKEN": token } : {}),
  };
}
async function readError(response: Response, fallback: string) {
  try {
    const p = await response.json();
    return (
      p.message ||
      Object.values(p.errors || {})
        .flat()
        .join(" ") ||
      fallback
    );
  } catch {
    return fallback;
  }
}
async function loadLeads() {
  loading.value = true;
  error.value = "";
  try {
    const q = new URLSearchParams({
      search: search.value,
      status: statusFilter.value,
      customer_type: typeFilter.value,
      sort_by: sortBy.value,
      sort_direction: sortDirection.value,
      page: String(page.value),
      per_page: "15",
    });
    if (followUpOnly.value) q.set("follow_up", "due");
    const r = await fetch(`/api/v1/backend/leads?${q}`, {
      headers: headers(),
      credentials: "same-origin",
      cache: "no-store",
    });
    if (!r.ok) throw new Error(await readError(r, "Could not load leads."));
    const p = await r.json();
    leads.value = p.data || [];
    total.value = p.total || 0;
    lastPage.value = p.last_page || 1;
    stats.value = p.stats || stats.value;
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Could not load leads.";
    leads.value = [];
  } finally {
    loading.value = false;
  }
}
function clearFilters() {
  search.value = "";
  statusFilter.value = "all";
  typeFilter.value = "all";
  followUpOnly.value = false;
  sortBy.value = "follow_up";
  sortDirection.value = "asc";
  page.value = 1;
  loadLeads();
}
function openCreate() {
  editing.value = null;
  Object.assign(form, emptyForm());
  form.next_follow_up_at = defaultFollowUpInput();
  formOpen.value = true;
}
function openEdit(lead: Lead) {
  editing.value = lead;
  Object.assign(form, {
    ...emptyForm(),
    ...lead,
    location_id: lead.location_id ?? null,
    assigned_to: lead.assigned_to ?? null,
    next_follow_up_at: lead.next_follow_up_at ? lead.next_follow_up_at.slice(0, 16) : "",
    alternate_phone: lead.alternate_phone || "",
    email: lead.email || "",
    address_line: lead.address_line || "",
    area: lead.area || "",
    district: lead.district || "",
    division: lead.division || "",
    source_detail: lead.source_detail || "",
    interested_product: lead.interested_product || "",
    notes: lead.notes || "",
  });
  formOpen.value = true;
}
async function saveLead() {
  saving.value = true;
  error.value = "";
  notice.value = "";
  try {
    const r = await fetch(
      editing.value ? `/api/v1/backend/leads/${editing.value.id}` : "/api/v1/backend/leads",
      {
        method: editing.value ? "PUT" : "POST",
        headers: headers(),
        credentials: "same-origin",
        body: JSON.stringify({
          ...form,
          next_follow_up_at: form.next_follow_up_at || null,
          alternate_phone: form.alternate_phone || null,
          email: form.email || null,
          area: form.area || null,
          district: form.district || null,
          division: form.division || null,
          source_detail: form.source_detail || null,
          interested_product: form.interested_product || null,
          notes: form.notes || null,
        }),
      }
    );
    if (!r.ok) throw new Error(await readError(r, "Could not save lead."));
    formOpen.value = false;
    notice.value = editing.value
      ? "Lead updated successfully."
      : "Lead created successfully.";
    await loadLeads();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Could not save lead.";
  } finally {
    saving.value = false;
  }
}
function toLocalInput(value: string | null | undefined) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
  return date.toISOString().slice(0, 16);
}

async function quickUpdateLead(
  lead: Lead,
  field: "customer_type" | "status" | "next_follow_up_at",
  eventOrValue: Event | string
) {
  if (quickSaving.value) return;
  const rawValue =
    typeof eventOrValue === "string"
      ? eventOrValue
      : (eventOrValue.target as HTMLSelectElement | HTMLInputElement | null)?.value ?? "";
  const value = field === "next_follow_up_at" ? rawValue || null : rawValue;
  const payload = {
    name: lead.name,
    phone: lead.phone,
    alternate_phone: lead.alternate_phone || null,
    email: lead.email || null,
    address_line: lead.address_line || null,
    area: lead.area || null,
    district: lead.district || null,
    division: lead.division || null,
    source: lead.source || "manual",
    source_detail: lead.source_detail || null,
    customer_type: field === "customer_type" ? value : lead.customer_type,
    interested_product: lead.interested_product || null,
    status: field === "status" ? value : lead.status,
    last_contact_outcome: lead.last_contact_outcome || null,
    interest_level: lead.interest_level || "warm",
    notes: lead.notes || null,
    next_follow_up_at:
      field === "next_follow_up_at"
        ? value
        : lead.next_follow_up_at
        ? new Date(lead.next_follow_up_at).toISOString()
        : null,
    assigned_to: lead.assigned_to ?? null,
  };
  quickSaving.value = true;
  error.value = "";
  notice.value = "";
  try {
    const response = await fetch(`/api/v1/backend/leads/${lead.id}`, {
      method: "PUT",
      headers: headers(),
      credentials: "same-origin",
      body: JSON.stringify(payload),
    });
    if (!response.ok)
      throw new Error(await readError(response, "Could not update lead."));
    notice.value =
      field === "customer_type"
        ? "Customer type updated successfully."
        : field === "status"
        ? "Pipeline updated successfully."
        : "Next follow-up updated successfully.";
    await loadLeads();
    if (detail.value?.id === lead.id) await openDetail(lead);
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Could not update lead.";
    await loadLeads();
    if (detail.value?.id === lead.id) await openDetail(lead);
  } finally {
    quickSaving.value = false;
  }
}

async function loadAssignmentOptions() {
  try {
    const response = await fetch("/api/v1/backend/leads/assignment-options", {
      headers: headers(),
      credentials: "same-origin",
      cache: "no-store",
    });
    if (!response.ok) return;
    const payload = await response.json();
    assignmentMembers.value = payload.members || [];
    assignmentLocations.value = payload.locations || [];
    defaultFollowUpHours.value = Math.max(1, Math.min(8760, Number(payload.default_follow_up_hours) || 24));
    assignmentCurrentUserId.value = payload.current_user_id ?? null;
    canManageAssignments.value = !!payload.can_manage_assignments;
  } catch {
    assignmentMembers.value = [];
    assignmentLocations.value = [];
  }
}
function membersForLocation(locationId: number | null | undefined) {
  if (locationId) return assignmentMembers.value.filter((member) => member.location_ids.includes(locationId));
  return assignmentMembers.value;
}
async function saveAssignment() {
  if (!detail.value || !assignmentTarget.value || assignmentSaving.value) return;
  assignmentSaving.value = true;
  error.value = "";
  notice.value = "";
  try {
    const r = await fetch(`/api/v1/backend/leads/${detail.value.id}/assignment`, {
      method: "POST",
      headers: headers(),
      credentials: "same-origin",
      body: JSON.stringify({
        assigned_to: assignmentTarget.value,
        reason: assignmentReason.value.trim() || null,
        location_id: detail.value.location_id || assignmentLocationId.value || null,
      }),
    });
    if (!r.ok) throw new Error(await readError(r, "Could not assign lead."));
    const payload = await r.json();
    detail.value = payload.data;
    assignmentTarget.value = null;
    assignmentReason.value = "";
    assignmentLocationId.value = null;
    notice.value = "Lead owner updated successfully.";
    await loadLeads();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Could not assign lead.";
  } finally {
    assignmentSaving.value = false;
  }
}

function convertLeadToCustomer() {
  if (!detail.value || detail.value.status !== "qualified" || detail.value.customer || saving.value) return;
  convertConfirmOpen.value = true;
}

async function confirmConvertAndCreateOrder() {
  const lead = detail.value;
  if (!lead || lead.status !== "qualified" || saving.value) return;
  convertConfirmOpen.value = false;
  orderModalOpen.value = true;
  orderLocationId.value = lead.location_id || assignmentLocations.value[0]?.id || null;
  orderVariantId.value = null;
  orderQuantity.value = 1;
  orderNotes.value = `Lead ${lead.lead_code}`;
  orderCart.value = [];
  orderIdempotencyKey.value = `lead-${lead.id}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  await loadOrderOptions();
}

async function loadOrderOptions() {
  orderOptionsLoading.value = true;
  error.value = "";
  try {
    const r = await fetch("/api/v1/backend/orders/options", { headers: headers(), credentials: "same-origin" });
    if (!r.ok) throw new Error(await readError(r, "Could not load order locations."));
    const payload = await r.json();
    orderLocations.value = payload.locations || [];
    if (!orderLocationId.value || !orderLocations.value.some(l => l.id === orderLocationId.value)) {
      orderLocationId.value = orderLocations.value[0]?.id ?? null;
    }
    await loadOrderVariants();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Could not load order options.";
  } finally {
    orderOptionsLoading.value = false;
  }
}

async function loadOrderVariants() {
  orderVariants.value = [];
  if (!orderLocationId.value) return;
  const r = await fetch(`/api/v1/backend/orders/options?location_id=${orderLocationId.value}`, { headers: headers(), credentials: "same-origin" });
  if (!r.ok) throw new Error(await readError(r, "Could not load products for this location."));
  const payload = await r.json();
  orderVariants.value = payload.variants || [];
}

function addOrderLine() {
  const v = selectedOrderVariant.value;
  if (!v || orderQuantity.value <= 0) return;
  const available = Number(v.available);
  if (orderQuantity.value > available) { error.value = `Only ${available} unit(s) are available for ${v.name}.`; return; }
  const existing = orderCart.value.find(item => item.product_variant_id === v.id);
  if (existing) {
    if (existing.quantity + orderQuantity.value > available) { error.value = `Available quantity exceeded for ${v.name}.`; return; }
    existing.quantity += orderQuantity.value;
  } else {
    orderCart.value.push({ product_variant_id: v.id, name: v.name, sku: v.sku, price: Number(v.price), quantity: orderQuantity.value, available });
  }
  error.value = "";
  orderVariantId.value = null;
  orderQuantity.value = 1;
}

async function submitConversionOrder() {
  const lead = detail.value;
  if (!lead || !orderLocationId.value || !orderCart.value.length || orderSubmitting.value) return;
  orderSubmitting.value = true;
  error.value = "";
  try {
    const r = await fetch(`/api/v1/backend/leads/${lead.id}/convert-with-order`, {
      method: "POST", headers: headers(), credentials: "same-origin",
      body: JSON.stringify({
        location_id: orderLocationId.value, notes: orderNotes.value || null,
        idempotency_key: orderIdempotencyKey.value,
        items: orderCart.value.map(item => ({ product_variant_id: item.product_variant_id, quantity: item.quantity })),
      }),
    });
    if (!r.ok) throw new Error(await readError(r, "Order could not be created. The lead remains unchanged."));
    const payload = await r.json();
    detail.value = payload.data;
    orderModalOpen.value = false;
    notice.value = `Order ${payload.order?.order_number || ""} created and customer conversion completed.`;
    await loadLeads();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Order failed. The lead remains unchanged.";
  } finally {
    orderSubmitting.value = false;
  }
}
function viewCustomer() {
  if (!detail.value?.customer) return;
  window.location.href = `/backend/customers?search=${encodeURIComponent(detail.value.customer.customer_code)}`;
}

async function openDetail(lead: Lead) {
  error.value = "";
  try {
    const r = await fetch(`/api/v1/backend/leads/${lead.id}`, {
      headers: headers(),
      credentials: "same-origin",
      cache: "no-store",
    });
    if (!r.ok) throw new Error(await readError(r, "Could not load lead details."));
    const p = await r.json();
    detail.value = p.data;
    assignmentTarget.value = null;
    assignmentReason.value = "";
    assignmentLocationId.value = p.data.location_id ?? null;
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Could not load lead details.";
  }
}
function openActivity() {
  if (!detail.value) return;
  Object.assign(activity, emptyActivity());
  activityOpen.value = true;
}
async function saveActivity() {
  if (!detail.value) return;
  activitySaving.value = true;
  error.value = "";
  try {
    const r = await fetch(`/api/v1/backend/leads/${detail.value.id}/activities`, {
      method: "POST",
      headers: headers(),
      credentials: "same-origin",
      body: JSON.stringify({
        ...activity,
        occurred_at: new Date(activity.occurred_at).toISOString(),
        next_follow_up_at: activity.next_follow_up_at
          ? new Date(activity.next_follow_up_at).toISOString()
          : null,
        duration_seconds: activity.duration_seconds
          ? Number(activity.duration_seconds)
          : null,
        outcome: activity.outcome || null,
      }),
    });
    if (!r.ok) throw new Error(await readError(r, "Could not record activity."));
    activityOpen.value = false;
    await openDetail(detail.value);
    await loadLeads();
    notice.value = "Activity recorded successfully.";
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Could not record activity.";
  } finally {
    activitySaving.value = false;
  }
}
async function archiveLead(lead: Lead) {
  if (!confirm(`Archive lead ${lead.name}?`)) return;
  error.value = "";
  try {
    const r = await fetch(`/api/v1/backend/leads/${lead.id}`, {
      method: "DELETE",
      headers: headers(),
      credentials: "same-origin",
    });
    if (!r.ok) throw new Error(await readError(r, "Could not archive lead."));
    detail.value = null;
    notice.value = "Lead archived.";
    await loadLeads();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Could not archive lead.";
  }
}
function labelForStatus(value: string) {
  return statuses.find((s) => s.value === value)?.label || value.replaceAll("_", " ");
}
function formatDate(value: string | null | undefined) {
  if (!value) return "—";
  return new Date(value).toLocaleString(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  });
}
function isOverdue(value: string | null) {
  return !!value && new Date(value).getTime() <= Date.now();
}
onMounted(() => { loadLeads(); loadAssignmentOptions(); });
</script>

<template>
  <Head title="Sales CRM · Lead Management" />
  <ErpLayout :user-name="userName" :user-email="userEmail">
    <main class="crm-page">
      <header class="crm-heading">
        <div>
          <div class="crumb">
            Sales <Icon name="chevron-right" :size="12" /><b>Sales CRM</b>
          </div>
          <div class="title-line">
            <span class="hero-icon"><Icon name="list-details" :size="23" /></span>
            <div>
              <h1>Lead Management</h1>
              <p>Capture prospects, track conversations, and never miss a follow-up.</p>
            </div>
          </div>
        </div>
        <button class="crm-primary" @click="openCreate">
          <Icon name="plus" :size="17" /> Add lead
        </button>
      </header>
      <div v-if="notice" class="crm-alert success">
        <Icon name="check-circle" :size="17" />{{ notice
        }}<button @click="notice = ''" aria-label="Dismiss">×</button>
      </div>
      <div v-if="error" class="crm-alert danger">
        <Icon name="alert-circle" :size="17" />{{ error
        }}<button @click="error = ''" aria-label="Dismiss">×</button>
      </div>
      <section class="crm-kpis">
        <article>
          <span>Total leads</span><strong>{{ stats.total }}</strong
          ><small>All active lead records</small>
        </article>
        <article>
          <span>New leads</span><strong>{{ stats.new }}</strong
          ><small>Awaiting first contact</small>
        </article>
        <article class="attention">
          <span>Follow-ups due</span><strong>{{ stats.follow_up_due }}</strong
          ><small>Scheduled callbacks to action</small>
        </article>
        <article class="positive">
          <span>Converted</span><strong>{{ stats.converted }}</strong
          ><small>Marked as converted</small>
        </article>
      </section>
      <section class="crm-panel">
        <div class="crm-panel-head">
          <div>
            <h2>Lead register</h2>
            <p>Search by name, phone, location, lead code or interested product.</p>
          </div>
          <span class="crm-count">{{ filteredCount }} matching records</span>
        </div>
        <div class="crm-filters">
          <label class="crm-search">
            <Icon name="search" :size="17" />
            <input
              v-model="search"
              placeholder="Search leads by name, phone, location, code or product…"
              @keyup.enter="
                page = 1;
                loadLeads();
              "
            />
            <button
              v-if="search"
              type="button"
              aria-label="Clear search"
              @click="
                search = '';
                page = 1;
                loadLeads();
              "
            >
              <Icon name="x" :size="14" />
            </button>
          </label>
          <label class="crm-sort" aria-label="Sort leads">
            <Icon name="arrows-transfer-up" :size="15" />
            <select v-model="sortPreset">
              <option value="follow_up:asc">Follow-up · Soonest</option>
              <option value="follow_up:desc">Follow-up · Latest</option>
              <option value="created_at:desc">Recently added</option>
              <option value="created_at:asc">Oldest added</option>
              <option value="name:asc">Name · A to Z</option>
              <option value="name:desc">Name · Z to A</option>
              <option value="status:asc">Pipeline status</option>
              <option value="interest_level:desc">Interest level</option>
            </select>
          </label>
          <button
            type="button"
            class="crm-secondary compact crm-filter-toggle"
            :class="{
              active:
                showAdvancedFilters ||
                statusFilter !== 'all' ||
                typeFilter !== 'all' ||
                followUpOnly,
            }"
            :aria-expanded="showAdvancedFilters"
            @click="showAdvancedFilters = !showAdvancedFilters"
          >
            <Icon name="sliders-horizontal" :size="15" /> Filters
            <span
              v-if="statusFilter !== 'all' || typeFilter !== 'all' || followUpOnly"
              class="crm-filter-dot"
            ></span>
          </button>
          <button
            type="button"
            class="crm-secondary compact crm-refresh"
            :disabled="loading"
            @click="loadLeads"
          >
            <Icon name="refresh-cw" :size="15" />
            <span>{{ loading ? "Loading…" : "Refresh" }}</span>
          </button>
        </div>
        <div v-if="showAdvancedFilters" class="crm-advanced-filters">
          <label
            ><span>Status</span>
            <select
              v-model="statusFilter"
              @change="
                page = 1;
                loadLeads();
              "
            >
              <option value="all">All statuses</option>
              <option v-for="s in statuses" :key="s.value" :value="s.value">
                {{ s.label }}
              </option>
            </select>
          </label>
          <label
            ><span>Customer type</span>
            <select
              v-model="typeFilter"
              @change="
                page = 1;
                loadLeads();
              "
            >
              <option value="all">All types</option>
              <option value="retail">Retail</option>
              <option value="b2b">B2B / Wholesale</option>
              <option value="undecided">Undecided</option>
            </select>
          </label>
          <label class="crm-check"
            ><input
              v-model="followUpOnly"
              type="checkbox"
              @change="
                page = 1;
                loadLeads();
              "
            />
            Follow-ups due only</label
          >
          <button
            v-if="hasActiveFilters"
            type="button"
            class="crm-secondary compact"
            @click="clearFilters"
          >
            <Icon name="rotate-ccw" :size="14" /> Reset all
          </button>
        </div>
        <div v-if="loading" class="crm-empty">
          <Icon name="loader-circle" :size="22" /> Loading leads…
        </div>
        <div v-else-if="!leads.length" class="crm-empty">
          <Icon name="users" :size="28" /><strong>No leads found</strong
          ><span>Add your first prospect or adjust the filters.</span>
        </div>
        <div v-else class="crm-table-wrap">
          <table class="crm-table crm-lead-register-table">
            <thead>
              <tr>
                <th>Lead / contact</th>
                <th>Location</th>
                <th>Type</th>
                <th>Pipeline</th>
                <th>Next follow-up</th>
                <th>Created by / Assigned</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="lead in leads"
                :key="lead.id"
                class="crm-lead-row"
                tabindex="0"
                @click="openDetail(lead)"
                @keydown.enter.self.prevent="openDetail(lead)"
                @keydown.space.self.prevent="openDetail(lead)"
              >
                <td>
                  <button class="crm-link" @click.stop="openDetail(lead)">
                    {{ lead.name }}</button
                  ><small>{{ lead.lead_code }} · {{ lead.phone }}</small
                  ><small v-if="lead.interested_product"
                    >Interested: {{ lead.interested_product }}</small
                  >
                </td>
                <td>
                  {{
                    [lead.area, lead.district, lead.division]
                      .filter(Boolean)
                      .join(", ") || "—"
                  }}
                </td>
                <td>
                  <select
                    class="crm-inline-select crm-inline-type"
                    :class="lead.customer_type"
                    :value="lead.customer_type"
                    :disabled="quickSaving || lead.status === 'converted'"
                    aria-label="Update customer type"
                    @click.stop
                    @change="quickUpdateLead(lead, 'customer_type', $event)"
                  >
                    <option value="undecided">Undecided</option>
                    <option value="retail">Retail</option>
                    <option value="b2b">B2B / Wholesale</option>
                  </select>
                </td>
                <td>
                  <select
                    class="crm-inline-select crm-inline-status"
                    :class="lead.status"
                    :value="lead.status"
                    :disabled="quickSaving || lead.status === 'converted'"
                    aria-label="Update pipeline status"
                    @click.stop
                    @change="quickUpdateLead(lead, 'status', $event)"
                  >
                    <option
                      v-for="s in statuses.filter(
                        (item) =>
                          item.value !== 'converted' || lead.status === 'converted'
                      )"
                      :key="s.value"
                      :value="s.value"
                    >
                      {{ s.label }}
                    </option>
                  </select>
                  <small v-if="lead.status === 'converted'" class="crm-converted-lock"><Icon name="lock-keyhole" :size="12" /> <Icon name="check-circle-2" :size="12" /> Converted</small>
                  <small v-if="lead.last_contact_outcome"
                    >Last outcome:
                    {{ lead.last_contact_outcome.replaceAll("_", " ") }}</small
                  >
                </td>
                <td>
                  <span
                    :class="{
                      overdue:
                        isOverdue(lead.next_follow_up_at) &&
                        !['converted', 'lost'].includes(lead.status),
                    }"
                    >{{ formatDate(lead.next_follow_up_at) }}</span
                  >
                </td>
                <td>
                  <span class="created-by"
                    ><Icon name="user" :size="13" />
                    {{ lead.creator?.name || "Unknown user" }}</span
                  ><small>Assigned: {{ lead.assignee?.name || "Unassigned" }}</small
                  ><small>Added: {{ formatDate(lead.created_at) }}</small>
                </td>
                <td>
                  <div class="crm-actions">
                    <button title="View timeline" @click.stop="openDetail(lead)">
                      <Icon name="eye" :size="16" /></button
                    ><button v-if="lead.status !== 'converted'" title="Edit lead" @click.stop="openEdit(lead)">
                      <Icon name="pencil" :size="16" /></button
                    ><button
                      v-if="!['converted'].includes(lead.status)"
                      title="Archive lead"
                      @click.stop="archiveLead(lead)"
                    >
                      <Icon name="archive" :size="16" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="crm-pagination">
          <span>Page {{ page }} of {{ lastPage }} · {{ total }} leads</span>
          <div>
            <button
              :disabled="page <= 1 || loading"
              @click="
                page--;
                loadLeads();
              "
            >
              Previous</button
            ><button
              :disabled="page >= lastPage || loading"
              @click="
                page++;
                loadLeads();
              "
            >
              Next
            </button>
          </div>
        </footer>
      </section>
    </main>
    <div v-if="formOpen" class="crm-overlay" @click.self="formOpen = false">
      <section class="crm-modal">
        <header>
          <div>
            <div class="eyebrow">{{ editing ? "UPDATE PROSPECT" : "NEW PROSPECT" }}</div>
            <h2>{{ editing ? "Edit lead" : "Add lead" }}</h2>
            <p>
              Capture the contact first; enrich the profile as the conversation
              progresses.
            </p>
          </div>
          <button class="crm-close" @click="formOpen = false">×</button>
        </header>
        <form @submit.prevent="saveLead">
          <div class="crm-form-grid">
            <div class="crm-form-section full">
              <span class="crm-section-icon"><Icon name="list" :size="17" /></span>
              <div>
                <strong>Contact information</strong
                ><small>Start with a reliable name and phone number.</small>
              </div>
            </div>
            <label
              >Customer name *<input
                v-model="form.name"
                required
                maxlength="160" /></label
            ><label
              >Phone number *<input
                v-model="form.phone"
                required
                maxlength="40"
                inputmode="tel"
                placeholder="01XXXXXXXXX" /></label
            ><label
              >Alternate phone<input
                v-model="form.alternate_phone"
                inputmode="tel" /></label
            ><label>Email<input v-model="form.email" type="email" /></label
            ><label>Area / locality<input v-model="form.area" /></label
            ><label>District<input v-model="form.district" /></label
            ><label>Division<input v-model="form.division" /></label>
            <div class="crm-form-section full">
              <span class="crm-section-icon"><Icon name="menu-2" :size="17" /></span>
              <div>
                <strong>Qualification & source</strong
                ><small>Capture where this prospect came from and what they need.</small>
              </div>
            </div>
            <label
              >Lead source<select v-model="form.source">
                <option value="manual">Manual / phone list</option>
                <option value="facebook">Facebook</option>
                <option value="website">Website</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="referral">Referral</option>
                <option value="walk_in">Walk-in</option>
                <option value="phone">Inbound phone</option>
                <option value="other">Other</option>
              </select></label
            ><label
              >Source details<input
                v-model="form.source_detail"
                placeholder="Campaign, group, referrer…" /></label
            ><label
              >Customer type<select v-model="form.customer_type">
                <option value="undecided">Undecided</option>
                <option value="retail">Retail</option>
                <option value="b2b">B2B / Wholesale</option>
              </select></label
            ><label
              >Interested product<input
                v-model="form.interested_product"
                placeholder="Product or variant" /></label
            ><label
              >Pipeline status<select v-model="form.status">
                <option v-for="s in editableStatuses" :key="s.value" :value="s.value">
                  {{ s.label }}
                </option>
              </select></label
            ><label
              >Interest level<select v-model="form.interest_level">
                <option value="hot">Hot</option>
                <option value="warm">Warm</option>
                <option value="cold">Cold</option>
              </select></label
            ><label
              >Next follow-up<input
                v-model="form.next_follow_up_at"
                type="datetime-local"
            /></label>
            <label v-if="!editing && assignmentLocations.length"
              >Team location
              <select v-model="form.location_id">
                <option :value="null">Auto-select from my team</option>
                <option v-for="location in assignmentLocations" :key="location.id" :value="location.id">
                  {{ location.name }}
                </option>
              </select>
            </label>
            <div v-if="!editing" class="crm-owner-default-note">
              <span class="crm-owner-default-icon"><Icon name="shield-check" :size="16" /></span>
              <div>
                <span class="crm-owner-badge">Permanent owner</span>
                <strong>You’ll own this lead</strong>
                <small>Assign a teammate for service; the original owner never changes.</small>
              </div>
            </div>
            <div class="crm-form-section full">
              <span class="crm-section-icon"><Icon name="map-pin" :size="17" /></span>
              <div>
                <strong>Location & notes</strong
                ><small>Keep useful context for the next conversation.</small>
              </div>
            </div>
            <label class="full"
              >Address<textarea v-model="form.address_line" rows="2" /></label
            ><label class="full"
              >Notes<textarea
                v-model="form.notes"
                rows="3"
                placeholder="Requirements, quantity, budget, questions or objections…"
              />
            </label>
          </div>
          <footer>
            <button type="button" class="crm-secondary" @click="formOpen = false">
              Cancel</button
            ><button class="crm-primary" type="submit" :disabled="saving">
              <Icon name="check" :size="16" />{{
                saving ? "Saving…" : editing ? "Save changes" : "Create lead"
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
    <div v-if="detail" class="crm-overlay" @click.self="detail = null">
      <section class="crm-modal detail-modal detail-modal-premium">
        <header>
          <div>
            <div class="eyebrow">{{ detail.lead_code }}</div>
            <h2>{{ detail.name }}</h2>
            <p>
              {{ detail.phone }} <span v-if="detail.email">· {{ detail.email }}</span>
            </p>
          </div>
          <button class="crm-close" @click="detail = null">×</button>
        </header>
        <div class="crm-detail-hero">
          <div class="crm-detail-avatar"><Icon name="user" :size="25" /></div>
          <div class="crm-detail-identity">
            <div class="crm-detail-badges">
              <span class="crm-status" :class="detail.status">{{
                labelForStatus(detail.status)
              }}</span
              ><span class="crm-type" :class="detail.customer_type">{{
                detail.customer_type === "b2b"
                  ? "B2B / Wholesale"
                  : detail.customer_type === "retail"
                  ? "Retail"
                  : "Undecided"
              }}</span
              ><span class="crm-interest" :class="detail.interest_level"
                >{{ detail.interest_level }} interest</span
              >
            </div>
            <p>
              <Icon name="phone" :size="14" /> {{ detail.phone }}
              <span v-if="detail.alternate_phone"> · {{ detail.alternate_phone }}</span>
              <span v-if="detail.email"> · {{ detail.email }}</span>
            </p>
            <small
              ><Icon name="calendar-plus" :size="13" /> Added
              {{ formatDate(detail.created_at) }} by
              <strong>{{ detail.creator?.name || "Unknown user" }}</strong></small
            >
          </div>
        </div>
        <div v-if="detail.customer || detail.status === 'converted'" class="crm-converted-banner">
          <div class="crm-converted-icon"><Icon name="user-check" :size="19" /></div>
          <div class="crm-converted-copy">
            <strong>Converted to Customer</strong>
            <span v-if="detail.customer">{{ detail.customer.customer_code }} · {{ detail.customer.name }}<span v-if="detail.customer.phone"> · {{ detail.customer.phone }}</span></span>
            <span v-else>Conversion status recorded, but the linked customer record was not returned.</span>
          </div>
          <button v-if="detail.customer" type="button" class="crm-customer-link" @click="viewCustomer">View customer <Icon name="arrow-up-right" :size="14" /></button>
        </div>
        <div v-else-if="detail.status === 'qualified'" class="crm-convert-action">
          <div class="crm-convert-mark"><Icon name="user-round-check" :size="21" /></div>
          <div class="crm-convert-copy"><span class="crm-convert-eyebrow">QUALIFIED LEAD</span><strong>Ready for the next step?</strong><span>Convert this lead to a customer, then prepare an order with their details pre-filled. Product, quantity and live catalogue pricing can be reviewed before saving.</span></div>
          <button type="button" class="crm-convert-cta" :disabled="saving" @click="convertLeadToCustomer"><Icon name="arrow-right-circle" :size="17" /> Convert & create order <Icon name="arrow-up-right" :size="14" /></button>
        </div>
        <div class="crm-detail-summary">
          <div class="crm-detail-edit-card pipeline-edit-card" :class="{ 'is-locked': detail.status === 'converted' }">
            <small><Icon name="git-branch" :size="13" /> Pipeline <span v-if="detail.status === 'converted'" class="crm-lock-chip"><Icon name="lock-keyhole" :size="11" /> Locked <Icon name="check-circle-2" :size="12" /></span></small>
            <select
              class="crm-detail-inline-select"
              :value="detail.status"
              :disabled="quickSaving || detail.status === 'converted'"
              aria-label="Update pipeline status"
              @change="quickUpdateLead(detail, 'status', $event)"
            >
              <option
                v-for="s in statuses.filter((item) => item.value !== 'converted')"
                :key="s.value"
                :value="s.value"
              >
                {{ s.label }}
              </option>
            </select>
          </div>
          <div class="crm-detail-edit-card followup-edit-card" :class="{ 'is-locked': detail.status === 'converted' }">
            <small><Icon name="calendar-clock" :size="13" /> Next follow-up <span v-if="detail.status === 'converted'" class="crm-lock-chip"><Icon name="lock-keyhole" :size="11" /> Locked <Icon name="check-circle-2" :size="12" /></span></small>
            <input
              class="crm-detail-inline-date"
              type="datetime-local"
              :value="toLocalInput(detail.next_follow_up_at)"
              :disabled="quickSaving || detail.status === 'converted'"
              aria-label="Update next follow-up"
              @change="quickUpdateLead(detail, 'next_follow_up_at', $event)"
            />
            <span
              v-if="detail.next_follow_up_at"
              class="crm-detail-date-current"
              :class="{
                overdue:
                  isOverdue(detail.next_follow_up_at) &&
                  !['converted', 'lost'].includes(detail.status),
              }"
              >Current: {{ formatDate(detail.next_follow_up_at) }}</span
            >
          </div>
          <div>
            <small><Icon name="messages-square" :size="13" /> Interactions</small
            ><strong>{{
              detail.activities_count || detail.activities?.length || 0
            }}</strong>
          </div>
          <div class="crm-owner-assigned-card">
            <small><Icon name="user-check" :size="13" /> Created by / Assigned</small>
            <strong>{{ detail.creator?.name || "Unknown user" }}</strong>
            <small class="crm-assigned-label">Assigned: {{ detail.assignee?.name || "Unassigned" }}</small>
          </div>
          <div v-if="canManageAssignments || detail.assigned_to === assignmentCurrentUserId || detail.created_by === assignmentCurrentUserId" class="crm-assignment-panel" :class="{ 'is-locked': detail.status === 'converted' }">
            <small><Icon name="users" :size="13" /> Assigned teammate <span v-if="detail.status === 'converted'" class="crm-lock-chip"><Icon name="lock-keyhole" :size="11" /> Locked <Icon name="check-circle-2" :size="12" /></span></small>
            <select v-if="!detail.location_id && assignmentLocations.length" v-model="assignmentLocationId" :disabled="assignmentSaving || detail.status === 'converted'">
              <option :value="null">Select team location…</option>
              <option v-for="location in assignmentLocations" :key="location.id" :value="location.id">{{ location.name }}</option>
            </select>
            <select v-model="assignmentTarget" :disabled="assignmentSaving || detail.status === 'converted'">
              <option :value="null">Select team member…</option>
              <option
                v-for="member in membersForLocation(detail.location_id || assignmentLocationId)"
                :key="member.id"
                :value="member.id"
                :disabled="member.id === detail.assigned_to || (member.id === assignmentCurrentUserId && !canManageAssignments)"
              >
                {{ member.name }}{{ member.designation ? ` · ${member.designation}` : "" }}
              </option>
            </select>
            <input v-model="assignmentReason" maxlength="500" placeholder="Reason for assignment / transfer (optional)" :disabled="assignmentSaving || detail.status === 'converted'" />
            <button v-if="detail.status !== 'converted'" class="crm-primary" type="button" :disabled="!assignmentTarget || assignmentSaving" @click="saveAssignment">
              {{ assignmentSaving ? "Saving…" : "Update owner" }}
            </button>
          </div>
          <div>
            <small><Icon name="map-pin" :size="13" /> Location</small
            ><strong>{{
              [detail.area, detail.district, detail.division]
                .filter(Boolean)
                .join(", ") || "Not provided"
            }}</strong>
          </div>
          <div>
            <small><Icon name="package" :size="13" /> Interested product</small
            ><strong>{{ detail.interested_product || "Not specified" }}</strong>
          </div>
          <div>
            <small><Icon name="share-2" :size="13" /> Lead source</small
            ><strong>{{ detail.source.replaceAll("_", " ") || "Not specified" }}<span v-if="detail.source_detail"> · {{ detail.source_detail }}</span></strong>
          </div>
        </div>
        <div v-if="detail.notes" class="crm-detail-context">
          <div v-if="detail.notes">
            <small>Notes & requirements</small>
            <p>{{ detail.notes }}</p>
          </div>
        </div>
        <div class="crm-detail-actions">
          <button v-if="detail.status !== 'converted'" class="crm-secondary" @click="openEdit(detail!); detail = null">
            <Icon name="pencil" :size="15" /> Edit lead</button
          ><button v-if="detail.status !== 'converted'" class="crm-primary" @click="openActivity">
            <Icon name="phone-call" :size="15" /> Log interaction
          </button>
        </div>
        <div v-if="detail.assignment_history?.length" class="crm-timeline crm-assignment-history">
          <h3><Icon name="users" :size="16" /> Assignment history</h3>
          <div v-for="entry in detail.assignment_history" :key="entry.id" class="crm-assignment-entry">
            <strong v-if="entry.reason === 'Lead owner set to creator on creation'">Created and assigned to {{ entry.to_user?.name || detail.creator?.name || 'lead creator' }}</strong>
            <strong v-else>{{ entry.from_user?.name || 'Previous owner not recorded' }} → {{ entry.to_user?.name || 'Unassigned' }}</strong>
            <small>{{ entry.actor?.name || "System" }} · {{ formatDate(entry.created_at) }}</small>
            <p v-if="entry.reason && entry.reason !== 'Lead owner set to creator on creation'">{{ entry.reason }}</p>
          </div>
        </div>
        <div class="crm-timeline">
          <h3><Icon name="history" :size="16" /> Activity timeline</h3>
          <div v-if="!detail.activities?.length" class="crm-empty small">
            No interactions recorded yet.
          </div>
          <article
            v-for="item in detail.activities"
            :key="item.id"
            class="crm-timeline-item"
          >
            <span class="crm-timeline-dot"
              ><Icon
                :name="item.activity_type === 'call' ? 'phone' : 'message-circle'"
                :size="15"
            /></span>
            <div>
              <strong
                >{{ item.activity_type.replaceAll("_", " ")
                }}<span v-if="item.outcome">
                  · {{ item.outcome.replaceAll("_", " ") }}</span
                ></strong
              ><small
                >{{ formatDate(item.occurred_at) }} ·
                {{ item.creator?.name || "Former user" }}</small
              >
              <p>{{ item.summary }}</p>
              <small v-if="item.next_follow_up_at"
                >Follow-up: {{ formatDate(item.next_follow_up_at) }}</small
              >
            </div>
          </article>
        </div>
        <div v-if="detail.customer" class="crm-linked">
          Linked customer: <strong>{{ detail.customer.name }}</strong> ({{
            detail.customer.customer_code
          }})
        </div>
        <div v-if="detail.converted_order" class="crm-linked">
          Order: <strong>{{ detail.converted_order.order_number }}</strong> ·
          {{ detail.converted_order.status }}
        </div>
      </section>
    </div>
    <div
      v-if="activityOpen"
      class="crm-overlay nested"
      @click.self="activityOpen = false"
    >
      <section class="crm-modal activity-modal">
        <header>
          <div>
            <div class="eyebrow">ACTIVITY LOG</div>
            <h2>Record interaction</h2>
            <p>Every interaction is saved to the lead timeline.</p>
          </div>
          <button class="crm-close" @click="activityOpen = false">×</button>
        </header>
        <form @submit.prevent="saveActivity">
          <div class="crm-form-grid">
            <label
              >Activity type<select v-model="activity.activity_type">
                <option value="call">Phone call</option>
                <option value="note">Note</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="email">Email</option>
                <option value="meeting">Meeting</option>
                <option value="other">Other</option>
              </select></label
            ><label
              >Outcome<select v-model="activity.outcome">
                <option value="answered">Answered / spoke</option>
                <option value="no_answer">No answer</option>
                <option value="busy">Busy</option>
                <option value="callback_requested">Callback requested</option>
                <option value="interested">Interested</option>
                <option value="not_interested">Not interested</option>
                <option value="wrong_number">Wrong number</option>
                <option value="message_sent">Message sent</option>
                <option value="other">Other</option>
              </select></label
            ><label
              >Interaction date & time *<input
                v-model="activity.occurred_at"
                type="datetime-local"
                required /></label
            ><label
              >Duration (seconds)<input
                v-model="activity.duration_seconds"
                type="number"
                min="0"
                max="86400" /></label
            ><label class="full"
              >Conversation summary *<textarea
                v-model="activity.summary"
                required
                rows="4"
                placeholder="What did the customer say? Needs, objection, decision…"
              /></label
            ><label class="full"
              >Next follow-up<input
                v-model="activity.next_follow_up_at"
                type="datetime-local"
            /></label>
          </div>
          <footer>
            <button type="button" class="crm-secondary" @click="activityOpen = false">
              Cancel</button
            ><button class="crm-primary" type="submit" :disabled="activitySaving">
              <Icon name="check" :size="16" />{{
                activitySaving ? "Saving…" : "Save activity"
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
    <div v-if="convertConfirmOpen && detail" class="crm-convert-confirm-backdrop" role="presentation" @click.self="convertConfirmOpen = false" @keydown.esc="convertConfirmOpen = false">
      <section class="crm-convert-confirm" role="dialog" aria-modal="true" aria-labelledby="crm-convert-confirm-title">
        <button class="crm-convert-confirm-close" type="button" aria-label="Close confirmation" :disabled="saving" @click="convertConfirmOpen = false"><Icon name="x" :size="18" /></button>
        <div class="crm-confirm-icon"><Icon name="user-round-check" :size="25" /></div>
        <span class="crm-confirm-kicker">LEAD CONVERSION</span>
        <h3 id="crm-convert-confirm-title">Convert {{ detail.name }} to a customer?</h3>
        <p>A customer record is created only after an order succeeds. Choose a location and at least one product in the next step; prices and stock are verified by the server.</p>
        <div class="crm-confirm-summary"><span><Icon name="user-round" :size="15" /> {{ detail.name }}</span><span v-if="detail.phone"><Icon name="phone" :size="15" /> {{ detail.phone }}</span><span v-if="detail.interested_product"><Icon name="package" :size="15" /> Interested in {{ detail.interested_product }}</span></div>
        <div class="crm-confirm-actions"><button type="button" class="crm-confirm-cancel" :disabled="saving" @click="convertConfirmOpen = false">Not yet</button><button type="button" class="crm-confirm-primary" :disabled="saving" @click="confirmConvertAndCreateOrder"><Icon :name="saving ? 'loader-circle' : 'check-circle-2'" :size="16" /> {{ saving ? 'Converting…' : 'Continue to order' }}</button></div>
      </section>
    </div>
    <div v-if="orderModalOpen && detail" class="crm-order-backdrop" role="presentation" @click.self="!orderSubmitting && (orderModalOpen = false)">
      <section class="crm-order-modal" role="dialog" aria-modal="true" aria-labelledby="crm-order-title">
        <header class="crm-order-header">
          <div><span class="crm-order-kicker">ORDER FIRST · CUSTOMER AFTER PURCHASE</span><h3 id="crm-order-title">Create order for {{ detail.name }}</h3><p>{{ detail.lead_code }} · {{ detail.phone }}</p></div>
          <button type="button" class="crm-convert-confirm-close" :disabled="orderSubmitting" aria-label="Close order modal" @click="orderModalOpen = false"><Icon name="x" :size="18" /></button>
        </header>
        <div class="crm-order-body">
          <div class="crm-order-grid">
            <label>Fulfilment location<select v-model.number="orderLocationId" :disabled="orderOptionsLoading || orderSubmitting" @change="loadOrderVariants().catch(e => error = e.message)"><option :value="null" disabled>Select location</option><option v-for="loc in orderLocations" :key="loc.id" :value="loc.id">{{ loc.name }}</option></select></label>
            <label>Product / variant<select v-model.number="orderVariantId" :disabled="orderOptionsLoading || orderSubmitting || !orderVariants.length"><option :value="null">Select product</option><option v-for="v in orderVariants" :key="v.id" :value="v.id">{{ v.name }} · ৳{{ Number(v.price).toLocaleString() }} · Available {{ v.available }}</option></select></label>
            <label>Quantity<input v-model.number="orderQuantity" type="number" min="0.0001" step="any" :disabled="orderSubmitting" /></label>
            <button type="button" class="crm-primary crm-order-add" :disabled="!selectedOrderVariant || orderQuantity <= 0 || orderSubmitting" @click="addOrderLine"><Icon name="plus" :size="15" /> Add product</button>
          </div>
          <div v-if="orderOptionsLoading" class="crm-order-empty">Loading location and catalogue…</div>
          <div v-else-if="!orderVariants.length" class="crm-order-empty">No sellable products with inventory were found at this location.</div>
          <div v-else-if="!orderCart.length" class="crm-order-empty">Add at least one product to place the order. Customer conversion happens only after order success.</div>
          <div v-if="orderCart.length" class="crm-order-lines">
            <div v-for="line in orderCart" :key="line.product_variant_id" class="crm-order-line"><div><strong>{{ line.name }}</strong><small>{{ line.sku || 'No SKU' }} · {{ line.quantity }} × ৳{{ line.price.toLocaleString() }}</small></div><strong>৳{{ (line.price * line.quantity).toLocaleString() }}</strong><button type="button" :disabled="orderSubmitting" aria-label="Remove item" @click="orderCart = orderCart.filter(item => item.product_variant_id !== line.product_variant_id)"><Icon name="trash-2" :size="15" /></button></div>
            <div class="crm-order-total"><span>Order subtotal</span><strong>৳{{ orderSubtotal.toLocaleString() }}</strong></div>
          </div>
          <label class="crm-order-notes">Order notes<textarea v-model="orderNotes" rows="2" placeholder="Optional note for this order" :disabled="orderSubmitting"></textarea></label>
          <p class="crm-order-footnote"><Icon name="shield-check" :size="15" /> Prices and stock are verified server-side. If order creation fails, this lead remains a lead.</p>
          <p v-if="error" class="crm-order-error">{{ error }}</p>
        </div>
        <footer class="crm-order-footer"><button type="button" class="crm-secondary" :disabled="orderSubmitting" @click="orderModalOpen = false">Cancel</button><button type="button" class="crm-primary" :disabled="orderSubmitting || orderOptionsLoading || !orderLocationId || !orderCart.length" @click="submitConversionOrder"><Icon :name="orderSubmitting ? 'loader-circle' : 'shopping-cart'" :size="16" /> {{ orderSubmitting ? 'Creating order…' : 'Place order & convert to customer' }}</button></footer>
      </section>
    </div>
  </ErpLayout>
</template>

<style scoped>
.crm-page {
  /* Let the CRM page use the full content width without extra side gutters. */
  padding: 28px 0;
  width: 100%;
  max-width: none;
  margin: 0;
  color: var(--text-primary, #111827);
}
.crm-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 24px;
}
.crm-heading h1 {
  font-size: 28px;
  line-height: 1.2;
  letter-spacing: -0.04em;
  font-weight: 750;
  margin: 5px 0 7px;
}
.crm-heading p,
.crm-panel-head p,
.crm-modal header p {
  margin: 0;
  color: #718096;
  font-size: 13px;
}
.eyebrow {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.14em;
  color: #059669;
}
.crm-primary,
.crm-secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  padding: 10px 16px;
  border-radius: 11px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: 0.18s;
}
.crm-primary {
  background: #059669;
  color: #fff;
  border: 1px solid #059669;
  box-shadow: 0 3px 8px #0596691c;
}
.crm-primary:hover:not(:disabled) {
  background: #047857;
}
.crm-primary:disabled {
  opacity: 0.6;
  cursor: wait;
}
.crm-secondary {
  background: #fff;
  color: #334155;
  border: 1px solid #dbe3ed;
}
.crm-alert {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 12px 14px;
  border-radius: 12px;
  margin-bottom: 14px;
  font-size: 13px;
}
.crm-alert button {
  margin-left: auto;
  background: transparent;
  border: 0;
  font-size: 20px;
  cursor: pointer;
}
.crm-alert.success {
  color: #047857;
  background: #ecfdf5;
  border: 1px solid #bbf7d0;
}
.crm-alert.danger {
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
}
.crm-kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 22px;
}
.crm-kpis article {
  border: 1px solid #e1e8f0;
  border-radius: 16px;
  background: #fff;
  padding: 20px 22px;
  box-shadow: 0 4px 14px #0f172a05;
}
.crm-kpis span,
.crm-kpis small {
  display: block;
  color: #8190a6;
  font-size: 12px;
}
.crm-kpis strong {
  display: block;
  font-size: 29px;
  line-height: 1.3;
  margin: 10px 0 7px;
  font-weight: 750;
  color: #172033;
}
.crm-kpis .attention strong {
  color: #b7791f;
}
.crm-kpis .positive strong {
  color: #059669;
}
.crm-panel {
  background: #fff;
  border: 1px solid #e0e8f1;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 8px 24px #0f172a06;
}
.crm-panel-head {
  padding: 22px 24px 18px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
}
.crm-panel-head h2 {
  font-size: 16px;
  font-weight: 750;
  margin: 0 0 5px;
}
.crm-count {
  border: 1px solid #dbe5ef;
  border-radius: 9px;
  padding: 8px 11px;
  color: #64748b;
  font-size: 12px;
  font-weight: 650;
  white-space: nowrap;
}
.crm-filters {
  display: flex;
  gap: 12px;
  align-items: center;
  padding: 0 24px 18px;
  flex-wrap: wrap;
}
.crm-search {
  display: flex;
  align-items: center;
  gap: 9px;
  flex: 1;
  min-width: 230px;
  border: 1px solid #dce5ef;
  border-radius: 10px;
  padding: 0 12px;
  color: #94a3b8;
  height: 42px;
}
.crm-search input {
  border: 0;
  outline: 0;
  min-width: 0;
  flex: 1;
  font-size: 13px;
  background: transparent;
  color: #1e293b;
}
.crm-filters label:not(.crm-search):not(.crm-check) {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #64748b;
  font-size: 12px;
}
.crm-filters select,
.crm-form-grid input,
.crm-form-grid select,
.crm-form-grid textarea {
  border: 1px solid #dbe4ee;
  border-radius: 9px;
  padding: 10px 11px;
  background: #fff;
  color: #334155;
  font: inherit;
  font-size: 13px;
  min-width: 0;
  width: 100%;
  outline: none;
}
.crm-filters select {
  width: auto;
  min-width: 120px;
  height: 42px;
}
.crm-form-grid input:focus,
.crm-form-grid select:focus,
.crm-form-grid textarea:focus,
.crm-filters select:focus {
  border-color: #10b981;
  box-shadow: 0 0 0 3px #10b9811a;
}
.crm-check {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  color: #475569;
  white-space: nowrap;
}
.crm-check input {
  accent-color: #059669;
}
.crm-table-wrap {
  overflow-x: auto;
  border-top: 1px solid #e8eef5;
}
.crm-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  white-space: normal;
}
.crm-table th {
  background: #f8fafc;
  color: #64748b;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 13px 16px;
  font-weight: 750;
  white-space: nowrap;
}
.crm-table td {
  padding: 15px 16px;
  border-top: 1px solid #edf1f6;
  font-size: 12px;
  color: #64748b;
  vertical-align: middle;
  min-width: 100px;
}
.crm-table td:first-child {
  min-width: 180px;
}
.crm-table td small {
  display: block;
  color: #94a3b8;
  font-size: 11px;
  margin-top: 4px;
}
.crm-link {
  font-size: 13px;
  font-weight: 750;
  color: #0f766e;
  background: none;
  border: 0;
  padding: 0;
  text-align: left;
  cursor: pointer;
}
.crm-link:hover {
  text-decoration: underline;
}
.crm-status,
.crm-type {
  display: inline-flex;
  align-items: center;
  border-radius: 7px;
  padding: 5px 8px;
  font-size: 11px;
  font-weight: 700;
  text-transform: capitalize;
  background: #f1f5f9;
  color: #475569;
}
.crm-status.new {
  background: #eff6ff;
  color: #1d4ed8;
}
.crm-status.contacting,
.crm-status.qualified,
.crm-status.interested {
  background: #ecfeff;
  color: #0e7490;
}
.crm-status.follow_up {
  background: #fffbeb;
  color: #b45309;
}
.crm-status.converted {
  background: #ecfdf5;
  color: #047857;
}
.crm-status.lost {
  background: #fef2f2;
  color: #b91c1c;
}
.crm-type.b2b {
  background: #f5f3ff;
  color: #6d28d9;
}
.crm-type.retail {
  background: #eff6ff;
  color: #1d4ed8;
}
.overdue {
  color: #b91c1c;
  font-weight: 750;
}
.crm-actions {
  display: flex;
  gap: 6px;
}
.crm-actions button {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border: 1px solid #dfe7f0;
  border-radius: 9px;
  background: #fff;
  color: #64748b;
  cursor: pointer;
}
.crm-actions button:hover {
  color: #047857;
  border-color: #86efac;
  background: #f0fdf4;
}
.crm-assignment-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 220px;
  margin-top: 12px;
}
.crm-assignment-panel select,
.crm-assignment-panel input {
  width: 100%;
  min-width: 0;
  padding: 9px 10px;
  border: 1px solid #dce4ed;
  border-radius: 8px;
  background: #fff;
  color: #334155;
  font-size: 12px;
}
.crm-assignment-panel .crm-primary {
  justify-content: center;
}
.crm-assignment-entry {
  padding: 10px 0;
  border-bottom: 1px solid #edf1f6;
}
.crm-assignment-entry strong,
.crm-assignment-entry small {
  display: block;
}
.crm-assignment-entry small {
  margin-top: 4px;
  color: #94a3b8;
}
.crm-assignment-entry p {
  margin: 6px 0 0;
  color: #64748b;
  font-size: 12px;
}
.crm-empty {
  display: flex;
  min-height: 160px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 10px;
  color: #94a3b8;
  font-size: 13px;
}
.crm-empty strong {
  color: #475569;
}
.crm-empty.small {
  min-height: 80px;
}
.crm-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-top: 1px solid #edf1f6;
  color: #94a3b8;
  font-size: 12px;
}
.crm-pagination div {
  display: flex;
  gap: 8px;
}
.crm-pagination button {
  border: 1px solid #dce4ed;
  background: #fff;
  color: #475569;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 12px;
  cursor: pointer;
}
.crm-pagination button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.crm-overlay {
  position: fixed;
  inset: 0;
  z-index: 80;
  background: #0f172a80;
  backdrop-filter: blur(5px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.crm-modal {
  background: #fff;
  border-radius: 20px;
  clip-path: inset(0 round 20px);
  width: min(850px, 100%);
  max-height: 90vh;
  overflow: auto;
  scrollbar-width: thin;
  box-shadow: 0 24px 80px #02061740;
}
.crm-modal > header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 24px 28px;
  border-bottom: 1px solid #e8eef5;
  background: linear-gradient(120deg, #fff, #f5faff);
}
.crm-modal h2 {
  font-size: 23px;
  letter-spacing: -0.035em;
  margin: 5px 0 7px;
  font-weight: 750;
}
.crm-close {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border: 1px solid #dbe4ee;
  background: #fff;
  border-radius: 11px;
  font-size: 23px;
  color: #475569;
  cursor: pointer;
}
.crm-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  padding: 24px 28px;
}
.crm-form-grid label {
  display: flex;
  flex-direction: column;
  gap: 7px;
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  min-width: 0;
}
.crm-form-grid .full {
  grid-column: 1/-1;
}
.crm-modal form > footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 28px 24px;
  border-top: 1px solid #edf1f6;
}
.detail-modal {
  width: min(1060px, 100%);
  border-radius: 20px;
  clip-path: inset(0 round 20px);
}
.crm-detail-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  padding: 18px 26px;
}
.crm-detail-summary > div {
  border: 1px solid #e5edf5;
  border-radius: 14px;
  padding: 13px 14px;
  background: #f8fafc;
  transition: border-color 0.18s ease, background 0.18s ease, transform 0.18s ease;
}
.crm-detail-summary > div:nth-child(1) {
  background: #eff6ff;
  border-color: #bfdbfe;
}
.crm-detail-summary > div:nth-child(2) {
  background: #fff7ed;
  border-color: #fed7aa;
}
.crm-detail-summary > div:nth-child(3) {
  background: #ecfdf5;
  border-color: #a7f3d0;
}
.crm-detail-summary > div:nth-child(4) {
  background: #f5f3ff;
  border-color: #ddd6fe;
}
.crm-detail-summary > div:nth-child(5) {
  background: #ecfeff;
  border-color: #a5f3fc;
}
.crm-detail-summary > div:nth-child(6) {
  background: #fefce8;
  border-color: #fde68a;
}
.crm-detail-summary > div:nth-child(7) {
  background: #fff1f2;
  border-color: #fecdd3;
}
.crm-detail-summary > div:hover {
  transform: translateY(-1px);
}
.crm-detail-summary > div:nth-child(1) small,
.crm-detail-summary > div:nth-child(1) small :deep(svg) {
  color: #2563eb;
}
.crm-detail-summary > div:nth-child(2) small {
  color: #c2410c;
}
.crm-detail-summary > div:nth-child(3) small {
  color: #047857;
}
.crm-detail-summary > div:nth-child(4) small {
  color: #6d28d9;
}
.crm-detail-summary > div:nth-child(5) small {
  color: #0e7490;
}
.crm-detail-summary > div:nth-child(6) small {
  color: #a16207;
}
.crm-detail-summary > div:nth-child(7) small {
  color: #be123c;
}
.crm-detail-summary small,
.crm-detail-summary strong {
  display: block;
}
.crm-detail-summary small {
  color: #94a3b8;
  font-size: 11px;
  margin-bottom: 6px;
}
.crm-detail-summary strong {
  font-size: 13px;
  color: #334155;
  text-transform: capitalize;
}
.crm-detail-summary > div small :deep(svg) {
  vertical-align: -2px;
}
.crm-detail-actions {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  padding: 0 26px 18px;
}
.crm-timeline {
  padding: 0 26px 24px;
}
.crm-timeline h3 {
  font-size: 15px;
  margin: 5px 0 18px;
}
.crm-timeline-item {
  display: flex;
  gap: 12px;
  padding: 0 0 18px;
  position: relative;
}
.crm-timeline-dot {
  flex: 0 0 32px;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  background: #ecfdf5;
  color: #059669;
  display: grid;
  place-items: center;
}
.crm-timeline-item strong {
  display: block;
  font-size: 13px;
  text-transform: capitalize;
}
.crm-timeline-item small {
  display: block;
  font-size: 11px;
  color: #94a3b8;
  margin-top: 4px;
}
.crm-timeline-item p {
  font-size: 13px;
  line-height: 1.55;
  color: #475569;
  margin: 8px 0;
}
.crm-linked {
  padding: 12px 26px;
  background: #f8fafc;
  border-top: 1px solid #edf1f6;
  font-size: 12px;
  color: #64748b;
}
.nested {
  z-index: 90;
}
.activity-modal {
  width: min(680px, 100%);
}
/* Customer-index-aligned CRM visual system */
.crm-page {
  padding: 0 12px 16px;
  max-width: 1480px;
  display: grid;
  gap: 16px;
  font-size: 12px;
}
.crm-heading {
  margin: 0;
  align-items: flex-end;
}
.crm-heading h1 {
  font-size: 20px;
  letter-spacing: -0.04em;
  margin: 0;
  font-weight: 750;
}
.crm-heading p {
  font-size: 11px;
  margin-top: 5px;
}
.crumb {
  display: flex;
  align-items: center;
  gap: 7px;
  color: var(--muted-foreground, #788397);
  font-size: 10px;
  margin-bottom: 8px;
}
.crumb b {
  color: var(--foreground, #172033);
  font-weight: 600;
}
.title-line {
  display: flex;
  align-items: center;
  gap: 12px;
}
.hero-icon {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: grid;
  place-items: center;
  border: 1px solid #d8e4ff;
  border-radius: 12px;
  background: linear-gradient(145deg, #f0f5ff, #e7eeff);
  color: #3159c9;
}
.crm-primary,
.crm-secondary {
  min-height: 38px;
  padding: 0 13px;
  border-radius: 9px;
  font-size: 11px;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.crm-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 5px 13px #05966924;
}
.crm-kpis {
  gap: 16px;
  margin: 0;
}
.crm-kpis article {
  border-radius: 14px;
  padding: 16px 20px;
  box-shadow: 0 3px 12px #0f172a05;
}
.crm-kpis span,
.crm-kpis small {
  font-size: 11px;
}
.crm-kpis strong {
  font-size: 26px;
  margin: 8px 0 5px;
}
.crm-panel {
  border-radius: 16px;
  box-shadow: 0 4px 16px #0f172a05;
}
.crm-panel-head {
  padding: 18px 22px 14px;
}
.crm-panel-head h2 {
  font-size: 14px;
  margin-bottom: 5px;
}
.crm-panel-head p {
  font-size: 11px;
}
.crm-count {
  font-size: 11px;
  padding: 7px 10px;
}
.crm-filters {
  padding: 0 22px 15px;
  gap: 9px;
}
.crm-filters label:not(.crm-search):not(.crm-check) {
  gap: 6px;
  font-size: 11px;
}
.crm-filters select {
  min-width: 105px;
  height: 38px;
  font-size: 11px;
  padding: 8px 10px;
}
.crm-search {
  height: 38px;
  border-radius: 9px;
  min-width: 220px;
}
.crm-search input {
  font-size: 11px;
}
.crm-search button {
  border: 0;
  background: transparent;
  color: #94a3b8;
  display: grid;
  place-items: center;
  cursor: pointer;
}
.crm-secondary.compact {
  min-height: 36px;
  padding: 0 10px;
  white-space: nowrap;
}
.crm-table th {
  font-size: 10px;
  padding: 12px 14px;
}
.crm-table td {
  padding: 13px 14px;
  font-size: 11px;
}
.crm-table td:first-child {
  min-width: 190px;
}
.crm-link {
  font-size: 12px;
}
.crm-table td small {
  font-size: 10px;
}
.crm-lead-row {
  cursor: pointer;
  transition: background 0.15s ease;
}
.crm-lead-row:hover {
  background: #f8fbff;
}
.crm-lead-row:focus-visible {
  outline: 2px solid #10b981;
  outline-offset: -2px;
}
.created-by {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #334155;
  font-weight: 650;
}
.crm-actions {
  position: relative;
  z-index: 1;
}
.crm-pagination {
  padding: 14px 22px;
  font-size: 11px;
}
.crm-modal {
  border-radius: 18px;
}
.crm-modal > header {
  padding: 20px 24px;
}
.crm-modal h2 {
  font-size: 20px;
}
.crm-form-grid {
  padding: 20px 24px;
  gap: 14px;
}
.crm-form-grid label {
  font-size: 11px;
}
.crm-form-grid input,
.crm-form-grid select,
.crm-form-grid textarea {
  font-size: 12px;
  padding: 10px;
}
.crm-form-section {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid #e4ecf5;
  border-radius: 11px;
  background: linear-gradient(110deg, #f8fbff, #fff);
}
.crm-section-icon {
  width: 32px;
  height: 32px;
  display: grid;
  place-items: center;
  border: 1px solid #d8f1e5;
  border-radius: 9px;
  background: #ecfdf5;
  color: #059669;
  flex: 0 0 32px;
}
.crm-form-section strong,
.crm-form-section small {
  display: block;
}
.crm-form-section strong {
  font-size: 12px;
  color: #243247;
}
.crm-form-section small {
  font-size: 10px;
  color: #8491a5;
  margin-top: 3px;
}
.detail-modal {
  width: min(900px, 100%);
}
.crm-detail-hero {
  display: flex;
  gap: 14px;
  align-items: center;
  padding: 20px 24px 14px;
}
.crm-detail-avatar {
  width: 52px;
  height: 52px;
  display: grid;
  place-items: center;
  border-radius: 15px;
  background: linear-gradient(145deg, #ecfdf5, #d1fae5);
  color: #059669;
  border: 1px solid #bbf7d0;
  flex: 0 0 52px;
}
.crm-detail-identity {
  min-width: 0;
  flex: 1;
}
.crm-detail-badges {
  display: flex;
  gap: 7px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}
.crm-detail-identity p {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
  font-size: 12px;
  color: #475569;
  margin: 0 0 6px;
}
.crm-detail-identity > small {
  display: flex;
  gap: 5px;
  align-items: center;
  color: #8793a6;
  font-size: 10px;
}
.crm-interest {
  display: inline-flex;
  align-items: center;
  border-radius: 7px;
  padding: 5px 8px;
  background: #fffbeb;
  color: #b45309;
  font-size: 10px;
  font-weight: 700;
  text-transform: capitalize;
}
.crm-interest.hot {
  background: #fff1f2;
  color: #be123c;
}
.crm-interest.cold {
  background: #f1f5f9;
  color: #64748b;
}
.crm-detail-summary {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  padding: 12px 24px 18px;
}
.crm-detail-summary > div {
  border-radius: 11px;
  padding: 12px;
  background: #fff;
  border-color: #e7edf5;
}
.crm-detail-summary small {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 10px;
}
.crm-detail-summary strong {
  font-size: 11px;
  line-height: 1.5;
}
.crm-detail-context {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 12px;
  padding: 0 24px 18px;
}
.crm-detail-context > div {
  padding: 12px;
  border: 1px solid #e7edf5;
  border-radius: 11px;
  min-width: 0;
}
.crm-detail-context small {
  display: block;
  color: #94a3b8;
  font-size: 10px;
  margin-bottom: 5px;
}
.crm-detail-context strong,
.crm-detail-context p {
  font-size: 11px;
  color: #475569;
  line-height: 1.5;
  margin: 0;
  overflow-wrap: anywhere;
}
.crm-detail-actions {
  padding: 0 24px 18px;
}
.crm-timeline {
  padding: 0 24px 24px;
}
.crm-timeline h3 {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 13px;
}
.crm-linked {
  font-size: 11px;
  padding: 12px 24px;
}
@media (max-width: 900px) {
  .crm-page {
    padding: 18px;
  }
  .crm-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .crm-filters {
    padding: 0 16px 16px;
  }
  .crm-panel-head {
    padding: 18px 16px;
  }
  .crm-table th,
  .crm-table td {
    padding: 12px;
  }
  .crm-heading h1 {
    font-size: 24px;
  }
}
@media (max-width: 560px) {
  .crm-heading {
    align-items: flex-start;
    flex-direction: column;
  }
  .crm-kpis {
    gap: 10px;
  }
  .crm-kpis article {
    padding: 15px;
  }
  .crm-kpis strong {
    font-size: 24px;
  }
  .crm-form-grid {
    grid-template-columns: 1fr;
    padding: 18px;
  }
  .crm-form-grid .full {
    grid-column: auto;
  }
  .crm-modal > header {
    padding: 18px;
  }
  .crm-overlay {
    padding: 10px;
  }
  .crm-detail-summary {
    grid-template-columns: 1fr;
    padding: 14px;
  }
  .crm-detail-actions {
    padding: 0 14px 14px;
  }
  .crm-timeline {
    padding: 0 14px 18px;
  }
}

/* Compact, single-row lead register toolbar and tighter content alignment */
.crm-page {
  width: 100%;
  max-width: none;
  margin: 0;
  padding-left: 4px;
  padding-right: 12px;
}
.crm-filters {
  display: grid;
  grid-template-columns: minmax(0, 2.6fr) minmax(190px, 1fr) auto auto;
  align-items: center;
  gap: 10px;
  flex-wrap: nowrap;
  padding: 0 22px 14px;
}
.crm-search {
  width: 100%;
  min-width: 0;
  height: 42px;
  box-sizing: border-box;
  padding: 0 13px;
}
.crm-search input {
  width: 100%;
  font-size: 12px;
}
.crm-sort {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  height: 42px;
  padding: 0 10px;
  border: 1px solid #dbe4ee;
  border-radius: 10px;
  color: #64748b;
  background: #fff;
}
.crm-sort select {
  width: 100%;
  min-width: 0;
  height: 38px;
  border: 0;
  padding: 0 2px;
  font-size: 12px;
  color: #334155;
  background: transparent;
  outline: none;
}
.crm-filter-toggle,
.crm-refresh {
  height: 42px;
  min-height: 42px !important;
  padding: 0 12px !important;
  gap: 7px;
  font-size: 12px;
  white-space: nowrap;
}
.crm-filter-toggle.active {
  border-color: #a7f3d0;
  background: #ecfdf5;
  color: #047857;
}
.crm-filter-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #059669;
}
.crm-refresh:disabled {
  opacity: 0.65;
  cursor: wait;
}
.crm-advanced-filters {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 12px 22px 15px;
  border-top: 1px solid #f1f5f9;
}
.crm-advanced-filters > label:not(.crm-check) {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #64748b;
  font-size: 12px;
}
.crm-advanced-filters select {
  min-width: 145px;
  height: 36px;
  border: 1px solid #dbe4ee;
  border-radius: 9px;
  padding: 0 10px;
  background: #fff;
  color: #334155;
  font: inherit;
}
@media (max-width: 1050px) {
  .crm-filters {
    grid-template-columns: minmax(0, 1fr) minmax(175px, 0.55fr) auto auto;
    gap: 7px;
    padding-left: 16px;
    padding-right: 16px;
  }
  .crm-filter-toggle,
  .crm-refresh {
    padding: 0 9px !important;
  }
}
@media (max-width: 720px) {
  .crm-page {
    padding-left: 8px;
    padding-right: 8px;
  }
  .crm-filters {
    grid-template-columns: minmax(0, 1fr) auto auto;
    padding: 0 14px 14px;
  }
  .crm-search {
    grid-column: 1 / -1;
  }
  .crm-sort {
    grid-column: 1 / 2;
  }
  .crm-advanced-filters {
    padding-left: 14px;
    padding-right: 14px;
  }
}
@media (max-width: 420px) {
  .crm-filters {
    grid-template-columns: minmax(0, 1fr) auto;
  }
  .crm-sort {
    grid-column: 1 / -1;
    grid-row: 2;
  }
  .crm-filter-toggle {
    grid-column: 1;
  }
  .crm-refresh {
    grid-column: 2;
  }
}

@media (max-width: 640px) {
  .crm-page {
    padding-top: 18px;
    padding-bottom: 18px;
  }
  .crm-detail-summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    padding: 14px;
  }
}

/* Quick inline editing for lead register and lead details */
.crm-inline-select {
  display: inline-block;
  max-width: 100%;
  min-width: 0;
  border: 1px solid transparent;
  border-radius: 8px;
  padding: 7px 23px 7px 9px;
  font: inherit;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.crm-inline-select:hover,
.crm-inline-select:focus-visible {
  border-color: #cbd5e1;
  box-shadow: 0 0 0 2px #e2e8f0;
}
.crm-inline-select:disabled,
.crm-detail-inline-select:disabled,
.crm-detail-inline-date:disabled {
  opacity: 0.65;
  cursor: wait;
}
.crm-inline-type.undecided {
  background: #f1f5f9;
  color: #475569;
}
.crm-inline-type.retail {
  background: #eff6ff;
  color: #1d4ed8;
}
.crm-inline-type.b2b {
  background: #f5f3ff;
  color: #6d28d9;
}
.crm-inline-status.new {
  background: #eff6ff;
  color: #1d4ed8;
}
.crm-inline-status.contacting,
.crm-inline-status.qualified,
.crm-inline-status.interested {
  background: #ecfeff;
  color: #0e7490;
}
.crm-inline-status.follow_up {
  background: #fffbeb;
  color: #b45309;
}
.crm-inline-status.converted {
  background: #ecfdf5;
  color: #047857;
}
.crm-inline-status.lost {
  background: #fef2f2;
  color: #b91c1c;
}
.crm-detail-edit-card {
  min-width: 0;
}
.crm-detail-inline-select,
.crm-detail-inline-date {
  display: block;
  width: 100%;
  min-width: 0;
  margin-top: 3px;
  border: 1px solid #dbe4ee;
  border-radius: 8px;
  padding: 7px 8px;
  background: #ffffffd9;
  color: #334155;
  font: inherit;
  font-size: 12px;
  outline: none;
  box-sizing: border-box;
}
.crm-detail-inline-select:focus,
.crm-detail-inline-date:focus {
  border-color: #10b981;
  box-shadow: 0 0 0 3px #10b9811a;
}
.crm-detail-date-current {
  display: block;
  margin-top: 5px;
  font-size: 10px;
  color: #64748b;
  line-height: 1.4;
}
@media (max-width: 560px) {
  .crm-inline-select {
    font-size: 10px;
    padding-left: 6px;
    padding-right: 18px;
  }
  .crm-detail-inline-date {
    font-size: 10px;
  }
}


/* Premium lead details experience; intentionally scoped to this modal only. */
.crm-owner-default-note {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid #d8eee5;
  border-radius: 14px;
  background: linear-gradient(115deg, #f0fdf7 0%, #f8fffc 100%);
}
.crm-owner-default-icon {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  flex: 0 0 36px;
  border-radius: 11px;
  color: #087f5b;
  background: #d9f7e9;
}
.crm-owner-default-note strong,
.crm-owner-default-note small { display: block; }
.crm-owner-default-note strong { color: #145744; font-size: 12px; font-weight: 750; }
.crm-owner-default-note small { margin-top: 3px; color: #66877b; font-size: 11px; line-height: 1.5; }
.detail-modal-premium {
  width: min(1080px, 100%);
  max-height: min(88vh, 920px);
  border: 1px solid #e3eaf2;
  border-radius: 24px;
  background: #fff;
  box-shadow: 0 32px 100px rgba(15, 23, 42, .28), 0 4px 18px rgba(15, 23, 42, .08);
  scrollbar-color: #cbd5e1 transparent;
}
.detail-modal-premium > header {
  position: sticky;
  top: 0;
  z-index: 5;
  align-items: center;
  padding: 20px 28px;
  background: rgba(255, 255, 255, .94);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid #e9eef5;
}
.detail-modal-premium > header p { color: #64748b; font-size: 12px; }
.detail-modal-premium .crm-detail-hero {
  padding: 24px 28px 18px;
  background: linear-gradient(180deg, #f8fbff 0%, #fff 100%);
  border-bottom: 1px solid #edf2f7;
}
.detail-modal-premium .crm-detail-avatar {
  width: 60px; height: 60px; flex-basis: 60px; border-radius: 18px;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.85), 0 5px 14px rgba(16,185,129,.10);
}
.detail-modal-premium .crm-detail-identity > p { color: #475569; font-size: 12px; line-height: 1.7; }
.detail-modal-premium .crm-detail-identity > small { color: #8290a4; font-size: 11px; }
.detail-modal-premium .crm-detail-summary {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  padding: 20px 28px;
}
.detail-modal-premium .crm-detail-summary > div {
  min-width: 0;
  border: 1px solid #e7edf4 !important;
  border-radius: 16px;
  padding: 15px 16px;
  background: #fff !important;
  box-shadow: 0 2px 5px rgba(15,23,42,.025);
  transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}
.detail-modal-premium .crm-detail-summary > div:hover {
  border-color: #cbd9e8 !important;
  box-shadow: 0 8px 22px rgba(15,23,42,.055);
  transform: translateY(-1px);
}
.detail-modal-premium .crm-detail-summary > div small,
.detail-modal-premium .crm-detail-summary > div small :deep(svg) { color: #64748b !important; }
.detail-modal-premium .crm-detail-summary > div strong { color: #1e293b; font-size: 13px; line-height: 1.5; overflow-wrap: anywhere; }
.detail-modal-premium .crm-assignment-panel {
  background: linear-gradient(145deg, #f3fbf8, #fbfefd) !important;
  border-color: #ccebe0 !important;
}
.detail-modal-premium .crm-assignment-panel select,
.detail-modal-premium .crm-assignment-panel input {
  width: 100%; min-width: 0; border: 1px solid #dbe5ee; border-radius: 11px;
  background-color: #fff; color: #243247; padding: 11px 12px; font-size: 12px;
  outline: none; transition: border-color .18s ease, box-shadow .18s ease;
}
.detail-modal-premium .crm-assignment-panel select:focus,
.detail-modal-premium .crm-assignment-panel input:focus {
  border-color: #34b38a; box-shadow: 0 0 0 3px rgba(16,185,129,.12);
}
.detail-modal-premium .crm-assignment-panel .crm-primary { min-height: 42px; border-radius: 11px; box-shadow: 0 5px 12px rgba(5,150,105,.14); }
.detail-modal-premium .crm-detail-context {
  margin: 0 28px 20px; padding: 18px 20px; border: 1px solid #e7edf4;
  border-radius: 16px; background: #fbfcfe;
}
.detail-modal-premium .crm-detail-actions {
  position: sticky; bottom: 0; z-index: 4; padding: 14px 28px;
  background: rgba(255,255,255,.94); backdrop-filter: blur(14px);
  border-top: 1px solid #e9eef5; border-bottom: 1px solid #e9eef5;
}
.detail-modal-premium .crm-detail-actions button { min-height: 42px; border-radius: 12px; padding-inline: 16px; }
.detail-modal-premium .crm-timeline { padding: 20px 28px 26px; }
.detail-modal-premium .crm-timeline h3 { display: flex; align-items: center; gap: 8px; color: #243247; font-weight: 750; }
.detail-modal-premium .crm-assignment-history { margin: 0 28px 18px; padding: 18px 20px; border: 1px solid #e7edf4; border-radius: 16px; background: #fbfcfe; }
.detail-modal-premium .crm-assignment-history .crm-assignment-entry { padding: 12px 0; border-top: 1px solid #edf2f7; }
.detail-modal-premium .crm-assignment-history .crm-assignment-entry:first-of-type { border-top: 0; }
@media (max-width: 760px) {
  .detail-modal-premium { max-height: 94vh; border-radius: 18px; }
  .detail-modal-premium > header, .detail-modal-premium .crm-detail-hero { padding-inline: 18px; }
  .detail-modal-premium .crm-detail-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 16px 18px; gap: 9px; }
  .detail-modal-premium .crm-detail-actions { padding-inline: 18px; }
  .detail-modal-premium .crm-detail-context { margin-inline: 18px; }
  .detail-modal-premium .crm-timeline { padding-inline: 18px; }
  .detail-modal-premium .crm-assignment-history { margin-inline: 18px; }
}
@media (max-width: 460px) {
  .detail-modal-premium .crm-detail-summary { grid-template-columns: minmax(0, 1fr); }
  .detail-modal-premium .crm-detail-actions { flex-wrap: wrap; }
  .detail-modal-premium .crm-detail-actions button { flex: 1 1 auto; }
}


/* Final polish: white information cards with color reserved for labels/icons. */
.detail-modal-premium .crm-detail-summary > div:not(.crm-assignment-panel) {
  background: #fff !important;
  border: 1px solid #e6ebf2 !important;
  box-shadow: 0 3px 10px rgba(15, 23, 42, .025);
}
.detail-modal-premium .crm-detail-summary > div small { color: #64748b; }
.detail-modal-premium .crm-detail-summary > div:nth-child(1) small,
.detail-modal-premium .crm-detail-summary > div:nth-child(4) small { color: #2563eb; }
.detail-modal-premium .crm-detail-summary > div:nth-child(2) small { color: #d97706; }
.detail-modal-premium .crm-detail-summary > div:nth-child(3) small { color: #059669; }
.detail-modal-premium .crm-detail-summary > div:nth-child(7) small { color: #db2777; }
.detail-modal-premium .crm-detail-summary > div:nth-child(8) small { color: #7c3aed; }
.crm-converted-banner, .crm-convert-action {
  margin: 18px 28px 0; padding: 16px 18px; border: 1px solid #dbece4; border-radius: 15px;
  display: flex; align-items: center; gap: 13px; background: #fff;
}
.crm-converted-icon { width: 40px; height: 40px; flex: 0 0 40px; display: grid; place-items: center; color: #059669; background: #ecfdf5; border-radius: 12px; }
.crm-converted-copy, .crm-convert-action > div { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 0; }
.crm-converted-copy strong, .crm-convert-action strong { color: #065f46; font-size: 14px; }
.crm-converted-copy span, .crm-convert-action span { color: #64748b; font-size: 12px; overflow-wrap: anywhere; }
.crm-customer-link { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #cfe7dc; background: #fff; color: #047857; border-radius: 10px; padding: 10px 12px; font-weight: 700; white-space: nowrap; }
.crm-convert-action { border-color: #dce6f3; justify-content: space-between; }
.crm-convert-action strong { color: #243247; }
.crm-convert-action .crm-primary { display: inline-flex; align-items: center; gap: 7px; white-space: nowrap; }
@media (max-width: 600px) { .crm-converted-banner, .crm-convert-action { margin-inline: 18px; align-items: flex-start; flex-wrap: wrap; } .crm-customer-link { margin-left: 53px; } }

</style>

<style scoped>
/* Lead detail visual refinement: neutral white surfaces with color reserved for labels/icons. */
.detail-modal-premium .crm-detail-summary > div,
.detail-modal-premium .crm-assignment-panel { background: #fff !important; border-color: #e5eaf1 !important; }
.detail-modal-premium .crm-detail-summary > div small { color: #0f766e !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(1) small { color: #2563eb !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(2) small { color: #c2410c !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(3) small { color: #0f766e !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(4) small { color: #7c3aed !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(5) small { color: #0891b2 !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(6) small { color: #b45309 !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(7) small { color: #db2777 !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(8) small { color: #2563eb !important; }
.crm-convert-action { margin: 18px 28px 0; padding: 18px 20px; border: 1px solid #dbe5ef; border-radius: 18px; display: flex; align-items: center; gap: 15px; background: linear-gradient(135deg,#fff 0%,#f8fbff 100%); box-shadow: 0 8px 24px rgba(15,23,42,.055); }
.crm-convert-mark { width: 48px; height: 48px; flex: 0 0 48px; display: grid; place-items: center; border-radius: 15px; color: #047857; background: #ecfdf5; border: 1px solid #c7f0dd; }
.crm-convert-copy { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 0; }
.crm-convert-copy .crm-convert-eyebrow { font-size: 10px; font-weight: 800; letter-spacing: .12em; color: #0f766e; }
.crm-convert-copy strong { color: #172b43; font-size: 15px; }
.crm-convert-copy > span:last-child { color: #64748b; font-size: 12px; line-height: 1.6; }
.crm-convert-cta { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0 15px; border: 1px solid #059669; border-radius: 12px; color: #fff; background: linear-gradient(135deg,#059669,#047857); box-shadow: 0 6px 14px rgba(5,150,105,.18); font-size: 12px; font-weight: 750; white-space: nowrap; transition: transform .18s ease, box-shadow .18s ease; }
.crm-convert-cta:hover { transform: translateY(-1px); box-shadow: 0 9px 20px rgba(5,150,105,.23); }
.crm-convert-cta:disabled { opacity: .65; cursor: wait; }
.crm-convert-confirm-backdrop { position: fixed; inset: 0; z-index: 1200; display: grid; place-items: center; padding: 20px; background: rgba(15,23,42,.54); backdrop-filter: blur(7px); }
.crm-convert-confirm { position: relative; width: min(100%, 510px); padding: 30px; border: 1px solid rgba(255,255,255,.75); border-radius: 24px; background: #fff; box-shadow: 0 28px 90px rgba(15,23,42,.3); animation: crm-confirm-in .18s ease-out; }
.crm-convert-confirm-close { position: absolute; top: 16px; right: 16px; width: 34px; height: 34px; display: grid; place-items: center; border: 1px solid #e2e8f0; border-radius: 10px; color: #64748b; background: #fff; }
.crm-confirm-icon { width: 54px; height: 54px; display: grid; place-items: center; margin-bottom: 17px; border-radius: 17px; color: #047857; background: #ecfdf5; border: 1px solid #c7f0dd; }
.crm-confirm-kicker { color: #059669; font-size: 10px; font-weight: 850; letter-spacing: .14em; }
.crm-convert-confirm h3 { margin: 8px 35px 10px 0; color: #14263e; font-size: 22px; line-height: 1.3; letter-spacing: -.025em; }
.crm-convert-confirm > p { margin: 0; color: #64748b; font-size: 13px; line-height: 1.75; }
.crm-confirm-summary { display: flex; flex-direction: column; gap: 10px; margin-top: 19px; padding: 15px; border: 1px solid #e8edf3; border-radius: 14px; background: #f8fafc; }
.crm-confirm-summary span { display: flex; align-items: center; gap: 9px; color: #334155; font-size: 12px; overflow-wrap: anywhere; }
.crm-confirm-summary svg { flex: 0 0 auto; color: #0f766e; }
.crm-confirm-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
.crm-confirm-cancel, .crm-confirm-primary { min-height: 43px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0 14px; border-radius: 11px; font-size: 12px; font-weight: 750; }
.crm-confirm-cancel { border: 1px solid #dbe3ec; color: #475569; background: #fff; }
.crm-confirm-primary { border: 1px solid #047857; color: #fff; background: #047857; box-shadow: 0 5px 14px rgba(4,120,87,.17); }
.crm-confirm-primary:disabled { opacity: .65; cursor: wait; }
@keyframes crm-confirm-in { from { opacity: 0; transform: translateY(7px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
@media (max-width: 680px) { .crm-convert-action { margin-inline: 18px; align-items: flex-start; flex-wrap: wrap; } .crm-convert-cta { width: 100%; } .crm-convert-confirm { padding: 24px 20px; } .crm-confirm-actions { flex-direction: column-reverse; } .crm-confirm-actions button { width: 100%; } }
</style>

<style scoped>
/* Final detail-modal refinement: white cards and colored labels/icons only. */
.detail-modal-premium .crm-detail-summary > div, .detail-modal-premium .crm-assignment-panel { background:#fff !important; border-color:#e5eaf1 !important; }
.detail-modal-premium .crm-detail-summary > div small { color:#0f766e !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(1) small { color:#2563eb !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(2) small { color:#c2410c !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(3) small { color:#0f766e !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(4) small { color:#7c3aed !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(5) small { color:#0891b2 !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(6) small { color:#b45309 !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(7) small { color:#db2777 !important; }
.detail-modal-premium .crm-detail-summary > div:nth-child(8) small { color:#2563eb !important; }
.crm-convert-action { margin:18px 28px 0; padding:18px 20px; border:1px solid #dbe5ef; border-radius:18px; display:flex; align-items:center; gap:15px; background:linear-gradient(135deg,#fff 0%,#f8fbff 100%); box-shadow:0 8px 24px rgba(15,23,42,.055); }
.crm-convert-mark { width:48px; height:48px; flex:0 0 48px; display:grid; place-items:center; border-radius:15px; color:#047857; background:#ecfdf5; border:1px solid #c7f0dd; }
.crm-convert-copy { display:flex; flex-direction:column; gap:5px; flex:1; min-width:0; }
.crm-convert-copy .crm-convert-eyebrow { font-size:10px; font-weight:800; letter-spacing:.12em; color:#0f766e; }
.crm-convert-copy strong { color:#172b43; font-size:15px; }
.crm-convert-copy > span:last-child { color:#64748b; font-size:12px; line-height:1.6; }
.crm-convert-cta { min-height:44px; display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:0 15px; border:1px solid #059669; border-radius:12px; color:#fff; background:linear-gradient(135deg,#059669,#047857); box-shadow:0 6px 14px rgba(5,150,105,.18); font-size:12px; font-weight:750; white-space:nowrap; transition:transform .18s ease,box-shadow .18s ease; }
.crm-convert-cta:hover { transform:translateY(-1px); box-shadow:0 9px 20px rgba(5,150,105,.23); }
.crm-convert-confirm-backdrop { position:fixed; inset:0; z-index:1200; display:grid; place-items:center; padding:20px; background:rgba(15,23,42,.54); backdrop-filter:blur(7px); }
.crm-convert-confirm { position:relative; width:min(100%,510px); padding:30px; border:1px solid rgba(255,255,255,.75); border-radius:24px; background:#fff; box-shadow:0 28px 90px rgba(15,23,42,.3); animation:crm-confirm-in .18s ease-out; }
.crm-convert-confirm-close { position:absolute; top:16px; right:16px; width:34px; height:34px; display:grid; place-items:center; border:1px solid #e2e8f0; border-radius:10px; color:#64748b; background:#fff; }
.crm-confirm-icon { width:54px; height:54px; display:grid; place-items:center; margin-bottom:17px; border-radius:17px; color:#047857; background:#ecfdf5; border:1px solid #c7f0dd; }
.crm-confirm-kicker { color:#059669; font-size:10px; font-weight:850; letter-spacing:.14em; }
.crm-convert-confirm h3 { margin:8px 35px 10px 0; color:#14263e; font-size:22px; line-height:1.3; letter-spacing:-.025em; }
.crm-convert-confirm > p { margin:0; color:#64748b; font-size:13px; line-height:1.75; }
.crm-confirm-summary { display:flex; flex-direction:column; gap:10px; margin-top:19px; padding:15px; border:1px solid #e8edf3; border-radius:14px; background:#f8fafc; }
.crm-confirm-summary span { display:flex; align-items:center; gap:9px; color:#334155; font-size:12px; overflow-wrap:anywhere; }
.crm-confirm-summary svg { flex:0 0 auto; color:#0f766e; }
.crm-confirm-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:22px; }
.crm-confirm-cancel,.crm-confirm-primary { min-height:43px; display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:0 14px; border-radius:11px; font-size:12px; font-weight:750; }
.crm-confirm-cancel { border:1px solid #dbe3ec; color:#475569; background:#fff; }
.crm-confirm-primary { border:1px solid #047857; color:#fff; background:#047857; box-shadow:0 5px 14px rgba(4,120,87,.17); }
.crm-confirm-primary:disabled { opacity:.65; cursor:wait; }
@keyframes crm-confirm-in { from { opacity:0; transform:translateY(7px) scale(.985); } to { opacity:1; transform:translateY(0) scale(1); } }
@media(max-width:680px) { .crm-convert-action { margin-inline:18px; align-items:flex-start; flex-wrap:wrap; } .crm-convert-cta { width:100%; } .crm-convert-confirm { padding:24px 20px; } .crm-confirm-actions { flex-direction:column-reverse; } .crm-confirm-actions button { width:100%; } }
</style>

<style scoped>
.crm-order-backdrop{position:fixed;inset:0;z-index:1300;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.58);backdrop-filter:blur(8px)}
.crm-order-modal{width:min(100%,780px);max-height:min(90vh,900px);overflow:auto;border:1px solid #e4eaf2;border-radius:22px;background:#fff;box-shadow:0 30px 100px rgba(15,23,42,.32);color:#172b43}
.crm-order-header{position:sticky;top:0;z-index:2;display:flex;justify-content:space-between;gap:20px;align-items:flex-start;padding:22px 26px 18px;border-bottom:1px solid #edf1f6;background:rgba(255,255,255,.96);backdrop-filter:blur(10px)}
.crm-order-kicker{font-size:10px;font-weight:800;letter-spacing:.12em;color:#059669}
.crm-order-header h3{margin:7px 0 4px;font-size:21px;letter-spacing:-.03em}
.crm-order-header p{margin:0;color:#64748b;font-size:12px}
.crm-order-body{padding:20px 26px}
.crm-order-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.5fr) 100px auto;gap:12px;align-items:end}
.crm-order-grid label,.crm-order-notes{display:flex;flex-direction:column;gap:7px;font-size:12px;font-weight:700;color:#475569}
.crm-order-grid select,.crm-order-grid input,.crm-order-notes textarea{width:100%;min-height:42px;padding:10px 11px;border:1px solid #dbe4ef;border-radius:10px;background:#fff;color:#172b43;font:inherit;font-weight:500;outline:none}
.crm-order-grid select:focus,.crm-order-grid input:focus,.crm-order-notes textarea:focus{border-color:#34b48a;box-shadow:0 0 0 3px rgba(16,185,129,.1)}
.crm-order-add{min-height:42px;white-space:nowrap}
.crm-order-empty{margin-top:16px;padding:20px;border:1px dashed #d7e1ed;border-radius:12px;background:#f8fafc;color:#64748b;text-align:center;font-size:12px}
.crm-order-lines{margin-top:16px;border:1px solid #e5ebf3;border-radius:13px;overflow:hidden}
.crm-order-line{display:grid;grid-template-columns:minmax(0,1fr) auto 30px;gap:14px;align-items:center;padding:12px 14px;border-bottom:1px solid #edf1f6}
.crm-order-line div{display:flex;flex-direction:column;gap:4px;min-width:0}
.crm-order-line strong{font-size:12px;overflow-wrap:anywhere}
.crm-order-line small{font-size:11px;color:#64748b}
.crm-order-line button{width:30px;height:30px;border:0;border-radius:8px;color:#dc2626;background:#fff1f2}
.crm-order-total{display:flex;justify-content:space-between;align-items:center;padding:14px;background:#f8fafc;font-size:12px}
.crm-order-total strong{font-size:17px;color:#047857}
.crm-order-notes{margin-top:16px}
.crm-order-notes textarea{resize:vertical}
.crm-order-footnote{display:flex;gap:8px;align-items:flex-start;margin:14px 0 0;color:#64748b;font-size:11px;line-height:1.6}
.crm-order-footnote :deep(svg){flex:0 0 auto;color:#059669}
.crm-order-error{padding:10px 12px;border-radius:9px;background:#fff1f2;color:#be123c;font-size:12px}
.crm-order-footer{position:sticky;bottom:0;display:flex;justify-content:flex-end;gap:10px;padding:14px 26px;border-top:1px solid #edf1f6;background:rgba(255,255,255,.96);backdrop-filter:blur(10px)}
.crm-owner-badge{display:inline-flex;align-items:center;gap:6px;width:max-content;padding:5px 9px;border:1px solid #b7ecd6;border-radius:999px;background:#ecfdf5;color:#047857;font-size:10px;font-weight:800}
@media(max-width:700px){.crm-order-grid{grid-template-columns:1fr 1fr}.crm-order-grid label:nth-child(2){grid-column:1/-1}.crm-order-add{grid-column:1/-1}.crm-order-header,.crm-order-body{padding-inline:17px}.crm-order-footer{padding-inline:17px;flex-wrap:wrap}.crm-order-footer button{flex:1}.crm-order-line{grid-template-columns:minmax(0,1fr) auto 28px;gap:8px}}
@media(max-width:440px){.crm-order-grid{grid-template-columns:1fr}.crm-order-grid label:nth-child(2),.crm-order-add{grid-column:auto}.crm-order-modal{border-radius:16px}.crm-order-header h3{font-size:18px}}
</style>

<style scoped>
.crm-converted-lock{display:flex;align-items:center;gap:4px;color:#059669;font-weight:700;margin-top:5px}.crm-owner-assigned-card .crm-assigned-label{display:block;margin-top:8px;color:#64748b;font-weight:500}.crm-owner-assigned-card strong{display:block}


/* Lead register alignment: compact, consistent rows without stacked visual clutter. */
.crm-lead-register-table {
  width: 100%;
  min-width: 1080px;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
}
.crm-lead-register-table th,
.crm-lead-register-table td { vertical-align: middle; }
.crm-lead-register-table th { white-space: nowrap; padding: 13px 16px; }
.crm-lead-register-table td { padding: 15px 16px; }
.crm-lead-register-table th:nth-child(1), .crm-lead-register-table td:nth-child(1) { width: 20%; }
.crm-lead-register-table th:nth-child(2), .crm-lead-register-table td:nth-child(2) { width: 10%; }
.crm-lead-register-table th:nth-child(3), .crm-lead-register-table td:nth-child(3) { width: 15%; }
.crm-lead-register-table th:nth-child(4), .crm-lead-register-table td:nth-child(4) { width: 13%; }
.crm-lead-register-table th:nth-child(5), .crm-lead-register-table td:nth-child(5) { width: 14%; }
.crm-lead-register-table th:nth-child(6), .crm-lead-register-table td:nth-child(6) { width: 20%; }
.crm-lead-register-table th:nth-child(7), .crm-lead-register-table td:nth-child(7) { width: 8%; }
.crm-lead-register-table td > small { display: block; margin-top: 5px; line-height: 1.45; }
.crm-lead-register-table .crm-inline-select { max-width: 100%; }
.crm-lead-register-table .created-by { display: flex; align-items: center; gap: 7px; white-space: normal; line-height: 1.4; }
.crm-lead-register-table .crm-converted-lock { display: flex; align-items: center; gap: 4px; color: #07845d; font-weight: 700; }
.crm-lead-register-table .crm-actions { display: flex; justify-content: flex-start; gap: 6px; }
.crm-detail-summary .is-locked {
  position: relative;
  border-color: #cfe8df !important;
  background: linear-gradient(145deg, #f2fbf7, #fbfefd) !important;
}
.crm-detail-summary .is-locked .crm-detail-inline-select,
.crm-detail-summary .is-locked .crm-detail-inline-date,
.crm-detail-summary .is-locked select,
.crm-detail-summary .is-locked input {
  color: #64748b !important;
  background: #edf3f1 !important;
  border-color: #dce9e4 !important;
  cursor: not-allowed;
  opacity: .9;
}
.crm-lock-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-left: auto;
  padding: 3px 7px;
  border: 1px solid #c7eadc;
  border-radius: 999px;
  background: #e6f8ef;
  color: #087f5b;
  font-size: 9px;
  font-weight: 750;
  letter-spacing: .02em;
  white-space: nowrap;
}
.crm-detail-summary .is-locked > small:first-child { display: flex; align-items: center; gap: 6px; }
.crm-detail-summary .crm-assignment-panel.is-locked { background: linear-gradient(145deg, #f2fbf7, #fbfefd) !important; }
.crm-detail-summary .crm-assignment-panel.is-locked button { display: none; }
@media (max-width: 760px) {
  .crm-lead-register-table { min-width: 1040px; }
  .crm-lead-register-table th, .crm-lead-register-table td { padding: 12px 10px; }
}

</style>
