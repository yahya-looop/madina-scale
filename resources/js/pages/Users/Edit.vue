<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";

import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Role {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  isSystem: boolean;
  isActive: boolean;
}

interface User {
  id: number;
  name: string;
  email: string;
  status: "active" | "inactive";
  roles?: Role[];
  created_at?: string | null;
  updated_at?: string | null;
}

interface Capabilities {
  canView: boolean;
  canUpdate: boolean;
  isDeveloper?: boolean;
}

interface Props {
  user: User;
  roles?: Role[];
  capabilities?: Capabilities;
  userName?: string;
  userEmail?: string;
}

interface ApiErrorPayload {
  message?: string;
  errors?: Record<string, string | string[]>;
}

interface ApiSuccessPayload<T> {
  data: T;
}

class ApiRequestError extends Error {
  readonly status: number;
  readonly validationErrors: Record<string, string>;

  constructor(
    message: string,
    status: number,
    validationErrors: Record<string, string> = {}
  ) {
    super(message);
    this.name = "ApiRequestError";
    this.status = status;
    this.validationErrors = validationErrors;
  }
}

const props = withDefaults(defineProps<Props>(), {
  roles: () => [],
  capabilities: () => ({
    canView: false,
    canUpdate: false,
    isDeveloper: false,
  }),
  userName: "",
  userEmail: "",
});

const isSubmitting = ref(false);
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const formErrors = ref<Record<string, string>>({});

const operationMessage = ref("");
const operationMessageType = ref<"success" | "error">("success");

const form = reactive({
  name: props.user.name,
  email: props.user.email,
  password: "",
  password_confirmation: "",
  status: props.user.status,
  role_ids: (props.user.roles ?? [])
    .filter((role) => role.isActive && !role.isSystem)
    .map((role) => role.id),
});

const availableRoles = computed(() => {
  const roles = new Map<number, Role>();

  props.roles
    .filter((role) => role.isActive && !role.isSystem)
    .forEach((role) => {
      roles.set(role.id, role);
    });

  /*
   * Keep currently assigned roles available even if the roles
   * collection sent by the page is incomplete.
   */
  (props.user.roles ?? [])
    .filter((role) => role.isActive && !role.isSystem)
    .forEach((role) => {
      roles.set(role.id, role);
    });

  return Array.from(roles.values()).sort((a, b) => a.name.localeCompare(b.name));
});

const currentSystemRoles = computed(() =>
  (props.user.roles ?? []).filter((role) => role.isSystem)
);

const selectedRoleCount = computed(() => form.role_ids.length);

const initials = computed(() => {
  return props.user.name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join("");
});

const canEdit = computed(() => props.capabilities.canUpdate);

function apiRequest<T>(url: string, method: "PUT", body?: unknown): Promise<T> {
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

  return fetch(url, {
    method,
    headers,
    credentials: "same-origin",
    body: body === undefined ? undefined : JSON.stringify(body),
  }).then(async (response) => {
    const text = await response.text();

    let payload: unknown = null;

    if (text.trim() !== "") {
      try {
        payload = JSON.parse(text) as unknown;
      } catch {
        payload = null;
      }
    }

    if (!response.ok) {
      const errorPayload = (payload ?? {}) as ApiErrorPayload;

      const validationErrors: Record<string, string> = {};

      Object.entries(errorPayload.errors ?? {}).forEach(([key, value]) => {
        validationErrors[key] = Array.isArray(value)
          ? String(value[0] ?? "")
          : String(value);
      });

      throw new ApiRequestError(
        errorPayload.message ?? `Request failed with status ${response.status}.`,
        response.status,
        validationErrors
      );
    }

    if (payload && typeof payload === "object" && "data" in payload) {
      return (payload as ApiSuccessPayload<T>).data;
    }

    return payload as T;
  });
}

function toggleRole(roleId: number): void {
  if (!canEdit.value) {
    return;
  }

  const role = availableRoles.value.find((item) => item.id === roleId);

  if (!role || role.isSystem || !role.isActive) {
    return;
  }

  form.role_ids = form.role_ids.includes(roleId)
    ? form.role_ids.filter((id) => id !== roleId)
    : [...form.role_ids, roleId];

  clearFieldError("role_ids");
}

function hasError(field: string): boolean {
  return Boolean(formErrors.value[field]);
}

function clearFieldError(field: string): void {
  delete formErrors.value[field];
}

function validate(): boolean {
  formErrors.value = {};

  if (!form.name.trim()) {
    formErrors.value.name = "Name is required.";
  }

  if (!form.email.trim()) {
    formErrors.value.email = "Email is required.";
  }

  if (form.password && form.password.length < 8) {
    formErrors.value.password = "Password must be at least 8 characters.";
  }

  if (form.password || form.password_confirmation) {
    if (!form.password_confirmation) {
      formErrors.value.password_confirmation = "Please confirm the new password.";
    } else if (form.password !== form.password_confirmation) {
      formErrors.value.password_confirmation = "Passwords do not match.";
    }
  }

  return Object.keys(formErrors.value).length === 0;
}

async function submit(): Promise<void> {
  if (isSubmitting.value || !canEdit.value) {
    return;
  }

  operationMessage.value = "";

  if (!validate()) {
    operationMessageType.value = "error";
    operationMessage.value = "Please correct the highlighted fields.";
    return;
  }

  isSubmitting.value = true;

  try {
    /*
     * Only submit active, non-system roles.
     * System roles are always controlled by the platform.
     */
    const validRoleIds = form.role_ids.filter((roleId) =>
      availableRoles.value.some(
        (role) => role.id === roleId && role.isActive && !role.isSystem
      )
    );

    const payload: Record<string, unknown> = {
      name: form.name.trim(),
      email: form.email.trim(),
      status: form.status,
      role_ids: validRoleIds,
    };

    if (form.password.trim() !== "") {
      payload.password = form.password;
      payload.password_confirmation = form.password_confirmation;
    }

    await apiRequest<User>(`/api/users/${props.user.id}`, "PUT", payload);

    operationMessageType.value = "success";
    operationMessage.value = "User updated successfully.";

    form.password = "";
    form.password_confirmation = "";

    window.setTimeout(() => {
      router.visit(`/users/${props.user.id}`);
    }, 450);
  } catch (error) {
    if (error instanceof ApiRequestError) {
      formErrors.value = error.validationErrors;

      operationMessageType.value = "error";

      operationMessage.value =
        Object.keys(error.validationErrors).length > 0
          ? "Please correct the highlighted fields."
          : error.message;
    } else {
      operationMessageType.value = "error";
      operationMessage.value = "Unable to update the user. Please try again.";
    }
  } finally {
    isSubmitting.value = false;
  }
}

function cancel(): void {
  router.visit(`/users/${props.user.id}`);
}

function backToUsers(): void {
  router.visit("/users");
}

function formatDate(value?: string | null): string {
  if (!value) {
    return "—";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  return new Intl.DateTimeFormat(undefined, {
    year: "numeric",
    month: "short",
    day: "2-digit",
  }).format(date);
}
</script>

<template>
  <Head :title="`Edit ${props.user.name}`" />

  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <div class="user-edit-page">
      <!-- =========================================================
                 PAGE HEADER
                 ========================================================= -->
      <header class="page-header">
        <div class="page-header-copy">
          <div class="page-breadcrumb" aria-label="Breadcrumb">
            <button class="breadcrumb-link" type="button" @click="backToUsers">
              System
            </button>

            <span class="page-breadcrumb-separator" aria-hidden="true"> / </span>

            <button class="breadcrumb-link" type="button" @click="backToUsers">
              Identity &amp; Access
            </button>

            <span class="page-breadcrumb-separator" aria-hidden="true"> / </span>

            <span class="is-current"> Edit User </span>
          </div>

          <div class="page-title-row">
            <div class="page-title-icon" aria-hidden="true">
              <Icon name="users" :size="21" />
            </div>

            <div>
              <h1>Edit User</h1>

              <p>Update account information, access status and assigned roles.</p>
            </div>
          </div>
        </div>

        <div class="header-actions">
          <button
            class="btn btn-secondary"
            type="button"
            :disabled="isSubmitting"
            @click="cancel"
          >
            <Icon name="arrow-left" :size="15" />
            Cancel
          </button>

          <button
            v-if="canEdit"
            class="btn btn-primary"
            type="button"
            :disabled="isSubmitting"
            @click="submit"
          >
            <Icon name="check" :size="15" />

            {{ isSubmitting ? "Saving..." : "Save changes" }}
          </button>
        </div>
      </header>

      <!-- =========================================================
                 OPERATION MESSAGE
                 ========================================================= -->
      <div
        v-if="operationMessage"
        class="operation-message"
        :class="operationMessageType"
        role="status"
      >
        <Icon :name="operationMessageType === 'success' ? 'check' : 'alert'" :size="15" />

        {{ operationMessage }}
      </div>

      <!-- =========================================================
                 PERMISSION NOTICE
                 ========================================================= -->
      <div v-if="!canEdit" class="operation-message error" role="alert">
        <Icon name="lock" :size="15" />

        You do not have permission to update this user.
      </div>

      <!-- =========================================================
                 FORM
                 ========================================================= -->
      <form v-if="canEdit" class="content-grid" @submit.prevent="submit">
        <!-- =====================================================
                     BASIC INFORMATION
                     ===================================================== -->
        <section class="panel">
          <div class="panel-heading">
            <div>
              <span class="section-kicker"> Profile </span>

              <h2>Basic information</h2>
            </div>

            <span class="required-note"> * Required </span>
          </div>

          <div class="form-grid">
            <label class="field full">
              <span>
                Full name
                <em>*</em>
              </span>

              <input
                v-model="form.name"
                type="text"
                autocomplete="name"
                placeholder="e.g. John Doe"
                :class="{
                  invalid: hasError('name'),
                }"
                @input="clearFieldError('name')"
              />

              <small v-if="formErrors.name">
                {{ formErrors.name }}
              </small>
            </label>

            <label class="field full">
              <span>
                Email address
                <em>*</em>
              </span>

              <input
                v-model="form.email"
                type="email"
                autocomplete="email"
                placeholder="user@example.com"
                :class="{
                  invalid: hasError('email'),
                }"
                @input="clearFieldError('email')"
              />

              <small v-if="formErrors.email">
                {{ formErrors.email }}
              </small>
            </label>

            <label class="field">
              <span> Account status </span>

              <select
                v-model="form.status"
                :class="{
                  invalid: hasError('status'),
                }"
                @change="clearFieldError('status')"
              >
                <option value="active">Active</option>

                <option value="inactive">Inactive</option>
              </select>

              <small v-if="formErrors.status">
                {{ formErrors.status }}
              </small>
            </label>

            <div class="field">
              <span> Account ID </span>

              <div class="readonly-value">#{{ props.user.id }}</div>
            </div>
          </div>
        </section>

        <!-- =====================================================
                     SECURITY
                     ===================================================== -->
        <section class="panel">
          <div class="panel-heading">
            <div>
              <span class="section-kicker"> Security </span>

              <h2>Change password</h2>
            </div>

            <span class="optional-note"> Optional </span>
          </div>

          <p class="panel-description">
            Leave both password fields empty to keep the current password unchanged.
          </p>

          <div class="form-grid">
            <label class="field">
              <span> New password </span>

              <div class="password-field">
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  placeholder="Minimum 8 characters"
                  :class="{
                    invalid: hasError('password'),
                  }"
                  @input="clearFieldError('password')"
                />

                <button
                  class="password-toggle"
                  type="button"
                  :aria-label="showPassword ? 'Hide password' : 'Show password'"
                  @click="showPassword = !showPassword"
                >
                  <Icon :name="showPassword ? 'eye-off' : 'eye'" :size="15" />
                </button>
              </div>

              <small v-if="formErrors.password">
                {{ formErrors.password }}
              </small>
            </label>

            <label class="field">
              <span> Confirm new password </span>

              <div class="password-field">
                <input
                  v-model="form.password_confirmation"
                  :type="showPasswordConfirmation ? 'text' : 'password'"
                  autocomplete="new-password"
                  placeholder="Repeat new password"
                  :class="{
                    invalid: hasError('password_confirmation'),
                  }"
                  @input="clearFieldError('password_confirmation')"
                />

                <button
                  class="password-toggle"
                  type="button"
                  :aria-label="
                    showPasswordConfirmation ? 'Hide password' : 'Show password'
                  "
                  @click="showPasswordConfirmation = !showPasswordConfirmation"
                >
                  <Icon :name="showPasswordConfirmation ? 'eye-off' : 'eye'" :size="15" />
                </button>
              </div>

              <small v-if="formErrors.password_confirmation">
                {{ formErrors.password_confirmation }}
              </small>
            </label>
          </div>
        </section>

        <!-- =====================================================
                     ROLES
                     ===================================================== -->
        <section class="panel roles-panel">
          <div class="panel-heading">
            <div>
              <span class="section-kicker"> Authorization </span>

              <h2>Assigned roles</h2>
            </div>

            <span class="selection-count"> {{ selectedRoleCount }} selected </span>
          </div>

          <p class="panel-description">
            Select the active non-system roles this user should receive.
          </p>

          <div v-if="availableRoles.length" class="role-grid">
            <button
              v-for="role in availableRoles"
              :key="role.id"
              class="role-option"
              :class="{
                selected: form.role_ids.includes(role.id),
              }"
              type="button"
              @click="toggleRole(role.id)"
            >
              <span class="role-check">
                <Icon v-if="form.role_ids.includes(role.id)" name="check" :size="13" />
              </span>

              <span class="role-copy">
                <strong>
                  {{ role.name }}
                </strong>

                <small>
                  {{ role.description || role.slug }}
                </small>
              </span>
            </button>
          </div>

          <div v-else class="empty-roles">
            <Icon name="lock" :size="18" />

            <div>
              <strong> No assignable roles </strong>

              <p>There are currently no active non-system roles available.</p>
            </div>
          </div>

          <div v-if="currentSystemRoles.length" class="system-role-notice">
            <Icon name="developer" :size="16" />

            <div>
              <strong> System role protected </strong>

              <p>
                {{ currentSystemRoles.map((role) => role.name).join(", ") }}
                is managed by the system and cannot be changed from this form.
              </p>
            </div>
          </div>
        </section>

        <!-- =====================================================
                     ACCOUNT METADATA
                     ===================================================== -->
        <section class="panel metadata-panel">
          <div class="panel-heading">
            <div>
              <span class="section-kicker"> Record </span>

              <h2>Account metadata</h2>
            </div>
          </div>

          <div class="metadata-grid">
            <article>
              <span>Created</span>

              <strong>
                {{ formatDate(props.user.created_at) }}
              </strong>
            </article>

            <article>
              <span>Last updated</span>

              <strong>
                {{ formatDate(props.user.updated_at) }}
              </strong>
            </article>

            <article>
              <span>Current status</span>

              <strong class="status" :class="form.status">
                <i></i>
                {{ form.status }}
              </strong>
            </article>

            <article>
              <span>Authorization</span>

              <strong>
                {{ props.capabilities.isDeveloper ? "Developer" : "Permission based" }}
              </strong>
            </article>
          </div>
        </section>

        <!-- =====================================================
                     FORM FOOTER
                     ===================================================== -->
        <footer class="form-footer">
          <span>
            <Icon name="lock" :size="13" />

            Authorization is enforced server-side.
          </span>

          <div class="footer-actions">
            <button
              class="btn btn-secondary"
              type="button"
              :disabled="isSubmitting"
              @click="cancel"
            >
              Cancel
            </button>

            <button class="btn btn-primary" type="submit" :disabled="isSubmitting">
              <Icon name="check" :size="15" />

              {{ isSubmitting ? "Saving..." : "Save changes" }}
            </button>
          </div>
        </footer>
      </form>
    </div>
  </ErpLayout>
</template>

<style scoped>
/* ================================================================
   PAGE
   ================================================================ */

.user-edit-page {
  width: auto;
  min-width: 0;
  margin-inline: -22px;
  padding: 16px 16px 28px;
  box-sizing: border-box;
  color: var(--text-primary, #17202a);

  animation: users-enter 500ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

/* ================================================================
   HEADER
   ================================================================ */

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 22px;

  animation: users-enter 480ms cubic-bezier(0.22, 1, 0.36, 1) 20ms both;
}

.page-header-copy {
  min-width: 0;
}

.page-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  color: var(--text-muted, #7b8794);
  font-size: 11px;
  font-weight: 600;
}

.breadcrumb-link {
  padding: 0;
  border: 0;
  color: inherit;
  background: transparent;
  font: inherit;
  cursor: pointer;
}

.breadcrumb-link:hover {
  color: var(--primary, #1f8f68);
}

.page-breadcrumb .is-current {
  color: var(--text-secondary, #596573);
}

.page-breadcrumb-separator {
  color: var(--border-strong, #dfe4eb);
}

.page-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-title-icon {
  display: inline-flex;
  width: 42px;
  height: 42px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border: 1px solid
    color-mix(in srgb, var(--primary, #1f8f68) 22%, var(--border, #e5e9ee));
  border-radius: 12px;
  color: var(--primary, #1f8f68);
  background: color-mix(in srgb, var(--primary, #1f8f68) 10%, var(--surface, #fff));
  box-shadow: 0 8px 22px color-mix(in srgb, var(--primary, #1f8f68) 9%, transparent);
}

.page-header h1 {
  margin: 0;
  font-size: 24px;
  line-height: 1.15;
  letter-spacing: -0.025em;
  font-weight: 750;
}

.page-header p {
  max-width: 620px;
  margin: 5px 0 0;
  color: var(--text-secondary, #596573);
  font-size: 12px;
  line-height: 1.5;
}

/* ================================================================
   BUTTONS
   ================================================================ */

.header-actions,
.footer-actions {
  display: flex;
  align-items: center;
  gap: 9px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid transparent;
  border-radius: 10px;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;

  transition: transform 140ms ease, border-color 140ms ease, background 140ms ease,
    opacity 140ms ease;
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.btn:not(:disabled):hover {
  transform: translateY(-1px);
}

.btn-primary {
  color: #fff;
  background: var(--primary, #1f8f68);
}

.btn-secondary {
  color: var(--text-primary, #17202a);
  border-color: var(--border, #e5e9ee);
  background: var(--surface, #fff);
}

/* ================================================================
   NOTICES
   ================================================================ */

.operation-message {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 18px;
  padding: 11px 14px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 10px;
  font-size: 13px;
}

.operation-message.success {
  border-color: #bfe5d3;
  color: #17714e;
  background: #f0fbf5;
}

.operation-message.error {
  border-color: #f1b8b8;
  color: #a52d2d;
  background: #fff6f6;
}

/* ================================================================
   CONTENT GRID
   ================================================================ */

.content-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.panel {
  min-width: 0;
  padding: 16px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 14px;
  background: var(--surface, #fff);
  box-shadow: 0 5px 18px rgb(15 23 42 / 3%);

  animation: users-enter 480ms cubic-bezier(0.22, 1, 0.36, 1) 80ms both;
}

.panel.roles-panel,
.panel.metadata-panel {
  grid-column: 1 / -1;
}

.panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 15px;
  margin-bottom: 16px;
}

.section-kicker {
  display: block;
  margin-bottom: 4px;
  color: var(--text-muted, #7b8794);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.panel-heading h2 {
  margin: 0;
  color: var(--text-primary, #17202a);
  font-size: 16px;
  letter-spacing: -0.015em;
}

.required-note,
.optional-note,
.selection-count {
  color: var(--text-muted, #7b8794);
  font-size: 11px;
  font-weight: 700;
}

.selection-count {
  color: var(--primary, #1f8f68);
}

.panel-description {
  margin: -4px 0 16px;
  color: var(--text-muted, #7b8794);
  font-size: 12px;
  line-height: 1.55;
}

/* ================================================================
   FORM
   ================================================================ */

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.field {
  display: grid;
  gap: 7px;
  min-width: 0;
}

.field.full {
  grid-column: 1 / -1;
}

.field > span {
  color: #394457;
  font-size: 12px;
  font-weight: 750;
}

.field em {
  color: #c24b4b;
  font-style: normal;
}

.field input,
.field select,
.readonly-value {
  width: 100%;
  min-height: 40px;
  box-sizing: border-box;
  padding: 0 12px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 9px;
  outline: 0;
  color: var(--text-primary, #18212f);
  background: var(--surface, #fff);
  font: inherit;
  font-size: 13px;
}

.field input::placeholder {
  color: #a1aab7;
}

.field input:focus,
.field select:focus {
  border-color: var(--primary, #1f8f68);
  box-shadow: 0 0 0 3px rgb(31 143 104 / 10%);
}

.field input.invalid,
.field select.invalid {
  border-color: #d85c5c;
}

.field small {
  color: #b33d3d;
  font-size: 11px;
}

.readonly-value {
  display: flex;
  align-items: center;
  color: var(--text-muted, #7a8495);
  background: var(--surface-muted, #f7f9fa);
}

/* ================================================================
   PASSWORD
   ================================================================ */

.password-field {
  position: relative;
}

.password-field input {
  padding-right: 42px;
}

.password-toggle {
  position: absolute;
  top: 50%;
  right: 7px;
  display: grid;
  width: 30px;
  height: 30px;
  place-items: center;
  transform: translateY(-50%);
  border: 0;
  border-radius: 7px;
  color: var(--text-muted, #7a8495);
  background: transparent;
  cursor: pointer;
}

.password-toggle:hover {
  color: var(--primary, #1f8f68);
  background: var(--surface-muted, #f3f5f7);
}

/* ================================================================
   ROLES
   ================================================================ */

.role-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.role-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  min-width: 0;
  padding: 12px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 10px;
  color: var(--text-primary, #18212f);
  background: var(--surface, #fff);
  text-align: left;
  cursor: pointer;

  transition: border-color 140ms ease, background 140ms ease, transform 140ms ease;
}

.role-option:hover {
  transform: translateY(-1px);
  border-color: #bfd8ce;
}

.role-option.selected {
  border-color: var(--primary, #1f8f68);
  background: var(--primary-soft, #eef9f4);
}

.role-check {
  display: grid;
  width: 21px;
  height: 21px;
  flex: 0 0 21px;
  place-items: center;
  border: 1px solid #d5dce3;
  border-radius: 6px;
  color: #fff;
  background: #fff;
}

.role-option.selected .role-check {
  border-color: var(--primary, #1f8f68);
  background: var(--primary, #1f8f68);
}

.role-copy {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.role-copy strong {
  overflow: hidden;
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.role-copy small {
  overflow: hidden;
  color: var(--text-muted, #7a8495);
  font-size: 10px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.empty-roles {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px;
  border: 1px dashed var(--border, #dfe5ea);
  border-radius: 10px;
  color: var(--text-muted, #7a8495);
  background: var(--surface-muted, #fafbfc);
}

.empty-roles strong {
  display: block;
  color: #394457;
  font-size: 12px;
}

.empty-roles p {
  margin: 3px 0 0;
  font-size: 11px;
}

.system-role-notice {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  margin-top: 14px;
  padding: 12px;
  border: 1px solid #dfd3f0;
  border-radius: 10px;
  color: #6c4b9e;
  background: #f8f4fc;
}

.system-role-notice strong {
  display: block;
  font-size: 12px;
}

.system-role-notice p {
  margin: 3px 0 0;
  color: #7d6999;
  font-size: 11px;
  line-height: 1.5;
}

/* ================================================================
   METADATA
   ================================================================ */

.metadata-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.metadata-grid article {
  padding: 13px;
  border: 1px solid var(--border, #edf0f3);
  border-radius: 10px;
  background: var(--surface-muted, #fafbfc);
}

.metadata-grid span {
  display: block;
  margin-bottom: 5px;
  color: var(--text-muted, #7a8495);
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
}

.metadata-grid strong {
  display: block;
  color: #394457;
  font-size: 12px;
}

.status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-transform: capitalize;
}

.status i {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.status.active {
  color: #17714e;
}

.status.inactive {
  color: #9a6518;
}

/* ================================================================
   FORM FOOTER
   ================================================================ */

.form-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  grid-column: 1 / -1;
  gap: 14px;
  padding: 4px 2px 0;
  color: var(--text-muted, #7a8495);
  font-size: 11px;
}

.form-footer > span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

/* ================================================================
   ANIMATION
   ================================================================ */

@keyframes users-enter {
  from {
    opacity: 0;
    transform: translateY(8px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .user-edit-page,
  .page-header,
  .panel {
    animation: none !important;
  }
}

/* ================================================================
   RESPONSIVE
   ================================================================ */

@media (max-width: 980px) {
  .content-grid {
    grid-template-columns: 1fr;
  }

  .panel.roles-panel,
  .panel.metadata-panel,
  .form-footer {
    grid-column: auto;
  }

  .role-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .metadata-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .user-edit-page {
    margin-inline: -22px;
    padding: 16px 12px 22px;
  }

  .page-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .page-header-copy {
    width: 100%;
  }

  .header-actions {
    width: 100%;
  }
}

@media (max-width: 700px) {
  .form-grid,
  .role-grid,
  .metadata-grid {
    grid-template-columns: 1fr;
  }

  .field.full {
    grid-column: auto;
  }

  .form-footer {
    align-items: flex-start;
    flex-direction: column;
  }
}

@media (max-width: 560px) {
  .user-edit-page {
    margin-inline: -22px;
    padding: 16px 10px 22px;
  }

  .page-title-row {
    align-items: flex-start;
  }

  .page-title-icon {
    width: 40px;
    height: 40px;
  }

  .page-header h1 {
    font-size: 22px;
  }

  .header-actions,
  .header-actions .btn {
    width: 100%;
  }

  .header-actions {
    flex-direction: column;
  }

  .footer-actions,
  .footer-actions .btn {
    width: 100%;
  }

  .footer-actions {
    flex-direction: column;
  }

  .panel {
    padding: 14px;
  }

  .page-breadcrumb {
    flex-wrap: wrap;
  }
}
</style>
