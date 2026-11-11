<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';
import AddressSuggest from '@/components/customers/AddressSuggest.vue';
import bd from 'bangladesh-geojson';
interface Props {
    userName?: string;
    userEmail?: string;
}
const props = withDefaults(defineProps<Props>(), {
    userName: '',
    userEmail: '',
});
type Customer = {
    id: number;
    customer_code: string;
    name: string;
    phone: string | null;
    email: string | null;
    company_name: string | null;
    address_line: string | null;
    area: string | null;
    district: string | null;
    division: string | null;
    postal_code: string | null;
    notes: string | null;
    status: 'active' | 'inactive';
    orders_count?: number;
    created_at: string;
};
type Api<T> = {
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
    stats?: { total: number; active: number; with_phone: number };
    current_page?: number;
    last_page?: number;
    total?: number;
};
type Form = Omit<
    Customer,
    'id' | 'customer_code' | 'orders_count' | 'created_at'
>;
const customers = ref<Customer[]>([]),
    loading = ref(false),
    saving = ref(false),
    search = ref(''),
    status = ref('all'),
    page = ref(1),
    lastPage = ref(1),
    total = ref(0),
    stats = ref({ total: 0, active: 0, with_phone: 0 });
const showForm = ref(false),
    editing = ref<Customer | null>(null),
    detail = ref<Record<string, any> | null>(null),
    deleteTarget = ref<Customer | null>(null),
    notice = ref(''),
    noticeType = ref<'success' | 'error'>('success'),
    errors = ref<Record<string, string>>({});
function emptyForm(): Form {
    return {
        name: '',
        phone: '',
        email: '',
        company_name: '',
        address_line: '',
        area: '',
        district: '',
        division: '',
        postal_code: '',
        notes: '',
        status: 'active',
    };
}
const form = reactive<Form>(emptyForm());

type BdDivision = { id: string | number; name: string; bn_name?: string };
type BdDistrict = {
    id: string | number;
    division_id: string | number;
    name: string;
    bn_name?: string;
};
type BdUpazila = {
    id: string | number;
    district_id: string | number;
    name: string;
    bn_name?: string;
};
type BdPostcode = {
    division_id: string | number;
    district_id: string | number;
    upazila: string;
    postOffice: string;
    postCode: string | number;
};
const bdDivisions = bd.getDivisions() as BdDivision[];
const bdDistricts = bd.getDistricts() as BdDistrict[];
const bdUpazilas = bd.getUpazilas() as BdUpazila[];
const bdPostcodes = bd.getPostcodes() as BdPostcode[];

// Built-in Bangladesh dataset plus shared, database-backed custom suggestions.
type LocationSuggestionKey =
    | 'divisions'
    | 'districts'
    | 'upazilas'
    | 'postcodes';
const customLocationSuggestions = reactive<
    Record<LocationSuggestionKey, string[]>
>({ divisions: [], districts: [], upazilas: [], postcodes: [] });
const locationTypeMap: Record<LocationSuggestionKey, string> = {
    divisions: 'division',
    districts: 'district',
    upazilas: 'upazila',
    postcodes: 'postcode',
};
async function loadCustomLocationSuggestions() {
    try {
        const response = await fetch('/api/v1/backend/customer-location-suggestions', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const payload = await response.json();
        for (const key of Object.keys(
            customLocationSuggestions,
        ) as LocationSuggestionKey[]) {
            const values = payload.data?.[key];
            if (Array.isArray(values))
                customLocationSuggestions[key] = values.filter(
                    (v: unknown): v is string => typeof v === 'string',
                );
        }
    } catch {
        /* Keep built-in dataset available when API is temporarily unavailable. */
    }
}
async function rememberLocationSuggestion(
    key: LocationSuggestionKey,
    value: string,
) {
    const cleanValue = value.trim();
    if (!cleanValue) return;
    const current = customLocationSuggestions[key];
    if (current.some((item) => item.toLowerCase() === cleanValue.toLowerCase()))
        return;
    const response = await fetch('/api/v1/backend/customer-location-suggestions', {
        method: 'POST',
        headers: { ...headers(), Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ type: locationTypeMap[key], name: cleanValue }),
    });
    if (!response.ok)
        throw new Error(
            await errorText(
                response,
                'Could not save this location suggestion.',
            ),
        );
    await loadCustomLocationSuggestions();
}
const divisionSuggestions = computed(() =>
    [
        ...new Set([
            ...bdDivisions.map((item) => item.name),
            ...customLocationSuggestions.divisions,
        ]),
    ].sort((a, b) => a.localeCompare(b)),
);
const districtSuggestions = computed(() =>
    [
        ...new Set([
            ...availableDistricts.value.map((item) => item.name),
            ...customLocationSuggestions.districts,
        ]),
    ].sort((a, b) => a.localeCompare(b)),
);
const upazilaSuggestions = computed(() =>
    [
        ...new Set([
            ...availableUpazilas.value.map((item) => item.name),
            ...customLocationSuggestions.upazilas,
        ]),
    ].sort((a, b) => a.localeCompare(b)),
);
const postcodeSuggestions = computed(() =>
    [
        ...new Set([
            ...availablePostcodes.value.map((item) => String(item.postCode)),
            ...customLocationSuggestions.postcodes,
        ]),
    ].sort((a, b) => a.localeCompare(b)),
);

const selectedDivision = computed(() =>
    bdDivisions.find(
        (item) =>
            item.name.toLowerCase() === (form.division ?? '').toLowerCase(),
    ),
);
const availableDistricts = computed(() =>
    selectedDivision.value
        ? bdDistricts.filter(
              (item) =>
                  String(item.division_id) ===
                  String(selectedDivision.value?.id),
          )
        : [],
);
const selectedDistrict = computed(() =>
    availableDistricts.value.find(
        (item) =>
            item.name.toLowerCase() === (form.district ?? '').toLowerCase(),
    ),
);
const availableUpazilas = computed(() =>
    selectedDistrict.value
        ? bdUpazilas.filter(
              (item) =>
                  String(item.district_id) ===
                  String(selectedDistrict.value?.id),
          )
        : [],
);
const availablePostcodes = computed(() => {
    if (!selectedDistrict.value) return [];
    const upazilaName = (form.area ?? '').trim().toLowerCase();
    return bdPostcodes.filter(
        (item) =>
            String(item.district_id) === String(selectedDistrict.value?.id) &&
            (!upazilaName || item.upazila.toLowerCase() === upazilaName),
    );
});
async function changeDivision(value: string) {
    form.division = value;
    await rememberLocationSuggestion('divisions', value).catch((e) =>
        notify(
            e instanceof Error ? e.message : 'Could not save location.',
            'error',
        ),
    );
    form.district = '';
    form.area = '';
    form.postal_code = '';
}
async function changeDistrict(value: string) {
    form.district = value;
    await rememberLocationSuggestion('districts', value).catch((e) =>
        notify(
            e instanceof Error ? e.message : 'Could not save location.',
            'error',
        ),
    );
    form.area = '';
    form.postal_code = '';
}
async function changeUpazila(value: string) {
    form.area = value;
    await rememberLocationSuggestion('upazilas', value).catch((e) =>
        notify(
            e instanceof Error ? e.message : 'Could not save location.',
            'error',
        ),
    );
    form.postal_code = '';
}

function headers() {
    const csrf =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
    };
}
async function errorText(r: Response, fallback: string) {
    const p = (await r.json().catch(() => ({}))) as Api<unknown>;
    errors.value = Object.fromEntries(
        Object.entries(p.errors ?? {}).map(([k, v]) => [
            k,
            v[0] ?? 'Invalid value',
        ]),
    );
    return p.message ?? Object.values(p.errors ?? {}).flat()[0] ?? fallback;
}
async function load() {
    loading.value = true;
    try {
        const q = new URLSearchParams({
            search: search.value,
            status: status.value,
            page: String(page.value),
            per_page: '15',
        });
        const r = await fetch(`/api/v1/backend/customers?${q}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!r.ok)
            throw new Error(await errorText(r, 'Could not load customers.'));
        const p = (await r.json()) as Api<Customer[]>;
        customers.value = p.data ?? [];
        lastPage.value = p.last_page ?? 1;
        total.value = p.total ?? customers.value.length;
        stats.value = p.stats ?? stats.value;
    } catch (e) {
        notify(
            e instanceof Error ? e.message : 'Could not load customers.',
            'error',
        );
    } finally {
        loading.value = false;
    }
}
function notify(s: string, t: 'success' | 'error' = 'success') {
    notice.value = s;
    noticeType.value = t;
}
function openCreate() {
    editing.value = null;
    Object.assign(form, emptyForm());
    errors.value = {};
    showForm.value = true;
}
function openEdit(c: Customer) {
    editing.value = c;
    Object.assign(form, {
        name: c.name,
        phone: c.phone ?? '',
        email: c.email ?? '',
        company_name: c.company_name ?? '',
        address_line: c.address_line ?? '',
        area: c.area ?? '',
        district: c.district ?? '',
        division: c.division ?? '',
        postal_code: c.postal_code ?? '',
        notes: c.notes ?? '',
        status: c.status,
    });
    errors.value = {};
    showForm.value = true;
}
async function save() {
    saving.value = true;
    errors.value = {};
    const payload = Object.fromEntries(
        Object.entries(form).map(([k, v]) => [
            k,
            typeof v === 'string' ? v.trim() || null : v,
        ]),
    );
    try {
        const r = await fetch(
            editing.value
                ? `/api/v1/backend/customers/${editing.value.id}`
                : '/api/v1/backend/customers',
            {
                method: editing.value ? 'PUT' : 'POST',
                headers: headers(),
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            },
        );
        if (!r.ok)
            throw new Error(await errorText(r, 'Could not save customer.'));
        const p = (await r.json()) as Api<Customer>;
        showForm.value = false;
        notify(p.message ?? 'Customer saved.');
        await load();
    } catch (e) {
        notify(
            e instanceof Error ? e.message : 'Could not save customer.',
            'error',
        );
    } finally {
        saving.value = false;
    }
}
async function showCustomer(c: Customer) {
    try {
        const r = await fetch(`/api/v1/backend/customers/${c.id}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!r.ok)
            throw new Error(
                await errorText(r, 'Could not load customer profile.'),
            );
        detail.value =
            ((await r.json()) as Api<Record<string, any>>).data ?? null;
    } catch (e) {
        notify(
            e instanceof Error ? e.message : 'Could not load customer profile.',
            'error',
        );
    }
}
async function removeCustomer() {
    if (!deleteTarget.value) return;
    try {
        const r = await fetch(`/api/v1/backend/customers/${deleteTarget.value.id}`, {
            method: 'DELETE',
            headers: headers(),
            credentials: 'same-origin',
        });
        if (!r.ok)
            throw new Error(await errorText(r, 'Could not delete customer.'));
        deleteTarget.value = null;
        notify('Customer deleted.');
        await load();
    } catch (e) {
        notify(
            e instanceof Error ? e.message : 'Could not delete customer.',
            'error',
        );
    }
}
function money(v: string | number) {
    return new Intl.NumberFormat('en-BD', {
        style: 'currency',
        currency: 'BDT',
        maximumFractionDigits: 2,
    }).format(Number(v) || 0);
}
function date(v: string) {
    return v
        ? new Date(v).toLocaleDateString('en-BD', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          })
        : '—';
}
const initials = (c: Customer) =>
    c.name
        .split(/\s+/)
        .slice(0, 2)
        .map((x) => x[0] ?? '')
        .join('')
        .toUpperCase();
onMounted(() => {
    loadCustomLocationSuggestions();
    load();
    const customerId = new URLSearchParams(window.location.search).get('customer_id');
    if (customerId && /^\d+$/.test(customerId)) {
        void showCustomer({ id: Number(customerId) } as Customer);
    }
});
</script>
<template>
    <Head title="Customers" /><ErpLayout
        :user-name="props.userName"
        :user-email="props.userEmail"
        ><main class="customers-page">
            <header class="page-head">
                <div>
                    <div class="crumb">
                        Sales <Icon name="chevron-right" :size="12" />
                        <b>Customers</b>
                    </div>
                    <div class="title-line">
                        <div class="hero-icon">
                            <Icon name="users" :size="23" />
                        </div>
                        <div>
                            <h1>Customers Management</h1>
                            <p>
                                Manage customer profiles, contact details and
                                order relationships.
                            </p>
                        </div>
                    </div>
                </div>
                <button class="btn primary" @click="openCreate">
                    <Icon name="user-plus" :size="15" /> Add customer
                </button>
            </header>
            <div v-if="notice" class="notice" :class="noticeType" role="status">
                <Icon
                    :name="
                        noticeType === 'success'
                            ? 'check-circle'
                            : 'alert-circle'
                    "
                    :size="16"
                />{{ notice
                }}<button @click="notice = ''" aria-label="Dismiss">
                    <Icon name="x" :size="14" />
                </button>
            </div>
            <section class="stats">
                <article>
                    <div class="metric-top">
                        <span>Total customers</span
                        ><span class="metric-icon blue"
                            ><Icon name="users" :size="19"
                        /></span>
                    </div>
                    <strong>{{ stats.total }}</strong
                    ><small>All customer profiles</small>
                </article>
                <article>
                    <div class="metric-top">
                        <span>Active customers</span
                        ><span class="metric-icon teal"
                            ><Icon name="check-circle" :size="19"
                        /></span>
                    </div>
                    <strong>{{ stats.active }}</strong
                    ><small>Available for new orders</small>
                </article>
                <article>
                    <div class="metric-top">
                        <span>With phone number</span
                        ><span class="metric-icon green"
                            ><Icon name="phone" :size="19"
                        /></span>
                    </div>
                    <strong>{{ stats.with_phone }}</strong
                    ><small>Contact-ready profiles</small>
                </article>
            </section>
            <section class="panel">
                <header class="panel-head">
                    <div>
                        <h2>Customer register</h2>
                        <p>
                            Search by customer name, code, phone number or
                            email.
                        </p>
                    </div>
                    <span class="record-count"
                        >{{ total }} matching records</span
                    >
                </header>
                <div class="toolbar">
                    <label class="search"
                        ><Icon name="search" :size="16" /><input
                            v-model="search"
                            placeholder="Search name, code, phone, email…"
                            @keyup.enter="
                                page = 1;
                                load();
                            " /><button
                            v-if="search"
                            @click="
                                search = '';
                                page = 1;
                                load();
                            "
                            aria-label="Clear search"
                        >
                            <Icon name="x" :size="14" /></button
                    ></label>
                    <div class="filters">
                        <select
                            v-model="status"
                            aria-label="Customer status"
                            @change="
                                page = 1;
                                load();
                            "
                        >
                            <option value="all">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option></select
                        ><button class="btn" @click="load">
                            <Icon name="refresh-cw" :size="14" /> Refresh
                        </button>
                    </div>
                </div>
                <div v-if="loading" class="empty">
                    <Icon name="loader-circle" :size="20" /> Loading customers…
                </div>
                <div v-else-if="!customers.length" class="empty">
                    <Icon name="users" :size="28" /><b>No customers found</b
                    ><span>Try another search or add your first customer.</span
                    ><button class="btn primary" @click="openCreate">
                        <Icon name="plus" :size="14" /> Add customer
                    </button>
                </div>
                <div v-else class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Location</th>
                                <th>Orders</th>
                                <th>Status</th>
                                <th>Added</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="c in customers"
                                :key="c.id"
                                class="customer-row"
                                tabindex="0"
                                @click="showCustomer(c)"
                                @keydown.enter.self.prevent="showCustomer(c)"
                                @keydown.space.self.prevent="showCustomer(c)"
                            >
                                <td>
                                    <button
                                        class="customer-cell"
                                        @click.stop="showCustomer(c)"
                                    >
                                        <span class="avatar">{{
                                            initials(c)
                                        }}</span
                                        ><span
                                            ><b>{{ c.name }}</b
                                            ><small
                                                >{{ c.customer_code
                                                }}<template
                                                    v-if="c.company_name"
                                                >
                                                    ·
                                                    {{
                                                        c.company_name
                                                    }}</template
                                                ></small
                                            ></span
                                        >
                                    </button>
                                </td>
                                <td>
                                    <b class="contact">{{
                                        c.phone || 'No phone'
                                    }}</b
                                    ><small class="sub">{{
                                        c.email || 'No email'
                                    }}</small>
                                </td>
                                <td>
                                    <span>{{
                                        [c.area, c.district, c.division]
                                            .filter(Boolean)
                                            .join(', ') || '—'
                                    }}</span>
                                </td>
                                <td>
                                    <span class="count">{{
                                        c.orders_count ?? 0
                                    }}</span>
                                </td>
                                <td>
                                    <span class="status" :class="c.status">{{
                                        c.status
                                    }}</span>
                                </td>
                                <td>{{ date(c.created_at) }}</td>
                                <td>
                                    <div class="row-actions">
                                        <button
                                            class="icon-btn"
                                            title="View profile"
                                            @click.stop="showCustomer(c)"
                                        >
                                            <Icon
                                                name="eye"
                                                :size="15"
                                            /></button
                                        ><button
                                            class="icon-btn"
                                            title="Edit customer"
                                            @click.stop="openEdit(c)"
                                        >
                                            <Icon
                                                name="pencil"
                                                :size="15"
                                            /></button
                                        ><button
                                            class="icon-btn danger"
                                            title="Delete customer"
                                            @click.stop="deleteTarget = c"
                                        >
                                            <Icon name="trash-2" :size="15" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer v-if="total" class="pagination">
                    <span
                        >Showing {{ customers.length }} of
                        {{ total }} customers</span
                    >
                    <div>
                        <button
                            class="btn"
                            :disabled="page <= 1 || loading"
                            @click="
                                page--;
                                load();
                            "
                        >
                            <Icon name="chevron-left" :size="14" />
                            Previous</button
                        ><span>Page {{ page }} / {{ lastPage }}</span
                        ><button
                            class="btn"
                            :disabled="page >= lastPage || loading"
                            @click="
                                page++;
                                load();
                            "
                        >
                            Next <Icon name="chevron-right" :size="14" />
                        </button>
                    </div>
                </footer>
            </section>
            <Teleport to="body">
                <div
                    v-if="showForm"
                    class="backdrop"
                    @click.self="showForm = false"
                >
                    <section
                        class="modal customer-form-modal"
                        :class="{ 'is-editing': !!editing }"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="customer-form-title"
                    >
                        <header class="modal-head">
                            <div class="modal-title-wrap">
                                <span class="modal-title-icon"
                                    ><Icon
                                        :name="
                                            editing ? 'user-cog' : 'user-plus'
                                        "
                                        :size="21"
                                /></span>
                                <div>
                                    <div class="eyebrow">
                                        {{
                                            editing
                                                ? 'CUSTOMER PROFILE'
                                                : 'NEW CUSTOMER'
                                        }}
                                    </div>
                                    <h2 id="customer-form-title">
                                        {{
                                            editing
                                                ? 'Edit customer'
                                                : 'Add customer'
                                        }}
                                    </h2>
                                    <p>
                                        Keep customer, contact and delivery
                                        information accurate.
                                    </p>
                                </div>
                            </div>
                            <button
                                class="icon-btn"
                                @click="showForm = false"
                                aria-label="Close"
                            >
                                <Icon name="x" :size="17" />
                            </button>
                        </header>
                        <form @submit.prevent="save">
                            <div class="modal-body">
                                <section class="form-section">
                                    <div class="form-section-title">
                                        <span class="section-icon"
                                            ><Icon
                                                name="clipboard-text"
                                                :size="16"
                                        /></span>
                                        <div>
                                            <h3>Basic information</h3>
                                            <p>
                                                Identify the customer and
                                                preferred contact details.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="form-grid">
                                        <label class="field"
                                            ><span>Customer name <i>*</i></span
                                            ><input
                                                v-model="form.name"
                                                maxlength="160"
                                                required
                                                placeholder="Full name"
                                                autocomplete="name"
                                            /><small
                                                v-if="errors.name"
                                                class="field-error"
                                                >{{ errors.name }}</small
                                            ></label
                                        >
                                        <label class="field"
                                            ><span>Phone number</span
                                            ><input
                                                v-model="form.phone"
                                                maxlength="40"
                                                inputmode="tel"
                                                autocomplete="tel"
                                                placeholder="01XXXXXXXXX or +880…"
                                            /><small
                                                >Use a number customers can be
                                                reached on.</small
                                            ><small
                                                v-if="errors.phone"
                                                class="field-error"
                                                >{{ errors.phone }}</small
                                            ></label
                                        >
                                        <label class="field"
                                            ><span>Email address</span
                                            ><input
                                                v-model="form.email"
                                                type="email"
                                                maxlength="190"
                                                autocomplete="email"
                                                placeholder="name@example.com"
                                            /><small
                                                v-if="errors.email"
                                                class="field-error"
                                                >{{ errors.email }}</small
                                            ></label
                                        >
                                        <label class="field"
                                            ><span>Company / business</span
                                            ><input
                                                v-model="form.company_name"
                                                maxlength="160"
                                                autocomplete="organization"
                                                placeholder="Optional"
                                            /><small
                                                v-if="errors.company_name"
                                                class="field-error"
                                                >{{
                                                    errors.company_name
                                                }}</small
                                            ></label
                                        >
                                    </div>
                                </section>
                                <section class="form-section">
                                    <div class="form-section-title">
                                        <span class="section-icon"
                                            ><Icon name="map-pin" :size="16"
                                        /></span>
                                        <div>
                                            <h3>Address & delivery</h3>
                                            <p>
                                                Location details help your team
                                                coordinate deliveries.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="form-grid">
                                        <label class="field wide"
                                            ><span>Street address</span
                                            ><input
                                                v-model="form.address_line"
                                                maxlength="500"
                                                autocomplete="street-address"
                                                placeholder="House, road, building or village"
                                            /><small
                                                v-if="errors.address_line"
                                                class="field-error"
                                                >{{
                                                    errors.address_line
                                                }}</small
                                            ></label
                                        >
                                        <label class="field">
                                            <span>Division</span>
                                            <AddressSuggest
                                                :model-value="
                                                    form.division ?? ''
                                                "
                                                :options="divisionSuggestions"
                                                placeholder="Search or select division"
                                                @update:model-value="
                                                    changeDivision($event)
                                                "
                                                @add="changeDivision($event)"
                                            />
                                        </label>
                                        <label class="field">
                                            <span>District</span>
                                            <AddressSuggest
                                                :model-value="
                                                    form.district ?? ''
                                                "
                                                :options="districtSuggestions"
                                                placeholder="Search or select district"
                                                @update:model-value="
                                                    changeDistrict($event)
                                                "
                                                @add="changeDistrict($event)"
                                            />
                                            <small
                                                v-if="errors.district"
                                                class="field-error"
                                                >{{ errors.district }}</small
                                            >
                                        </label>
                                        <label class="field">
                                            <span>Upazila / Thana</span>
                                            <AddressSuggest
                                                :model-value="form.area ?? ''"
                                                :options="upazilaSuggestions"
                                                placeholder="Search or select upazila / thana"
                                                @update:model-value="
                                                    changeUpazila($event)
                                                "
                                                @add="changeUpazila($event)"
                                            />
                                        </label>
                                        <label class="field">
                                            <span>Postcode</span>
                                            <AddressSuggest
                                                :model-value="
                                                    form.postal_code ?? ''
                                                "
                                                :options="postcodeSuggestions"
                                                placeholder="Search or type postcode"
                                                @update:model-value="
                                                    form.postal_code = $event
                                                "
                                                @add="
                                                    async (value) => {
                                                        form.postal_code =
                                                            value;
                                                        await rememberLocationSuggestion(
                                                            'postcodes',
                                                            value,
                                                        ).catch((e) =>
                                                            notify(
                                                                e instanceof
                                                                    Error
                                                                    ? e.message
                                                                    : 'Could not save postcode.',
                                                                'error',
                                                            ),
                                                        );
                                                    }
                                                "
                                            />
                                            <small
                                                v-if="errors.postal_code"
                                                class="field-error"
                                                >{{ errors.postal_code }}</small
                                            >
                                        </label>
                                    </div>
                                </section>
                                <section class="form-section">
                                    <div class="form-section-title">
                                        <span class="section-icon"
                                            ><Icon name="settings-2" :size="16"
                                        /></span>
                                        <div>
                                            <h3>Account settings & notes</h3>
                                            <p>
                                                Set account availability and
                                                keep useful internal context.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="form-grid">
                                        <label class="field"
                                            ><span>Customer status</span
                                            ><select v-model="form.status">
                                                <option value="active">
                                                    Active
                                                </option>
                                                <option value="inactive">
                                                    Inactive
                                                </option>
                                            </select></label
                                        >
                                        <label class="field wide"
                                            ><span>Internal notes</span
                                            ><textarea
                                                v-model="form.notes"
                                                rows="3"
                                                maxlength="5000"
                                                placeholder="Optional notes for your team"
                                            /><small
                                                >Visible to your team; use this
                                                for relevant service or delivery
                                                notes.</small
                                            ><small
                                                v-if="errors.notes"
                                                class="field-error"
                                                >{{ errors.notes }}</small
                                            ></label
                                        >
                                    </div>
                                </section>
                            </div>
                            <footer class="modal-foot">
                                <button
                                    class="btn"
                                    type="button"
                                    @click="showForm = false"
                                >
                                    Cancel</button
                                ><button
                                    class="btn primary"
                                    type="submit"
                                    :disabled="saving"
                                >
                                    <Icon name="check" :size="15" />
                                    {{
                                        saving
                                            ? 'Saving…'
                                            : editing
                                              ? 'Save changes'
                                              : 'Create customer'
                                    }}
                                </button>
                            </footer>
                        </form>
                    </section>
                </div>
                <div v-if="detail" class="backdrop" @click.self="detail = null">
                    <section
                        class="modal profile profile-modal"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="customer-profile-title"
                    >
                        <header class="modal-head profile-head">
                            <div class="modal-title-wrap">
                                <span class="modal-title-icon"
                                    ><Icon name="user" :size="20"
                                /></span>
                                <div class="profile-heading-copy">
                                    <div class="eyebrow">CUSTOMER DETAILS</div>
                                    <h2 id="customer-profile-title">
                                        Customer profile
                                    </h2>
                                    <p>
                                        {{
                                            detail.customer_code ||
                                            'Customer record'
                                        }}
                                    </p>
                                </div>
                            </div>
                            <button
                                class="icon-btn"
                                @click="detail = null"
                                aria-label="Close customer profile"
                            >
                                <Icon name="x" :size="17" />
                            </button>
                        </header>

                        <div class="modal-body profile-body">
                            <section class="profile-hero">
                                <span class="avatar large">{{
                                    String(detail.name ?? 'C')
                                        .split(/\\s+/)
                                        .slice(0, 2)
                                        .map((x: string) => x[0])
                                        .join('')
                                        .toUpperCase()
                                }}</span>
                                <div class="profile-identity">
                                    <h3>
                                        {{ detail.name || 'Unnamed customer' }}
                                    </h3>
                                    <div class="profile-identity-meta">
                                        <span
                                            class="status"
                                            :class="detail.status"
                                            >{{
                                                detail.status || 'unknown'
                                            }}</span
                                        >
                                        <span class="profile-code">{{
                                            detail.customer_code || '—'
                                        }}</span>
                                    </div>
                                </div>
                                <div class="profile-value">
                                    <small
                                        ><Icon name="wallet" :size="13" />
                                        Lifetime order value</small
                                    >
                                    <strong>{{
                                        money(detail.lifetime_value ?? 0)
                                    }}</strong>
                                </div>
                            </section>

                            <section
                                class="profile-section"
                                aria-labelledby="profile-contact-title"
                            >
                                <h3
                                    id="profile-contact-title"
                                    class="profile-section-title"
                                >
                                    <Icon name="user-cog" :size="15" /> Contact
                                    information
                                </h3>
                                <div class="profile-grid">
                                    <div class="profile-field">
                                        <span class="profile-field-icon"
                                            ><Icon name="phone" :size="15"
                                        /></span>
                                        <span class="profile-field-copy"
                                            ><small>Phone number</small
                                            ><b>{{
                                                detail.phone || 'Not provided'
                                            }}</b></span
                                        >
                                    </div>
                                    <div class="profile-field">
                                        <span class="profile-field-icon"
                                            ><Icon name="mail" :size="15"
                                        /></span>
                                        <span class="profile-field-copy"
                                            ><small>Email address</small
                                            ><b>{{
                                                detail.email || 'Not provided'
                                            }}</b></span
                                        >
                                    </div>
                                    <div class="profile-field">
                                        <span class="profile-field-icon"
                                            ><Icon name="building" :size="15"
                                        /></span>
                                        <span class="profile-field-copy"
                                            ><small>Company / business</small
                                            ><b>{{
                                                detail.company_name ||
                                                'Not provided'
                                            }}</b></span
                                        >
                                    </div>
                                    <div class="profile-field">
                                        <span class="profile-field-icon"
                                            ><Icon
                                                name="calendar-days"
                                                :size="15"
                                        /></span>
                                        <span class="profile-field-copy"
                                            ><small>Customer since</small
                                            ><b>{{
                                                detail.created_at
                                                    ? date(detail.created_at)
                                                    : '—'
                                            }}</b></span
                                        >
                                    </div>
                                </div>
                            </section>

                            <section
                                class="profile-section"
                                aria-labelledby="profile-address-title"
                            >
                                <h3
                                    id="profile-address-title"
                                    class="profile-section-title"
                                >
                                    <Icon name="map-pin" :size="15" /> Address &
                                    delivery
                                </h3>
                                <div class="profile-address">
                                    <span class="profile-field-icon"
                                        ><Icon name="home" :size="16"
                                    /></span>
                                    <div>
                                        <small>Full address</small>
                                        <b>{{
                                            [
                                                detail.address_line,
                                                detail.area,
                                                detail.district,
                                                detail.division,
                                            ]
                                                .filter(Boolean)
                                                .join(', ') ||
                                            'No address provided'
                                        }}</b>
                                        <span
                                            v-if="detail.postal_code"
                                            class="profile-postcode"
                                            >Postcode:
                                            {{ detail.postal_code }}</span
                                        >
                                    </div>
                                </div>
                            </section>

                            <section
                                v-if="detail.notes"
                                class="profile-section"
                                aria-labelledby="profile-notes-title"
                            >
                                <h3
                                    id="profile-notes-title"
                                    class="profile-section-title"
                                >
                                    <Icon name="sticky-note" :size="15" /> Notes
                                </h3>
                                <p class="profile-notes">{{ detail.notes }}</p>
                            </section>

                            <section
                                class="profile-section"
                                aria-labelledby="profile-orders-title"
                            >
                                <div class="profile-orders-heading">
                                    <h3
                                        id="profile-orders-title"
                                        class="profile-section-title"
                                    >
                                        <Icon name="shopping-bag" :size="15" />
                                        Recent orders
                                    </h3>
                                    <span class="profile-order-count"
                                        >{{
                                            detail.orders?.length ?? 0
                                        }}
                                        shown</span
                                    >
                                </div>
                                <div
                                    v-if="!detail.orders?.length"
                                    class="empty small-empty"
                                >
                                    <Icon name="package-open" :size="22" />
                                    <b>No linked orders yet</b>
                                    <span
                                        >Order history will appear here when
                                        available.</span
                                    >
                                </div>
                                <div
                                    v-else
                                    class="order-list profile-order-list"
                                >
                                    <div v-for="o in detail.orders" :key="o.id">
                                        <span class="order-main">
                                            <b>{{
                                                o.order_number ||
                                                `Order #${o.id}`
                                            }}</b>
                                            <small>{{
                                                date(o.created_at)
                                            }}</small>
                                        </span>
                                        <span
                                            class="status"
                                            :class="o.status"
                                            >{{ o.status || 'unknown' }}</span
                                        >
                                        <strong>{{
                                            money(o.grand_total ?? 0)
                                        }}</strong>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <footer class="modal-foot profile-foot">
                            <span class="profile-foot-note"
                                ><Icon name="shield-check" :size="14" />
                                Customer record</span
                            >
                            <div class="profile-foot-actions">
                                <button class="btn" @click="detail = null">
                                    Close
                                </button>
                                <button
                                    class="btn primary"
                                    @click="
                                        openEdit(detail as Customer);
                                        detail = null;
                                    "
                                >
                                    <Icon name="pencil" :size="14" /> Edit
                                    customer
                                </button>
                            </div>
                        </footer>
                    </section>
                </div>
            </Teleport>
            <Teleport to="body">
                <div
                    v-if="deleteTarget"
                    class="backdrop"
                    @click.self="deleteTarget = null"
                >
                    <section
                        class="confirm"
                        role="alertdialog"
                        aria-modal="true"
                    >
                        <div class="delete-icon">
                            <Icon name="trash-2" :size="22" />
                        </div>
                        <h2>Delete customer?</h2>
                        <p>
                            Delete {{ deleteTarget.name }} only if there is no
                            linked order history. Customers with orders must be
                            marked inactive instead.
                        </p>
                        <footer class="modal-foot">
                            <button class="btn" @click="deleteTarget = null">
                                Cancel</button
                            ><button
                                class="btn danger-solid"
                                @click="removeCustomer"
                            >
                                <Icon name="trash-2" :size="14" /> Delete
                                customer
                            </button>
                        </footer>
                    </section>
                </div>
            </Teleport>
        </main></ErpLayout
    >
</template>
<style scoped>
.customers-page {
    max-width: 1480px;
    margin: 0 auto;
    padding: 0 12px 16px;
    color: var(--foreground, #172033);
    display: grid;
    gap: 16px;
    font-size: 12px;
}
.page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
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
    justify-content: flex-start;
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
h1 {
    font-size: 20px;
    font-weight: 750;
    letter-spacing: -0.04em;
    margin: 0;
}
.page-head p,
.modal-head p {
    color: var(--muted-foreground, #788397);
    font-size: 11px;
    margin: 5px 0 0;
}
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border-radius: 9px;
    min-height: 38px;
    padding: 0 13px;
    font-size: 11px;
    font-weight: 650;
    border: 1px solid transparent;
    cursor: pointer;
    transition: 0.15s ease;
}
.btn:hover:not(:disabled) {
    border-color: #aebfe8;
}
.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.btn.primary {
    min-height: 38px;
    padding: 0 13px;
    background: var(--primary, #059669);
    border-color: var(--primary, #059669);
    color: var(--primary-foreground, #fff);
    box-shadow: 0 4px 10px rgb(5 150 105 / 16%);
}
.btn.primary:hover:not(:disabled) {
    filter: brightness(0.92);
    transform: translateY(-1px);
}
.btn.danger-solid {
    background: #b42318;
    border-color: #b42318;
    color: #fff;
}
.notice {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid;
    border-radius: 10px;
    font-size: 12px;
}
.notice button {
    margin-left: auto;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
}
.notice.success {
    color: #067647;
    background: #ecfdf3;
    border-color: #abefc6;
}
.notice.error {
    color: #b42318;
    background: #fff1f0;
    border-color: #fecdca;
}
.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}
.stats article {
    display: grid;
    min-width: 0;
    gap: 8px;
    padding: 15px 16px;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 14px;
    background: var(--card, #fff);
    box-shadow: 0 2px 8px #10204005;
}
.metric-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.metric-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    border: 1px solid transparent;
}
.metric-icon.blue {
    color: #3159c9;
    background: #eff6ff;
    border-color: #dbeafe;
}
.metric-icon.teal {
    color: #009688;
    background: #f0fdfa;
    border-color: #ccfbf1;
}
.metric-icon.green {
    color: #15803d;
    background: #f0fdf4;
    border-color: #dcfce7;
}
.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid var(--border, #e8edf4);
}
.panel-head h2 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
}
.panel-head p {
    margin: 5px 0 0;
    color: var(--muted-foreground, #788397);
    font-size: 12px;
}
.record-count {
    flex: 0 0 auto;
    font-size: 11px;
    padding: 7px 10px;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 9px;
    background: var(--muted, #f8fafc);
    color: var(--muted-foreground, #68758b);
}
.stats span,
.stats small {
    color: var(--muted-foreground, #788397);
    font-size: 12px;
}
.stats strong {
    color: var(--foreground, #172033);
    font-size: 22px;
    line-height: 1.2;
    font-weight: 750;
    letter-spacing: -0.04em;
    font-variant-numeric: tabular-nums;
}
.panel {
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 14px;
    background: var(--card, #fff);
    overflow: hidden;
    box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
}
.toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border, #e8edf4);
}
.search {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 180px;
    height: 38px;
    padding: 0 12px;
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 9px;
    color: #788397;
}
.search:focus-within {
    border-color: #9bb2ed;
    box-shadow: 0 0 0 3px #3159c90d;
}
.search input {
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--foreground, #172033);
    width: 100%;
    font: inherit;
    font-size: 12px;
}
.search input::placeholder {
    color: #98a2b3;
}
.search button {
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
}
.filters {
    display: flex;
    align-items: center;
    gap: 10px;
}
.filters select,
.field input,
.field select,
.field textarea {
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 9px;
    padding: 9px 10px;
    background: var(--card, #fff);
    color: var(--foreground, #172033);
    font: inherit;
    font-size: 13px;
    min-width: 120px;
}
.table-wrap {
    overflow: auto;
    border-top: 1px solid var(--border, #e2e8f0);
}
table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    min-width: 900px;
    text-align: left;
}
th {
    text-align: left;
    padding: 12px 16px;
    background: var(--muted, #f8fafc);
    color: var(--muted-foreground, #788397);
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 700;
}
th,
td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border, #edf0f5);
    font-size: 12px;
    vertical-align: middle;
}
tbody tr {
    transition: background 0.16s ease;
}
tbody tr:hover {
    background: color-mix(in srgb, var(--muted, #f8fafc) 65%, transparent);
}
tbody tr:last-child td {
    border-bottom: 0;
}
.customer-cell {
    display: flex;
    align-items: center;
    gap: 10px;
    text-align: left;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
    padding: 0;
}
.customer-cell > span:last-child {
    display: grid;
    gap: 4px;
}
.customer-cell b,
.contact {
    color: var(--foreground, #172033);
    font-size: 11px;
    font-weight: 650;
}
.customer-cell small,
.sub {
    display: block;
    font-size: 10px;
    color: var(--muted-foreground, #788397);
    margin-top: 4px;
}
.avatar {
    display: grid;
    place-items: center;
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    border-radius: 11px;
    background: #edf2ff;
    color: #3159c9;
    font-size: 11px;
    font-weight: 800;
}
.count {
    display: inline-grid;
    place-items: center;
    min-width: 28px;
    padding: 5px 8px;
    border-radius: 8px;
    background: var(--muted, #f3f5f9);
    color: var(--foreground, #172033);
    font-weight: 700;
}
.status {
    display: inline-flex;
    border-radius: 999px;
    padding: 5px 9px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
    background: #eef2f7;
    color: #475569;
}
.status.active {
    background: #ecfdf3;
    color: #067647;
}
.status.inactive {
    background: #f2f4f7;
    color: #667085;
}
.row-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}
.icon-btn {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 10px;
    background: var(--card, #fff);
    color: var(--muted-foreground, #788397);
    cursor: pointer;
    transition: 0.15s ease;
}
.icon-btn:hover {
    color: #3159c9;
    border-color: #b8c8f1;
    background: #f7f9ff;
}
.icon-btn.danger {
    color: #b42318;
}
.pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 13px 22px;
    border-top: 1px solid var(--border, #e8edf4);
    font-size: 11px;
    color: var(--muted-foreground, #788397);
    flex-wrap: wrap;
}
.pagination > div {
    display: flex;
    align-items: center;
    gap: 10px;
}
.empty {
    display: grid;
    justify-items: center;
    gap: 9px;
    padding: 42px 18px;
    color: var(--muted-foreground, #788397);
    font-size: 12px;
}
.empty b {
    color: var(--foreground, #172033);
    font-size: 14px;
}
.backdrop {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 20px;
    background: rgb(15 23 42 / 54%);
    backdrop-filter: blur(7px);
    -webkit-backdrop-filter: blur(7px);
}
.modal,
.confirm {
    width: min(760px, 100%);
    max-height: calc(100dvh - 40px);
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
    border: 1px solid var(--border, #dfe5ef);
    border-radius: 16px;
    background: var(--card, #fff);
    box-shadow: 0 24px 80px #02061735;
}
.modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 17px 18px;
    border-bottom: 1px solid var(--border, #e8edf4);
    flex: 0 0 auto;
}
.modal-head h2,
.confirm h2 {
    font-size: 16px;
    letter-spacing: -0.3px;
    margin: 0;
}
.modal > form {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
}
.modal-body {
    flex: 1 1 auto;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 16px 18px;
    min-height: 0;
    scrollbar-gutter: stable;
}
.modal-title-wrap {
    display: flex;
    align-items: center;
    gap: 13px;
    min-width: 0;
}
.modal-title-icon {
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    display: grid;
    place-items: center;
    border-radius: 14px;
    color: #3159c9;
    background: #eff4ff;
    border: 1px solid #dbe5ff;
}
.eyebrow {
    color: #64748b;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.11em;
    margin-bottom: 4px;
}
.form-section + .form-section {
    margin-top: 24px;
    padding-top: 22px;
    border-top: 1px solid var(--border, #e8edf4);
}
.form-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}
.section-icon {
    width: 32px;
    height: 32px;
    flex: 0 0 32px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: #f1f5f9;
    color: #475569;
}
.form-section-title h3 {
    margin: 0;
    font-size: 12px;
    font-weight: 750;
}
.form-section-title p {
    margin: 3px 0 0;
    color: var(--muted-foreground, #788397);
    font-size: 11px;
    font-weight: 400;
}
.field .field-error {
    color: #b42318;
    font-size: 11px;
    font-weight: 600;
}
.field textarea {
    resize: vertical;
    min-height: 94px;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.field {
    display: grid;
    gap: 7px;
    font-size: 11px;
    font-weight: 650;
}
.field span i {
    color: #b42318;
    font-style: normal;
}
.field input,
.field select,
.field textarea {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}
.field input:focus,
.field select:focus,
.field textarea:focus {
    outline: 2px solid #3159c926;
    border-color: #4777e8;
}
.field small {
    color: var(--muted-foreground, #788397);
    font-size: 11px;
    font-weight: 400;
}
.field small:has(+ small) {
    color: #b42318;
}
.wide {
    grid-column: 1 / -1;
}
.modal-foot {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    padding: 13px 20px;
    border-top: 1px solid var(--border, #e8edf4);
    flex: 0 0 auto;
}
.profile {
    width: min(720px, 100%);
}
.profile-hero {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 12px;
    padding: 14px;
    border: 1px solid var(--border, #e8edf4);
    border-radius: 12px;
    background: var(--muted, #f6f8fb);
}
.avatar.large {
    width: 48px;
    height: 48px;
    font-size: 14px;
}
.profile-hero h3 {
    margin: 0 0 7px;
    font-size: 16px;
}
.profile-hero > strong {
    font-size: 18px;
    align-self: end;
    text-align: right;
}
.profile-hero > small {
    grid-column: 3;
    text-align: right;
    color: var(--muted-foreground, #788397);
    font-size: 11px;
}
.profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 18px;
}
.profile-grid > div {
    display: grid;
    gap: 5px;
}
.profile-grid small {
    color: var(--muted-foreground, #788397);
    font-size: 11px;
}
.profile-grid b {
    font-size: 12px;
    overflow-wrap: anywhere;
}
.history-title {
    font-size: 13px;
    margin: 22px 0 8px;
}
.order-list {
    display: grid;
}
.order-list > div {
    display: grid;
    grid-template-columns: 1fr auto auto;
    align-items: center;
    gap: 14px;
    padding: 11px 0;
    border-bottom: 1px solid var(--border, #e8edf4);
    font-size: 12px;
}
.order-list > div > span:first-child {
    display: grid;
    gap: 4px;
}
.order-list small {
    font-size: 11px;
    color: var(--muted-foreground, #788397);
}
.small-empty {
    padding: 20px;
}
.confirm {
    width: min(430px, 100%);
    padding: 24px;
    gap: 12px;
}
.confirm p {
    line-height: 1.6;
}
.delete-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: #fef3f2;
    color: #b42318;
}
.confirm .modal-foot {
    padding: 12px 0 0;
    margin-top: 5px;
    border-top: 1px solid var(--border, #e8edf4);
}

.btn:focus-visible,
.icon-btn:focus-visible,
.customer-cell:focus-visible,
.search:focus-within {
    outline: 2px solid #3159c94a;
    outline-offset: 2px;
}

/* Canonical Madina Scale page standard: compact typography, spacing and controls. */
.customers-page {
    width: 100%;
    max-width: none;
    padding: 0;
    gap: 14px;
    font-size: 11px;
    animation: customers-page-enter 180ms ease both;
}
.stats article {
    padding: 17px 18px;
    border-radius: 14px;
    box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        border-color 0.2s ease;
    animation: card-enter 350ms ease both;
}
.stats article:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 18px rgb(16 24 40 / 5%);
    border-color: #d5deef;
}
.stats article:nth-child(1) {
    animation-delay: 0ms;
}
.stats article:nth-child(2) {
    animation-delay: 45ms;
}
.stats article:nth-child(3) {
    animation-delay: 90ms;
}

.panel {
    border-radius: 14px;
    box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
}
.panel-head {
    padding: 17px 18px;
}
.record-count {
    font-size: 11px;
    padding: 6px 9px;
    border-radius: 9px;
}
.filters select,
.field input,
.field select,
.field textarea {
    min-height: 38px;
}
.icon-btn {
    width: 32px;
    height: 32px;
    transition:
        color 0.2s ease,
        border-color 0.2s ease,
        background 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
}
.icon-btn:hover:not(:disabled) {
    transform: translateY(-1px);
}
.btn:not(:disabled):active {
    transform: translateY(0);
}
.table-wrap {
    border-top: 1px solid var(--border, #e2e8f0);
}
tbody tr {
    animation: customer-row-enter 260ms ease both;
}
tbody tr:nth-child(1) {
    animation-delay: 20ms;
}
tbody tr:nth-child(2) {
    animation-delay: 40ms;
}
tbody tr:nth-child(3) {
    animation-delay: 60ms;
}
tbody tr:nth-child(4) {
    animation-delay: 80ms;
}
tbody tr:nth-child(5) {
    animation-delay: 100ms;
}
tbody tr:nth-child(n + 6) {
    animation-delay: 120ms;
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
@keyframes customers-page-enter {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes customer-row-enter {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
    }
}
@media (min-width: 761px) and (max-width: 1050px) {
    .customers-page {
        padding: 20px;
    }
    .stats {
        gap: 12px;
    }
    .stats article {
        padding: 16px;
    }
    .metric-icon {
        width: 38px;
        height: 38px;
        flex-basis: 38px;
    }
    .modal {
        width: min(760px, 100%);
    }
}
@media (max-width: 760px) {
    .customers-page {
        padding: 16px;
        gap: 16px;
    }
    .page-head {
        align-items: flex-start;
    }
    .page-head > .btn {
        white-space: nowrap;
    }
    .customers-page {
        padding: 14px;
    }
    .page-head {
        gap: 14px;
    }
    .title-line {
        align-items: flex-start;
        gap: 11px;
    }
    .hero-icon {
        width: 42px;
        height: 42px;
        flex-basis: 42px;
        border-radius: 12px;
    }
    h1 {
        font-size: 22px;
    }
    .page-head p {
        font-size: 12px;
        line-height: 1.5;
    }
    .stats {
        grid-template-columns: 1fr;
    }
    .toolbar {
        align-items: stretch;
        flex-direction: column;
        padding: 14px;
    }
    .search {
        min-width: 100%;
        box-sizing: border-box;
    }
    .filters {
        justify-content: space-between;
    }
    .form-grid,
    .profile-grid {
        grid-template-columns: 1fr;
    }
    .panel-head {
        align-items: flex-start;
        padding: 16px;
    }
    .record-count {
        white-space: nowrap;
    }
    .modal {
        width: 100%;
        max-height: calc(100dvh - 20px);
        border-radius: 16px;
    }
    .modal-title-icon {
        width: 40px;
        height: 40px;
        flex-basis: 40px;
    }
    .modal-head {
        align-items: flex-start;
    }
    .modal-foot {
        flex-wrap: wrap;
    }
    .modal-foot .btn {
        flex: 1;
    }
    .wide {
        grid-column: auto;
    }
    .backdrop {
        padding: 10px;
    }
    .modal-head,
    .modal-body {
        padding: 15px;
    }
    .modal-foot {
        padding: 12px 15px;
    }
    .pagination {
        align-items: flex-start;
        padding: 12px 14px;
    }
    .pagination > div {
        width: 100%;
        justify-content: space-between;
    }
    .profile-hero {
        grid-template-columns: auto 1fr;
    }
    .profile-hero > strong,
    .profile-hero > small {
        grid-column: 1 / -1;
        text-align: left;
    }
    .order-list > div {
        grid-template-columns: 1fr auto;
    }
    .order-list > div > strong {
        grid-column: 1 / -1;
    }
}

/* Final source-of-truth alignment */
.customers-page {
    padding: 0 !important;
    gap: 14px !important;
    font-size: 11px;
}
.page-head {
    gap: 20px;
}
.crumb {
    font-size: 11px;
    gap: 7px;
    margin-bottom: 8px;
}
h1 {
    font-size: 23px;
    font-weight: 750;
    letter-spacing: -0.025em;
}
.page-head p {
    font-size: 12px;
    line-height: 1.5;
}
.btn {
    min-height: 36px;
    padding: 0 13px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
}
.btn.primary {
    background: var(--primary, #059669);
    border-color: var(--primary, #059669);
    box-shadow: 0 2px 5px rgb(5 150 105 / 14%);
}
.btn.primary:hover:not(:disabled) {
    filter: brightness(0.92);
    transform: translateY(-1px);
}
.stats {
    gap: 12px;
}
.stats article {
    padding: 12px 14px;
    border-radius: 12px;
    gap: 7px;
}
.metric-icon {
    width: 34px;
    height: 34px;
    flex-basis: 34px;
    border-radius: 9px;
}
.stats span,
.stats small {
    font-size: 10px;
}
.stats strong {
    font-size: 20px;
    letter-spacing: -0.02em;
}
.panel {
    border-radius: 12px;
    box-shadow: 0 1px 4px rgb(16 24 40 / 3%);
}
.panel-head {
    padding: 14px 16px;
}
.panel-head h2 {
    font-size: 15px;
}
.panel-head p {
    font-size: 11px;
}
.record-count {
    font-size: 10px;
    padding: 5px 8px;
}
.toolbar {
    gap: 8px;
    padding: 12px 16px;
}
.search {
    height: 36px;
    border-radius: 8px;
}
.search input,
.filters select {
    font-size: 12px;
}
.filters select {
    min-height: 36px;
    border-radius: 8px;
}
th {
    padding: 10px 14px;
    font-size: 9px;
}
th,
td {
    padding: 10px 14px;
    font-size: 11px;
}
.customer-cell b,
.contact {
    font-size: 11px;
}
.customer-cell small,
.sub {
    font-size: 9px;
}
.status {
    padding: 4px 7px;
    font-size: 9px;
}
.pagination {
    padding: 11px 16px;
    font-size: 10px;
}
.empty {
    padding: 32px 16px;
    font-size: 11px;
}
.empty b {
    font-size: 13px;
}
.backdrop {
    padding: 18px;
    background: rgb(15 23 42 / 48%);
    backdrop-filter: blur(5px);
}
.modal,
.confirm {
    width: min(720px, 100%);
    max-height: min(88dvh, 820px);
    border-radius: 14px;
    box-shadow: 0 20px 60px rgb(2 6 23 / 22%);
}
.modal-head {
    padding: 18px 18px 14px;
    gap: 12px;
}
.modal-head h2,
.confirm h2 {
    font-size: 17px;
    font-weight: 750;
    letter-spacing: -0.02em;
}
.modal-head p {
    font-size: 11px;
    line-height: 1.5;
}
.modal-title-wrap {
    gap: 11px;
}
.modal-title-icon {
    width: 40px;
    height: 40px;
    flex-basis: 40px;
    border-radius: 11px;
}
.eyebrow {
    font-size: 9px;
    letter-spacing: 0.1em;
    margin-bottom: 3px;
}
.modal-body {
    padding: 16px 18px;
}
.form-section + .form-section {
    margin-top: 18px;
    padding-top: 16px;
}
.form-section-title {
    gap: 9px;
    margin-bottom: 12px;
}
.section-icon {
    width: 29px;
    height: 29px;
    flex-basis: 29px;
    border-radius: 8px;
}
.form-section-title h3 {
    font-size: 13px;
}
.form-section-title p {
    font-size: 10px;
}
.form-grid {
    gap: 13px 14px;
}
.field {
    gap: 6px;
    font-size: 10px;
    font-weight: 700;
}
.field input,
.field select,
.field textarea {
    min-height: 36px;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 12px;
}
.field textarea {
    min-height: 82px;
}
.field small,
.field .field-error {
    font-size: 10px;
    line-height: 1.45;
}
.modal-foot {
    padding: 12px 18px;
    gap: 8px;
}
.profile-hero h3 {
    font-size: 14px;
}
.profile-hero > strong {
    font-size: 16px;
}
.profile-grid {
    gap: 12px;
    margin-top: 15px;
}
.profile-grid small,
.order-list small {
    font-size: 10px;
}
.profile-grid b,
.order-list > div {
    font-size: 11px;
}
.history-title {
    font-size: 13px;
    margin: 18px 0 8px;
}
.confirm {
    padding: 22px;
    gap: 10px;
}
.confirm p {
    font-size: 12px;
}
.btn:focus-visible,
.icon-btn:focus-visible,
.customer-cell:focus-visible,
.field input:focus-visible,
.field select:focus-visible,
.field textarea:focus-visible {
    outline: 2px solid var(--ms-focus-ring, #059669);
    outline-offset: 2px;
}
.field select:disabled {
    cursor: not-allowed;
    opacity: 0.58;
    background: var(--muted, #f8fafc);
}
@media (max-width: 760px) {
    .customers-page {
        padding: 0 !important;
        gap: 12px !important;
    }
    h1 {
        font-size: 20px;
    }
    .stats {
        grid-template-columns: 1fr;
    }
    .modal {
        width: 100%;
        max-height: calc(100dvh - 20px);
        border-radius: 14px;
    }
    .modal-head,
    .modal-body {
        padding: 14px;
    }
    .modal-foot {
        padding: 11px 14px;
    }
    .field {
        font-size: 10px;
    }
}

@media (max-width: 600px) {
    .backdrop {
        align-items: flex-start;
        padding: 10px;
    }
    .modal,
    .confirm {
        max-height: calc(100dvh - 20px);
        border-radius: 12px;
    }
    .modal-head {
        padding: 13px 14px;
    }
    .modal-body {
        padding: 14px;
    }
    .modal-foot {
        padding: 11px 14px;
    }
    .form-grid {
        grid-template-columns: minmax(0, 1fr);
    }
    .wide {
        grid-column: auto;
    }
}
@media (prefers-reduced-motion: reduce) {
    .customers-page,
    .stats article,
    tbody tr {
        animation: none !important;
    }
}

/* Final compact design-system pass + viewport-level modal layers. */
.customers-page {
    font-size: 11px !important;
    gap: 12px !important;
}
.crumb {
    font-size: 10px !important;
}
h1 {
    font-size: 21px !important;
}
.page-head p {
    font-size: 11px !important;
}
.btn {
    font-size: 10px !important;
    min-height: 34px;
}
.stats article {
    padding: 12px 14px !important;
}
.stats span,
.stats small {
    font-size: 9px !important;
}
.stats strong {
    font-size: 18px !important;
}
.panel-head h2 {
    font-size: 14px !important;
}
.panel-head p,
.record-count {
    font-size: 10px !important;
}
.search input,
.filters select {
    font-size: 11px !important;
}
th {
    font-size: 8px !important;
}
th,
td {
    font-size: 10px !important;
    padding: 9px 12px;
}
.customer-cell b,
.contact {
    font-size: 10px !important;
}
.customer-cell small,
.sub,
.status {
    font-size: 9px !important;
}
.pagination,
.empty {
    font-size: 10px !important;
}
.empty b {
    font-size: 12px !important;
}

/* Teleport keeps overlays outside layout stacking/overflow contexts. */
.backdrop {
    position: fixed !important;
    inset: 0 !important;
    z-index: 2147483000 !important;
    width: 100vw;
    height: 100dvh;
    box-sizing: border-box;
    display: grid;
    place-items: center;
    padding: 24px;
    overflow-y: auto;
    background: rgb(8 15 30 / 48%);
    -webkit-backdrop-filter: blur(8px) saturate(115%);
    backdrop-filter: blur(8px) saturate(115%);
}
.modal,
.confirm {
    max-height: calc(100dvh - 48px) !important;
    box-sizing: border-box;
    border-radius: 14px !important;
    box-shadow: 0 28px 90px rgb(2 6 23 / 32%) !important;
}
.modal {
    width: min(840px, calc(100vw - 48px)) !important;
}
.modal-head h2,
.confirm h2 {
    font-size: 16px !important;
}
.modal-head p {
    font-size: 11px !important;
}
.modal-body {
    overflow-y: auto;
    overscroll-behavior: contain;
}
.form-section-title h3 {
    font-size: 12px !important;
}
.form-section-title p,
.field small,
.field .field-error {
    font-size: 10px !important;
}
.field {
    font-size: 10px !important;
}
.field input,
.field select,
.field textarea {
    font-size: 11px !important;
    min-height: 36px;
}
.modal-foot {
    flex: 0 0 auto;
}
@media (max-width: 560px) {
    .backdrop {
        padding: 10px;
    }
    .modal,
    .confirm {
        width: calc(100vw - 20px) !important;
        max-height: calc(100dvh - 20px) !important;
    }
    h1 {
        font-size: 19px !important;
    }
}

/* Customer row opens the profile; action buttons remain independent. */
.customer-row {
    cursor: pointer;
    transition:
        background-color 150ms ease,
        box-shadow 150ms ease;
}
.customer-row:hover {
    background: color-mix(
        in srgb,
        var(--primary, #059669) 4%,
        var(--card, #fff)
    );
}
.customer-row:focus-visible {
    outline: 2px solid #05966970;
    outline-offset: -2px;
}
.customer-row .row-actions {
    position: relative;
    z-index: 1;
}

/* Compact, responsive customer profile dialog. */
.profile-modal {
    width: min(900px, 100%);
    max-height: min(820px, calc(100dvh - 32px));
    border-radius: 16px;
}
.profile-head {
    padding: 14px 18px;
}
.profile-head .modal-title-icon {
    width: 42px;
    height: 42px;
    flex-basis: 42px;
    border-radius: 12px;
}
.profile-head h2 {
    font-size: 16px;
}
.profile-body {
    display: grid;
    align-content: start;
    gap: 16px;
    padding: 16px 18px;
}
.profile-hero {
    grid-template-columns: auto minmax(0, 1fr) minmax(160px, auto);
    padding: 13px 14px;
    gap: 12px;
    background: linear-gradient(120deg, #f5f8ff 0%, var(--muted, #f6f8fb) 65%);
}
.profile-identity {
    min-width: 0;
}
.profile-identity h3 {
    margin: 0 0 7px;
    font-size: 16px;
    line-height: 1.3;
    overflow-wrap: anywhere;
}
.profile-identity-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.profile-code {
    color: var(--muted-foreground, #788397);
    font-size: 10px;
    font-weight: 600;
}
.profile-value {
    display: grid;
    justify-items: end;
    gap: 5px;
}
.profile-value small {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--muted-foreground, #788397);
    font-size: 10px;
}
.profile-value strong {
    font-size: 17px;
    font-variant-numeric: tabular-nums;
}
.profile-section {
    min-width: 0;
}
.profile-section-title {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0 0 10px;
    font-size: 12px;
    font-weight: 750;
    color: var(--foreground, #172033);
}
.profile-section-title :deep(svg) {
    color: var(--primary, #059669);
}
.profile-grid {
    margin-top: 0;
    gap: 8px;
}
.profile-field {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    min-width: 0;
    padding: 10px;
    border: 1px solid var(--border, #e8edf4);
    border-radius: 10px;
    background: var(--card, #fff);
}
.profile-field-icon {
    display: grid;
    place-items: center;
    flex: 0 0 30px;
    width: 30px;
    height: 30px;
    border-radius: 9px;
    background: var(--muted, #f6f8fb);
    color: var(--muted-foreground, #64748b);
}
.profile-field-copy {
    display: grid;
    gap: 4px;
    min-width: 0;
}
.profile-field-copy small,
.profile-address small {
    color: var(--muted-foreground, #788397);
    font-size: 10px;
}
.profile-field-copy b,
.profile-address b {
    font-size: 11px;
    line-height: 1.45;
    font-weight: 650;
    overflow-wrap: anywhere;
}
.profile-address {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px;
    border: 1px solid var(--border, #e8edf4);
    border-radius: 10px;
}
.profile-address > div {
    display: grid;
    gap: 5px;
    min-width: 0;
}
.profile-postcode {
    color: var(--muted-foreground, #788397);
    font-size: 10px;
}
.profile-notes {
    margin: 0;
    padding: 11px 12px;
    border-radius: 10px;
    background: var(--muted, #f6f8fb);
    font-size: 11px;
    line-height: 1.55;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
.profile-orders-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.profile-order-count {
    color: var(--muted-foreground, #788397);
    font-size: 10px;
}
.profile-order-list > div {
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 12px;
    padding: 10px 4px;
}
.profile-order-list .order-main {
    min-width: 0;
    display: grid;
    gap: 4px;
}
.profile-order-list .order-main b {
    overflow-wrap: anywhere;
}
.profile-order-list > div > strong {
    font-size: 11px;
    white-space: nowrap;
}
.profile-body .small-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 18px 12px;
    border: 1px dashed var(--border, #dfe5ef);
    border-radius: 10px;
    color: var(--muted-foreground, #788397);
    font-size: 11px;
}
.profile-body .small-empty b {
    color: var(--foreground, #172033);
    font-size: 12px;
}
.profile-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 18px;
    flex: 0 0 auto;
}
.profile-foot-note,
.profile-foot-actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.profile-foot-note {
    color: var(--muted-foreground, #788397);
    font-size: 10px;
}
.profile-foot-actions {
    justify-content: flex-end;
}
@media (max-width: 640px) {
    .profile-modal {
        max-height: calc(100dvh - 16px);
        border-radius: 13px;
    }
    .profile-body {
        gap: 13px;
        padding: 12px;
    }
    .profile-head {
        padding: 12px;
    }
    .profile-hero {
        grid-template-columns: auto minmax(0, 1fr);
    }
    .profile-value {
        grid-column: 1 / -1;
        justify-items: start;
        padding-top: 10px;
        border-top: 1px solid var(--border, #e8edf4);
    }
    .profile-grid {
        grid-template-columns: 1fr;
    }
    .profile-foot {
        align-items: stretch;
        flex-direction: column;
        padding: 10px 12px;
    }
    .profile-foot-actions {
        width: 100%;
    }
    .profile-foot-actions .btn {
        flex: 1;
    }
    .profile-order-list > div {
        grid-template-columns: minmax(0, 1fr) auto;
    }
    .profile-order-list > div > strong {
        grid-column: 1 / -1;
        justify-self: start;
    }
}
@media (prefers-reduced-motion: reduce) {
    .customer-row {
        transition: none;
    }
}

/* Final profile-dialog layout correction: use the entire dialog width. */
.profile-modal {
    display: flex !important;
    flex-direction: column !important;
    width: min(920px, calc(100vw - 48px)) !important;
    max-width: none !important;
    max-height: calc(100dvh - 48px) !important;
    min-width: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    align-self: center !important;
}
.profile-modal > .profile-head,
.profile-modal > .profile-foot {
    width: 100% !important;
    max-width: none !important;
    box-sizing: border-box !important;
    flex: 0 0 auto !important;
}
.profile-modal > .profile-head {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 18px 22px !important;
    text-align: left !important;
    border-bottom: 1px solid var(--border, #e2e8f0) !important;
    background: linear-gradient(120deg, #ffffff, #f8fbff) !important;
}
.profile-modal .modal-title-wrap {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 12px !important;
    min-width: 0 !important;
}
.profile-modal .profile-heading-copy {
    min-width: 0 !important;
    text-align: left !important;
}
.profile-modal > .profile-body {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) !important;
    align-content: start !important;
    justify-items: stretch !important;
    gap: 18px !important;
    width: 100% !important;
    max-width: none !important;
    min-width: 0 !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 20px 22px !important;
    box-sizing: border-box !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    flex: 1 1 auto !important;
    scrollbar-gutter: stable !important;
}
.profile-modal .profile-hero {
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    grid-template-columns: auto minmax(0, 1fr) minmax(150px, auto) !important;
    align-items: center !important;
    padding: 16px 18px !important;
    gap: 14px !important;
    border: 1px solid #dfe7f4 !important;
    border-radius: 14px !important;
    background: linear-gradient(
        120deg,
        #f2f7ff 0%,
        #fbfdff 60%,
        #f0fdf7 100%
    ) !important;
}
.profile-modal .profile-identity h3 {
    font-size: 18px !important;
    font-weight: 700 !important;
    letter-spacing: -0.025em !important;
}
.profile-modal .profile-value strong {
    color: var(--foreground, #172033) !important;
    font-size: 20px !important;
    font-weight: 750 !important;
}
.profile-modal .profile-section {
    width: 100% !important;
    min-width: 0 !important;
}
.profile-modal .profile-grid {
    display: grid !important;
    width: 100% !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 10px !important;
    margin: 0 !important;
}
.profile-modal .profile-field {
    min-width: 0 !important;
    padding: 12px 13px !important;
    border-radius: 12px !important;
    background: var(--card, #fff) !important;
    box-shadow: 0 2px 8px rgb(15 23 42 / 3%) !important;
}
.profile-modal .profile-address {
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    padding: 14px !important;
    border-radius: 12px !important;
    background: linear-gradient(120deg, #fff, #f8fafc) !important;
}
.profile-modal .profile-foot {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 12px !important;
    padding: 14px 22px !important;
    border-top: 1px solid var(--border, #e2e8f0) !important;
    background: #fff !important;
}
.profile-modal .profile-foot-actions {
    display: flex !important;
    justify-content: flex-end !important;
    gap: 8px !important;
}
.profile-modal .profile-section-title {
    font-size: 13px !important;
    margin-bottom: 10px !important;
}
.profile-modal .profile-order-list {
    width: 100% !important;
}
.profile-modal .small-empty {
    width: 100% !important;
    box-sizing: border-box !important;
}
@media (max-width: 680px) {
    .profile-modal {
        width: calc(100vw - 20px) !important;
        max-height: calc(100dvh - 20px) !important;
    }
    .profile-modal > .profile-head {
        padding: 13px 14px !important;
    }
    .profile-modal > .profile-body {
        padding: 14px !important;
        gap: 15px !important;
    }
    .profile-modal .profile-hero {
        grid-template-columns: auto minmax(0, 1fr) !important;
        padding: 13px !important;
    }
    .profile-modal .profile-value {
        grid-column: 1 / -1 !important;
        justify-items: start !important;
        width: 100% !important;
        padding-top: 10px !important;
        border-top: 1px solid var(--border, #e2e8f0) !important;
    }
    .profile-modal .profile-grid {
        grid-template-columns: minmax(0, 1fr) !important;
    }
    .profile-modal .profile-foot {
        padding: 11px 14px !important;
        flex-direction: column !important;
        align-items: stretch !important;
    }
    .profile-modal .profile-foot-actions {
        width: 100% !important;
    }
    .profile-modal .profile-foot-actions .btn {
        flex: 1 1 0 !important;
    }
}

/* Edit customer modal refinement — isolated from the profile and list views. */
.customer-form-modal.is-editing {
    width: min(900px, 100%);
    max-height: min(860px, calc(100dvh - 32px));
    border: 1px solid rgb(203 213 225 / 78%);
    border-radius: 18px;
    background: var(--card, #fff);
    box-shadow:
        0 30px 90px rgb(2 6 23 / 28%),
        0 4px 14px rgb(2 6 23 / 8%);
    animation: customer-edit-modal-in 180ms cubic-bezier(0.2, 0.75, 0.25, 1)
        both;
}
.customer-form-modal.is-editing .modal-head {
    padding: 18px 22px;
    background: linear-gradient(115deg, #ffffff 0%, #f8fbff 100%);
    border-bottom: 1px solid #e5ebf3;
}
.customer-form-modal.is-editing .modal-title-wrap {
    gap: 13px;
}
.customer-form-modal.is-editing .modal-title-icon {
    width: 44px;
    height: 44px;
    flex-basis: 44px;
    border-radius: 13px;
    color: #047857;
    background: #ecfdf5;
    border-color: #bbf7d0;
}
.customer-form-modal.is-editing .eyebrow {
    color: #047857;
    font-size: 9px;
    letter-spacing: 0.12em;
}
.customer-form-modal.is-editing .modal-head h2 {
    font-size: 20px;
    font-weight: 750;
    letter-spacing: -0.55px;
    line-height: 1.25;
}
.customer-form-modal.is-editing .modal-head p {
    margin-top: 5px;
    font-size: 12px;
    line-height: 1.5;
}
.customer-form-modal.is-editing .icon-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #fff;
    border-color: #e2e8f0;
    transition:
        background 150ms ease,
        color 150ms ease,
        border-color 150ms ease,
        transform 150ms ease;
}
.customer-form-modal.is-editing .icon-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: rotate(3deg);
}
.customer-form-modal.is-editing > form {
    min-height: 0;
}
.customer-form-modal.is-editing .modal-body {
    padding: 20px 22px;
    background: #fbfcfe;
    scrollbar-color: #cbd5e1 transparent;
    scrollbar-width: thin;
}
.customer-form-modal.is-editing .form-section {
    padding: 17px 18px 18px;
    border: 1px solid #e6ebf2;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 2px 8px rgb(15 23 42 / 2.5%);
}
.customer-form-modal.is-editing .form-section + .form-section {
    margin-top: 14px;
    padding-top: 17px;
    border-top: 1px solid #e6ebf2;
}
.customer-form-modal.is-editing .form-section-title {
    margin-bottom: 16px;
    gap: 11px;
}
.customer-form-modal.is-editing .section-icon {
    width: 34px;
    height: 34px;
    flex-basis: 34px;
    color: #047857;
    background: #ecfdf5;
    border: 1px solid #d1fae5;
    border-radius: 10px;
}
.customer-form-modal.is-editing .form-section-title h3 {
    font-size: 13px;
    font-weight: 750;
    letter-spacing: -0.15px;
}
.customer-form-modal.is-editing .form-section-title p {
    font-size: 11px;
    line-height: 1.45;
}
.customer-form-modal.is-editing .form-grid {
    gap: 14px 16px;
}
.customer-form-modal.is-editing .field {
    gap: 7px;
    font-size: 11px;
    font-weight: 700;
    color: #263449;
}
.customer-form-modal.is-editing .field input,
.customer-form-modal.is-editing .field select,
.customer-form-modal.is-editing .field textarea {
    min-height: 39px;
    padding: 9px 11px;
    border: 1px solid #dbe4ef;
    border-radius: 9px;
    background: #fff;
    color: #172033;
    font: inherit;
    font-size: 12px;
    font-weight: 500;
    box-shadow: 0 1px 2px rgb(15 23 42 / 2%);
    transition:
        border-color 150ms ease,
        box-shadow 150ms ease,
        background 150ms ease;
}
.customer-form-modal.is-editing .field input::placeholder,
.customer-form-modal.is-editing .field textarea::placeholder {
    color: #9aa6b5;
}
.customer-form-modal.is-editing .field textarea {
    min-height: 88px;
    line-height: 1.5;
}
.customer-form-modal.is-editing .field input:hover,
.customer-form-modal.is-editing .field select:hover,
.customer-form-modal.is-editing .field textarea:hover {
    border-color: #bdcbdc;
}
.customer-form-modal.is-editing .field input:focus,
.customer-form-modal.is-editing .field select:focus,
.customer-form-modal.is-editing .field textarea:focus {
    outline: none;
    border-color: #059669;
    box-shadow: 0 0 0 3px rgb(5 150 105 / 12%);
    background: #fff;
}
.customer-form-modal.is-editing .field small {
    font-size: 10px;
    line-height: 1.45;
}
.customer-form-modal.is-editing .field .field-error {
    padding: 6px 8px;
    border: 1px solid #fecaca;
    border-radius: 7px;
    background: #fef2f2;
    color: #b42318;
    font-size: 10px;
}
.customer-form-modal.is-editing .modal-foot {
    padding: 13px 22px;
    background: #fff;
    border-top: 1px solid #e5ebf3;
    box-shadow: 0 -5px 14px rgb(15 23 42 / 2%);
}
.customer-form-modal.is-editing .modal-foot .btn {
    min-height: 38px;
    padding: 0 15px;
    border-radius: 9px;
    font-size: 11px;
    font-weight: 750;
}
.customer-form-modal.is-editing .modal-foot .btn.primary {
    background: #059669;
    border-color: #059669;
    box-shadow: 0 3px 8px rgb(5 150 105 / 17%);
}
.customer-form-modal.is-editing .modal-foot .btn.primary:hover:not(:disabled) {
    background: #047857;
    border-color: #047857;
    filter: none;
    transform: translateY(-1px);
}
.customer-form-modal.is-editing .modal-foot .btn:disabled {
    opacity: 0.65;
    cursor: wait;
}
@keyframes customer-edit-modal-in {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.992);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
@media (max-width: 760px) {
    .customer-form-modal.is-editing {
        width: 100%;
        max-height: calc(100dvh - 20px);
        border-radius: 15px;
    }
    .customer-form-modal.is-editing .modal-head {
        padding: 14px 15px;
    }
    .customer-form-modal.is-editing .modal-head h2 {
        font-size: 18px;
    }
    .customer-form-modal.is-editing .modal-body {
        padding: 12px;
    }
    .customer-form-modal.is-editing .form-section {
        padding: 14px;
        border-radius: 12px;
    }
    .customer-form-modal.is-editing .form-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 12px;
    }
    .customer-form-modal.is-editing .wide {
        grid-column: auto;
    }
    .customer-form-modal.is-editing .modal-foot {
        padding: 11px 14px;
    }
}
@media (max-width: 420px) {
    .customer-form-modal.is-editing .modal-title-icon {
        width: 38px;
        height: 38px;
        flex-basis: 38px;
    }
    .customer-form-modal.is-editing .modal-head p {
        max-width: 235px;
    }
    .customer-form-modal.is-editing .modal-foot .btn {
        flex: 1 1 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .customer-form-modal.is-editing {
        animation: none;
    }
    .customer-form-modal.is-editing *,
    .customer-form-modal.is-editing *::before,
    .customer-form-modal.is-editing *::after {
        transition-duration: 0.01ms !important;
    }
}
</style>
