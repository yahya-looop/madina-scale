<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Head } from "@inertiajs/vue3";

import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Props {
  userName?: string;
  userEmail?: string;
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

interface SettingDefinition {
  key: string;
  label: string;
  description: string;
  group: "general" | "system";
  type: "string" | "boolean" | "integer" | "json";
  icon: string;
}

const props = withDefaults(defineProps<Props>(), {
  userName: "",
  userEmail: "",
});

const settings = ref<Record<string, unknown>>({});
const originalSettings = ref<Record<string, unknown>>({});

const isLoading = ref(false);
const isSaving = ref(false);
const operationMessage = ref("");
const operationMessageType = ref<"success" | "error">("success");

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
    key: "system.maintenance_mode",
    label: "Maintenance Mode",
    description: "Temporarily place the platform into maintenance mode.",
    group: "system",
    type: "boolean",
    icon: "settings",
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
    label: "Support Channels",
    description: "Support channels currently available to the platform.",
    group: "system",
    type: "json",
    icon: "settings",
  },
];

const generalSettings = computed(() =>
  definitions.filter((definition) => definition.group === "general")
);

const systemSettings = computed(() =>
  definitions.filter((definition) => definition.group === "system")
);

const hasChanges = computed<boolean>(() => {
  return JSON.stringify(settings.value) !== JSON.stringify(originalSettings.value);
});

const configuredCount = computed<number>(() => {
  return definitions.filter((definition) =>
    Object.prototype.hasOwnProperty.call(settings.value, definition.key)
  ).length;
});

const totalCount = computed<number>(() => definitions.length);

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

function numberValue(key: string): number {
  const value = displayValue(key);

  if (typeof value === "number") {
    return value;
  }

  const parsed = Number(value);

  return Number.isFinite(parsed) ? parsed : 25;
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

function definitionFor(key: string): SettingDefinition | undefined {
  return definitions.find((definition) => definition.key === key);
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
    await apiRequest<ApiSettingsResponse>("/api/settings", "PUT", {
      settings: changedSettings,
    });

    originalSettings.value = {
      ...settings.value,
    };

    operationMessageType.value = "success";
    operationMessage.value = "System settings updated successfully.";
  } catch (error) {
    operationMessageType.value = "error";
    operationMessage.value =
      error instanceof Error ? error.message : "Unable to update system settings.";
  } finally {
    isSaving.value = false;
  }
}

function toggleSupportChannel(channel: string): void {
  const channels = arrayValue("system.support_channels");

  const exists = channels.includes(channel);

  const nextChannels = exists
    ? channels.filter((item) => item !== channel)
    : [...channels, channel];

  setValue("system.support_channels", nextChannels);
}

onMounted(() => {
  void fetchSettings();
});
</script>

<template>
  <Head title="System Settings" />

  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <div class="settings-page">
      <!-- ========================================================== -->
      <!-- Header                                                     -->
      <!-- ========================================================== -->

      <header class="page-header">
        <div class="page-heading">
          <div class="breadcrumb">
            <span>Settings</span>
            <span class="breadcrumb-separator">/</span>
            <strong>System</strong>
          </div>

          <div class="heading-row">
            <div class="heading-icon">
              <Icon name="settings" :size="17" />
            </div>

            <div>
              <h1>System Settings</h1>

              <p>Manage core platform configuration and system behaviour.</p>
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
            <span>
              {{ isLoading ? "Refreshing..." : "Refresh" }}
            </span>
          </button>

          <button
            class="btn primary"
            type="button"
            :disabled="!hasChanges || isSaving"
            @click="saveSettings"
          >
            <Icon name="settings" :size="14" />

            <span>
              {{ isSaving ? "Saving..." : "Save Changes" }}
            </span>
          </button>
        </div>
      </header>

      <!-- ========================================================== -->
      <!-- Operation message                                          -->
      <!-- ========================================================== -->

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

      <!-- ========================================================== -->
      <!-- Overview                                                    -->
      <!-- ========================================================== -->

      <section class="stat-grid">
        <article class="stat-card">
          <div class="stat-icon">
            <Icon name="settings" :size="16" />
          </div>

          <div>
            <span>Configured</span>
            <strong> {{ configuredCount }}/{{ totalCount }} </strong>
          </div>
        </article>

        <article class="stat-card">
          <div class="stat-icon">
            <Icon name="activity" :size="16" />
          </div>

          <div>
            <span>Current Status</span>
            <strong>Operational</strong>
          </div>
        </article>

        <article class="stat-card">
          <div class="stat-icon">
            <Icon name="developer" :size="16" />
          </div>

          <div>
            <span>Access</span>
            <strong>Protected</strong>
          </div>
        </article>
      </section>

      <!-- ========================================================== -->
      <!-- Loading                                                    -->
      <!-- ========================================================== -->

      <section v-if="isLoading" class="settings-card loading-card">
        <div class="loading-indicator">
          <span class="loading-spinner"></span>

          <div>
            <strong>Loading system settings</strong>
            <span> Retrieving the latest configuration... </span>
          </div>
        </div>
      </section>

      <template v-else>
        <!-- ====================================================== -->
        <!-- General                                                 -->
        <!-- ====================================================== -->

        <section class="settings-section">
          <div class="section-heading">
            <div>
              <span class="section-kicker">GENERAL</span>
              <h2>General Settings</h2>
              <p>Core application identity and platform defaults.</p>
            </div>

            <span class="section-badge">
              {{ generalSettings.length }} setting
              <span v-if="generalSettings.length !== 1"> s </span>
            </span>
          </div>

          <div class="settings-card">
            <div
              v-for="definition in generalSettings"
              :key="definition.key"
              class="setting-row"
            >
              <div class="setting-copy">
                <div class="setting-icon">
                  <Icon :name="definition.icon" :size="15" />
                </div>

                <div>
                  <strong>
                    {{ definition.label }}
                  </strong>

                  <span>
                    {{ definition.description }}
                  </span>

                  <small>
                    {{ definition.key }}
                  </small>
                </div>
              </div>

              <div class="setting-control">
                <input
                  v-if="definition.type === 'string'"
                  :value="stringValue(definition.key)"
                  type="text"
                  :aria-label="definition.label"
                  @input="
                                        setValue(
                                            definition.key,
                                            (
                                                $event.target as HTMLInputElement
                                            ).value,
                                        )
                                    "
                />
              </div>
            </div>
          </div>
        </section>

        <!-- ====================================================== -->
        <!-- System                                                   -->
        <!-- ====================================================== -->

        <section class="settings-section">
          <div class="section-heading">
            <div>
              <span class="section-kicker">SYSTEM</span>
              <h2>System Behaviour</h2>
              <p>Platform-wide behaviour and operational configuration.</p>
            </div>

            <span class="section-badge"> {{ systemSettings.length }} settings </span>
          </div>

          <div class="settings-card">
            <div
              v-for="definition in systemSettings"
              :key="definition.key"
              class="setting-row"
            >
              <div class="setting-copy">
                <div class="setting-icon">
                  <Icon :name="definition.icon" :size="15" />
                </div>

                <div>
                  <strong>
                    {{ definition.label }}
                  </strong>

                  <span>
                    {{ definition.description }}
                  </span>

                  <small>
                    {{ definition.key }}
                  </small>
                </div>
              </div>

              <div class="setting-control">
                <!-- Maintenance mode -->

                <label
                  v-if="definition.key === 'system.maintenance_mode'"
                  class="switch-control"
                >
                  <input
                    :checked="booleanValue(definition.key)"
                    type="checkbox"
                    :aria-label="definition.label"
                    @change="
                                            setValue(
                                                definition.key,
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
                    {{ booleanValue(definition.key) ? "Enabled" : "Disabled" }}
                  </span>
                </label>

                <!-- Items per page -->

                <div
                  v-else-if="definition.key === 'system.items_per_page'"
                  class="number-control"
                >
                  <input
                    :value="numberValue(definition.key)"
                    type="number"
                    min="10"
                    max="100"
                    step="5"
                    :aria-label="definition.label"
                    @input="
                                            setValue(
                                                definition.key,
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

                <!-- Support channels -->

                <div
                  v-else-if="definition.key === 'system.support_channels'"
                  class="channel-control"
                >
                  <button
                    v-for="channel in ['chat', 'email', 'phone']"
                    :key="channel"
                    class="channel-chip"
                    :class="{
                      active: arrayValue(definition.key).includes(channel),
                    }"
                    type="button"
                    @click="toggleSupportChannel(channel)"
                  >
                    {{ channel.charAt(0).toUpperCase() + channel.slice(1) }}
                  </button>
                </div>

                <span v-else class="setting-value">
                  {{ formatSettingValue(definition.key) }}
                </span>
              </div>
            </div>
          </div>
        </section>

        <!-- ====================================================== -->
        <!-- Future sections                                          -->
        <!-- ====================================================== -->

        <section class="settings-section future-section">
          <div class="section-heading">
            <div>
              <span class="section-kicker">ROADMAP</span>
              <h2>Additional Configuration</h2>
              <p>These areas are reserved for the next settings phases.</p>
            </div>
          </div>

          <div class="future-grid">
            <article class="future-card">
              <div class="future-icon">
                <Icon name="settings" :size="16" />
              </div>

              <div>
                <strong>Appearance &amp; UI</strong>
                <span>
                  Theme, colors, typography, density, radius and layout preferences.
                </span>
              </div>

              <span class="future-badge">Next</span>
            </article>

            <article class="future-card">
              <div class="future-icon">
                <Icon name="developer" :size="16" />
              </div>

              <div>
                <strong>Security</strong>
                <span> Platform security and authentication configuration. </span>
              </div>

              <span class="future-badge">Planned</span>
            </article>

            <article class="future-card">
              <div class="future-icon">
                <Icon name="activity" :size="16" />
              </div>

              <div>
                <strong>Advanced</strong>
                <span> Advanced operational and integration settings. </span>
              </div>

              <span class="future-badge">Planned</span>
            </article>
          </div>
        </section>

        <!-- ====================================================== -->
        <!-- Save bar                                                 -->
        <!-- ====================================================== -->

        <footer class="save-bar">
          <div>
            <span class="save-status-dot" :class="{ dirty: hasChanges }"></span>

            <div>
              <strong>
                {{ hasChanges ? "Unsaved changes" : "All changes saved" }}
              </strong>

              <small>
                {{
                  hasChanges
                    ? "Review your changes before saving."
                    : "System configuration is synchronized."
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

        <!-- ====================================================== -->
        <!-- Footer notice                                            -->
        <!-- ====================================================== -->

        <footer class="access-footer">
          <div>
            <span class="footer-status-dot"></span>
            Authorization is enforced server-side
          </div>

          <span> Settings changes are recorded in Activity Logs. </span>
        </footer>
      </template>
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

  padding: 0;

  color: var(--text-primary);
}

/* -------------------------------------------------------------------------- */
/* Header                                                                     */
/* -------------------------------------------------------------------------- */

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

  width: 34px;
  height: 34px;

  border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));

  border-radius: 10px;

  background: color-mix(in srgb, var(--primary) 7%, var(--surface));

  color: var(--primary);
}

.heading-row h1 {
  margin: 0;

  color: var(--text-primary);
  font-size: 19px;
  font-weight: 750;
  letter-spacing: -0.025em;
}

.heading-row p {
  margin: 3px 0 0;

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

/* -------------------------------------------------------------------------- */
/* Buttons                                                                    */
/* -------------------------------------------------------------------------- */

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

/* -------------------------------------------------------------------------- */
/* Operation message                                                          */
/* -------------------------------------------------------------------------- */

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

/* -------------------------------------------------------------------------- */
/* Statistics                                                                 */
/* -------------------------------------------------------------------------- */

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

/* -------------------------------------------------------------------------- */
/* Sections                                                                   */
/* -------------------------------------------------------------------------- */

.settings-section {
  margin-bottom: 16px;
}

.section-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;

  margin-bottom: 8px;
}

.section-kicker {
  display: block;

  color: var(--primary);
  font-size: 8px;
  font-weight: 750;
  letter-spacing: 0.09em;
}

.section-heading h2 {
  margin: 3px 0 0;

  color: var(--text-primary);
  font-size: 13px;
  font-weight: 750;
}

.section-heading p {
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

/* -------------------------------------------------------------------------- */
/* Settings card                                                              */
/* -------------------------------------------------------------------------- */

.settings-card {
  overflow: hidden;

  border: 1px solid var(--border);
  border-radius: var(--settings-radius);

  background: var(--surface);

  box-shadow: var(--shadow-xs, 0 1px 2px rgb(0 0 0 / 3%));
}

.setting-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;

  min-height: 76px;
  padding: 12px 14px;

  border-bottom: 1px solid var(--border);
}

.setting-row:last-child {
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

.setting-control {
  display: flex;
  align-items: center;
  justify-content: flex-end;

  flex: 0 0 auto;
}

/* -------------------------------------------------------------------------- */
/* Inputs                                                                     */
/* -------------------------------------------------------------------------- */

.setting-control input[type="text"],
.setting-control input[type="number"] {
  width: 240px;
  min-height: 34px;

  padding: 0 10px;

  border: 1px solid var(--border);
  border-radius: 8px;

  outline: none;

  background: var(--surface);
  color: var(--text-primary);

  font-size: 10px;
  font-weight: 600;

  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.setting-control input:focus {
  border-color: color-mix(in srgb, var(--primary) 55%, var(--border));

  box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 9%, transparent);
}

/* -------------------------------------------------------------------------- */
/* Switch                                                                     */
/* -------------------------------------------------------------------------- */

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

/* -------------------------------------------------------------------------- */
/* Number control                                                             */
/* -------------------------------------------------------------------------- */

.number-control {
  display: flex;
  align-items: center;
  gap: 7px;
}

.number-control input {
  width: 82px !important;
}

.number-control span {
  color: var(--text-muted);
  font-size: 8px;
  font-weight: 650;
}

/* -------------------------------------------------------------------------- */
/* Support channels                                                           */
/* -------------------------------------------------------------------------- */

.channel-control {
  display: flex;
  align-items: center;
  gap: 5px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.channel-chip {
  min-height: 28px;
  padding: 0 9px;

  border: 1px solid var(--border);
  border-radius: 999px;

  background: var(--surface);
  color: var(--text-secondary);

  font-size: 8px;
  font-weight: 700;

  cursor: pointer;

  transition: border-color 160ms ease, background 160ms ease, color 160ms ease;
}

.channel-chip:hover {
  border-color: color-mix(in srgb, var(--primary) 28%, var(--border));
}

.channel-chip.active {
  border-color: color-mix(in srgb, var(--primary) 35%, var(--border));

  background: color-mix(in srgb, var(--primary) 9%, var(--surface));

  color: var(--primary);
}

.setting-value {
  color: var(--text-secondary);
  font-size: 9px;
  font-weight: 650;
}

/* -------------------------------------------------------------------------- */
/* Loading                                                                    */
/* -------------------------------------------------------------------------- */

.loading-card {
  min-height: 150px;

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

/* -------------------------------------------------------------------------- */
/* Future sections                                                            */
/* -------------------------------------------------------------------------- */

.future-section {
  margin-top: 4px;
}

.future-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.future-card {
  position: relative;

  display: flex;
  align-items: flex-start;
  gap: 9px;

  min-width: 0;
  padding: 12px;

  border: 1px dashed var(--border);
  border-radius: 11px;

  background: color-mix(in srgb, var(--surface) 88%, var(--border));
}

.future-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  width: 28px;
  height: 28px;

  flex: 0 0 auto;

  border-radius: 8px;

  background: color-mix(in srgb, var(--primary) 6%, var(--surface));

  color: var(--text-muted);
}

.future-card strong,
.future-card span {
  display: block;
}

.future-card strong {
  color: var(--text-primary);
  font-size: 9px;
  font-weight: 700;
}

.future-card > div:nth-child(2) span {
  margin-top: 3px;

  color: var(--text-secondary);
  font-size: 8px;
  line-height: 1.45;
}

.future-badge {
  position: absolute;
  top: 8px;
  right: 8px;

  padding: 3px 5px;

  border-radius: 999px;

  background: color-mix(in srgb, var(--primary) 8%, var(--surface));

  color: var(--primary);

  font-size: 7px;
  font-weight: 700;
}

/* -------------------------------------------------------------------------- */
/* Save bar                                                                   */
/* -------------------------------------------------------------------------- */

.save-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;

  margin-top: 14px;
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

/* -------------------------------------------------------------------------- */
/* Footer                                                                     */
/* -------------------------------------------------------------------------- */

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

/* -------------------------------------------------------------------------- */
/* Transitions                                                                */
/* -------------------------------------------------------------------------- */

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

/* -------------------------------------------------------------------------- */
/* Responsive                                                                 */
/* -------------------------------------------------------------------------- */

@media (max-width: 900px) {
  .future-grid {
    grid-template-columns: 1fr;
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

  .setting-row {
    align-items: flex-start;
    flex-direction: column;
  }

  .setting-control {
    width: 100%;
    justify-content: flex-start;
  }

  .setting-control input[type="text"] {
    width: 100%;
  }

  .channel-control {
    justify-content: flex-start;
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
  .heading-row {
    align-items: flex-start;
  }

  .heading-row h1 {
    font-size: 17px;
  }

  .section-heading {
    align-items: flex-start;
    flex-direction: column;
  }

  .section-badge {
    align-self: flex-start;
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
