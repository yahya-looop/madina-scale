<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Head } from "@inertiajs/vue3";

import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Props {
  userName?: string;
  userEmail?: string;
}

type SettingGroup = "general" | "localization" | "currency" | "business" | "system";

type SettingType = "string" | "boolean" | "integer" | "json";

interface SettingDefinition {
  key: string;
  label: string;
  description: string;
  group: SettingGroup;
  type: SettingType;
  icon: string;
}

interface SettingValue {
  key: string;
  value: unknown;
  type: string;
}

interface ApiSettingsResponse {
  data?: Record<string, unknown>;
  message?: string;
}

interface CategoryDefinition {
  id: SettingGroup | "overview";
  label: string;
  description: string;
  icon: string;
}

interface BusinessHour {
  enabled: boolean;
  open: string;
  close: string;
}

type BusinessHours = Record<string, BusinessHour>;

const props = withDefaults(defineProps<Props>(), {
  userName: "",
  userEmail: "",
});

const categories: CategoryDefinition[] = [
  {
    id: "overview",
    label: "Overview",
    description: "Settings health and configuration summary.",
    icon: "activity",
  },
  {
    id: "general",
    label: "General",
    description: "Application identity, contact and support.",
    icon: "settings",
  },
  {
    id: "localization",
    label: "Localization",
    description: "Language, timezone and date/time behavior.",
    icon: "settings",
  },
  {
    id: "currency",
    label: "Currency",
    description: "Currency and number formatting.",
    icon: "settings",
  },
  {
    id: "business",
    label: "Business",
    description: "Business identity and operating information.",
    icon: "settings",
  },
  {
    id: "system",
    label: "System",
    description: "Core platform behavior and operations.",
    icon: "developer",
  },
];

const definitions: SettingDefinition[] = [
  {
    key: "general.site_name",
    label: "Site Name",
    description: "Application name displayed across the platform.",
    group: "general",
    type: "string",
    icon: "settings",
  },
  {
    key: "general.site_description",
    label: "Site Description",
    description: "Short description used to identify the platform.",
    group: "general",
    type: "string",
    icon: "settings",
  },
  {
    key: "general.contact_email",
    label: "Contact Email",
    description: "Primary platform contact email address.",
    group: "general",
    type: "string",
    icon: "settings",
  },
  {
    key: "general.contact_phone",
    label: "Contact Phone",
    description: "Primary platform contact phone number.",
    group: "general",
    type: "string",
    icon: "settings",
  },
  {
    key: "general.support_channels",
    label: "Support Channels",
    description: "Customer support channels available across the platform.",
    group: "general",
    type: "json",
    icon: "activity",
  },

  {
    key: "localization.timezone",
    label: "Timezone",
    description: "Default timezone used for application dates and times.",
    group: "localization",
    type: "string",
    icon: "settings",
  },
  {
    key: "localization.locale",
    label: "Locale",
    description: "Default application language and locale code.",
    group: "localization",
    type: "string",
    icon: "settings",
  },
  {
    key: "localization.date_format",
    label: "Date Format",
    description: "Default format used when displaying calendar dates.",
    group: "localization",
    type: "string",
    icon: "settings",
  },
  {
    key: "localization.time_format",
    label: "Time Format",
    description: "Default 12-hour or 24-hour time display.",
    group: "localization",
    type: "string",
    icon: "settings",
  },
  {
    key: "localization.week_starts_on",
    label: "Week Starts On",
    description: "First day used by calendars and weekly views.",
    group: "localization",
    type: "integer",
    icon: "settings",
  },

  {
    key: "currency.default",
    label: "Default Currency",
    description: "Primary currency code used for monetary values.",
    group: "currency",
    type: "string",
    icon: "settings",
  },
  {
    key: "currency.symbol",
    label: "Currency Symbol",
    description: "Symbol displayed alongside monetary amounts.",
    group: "currency",
    type: "string",
    icon: "settings",
  },
  {
    key: "currency.decimal_places",
    label: "Decimal Places",
    description: "Number of decimal places used for monetary values.",
    group: "currency",
    type: "integer",
    icon: "settings",
  },
  {
    key: "currency.thousand_separator",
    label: "Thousands Separator",
    description: "Character used to separate groups of thousands.",
    group: "currency",
    type: "string",
    icon: "settings",
  },
  {
    key: "currency.decimal_separator",
    label: "Decimal Separator",
    description: "Character used to separate the decimal portion.",
    group: "currency",
    type: "string",
    icon: "settings",
  },

  {
    key: "business.name",
    label: "Business Name",
    description: "Primary business name shown throughout the platform.",
    group: "business",
    type: "string",
    icon: "settings",
  },
  {
    key: "business.email",
    label: "Business Email",
    description: "Primary business email address.",
    group: "business",
    type: "string",
    icon: "settings",
  },
  {
    key: "business.phone",
    label: "Business Phone",
    description: "Primary business phone number.",
    group: "business",
    type: "string",
    icon: "settings",
  },
  {
    key: "business.address",
    label: "Business Address",
    description: "Primary registered or operating business address.",
    group: "business",
    type: "string",
    icon: "settings",
  },
  {
    key: "business.business_hours",
    label: "Business Hours",
    description: "Operating schedule used by the business.",
    group: "business",
    type: "json",
    icon: "activity",
  },

  {
    key: "system.maintenance_mode",
    label: "Maintenance Mode",
    description: "Temporarily place the platform into maintenance mode.",
    group: "system",
    type: "boolean",
    icon: "developer",
  },
  {
    key: "system.items_per_page",
    label: "Items Per Page",
    description: "Default number of records displayed in paginated lists.",
    group: "system",
    type: "integer",
    icon: "settings",
  },
  {
    key: "system.support_channels",
    label: "System Support Channels",
    description: "Legacy system-level support channel configuration.",
    group: "system",
    type: "json",
    icon: "activity",
  },
];

const dayDefinitions = [
  { key: "saturday", label: "Saturday" },
  { key: "sunday", label: "Sunday" },
  { key: "monday", label: "Monday" },
  { key: "tuesday", label: "Tuesday" },
  { key: "wednesday", label: "Wednesday" },
  { key: "thursday", label: "Thursday" },
  { key: "friday", label: "Friday" },
];

const supportChannels = ["chat", "email", "phone"];

const settings = ref<Record<string, unknown>>({});
const originalSettings = ref<Record<string, unknown>>({});

const activeCategory = ref<SettingGroup | "overview">("overview");
const isLoading = ref(false);
const isSaving = ref(false);
const operationMessage = ref("");
const operationMessageType = ref<"success" | "error">("success");

const activeCategoryDefinition = computed(() =>
  categories.find((category) => category.id === activeCategory.value)
);

const activeDefinitions = computed(() =>
  definitions.filter((definition) => definition.group === activeCategory.value)
);

const configuredCount = computed(
  () =>
    definitions.filter((definition) =>
      Object.prototype.hasOwnProperty.call(settings.value, definition.key)
    ).length
);

const totalCount = computed(() => definitions.length);

const changedCount = computed(
  () =>
    definitions.filter(
      (definition) =>
        JSON.stringify(settings.value[definition.key]) !==
        JSON.stringify(originalSettings.value[definition.key])
    ).length
);

const hasChanges = computed(() => changedCount.value > 0);

const categoryStats = computed(() =>
  categories
    .filter(
      (category): category is CategoryDefinition & { id: SettingGroup } =>
        category.id !== "overview"
    )
    .map((category) => {
      const categoryDefinitions = definitions.filter(
        (definition) => definition.group === category.id
      );

      const configured = categoryDefinitions.filter((definition) =>
        Object.prototype.hasOwnProperty.call(settings.value, definition.key)
      ).length;

      const changed = categoryDefinitions.filter(
        (definition) =>
          JSON.stringify(settings.value[definition.key]) !==
          JSON.stringify(originalSettings.value[definition.key])
      ).length;

      return {
        ...category,
        total: categoryDefinitions.length,
        configured,
        changed,
      };
    })
);

function displayValue(key: string): unknown {
  return settings.value[key];
}

function setValue(key: string, value: unknown): void {
  settings.value = {
    ...settings.value,
    [key]: value,
  };

  operationMessage.value = "";
}

function stringValue(key: string): string {
  const value = displayValue(key);

  return typeof value === "string" ? value : "";
}

function numberValue(key: string, fallback = 0): number {
  const value = displayValue(key);

  if (typeof value === "number") {
    return Number.isFinite(value) ? value : fallback;
  }

  const parsed = Number(value);

  return Number.isFinite(parsed) ? parsed : fallback;
}

function booleanValue(key: string): boolean {
  return displayValue(key) === true;
}

function arrayValue(key: string): string[] {
  const value = displayValue(key);

  return Array.isArray(value)
    ? value.filter((item): item is string => typeof item === "string")
    : [];
}

function businessHoursValue(): BusinessHours {
  const value = displayValue("business.business_hours");

  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return {};
  }

  return value as BusinessHours;
}

function updateBusinessHour(
  day: string,
  field: keyof BusinessHour,
  value: boolean | string
): void {
  const current = businessHoursValue();

  const next: BusinessHours = {
    ...current,
    [day]: {
      enabled: current[day]?.enabled ?? false,
      open: current[day]?.open ?? "09:00",
      close: current[day]?.close ?? "18:00",
      [field]: value,
    },
  };

  setValue("business.business_hours", next);
}

function businessHour(day: string): BusinessHour {
  const hours = businessHoursValue();

  return (
    hours[day] ?? {
      enabled: false,
      open: "09:00",
      close: "18:00",
    }
  );
}

function toggleSupportChannel(
  key: "general.support_channels" | "system.support_channels",
  channel: string
): void {
  const channels = arrayValue(key);
  const exists = channels.includes(channel);

  const nextChannels = exists
    ? channels.filter((item) => item !== channel)
    : [...channels, channel];

  setValue(key, nextChannels);
}

function formatSettingValue(key: string): string {
  const value = displayValue(key);

  if (Array.isArray(value)) {
    return value.join(", ");
  }

  if (typeof value === "boolean") {
    return value ? "Enabled" : "Disabled";
  }

  if (value === null || value === undefined || value === "") {
    return "Not configured";
  }

  return String(value);
}

function categoryConfigured(group: SettingGroup | "overview"): number {
  if (group === "overview") {
    return 0;
  }

  return definitions.filter(
    (definition) =>
      definition.group === group &&
      Object.prototype.hasOwnProperty.call(settings.value, definition.key)
  ).length;
}

function selectCategory(category: SettingGroup | "overview"): void {
  activeCategory.value = category;
  operationMessage.value = "";
}

async function apiRequest<T>(
  url: string,
  method: "GET" | "PUT",
  body?: unknown
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };

  const csrfToken = document
    .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
    ?.getAttribute("content");

  if (csrfToken) {
    headers["X-CSRF-TOKEN"] = csrfToken;
  }

  if (body !== undefined) {
    headers["Content-Type"] = "application/json";
  }

  const response = await fetch(url, {
    method,
    headers,
    credentials: "same-origin",
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  const responseText = await response.text();
  let payload: unknown = null;

  if (responseText.trim() !== "") {
    try {
      payload = JSON.parse(responseText) as unknown;
    } catch {
      payload = null;
    }
  }

  if (!response.ok) {
    const errorPayload = payload as {
      message?: string;
      errors?: Record<string, string | string[]>;
    } | null;

    const firstValidationError = Object.values(errorPayload?.errors ?? {})[0];

    const message =
      typeof firstValidationError === "string"
        ? firstValidationError
        : Array.isArray(firstValidationError)
        ? firstValidationError[0]
        : errorPayload?.message;

    throw new Error(message || `Request failed with status ${response.status}.`);
  }

  return payload as T;
}

async function fetchSettings(): Promise<void> {
  isLoading.value = true;
  operationMessage.value = "";

  try {
    const response = await apiRequest<ApiSettingsResponse>("/api/settings", "GET");

    const data = response.data ?? {};

    settings.value = { ...data };
    originalSettings.value = { ...data };
  } catch (error) {
    operationMessageType.value = "error";
    operationMessage.value =
      error instanceof Error ? error.message : "Unable to load system settings.";
  } finally {
    isLoading.value = false;
  }
}

function resetChanges(): void {
  settings.value = {
    ...originalSettings.value,
  };

  operationMessage.value = "";
}

function buildChangedSettings(): SettingValue[] {
  return definitions
    .filter((definition) => {
      const current = settings.value[definition.key];
      const original = originalSettings.value[definition.key];

      return JSON.stringify(current) !== JSON.stringify(original);
    })
    .map((definition) => ({
      key: definition.key,
      value: settings.value[definition.key],
      type: definition.type,
    }));
}

async function saveSettings(): Promise<void> {
  if (!hasChanges.value || isSaving.value) {
    return;
  }

  const changedSettings = buildChangedSettings();

  if (changedSettings.length === 0) {
    return;
  }

  isSaving.value = true;
  operationMessage.value = "";

  try {
    const response = await apiRequest<ApiSettingsResponse>("/api/settings", "PUT", {
      settings: changedSettings,
    });

    const refreshedData = response.data ?? {};

    settings.value = {
      ...settings.value,
      ...refreshedData,
    };

    originalSettings.value = {
      ...settings.value,
    };

    operationMessageType.value = "success";
    operationMessage.value =
      changedSettings.length === 1
        ? "Setting updated successfully."
        : `${changedSettings.length} settings updated successfully.`;
  } catch (error) {
    operationMessageType.value = "error";
    operationMessage.value =
      error instanceof Error ? error.message : "Unable to update system settings.";
  } finally {
    isSaving.value = false;
  }
}

onMounted(() => {
  void fetchSettings();
});
</script>

<template>
  <Head title="Settings Center" />

  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <div class="settings-page">
      <header class="page-header">
        <div class="page-heading">
          <div class="breadcrumb">
            <span>Settings</span>
            <span class="breadcrumb-separator">/</span>
            <strong>{{ activeCategoryDefinition?.label }}</strong>
          </div>

          <div class="heading-row">
            <div class="heading-icon">
              <Icon name="settings" :size="17" />
            </div>

            <div>
              <h1>Settings Center</h1>
              <p>
                Configure Madina Scale from one centralized, permission-protected control
                center.
              </p>
            </div>
          </div>
        </div>

        <div class="header-actions">
          <button
            class="btn secondary"
            type="button"
            :disabled="isLoading || isSaving"
            @click="fetchSettings"
          >
            <Icon name="activity" :size="14" />
            <span>{{ isLoading ? "Refreshing..." : "Refresh" }}</span>
          </button>

          <button
            class="btn primary"
            type="button"
            :disabled="!hasChanges || isSaving"
            @click="saveSettings"
          >
            <Icon name="settings" :size="14" />
            <span>{{ isSaving ? "Saving..." : "Save Changes" }}</span>
          </button>
        </div>
      </header>

      <Transition name="message">
        <div
          v-if="operationMessage"
          class="operation-message"
          :class="operationMessageType"
          role="status"
        >
          <span class="message-icon">
            <Icon
              :name="operationMessageType === 'success' ? 'activity' : 'settings'"
              :size="14"
            />
          </span>

          <div>
            <strong>
              {{
                operationMessageType === "success"
                  ? "Settings saved"
                  : "Unable to complete request"
              }}
            </strong>
            <span>{{ operationMessage }}</span>
          </div>
        </div>
      </Transition>

      <section class="stat-grid">
        <article class="stat-card">
          <div class="stat-icon">
            <Icon name="settings" :size="16" />
          </div>
          <div>
            <span>Configured</span>
            <strong>{{ configuredCount }}/{{ totalCount }}</strong>
          </div>
        </article>

        <article class="stat-card">
          <div class="stat-icon">
            <Icon name="activity" :size="16" />
          </div>
          <div>
            <span>Categories</span>
            <strong>{{ categories.length - 1 }}</strong>
          </div>
        </article>

        <article class="stat-card">
          <div class="stat-icon">
            <Icon name="developer" :size="16" />
          </div>
          <div>
            <span>Unsaved</span>
            <strong>{{ changedCount }}</strong>
          </div>
        </article>
      </section>

      <div class="settings-layout">
        <aside class="settings-sidebar">
          <div class="sidebar-heading">
            <span class="section-kicker">CONFIGURATION</span>
            <strong>Settings</strong>
            <small>Choose an area to configure.</small>
          </div>

          <nav class="category-nav" aria-label="Settings categories">
            <button
              v-for="category in categories"
              :key="category.id"
              type="button"
              class="category-item"
              :class="{ active: activeCategory === category.id }"
              @click="selectCategory(category.id)"
            >
              <span class="category-icon">
                <Icon :name="category.icon" :size="14" />
              </span>

              <span class="category-copy">
                <strong>{{ category.label }}</strong>
                <small>{{ category.description }}</small>
              </span>

              <span v-if="category.id !== 'overview'" class="category-count">
                {{ categoryConfigured(category.id) }}
              </span>
            </button>
          </nav>

          <div class="sidebar-security">
            <span class="security-dot"></span>
            <div>
              <strong>Protected</strong>
              <small> Authorization is enforced server-side. </small>
            </div>
          </div>
        </aside>

        <main class="settings-content">
          <section v-if="isLoading" class="settings-card loading-card">
            <div class="loading-indicator">
              <span class="loading-spinner"></span>
              <div>
                <strong>Loading settings</strong>
                <span> Retrieving the latest configuration... </span>
              </div>
            </div>
          </section>

          <template v-else>
            <section v-if="activeCategory === 'overview'" class="overview-panel">
              <div class="content-heading">
                <div>
                  <span class="section-kicker">OVERVIEW</span>
                  <h2>Settings Center</h2>
                  <p>
                    A centralized view of your platform configuration and settings health.
                  </p>
                </div>

                <span class="section-badge"> {{ totalCount }} registered settings </span>
              </div>

              <div class="overview-hero">
                <div class="overview-hero-icon">
                  <Icon name="settings" :size="22" />
                </div>

                <div>
                  <strong>Platform configuration is ready</strong>
                  <span>
                    All active settings are loaded from the server and changes are
                    protected by authorization and activity logging.
                  </span>
                </div>
              </div>

              <div class="category-grid">
                <button
                  v-for="category in categoryStats"
                  :key="category.id"
                  type="button"
                  class="category-card"
                  @click="selectCategory(category.id)"
                >
                  <div class="category-card-top">
                    <span class="category-card-icon">
                      <Icon :name="category.icon" :size="16" />
                    </span>

                    <span v-if="category.changed" class="changed-badge">
                      {{ category.changed }} changed
                    </span>
                  </div>

                  <strong>{{ category.label }}</strong>
                  <span>{{ category.description }}</span>

                  <div class="category-progress">
                    <div class="progress-track">
                      <span
                        :style="{
                          width:
                            category.total > 0
                              ? `${(category.configured / category.total) * 100}%`
                              : '0%',
                        }"
                      ></span>
                    </div>
                    <small>
                      {{ category.configured }}/{{ category.total }}
                      configured
                    </small>
                  </div>
                </button>
              </div>

              <div class="overview-note">
                <Icon name="activity" :size="14" />
                <div>
                  <strong>Operational note</strong>
                  <span>
                    Settings changes are recorded in Activity Logs. Additional areas such
                    as security, email, integrations, SEO and advanced controls can be
                    added without changing this settings architecture.
                  </span>
                </div>
              </div>
            </section>

            <section v-else class="category-panel">
              <div class="content-heading">
                <div>
                  <span class="section-kicker">
                    {{ activeCategoryDefinition?.label?.toUpperCase() }}
                  </span>
                  <h2>
                    {{ activeCategoryDefinition?.label }}
                    Settings
                  </h2>
                  <p>
                    {{ activeCategoryDefinition?.description }}
                  </p>
                </div>

                <span class="section-badge">
                  {{ activeDefinitions.length }} settings
                </span>
              </div>

              <div
                v-if="activeCategory === 'general'"
                class="settings-card setting-group-card"
              >
                <div class="subsection-heading">
                  <div>
                    <strong>Site Identity</strong>
                    <span> Core information displayed across the platform. </span>
                  </div>
                </div>

                <div class="form-grid">
                  <label class="field full">
                    <span>Site Name</span>
                    <small> Application name displayed across the platform. </small>
                    <input
                      :value="stringValue('general.site_name')"
                      type="text"
                      @input="
                                                setValue(
                                                    'general.site_name',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>

                  <label class="field full">
                    <span>Site Description</span>
                    <small> Short description used to identify the platform. </small>
                    <textarea
                      :value="stringValue('general.site_description')"
                      rows="3"
                      @input="
                                                setValue(
                                                    'general.site_description',
                                                    (
                                                        $event.target as HTMLTextAreaElement
                                                    ).value,
                                                )
                                            "
                    ></textarea>
                  </label>
                </div>
              </div>

              <div
                v-if="activeCategory === 'general'"
                class="settings-card setting-group-card"
              >
                <div class="subsection-heading">
                  <div>
                    <strong>Contact Information</strong>
                    <span> Primary platform contact details. </span>
                  </div>
                </div>

                <div class="form-grid">
                  <label class="field">
                    <span>Contact Email</span>
                    <small>Primary contact email.</small>
                    <input
                      :value="stringValue('general.contact_email')"
                      type="email"
                      @input="
                                                setValue(
                                                    'general.contact_email',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>

                  <label class="field">
                    <span>Contact Phone</span>
                    <small>Primary contact phone.</small>
                    <input
                      :value="stringValue('general.contact_phone')"
                      type="text"
                      @input="
                                                setValue(
                                                    'general.contact_phone',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>
                </div>
              </div>

              <div
                v-if="activeCategory === 'general'"
                class="settings-card setting-group-card"
              >
                <div class="subsection-heading">
                  <div>
                    <strong>Support Channels</strong>
                    <span> Select channels currently available to customers. </span>
                  </div>
                </div>

                <div class="channel-control large">
                  <button
                    v-for="channel in supportChannels"
                    :key="channel"
                    type="button"
                    class="channel-chip"
                    :class="{
                      active: arrayValue('general.support_channels').includes(channel),
                    }"
                    @click="toggleSupportChannel('general.support_channels', channel)"
                  >
                    {{ channel.charAt(0).toUpperCase() + channel.slice(1) }}
                  </button>
                </div>
              </div>

              <div
                v-if="activeCategory === 'localization'"
                class="settings-card setting-group-card"
              >
                <div class="subsection-heading">
                  <div>
                    <strong>Language & Time</strong>
                    <span> Configure the application's regional behavior. </span>
                  </div>
                </div>

                <div class="form-grid">
                  <label class="field">
                    <span>Timezone</span>
                    <small> Default application timezone. </small>
                    <input
                      :value="stringValue('localization.timezone')"
                      type="text"
                      placeholder="Asia/Dhaka"
                      @input="
                                                setValue(
                                                    'localization.timezone',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>

                  <label class="field">
                    <span>Locale</span>
                    <small>Default locale code.</small>
                    <input
                      :value="stringValue('localization.locale')"
                      type="text"
                      placeholder="en"
                      @input="
                                                setValue(
                                                    'localization.locale',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>

                  <label class="field">
                    <span>Date Format</span>
                    <small> Default calendar date format. </small>
                    <input
                      :value="stringValue('localization.date_format')"
                      type="text"
                      placeholder="DD/MM/YYYY"
                      @input="
                                                setValue(
                                                    'localization.date_format',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>

                  <label class="field">
                    <span>Time Format</span>
                    <small> 12-hour or 24-hour display. </small>
                    <select
                      :value="stringValue('localization.time_format')"
                      @change="
                                                setValue(
                                                    'localization.time_format',
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                    >
                      <option value="12-hour">12-hour</option>
                      <option value="24-hour">24-hour</option>
                    </select>
                  </label>

                  <label class="field">
                    <span>Week Starts On</span>
                    <small> First day used in weekly views. </small>
                    <select
                      :value="numberValue('localization.week_starts_on', 6)"
                      @change="
                                                setValue(
                                                    'localization.week_starts_on',
                                                    Number(
                                                        (
                                                            $event.target as HTMLSelectElement
                                                        ).value,
                                                    ),
                                                )
                                            "
                    >
                      <option :value="0">Sunday</option>
                      <option :value="1">Monday</option>
                      <option :value="2">Tuesday</option>
                      <option :value="3">Wednesday</option>
                      <option :value="4">Thursday</option>
                      <option :value="5">Friday</option>
                      <option :value="6">Saturday</option>
                    </select>
                  </label>
                </div>
              </div>

              <div
                v-if="activeCategory === 'currency'"
                class="settings-card setting-group-card"
              >
                <div class="subsection-heading">
                  <div>
                    <strong>Currency & Number Formatting</strong>
                    <span> Configure monetary display rules for the platform. </span>
                  </div>
                </div>

                <div class="form-grid">
                  <label class="field">
                    <span>Default Currency</span>
                    <small>ISO currency code.</small>
                    <input
                      :value="stringValue('currency.default')"
                      type="text"
                      maxlength="10"
                      placeholder="BDT"
                      @input="
                                                setValue(
                                                    'currency.default',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value.toUpperCase(),
                                                )
                                            "
                    />
                  </label>

                  <label class="field">
                    <span>Currency Symbol</span>
                    <small>Displayed currency symbol.</small>
                    <input
                      :value="stringValue('currency.symbol')"
                      type="text"
                      maxlength="10"
                      placeholder="৳"
                      @input="
                                                setValue(
                                                    'currency.symbol',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>

                  <label class="field">
                    <span>Decimal Places</span>
                    <small> Digits shown after the decimal. </small>
                    <input
                      :value="numberValue('currency.decimal_places', 2)"
                      type="number"
                      min="0"
                      max="6"
                      step="1"
                      @input="
                                                setValue(
                                                    'currency.decimal_places',
                                                    Number(
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    ),
                                                )
                                            "
                    />
                  </label>

                  <label class="field">
                    <span>Thousands Separator</span>
                    <small> Separator between thousands. </small>
                    <input
                      :value="stringValue('currency.thousand_separator')"
                      type="text"
                      maxlength="3"
                      @input="
                                                setValue(
                                                    'currency.thousand_separator',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>

                  <label class="field">
                    <span>Decimal Separator</span>
                    <small>Decimal character.</small>
                    <input
                      :value="stringValue('currency.decimal_separator')"
                      type="text"
                      maxlength="3"
                      @input="
                                                setValue(
                                                    'currency.decimal_separator',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>
                </div>
              </div>

              <div
                v-if="activeCategory === 'business'"
                class="settings-card setting-group-card"
              >
                <div class="subsection-heading">
                  <div>
                    <strong>Business Identity</strong>
                    <span> Core business information used by the platform. </span>
                  </div>
                </div>

                <div class="form-grid">
                  <label class="field">
                    <span>Business Name</span>
                    <small>Primary business name.</small>
                    <input
                      :value="stringValue('business.name')"
                      type="text"
                      @input="
                                                setValue(
                                                    'business.name',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>

                  <label class="field">
                    <span>Business Email</span>
                    <small>Primary business email.</small>
                    <input
                      :value="stringValue('business.email')"
                      type="email"
                      @input="
                                                setValue(
                                                    'business.email',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>

                  <label class="field">
                    <span>Business Phone</span>
                    <small>Primary business phone.</small>
                    <input
                      :value="stringValue('business.phone')"
                      type="text"
                      @input="
                                                setValue(
                                                    'business.phone',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                    />
                  </label>

                  <label class="field full">
                    <span>Business Address</span>
                    <small> Registered or operating address. </small>
                    <textarea
                      :value="stringValue('business.address')"
                      rows="3"
                      @input="
                                                setValue(
                                                    'business.address',
                                                    (
                                                        $event.target as HTMLTextAreaElement
                                                    ).value,
                                                )
                                            "
                    ></textarea>
                  </label>
                </div>
              </div>

              <div
                v-if="activeCategory === 'business'"
                class="settings-card setting-group-card"
              >
                <div class="subsection-heading">
                  <div>
                    <strong>Business Hours</strong>
                    <span> Configure weekly operating hours. </span>
                  </div>
                </div>

                <div class="hours-list">
                  <div v-for="day in dayDefinitions" :key="day.key" class="hours-row">
                    <div class="day-copy">
                      <strong>{{ day.label }}</strong>
                      <small>
                        {{ businessHour(day.key).enabled ? "Open" : "Closed" }}
                      </small>
                    </div>

                    <label class="switch-control compact">
                      <input
                        :checked="businessHour(day.key).enabled"
                        type="checkbox"
                        :aria-label="`${day.label} enabled`"
                        @change="
                                                    updateBusinessHour(
                                                        day.key,
                                                        'enabled',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).checked,
                                                    )
                                                "
                      />
                      <span class="switch-track">
                        <span class="switch-thumb"></span>
                      </span>
                    </label>

                    <div class="time-controls">
                      <input
                        :value="businessHour(day.key).open"
                        type="time"
                        :disabled="!businessHour(day.key).enabled"
                        @input="
                                                    updateBusinessHour(
                                                        day.key,
                                                        'open',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                      />
                      <span>to</span>
                      <input
                        :value="businessHour(day.key).close"
                        type="time"
                        :disabled="!businessHour(day.key).enabled"
                        @input="
                                                    updateBusinessHour(
                                                        day.key,
                                                        'close',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                      />
                    </div>
                  </div>
                </div>
              </div>

              <div
                v-if="activeCategory === 'system'"
                class="settings-card setting-group-card"
              >
                <div class="subsection-heading">
                  <div>
                    <strong>Platform Behaviour</strong>
                    <span> Core operational controls for the application. </span>
                  </div>
                </div>

                <div class="system-setting-list">
                  <div class="system-setting-row">
                    <div class="setting-copy">
                      <div class="setting-icon">
                        <Icon name="developer" :size="15" />
                      </div>
                      <div>
                        <strong>Maintenance Mode</strong>
                        <span>
                          Temporarily place the platform into maintenance mode.
                        </span>
                        <small> system.maintenance_mode </small>
                      </div>
                    </div>

                    <label class="switch-control">
                      <input
                        :checked="booleanValue('system.maintenance_mode')"
                        type="checkbox"
                        aria-label="Maintenance Mode"
                        @change="
                                                    setValue(
                                                        'system.maintenance_mode',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).checked,
                                                    )
                                                "
                      />
                      <span class="switch-track">
                        <span class="switch-thumb"></span>
                      </span>
                      <span class="switch-label">
                        {{
                          booleanValue("system.maintenance_mode") ? "Enabled" : "Disabled"
                        }}
                      </span>
                    </label>
                  </div>

                  <div class="system-setting-row">
                    <div class="setting-copy">
                      <div class="setting-icon">
                        <Icon name="settings" :size="15" />
                      </div>
                      <div>
                        <strong>Items Per Page</strong>
                        <span> Default number of records shown in paginated lists. </span>
                        <small> system.items_per_page </small>
                      </div>
                    </div>

                    <div class="number-control">
                      <input
                        :value="numberValue('system.items_per_page', 25)"
                        type="number"
                        min="10"
                        max="100"
                        step="5"
                        aria-label="Items Per Page"
                        @input="
                                                    setValue(
                                                        'system.items_per_page',
                                                        Number(
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).value,
                                                        ),
                                                    )
                                                "
                      />
                      <span>per page</span>
                    </div>
                  </div>
                </div>
              </div>

              <div
                v-if="activeCategory === 'system'"
                class="settings-card setting-group-card"
              >
                <div class="subsection-heading">
                  <div>
                    <strong>System Support Channels</strong>
                    <span> Legacy system-level support configuration. </span>
                  </div>
                </div>

                <div class="channel-control large">
                  <button
                    v-for="channel in supportChannels"
                    :key="channel"
                    type="button"
                    class="channel-chip"
                    :class="{
                      active: arrayValue('system.support_channels').includes(channel),
                    }"
                    @click="toggleSupportChannel('system.support_channels', channel)"
                  >
                    {{ channel.charAt(0).toUpperCase() + channel.slice(1) }}
                  </button>
                </div>
              </div>
            </section>
          </template>

          <footer class="save-bar">
            <div>
              <span class="save-status-dot" :class="{ dirty: hasChanges }"></span>

              <div>
                <strong>
                  {{
                    hasChanges
                      ? `${changedCount} unsaved ${
                          changedCount === 1 ? "change" : "changes"
                        }`
                      : "All changes saved"
                  }}
                </strong>
                <small>
                  {{
                    hasChanges
                      ? "Review your changes before saving."
                      : "Configuration is synchronized with the server."
                  }}
                </small>
              </div>
            </div>

            <div class="save-actions">
              <button
                class="btn secondary"
                type="button"
                :disabled="!hasChanges || isSaving"
                @click="resetChanges"
              >
                Reset
              </button>

              <button
                class="btn primary"
                type="button"
                :disabled="!hasChanges || isSaving"
                @click="saveSettings"
              >
                <Icon name="settings" :size="13" />
                {{ isSaving ? "Saving..." : "Save Changes" }}
              </button>
            </div>
          </footer>

          <footer class="access-footer">
            <div>
              <span class="footer-status-dot"></span>
              Authorization is enforced server-side
            </div>

            <span> Settings changes are recorded in Activity Logs. </span>
          </footer>
        </main>
      </div>
    </div>
  </ErpLayout>
</template>

<style scoped>
.settings-page {
  --settings-radius: 14px;
  --settings-radius-sm: 10px;

  width: 100%;
  min-width: 0;
  min-height: 100%;
  box-sizing: border-box;
  color: var(--text-primary);
}

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 18px;
}

.page-heading {
  min-width: 0;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-bottom: 8px;
  color: var(--text-muted);
  font-size: 10px;
  font-weight: 600;
}

.breadcrumb strong {
  color: var(--text-secondary);
}

.breadcrumb-separator {
  opacity: 0.55;
}

.heading-row {
  display: flex;
  align-items: center;
  gap: 11px;
}

.heading-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  flex: 0 0 auto;
  border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));
  border-radius: 10px;
  background: color-mix(in srgb, var(--primary) 7%, var(--surface));
  color: var(--primary);
}

.heading-row h1 {
  margin: 0;
  color: var(--text-primary);
  font-size: 20px;
  font-weight: 760;
  letter-spacing: -0.025em;
}

.heading-row p {
  max-width: 620px;
  margin: 4px 0 0;
  color: var(--text-secondary);
  font-size: 10px;
  line-height: 1.5;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 7px;
  flex-shrink: 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 34px;
  padding: 0 11px;
  border: 1px solid transparent;
  border-radius: 9px;
  font-size: 10px;
  font-weight: 700;
  cursor: pointer;
  transition: transform 160ms ease, border-color 160ms ease, background 160ms ease,
    color 160ms ease, opacity 160ms ease;
}

.btn:hover:not(:disabled) {
  transform: translateY(-1px);
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.48;
}

.btn.primary {
  border-color: var(--primary);
  background: var(--primary);
  color: var(--primary-contrast, #fff);
}

.btn.secondary {
  border-color: var(--border);
  background: var(--surface);
  color: var(--text-secondary);
}

.btn.secondary:hover:not(:disabled) {
  border-color: color-mix(in srgb, var(--primary) 22%, var(--border));
  color: var(--text-primary);
}

.operation-message {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 12px;
  padding: 10px 11px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--surface);
}

.operation-message.success {
  border-color: color-mix(in srgb, var(--success) 28%, var(--border));
}

.operation-message.error {
  border-color: color-mix(in srgb, var(--danger) 28%, var(--border));
}

.message-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 27px;
  height: 27px;
  flex: 0 0 auto;
  border-radius: 8px;
  background: color-mix(in srgb, var(--primary) 8%, var(--surface));
  color: var(--primary);
}

.operation-message.success .message-icon {
  color: var(--success);
}

.operation-message.error .message-icon {
  color: var(--danger);
}

.operation-message strong,
.operation-message span {
  display: block;
}

.operation-message strong {
  font-size: 10px;
  font-weight: 700;
}

.operation-message span {
  margin-top: 2px;
  color: var(--text-secondary);
  font-size: 9px;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 9px;
  margin-bottom: 14px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
  min-height: 68px;
  padding: 10px 11px;
  border: 1px solid var(--border);
  border-radius: var(--settings-radius-sm);
  background: var(--surface);
  box-shadow: var(--shadow-xs, 0 1px 2px rgb(0 0 0 / 3%));
}

.stat-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 29px;
  height: 29px;
  flex: 0 0 auto;
  border-radius: 8px;
  background: color-mix(in srgb, var(--primary) 8%, var(--surface));
  color: var(--primary);
}

.stat-card span,
.stat-card strong {
  display: block;
}

.stat-card span {
  color: var(--text-muted);
  font-size: 8px;
  font-weight: 650;
  text-transform: uppercase;
  letter-spacing: 0.045em;
}

.stat-card strong {
  margin-top: 3px;
  color: var(--text-primary);
  font-size: 13px;
  font-weight: 750;
}

.settings-layout {
  display: grid;
  grid-template-columns: 218px minmax(0, 1fr);
  gap: 12px;
  align-items: start;
}

.settings-sidebar {
  position: sticky;
  top: 16px;
  min-width: 0;
  padding: 10px;
  border: 1px solid var(--border);
  border-radius: var(--settings-radius);
  background: var(--surface);
  box-shadow: var(--shadow-xs, 0 1px 2px rgb(0 0 0 / 3%));
}

.sidebar-heading {
  padding: 4px 5px 10px;
}

.sidebar-heading strong,
.sidebar-heading small {
  display: block;
}

.sidebar-heading strong {
  margin-top: 3px;
  color: var(--text-primary);
  font-size: 12px;
  font-weight: 750;
}

.sidebar-heading small {
  margin-top: 3px;
  color: var(--text-muted);
  font-size: 8px;
  line-height: 1.4;
}

.category-nav {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.category-item {
  display: grid;
  grid-template-columns: 29px minmax(0, 1fr) auto;
  align-items: center;
  gap: 8px;
  width: 100%;
  min-width: 0;
  padding: 8px 7px;
  border: 1px solid transparent;
  border-radius: 9px;
  background: transparent;
  color: var(--text-secondary);
  text-align: left;
  cursor: pointer;
  transition: background 160ms ease, border-color 160ms ease, color 160ms ease,
    transform 160ms ease;
}

.category-item:hover {
  border-color: var(--border);
  background: color-mix(in srgb, var(--primary) 4%, var(--surface));
}

.category-item.active {
  border-color: color-mix(in srgb, var(--primary) 18%, var(--border));
  background: color-mix(in srgb, var(--primary) 8%, var(--surface));
  color: var(--primary);
}

.category-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 29px;
  height: 29px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--primary) 6%, var(--surface));
}

.category-copy {
  min-width: 0;
}

.category-copy strong,
.category-copy small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.category-copy strong {
  color: inherit;
  font-size: 9px;
  font-weight: 720;
}

.category-copy small {
  margin-top: 2px;
  color: var(--text-muted);
  font-size: 7px;
  line-height: 1.35;
}

.category-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 19px;
  height: 19px;
  padding: 0 4px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--primary) 7%, var(--surface));
  color: var(--text-muted);
  font-size: 7px;
  font-weight: 750;
}

.category-item.active .category-count {
  background: color-mix(in srgb, var(--primary) 12%, var(--surface));
  color: var(--primary);
}

.sidebar-security {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  margin: 9px 2px 1px;
  padding: 8px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: color-mix(in srgb, var(--primary) 3%, var(--surface));
}

.security-dot {
  width: 6px;
  height: 6px;
  margin-top: 4px;
  flex: 0 0 auto;
  border-radius: 50%;
  background: var(--success);
}

.sidebar-security strong,
.sidebar-security small {
  display: block;
}

.sidebar-security strong {
  color: var(--text-primary);
  font-size: 8px;
  font-weight: 700;
}

.sidebar-security small {
  margin-top: 2px;
  color: var(--text-muted);
  font-size: 7px;
  line-height: 1.4;
}

.settings-content {
  min-width: 0;
}

.content-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 9px;
}

.section-kicker {
  display: block;
  color: var(--primary);
  font-size: 8px;
  font-weight: 750;
  letter-spacing: 0.09em;
}

.content-heading h2 {
  margin: 3px 0 0;
  color: var(--text-primary);
  font-size: 14px;
  font-weight: 750;
}

.content-heading p {
  max-width: 700px;
  margin: 3px 0 0;
  color: var(--text-secondary);
  font-size: 9px;
  line-height: 1.45;
}

.section-badge {
  flex: 0 0 auto;
  padding: 5px 8px;
  border: 1px solid var(--border);
  border-radius: 999px;
  color: var(--text-muted);
  background: var(--surface);
  font-size: 8px;
  font-weight: 700;
}

.settings-card {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: var(--settings-radius);
  background: var(--surface);
  box-shadow: var(--shadow-xs, 0 1px 2px rgb(0 0 0 / 3%));
}

.loading-card {
  min-height: 260px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.loading-indicator {
  display: flex;
  align-items: center;
  gap: 10px;
}

.loading-spinner {
  width: 22px;
  height: 22px;
  border: 2px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 800ms linear infinite;
}

.loading-indicator strong,
.loading-indicator span {
  display: block;
}

.loading-indicator strong {
  color: var(--text-primary);
  font-size: 10px;
}

.loading-indicator span {
  margin-top: 2px;
  color: var(--text-secondary);
  font-size: 8px;
}

.setting-group-card {
  margin-bottom: 10px;
}

.subsection-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 13px 14px 10px;
  border-bottom: 1px solid var(--border);
}

.subsection-heading strong,
.subsection-heading span {
  display: block;
}

.subsection-heading strong {
  color: var(--text-primary);
  font-size: 10px;
  font-weight: 720;
}

.subsection-heading span {
  margin-top: 3px;
  color: var(--text-muted);
  font-size: 8px;
  line-height: 1.4;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1px;
  background: var(--border);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  padding: 13px;
  background: var(--surface);
}

.field.full {
  grid-column: 1 / -1;
}

.field > span {
  color: var(--text-primary);
  font-size: 9px;
  font-weight: 700;
}

.field > small {
  min-height: 20px;
  color: var(--text-muted);
  font-size: 7px;
  line-height: 1.4;
}

.field input,
.field textarea,
.field select,
.time-controls input {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid var(--border);
  border-radius: 8px;
  outline: none;
  background: var(--surface);
  color: var(--text-primary);
  font-family: inherit;
  font-size: 9px;
  font-weight: 600;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.field input,
.field select {
  min-height: 34px;
  padding: 0 9px;
}

.field textarea {
  padding: 8px 9px;
  resize: vertical;
  line-height: 1.5;
}

.field input:focus,
.field textarea:focus,
.field select:focus,
.time-controls input:focus {
  border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 9%, transparent);
}

.channel-control {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 6px;
  flex-wrap: wrap;
  padding: 13px;
}

.channel-control.large {
  min-height: 58px;
}

.channel-chip {
  min-height: 29px;
  padding: 0 10px;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--surface);
  color: var(--text-secondary);
  font-size: 8px;
  font-weight: 700;
  cursor: pointer;
  transition: border-color 160ms ease, background 160ms ease, color 160ms ease,
    transform 160ms ease;
}

.channel-chip:hover {
  border-color: color-mix(in srgb, var(--primary) 28%, var(--border));
  transform: translateY(-1px);
}

.channel-chip.active {
  border-color: color-mix(in srgb, var(--primary) 35%, var(--border));
  background: color-mix(in srgb, var(--primary) 9%, var(--surface));
  color: var(--primary);
}

.hours-list {
  display: flex;
  flex-direction: column;
}

.hours-row {
  display: grid;
  grid-template-columns: minmax(130px, 1fr) auto minmax(230px, 0.75fr);
  align-items: center;
  gap: 14px;
  min-height: 58px;
  padding: 9px 13px;
  border-bottom: 1px solid var(--border);
}

.hours-row:last-child {
  border-bottom: 0;
}

.day-copy strong,
.day-copy small {
  display: block;
}

.day-copy strong {
  color: var(--text-primary);
  font-size: 9px;
  font-weight: 700;
}

.day-copy small {
  margin-top: 2px;
  color: var(--text-muted);
  font-size: 7px;
}

.time-controls {
  display: flex;
  align-items: center;
  gap: 6px;
}

.time-controls input {
  min-height: 31px;
  padding: 0 7px;
}

.time-controls span {
  flex: 0 0 auto;
  color: var(--text-muted);
  font-size: 8px;
}

.switch-control {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.switch-control input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}

.switch-track {
  position: relative;
  width: 38px;
  height: 21px;
  flex: 0 0 auto;
  border-radius: 999px;
  background: var(--border);
  transition: background 160ms ease;
}

.switch-thumb {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 15px;
  height: 15px;
  border-radius: 50%;
  background: var(--surface);
  box-shadow: 0 1px 3px rgb(0 0 0 / 18%);
  transition: transform 160ms ease;
}

.switch-control input:checked + .switch-track {
  background: var(--primary);
}

.switch-control input:checked + .switch-track .switch-thumb {
  transform: translateX(17px);
}

.switch-label {
  color: var(--text-secondary);
  font-size: 9px;
  font-weight: 650;
}

.switch-control.compact {
  gap: 0;
}

.switch-control.compact .switch-track {
  transform: scale(0.85);
  transform-origin: center;
}

.system-setting-list {
  display: flex;
  flex-direction: column;
}

.system-setting-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  min-height: 76px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--border);
}

.system-setting-row:last-child {
  border-bottom: 0;
}

.setting-copy {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.setting-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 29px;
  height: 29px;
  flex: 0 0 auto;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: color-mix(in srgb, var(--primary) 5%, var(--surface));
  color: var(--primary);
}

.setting-copy > div:last-child {
  min-width: 0;
}

.setting-copy strong,
.setting-copy span,
.setting-copy small {
  display: block;
}

.setting-copy strong {
  color: var(--text-primary);
  font-size: 10px;
  font-weight: 700;
}

.setting-copy span {
  margin-top: 2px;
  color: var(--text-secondary);
  font-size: 9px;
  line-height: 1.45;
}

.setting-copy small {
  margin-top: 3px;
  color: var(--text-muted);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 7px;
}

.number-control {
  display: flex;
  align-items: center;
  gap: 7px;
  flex: 0 0 auto;
}

.number-control input {
  width: 82px;
  min-height: 34px;
  padding: 0 9px;
  box-sizing: border-box;
  border: 1px solid var(--border);
  border-radius: 8px;
  outline: none;
  background: var(--surface);
  color: var(--text-primary);
  font-size: 10px;
  font-weight: 650;
}

.number-control input:focus {
  border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 9%, transparent);
}

.number-control span {
  color: var(--text-muted);
  font-size: 8px;
  font-weight: 650;
}

.overview-panel,
.category-panel {
  min-width: 0;
}

.overview-hero {
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 10px;
  padding: 13px;
  border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));
  border-radius: var(--settings-radius);
  background: color-mix(in srgb, var(--primary) 5%, var(--surface));
}

.overview-hero-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  flex: 0 0 auto;
  border-radius: 11px;
  background: color-mix(in srgb, var(--primary) 10%, var(--surface));
  color: var(--primary);
}

.overview-hero strong,
.overview-hero span {
  display: block;
}

.overview-hero strong {
  color: var(--text-primary);
  font-size: 10px;
  font-weight: 750;
}

.overview-hero span {
  max-width: 760px;
  margin-top: 3px;
  color: var(--text-secondary);
  font-size: 8px;
  line-height: 1.5;
}

.category-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.category-card {
  min-width: 0;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 11px;
  background: var(--surface);
  color: var(--text-primary);
  text-align: left;
  cursor: pointer;
  box-shadow: var(--shadow-xs, 0 1px 2px rgb(0 0 0 / 3%));
  transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
}

.category-card:hover {
  transform: translateY(-1px);
  border-color: color-mix(in srgb, var(--primary) 24%, var(--border));
  background: color-mix(in srgb, var(--primary) 2%, var(--surface));
}

.category-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 9px;
}

.category-card-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--primary) 7%, var(--surface));
  color: var(--primary);
}

.category-card > strong,
.category-card > span {
  display: block;
}

.category-card > strong {
  color: var(--text-primary);
  font-size: 10px;
  font-weight: 720;
}

.category-card > span {
  min-height: 25px;
  margin-top: 3px;
  color: var(--text-secondary);
  font-size: 8px;
  line-height: 1.45;
}

.changed-badge {
  padding: 3px 6px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--primary) 9%, var(--surface));
  color: var(--primary) !important;
  font-size: 7px !important;
  font-weight: 700;
}

.category-progress {
  margin-top: 11px;
}

.progress-track {
  height: 4px;
  overflow: hidden;
  border-radius: 999px;
  background: color-mix(in srgb, var(--border) 75%, var(--surface));
}

.progress-track span {
  display: block;
  height: 100%;
  min-height: 4px;
  border-radius: inherit;
  background: var(--primary);
  transition: width 200ms ease;
}

.category-progress small {
  display: block;
  margin-top: 5px;
  color: var(--text-muted);
  font-size: 7px;
}

.overview-note {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 10px;
  padding: 10px 11px;
  border: 1px dashed var(--border);
  border-radius: 10px;
  color: var(--text-muted);
}

.overview-note > svg {
  flex: 0 0 auto;
  margin-top: 1px;
  color: var(--primary);
}

.overview-note strong,
.overview-note span {
  display: block;
}

.overview-note strong {
  color: var(--text-primary);
  font-size: 8px;
  font-weight: 700;
}

.overview-note span {
  margin-top: 2px;
  font-size: 8px;
  line-height: 1.45;
}

.save-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 12px;
  padding: 10px 11px;
  border: 1px solid color-mix(in srgb, var(--primary) 20%, var(--border));
  border-radius: 10px;
  background: color-mix(in srgb, var(--primary) 5%, var(--surface));
}

.save-bar > div:first-child {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.save-status-dot {
  width: 7px;
  height: 7px;
  flex: 0 0 auto;
  border-radius: 50%;
  background: var(--success);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--success) 10%, transparent);
}

.save-status-dot.dirty {
  background: var(--primary);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 10%, transparent);
}

.save-bar strong,
.save-bar small {
  display: block;
}

.save-bar strong {
  color: var(--text-primary);
  font-size: 10px;
}

.save-bar small {
  margin-top: 2px;
  color: var(--text-secondary);
  font-size: 8px;
}

.save-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

.access-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 12px;
  padding: 9px 1px 2px;
  color: var(--text-muted);
  font-size: 8px;
}

.access-footer > div {
  display: flex;
  align-items: center;
  gap: 6px;
}

.footer-status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--success);
}

.message-enter-active,
.message-leave-active {
  transition: opacity 180ms ease, transform 180ms ease;
}

.message-enter-from,
.message-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1050px) {
  .settings-layout {
    grid-template-columns: 190px minmax(0, 1fr);
  }

  .category-copy small {
    display: none;
  }

  .category-item {
    grid-template-columns: 29px minmax(0, 1fr) auto;
  }
}

@media (max-width: 900px) {
  .settings-layout {
    grid-template-columns: 1fr;
  }

  .settings-sidebar {
    position: static;
  }

  .category-nav {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .category-copy small {
    display: block;
  }

  .category-grid {
    grid-template-columns: 1fr;
  }

  .hours-row {
    grid-template-columns: minmax(120px, 1fr) auto;
  }

  .time-controls {
    grid-column: 1 / -1;
  }
}

@media (max-width: 760px) {
  .page-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .header-actions {
    width: 100%;
  }

  .header-actions .btn {
    flex: 1;
  }

  .stat-grid {
    grid-template-columns: 1fr;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .field.full {
    grid-column: auto;
  }

  .system-setting-row {
    align-items: flex-start;
    flex-direction: column;
  }

  .save-bar {
    align-items: flex-start;
    flex-direction: column;
  }

  .save-actions {
    width: 100%;
    justify-content: flex-end;
  }
}

@media (max-width: 560px) {
  .category-nav {
    grid-template-columns: 1fr;
  }

  .content-heading {
    align-items: flex-start;
    flex-direction: column;
  }

  .section-badge {
    align-self: flex-start;
  }

  .hours-row {
    grid-template-columns: 1fr auto;
  }

  .time-controls {
    width: 100%;
  }

  .access-footer {
    align-items: flex-start;
    flex-direction: column;
  }
}

@media (prefers-reduced-motion: reduce) {
  .loading-spinner {
    animation: none;
  }

  *,
  *::before,
  *::after {
    scroll-behavior: auto !important;
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}
</style>
