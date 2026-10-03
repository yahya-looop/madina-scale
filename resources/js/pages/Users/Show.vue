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

interface User {
  id: number;
  name: string;
  email: string;
  status: "active" | "inactive" | string;
  roles: Role[];
  createdAt?: string | null;
  updatedAt?: string | null;
  lastLoginAt?: string | null;
  passwordChangedAt?: string | null;
  failedLoginAttempts?: number;
  lockedUntil?: string | null;
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
 * Read the route parameter directly instead of depending on ?id=...
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

const initials = computed(
  () =>
    user.value?.name
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase() ?? "")
      .join("") ?? ""
);

const activeRoles = computed(
  () => user.value?.roles.filter((role) => role.isActive) ?? []
);

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

  try {
    user.value = await apiRequest<User>(`/api/users/${userId}`, "GET");

    form.name = user.value.name;
    form.email = user.value.email;
    form.status = user.value.status;
    form.role_ids = user.value.roles
      .filter((role) => role.isActive && !role.isSystem)
      .map((role) => role.id);
  } catch (error) {
    operationMessageType.value = "error";
    operationMessage.value =
      error instanceof Error ? error.message : "Unable to load the user.";
  } finally {
    isLoading.value = false;
  }
}

function toggleRole(roleId: number): void {
  form.role_ids = form.role_ids.includes(roleId)
    ? form.role_ids.filter((id) => id !== roleId)
    : [...form.role_ids, roleId];
}

async function save(): Promise<void> {
  if (!user.value || isSaving.value) {
    return;
  }

  formErrors.value = {};

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
  operationMessage.value = "";

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
    user.value = await apiRequest<User>(`/api/users/${user.value.id}`, "PUT", payload);

    form.password = "";
    form.password_confirmation = "";
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

function cancelEdit(): void {
  if (!user.value) {
    return;
  }

  form.name = user.value.name;
  form.email = user.value.email;
  form.status = user.value.status;
  form.role_ids = user.value.roles
    .filter((role) => role.isActive && !role.isSystem)
    .map((role) => role.id);
  form.password = "";
  form.password_confirmation = "";
  formErrors.value = {};
  isEditing.value = false;
}

function back(): void {
  router.get("/users");
}

function formatDate(value?: string | null): string {
  if (!value) {
    return "—";
  }

  const date = new Date(value);

  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

onMounted(loadUser);
</script>

<template>
  <Head :title="user ? `User — ${user.name}` : 'User Details'" />

  <ErpLayout :user-name="authUser.name" :user-email="authUser.email">
    <div class="user-show-page">
      <div v-if="isLoading" class="loading-page">
        <Icon name="users" :size="24" />
        <strong>Loading user...</strong>
      </div>

      <template v-else-if="user">
        <header class="page-header">
          <div>
            <button class="back-button" type="button" @click="back">
              <Icon name="arrow-left" :size="16" />
              Users
            </button>

            <span class="eyebrow">Identity Management</span>

            <div class="title-row">
              <div class="avatar">{{ initials }}</div>
              <div>
                <h1>{{ user.name }}</h1>
                <p>{{ user.email }}</p>
              </div>
            </div>
          </div>

          <div class="header-actions">
            <button
              v-if="!isEditing"
              class="btn primary"
              type="button"
              @click="isEditing = true"
            >
              <Icon name="settings" :size="15" />
              Edit User
            </button>

            <template v-else>
              <button class="btn secondary" type="button" @click="cancelEdit">
                Cancel
              </button>
              <button
                class="btn primary"
                type="button"
                :disabled="isSaving"
                @click="save"
              >
                {{ isSaving ? "Saving..." : "Save Changes" }}
              </button>
            </template>
          </div>
        </header>

        <div
          v-if="operationMessage"
          class="notice"
          :class="operationMessageType"
          role="status"
        >
          {{ operationMessage }}
        </div>

        <div class="summary-grid">
          <article>
            <span>Status</span>
            <strong :class="['status', user.status]">
              {{ user.status }}
            </strong>
          </article>
          <article>
            <span>Roles</span>
            <strong>{{ activeRoles.length }}</strong>
          </article>
          <article>
            <span>Failed logins</span>
            <strong>{{ user.failedLoginAttempts ?? 0 }}</strong>
          </article>
          <article>
            <span>Last login</span>
            <strong>{{ formatDate(user.lastLoginAt) }}</strong>
          </article>
        </div>

        <div class="content-grid">
          <section class="panel">
            <div class="panel-heading">
              <div>
                <span class="section-kicker">Account</span>
                <h2>Profile & security</h2>
              </div>
            </div>

            <div class="form-grid">
              <label class="field">
                <span>Name</span>
                <input
                  v-model="form.name"
                  type="text"
                  :disabled="!isEditing"
                  :class="{ invalid: formErrors.name }"
                />
                <small v-if="formErrors.name">{{ formErrors.name }}</small>
              </label>

              <label class="field">
                <span>Email</span>
                <input
                  v-model="form.email"
                  type="email"
                  :disabled="!isEditing"
                  :class="{ invalid: formErrors.email }"
                />
                <small v-if="formErrors.email">{{ formErrors.email }}</small>
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
                    placeholder="Leave blank to keep current password"
                    :class="{ invalid: formErrors.password }"
                  />
                  <button
                    type="button"
                    class="input-action"
                    @click="showPassword = !showPassword"
                  >
                    {{ showPassword ? "Hide" : "Show" }}
                  </button>
                </div>
                <small v-if="formErrors.password">{{ formErrors.password }}</small>
              </label>

              <label v-if="isEditing" class="field">
                <span>Confirm new password</span>
                <div class="input-with-action">
                  <input
                    v-model="form.password_confirmation"
                    :type="showPasswordConfirmation ? 'text' : 'password'"
                    placeholder="Repeat new password"
                    :class="{ invalid: formErrors.password_confirmation }"
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
          </section>

          <section class="panel">
            <div class="panel-heading">
              <div>
                <span class="section-kicker">Access Control</span>
                <h2>Assigned roles</h2>
              </div>
              <span>{{ form.role_ids.length }} selected</span>
            </div>

            <div class="role-list">
              <div v-for="role in user.roles" :key="role.id" class="role-row">
                <span class="role-icon">
                  <Icon :name="role.isSystem ? 'developer' : 'users'" :size="14" />
                </span>
                <div>
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
              </div>

              <div v-if="user.roles.length === 0" class="empty-state">
                No roles assigned.
              </div>
            </div>

            <div v-if="isEditing" class="edit-role-list">
              <p>Assignable roles</p>

              <button
                v-for="role in user.roles.filter(
                  (item) => item.isActive && !item.isSystem
                )"
                :key="`edit-${role.id}`"
                type="button"
                class="edit-role-button"
                :class="{ selected: form.role_ids.includes(role.id) }"
                @click="toggleRole(role.id)"
              >
                <span>{{ form.role_ids.includes(role.id) ? "✓" : "" }}</span>
                {{ role.name }}
              </button>
            </div>
          </section>
        </div>

        <section class="metadata-grid">
          <article>
            <span>Created</span>
            <strong>{{ formatDate(user.createdAt) }}</strong>
          </article>
          <article>
            <span>Updated</span>
            <strong>{{ formatDate(user.updatedAt) }}</strong>
          </article>
          <article>
            <span>Password changed</span>
            <strong>{{ formatDate(user.passwordChangedAt) }}</strong>
          </article>
          <article>
            <span>Lock status</span>
            <strong>{{
              user.lockedUntil
                ? `Locked until ${formatDate(user.lockedUntil)}`
                : "Not locked"
            }}</strong>
          </article>
        </section>

        <footer class="access-footer">
          <span><i></i> Authorization is enforced server-side</span>
          <span>UI visibility is not a security boundary.</span>
        </footer>
      </template>

      <div v-else class="error-page">
        <Icon name="users" :size="28" />
        <h2>Unable to load user</h2>
        <p>{{ operationMessage }}</p>
        <button class="btn primary" type="button" @click="back">Back to Users</button>
      </div>
    </div>
  </ErpLayout>
</template>

<style scoped>
.user-show-page {
  min-height: 100%;
  padding: 30px;
  color: var(--text-primary, #18212f);
}
.page-header {
  display: flex;
  justify-content: space-between;
  gap: 24px;
  align-items: flex-end;
  margin-bottom: 22px;
}
.back-button {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  border: 0;
  background: transparent;
  color: #687386;
  padding: 0;
  margin-bottom: 14px;
  cursor: pointer;
}
.eyebrow,
.section-kicker {
  display: block;
  color: #7a8495;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
.title-row {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 7px;
}
.avatar {
  width: 48px;
  height: 48px;
  display: grid;
  place-items: center;
  border-radius: 13px;
  background: #eef1f5;
  color: #394457;
  font-weight: 850;
}
h1 {
  margin: 0;
  font-size: 29px;
  letter-spacing: -0.03em;
}
.title-row p {
  margin: 4px 0 0;
  color: #707a8c;
}
.header-actions {
  display: flex;
  gap: 10px;
}
.btn {
  border: 1px solid #dfe4ec;
  border-radius: 10px;
  padding: 11px 16px;
  font-weight: 750;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.btn.secondary {
  background: #fff;
  color: #384356;
}
.btn.primary {
  background: #18212f;
  color: #fff;
  border-color: #18212f;
}
.notice {
  border-radius: 10px;
  padding: 12px 15px;
  margin-bottom: 18px;
  font-size: 13px;
}
.notice.success {
  background: #eef9f1;
  color: #337044;
  border: 1px solid #d1ecd7;
}
.notice.error {
  background: #fff1f1;
  color: #a53030;
  border: 1px solid #ffd5d5;
}
.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 18px;
}
.summary-grid article,
.metadata-grid article {
  background: #fff;
  border: 1px solid #e7ebf0;
  border-radius: 13px;
  padding: 15px;
}
.summary-grid span,
.metadata-grid span {
  display: block;
  color: #8a94a5;
  font-size: 11px;
  margin-bottom: 7px;
}
.summary-grid strong {
  font-size: 15px;
}
.status {
  text-transform: capitalize;
}
.status.active {
  color: #3f8651;
}
.status.inactive {
  color: #a04b4b;
}
.content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.85fr);
  gap: 18px;
}
.panel {
  background: #fff;
  border: 1px solid #e7ebf0;
  border-radius: 16px;
  padding: 22px;
  box-shadow: 0 8px 28px rgba(28, 39, 56, 0.04);
}
.panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
  color: #8a94a5;
  font-size: 12px;
}
.panel h2 {
  margin: 5px 0 0;
  color: #293548;
  font-size: 18px;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}
.field > span {
  font-size: 13px;
  font-weight: 750;
  color: #394457;
}
input,
select,
.readonly-value {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #dfe4eb;
  border-radius: 10px;
  background: #fff;
  color: #202b3a;
  padding: 11px 12px;
  outline: none;
}
input:disabled,
select:disabled {
  background: #f7f8fa;
  color: #667186;
}
input.invalid {
  border-color: #d96565;
}
.field small {
  color: #b13d3d;
  font-size: 12px;
}
.input-with-action {
  position: relative;
}
.input-with-action input {
  padding-right: 62px;
}
.input-action {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  border: 0;
  background: transparent;
  color: #667186;
  font-size: 11px;
  font-weight: 800;
  cursor: pointer;
}
.role-list {
  display: grid;
  gap: 9px;
}
.role-row {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 11px;
  border: 1px solid #e5e9ef;
  border-radius: 11px;
}
.role-icon {
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  border-radius: 8px;
  background: #f1f3f6;
  color: #667186;
}
.role-row strong,
.role-row small {
  display: block;
}
.role-row strong {
  color: #344052;
  font-size: 13px;
}
.role-row small {
  color: #8b95a5;
  margin-top: 2px;
  font-size: 11px;
}
.role-badge {
  margin-left: auto;
  font-size: 10px;
  font-weight: 800;
  padding: 4px 7px;
  border-radius: 999px;
  background: #edf7ef;
  color: #428050;
}
.role-badge.system {
  background: #f0edf8;
  color: #6d5796;
}
.role-badge.inactive {
  background: #f8eeee;
  color: #a04b4b;
}
.edit-role-list {
  margin-top: 18px;
  padding-top: 18px;
  border-top: 1px solid #edf0f4;
}
.edit-role-list p {
  margin: 0 0 9px;
  font-size: 12px;
  font-weight: 800;
  color: #596477;
}
.edit-role-button {
  display: flex;
  align-items: center;
  gap: 9px;
  width: 100%;
  margin-bottom: 7px;
  padding: 9px 10px;
  border: 1px solid #e3e7ed;
  border-radius: 9px;
  background: #fff;
  color: #445064;
  text-align: left;
  cursor: pointer;
}
.edit-role-button span {
  width: 18px;
  height: 18px;
  display: grid;
  place-items: center;
  border: 1px solid #d6dce4;
  border-radius: 5px;
  font-size: 11px;
}
.edit-role-button.selected span {
  background: #18212f;
  border-color: #18212f;
  color: #fff;
}
.metadata-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-top: 18px;
}
.metadata-grid strong {
  color: #3e495b;
  font-size: 12px;
  line-height: 1.5;
}
.access-footer {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin-top: 18px;
  color: #8a94a5;
  font-size: 11px;
}
.access-footer span {
  display: inline-flex;
  align-items: center;
  gap: 7px;
}
.access-footer i {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #55a56b;
}
.loading-page,
.error-page {
  min-height: 55vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 9px;
  color: #7c8697;
  text-align: center;
}
.error-page h2 {
  margin: 5px 0 0;
  color: #354052;
}
.error-page p {
  margin: 0 0 8px;
}
.empty-state {
  padding: 20px;
  text-align: center;
  color: #8a94a5;
  font-size: 12px;
  border: 1px dashed #dfe4eb;
  border-radius: 10px;
}
@media (max-width: 980px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
  .summary-grid,
  .metadata-grid {
    grid-template-columns: 1fr 1fr;
  }
}
@media (max-width: 700px) {
  .user-show-page {
    padding: 18px;
  }
  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .header-actions {
    width: 100%;
  }
  .header-actions .btn {
    flex: 1;
    justify-content: center;
  }
  .form-grid {
    grid-template-columns: 1fr;
  }
  .summary-grid,
  .metadata-grid {
    grid-template-columns: 1fr;
  }
  .access-footer {
    flex-direction: column;
  }
}
</style>
