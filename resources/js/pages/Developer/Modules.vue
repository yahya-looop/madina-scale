<script setup lang="ts">
import { computed, onMounted, ref } from "vue";

import { Head } from "@inertiajs/vue3";

import ErpLayout from "@/layouts/ErpLayout.vue";

import Icon from "@/components/dashboard/Icon.vue";

interface Props {
  userName?: string;

  userEmail?: string;
}

interface Module {
  id: number;

  name: string;

  slug: string;

  description: string | null;

  icon: string | null;

  sort_order: number;

  is_system: boolean;

  is_active: boolean;
}

interface ApiResponse<T> {
  data?: T;

  message?: string;

  errors?: Record<string, string[]>;
}

interface ModuleForm {
  name: string;

  slug: string;

  description: string;

  icon: string;

  sort_order: number;

  is_active: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  userName: "",

  userEmail: "",
});

const modules = ref<Module[]>([]);

const isLoading = ref(false);

const isSaving = ref(false);

const showModal = ref(false);

const editingModule = ref<Module | null>(null);

const searchQuery = ref("");

const statusFilter = ref<"all" | "active" | "inactive">("all");

const operationMessage = ref("");

const operationMessageType = ref<"success" | "error">("success");

const validationErrors = ref<Record<string, string[]>>({});

const form = ref<ModuleForm>(createEmptyForm());

function createEmptyForm(): ModuleForm {
  return {
    name: "",

    slug: "",

    description: "",

    icon: "",

    sort_order: 0,

    is_active: true,
  };
}

const isEditing = computed<boolean>(() => editingModule.value !== null);

const modalTitle = computed<string>(() =>
  isEditing.value ? "Edit Module" : "Create Module"
);

const filteredModules = computed<Module[]>(() => {
  const query = searchQuery.value.trim().toLowerCase();

  return modules.value.filter((module) => {
    const matchesSearch =
      query.length === 0 ||
      module.name.toLowerCase().includes(query) ||
      module.slug.toLowerCase().includes(query) ||
      (module.description ?? "").toLowerCase().includes(query);

    const matchesStatus =
      statusFilter.value === "all" ||
      (statusFilter.value === "active" && module.is_active) ||
      (statusFilter.value === "inactive" && !module.is_active);

    return matchesSearch && matchesStatus;
  });
});

const totalModules = computed<number>(() => modules.value.length);

const activeModules = computed<number>(
  () => modules.value.filter((module) => module.is_active).length
);

const inactiveModules = computed<number>(
  () => modules.value.filter((module) => !module.is_active).length
);

const systemModules = computed<number>(
  () => modules.value.filter((module) => module.is_system).length
);

const customModules = computed<number>(
  () => modules.value.filter((module) => !module.is_system).length
);

function resetForm(): void {
  form.value = createEmptyForm();

  validationErrors.value = {};
}

function openCreateModal(): void {
  editingModule.value = null;

  resetForm();

  operationMessage.value = "";

  showModal.value = true;
}

function openEditModal(module: Module): void {
  editingModule.value = module;

  form.value = {
    name: module.name,

    slug: module.slug,

    description: module.description ?? "",

    icon: module.icon ?? "",

    sort_order: module.sort_order,

    is_active: module.is_active,
  };

  validationErrors.value = {};

  operationMessage.value = "";

  showModal.value = true;
}

function closeModal(): void {
  if (isSaving.value) {
    return;
  }

  showModal.value = false;

  editingModule.value = null;

  resetForm();
}

function clearOperationMessage(): void {
  operationMessage.value = "";
}

function normalizeSlug(): void {
  form.value.slug = form.value.slug

    .toLowerCase()

    .trim()

    .replace(/[^a-z0-9.\_-]+/g, "-")

    .replace(/-{2,}/g, "-");
}

function fieldError(field: string): string {
  return validationErrors.value[field]?.[0] ?? "";
}

function getCsrfToken(): string | null {
  const cookie = document.cookie.split("; ").find((row) => row.startsWith("XSRF-TOKEN="));

  if (!cookie) {
    return null;
  }

  const value = cookie.substring("XSRF-TOKEN=".length);

  try {
    return decodeURIComponent(value);
  } catch {
    return value;
  }
}

function buildHeaders(): HeadersInit {
  const headers: HeadersInit = {
    Accept: "application/json",

    "Content-Type": "application/json",
  };

  const csrfToken = getCsrfToken();

  if (csrfToken) {
    headers["X-XSRF-TOKEN"] = csrfToken;
  }

  return headers;
}

async function fetchModules(): Promise<void> {
  isLoading.value = true;

  clearOperationMessage();

  try {
    const response = await fetch("/api/modules", {
      method: "GET",

      headers: {
        Accept: "application/json",
      },

      credentials: "same-origin",
    });

    const payload = (await response.json()) as ApiResponse<Module[]>;

    if (!response.ok) {
      throw new Error(payload.message ?? "Unable to load modules.");
    }

    modules.value = Array.isArray(payload.data) ? payload.data : [];
  } catch (error) {
    operationMessageType.value = "error";

    operationMessage.value =
      error instanceof Error ? error.message : "Unable to load modules.";
  } finally {
    isLoading.value = false;
  }
}

async function saveModule(): Promise<void> {
  validationErrors.value = {};

  clearOperationMessage();

  const name = form.value.name.trim();

  const slug = form.value.slug.trim();

  if (!name) {
    validationErrors.value.name = ["The name field is required."];
  }

  if (!slug) {
    validationErrors.value.slug = ["The slug field is required."];
  }

  if (Object.keys(validationErrors.value).length > 0) {
    return;
  }

  isSaving.value = true;

  try {
    const editing = editingModule.value !== null;

    const endpoint = editing ? `/api/modules/${editingModule.value?.id}` : "/api/modules";

    const method = editing ? "PUT" : "POST";

    const response = await fetch(endpoint, {
      method,

      headers: buildHeaders(),

      credentials: "same-origin",

      body: JSON.stringify({
        name,

        slug,

        description: form.value.description.trim() || null,

        icon: form.value.icon.trim() || null,

        sort_order: Number(form.value.sort_order) || 0,

        is_active: Boolean(form.value.is_active),
      }),
    });

    const payload = (await response.json()) as ApiResponse<Module>;

    if (!response.ok) {
      if (payload.errors) {
        validationErrors.value = payload.errors;
      }

      throw new Error(
        payload.message ??
          (editing ? "Unable to update module." : "Unable to create module.")
      );
    }

    operationMessageType.value = "success";

    operationMessage.value = editing
      ? "Module updated successfully."
      : "Module created successfully.";

    showModal.value = false;

    editingModule.value = null;

    resetForm();

    await fetchModules();

    operationMessageType.value = "success";

    operationMessage.value = editing
      ? "Module updated successfully."
      : "Module created successfully.";
  } catch (error) {
    if (Object.keys(validationErrors.value).length === 0 || !(error instanceof Error)) {
      operationMessageType.value = "error";

      operationMessage.value =
        error instanceof Error ? error.message : "Unable to save module.";
    }
  } finally {
    isSaving.value = false;
  }
}

function moduleInitial(module: Module): string {
  const value = module.name.trim();

  return value.length > 0 ? value.charAt(0).toUpperCase() : "M";
}

function formatSortOrder(value: number): string {
  return String(value).padStart(2, "0");
}

onMounted(() => {
  void fetchModules();
});
</script>

<template>
  <Head title="Module Management" />

  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <div class="modules-page">
      <header class="page-header">
        <div class="page-heading">
          <div class="breadcrumb">
            <span>Developer</span>

            <span class="breadcrumb-separator">/</span>

            <strong>Module Management</strong>
          </div>

          <div class="heading-row">
            <div class="heading-icon">
              <Icon name="developer" :size="17" />
            </div>

            <div>
              <h1>Module Management</h1>

              <p>
                Control application modules, availability and navigation-level system
                capabilities.
              </p>
            </div>
          </div>
        </div>

        <div class="header-actions">
          <button
            class="btn secondary"
            type="button"
            :disabled="isLoading || isSaving"
            @click="fetchModules"
          >
            <Icon name="activity" :size="14" />

            <span>
              {{ isLoading ? "Refreshing..." : "Refresh" }}
            </span>
          </button>

          <button class="btn primary" type="button" @click="openCreateModal">
            <Icon name="developer" :size="14" />

            <span>Create Module</span>
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
                  ? "Operation completed"
                  : "Unable to complete request"
              }}
            </strong>

            <span>{{ operationMessage }}</span>
          </div>

          <button
            type="button"
            aria-label="Dismiss message"
            @click="clearOperationMessage"
          >
            ×
          </button>
        </div>
      </Transition>

      <section class="stat-grid">
        <article class="stat-card">
          <div class="stat-icon">
            <Icon name="developer" :size="16" />
          </div>

          <div>
            <span>Total Modules</span>

            <strong>{{ totalModules }}</strong>
          </div>
        </article>

        <article class="stat-card">
          <div class="stat-icon success">
            <Icon name="activity" :size="16" />
          </div>

          <div>
            <span>Active</span>

            <strong>{{ activeModules }}</strong>
          </div>
        </article>

        <article class="stat-card">
          <div class="stat-icon warning">
            <Icon name="settings" :size="16" />
          </div>

          <div>
            <span>Inactive</span>

            <strong>{{ inactiveModules }}</strong>
          </div>
        </article>

        <article class="stat-card">
          <div class="stat-icon">
            <Icon name="developer" :size="16" />
          </div>

          <div>
            <span>System / Custom</span>

            <strong>{{ systemModules }} / {{ customModules }}</strong>
          </div>
        </article>
      </section>

      <section class="module-panel">
        <div class="panel-toolbar">
          <div class="toolbar-heading">
            <span class="section-kicker">PLATFORM ARCHITECTURE</span>

            <div class="toolbar-title-row">
              <h2>Application Modules</h2>

              <span class="record-count">
                {{ filteredModules.length }}
              </span>
            </div>

            <p>
              System modules are protected by the platform authorization layer. Custom
              modules can be managed from this workspace.
            </p>
          </div>

          <div class="toolbar-controls">
            <label class="search-control">
              <Icon name="activity" :size="14" />

              <input
                v-model="searchQuery"
                type="search"
                placeholder="Search modules..."
                aria-label="Search modules"
              />
            </label>

            <select
              v-model="statusFilter"
              class="filter-control"
              aria-label="Filter module status"
            >
              <option value="all">All Status</option>

              <option value="active">Active</option>

              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>

        <div v-if="isLoading" class="loading-state">
          <span class="loading-spinner"></span>

          <div>
            <strong>Loading modules</strong>

            <span> Retrieving the latest module configuration... </span>
          </div>
        </div>

        <div v-else-if="filteredModules.length === 0" class="empty-state">
          <div class="empty-icon">
            <Icon name="developer" :size="21" />
          </div>

          <strong>
            {{
              modules.length === 0
                ? "No modules available"
                : "No modules match your filters"
            }}
          </strong>

          <span>
            {{
              modules.length === 0
                ? "Create your first custom module to start building the platform architecture."
                : "Try a different search term or status filter."
            }}
          </span>

          <button
            v-if="modules.length === 0"
            type="button"
            class="btn primary"
            @click="openCreateModal"
          >
            <Icon name="developer" :size="14" />

            <span>Create Module</span>
          </button>
        </div>

        <div v-else class="table-wrap">
          <table class="module-table">
            <thead>
              <tr>
                <th>Module</th>

                <th>Type</th>

                <th>Status</th>

                <th>Order</th>

                <th class="actions-column">Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="module in filteredModules" :key="module.id">
                <td>
                  <div class="module-identity">
                    <div class="module-avatar">
                      {{ moduleInitial(module) }}
                    </div>

                    <div class="module-copy">
                      <strong>{{ module.name }}</strong>

                      <span>
                        {{ module.slug }}
                      </span>

                      <small v-if="module.description">
                        {{ module.description }}
                      </small>
                    </div>
                  </div>
                </td>

                <td>
                  <span
                    class="type-badge"
                    :class="{
                      system: module.is_system,

                      custom: !module.is_system,
                    }"
                  >
                    <span class="status-dot"></span>

                    {{ module.is_system ? "System" : "Custom" }}
                  </span>
                </td>

                <td>
                  <span
                    class="status-badge"
                    :class="{
                      active: module.is_active,

                      inactive: !module.is_active,
                    }"
                  >
                    <span class="status-dot"></span>

                    {{ module.is_active ? "Active" : "Inactive" }}
                  </span>
                </td>

                <td>
                  <span class="sort-order">
                    {{ formatSortOrder(module.sort_order) }}
                  </span>
                </td>

                <td class="actions-column">
                  <button
                    class="table-action"
                    type="button"
                    :aria-label="
                      module.is_system ? `View ${module.name}` : `Edit ${module.name}`
                    "
                    @click="
                      module.is_system ? openEditModal(module) : openEditModal(module)
                    "
                  >
                    <Icon
                      :name="module.is_system ? 'settings' : 'developer'"
                      :size="14"
                    />

                    <span>
                      {{ module.is_system ? "View" : "Edit" }}
                    </span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="security-note">
        <div class="security-note-icon">
          <Icon name="developer" :size="15" />
        </div>

        <div>
          <strong>Protected platform configuration</strong>

          <span>
            System modules cannot be modified through the module management API.
            Authorization is enforced server-side for every module operation.
          </span>
        </div>
      </section>
    </div>

    <Transition name="modal">
      <div v-if="showModal" class="modal-backdrop" @mousedown.self="closeModal">
        <section
          class="module-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="module-modal-title"
        >
          <header class="modal-header">
            <div class="modal-title">
              <div class="modal-icon">
                <Icon name="developer" :size="17" />
              </div>

              <div>
                <span class="section-kicker"> MODULE CONFIGURATION </span>

                <h2 id="module-modal-title">
                  {{ modalTitle }}
                </h2>

                <p>
                  {{
                    isEditing
                      ? "Update the module configuration."
                      : "Register a new custom application module."
                  }}
                </p>
              </div>
            </div>

            <button
              class="modal-close"
              type="button"
              aria-label="Close"
              :disabled="isSaving"
              @click="closeModal"
            >
              ×
            </button>
          </header>

          <form class="module-form" @submit.prevent="saveModule">
            <div v-if="isEditing && editingModule?.is_system" class="system-warning">
              <Icon name="settings" :size="15" />

              <div>
                <strong>System module</strong>

                <span>
                  This module is protected. The backend prevents modifications to system
                  modules.
                </span>
              </div>
            </div>

            <div class="form-grid">
              <label class="form-field full">
                <span>
                  Module Name

                  <b>\*</b>
                </span>

                <input
                  v-model="form.name"
                  type="text"
                  maxlength="255"
                  placeholder="e.g. Loyalty & Rewards"
                  :disabled="isSaving || Boolean(editingModule?.is_system)"
                />

                <small v-if="fieldError('name')" class="field-error">
                  {{ fieldError("name") }}
                </small>
              </label>

              <label class="form-field">
                <span>
                  Slug

                  <b>\*</b>
                </span>

                <input
                  v-model="form.slug"
                  type="text"
                  maxlength="255"
                  placeholder="loyalty-rewards"
                  :disabled="isSaving || Boolean(editingModule?.is_system)"
                  @blur="normalizeSlug"
                />

                <small class="field-hint">
                  Lowercase letters, numbers, dots, underscores and hyphens.
                </small>

                <small v-if="fieldError('slug')" class="field-error">
                  {{ fieldError("slug") }}
                </small>
              </label>

              <label class="form-field">
                <span>Icon</span>

                <input
                  v-model="form.icon"
                  type="text"
                  maxlength="100"
                  placeholder="developer"
                  :disabled="isSaving || Boolean(editingModule?.is_system)"
                />

                <small class="field-hint"> Optional UI icon identifier. </small>

                <small v-if="fieldError('icon')" class="field-error">
                  {{ fieldError("icon") }}
                </small>
              </label>

              <label class="form-field">
                <span>Sort Order</span>

                <input
                  v-model.number="form.sort_order"
                  type="number"
                  min="0"
                  step="1"
                  :disabled="isSaving || Boolean(editingModule?.is_system)"
                />

                <small class="field-hint"> Lower values appear first. </small>

                <small v-if="fieldError('sort_order')" class="field-error">
                  {{ fieldError("sort_order") }}
                </small>
              </label>

              <label class="form-field">
                <span>Status</span>

                <div
                  class="status-toggle"
                  :class="{
                    disabled: isSaving || Boolean(editingModule?.is_system),
                  }"
                >
                  <input
                    v-model="form.is_active"
                    type="checkbox"
                    :disabled="isSaving || Boolean(editingModule?.is_system)"
                  />

                  <span class="toggle-track">
                    <span class="toggle-thumb"></span>
                  </span>

                  <strong>
                    {{ form.is_active ? "Active" : "Inactive" }}
                  </strong>
                </div>

                <small class="field-hint">
                  Inactive modules remain registered but are disabled by authorization.
                </small>
              </label>

              <label class="form-field full">
                <span>Description</span>

                <textarea
                  v-model="form.description"
                  rows="4"
                  placeholder="Describe the purpose of this module..."
                  :disabled="isSaving || Boolean(editingModule?.is_system)"
                ></textarea>

                <small v-if="fieldError('description')" class="field-error">
                  {{ fieldError("description") }}
                </small>
              </label>
            </div>

            <footer class="modal-footer">
              <button
                class="btn secondary"
                type="button"
                :disabled="isSaving"
                @click="closeModal"
              >
                Cancel
              </button>

              <button
                v-if="!editingModule?.is_system"
                class="btn primary"
                type="submit"
                :disabled="isSaving"
              >
                <Icon :name="isSaving ? 'activity' : 'developer'" :size="14" />

                <span>
                  {{
                    isSaving ? "Saving..." : isEditing ? "Save Changes" : "Create Module"
                  }}
                </span>
              </button>

              <button v-else class="btn secondary" type="button" @click="closeModal">
                Close
              </button>
            </footer>
          </form>
        </section>
      </div>
    </Transition>
  </ErpLayout>
</template>

<style scoped>
.modules-page {
  width: 100%;

  min-width: 0;

  padding: 0;

  box-sizing: border-box;

  color: #16222d;
}

.page-header {
  display: flex;

  align-items: flex-end;

  justify-content: space-between;

  gap: 24px;

  margin-bottom: 18px;
}

.page-heading {
  min-width: 0;
}

.breadcrumb {
  display: flex;

  align-items: center;

  gap: 7px;

  margin-bottom: 9px;

  color: #81909d;

  font-size: 11px;

  font-weight: 600;
}

.breadcrumb strong {
  color: #536473;

  font-weight: 700;
}

.breadcrumb-separator {
  color: #b5c0c8;
}

.heading-row {
  display: flex;

  align-items: flex-start;

  gap: 12px;
}

.heading-icon {
  display: flex;

  align-items: center;

  justify-content: center;

  width: 38px;

  height: 38px;

  flex: 0 0 38px;

  border: 1px solid rgba(8, 184, 120, 0.18);

  border-radius: 11px;

  background: rgba(8, 184, 120, 0.08);

  color: #08a970;
}

.heading-row h1 {
  margin: 0;

  color: #15212b;

  font-size: 23px;

  font-weight: 750;

  letter-spacing: -0.025em;
}

.heading-row p {
  max-width: 680px;

  margin: 5px 0 0;

  color: #788895;

  font-size: 12px;

  line-height: 1.55;
}

.header-actions {
  display: flex;

  align-items: center;

  gap: 8px;

  flex: 0 0 auto;
}

.btn {
  display: inline-flex;

  align-items: center;

  justify-content: center;

  gap: 7px;

  min-height: 36px;

  padding: 0 13px;

  border: 1px solid transparent;

  border-radius: 9px;

  font-size: 11px;

  font-weight: 700;

  cursor: pointer;

  transition: border-color 0.16s ease, background 0.16s ease, transform 0.16s ease,
    opacity 0.16s ease;
}

.btn:hover:not(:disabled) {
  transform: translateY(-1px);
}

.btn:disabled {
  cursor: not-allowed;

  opacity: 0.55;
}

.btn.primary {
  border-color: #08b878;

  background: #08b878;

  color: #fff;
}

.btn.primary:hover:not(:disabled) {
  background: #079f69;
}

.btn.secondary {
  border-color: #dce4e9;

  background: #fff;

  color: #51616e;
}

.btn.secondary:hover:not(:disabled) {
  border-color: #cbd7de;

  background: #f8fafb;
}

.operation-message {
  display: flex;

  align-items: center;

  gap: 10px;

  margin-bottom: 16px;

  padding: 11px 13px;

  border: 1px solid #dce5ea;

  border-radius: 10px;

  background: #fff;
}

.operation-message.success {
  border-color: rgba(8, 184, 120, 0.2);

  background: rgba(8, 184, 120, 0.055);
}

.operation-message.error {
  border-color: rgba(205, 74, 74, 0.2);

  background: rgba(205, 74, 74, 0.045);
}

.message-icon {
  display: flex;

  align-items: center;

  justify-content: center;

  width: 29px;

  height: 29px;

  flex: 0 0 29px;

  border-radius: 8px;

  background: rgba(8, 184, 120, 0.1);

  color: #08a970;
}

.operation-message.error .message-icon {
  background: rgba(205, 74, 74, 0.09);

  color: #c34c4c;
}

.operation-message > div {
  display: flex;

  flex-direction: column;

  gap: 2px;

  min-width: 0;
}

.operation-message strong {
  color: #24333f;

  font-size: 11px;
}

.operation-message span:not(.message-icon) {
  color: #758693;

  font-size: 10px;
}

.operation-message > button {
  margin-left: auto;

  border: 0;

  background: transparent;

  color: #82909a;

  font-size: 18px;

  line-height: 1;

  cursor: pointer;
}

.stat-grid {
  display: grid;

  grid-template-columns: repeat(4, minmax(0, 1fr));

  gap: 12px;

  margin-bottom: 14px;
}

.stat-card {
  display: flex;

  align-items: center;

  gap: 11px;

  min-width: 0;

  min-height: 78px;

  padding: 12px 14px;

  border: 1px solid #e5ebef;

  border-radius: 12px;

  background: #fff;

  box-shadow: 0 5px 18px rgba(22, 34, 45, 0.025);
}

.stat-icon {
  display: flex;

  align-items: center;

  justify-content: center;

  width: 32px;

  height: 32px;

  flex: 0 0 32px;

  border-radius: 9px;

  background: rgba(8, 184, 120, 0.08);

  color: #08a970;
}

.stat-icon.success {
  background: rgba(8, 184, 120, 0.09);

  color: #08a970;
}

.stat-icon.warning {
  background: rgba(210, 151, 48, 0.1);

  color: #b47c21;
}

.stat-card > div:last-child {
  display: flex;

  flex-direction: column;

  gap: 2px;

  min-width: 0;
}

.stat-card span {
  color: #87949f;

  font-size: 9px;

  font-weight: 650;
}

.stat-card strong {
  color: #1c2b36;

  font-size: 17px;

  font-weight: 750;
}

.module-panel {
  overflow: hidden;

  border: 1px solid #e2e9ed;

  border-radius: 14px;

  background: #fff;

  box-shadow: 0 7px 24px rgba(22, 34, 45, 0.035);
}

.panel-toolbar {
  display: flex;

  align-items: center;

  justify-content: space-between;

  gap: 14px;

  padding: 15px;

  border-bottom: 1px solid #edf1f3;
}

.toolbar-heading {
  min-width: 0;
}

.section-kicker {
  display: block;

  margin-bottom: 4px;

  color: #8997a2;

  font-size: 8px;

  font-weight: 800;

  letter-spacing: 0.13em;
}

.toolbar-title-row {
  display: flex;

  align-items: center;

  gap: 8px;
}

.toolbar-title-row h2 {
  margin: 0;

  color: #20303b;

  font-size: 15px;

  font-weight: 750;
}

.record-count {
  display: inline-flex;

  align-items: center;

  justify-content: center;

  min-width: 22px;

  height: 19px;

  padding: 0 6px;

  border-radius: 6px;

  background: #f0f4f6;

  color: #71808b;

  font-size: 9px;

  font-weight: 750;
}

.toolbar-heading p {
  max-width: 650px;

  margin: 5px 0 0;

  color: #8996a0;

  font-size: 10px;

  line-height: 1.5;
}

.toolbar-controls {
  display: flex;

  align-items: center;

  gap: 7px;

  flex: 0 0 auto;
}

.search-control {
  display: flex;

  align-items: center;

  gap: 7px;

  width: min(360px, 100%);

  height: 34px;

  padding: 0 10px;

  border: 1px solid #dfe7eb;

  border-radius: 8px;

  background: #fbfcfd;

  color: #94a0a9;
}

.search-control:focus-within {
  border-color: rgba(8, 184, 120, 0.45);

  background: #fff;
}

.search-control input {
  width: 100%;

  min-width: 0;

  border: 0;

  outline: 0;

  background: transparent;

  color: #344550;

  font: inherit;

  font-size: 10px;
}

.search-control input::placeholder {
  color: #a0abb3;
}

.filter-control {
  height: 34px;

  padding: 0 11px;

  border: 1px solid #dfe7eb;

  border-radius: 8px;

  outline: 0;

  background: #fbfcfd;

  color: #566671;

  font-size: 10px;

  font-weight: 650;
}

.loading-state,
.empty-state {
  display: flex;

  align-items: center;

  justify-content: center;

  flex-direction: column;

  min-height: 280px;

  gap: 7px;

  padding: 35px;

  text-align: center;
}

.loading-state {
  flex-direction: row;

  justify-content: flex-start;

  text-align: left;

  padding: 30px 20px;
}

.loading-spinner {
  width: 17px;

  height: 17px;

  flex: 0 0 17px;

  border: 2px solid #dfe8ec;

  border-top-color: #08b878;

  border-radius: 50%;

  animation: spin 0.7s linear infinite;
}

.loading-state > div {
  display: flex;

  flex-direction: column;

  gap: 3px;
}

.loading-state strong,
.empty-state strong {
  color: #34444f;

  font-size: 11px;
}

.loading-state span:not(.loading-spinner),
.empty-state > span {
  color: #8a98a2;

  font-size: 10px;

  line-height: 1.5;
}

.empty-icon {
  display: flex;

  align-items: center;

  justify-content: center;

  width: 48px;

  height: 48px;

  margin-bottom: 4px;

  border: 1px solid #e2e9ed;

  border-radius: 14px;

  background: #f7f9fa;

  color: #7f909c;
}

.empty-state .btn {
  margin-top: 9px;
}

.table-wrap {
  width: 100%;

  overflow-x: auto;
}

.module-table {
  width: 100%;

  min-width: 780px;

  border-collapse: collapse;
}

.module-table th {
  padding: 10px 17px;

  border-bottom: 1px solid #edf1f3;

  background: #fbfcfd;

  color: #87949e;

  font-size: 8px;

  font-weight: 800;

  letter-spacing: 0.08em;

  text-align: left;

  text-transform: uppercase;
}

.module-table td {
  padding: 12px 17px;

  border-bottom: 1px solid #f0f3f5;

  vertical-align: middle;
}

.module-table tbody tr:last-child td {
  border-bottom: 0;
}

.module-table tbody tr {
  transition: background 0.14s ease;
}

.module-table tbody tr:hover {
  background: #fbfdfc;
}

.module-identity {
  display: flex;

  align-items: center;

  gap: 10px;

  min-width: 280px;
}

.module-avatar {
  display: flex;

  align-items: center;

  justify-content: center;

  width: 34px;

  height: 34px;

  flex: 0 0 34px;

  border: 1px solid rgba(8, 184, 120, 0.14);

  border-radius: 10px;

  background: rgba(8, 184, 120, 0.07);

  color: #079e69;

  font-size: 12px;

  font-weight: 800;
}

.module-copy {
  display: flex;

  flex-direction: column;

  min-width: 0;

  gap: 2px;
}

.module-copy strong {
  overflow: hidden;

  color: #2a3a45;

  font-size: 11px;

  font-weight: 750;

  text-overflow: ellipsis;

  white-space: nowrap;
}

.module-copy span {
  overflow: hidden;

  color: #7c8b96;

  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

  font-size: 9px;

  text-overflow: ellipsis;

  white-space: nowrap;
}

.module-copy small {
  max-width: 470px;

  overflow: hidden;

  color: #9aa5ad;

  font-size: 9px;

  line-height: 1.35;

  text-overflow: ellipsis;

  white-space: nowrap;
}

.type-badge,
.status-badge {
  display: inline-flex;

  align-items: center;

  gap: 6px;

  min-height: 23px;

  padding: 0 8px;

  border-radius: 7px;

  font-size: 9px;

  font-weight: 750;
}

.type-badge.system {
  background: rgba(83, 103, 118, 0.08);

  color: #61727f;
}

.type-badge.custom {
  background: rgba(8, 184, 120, 0.08);

  color: #078f60;
}

.status-badge.active {
  background: rgba(8, 184, 120, 0.08);

  color: #078f60;
}

.status-badge.inactive {
  background: rgba(133, 146, 156, 0.1);

  color: #778691;
}

.status-dot {
  width: 5px;

  height: 5px;

  flex: 0 0 5px;

  border-radius: 50%;

  background: currentColor;
}

.sort-order {
  display: inline-flex;

  align-items: center;

  justify-content: center;

  min-width: 29px;

  height: 23px;

  border-radius: 7px;

  background: #f4f6f7;

  color: #71808b;

  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

  font-size: 9px;

  font-weight: 700;
}

.actions-column {
  width: 110px;

  text-align: right !important;
}

.table-action {
  display: inline-flex;

  align-items: center;

  gap: 5px;

  min-height: 28px;

  padding: 0 8px;

  border: 1px solid #e0e7eb;

  border-radius: 7px;

  background: #fff;

  color: #61717d;

  font-size: 9px;

  font-weight: 700;

  cursor: pointer;
}

.table-action:hover {
  border-color: rgba(8, 184, 120, 0.35);

  background: rgba(8, 184, 120, 0.04);

  color: #079f69;
}

.security-note {
  display: flex;

  align-items: flex-start;

  gap: 10px;

  margin-top: 12px;

  padding: 12px 14px;

  border: 1px solid rgba(8, 184, 120, 0.14);

  border-radius: 10px;

  background: rgba(8, 184, 120, 0.035);
}

.security-note-icon {
  display: flex;

  align-items: center;

  justify-content: center;

  width: 28px;

  height: 28px;

  flex: 0 0 28px;

  border-radius: 8px;

  background: rgba(8, 184, 120, 0.08);

  color: #08a970;
}

.security-note div:last-child {
  display: flex;

  flex-direction: column;

  gap: 3px;
}

.security-note strong {
  color: #365046;

  font-size: 10px;
}

.security-note span {
  color: #82918b;

  font-size: 9px;

  line-height: 1.5;
}

.modal-backdrop {
  position: fixed;

  z-index: 100;

  inset: 0;

  display: flex;

  align-items: center;

  justify-content: center;

  padding: 24px;

  background: rgba(19, 29, 37, 0.48);

  backdrop-filter: blur(5px);
}

.module-modal {
  width: min(700px, 100%);

  max-height: calc(100vh - 48px);

  overflow: auto;

  border: 1px solid rgba(255, 255, 255, 0.55);

  border-radius: 15px;

  background: #fff;

  box-shadow: 0 25px 80px rgba(12, 25, 34, 0.22);
}

.modal-header {
  display: flex;

  align-items: flex-start;

  justify-content: space-between;

  gap: 20px;

  padding: 18px 20px;

  border-bottom: 1px solid #edf1f3;
}

.modal-title {
  display: flex;

  align-items: flex-start;

  gap: 10px;
}

.modal-icon {
  display: flex;

  align-items: center;

  justify-content: center;

  width: 35px;

  height: 35px;

  flex: 0 0 35px;

  border-radius: 10px;

  background: rgba(8, 184, 120, 0.08);

  color: #08a970;
}

.modal-title h2 {
  margin: 0;

  color: #20303b;

  font-size: 16px;

  font-weight: 750;
}

.modal-title p {
  margin: 4px 0 0;

  color: #87949e;

  font-size: 10px;
}

.modal-close {
  display: flex;

  align-items: center;

  justify-content: center;

  width: 28px;

  height: 28px;

  border: 1px solid #e1e8ec;

  border-radius: 8px;

  background: #fff;

  color: #778691;

  font-size: 17px;

  cursor: pointer;
}

.modal-close:hover {
  background: #f6f8f9;
}

.module-form {
  padding: 18px 20px 20px;
}

.system-warning {
  display: flex;

  align-items: flex-start;

  gap: 9px;

  margin-bottom: 16px;

  padding: 10px 11px;

  border: 1px solid rgba(180, 124, 33, 0.2);

  border-radius: 9px;

  background: rgba(210, 151, 48, 0.055);

  color: #a17127;
}

.system-warning > div {
  display: flex;

  flex-direction: column;

  gap: 2px;
}

.system-warning strong {
  font-size: 10px;
}

.system-warning span {
  color: #96784b;

  font-size: 9px;

  line-height: 1.45;
}

.form-grid {
  display: grid;

  grid-template-columns: repeat(2, minmax(0, 1fr));

  gap: 13px;
}

.form-field {
  display: flex;

  flex-direction: column;

  gap: 6px;
}

.form-field.full {
  grid-column: 1 / -1;
}

.form-field > span:first-child {
  color: #455762;

  font-size: 10px;

  font-weight: 750;
}

.form-field > span b {
  color: #c24d4d;
}

.form-field input,
.form-field textarea {
  width: 100%;

  box-sizing: border-box;

  border: 1px solid #dce5ea;

  border-radius: 8px;

  outline: 0;

  background: #fbfcfd;

  color: #344550;

  font-family: inherit;

  font-size: 10px;

  transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
}

.form-field input {
  height: 35px;

  padding: 0 10px;
}

.form-field textarea {
  min-height: 88px;

  resize: vertical;

  padding: 9px 10px;

  line-height: 1.5;
}

.form-field input:focus,
.form-field textarea:focus {
  border-color: rgba(8, 184, 120, 0.48);

  background: #fff;

  box-shadow: 0 0 0 3px rgba(8, 184, 120, 0.06);
}

.form-field input:disabled,
.form-field textarea:disabled {
  background: #f5f7f8;

  color: #8d9aa3;

  cursor: not-allowed;
}

.field-hint {
  color: #97a3ab;

  font-size: 8px;

  line-height: 1.4;
}

.field-error {
  color: #c14d4d;

  font-size: 8px;

  font-weight: 650;
}

.status-toggle {
  display: flex;

  align-items: center;

  gap: 8px;

  min-height: 35px;
}

.status-toggle input {
  position: absolute;

  width: 1px;

  height: 1px;

  opacity: 0;

  pointer-events: none;
}

.toggle-track {
  position: relative;

  width: 34px;

  height: 19px;

  flex: 0 0 34px;

  border-radius: 999px;

  background: #cdd7dd;

  transition: background 0.16s ease;
}

.toggle-thumb {
  position: absolute;

  top: 3px;

  left: 3px;

  width: 13px;

  height: 13px;

  border-radius: 50%;

  background: #fff;

  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);

  transition: transform 0.16s ease;
}

.status-toggle input:checked + .toggle-track {
  background: #08b878;
}

.status-toggle input:checked + .toggle-track .toggle-thumb {
  transform: translateX(15px);
}

.status-toggle.disabled {
  opacity: 0.55;
}

.status-toggle strong {
  color: #53636e;

  font-size: 10px;
}

.modal-footer {
  display: flex;

  justify-content: flex-end;

  gap: 8px;

  margin-top: 18px;

  padding-top: 15px;

  border-top: 1px solid #edf1f3;
}

.message-enter-active,
.message-leave-active,
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.18s ease;
}

.message-enter-from,
.message-leave-to,
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .module-modal,
.modal-leave-active .module-modal {
  transition: transform 0.18s ease, opacity 0.18s ease;
}

.modal-enter-from .module-modal,
.modal-leave-to .module-modal {
  transform: translateY(8px) scale(0.99);

  opacity: 0;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1100px) {
  .stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .panel-toolbar {
    align-items: stretch;

    flex-direction: column;
  }

  .toolbar-controls {
    width: 100%;
  }

  .search-control {
    flex: 1;

    width: auto;
  }
}

@media (max-width: 760px) {
  .modules-page {
    padding: 0;
  }

  .page-header {
    align-items: stretch;

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

  .toolbar-controls {
    align-items: stretch;

    flex-direction: column;
  }

  .search-control {
    width: 100%;
  }

  .filter-control {
    width: 100%;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .form-field.full {
    grid-column: auto;
  }

  .modal-backdrop {
    align-items: flex-end;

    padding: 0;
  }

  .module-modal {
    max-height: 92vh;

    border-radius: 15px 15px 0 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  \*,
  \*::before,
  \*::after {
    scroll-behavior: auto !important;

    transition: none !important;

    animation: none !important;
  }
}
</style>
