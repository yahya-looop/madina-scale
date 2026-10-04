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

const userId = Number(window.location.pathname.match(/\/users\/(\d+)(?:\/|$)/)?.[1] ?? 0);

const user = ref<User | null>(null);
const isLoading = ref(true);
const operationMessage = ref("");
const operationMessageType = ref<"success" | "error">("success");

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

const accountStatusLabel = computed(() => {
  if (!user.value) {
    return "Unknown";
  }

  return user.value.status.charAt(0).toUpperCase() + user.value.status.slice(1);
});

async function apiRequest<T>(url: string, method: "GET"): Promise<T> {
  const response = await fetch(url, {
    method,
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
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

    user.value = normalizeUser(payload);
  } catch (error) {
    operationMessageType.value = "error";
    operationMessage.value =
      error instanceof Error ? error.message : "Unable to load the user.";
  } finally {
    isLoading.value = false;
  }
}

function back(): void {
  router.get("/users");
}

function editUser(): void {
  if (!user.value) {
    return;
  }

  router.get(`/users/${user.value.id}/edit`);
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
        <div class="page-enter">
          <header class="page-header">
            <div class="heading-copy">
              <nav class="breadcrumb" aria-label="Breadcrumb">
                <button class="breadcrumb-link" type="button" @click="back">
                  <Icon name="users" :size="14" />
                  <span>Users</span>
                </button>

                <span class="breadcrumb-separator" aria-hidden="true"> / </span>

                <span class="breadcrumb-current"> User details </span>
              </nav>

              <div class="title-row">
                <div class="page-title-icon" aria-hidden="true">
                  <Icon name="users" :size="22" />
                </div>

                <div class="title-copy">
                  <span class="eyebrow"> Identity Management </span>

                  <h1>{{ user.name }}</h1>

                  <p>View account details, security state and assigned access.</p>
                </div>
              </div>
            </div>

            <div class="header-actions">
              <button class="btn btn-secondary" type="button" @click="back">
                <Icon name="arrow-left" :size="15" />
                Back
              </button>

              <button class="btn btn-primary" type="button" @click="editUser">
                <Icon name="edit" :size="15" />
                Edit User
              </button>
            </div>
          </header>

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
                <strong>
                  {{ activeRoles.length }}
                </strong>
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
                  {{ formatDate(user.lastLoginAt, "Never") }}
                </strong>
              </div>
            </article>
          </section>

          <div class="content-grid">
            <section class="panel">
              <header class="panel-heading">
                <div>
                  <span class="section-kicker"> Account </span>

                  <h2>Profile &amp; security</h2>

                  <p>Identity information and authentication state.</p>
                </div>

                <div class="panel-icon" aria-hidden="true">
                  <Icon name="settings" :size="17" />
                </div>
              </header>

              <div class="detail-grid">
                <div class="detail-field">
                  <span>Name</span>
                  <strong>{{ user.name }}</strong>
                </div>

                <div class="detail-field">
                  <span>Email</span>
                  <strong>{{ user.email }}</strong>
                </div>

                <div class="detail-field">
                  <span>Status</span>
                  <strong class="status-value" :class="user.status">
                    {{ accountStatusLabel }}
                  </strong>
                </div>

                <div class="detail-field">
                  <span>Account ID</span>
                  <strong>#{{ user.id }}</strong>
                </div>
              </div>

              <div class="security-grid">
                <article class="security-item">
                  <span>Last login</span>

                  <strong>
                    {{ formatDate(user.lastLoginAt, "Never") }}
                  </strong>
                </article>

                <article class="security-item">
                  <span>Login IP</span>

                  <strong>
                    {{ user.lastLoginIp ?? "Not available" }}
                  </strong>
                </article>

                <article class="security-item">
                  <span>Password changed</span>

                  <strong>
                    {{ formatDate(user.passwordChangedAt, "Not recorded") }}
                  </strong>
                </article>

                <article class="security-item">
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
                </article>
              </div>
            </section>

            <section class="panel">
              <header class="panel-heading">
                <div>
                  <span class="section-kicker"> Access Control </span>

                  <h2>Assigned roles</h2>

                  <p>Roles currently attached to this account.</p>
                </div>

                <span class="selection-count">
                  {{ activeRoles.length }}
                  active
                </span>
              </header>

              <div class="role-list">
                <article v-for="role in user.roles" :key="role.id" class="role-row">
                  <span class="role-icon" aria-hidden="true">
                    <Icon :name="role.isSystem ? 'developer' : 'users'" :size="14" />
                  </span>

                  <div class="role-copy">
                    <strong>
                      {{ role.name }}
                    </strong>

                    <small>
                      {{ role.slug }}
                    </small>
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

                  <strong> No roles assigned </strong>

                  <span> This account currently has no assigned application roles. </span>
                </div>
              </div>
            </section>
          </div>

          <section class="metadata-grid" aria-label="User metadata">
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
.user-show-page {
  width: 100%;
  min-width: 0;
  color: var(--text-primary, #17202a);
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
  margin-bottom: 12px;
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
  border: 1px solid #dfe5ec;
  border-radius: 13px;
  color: var(--primary, #1f8f68);
  background: #f5faf8;
}

.title-copy {
  min-width: 0;
}

.eyebrow,
.section-kicker {
  display: block;
  color: #7a8495;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.12em;
  line-height: 1.3;
  text-transform: uppercase;
}

h1 {
  margin: 4px 0 5px;
  color: var(--text-primary, #17202a);
  font-size: 30px;
  font-weight: 700;
  letter-spacing: -0.03em;
  line-height: 1.15;
}

.title-copy p {
  margin: 0;
  color: var(--text-secondary, #6d7788);
  font-size: 13px;
  line-height: 1.55;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 9px;
  flex-shrink: 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 38px;
  padding: 9px 14px;
  border: 1px solid #dfe4ec;
  border-radius: 10px;
  font: inherit;
  font-size: 13px;
  font-weight: 750;
  line-height: 1;
  cursor: pointer;
  transition: border-color 160ms ease, background 160ms ease, transform 160ms ease;
}

.btn:hover {
  transform: translateY(-1px);
}

.btn-primary {
  color: #fff;
  border-color: #18212f;
  background: #18212f;
}

.btn-secondary {
  color: #384356;
  background: #fff;
}

.operation-message {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 16px;
  padding: 11px 13px;
  border: 1px solid;
  border-radius: 10px;
  font-size: 13px;
}

.operation-message.success {
  color: #176846;
  border-color: #cce7da;
  background: #f1faf5;
}

.operation-message.error {
  color: #a53030;
  border-color: #ffd5d5;
  background: #fff1f1;
}

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
  padding: 15px;
  border: 1px solid var(--border-color, #e7ebf0);
  border-radius: 13px;
  background: var(--card-bg, #fff);
  box-shadow: 0 5px 20px rgba(28, 39, 56, 0.035);
}

.summary-icon {
  display: grid;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  place-items: center;
  border-radius: 9px;
  color: #1f8f68;
  background: #eef8f3;
}

.summary-icon.warning {
  color: #9b6b18;
  background: #fff8e9;
}

.summary-card > div:last-child {
  min-width: 0;
}

.summary-card span {
  display: block;
  margin-bottom: 3px;
  color: var(--text-muted, #7b8794);
  font-size: 11px;
  font-weight: 650;
}

.summary-card strong {
  display: block;
  overflow: hidden;
  color: var(--text-primary, #17202a);
  font-size: 13px;
  font-weight: 700;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status-value.active {
  color: #1f8f68;
}

.status-value.inactive {
  color: #b14b4b;
}

.content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.9fr);
  gap: 16px;
}

.panel {
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--border-color, #e7ebf0);
  border-radius: 15px;
  background: var(--card-bg, #fff);
  box-shadow: 0 6px 22px rgba(28, 39, 56, 0.035);
}

.panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  padding: 18px 18px 15px;
  border-bottom: 1px solid var(--border-color, #edf0f3);
}

.panel-heading h2 {
  margin: 4px 0 4px;
  color: var(--text-primary, #17202a);
  font-size: 17px;
  font-weight: 700;
  letter-spacing: -0.015em;
}

.panel-heading p {
  margin: 0;
  color: var(--text-secondary, #6d7788);
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
  color: #657184;
  background: #f4f6f8;
}

.selection-count {
  flex-shrink: 0;
  color: #8a94a5;
  font-size: 11px;
  font-weight: 700;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1px;
  background: var(--border-color, #edf0f3);
}

.detail-field {
  min-width: 0;
  padding: 15px 18px;
  background: var(--card-bg, #fff);
}

.detail-field span,
.security-item span,
.metadata-card span {
  display: block;
  margin-bottom: 4px;
  color: var(--text-muted, #7b8794);
  font-size: 11px;
  font-weight: 650;
}

.detail-field strong,
.security-item strong,
.metadata-card strong {
  display: block;
  overflow: hidden;
  color: var(--text-primary, #202a38);
  font-size: 13px;
  font-weight: 700;
  line-height: 1.45;
  text-overflow: ellipsis;
}

.security-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1px;
  margin-top: 1px;
  background: var(--border-color, #edf0f3);
}

.security-item {
  min-width: 0;
  padding: 14px 18px;
  background: var(--card-bg, #fff);
}

.security-warning {
  color: #b2761e !important;
}

.role-list {
  display: grid;
}

.role-row {
  display: flex;
  align-items: center;
  gap: 11px;
  min-width: 0;
  padding: 13px 16px;
  border-bottom: 1px solid #edf0f3;
}

.role-row:last-child {
  border-bottom: 0;
}

.role-icon {
  display: grid;
  width: 31px;
  height: 31px;
  flex: 0 0 31px;
  place-items: center;
  border-radius: 8px;
  color: #657184;
  background: #f4f6f8;
}

.role-copy {
  min-width: 0;
  flex: 1;
}

.role-copy strong,
.role-copy small {
  display: block;
}

.role-copy strong {
  overflow: hidden;
  color: #273243;
  font-size: 13px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.role-copy small {
  margin-top: 2px;
  overflow: hidden;
  color: #8a94a5;
  font-size: 11px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.role-badge {
  flex-shrink: 0;
  padding: 4px 8px;
  border-radius: 999px;
  color: #247454;
  background: #edf8f2;
  font-size: 10px;
  font-weight: 750;
}

.role-badge.system {
  color: #6c4b9e;
  background: #f4effa;
}

.role-badge.inactive {
  color: #a34d4d;
  background: #fff0f0;
}

.empty-state {
  display: grid;
  place-items: center;
  gap: 7px;
  min-height: 180px;
  padding: 24px;
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
  color: #273243;
  font-size: 13px;
}

.empty-state span {
  max-width: 280px;
  color: #8a94a5;
  font-size: 11px;
  line-height: 1.5;
}

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
  padding: 13px;
  border: 1px solid var(--border-color, #e7ebf0);
  border-radius: 12px;
  background: var(--card-bg, #fff);
}

.metadata-icon {
  display: grid;
  width: 31px;
  height: 31px;
  flex: 0 0 31px;
  place-items: center;
  border-radius: 8px;
  color: #657184;
  background: #f4f6f8;
}

.metadata-card > div:last-child {
  min-width: 0;
}

.access-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 14px;
  color: #8a94a5;
  font-size: 11px;
}

.access-footer span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.access-footer i {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #55a56b;
}

.state-page {
  display: grid;
  min-height: 360px;
  place-items: center;
  align-content: center;
  gap: 8px;
  color: #7b8794;
  text-align: center;
}

.state-page strong {
  color: #273243;
  font-size: 14px;
}

.state-page > span,
.state-page p {
  margin: 0;
  max-width: 360px;
  font-size: 12px;
  line-height: 1.55;
}

.state-icon {
  display: grid;
  width: 48px;
  height: 48px;
  margin-bottom: 3px;
  place-items: center;
  border-radius: 13px;
  color: #657184;
  background: #f3f5f7;
}

.state-icon.error {
  color: #ad4a4a;
  background: #fff0f0;
}

.state-page h2 {
  margin: 0;
  color: #273243;
  font-size: 18px;
}

@media (max-width: 1000px) {
  .summary-grid,
  .metadata-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .content-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 720px) {
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

  .summary-grid,
  .metadata-grid {
    grid-template-columns: 1fr;
  }

  .detail-grid,
  .security-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 520px) {
  .title-row {
    align-items: flex-start;
  }

  h1 {
    font-size: 24px;
  }

  .header-actions {
    flex-direction: column;
  }

  .header-actions .btn {
    width: 100%;
  }

  .access-footer {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
