<script setup lang="ts">
import { computed, defineComponent, h, onMounted, reactive, ref, type Component } from "vue";
import { Head, router } from "@inertiajs/vue3";
import {
  Building2,
  Cog,
  Factory,
  HouseIcon,
  MapPinHouse,
  ShieldX,
  ShelvingUnit,
} from "lucide-vue-next";

import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Props {
  userName?: string;
  userEmail?: string;
}

interface BusinessHoursDay {
  enabled: boolean;
  open: string | null;
  close: string | null;
}

type BusinessHours = Record<string, BusinessHoursDay>;

interface Location {
  id: number;
  name: string;
  code: string;
  type: string;
  is_active: boolean;
  is_primary: boolean;
  country_code: string;
  division: string | null;
  district: string | null;
  postal_code: string | null;
  address: string | null;
  phone: string | null;
  email: string | null;
  business_hours: BusinessHours | null;
}

interface ApiPayload<T> {
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
}

const props = withDefaults(defineProps<Props>(), {
  userName: "",
  userEmail: "",
});

const locationId = Number(window.location.pathname.match(/\/locations\/(\d+)\/edit/)?.[1] ?? 0);

const locationTypes = [
  { value: "head_office", label: "Head Office", icon: "building-complex" },
  { value: "branch", label: "Branch Office", icon: "map-pin-house" },
  { value: "warehouse", label: "Warehouse", icon: "shelving-unit" },
  { value: "distribution_hub", label: "Distribution Hub", icon: "map-pin-house" },
  { value: "service_center", label: "Service Center", icon: "house-cog" },
  { value: "factory", label: "Factory", icon: "factory" },
  { value: "other", label: "Other", icon: "map-pin" },
] as const;

const divisions = [
  "Dhaka",
  "Chattogram",
  "Rajshahi",
  "Khulna",
  "Barishal",
  "Sylhet",
  "Rangpur",
  "Mymensingh",
];

const districtsByDivision: Record<string, string[]> = {
  Dhaka: ["Dhaka","Faridpur","Gazipur","Gopalganj","Kishoreganj","Madaripur","Manikganj","Munshiganj","Narayanganj","Narsingdi","Rajbari","Shariatpur","Tangail"],
  Chattogram: ["Bandarban","Brahmanbaria","Chandpur","Chattogram","Cumilla","Cox's Bazar","Feni","Khagrachhari","Lakshmipur","Noakhali","Rangamati"],
  Rajshahi: ["Bogura","Joypurhat","Naogaon","Natore","Chapainawabganj","Pabna","Rajshahi","Sirajganj"],
  Khulna: ["Bagerhat","Chuadanga","Jashore","Jhenaidah","Khulna","Kushtia","Magura","Meherpur","Narail","Satkhira"],
  Barishal: ["Barguna","Barishal","Bhola","Jhalokathi","Patuakhali","Pirojpur"],
  Sylhet: ["Habiganj","Moulvibazar","Sunamganj","Sylhet"],
  Rangpur: ["Dinajpur","Gaibandha","Kurigram","Lalmonirhat","Nilphamari","Panchagarh","Rangpur","Thakurgaon"],
  Mymensingh: ["Jamalpur","Mymensingh","Netrokona","Sherpur"],
};

const days = [
  { key: "monday", label: "Monday" },
  { key: "tuesday", label: "Tuesday" },
  { key: "wednesday", label: "Wednesday" },
  { key: "thursday", label: "Thursday" },
  { key: "friday", label: "Friday" },
  { key: "saturday", label: "Saturday" },
  { key: "sunday", label: "Sunday" },
] as const;

interface LocationForm {
  name: string;
  code: string;
  type: string;
  is_active: boolean;
  is_primary: boolean;
  country_code: string;
  division: string;
  district: string;
  postal_code: string;
  address: string;
  phone: string;
  email: string;
  business_hours: BusinessHours;
}

function emptyBusinessHours(): BusinessHours {
  return Object.fromEntries(
    days.map((day) => [day.key, { enabled: true, open: "09:00", close: "18:00" }]),
  ) as BusinessHours;
}

const form = reactive<LocationForm>({
  name: "",
  code: "",
  type: "branch",
  is_active: true,
  is_primary: false,
  country_code: "BD",
  division: "Dhaka",
  district: "Dhaka",
  postal_code: "",
  address: "",
  phone: "",
  email: "",
  business_hours: emptyBusinessHours(),
});

const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref("");
const successMessage = ref("");
const validationErrors = ref<Record<string, string>>({});

const districts = computed(() => districtsByDivision[form.division] ?? []);

function clearMessages(): void {
  errorMessage.value = "";
  successMessage.value = "";
}

function setError(key: string, message: string): void {
  if (!validationErrors.value[key]) validationErrors.value[key] = message;
}

function validateForm(): boolean {
  validationErrors.value = {};

  if (!form.name.trim()) setError("name", "Location name is required.");
  if (!form.code.trim()) setError("code", "Location code is required.");
  else if (!/^[A-Za-z0-9][A-Za-z0-9_-]*$/.test(form.code.trim())) {
    setError("code", "Use letters, numbers, hyphens or underscores only.");
  }

  if (!form.country_code.trim()) setError("country_code", "Country is required.");

  if (
    form.country_code.toUpperCase() === "BD" &&
    form.postal_code &&
    !/^\d{4}$/.test(form.postal_code.trim())
  ) {
    setError("postal_code", "Bangladesh postal code must contain exactly 4 digits.");
  }

  if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    setError("email", "Enter a valid email address.");
  }

  for (const day of days) {
    const schedule = form.business_hours[day.key];
    if (!schedule?.enabled) continue;

    if (!schedule.open || !schedule.close) {
      setError(`business_hours.${day.key}`, "Opening and closing times are required.");
    } else if (schedule.close <= schedule.open) {
      setError(`business_hours.${day.key}`, "Closing time must be later than opening time.");
    }
  }

  return Object.keys(validationErrors.value).length === 0;
}

function handleDivisionChange(): void {
  const available = districtsByDivision[form.division] ?? [];
  if (!available.includes(form.district)) form.district = available[0] ?? "";
}

function iconComponent(icon: string): Component {
  if (icon === "building-complex") return Building2;
  if (icon === "shelving-unit") return ShelvingUnit;
  if (icon === "map-pin-house") return MapPinHouse;
  if (icon === "factory") return Factory;
  return Icon;
}

const ServiceCenterIcon = defineComponent({
  name: "ServiceCenterIcon",
  props: {
    size: { type: Number, default: 18 },
  },
  setup(iconProps) {
    return () =>
      h(
        "span",
        {
          style: {
            position: "relative",
            display: "inline-flex",
            width: `${iconProps.size}px`,
            height: `${iconProps.size}px`,
            flex: "0 0 auto",
          },
          "aria-hidden": "true",
        },
        [
          h(HouseIcon, {
            size: iconProps.size,
            strokeWidth: 1.8,
          }),
          h(Cog, {
            size: Math.max(9, iconProps.size * 0.55),
            strokeWidth: 1.8,
            style: {
              position: "absolute",
              right: "-1px",
              bottom: "-1px",
            },
          }),
        ],
      );
  },
});

function isServiceCenter(icon: string): boolean {
  return icon === "house-cog";
}

function typeIcon(type: string): string {
  return locationTypes.find((item) => item.value === type)?.icon ?? "map-pin";
}

function countryLabel(code: string): string {
  const labels: Record<string, string> = {
    BD: "Bangladesh",
    IN: "India",
    AE: "United Arab Emirates",
    SA: "Saudi Arabia",
    SG: "Singapore",
    MY: "Malaysia",
    GB: "United Kingdom",
    US: "United States",
  };
  return labels[code] ?? code;
}

async function loadLocation(): Promise<void> {
  if (!locationId) {
    errorMessage.value = "Invalid location identifier.";
    isLoading.value = false;
    return;
  }

  try {
    const response = await fetch(`/api/locations/${locationId}`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      credentials: "same-origin",
    });

    const payload = (await response.json()) as ApiPayload<Location>;

    if (!response.ok || !payload.data) {
      throw new Error(payload.message ?? `Unable to load location. HTTP ${response.status}.`);
    }

    const item = payload.data;

    Object.assign(form, {
      name: item.name ?? "",
      code: item.code ?? "",
      type: item.type ?? "branch",
      is_active: Boolean(item.is_active),
      is_primary: Boolean(item.is_primary),
      country_code: item.country_code ?? "BD",
      division: item.division ?? "Dhaka",
      district: item.district ?? "Dhaka",
      postal_code: item.postal_code ?? "",
      address: item.address ?? "",
      phone: item.phone ?? "",
      email: item.email ?? "",
      business_hours: item.business_hours ?? emptyBusinessHours(),
    });
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Unable to load location. Please try again.";
  } finally {
    isLoading.value = false;
  }
}

async function save(): Promise<void> {
  if (isSaving.value || !validateForm()) {
    if (!validateForm()) errorMessage.value = "Please review the highlighted fields.";
    return;
  }

  isSaving.value = true;
  clearMessages();

  try {
    const response = await fetch(`/api/locations/${locationId}`, {
      method: "PUT",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-TOKEN":
          document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "",
      },
      credentials: "same-origin",
      body: JSON.stringify({
        name: form.name.trim(),
        code: form.code.trim().toUpperCase(),
        type: form.type,
        is_active: form.is_active,
        is_primary: form.is_primary,
        country_code: form.country_code.trim().toUpperCase(),
        division: form.division.trim() || null,
        district: form.district.trim() || null,
        postal_code: form.postal_code.trim() || null,
        address: form.address.trim() || null,
        phone: form.phone.trim() || null,
        email: form.email.trim() || null,
        business_hours: form.business_hours,
      }),
    });

    const payload = (await response.json()) as ApiPayload<Location>;

    if (!response.ok) {
      if (payload.errors) {
        validationErrors.value = Object.fromEntries(
          Object.entries(payload.errors).map(([key, messages]) => [key, messages[0] ?? "Invalid value."]),
        );
      }
      throw new Error(payload.message ?? `Unable to update location. HTTP ${response.status}.`);
    }

    router.get(`/locations/${locationId}`);
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Unable to update location. Please try again.";
  } finally {
    isSaving.value = false;
  }
}

function cancel(): void {
  router.get(`/locations/${locationId}`);
}

onMounted(() => {
  void loadLocation();
});
</script>

<template>
  <Head title="Edit Location" />

  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <div class="location-edit-page">
      <header class="page-header">
        <div>
          <div class="breadcrumb">
            <span>Settings</span>
            <Icon name="chevron-right" :size="12" />
            <button type="button" @click="cancel">Locations</button>
            <Icon name="chevron-right" :size="12" />
            <button type="button" @click="cancel">Location</button>
            <Icon name="chevron-right" :size="12" />
            <strong>Edit</strong>
          </div>

          <div class="title-row">
            <div class="title-icon"><Icon name="edit" :size="21" /></div>
            <div>
              <h1>Edit Location</h1>
              <p>
                Update the location profile, address, contact details and operating
                schedule.
              </p>
            </div>
          </div>
        </div>

        <button class="action-btn" type="button" @click="cancel">
          <Icon name="arrow-left" :size="15" />
          Cancel
        </button>
      </header>

      <div v-if="isLoading" class="state-card">
        <Icon name="loader" :size="20" />
        <strong>Loading location...</strong>
      </div>

      <div v-else class="form-shell">
        <div v-if="errorMessage" class="message error">
          <ShieldX :size="15" :stroke-width="1.8" />
          <span>{{ errorMessage }}</span>
        </div>

        <div v-if="successMessage" class="message success">
          <Icon name="check" :size="15" />
          <span>{{ successMessage }}</span>
        </div>

        <form @submit.prevent="save">
          <section class="form-card">
            <div class="section-heading">
              <div class="section-icon"><Icon name="map-pin" :size="16" /></div>
              <div>
                <h2>Location Identity</h2>
                <p>Define the core identity and operational status.</p>
              </div>
            </div>

            <div class="form-grid">
              <label class="field field-wide">
                <span>Location Name <b>*</b></span>
                <input
                  v-model="form.name"
                  type="text"
                  maxlength="150"
                  autocomplete="off"
                />
                <small v-if="validationErrors.name">{{ validationErrors.name }}</small>
              </label>

              <label class="field">
                <span>Location Code <b>*</b></span>
                <input
                  v-model="form.code"
                  type="text"
                  maxlength="50"
                  autocomplete="off"
                />
                <small v-if="validationErrors.code">{{ validationErrors.code }}</small>
              </label>

              <div class="field field-wide">
                <span>Location Type <b>*</b></span>
                <div class="type-grid">
                  <button
                    v-for="item in locationTypes"
                    :key="item.value"
                    class="type-option"
                    :class="{ selected: form.type === item.value }"
                    type="button"
                    @click="form.type = item.value"
                  >
                    <ServiceCenterIcon v-if="isServiceCenter(item.icon)" :size="18" />
                    <component
                      :is="iconComponent(item.icon)"
                      v-else
                      :name="item.icon === 'map-pin' ? 'map-pin' : undefined"
                      :size="18"
                      :stroke-width="1.8"
                    />
                    <span>{{ item.label }}</span>
                  </button>
                </div>
              </div>

              <label class="switch-card">
                <span>
                  <strong>Active Location</strong>
                  <small>Allow this location to operate.</small>
                </span>
                <input v-model="form.is_active" type="checkbox" />
              </label>

              <label class="switch-card">
                <span>
                  <strong>Primary Location</strong>
                  <small>Use as the primary business location.</small>
                </span>
                <input v-model="form.is_primary" type="checkbox" />
              </label>
            </div>
          </section>

          <section class="form-card">
            <div class="section-heading">
              <div class="section-icon"><Icon name="map" :size="16" /></div>
              <div>
                <h2>Address & Location</h2>
                <p>Maintain the registered and operational address.</p>
              </div>
            </div>

            <div class="form-grid">
              <label class="field">
                <span>Country <b>*</b></span>
                <select v-model="form.country_code">
                  <option value="BD">Bangladesh</option>
                  <option value="IN">India</option>
                  <option value="AE">United Arab Emirates</option>
                  <option value="SA">Saudi Arabia</option>
                  <option value="SG">Singapore</option>
                  <option value="MY">Malaysia</option>
                  <option value="GB">United Kingdom</option>
                  <option value="US">United States</option>
                </select>
              </label>

              <label class="field">
                <span>Division / Region</span>
                <select v-model="form.division" @change="handleDivisionChange">
                  <option value="">Select division</option>
                  <option v-for="division in divisions" :key="division" :value="division">
                    {{ division }}
                  </option>
                </select>
              </label>

              <label class="field">
                <span>District / City</span>
                <select v-if="districts.length" v-model="form.district">
                  <option value="">Select district</option>
                  <option v-for="district in districts" :key="district" :value="district">
                    {{ district }}
                  </option>
                </select>
                <input v-else v-model="form.district" type="text" maxlength="120" />
              </label>

              <label class="field">
                <span>Postal Code</span>
                <input
                  v-model="form.postal_code"
                  type="text"
                  maxlength="20"
                  inputmode="numeric"
                />
                <small v-if="validationErrors.postal_code">{{
                  validationErrors.postal_code
                }}</small>
              </label>

              <label class="field field-wide">
                <span>Registered / Operating Address</span>
                <textarea v-model="form.address" rows="4" maxlength="5000"></textarea>
              </label>
            </div>
          </section>

          <section class="form-card">
            <div class="section-heading">
              <div class="section-icon"><Icon name="phone" :size="16" /></div>
              <div>
                <h2>Contact Information</h2>
                <p>Primary communication details for this location.</p>
              </div>
            </div>

            <div class="form-grid">
              <label class="field">
                <span>Phone</span>
                <input
                  v-model="form.phone"
                  type="tel"
                  maxlength="30"
                  autocomplete="tel"
                />
              </label>

              <label class="field">
                <span>Email</span>
                <input
                  v-model="form.email"
                  type="email"
                  maxlength="150"
                  autocomplete="email"
                />
                <small v-if="validationErrors.email">{{ validationErrors.email }}</small>
              </label>
            </div>
          </section>

          <section class="form-card">
            <div class="section-heading">
              <div class="section-icon"><Icon name="clock" :size="16" /></div>
              <div>
                <h2>Business Hours</h2>
                <p>Configure the operating schedule for this location.</p>
              </div>
            </div>

            <div class="hours-grid">
              <div v-for="day in days" :key="day.key" class="hours-row">
                <label class="day-switch">
                  <input v-model="form.business_hours[day.key].enabled" type="checkbox" />
                  <span>{{ day.label }}</span>
                </label>

                <input
                  v-model="form.business_hours[day.key].open"
                  type="time"
                  :disabled="!form.business_hours[day.key].enabled"
                />
                <span class="to">to</span>
                <input
                  v-model="form.business_hours[day.key].close"
                  type="time"
                  :disabled="!form.business_hours[day.key].enabled"
                />

                <small v-if="validationErrors[`business_hours.${day.key}`]">
                  {{ validationErrors[`business_hours.${day.key}`] }}
                </small>
              </div>
            </div>
          </section>

          <footer class="form-footer">
            <button class="action-btn" type="button" :disabled="isSaving" @click="cancel">
              Cancel
            </button>
            <button
              class="action-btn action-btn-primary"
              type="submit"
              :disabled="isSaving"
            >
              <Icon v-if="!isSaving" name="check" :size="15" />
              <Icon v-else name="loader" :size="15" />
              {{ isSaving ? "Saving..." : "Save Changes" }}
            </button>
          </footer>
        </form>
      </div>
    </div>
  </ErpLayout>
</template>

<style scoped>
/* ==========================================================================
   LOCATION EDIT PAGE
   Same visual system as Locations Show page.
   No extra outer content padding.
   ========================================================================== */

.location-edit-page {
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  padding: 0;
  margin: 0;
  color: var(--text-primary);
}

/* ==========================================================================
   HEADER
   ========================================================================== */

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  margin: 0 0 22px;
}

.page-header > div:first-child {
  min-width: 0;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-bottom: 9px;
  color: var(--text-tertiary);
  font-size: 10px;
  font-weight: 600;
}

.breadcrumb button {
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--text-tertiary);
  font: inherit;
  cursor: pointer;
}

.breadcrumb button:hover {
  color: var(--text-primary);
}

.breadcrumb strong {
  color: var(--text-secondary);
  font-weight: 700;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.title-icon,
.section-icon {
  display: grid;
  place-items: center;
  flex: 0 0 auto;
}

.title-icon {
  width: 42px;
  height: 42px;
  border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
  border-radius: 12px;
  background: color-mix(in srgb, var(--primary) 10%, var(--surface));
  color: var(--primary);
  box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 9%, transparent);
}

.title-row h1 {
  margin: 0;
  font-size: 24px;
  line-height: 1.15;
  letter-spacing: -0.025em;
  font-weight: 750;
}

.title-row p {
  margin: 5px 0 0;
  color: var(--text-secondary);
  font-size: 12px;
}

.page-actions {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  gap: 8px;
}

/* ==========================================================================
   BUTTONS
   Same visual system as Locations Show page.
   ========================================================================== */

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 38px;
  padding: 0 13px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--surface);
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 650;
  cursor: pointer;
  transition: transform 180ms ease, border-color 180ms ease, background-color 180ms ease,
    color 180ms ease, box-shadow 180ms ease;
}

.action-btn:hover:not(:disabled) {
  border-color: var(--border-strong);
  color: var(--text-primary);
  transform: translateY(-1px);
}

.action-btn:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--primary) 55%, transparent);
  outline-offset: 2px;
}

.action-btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.action-btn-primary {
  border-color: color-mix(in srgb, var(--primary) 80%, var(--border));
  background: linear-gradient(
    135deg,
    color-mix(in srgb, var(--primary) 92%, white),
    var(--primary)
  );
  color: #fff;
  box-shadow: 0 9px 22px color-mix(in srgb, var(--primary) 17%, transparent);
}

.action-btn-primary:hover:not(:disabled) {
  border-color: var(--primary);
  color: #fff;
  box-shadow: 0 12px 26px color-mix(in srgb, var(--primary) 22%, transparent);
}

/* ==========================================================================
   FORM SHELL
   ========================================================================== */

.form-shell {
  width: 100%;
  max-width: none;
  margin: 0;
}

/* ==========================================================================
   MESSAGES
   ========================================================================== */

.message {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 12px;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--surface);
  font-size: 10px;
  line-height: 1.4;
}

.message span {
  flex: 1;
}

.message.error {
  border-color: color-mix(in srgb, var(--danger, #dc2626) 22%, var(--border));
  background: color-mix(in srgb, var(--danger, #dc2626) 7%, var(--surface));
  color: var(--danger, #dc2626);
}

.message.success {
  border-color: color-mix(in srgb, var(--success, #16a34a) 22%, var(--border));
  background: color-mix(in srgb, var(--success, #16a34a) 7%, var(--surface));
  color: var(--success, #16a34a);
}

/* ==========================================================================
   FORM CARDS
   Same card language as Show page.
   ========================================================================== */

.form-card {
  width: 100%;
  box-sizing: border-box;
  padding: 15px;
  margin: 0 0 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--surface);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.025);
}

.form-card:last-of-type {
  margin-bottom: 12px;
}

.section-heading {
  display: flex;
  align-items: center;
  gap: 9px;
  padding-bottom: 12px;
  margin-bottom: 12px;
  border-bottom: 1px solid var(--border);
}

.section-icon {
  width: 30px;
  height: 30px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface-muted, var(--surface));
  color: var(--text-secondary);
}

.section-heading h2 {
  margin: 0;
  font-size: 11px;
  line-height: 1.2;
  font-weight: 700;
}

.section-heading p {
  margin: 3px 0 0;
  color: var(--text-tertiary);
  font-size: 9px;
}

/* ==========================================================================
   FORM GRID
   ========================================================================== */

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 16px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.field-wide {
  grid-column: 1 / -1;
}

.field > span {
  color: var(--text-secondary);
  font-size: 9px;
  line-height: 1.3;
  font-weight: 650;
}

.field b {
  color: var(--danger, #dc2626);
}

.field input,
.field select,
.field textarea,
.hours-row input {
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  color: var(--text-primary);
  padding: 9px 10px;
  outline: none;
  font-family: inherit;
  font-size: 10px;
  font-weight: 500;
  line-height: 1.4;
  transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
}

.field input,
.field select {
  min-height: 36px;
}

.field textarea {
  min-height: 86px;
  resize: vertical;
}

.field input:hover,
.field select:hover,
.field textarea:hover,
.hours-row input:hover {
  border-color: var(--border-strong);
}

.field input:focus,
.field select:focus,
.field textarea:focus,
.hours-row input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 10%, transparent);
}

.field input::placeholder,
.field textarea::placeholder {
  color: var(--text-tertiary);
}

.field small,
.hours-row small {
  color: var(--danger, #dc2626);
  font-size: 9px;
  line-height: 1.4;
}

/* ==========================================================================
   LOCATION TYPE
   ========================================================================== */

.type-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}

.type-option {
  min-height: 68px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 8px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--surface);
  color: var(--text-secondary);
  cursor: pointer;
  font-family: inherit;
  font-size: 9px;
  font-weight: 650;
  line-height: 1.2;
  transition: transform 180ms ease, border-color 180ms ease, background-color 180ms ease,
    color 180ms ease, box-shadow 180ms ease;
}

.type-option:hover {
  border-color: var(--border-strong);
  color: var(--text-primary);
  transform: translateY(-1px);
}

.type-option:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--primary) 55%, transparent);
  outline-offset: 2px;
}

.type-option.selected {
  border-color: color-mix(in srgb, var(--primary) 70%, var(--border));
  background: color-mix(in srgb, var(--primary) 7%, var(--surface));
  color: var(--primary);
  box-shadow: 0 5px 14px color-mix(in srgb, var(--primary) 8%, transparent);
}

/* ==========================================================================
   SWITCH CARDS
   ========================================================================== */

.switch-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  min-height: 54px;
  box-sizing: border-box;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--surface-muted, var(--surface));
}

.switch-card span {
  min-width: 0;
}

.switch-card strong,
.switch-card small {
  display: block;
}

.switch-card strong {
  color: var(--text-primary);
  font-size: 10px;
  line-height: 1.3;
  font-weight: 650;
}

.switch-card small {
  margin-top: 3px;
  color: var(--text-tertiary);
  font-size: 8px;
  line-height: 1.4;
}

.switch-card input,
.day-switch input {
  flex: 0 0 auto;
  accent-color: var(--primary);
}

/* ==========================================================================
   BUSINESS HOURS
   ========================================================================== */

.hours-grid {
  display: grid;
}

.hours-row {
  position: relative;
  display: grid;
  grid-template-columns: minmax(150px, 1fr) 120px 20px 120px;
  align-items: center;
  gap: 8px;
  min-height: 35px;
  padding: 6px 0;
  border-bottom: 1px solid var(--border);
}

.hours-row:last-child {
  border-bottom: 0;
}

.day-switch {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  color: var(--text-secondary);
  font-size: 9px;
  font-weight: 600;
}

.hours-row .to {
  text-align: center;
  color: var(--text-tertiary);
  font-size: 9px;
}

.hours-row small {
  grid-column: 1 / -1;
  margin-top: -2px;
}

/* ==========================================================================
   STATE
   ========================================================================== */

.state-card {
  min-height: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 24px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--surface);
  color: var(--text-secondary);
  text-align: center;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.025);
}

.state-card strong {
  font-size: 11px;
}

.state-card span {
  max-width: 420px;
  color: var(--text-tertiary);
  font-size: 10px;
  line-height: 1.5;
}

/* ==========================================================================
   FORM FOOTER
   ========================================================================== */

.form-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  padding: 2px 0 10px;
}

/* ==========================================================================
   RESPONSIVE
   ========================================================================== */

@media (max-width: 850px) {
  .page-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .page-actions {
    width: 100%;
  }

  .page-actions .action-btn {
    flex: 1;
  }

  .type-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 620px) {
  .form-grid {
    grid-template-columns: 1fr;
  }

  .field-wide {
    grid-column: auto;
  }

  .hours-row {
    grid-template-columns: 1fr 100px 18px 100px;
  }

  .form-card {
    padding: 13px;
  }

  .title-row h1 {
    font-size: 22px;
  }

  .form-footer {
    flex-direction: column-reverse;
    align-items: stretch;
  }

  .form-footer .action-btn {
    width: 100%;
  }
}
</style>
