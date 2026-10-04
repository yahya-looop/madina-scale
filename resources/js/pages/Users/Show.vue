<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";

import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Role {
  id: number;
  name: string;
  slug: string;
  isSystem: boolean;
  isActive: boolean;
}

interface UserApiPayload {
  id: number;
  name: string;
  email: string;
  status: "active" | "inactive" | string;

  last_login_at?: string | null;
  last_login_ip?: string | null;

  failed_login_attempts?: number;
  locked_until?: string | null;
  password_changed_at?: string | null;

  email_verified_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;

  roles?: Role[];
}

interface User {
  id: number;
  name: string;
  email: string;
  status: "active" | "inactive" | string;

  lastLoginAt: string | null;
  lastLoginIp: string | null;

  failedLoginAttempts: number;
  lockedUntil: string | null;
  passwordChangedAt: string | null;

  emailVerifiedAt: string | null;
  createdAt: string | null;
  updatedAt: string | null;

  roles: Role[];
}

interface AuthUser {
  id: number;
  name: string;
  email: string;
}

interface PageProps {
  [key: string]: unknown;

  auth?: {
    user?: AuthUser;
  };
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

const page = usePage<PageProps>();

const authUser = computed<AuthUser>(() => {
  return (
    page.props.auth?.user ?? {
      id: 0,
      name: "Administrator",
      email: "",
    }
  );
});

/**
 * Users/Index navigates to /users/{id}.
 *
 * The ID is read directly from the pathname so this page works with:
 * /users/1
 * /users/25
 * /users/100/edit
 */
const userId = Number(window.location.pathname.match(/\/users\/(\d+)(?:\/|$)/)?.[1] ?? 0);

const user = ref<User | null>(null);

const isLoading = ref(true);
const isSaving = ref(false);
const isEditing = ref(false);

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const formErrors = ref<Record<string, string>>({});
const operationMessage = ref("");
const operationMessageType = ref<"success" | "error">("success");

const form = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  status: "active",
  role_ids: [] as number[],
});

/**
 * Normalize the API's snake_case payload into the frontend's
 * stable camelCase view model.
 *
 * This is intentionally kept here so the API contract can remain
 * Laravel-friendly without leaking naming differences into the UI.
 */
function normalizeUser(payload: UserApiPayload): User {
  return {
    id: payload.id,
    name: payload.name,
    email: payload.email,
    status: payload.status,

    lastLoginAt: payload.last_login_at ?? null,
    lastLoginIp: payload.last_login_ip ?? null,

    failedLoginAttempts: payload.failed_login_attempts ?? 0,
    lockedUntil: payload.locked_until ?? null,
    passwordChangedAt: payload.password_changed_at ?? null,

    emailVerifiedAt: payload.email_verified_at ?? null,
    createdAt: payload.created_at ?? null,
    updatedAt: payload.updated_at ?? null,

    roles: payload.roles ?? [],
  };
}

const initials = computed(() => {
  return (
    user.value?.name
      .trim()
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join("") ?? ""
  );
});

const activeRoles = computed(() => {
  return user.value?.roles.filter((role) => role.isActive) ?? [];
});

const systemRoles = computed(() => {
  return user.value?.roles.filter((role) => role.isSystem) ?? [];
});

const accountLocked = computed(() => {
  if (!user.value?.lockedUntil) {
    return false;
  }

  const timestamp = new Date(user.value.lockedUntil).getTime();

  return !Number.isNaN(timestamp) && timestamp > Date.now();
});

const hasLastLogin = computed(() => {
  return Boolean(user.value?.lastLoginAt);
});

const accountStatusLabel = computed(() => {
  if (!user.value) {
    return "Unknown";
  }

  return user.value.status.charAt(0).toUpperCase() + user.value.status.slice(1);
});

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
}

async function loadUser(): Promise<void> {
  if (!userId) {
    isLoading.value = false;
    operationMessageType.value = "error";
    operationMessage.value = "A valid user ID is required.";
    return;
  }

  isLoading.value = true;
  operationMessage.value = "";

  try {
    const payload = await apiRequest<UserApiPayload>(`/api/users/${userId}`, "GET");

    const normalizedUser = normalizeUser(payload);

    user.value = normalizedUser;

    syncFormFromUser(normalizedUser);
  } catch (error) {
    operationMessageType.value = "error";
    operationMessage.value =
      error instanceof Error ? error.message : "Unable to load the user.";
  } finally {
    isLoading.value = false;
  }
}

function syncFormFromUser(currentUser: User): void {
  form.name = currentUser.name;
  form.email = currentUser.email;
  form.status = currentUser.status;

  form.role_ids = currentUser.roles
    .filter((role) => role.isActive && !role.isSystem)
    .map((role) => role.id);

  form.password = "";
  form.password_confirmation = "";
}

function toggleRole(roleId: number): void {
  form.role_ids = form.role_ids.includes(roleId)
    ? form.role_ids.filter((id) => id !== roleId)
    : [...form.role_ids, roleId];
}

function clearFieldError(field: string): void {
  delete formErrors.value[field];
}

async function save(): Promise<void> {
  if (!user.value || isSaving.value) {
    return;
  }

  formErrors.value = {};
  operationMessage.value = "";

  if (!form.name.trim()) {
    formErrors.value.name = "Name is required.";
  }

  if (!form.email.trim()) {
    formErrors.value.email = "Email is required.";
  }

  if (form.password && form.password !== form.password_confirmation) {
    formErrors.value.password_confirmation = "Passwords do not match.";
  }

  if (Object.keys(formErrors.value).length > 0) {
    return;
  }

  isSaving.value = true;

  const payload: Record<string, unknown> = {
    name: form.name.trim(),
    email: form.email.trim(),
    status: form.status,
    role_ids: form.role_ids,
  };

  if (form.password) {
    payload.password = form.password;
    payload.password_confirmation = form.password_confirmation;
  }

  try {
    const response = await apiRequest<UserApiPayload>(
      `/api/users/${user.value.id}`,
      "PUT",
      payload
    );

    const normalizedUser = normalizeUser(response);

    user.value = normalizedUser;

    syncFormFromUser(normalizedUser);

    isEditing.value = false;

    operationMessageType.value = "success";
    operationMessage.value = "User updated successfully.";
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
    isSaving.value = false;
  }
}

function startEditing(): void {
  if (!user.value) {
    return;
  }

  formErrors.value = {};
  operationMessage.value = "";

  syncFormFromUser(user.value);

  isEditing.value = true;
}

function cancelEdit(): void {
  if (!user.value) {
    return;
  }

  syncFormFromUser(user.value);

  formErrors.value = {};
  operationMessage.value = "";

  isEditing.value = false;
}

function back(): void {
  router.get("/users");
}

function formatDate(value?: string | null, fallback = "Never"): string {
  if (!value) {
    return fallback;
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat(undefined, {
    year: "numeric",
    month: "short",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

function formatDateOnly(value?: string | null): string {
  if (!value) {
    return "—";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat(undefined, {
    year: "numeric",
    month: "short",
    day: "2-digit",
  }).format(date);
}

onMounted(() => {
  void loadUser();
});
</script>

<template>
  <Head :title="user ? `User — ${user.name}` : 'User Details'" />

  <ErpLayout :user-name="authUser.name" :user-email="authUser.email">
    <div class="user-show-page">
      <!-- Loading -->
      <section v-if="isLoading" class="state-page page-enter" aria-live="polite">
        <div class="state-icon">
          <Icon name="users" :size="24" />
        </div>

        <strong>Loading user...</strong>

        <span> Retrieving account information and access details. </span>
      </section>

      <!-- Loaded -->
      <template v-else-if="user">
        <div class="page-content page-enter">
          <!-- Page header -->
          <header class="page-header">
            <div class="heading-copy">
              <nav class="breadcrumb" aria-label="Breadcrumb">
                <button class="breadcrumb-link" type="button" @click="back">
                  <Icon name="users" :size="14" />
                  <span>Users</span>
                </button>

                <span class="breadcrumb-separator">/</span>

                <span class="breadcrumb-current"> User details </span>
              </nav>

              <div class="title-row">
                <div class="page-title-icon">
                  <Icon name="users" :size="22" />
                </div>

                <div>
                  <div class="eyebrow">Identity Management</div>

                  <h1>{{ user.name }}</h1>

                  <p>View account details, security state and assigned access.</p>
                </div>
              </div>
            </div>

            <div class="header-actions">
              <button
                v-if="!isEditing"
                class="btn btn-primary"
                type="button"
                @click="startEditing"
              >
                <Icon name="settings" :size="15" />
                Edit User
              </button>

              <template v-else>
                <button
                  class="btn btn-secondary"
                  type="button"
                  :disabled="isSaving"
                  @click="cancelEdit"
                >
                  Cancel
                </button>

                <button
                  class="btn btn-primary"
                  type="button"
                  :disabled="isSaving"
                  @click="save"
                >
                  <Icon v-if="!isSaving" name="check" :size="15" />

                  {{ isSaving ? "Saving..." : "Save Changes" }}
                </button>
              </template>
            </div>
          </header>

          <!-- Operation notice -->
          <div
            v-if="operationMessage"
            class="operation-message"
            :class="operationMessageType"
            role="status"
          >
            <Icon
              :name="operationMessageType === 'success' ? 'check' : 'lock'"
              :size="15"
            />

            <span>{{ operationMessage }}</span>
          </div>

          <!-- Summary -->
          <section class="summary-grid" aria-label="User account summary">
            <article class="summary-card">
              <div class="summary-icon">
                <Icon name="check" :size="16" />
              </div>

              <div>
                <span>Status</span>

                <strong class="status-value" :class="user.status">
                  {{ accountStatusLabel }}
                </strong>
              </div>
            </article>

            <article class="summary-card">
              <div class="summary-icon">
                <Icon name="users" :size="16" />
              </div>

              <div>
                <span>Active roles</span>
                <strong>{{ activeRoles.length }}</strong>
              </div>
            </article>

            <article class="summary-card">
              <div class="summary-icon warning">
                <Icon name="lock" :size="16" />
              </div>

              <div>
                <span>Failed logins</span>
                <strong>
                  {{ user.failedLoginAttempts }}
                </strong>
              </div>
            </article>

            <article class="summary-card">
              <div class="summary-icon">
                <Icon name="clock" :size="16" />
              </div>

              <div>
                <span>Last login</span>

                <strong>
                  {{ hasLastLogin ? formatDate(user.lastLoginAt) : "Never" }}
                </strong>
              </div>
            </article>
          </section>

          <!-- Main content -->
          <div class="content-grid">
            <!-- Account -->
            <section class="panel">
              <header class="panel-heading">
                <div>
                  <span class="section-kicker"> Account </span>

                  <h2>Profile &amp; security</h2>

                  <p>Manage identity information and authentication settings.</p>
                </div>

                <div class="panel-icon">
                  <Icon name="settings" :size="17" />
                </div>
              </header>

              <div class="form-grid">
                <label class="field">
                  <span>Name</span>

                  <input
                    v-model="form.name"
                    type="text"
                    autocomplete="name"
                    :disabled="!isEditing"
                    :class="{
                      invalid: formErrors.name,
                    }"
                    @input="clearFieldError('name')"
                  />

                  <small v-if="formErrors.name">
                    {{ formErrors.name }}
                  </small>
                </label>

                <label class="field">
                  <span>Email</span>

                  <input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    :disabled="!isEditing"
                    :class="{
                      invalid: formErrors.email,
                    }"
                    @input="clearFieldError('email')"
                  />

                  <small v-if="formErrors.email">
                    {{ formErrors.email }}
                  </small>
                </label>

                <label class="field">
                  <span>Status</span>

                  <select v-model="form.status" :disabled="!isEditing">
                    <option value="active">Active</option>

                    <option value="inactive">Inactive</option>
                  </select>
                </label>

                <div class="field">
                  <span>Account ID</span>

                  <div class="readonly-value">#{{ user.id }}</div>
                </div>

                <label v-if="isEditing" class="field">
                  <span>New password</span>

                  <div class="input-with-action">
                    <input
                      v-model="form.password"
                      :type="showPassword ? 'text' : 'password'"
                      autocomplete="new-password"
                      placeholder="Leave blank to keep current password"
                      :class="{
                        invalid: formErrors.password,
                      }"
                      @input="clearFieldError('password')"
                    />

                    <button
                      type="button"
                      class="input-action"
                      @click="showPassword = !showPassword"
                    >
                      {{ showPassword ? "Hide" : "Show" }}
                    </button>
                  </div>

                  <small v-if="formErrors.password">
                    {{ formErrors.password }}
                  </small>
                </label>

                <label v-if="isEditing" class="field">
                  <span>Confirm new password</span>

                  <div class="input-with-action">
                    <input
                      v-model="form.password_confirmation"
                      :type="showPasswordConfirmation ? 'text' : 'password'"
                      autocomplete="new-password"
                      placeholder="Repeat new password"
                      :class="{
                        invalid: formErrors.password_confirmation,
                      }"
                      @input="clearFieldError('password_confirmation')"
                    />

                    <button
                      type="button"
                      class="input-action"
                      @click="showPasswordConfirmation = !showPasswordConfirmation"
                    >
                      {{ showPasswordConfirmation ? "Hide" : "Show" }}
                    </button>
                  </div>

                  <small v-if="formErrors.password_confirmation">
                    {{ formErrors.password_confirmation }}
                  </small>
                </label>
              </div>

              <div class="security-grid">
                <div class="security-item">
                  <span>Last login</span>

                  <strong>
                    {{ formatDate(user.lastLoginAt, "Never") }}
                  </strong>
                </div>

                <div class="security-item">
                  <span>Login IP</span>

                  <strong>
                    {{ user.lastLoginIp ?? "Not available" }}
                  </strong>
                </div>

                <div class="security-item">
                  <span>Password changed</span>

                  <strong>
                    {{ formatDate(user.passwordChangedAt, "Not recorded") }}
                  </strong>
                </div>

                <div class="security-item">
                  <span>Lock status</span>

                  <strong
                    :class="{
                      'security-warning': accountLocked,
                    }"
                  >
                    {{
                      accountLocked
                        ? `Locked until ${formatDate(user.lockedUntil)}`
                        : "Not locked"
                    }}
                  </strong>
                </div>
              </div>
            </section>

            <!-- Roles -->
            <section class="panel">
              <header class="panel-heading">
                <div>
                  <span class="section-kicker"> Access Control </span>

                  <h2>Assigned roles</h2>

                  <p>Roles currently attached to this account.</p>
                </div>

                <span class="selection-count"> {{ form.role_ids.length }} selected </span>
              </header>

              <div class="role-list">
                <article v-for="role in user.roles" :key="role.id" class="role-row">
                  <span class="role-icon">
                    <Icon :name="role.isSystem ? 'developer' : 'users'" :size="14" />
                  </span>

                  <div class="role-copy">
                    <strong>{{ role.name }}</strong>

                    <small>{{ role.slug }}</small>
                  </div>

                  <span
                    class="role-badge"
                    :class="{
                      system: role.isSystem,
                      inactive: !role.isActive,
                    }"
                  >
                    {{ role.isSystem ? "System" : role.isActive ? "Active" : "Inactive" }}
                  </span>
                </article>

                <div v-if="user.roles.length === 0" class="empty-state">
                  <div class="empty-icon">
                    <Icon name="users" :size="19" />
                  </div>

                  <strong>No roles assigned</strong>

                  <span> This account currently has no assigned application roles. </span>
                </div>
              </div>

              <div v-if="isEditing" class="edit-role-list">
                <div class="edit-role-heading">
                  <div>
                    <span class="section-kicker"> Editable Access </span>

                    <strong> Assignable roles </strong>
                  </div>

                  <span>
                    {{ form.role_ids.length }}
                    selected
                  </span>
                </div>

                <button
                  v-for="role in user.roles.filter(
                    (item) => item.isActive && !item.isSystem
                  )"
                  :key="`edit-${role.id}`"
                  type="button"
                  class="edit-role-button"
                  :class="{
                    selected: form.role_ids.includes(role.id),
                  }"
                  @click="toggleRole(role.id)"
                >
                  <span class="role-check">
                    {{ form.role_ids.includes(role.id) ? "✓" : "" }}
                  </span>

                  <span>
                    <strong>{{ role.name }}</strong>
                    <small>{{ role.slug }}</small>
                  </span>
                </button>

                <div
                  v-if="
                    user.roles.filter((item) => item.isActive && !item.isSystem)
                      .length === 0
                  "
                  class="role-edit-empty"
                >
                  No editable non-system roles are available for this user.
                </div>
              </div>
            </section>
          </div>

          <!-- Metadata -->
          <section class="metadata-grid">
            <article class="metadata-card">
              <div class="metadata-icon">
                <Icon name="calendar" :size="15" />
              </div>

              <div>
                <span>Created</span>
                <strong>
                  {{ formatDateOnly(user.createdAt) }}
                </strong>
              </div>
            </article>

            <article class="metadata-card">
              <div class="metadata-icon">
                <Icon name="refresh" :size="15" />
              </div>

              <div>
                <span>Updated</span>
                <strong>
                  {{ formatDateOnly(user.updatedAt) }}
                </strong>
              </div>
            </article>

            <article class="metadata-card">
              <div class="metadata-icon">
                <Icon name="check" :size="15" />
              </div>

              <div>
                <span>Email verification</span>

                <strong>
                  {{
                    user.emailVerifiedAt
                      ? formatDateOnly(user.emailVerifiedAt)
                      : "Not verified"
                  }}
                </strong>
              </div>
            </article>

            <article class="metadata-card">
              <div class="metadata-icon">
                <Icon name="lock" :size="15" />
              </div>

              <div>
                <span>System roles</span>

                <strong>
                  {{ systemRoles.length }}
                </strong>
              </div>
            </article>
          </section>

          <footer class="access-footer">
            <span>
              <i></i>
              Authorization is enforced server-side
            </span>

            <span> UI visibility is not a security boundary. </span>
          </footer>
        </div>
      </template>

      <!-- Error -->
      <section v-else class="state-page error-state page-enter">
        <div class="state-icon error">
          <Icon name="users" :size="25" />
        </div>

        <h2>Unable to load user</h2>

        <p>
          {{ operationMessage || "The requested user could not be loaded." }}
        </p>

        <button class="btn btn-primary" type="button" @click="back">
          <Icon name="arrow-left" :size="15" />
          Back to Users
        </button>
      </section>
    </div>
  </ErpLayout>
</template>

<style scoped>
/* ================================================================
   PAGE
   ================================================================ */

.user-show-page {
  width: 100%;
  min-width: 0;
  color: var(--text-primary, #17202a);
}

.page-content {
  width: 100%;
  min-width: 0;
}

.page-enter {
  animation: page-enter 360ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes page-enter {
  from {
    opacity: 0;
    transform: translateY(8px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ================================================================
   HEADER
   ================================================================ */

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;
}

.heading-copy {
  min-width: 0;
}

.breadcrumb {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 7px;
  margin-bottom: 13px;
  color: var(--text-muted, #7b8794);
  font-size: 12px;
  line-height: 1.4;
}

.breadcrumb-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0;
  border: 0;
  color: var(--text-secondary, #596573);
  background: transparent;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.breadcrumb-link:hover {
  color: var(--primary, #1f8f68);
}

.breadcrumb-separator {
  color: #b3bac4;
}

.breadcrumb-current {
  color: var(--text-muted, #8a94a5);
}

.title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-title-icon {
  display: grid;
  width: 46px;
  height: 46px;
  flex: 0 0 46px;
  place-items: center;
  border: 1px solid #dfe9e4;
  border-radius: 13px;
  color: var(--primary, #1f8f68);
  background: var(--primary-soft, #eaf7f1);
}

.eyebrow {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-bottom: 4px;
  color: var(--text-muted, #7b8794);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.page-header h1 {
  margin: 0;
  font-size: clamp(24px, 3vw, 31px);
  line-height: 1.1;
  letter-spacing: -0.035em;
}

.page-header p {
  max-width: 620px;
  margin: 5px 0 0;
  color: var(--text-muted, #7b8794);
  font-size: 13px;
  line-height: 1.55;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 9px;
}

/* ================================================================
   BUTTONS
   ================================================================ */

.btn {
  display: inline-flex;
  min-height: 38px;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 0 14px;
  border: 1px solid transparent;
  border-radius: 10px;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: transform 140ms ease, border-color 140ms ease, background 140ms ease,
    opacity 140ms ease, color 140ms ease;
}

.btn:not(:disabled):hover {
  transform: translateY(-1px);
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.btn-primary {
  color: #fff;
  background: var(--primary, #1f8f68);
  border-color: var(--primary, #1f8f68);
}

.btn-primary:hover:not(:disabled) {
  background: #197958;
  border-color: #197958;
}

.btn-secondary {
  color: var(--text-primary, #17202a);
  border-color: var(--border, #e5e9ee);
  background: var(--surface, #fff);
}

/* ================================================================
   NOTICE
   ================================================================ */

.operation-message {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 16px;
  padding: 11px 13px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 10px;
  font-size: 13px;
}

.operation-message.success {
  color: #247047;
  border-color: #cfe8d8;
  background: #f2faf5;
}

.operation-message.error {
  color: #a52d2d;
  border-color: #f1b8b8;
  background: #fff6f6;
}

/* ================================================================
   SUMMARY
   ================================================================ */

.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 11px;
  min-width: 0;
  min-height: 76px;
  padding: 14px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 13px;
  background: var(--surface, #fff);
  box-shadow: 0 5px 18px rgb(15 23 42 / 3%);
}

.summary-icon {
  display: grid;
  width: 36px;
  height: 36px;
  flex: 0 0 36px;
  place-items: center;
  border-radius: 10px;
  color: var(--primary, #1f8f68);
  background: var(--primary-soft, #eaf7f1);
}

.summary-icon.warning {
  color: #9a6518;
  background: #fff5e4;
}

.summary-card span {
  display: block;
  color: var(--text-muted, #7b8794);
  font-size: 11px;
}

.summary-card strong {
  display: block;
  margin-top: 3px;
  overflow: hidden;
  color: var(--text-primary, #17202a);
  font-size: 14px;
  line-height: 1.25;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.summary-card .status-value.active {
  color: #17714e;
}

.summary-card .status-value.inactive {
  color: #9a6518;
}

/* ================================================================
   MAIN CONTENT
   ================================================================ */

.content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.85fr);
  gap: 16px;
}

.panel {
  min-width: 0;
  padding: 20px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 15px;
  background: var(--surface, #fff);
  box-shadow: 0 7px 24px rgb(15 23 42 / 3%);
}

.panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}

.panel-heading > div {
  min-width: 0;
}

.section-kicker {
  display: block;
  color: #7a8495;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.11em;
  text-transform: uppercase;
}

.panel h2 {
  margin: 4px 0 0;
  color: #293548;
  font-size: 17px;
  letter-spacing: -0.02em;
}

.panel-heading p {
  margin: 4px 0 0;
  color: var(--text-muted, #7b8794);
  font-size: 12px;
  line-height: 1.5;
}

.panel-icon {
  display: grid;
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  place-items: center;
  border-radius: 9px;
  color: var(--text-secondary, #596573);
  background: var(--surface-muted, #f3f5f7);
}

.selection-count {
  flex: 0 0 auto;
  padding-top: 2px;
  color: var(--text-muted, #8a94a5);
  font-size: 11px;
  font-weight: 700;
}

/* ================================================================
   FORM
   ================================================================ */

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.field {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 7px;
}

.field > span {
  color: #394457;
  font-size: 12px;
  font-weight: 750;
}

input,
select,
.readonly-value {
  width: 100%;
  box-sizing: border-box;
  min-height: 39px;
  padding: 9px 11px;
  border: 1px solid #dfe4eb;
  border-radius: 9px;
  outline: none;
  color: #202b3a;
  background: #fff;
  font: inherit;
  font-size: 13px;
  transition: border-color 140ms ease, box-shadow 140ms ease, background 140ms ease;
}

input:focus,
select:focus {
  border-color: #8995a8;
  box-shadow: 0 0 0 3px rgb(137 149 168 / 12%);
}

input:disabled,
select:disabled {
  color: #667186;
  background: #f7f8fa;
  cursor: not-allowed;
}

input.invalid {
  border-color: #d96565;
}

.field small {
  color: #b13d3d;
  font-size: 11px;
}

.input-with-action {
  position: relative;
}

.input-with-action input {
  padding-right: 58px;
}

.input-action {
  position: absolute;
  top: 50%;
  right: 8px;
  transform: translateY(-50%);
  padding: 2px 4px;
  border: 0;
  color: #667186;
  background: transparent;
  font: inherit;
  font-size: 10px;
  font-weight: 800;
  cursor: pointer;
}

.readonly-value {
  display: flex;
  align-items: center;
  color: #667186;
  background: #f7f8fa;
}

/* ================================================================
   SECURITY DETAILS
   ================================================================ */

.security-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  margin-top: 18px;
  padding-top: 17px;
  border-top: 1px solid #edf0f4;
}

.security-item {
  min-width: 0;
  padding: 10px;
  border: 1px solid #edf0f4;
  border-radius: 9px;
  background: #fafbfc;
}

.security-item span {
  display: block;
  margin-bottom: 4px;
  color: #8a94a5;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.security-item strong {
  display: block;
  overflow: hidden;
  color: #445064;
  font-size: 11px;
  line-height: 1.45;
  text-overflow: ellipsis;
}

.security-warning {
  color: #a04b4b !important;
}

/* ================================================================
   ROLES
   ================================================================ */

.role-list {
  display: grid;
  gap: 8px;
}

.role-row {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  padding: 10px;
  border: 1px solid #e5e9ef;
  border-radius: 10px;
  background: #fff;
}

.role-icon {
  display: grid;
  width: 29px;
  height: 29px;
  flex: 0 0 29px;
  place-items: center;
  border-radius: 8px;
  color: #667186;
  background: #f1f3f6;
}

.role-copy {
  min-width: 0;
}

.role-row strong,
.role-row small {
  display: block;
}

.role-row strong {
  overflow: hidden;
  color: #344052;
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.role-row small {
  margin-top: 2px;
  overflow: hidden;
  color: #8b95a5;
  font-size: 10px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.role-badge {
  margin-left: auto;
  flex: 0 0 auto;
  padding: 4px 7px;
  border-radius: 999px;
  color: #428050;
  background: #edf7ef;
  font-size: 9px;
  font-weight: 800;
}

.role-badge.system {
  color: #6d5796;
  background: #f0edf8;
}

.role-badge.inactive {
  color: #a04b4b;
  background: #f8eeee;
}

/* ================================================================
   EDITABLE ROLES
   ================================================================ */

.edit-role-list {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid #edf0f4;
}

.edit-role-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 9px;
}

.edit-role-heading strong {
  display: block;
  margin-top: 3px;
  color: #394457;
  font-size: 12px;
}

.edit-role-heading > span {
  color: #8a94a5;
  font-size: 10px;
  font-weight: 700;
}

.edit-role-button {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 9px;
  margin-bottom: 7px;
  padding: 9px 10px;
  border: 1px solid #e3e7ed;
  border-radius: 9px;
  color: #445064;
  background: #fff;
  text-align: left;
  cursor: pointer;
  transition: border-color 140ms ease, background 140ms ease, transform 140ms ease;
}

.edit-role-button:hover {
  transform: translateY(-1px);
  border-color: #cbd3dd;
}

.edit-role-button > span:last-child {
  min-width: 0;
}

.edit-role-button strong,
.edit-role-button small {
  display: block;
}

.edit-role-button strong {
  color: #394457;
  font-size: 12px;
}

.edit-role-button small {
  margin-top: 2px;
  color: #8a94a5;
  font-size: 10px;
}

.role-check {
  display: grid;
  width: 19px;
  height: 19px;
  flex: 0 0 19px;
  place-items: center;
  border: 1px solid #d6dce4;
  border-radius: 5px;
  color: #fff;
  font-size: 10px;
  font-weight: 900;
}

.edit-role-button.selected {
  border-color: #aab5c2;
  background: #fafbfc;
}

.edit-role-button.selected .role-check {
  border-color: #18212f;
  background: #18212f;
}

.role-edit-empty {
  padding: 11px;
  border: 1px dashed #dfe4eb;
  border-radius: 9px;
  color: #8a94a5;
  font-size: 11px;
  line-height: 1.5;
}

/* ================================================================
   EMPTY STATE
   ================================================================ */

.empty-state {
  display: flex;
  min-height: 160px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 20px;
  border: 1px dashed #dfe4eb;
  border-radius: 10px;
  color: #8a94a5;
  text-align: center;
}

.empty-icon {
  display: grid;
  width: 40px;
  height: 40px;
  margin-bottom: 2px;
  place-items: center;
  border-radius: 11px;
  color: #7b8794;
  background: #f3f5f7;
}

.empty-state strong {
  color: #445064;
  font-size: 12px;
}

.empty-state span {
  max-width: 250px;
  font-size: 10px;
  line-height: 1.55;
}

/* ================================================================
   METADATA
   ================================================================ */

.metadata-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-top: 16px;
}

.metadata-card {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  padding: 12px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 11px;
  background: var(--surface, #fff);
}

.metadata-icon {
  display: grid;
  width: 30px;
  height: 30px;
  flex: 0 0 30px;
  place-items: center;
  border-radius: 8px;
  color: var(--text-secondary, #596573);
  background: var(--surface-muted, #f3f5f7);
}

.metadata-card > div:last-child {
  min-width: 0;
}

.metadata-card span {
  display: block;
  margin-bottom: 3px;
  color: #8a94a5;
  font-size: 10px;
}

.metadata-card strong {
  display: block;
  overflow: hidden;
  color: #3e495b;
  font-size: 11px;
  line-height: 1.45;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ================================================================
   FOOTER
   ================================================================ */

.access-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-top: 15px;
  color: #8a94a5;
  font-size: 10px;
}

.access-footer span {
  display: inline-flex;
  align-items: center;
  gap: 7px;
}

.access-footer i {
  width: 6px;
  height: 6px;
  flex: 0 0 6px;
  border-radius: 50%;
  background: #55a56b;
}

/* ================================================================
   LOADING / ERROR
   ================================================================ */

.state-page {
  display: flex;
  min-height: 52vh;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #7c8697;
  text-align: center;
}

.state-icon {
  display: grid;
  width: 48px;
  height: 48px;
  place-items: center;
  border-radius: 13px;
  color: var(--primary, #1f8f68);
  background: var(--primary-soft, #eaf7f1);
}

.state-icon.error {
  color: #a04b4b;
  background: #f8eeee;
}

.state-page strong {
  color: #3e495b;
  font-size: 14px;
}

.state-page h2 {
  margin: 3px 0 0;
  color: #354052;
  font-size: 20px;
}

.state-page p {
  max-width: 420px;
  margin: 0 0 8px;
  color: #7c8697;
  font-size: 12px;
  line-height: 1.6;
}

.state-page > span {
  max-width: 360px;
  color: #8a94a5;
  font-size: 11px;
  line-height: 1.5;
}

/* ================================================================
   RESPONSIVE
   ================================================================ */

@media (max-width: 1100px) {
  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .metadata-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .content-grid {
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

  .form-grid {
    grid-template-columns: 1fr;
  }

  .security-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 560px) {
  .summary-grid,
  .metadata-grid {
    grid-template-columns: 1fr;
  }

  .header-actions {
    flex-direction: column;
  }

  .header-actions .btn {
    width: 100%;
  }

  .title-row {
    align-items: flex-start;
  }

  .page-title-icon {
    width: 42px;
    height: 42px;
    flex-basis: 42px;
  }

  .panel {
    padding: 16px;
  }

  .access-footer {
    align-items: flex-start;
    flex-direction: column;
  }
}

@media (prefers-reduced-motion: reduce) {
  .page-enter,
  .btn,
  .edit-role-button {
    animation: none;
    transition: none;
  }
}
</style>
