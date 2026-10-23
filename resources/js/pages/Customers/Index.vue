<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";
import bd from "bangladesh-geojson";
interface Props {
  userName?: string;
  userEmail?: string;
}
const props = withDefaults(defineProps<Props>(), { userName: "", userEmail: "" });
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
  status: "active" | "inactive";
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
type Form = Omit<Customer, "id" | "customer_code" | "orders_count" | "created_at">;
const customers = ref<Customer[]>([]),
  loading = ref(false),
  saving = ref(false),
  search = ref(""),
  status = ref("all"),
  page = ref(1),
  lastPage = ref(1),
  total = ref(0),
  stats = ref({ total: 0, active: 0, with_phone: 0 });
const showForm = ref(false),
  editing = ref<Customer | null>(null),
  detail = ref<Record<string, any> | null>(null),
  deleteTarget = ref<Customer | null>(null),
  notice = ref(""),
  noticeType = ref<"success" | "error">("success"),
  errors = ref<Record<string, string>>({});
function emptyForm(): Form {
  return {
    name: "",
    phone: "",
    email: "",
    company_name: "",
    address_line: "",
    area: "",
    district: "",
    division: "",
    postal_code: "",
    notes: "",
    status: "active",
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
const selectedDivision = computed(() =>
  bdDivisions.find(
    (item) => item.name.toLowerCase() === (form.division ?? "").toLowerCase()
  )
);
const availableDistricts = computed(() =>
  selectedDivision.value
    ? bdDistricts.filter(
        (item) => String(item.division_id) === String(selectedDivision.value?.id)
      )
    : []
);
const selectedDistrict = computed(() =>
  availableDistricts.value.find(
    (item) => item.name.toLowerCase() === (form.district ?? "").toLowerCase()
  )
);
const availableUpazilas = computed(() =>
  selectedDistrict.value
    ? bdUpazilas.filter(
        (item) => String(item.district_id) === String(selectedDistrict.value?.id)
      )
    : []
);
const availablePostcodes = computed(() => {
  if (!selectedDistrict.value) return [];
  const upazilaName = (form.area ?? "").trim().toLowerCase();
  return bdPostcodes.filter(
    (item) =>
      String(item.district_id) === String(selectedDistrict.value?.id) &&
      (!upazilaName || item.upazila.toLowerCase() === upazilaName)
  );
});
function changeDivision(value: string) {
  form.division = value;
  form.district = "";
  form.area = "";
  form.postal_code = "";
}
function changeDistrict(value: string) {
  form.district = value;
  form.area = "";
  form.postal_code = "";
}
function changeUpazila(value: string) {
  form.area = value;
  form.postal_code = "";
}

function headers() {
  const csrf =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
  return {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    ...(csrf ? { "X-CSRF-TOKEN": csrf } : {}),
  };
}
async function errorText(r: Response, fallback: string) {
  const p = (await r.json().catch(() => ({}))) as Api<unknown>;
  errors.value = Object.fromEntries(
    Object.entries(p.errors ?? {}).map(([k, v]) => [k, v[0] ?? "Invalid value"])
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
      per_page: "15",
    });
    const r = await fetch(`/api/customers?${q}`, {
      credentials: "same-origin",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    if (!r.ok) throw new Error(await errorText(r, "Could not load customers."));
    const p = (await r.json()) as Api<Customer[]>;
    customers.value = p.data ?? [];
    lastPage.value = p.last_page ?? 1;
    total.value = p.total ?? customers.value.length;
    stats.value = p.stats ?? stats.value;
  } catch (e) {
    notify(e instanceof Error ? e.message : "Could not load customers.", "error");
  } finally {
    loading.value = false;
  }
}
function notify(s: string, t: "success" | "error" = "success") {
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
    phone: c.phone ?? "",
    email: c.email ?? "",
    company_name: c.company_name ?? "",
    address_line: c.address_line ?? "",
    area: c.area ?? "",
    district: c.district ?? "",
    division: c.division ?? "",
    postal_code: c.postal_code ?? "",
    notes: c.notes ?? "",
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
      typeof v === "string" ? v.trim() || null : v,
    ])
  );
  try {
    const r = await fetch(
      editing.value ? `/api/customers/${editing.value.id}` : "/api/customers",
      {
        method: editing.value ? "PUT" : "POST",
        headers: headers(),
        credentials: "same-origin",
        body: JSON.stringify(payload),
      }
    );
    if (!r.ok) throw new Error(await errorText(r, "Could not save customer."));
    const p = (await r.json()) as Api<Customer>;
    showForm.value = false;
    notify(p.message ?? "Customer saved.");
    await load();
  } catch (e) {
    notify(e instanceof Error ? e.message : "Could not save customer.", "error");
  } finally {
    saving.value = false;
  }
}
async function showCustomer(c: Customer) {
  try {
    const r = await fetch(`/api/customers/${c.id}`, {
      credentials: "same-origin",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    if (!r.ok) throw new Error(await errorText(r, "Could not load customer profile."));
    detail.value = ((await r.json()) as Api<Record<string, any>>).data ?? null;
  } catch (e) {
    notify(e instanceof Error ? e.message : "Could not load customer profile.", "error");
  }
}
async function removeCustomer() {
  if (!deleteTarget.value) return;
  try {
    const r = await fetch(`/api/customers/${deleteTarget.value.id}`, {
      method: "DELETE",
      headers: headers(),
      credentials: "same-origin",
    });
    if (!r.ok) throw new Error(await errorText(r, "Could not delete customer."));
    deleteTarget.value = null;
    notify("Customer deleted.");
    await load();
  } catch (e) {
    notify(e instanceof Error ? e.message : "Could not delete customer.", "error");
  }
}
function money(v: string | number) {
  return new Intl.NumberFormat("en-BD", {
    style: "currency",
    currency: "BDT",
    maximumFractionDigits: 2,
  }).format(Number(v) || 0);
}
function date(v: string) {
  return v
    ? new Date(v).toLocaleDateString("en-BD", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      })
    : "—";
}
const initials = (c: Customer) =>
  c.name
    .split(/\s+/)
    .slice(0, 2)
    .map((x) => x[0] ?? "")
    .join("")
    .toUpperCase();
onMounted(load);
</script>
<template>
  <Head title="Customers" /><ErpLayout
    :user-name="props.userName"
    :user-email="props.userEmail"
    ><main class="customers-page">
      <header class="page-head">
        <div>
          <div class="crumb">
            Sales <Icon name="chevron-right" :size="12" /> <b>Customers</b>
          </div>
          <div class="title-line">
            <div class="hero-icon"><Icon name="users" :size="23" /></div>
            <div>
              <h1>Customers Management</h1>
              <p>Manage customer profiles, contact details and order relationships.</p>
            </div>
          </div>
        </div>
        <button class="btn primary" @click="openCreate">
          <Icon name="user-plus" :size="15" /> Add customer
        </button>
      </header>
      <div v-if="notice" class="notice" :class="noticeType" role="status">
        <Icon
          :name="noticeType === 'success' ? 'check-circle' : 'alert-circle'"
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
            ><span class="metric-icon blue"><Icon name="users" :size="19" /></span>
          </div>
          <strong>{{ stats.total }}</strong
          ><small>All customer profiles</small>
        </article>
        <article>
          <div class="metric-top">
            <span>Active customers</span
            ><span class="metric-icon teal"><Icon name="check-circle" :size="19" /></span>
          </div>
          <strong>{{ stats.active }}</strong
          ><small>Available for new orders</small>
        </article>
        <article>
          <div class="metric-top">
            <span>With phone number</span
            ><span class="metric-icon green"><Icon name="phone" :size="19" /></span>
          </div>
          <strong>{{ stats.with_phone }}</strong
          ><small>Contact-ready profiles</small>
        </article>
      </section>
      <section class="panel">
        <header class="panel-head">
          <div>
            <h2>Customer register</h2>
            <p>Search by customer name, code, phone number or email.</p>
          </div>
          <span class="record-count">{{ total }} matching records</span>
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
              <tr v-for="c in customers" :key="c.id">
                <td>
                  <button class="customer-cell" @click="showCustomer(c)">
                    <span class="avatar">{{ initials(c) }}</span
                    ><span
                      ><b>{{ c.name }}</b
                      ><small
                        >{{ c.customer_code
                        }}<template v-if="c.company_name">
                          · {{ c.company_name }}</template
                        ></small
                      ></span
                    >
                  </button>
                </td>
                <td>
                  <b class="contact">{{ c.phone || "No phone" }}</b
                  ><small class="sub">{{ c.email || "No email" }}</small>
                </td>
                <td>
                  <span>{{
                    [c.area, c.district, c.division].filter(Boolean).join(", ") || "—"
                  }}</span>
                </td>
                <td>
                  <span class="count">{{ c.orders_count ?? 0 }}</span>
                </td>
                <td>
                  <span class="status" :class="c.status">{{ c.status }}</span>
                </td>
                <td>{{ date(c.created_at) }}</td>
                <td>
                  <div class="row-actions">
                    <button
                      class="icon-btn"
                      title="View profile"
                      @click="showCustomer(c)"
                    >
                      <Icon name="eye" :size="15" /></button
                    ><button class="icon-btn" title="Edit customer" @click="openEdit(c)">
                      <Icon name="pencil" :size="15" /></button
                    ><button
                      class="icon-btn danger"
                      title="Delete customer"
                      @click="deleteTarget = c"
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
          <span>Showing {{ customers.length }} of {{ total }} customers</span>
          <div>
            <button
              class="btn"
              :disabled="page <= 1 || loading"
              @click="
                page--;
                load();
              "
            >
              <Icon name="chevron-left" :size="14" /> Previous</button
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
        <div v-if="showForm" class="backdrop" @click.self="showForm = false">
          <section
            class="modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="customer-form-title"
          >
            <header class="modal-head">
              <div class="modal-title-wrap">
                <span class="modal-title-icon"
                  ><Icon
                    :name="editing ? 'user-round-pen' : 'user-round-plus'"
                    :size="21"
                /></span>
                <div>
                  <div class="eyebrow">
                    {{ editing ? "CUSTOMER PROFILE" : "NEW CUSTOMER" }}
                  </div>
                  <h2 id="customer-form-title">
                    {{ editing ? "Edit customer" : "Add customer" }}
                  </h2>
                  <p>Keep customer, contact and delivery information accurate.</p>
                </div>
              </div>
              <button class="icon-btn" @click="showForm = false" aria-label="Close">
                <Icon name="x" :size="17" />
              </button>
            </header>
            <form @submit.prevent="save">
              <div class="modal-body">
                <section class="form-section">
                  <div class="form-section-title">
                    <span class="section-icon"
                      ><Icon name="user-round" :size="16"
                    /></span>
                    <div>
                      <h3>Basic information</h3>
                      <p>Identify the customer and preferred contact details.</p>
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
                      /><small v-if="errors.name" class="field-error">{{
                        errors.name
                      }}</small></label
                    >
                    <label class="field"
                      ><span>Phone number</span
                      ><input
                        v-model="form.phone"
                        maxlength="40"
                        inputmode="tel"
                        autocomplete="tel"
                        placeholder="01XXXXXXXXX or +880…"
                      /><small>Use a number customers can be reached on.</small
                      ><small v-if="errors.phone" class="field-error">{{
                        errors.phone
                      }}</small></label
                    >
                    <label class="field"
                      ><span>Email address</span
                      ><input
                        v-model="form.email"
                        type="email"
                        maxlength="190"
                        autocomplete="email"
                        placeholder="name@example.com"
                      /><small v-if="errors.email" class="field-error">{{
                        errors.email
                      }}</small></label
                    >
                    <label class="field"
                      ><span>Company / business</span
                      ><input
                        v-model="form.company_name"
                        maxlength="160"
                        autocomplete="organization"
                        placeholder="Optional"
                      /><small v-if="errors.company_name" class="field-error">{{
                        errors.company_name
                      }}</small></label
                    >
                  </div>
                </section>
                <section class="form-section">
                  <div class="form-section-title">
                    <span class="section-icon"><Icon name="map-pin" :size="16" /></span>
                    <div>
                      <h3>Address & delivery</h3>
                      <p>Location details help your team coordinate deliveries.</p>
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
                      /><small v-if="errors.address_line" class="field-error">{{
                        errors.address_line
                      }}</small></label
                    >
                    <label class="field"
                      ><span>Division / বিভাগ</span
                      ><input
                        v-model="form.division"
                        list="customer-bd-divisions"
                        autocomplete="address-level1"
                        placeholder="Choose or type division"
                        @change="changeDivision(form.division ?? '')"
                      />
                      <datalist id="customer-bd-divisions">
                        <option
                          v-for="item in bdDivisions"
                          :key="item.id"
                          :value="item.name"
                        >
                          {{ item.bn_name ? ` · ${item.bn_name}` : "" }}
                        </option>
                      </datalist>
                    </label>
                    <label class="field"
                      ><span>District / জেলা</span
                      ><input
                        v-model="form.district"
                        list="customer-bd-districts"
                        autocomplete="address-level2"
                        placeholder="Choose or type district"
                        @change="changeDistrict(form.district ?? '')"
                      />
                      <datalist id="customer-bd-districts">
                        <option
                          v-for="item in availableDistricts"
                          :key="item.id"
                          :value="item.name"
                        >
                          {{ item.bn_name ? ` · ${item.bn_name}` : "" }}
                        </option>
                      </datalist>
                      <small v-if="errors.district" class="field-error">{{
                        errors.district
                      }}</small></label
                    >
                    <label class="field"
                      ><span>Upazila / Thana / উপজেলা-থানা</span
                      ><input
                        v-model="form.area"
                        list="customer-bd-upazilas"
                        autocomplete="address-level3"
                        placeholder="Choose or type upazila / thana"
                        @change="changeUpazila(form.area ?? '')"
                      />
                      <datalist id="customer-bd-upazilas">
                        <option
                          v-for="item in availableUpazilas"
                          :key="item.id"
                          :value="item.name"
                        >
                          {{ item.bn_name ? ` · ${item.bn_name}` : "" }}
                        </option>
                      </datalist>
                      <small
                        >Choose a suggestion or type a locality not listed.</small
                      ></label
                    >
                    <label class="field"
                      ><span>Postcode / পোস্ট কোড</span
                      ><input
                        v-model="form.postal_code"
                        list="customer-bd-postcodes"
                        maxlength="20"
                        inputmode="numeric"
                        autocomplete="postal-code"
                        placeholder="Choose or type postcode"
                      />
                      <datalist id="customer-bd-postcodes">
                        <option
                          v-for="(item, index) in availablePostcodes"
                          :key="`${item.postCode}-${item.postOffice}-${index}`"
                          :value="String(item.postCode)"
                        >
                          {{ item.postOffice }} ({{ item.upazila }})
                        </option>
                      </datalist>
                      <small v-if="errors.postal_code" class="field-error">{{
                        errors.postal_code
                      }}</small></label
                    >
                  </div>
                </section>
                <section class="form-section">
                  <div class="form-section-title">
                    <span class="section-icon"
                      ><Icon name="settings-2" :size="16"
                    /></span>
                    <div>
                      <h3>Account settings & notes</h3>
                      <p>Set account availability and keep useful internal context.</p>
                    </div>
                  </div>
                  <div class="form-grid">
                    <label class="field"
                      ><span>Customer status</span
                      ><select v-model="form.status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
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
                        >Visible to your team; use this for relevant service or delivery
                        notes.</small
                      ><small v-if="errors.notes" class="field-error">{{
                        errors.notes
                      }}</small></label
                    >
                  </div>
                </section>
              </div>
              <footer class="modal-foot">
                <button class="btn" type="button" @click="showForm = false">Cancel</button
                ><button class="btn primary" type="submit" :disabled="saving">
                  <Icon name="check" :size="15" />
                  {{ saving ? "Saving…" : editing ? "Save changes" : "Create customer" }}
                </button>
              </footer>
            </form>
          </section>
        </div>
        <div v-if="detail" class="backdrop" @click.self="detail = null">
          <section class="modal profile" role="dialog" aria-modal="true">
            <header class="modal-head">
              <div class="modal-title-wrap">
                <span class="modal-title-icon"
                  ><Icon name="contact-round" :size="21"
                /></span>
                <div>
                  <div class="eyebrow">CUSTOMER DETAILS</div>
                  <h2>Customer profile</h2>
                  <p>{{ detail.customer_code }}</p>
                </div>
              </div>
              <button class="icon-btn" @click="detail = null" aria-label="Close">
                <Icon name="x" :size="17" />
              </button>
            </header>
            <div class="modal-body">
              <div class="profile-hero">
                <span
                  class="avatar large"
                  >{{String(detail.name??'C').split(/\s+/).slice(0,2).map((x:string)=>x[0]).join('').toUpperCase()}}</span
                >
                <div>
                  <h3>{{ detail.name }}</h3>
                  <span class="status" :class="detail.status">{{ detail.status }}</span>
                </div>
                <strong>{{ money(detail.lifetime_value) }}</strong
                ><small>Lifetime order value</small>
              </div>
              <div class="profile-grid">
                <div>
                  <small>Phone</small><b>{{ detail.phone || "—" }}</b>
                </div>
                <div>
                  <small>Email</small><b>{{ detail.email || "—" }}</b>
                </div>
                <div>
                  <small>Company</small><b>{{ detail.company_name || "—" }}</b>
                </div>
                <div>
                  <small>Address</small
                  ><b>{{
                    [
                      detail.address_line,
                      detail.area,
                      detail.district,
                      detail.division,
                      detail.postal_code,
                    ]
                      .filter(Boolean)
                      .join(", ") || "—"
                  }}</b>
                </div>
              </div>
              <h3 class="history-title">Recent orders</h3>
              <div v-if="!detail.orders?.length" class="empty small-empty">
                No orders linked yet. Existing historical orders remain unlinked until
                reviewed.
              </div>
              <div v-else class="order-list">
                <div v-for="o in detail.orders" :key="o.id">
                  <span
                    ><b>{{ o.order_number }}</b
                    ><small>{{ date(o.created_at) }}</small></span
                  ><span class="status" :class="o.status">{{ o.status }}</span
                  ><strong>{{ money(o.grand_total) }}</strong>
                </div>
              </div>
            </div>
            <footer class="modal-foot">
              <button class="btn" @click="detail = null">Close</button
              ><button
                class="btn primary"
                @click="openEdit(detail as Customer);detail=null"
              >
                <Icon name="pencil" :size="14" /> Edit customer
              </button>
            </footer>
          </section>
        </div>
      </Teleport>
      <Teleport to="body">
        <div v-if="deleteTarget" class="backdrop" @click.self="deleteTarget = null">
          <section class="confirm" role="alertdialog" aria-modal="true">
            <div class="delete-icon"><Icon name="trash-2" :size="22" /></div>
            <h2>Delete customer?</h2>
            <p>
              Delete {{ deleteTarget.name }} only if there is no linked order history.
              Customers with orders must be marked inactive instead.
            </p>
            <footer class="modal-foot">
              <button class="btn" @click="deleteTarget = null">Cancel</button
              ><button class="btn danger-solid" @click="removeCustomer">
                <Icon name="trash-2" :size="14" /> Delete customer
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
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
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
  transition: color 0.2s ease, border-color 0.2s ease, background 0.2s ease,
    transform 0.2s ease, box-shadow 0.2s ease;
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
</style>
