<script setup lang="ts">
import { computed, defineComponent, h, onMounted, ref, type PropType } from "vue";
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
    metadata?: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
}

interface ApiPayload<T> {
    data?: T;
    message?: string;
}

const props = withDefaults(defineProps<Props>(), {
    userName: "",
    userEmail: "",
});

const location = ref<Location | null>(null);
const isLoading = ref(true);
const errorMessage = ref("");

const locationId = Number(
    window.location.pathname.match(/\/locations\/(\d+)/)?.[1] ?? 0,
);

const locationTypeLabels: Record<string, string> = {
    head_office: "Head Office",
    branch: "Branch Office",
    warehouse: "Warehouse",
    distribution_hub: "Distribution Hub",
    service_center: "Service Center",
    factory: "Factory",
    store: "Store",
    other: "Other",
};

const locationIcon = defineComponent({
    name: "LocationTypeIcon",

    props: {
        type: {
            type: String,
            required: true,
        },
        size: {
            type: Number,
            default: 20,
        },
    },

    setup(iconProps) {
        return () => {
            const common = {
                size: iconProps.size,
                strokeWidth: 1.8,
            };

            if (iconProps.type === "head_office") {
                return h(Building2, common);
            }

            if (iconProps.type === "warehouse") {
                return h(ShelvingUnit, common);
            }

            if (iconProps.type === "distribution_hub") {
                return h(MapPinHouse, common);
            }

            if (iconProps.type === "service_center") {
                return h(
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
                            ...common,
                            style: {
                                position: "absolute",
                                inset: "0",
                            },
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
            }

            if (iconProps.type === "factory") {
                return h(Factory, common);
            }

            return h(Icon, {
                name: "map-pin",
                size: iconProps.size,
            });
        };
    },
});

const LocationTypeIcon = locationIcon;

const typeLabel = computed(() =>
    location.value
        ? locationTypeLabels[location.value.type] ?? location.value.type
        : "Location",
);

const address = computed(() => {
    if (!location.value) {
        return "—";
    }

    return [
        location.value.address,
        location.value.district,
        location.value.division,
        location.value.postal_code,
        location.value.country_code,
    ]
        .filter(Boolean)
        .join(", ");
});

const scheduleDays = [
    ["monday", "Monday"],
    ["tuesday", "Tuesday"],
    ["wednesday", "Wednesday"],
    ["thursday", "Thursday"],
    ["friday", "Friday"],
    ["saturday", "Saturday"],
    ["sunday", "Sunday"],
] as const;

function formatDate(value?: string): string {
    if (!value) {
        return "—";
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat("en-BD", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(date);
}

async function loadLocation(): Promise<void> {
    if (!locationId) {
        errorMessage.value = "Invalid location identifier.";
        isLoading.value = false;
        return;
    }

    isLoading.value = true;
    errorMessage.value = "";

    try {
        const response = await fetch(`/api/locations/${locationId}`, {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
        });

        const payload = (await response.json()) as ApiPayload<Location>;

        if (!response.ok || !payload.data) {
            throw new Error(
                payload.message ??
                    `Unable to load location. HTTP ${response.status}.`,
            );
        }

        location.value = payload.data;
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : "Unable to load location. Please try again.";
    } finally {
        isLoading.value = false;
    }
}

function back(): void {
    router.get("/locations");
}

function edit(): void {
    if (location.value) {
        router.get(`/locations/${location.value.id}/edit`);
    }
}

onMounted(() => {
    void loadLocation();
});
</script>

<template>
  <Head :title="location ? `${location.name} · Locations` : 'Location'" />

  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <div class="location-show-page">
      <header class="page-header">
        <div>
          <div class="breadcrumb">
            <span>Settings</span>

            <Icon name="chevron-right" :size="12" />

            <button type="button" @click="back">Locations</button>

            <Icon name="chevron-right" :size="12" />

            <strong>View</strong>
          </div>

          <div class="title-row">
            <div class="title-icon" aria-hidden="true">
              <LocationTypeIcon v-if="location" :type="location.type" :size="22" />

              <Icon v-else name="map-pin" :size="22" />
            </div>

            <div>
              <h1>
                {{ location?.name ?? "Location Details" }}
              </h1>

              <p>View complete business location information and operating details.</p>
            </div>
          </div>
        </div>

        <div class="page-actions">
          <button class="action-btn" type="button" @click="back">
            <Icon name="arrow-left" :size="15" />

            Back
          </button>

          <button
            v-if="location"
            type="button"
            class="action-btn action-btn-primary"
            @click="edit"
          >
            <Icon name="edit" :size="15" />

            Edit Location
          </button>
        </div>
      </header>

      <div v-if="isLoading" class="state-card">
        <Icon name="loader" :size="20" />

        <strong>Loading location...</strong>

        <span> Please wait while the location details are loaded. </span>
      </div>

      <div v-else-if="errorMessage" class="state-card error">
        <ShieldX :size="22" :stroke-width="1.8" />

        <strong>Unable to load location</strong>

        <span>{{ errorMessage }}</span>

        <button class="action-btn action-btn-primary" type="button" @click="loadLocation">
          Try Again
        </button>
      </div>

      <template v-else-if="location">
        <section class="hero-card">
          <div class="hero-icon">
            <LocationTypeIcon :type="location.type" :size="30" />
          </div>

          <div class="hero-copy">
            <div class="hero-title-row">
              <h2>{{ location.name }}</h2>

              <span :class="['status-badge', location.is_active ? 'active' : 'inactive']">
                <Icon :name="location.is_active ? 'check' : 'close'" :size="11" />

                {{ location.is_active ? "Active" : "Inactive" }}
              </span>

              <span v-if="location.is_primary" class="primary-badge">
                <Icon name="star" :size="11" />

                Primary
              </span>
            </div>

            <p>{{ typeLabel }} · {{ location.code }}</p>
          </div>
        </section>

        <div class="content-grid">
          <section class="content-card">
            <div class="card-heading">
              <div class="heading-icon">
                <Icon name="map-pin" :size="16" />
              </div>

              <div>
                <h3>Location Identity</h3>

                <p>Core identification and operational status.</p>
              </div>
            </div>

            <div class="detail-grid">
              <div class="detail-item">
                <span>Location Name</span>

                <strong>{{ location.name }}</strong>
              </div>

              <div class="detail-item">
                <span>Location Code</span>

                <strong>{{ location.code }}</strong>
              </div>

              <div class="detail-item">
                <span>Location Type</span>

                <strong>{{ typeLabel }}</strong>
              </div>

              <div class="detail-item">
                <span>Status</span>

                <strong>
                  {{ location.is_active ? "Active" : "Inactive" }}
                </strong>
              </div>

              <div class="detail-item">
                <span>Primary Location</span>

                <strong>
                  {{ location.is_primary ? "Yes" : "No" }}
                </strong>
              </div>

              <div class="detail-item">
                <span>Country</span>

                <strong>
                  {{ location.country_code || "—" }}
                </strong>
              </div>
            </div>
          </section>

          <section class="content-card">
            <div class="card-heading">
              <div class="heading-icon">
                <Icon name="map" :size="16" />
              </div>

              <div>
                <h3>Address</h3>

                <p>Registered and operational location address.</p>
              </div>
            </div>

            <div class="address-block">
              <strong>
                {{ location.address || "Address not provided" }}
              </strong>

              <span>{{ address }}</span>
            </div>

            <div class="detail-grid compact">
              <div class="detail-item">
                <span>Division / Region</span>

                <strong>
                  {{ location.division || "—" }}
                </strong>
              </div>

              <div class="detail-item">
                <span>District / City</span>

                <strong>
                  {{ location.district || "—" }}
                </strong>
              </div>

              <div class="detail-item">
                <span>Postal Code</span>

                <strong>
                  {{ location.postal_code || "—" }}
                </strong>
              </div>

              <div class="detail-item">
                <span>Country Code</span>

                <strong>
                  {{ location.country_code || "—" }}
                </strong>
              </div>
            </div>
          </section>

          <section class="content-card">
            <div class="card-heading">
              <div class="heading-icon">
                <Icon name="phone" :size="16" />
              </div>

              <div>
                <h3>Contact</h3>

                <p>Primary communication details for this location.</p>
              </div>
            </div>

            <div class="contact-list">
              <div class="contact-row">
                <Icon name="phone" :size="15" />

                <div>
                  <span>Phone</span>

                  <strong>
                    {{ location.phone || "Not provided" }}
                  </strong>
                </div>
              </div>

              <div class="contact-row">
                <Icon name="mail" :size="15" />

                <div>
                  <span>Email</span>

                  <strong>
                    {{ location.email || "Not provided" }}
                  </strong>
                </div>
              </div>
            </div>
          </section>

          <section class="content-card">
            <div class="card-heading">
              <div class="heading-icon">
                <Icon name="clock" :size="16" />
              </div>

              <div>
                <h3>Business Hours</h3>

                <p>Configured operating schedule for this location.</p>
              </div>
            </div>

            <div class="hours-list">
              <div v-for="[key, label] in scheduleDays" :key="key" class="hours-row">
                <span>{{ label }}</span>

                <template v-if="location.business_hours?.[key]?.enabled">
                  <strong>
                    {{ location.business_hours[key].open || "—" }}

                    <small>to</small>

                    {{ location.business_hours[key].close || "—" }}
                  </strong>
                </template>

                <strong v-else class="closed"> Closed </strong>
              </div>
            </div>
          </section>

          <section class="content-card metadata-card">
            <div class="card-heading">
              <div class="heading-icon">
                <Icon name="info" :size="16" />
              </div>

              <div>
                <h3>Record Information</h3>

                <p>System audit timestamps for this location.</p>
              </div>
            </div>

            <div class="detail-grid compact">
              <div class="detail-item">
                <span>Created</span>

                <strong>
                  {{ formatDate(location.created_at) }}
                </strong>
              </div>

              <div class="detail-item">
                <span>Last Updated</span>

                <strong>
                  {{ formatDate(location.updated_at) }}
                </strong>
              </div>
            </div>
          </section>
        </div>
      </template>
    </div>
  </ErpLayout>
</template>

<style scoped>
/* ==========================================================================
   LOCATION SHOW PAGE
   Matches Locations Index visual system.
   Outer content has no additional padding.
   ========================================================================== */

.location-show-page {
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
.hero-icon,
.heading-icon {
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
   Same visual system as Locations Index.
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

.action-btn:hover {
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

.action-btn-primary:hover {
  border-color: var(--primary);
  color: #fff;
  box-shadow: 0 12px 26px color-mix(in srgb, var(--primary) 22%, transparent);
}

/* ==========================================================================
   HERO
   ========================================================================== */

.hero-card,
.content-card,
.state-card {
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--surface);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.025);
}

.hero-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  margin-bottom: 14px;
}

.hero-icon {
  width: 48px;
  height: 48px;
  border: 1px solid color-mix(in srgb, var(--primary) 20%, var(--border));
  border-radius: 11px;
  background: color-mix(in srgb, var(--primary) 8%, var(--surface));
  color: var(--primary);
}

.hero-copy {
  min-width: 0;
}

.hero-title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 7px;
}

.hero-title-row h2 {
  margin: 0;
  font-size: 18px;
  line-height: 1.2;
  letter-spacing: -0.015em;
  font-weight: 750;
}

.hero-copy p {
  margin: 5px 0 0;
  color: var(--text-secondary);
  font-size: 10px;
}

.status-badge,
.primary-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 7px;
  border-radius: 999px;
  font-size: 9px;
  font-weight: 700;
}

.status-badge.active {
  background: color-mix(in srgb, var(--success, #16a34a) 12%, transparent);
  color: var(--success, #16a34a);
}

.status-badge.inactive {
  background: color-mix(in srgb, var(--danger, #dc2626) 12%, transparent);
  color: var(--danger, #dc2626);
}

.primary-badge {
  background: color-mix(in srgb, var(--primary) 12%, transparent);
  color: var(--primary);
}

/* ==========================================================================
   CONTENT GRID
   ========================================================================== */

.content-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.content-card {
  padding: 15px;
}

.card-heading {
  display: flex;
  align-items: center;
  gap: 9px;
  padding-bottom: 12px;
  margin-bottom: 12px;
  border-bottom: 1px solid var(--border);
}

.heading-icon {
  width: 30px;
  height: 30px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface-muted, var(--surface));
  color: var(--text-secondary);
}

.card-heading h3 {
  margin: 0;
  font-size: 11px;
  line-height: 1.2;
  font-weight: 700;
}

.card-heading p {
  margin: 3px 0 0;
  color: var(--text-tertiary);
  font-size: 9px;
}

/* ==========================================================================
   DETAILS
   ========================================================================== */

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 16px;
}

.detail-grid.compact {
  gap: 10px 16px;
}

.detail-item {
  min-width: 0;
}

.detail-item span,
.contact-row span {
  display: block;
  margin-bottom: 4px;
  color: var(--text-tertiary);
  font-size: 9px;
  font-weight: 500;
}

.detail-item strong,
.contact-row strong {
  display: block;
  color: var(--text-primary);
  font-size: 10px;
  line-height: 1.4;
  font-weight: 650;
  overflow-wrap: anywhere;
}

.address-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px;
  margin-bottom: 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface-muted, var(--surface));
}

.address-block strong {
  font-size: 10px;
  line-height: 1.4;
}

.address-block span {
  color: var(--text-tertiary);
  font-size: 9px;
  line-height: 1.5;
}

/* ==========================================================================
   CONTACT
   ========================================================================== */

.contact-list {
  display: grid;
  gap: 10px;
}

.contact-row {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  color: var(--text-secondary);
}

.contact-row > svg {
  margin-top: 2px;
  flex: 0 0 auto;
}

/* ==========================================================================
   BUSINESS HOURS
   ========================================================================== */

.hours-list {
  display: grid;
}

.hours-row {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  align-items: center;
  min-height: 35px;
  padding: 6px 0;
  border-bottom: 1px solid var(--border);
}

.hours-row:last-child {
  border-bottom: 0;
}

.hours-row span {
  color: var(--text-secondary);
  font-size: 9px;
}

.hours-row strong {
  font-size: 9px;
  font-weight: 650;
}

.hours-row small {
  color: var(--text-tertiary);
  font-size: 8px;
  font-weight: 500;
  margin: 0 4px;
}

.hours-row .closed {
  color: var(--text-tertiary);
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
  color: var(--text-secondary);
  text-align: center;
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

.state-card.error {
  color: var(--danger, #dc2626);
}

/* ==========================================================================
   RESPONSIVE
   ========================================================================== */

@media (max-width: 800px) {
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

  .content-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 560px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .hero-card {
    padding: 14px;
  }

  .content-card {
    padding: 13px;
  }
}
</style>
