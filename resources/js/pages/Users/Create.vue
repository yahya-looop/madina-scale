<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";

import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";

interface Role {
  id: number;
  name: string;
  slug: string;
  isSystem: boolean;
  isActive: boolean;
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

const roles = ref<Role[]>([]);
const isLoadingRoles = ref(false);
const isSubmitting = ref(false);

const formErrors = ref<Record<string, string>>({});
const operationMessage = ref("");
const operationMessageType = ref<"success" | "error">("error");

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const form = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  status: "active",
  role_ids: [] as number[],
});

const availableRoles = computed(() =>
  roles.value
    .filter((role) => role.isActive && !role.isSystem)
    .sort((a, b) => a.name.localeCompare(b.name))
);

const selectedRoleCount = computed(() => form.role_ids.length);

async function apiRequest<T>(
  url: string,
  method: "GET" | "POST",
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

async function loadRoles(): Promise<void> {
  isLoadingRoles.value = true;

  try {
    roles.value = await apiRequest<Role[]>("/api/roles", "GET");
  } catch (error) {
    operationMessageType.value = "error";
    operationMessage.value =
      error instanceof Error ? error.message : "Unable to load available roles.";
  } finally {
    isLoadingRoles.value = false;
  }
}

function toggleRole(roleId: number): void {
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

  if (!form.password) {
    formErrors.value.password = "Password is required.";
  }

  if (form.password && form.password_confirmation !== form.password) {
    formErrors.value.password_confirmation = "Passwords do not match.";
  }

  return Object.keys(formErrors.value).length === 0;
}

async function submit(): Promise<void> {
  if (isSubmitting.value) {
    return;
  }

  formErrors.value = {};
  operationMessage.value = "";

  if (!validate()) {
    operationMessageType.value = "error";
    operationMessage.value = "Please correct the highlighted fields.";
    return;
  }

  isSubmitting.value = true;

  try {
    await apiRequest("/api/users", "POST", {
      name: form.name.trim(),
      email: form.email.trim(),
      password: form.password,
      password_confirmation: form.password_confirmation,
      status: form.status,
      role_ids: form.role_ids,
    });

    operationMessageType.value = "success";
    operationMessage.value = "User created successfully.";

    window.setTimeout(() => {
      router.visit("/users");
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
      operationMessage.value = "Unable to create the user. Please try again.";
    }
  } finally {
    isSubmitting.value = false;
  }
}

function cancel(): void {
  router.visit("/users");
}

onMounted(() => {
  void loadRoles();
});
</script>

<template>
  <Head title="Create User" />

  <ErpLayout user-name="" user-email="">
    <div class="user-create-page">
      <!-- =========================================================
                 PAGE HEADER
                 ========================================================= -->
      <header class="page-header page-enter-header">
        <div class="heading-copy">
          <div class="eyebrow">
            <Icon name="users" :size="14" />
            <span>Identity &amp; Access</span>
          </div>

          <h1>Create User</h1>

          <p>
            Create an application user and assign the appropriate access roles from one
            workspace.
          </p>
        </div>

        <div class="header-actions">
          <button
            class="btn btn-secondary"
            type="button"
            :disabled="isSubmitting"
            @click="cancel"
          >
            <svg
              class="ui-icon"
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M19 12H5" />
              <path d="M12 19l-7-7 7-7" />
            </svg>

            Cancel
          </button>

          <button
            class="btn btn-primary"
            type="button"
            :disabled="isSubmitting"
            @click="submit"
          >
            <Icon name="plus" :size="15" />

            {{ isSubmitting ? "Creating..." : "Create User" }}
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

        <span>{{ operationMessage }}</span>
      </div>

      <!-- =========================================================
                 FORM WORKSPACE
                 ========================================================= -->
      <form class="content-grid" @submit.prevent="submit">
        <!-- =====================================================
                     BASIC INFORMATION
                     ===================================================== -->
        <section class="panel profile-panel">
          <div class="panel-heading">
            <div>
              <span class="section-kicker"> Profile </span>

              <h2>Basic information</h2>
            </div>

            <span class="required-note"> * Required </span>
          </div>

          <div class="form-grid">
            <!-- Full name -->
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

            <!-- Email -->
            <label class="field full">
              <span>
                Email address
                <em>*</em>
              </span>

              <input
                v-model="form.email"
                type="email"
                autocomplete="email"
                placeholder="name@example.com"
                :class="{
                  invalid: hasError('email'),
                }"
                @input="clearFieldError('email')"
              />

              <small v-if="formErrors.email">
                {{ formErrors.email }}
              </small>
            </label>

            <!-- Password -->
            <label class="field">
              <span>
                Password
                <em>*</em>
              </span>

              <div class="password-field">
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  placeholder="Minimum 12 characters"
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
                  <svg
                    class="ui-icon"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                  >
                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                    <circle cx="12" cy="12" r="2.8" />
                  </svg>
                </button>
              </div>

              <small v-if="formErrors.password">
                {{ formErrors.password }}
              </small>
            </label>

            <!-- Confirm password -->
            <label class="field">
              <span>
                Confirm password
                <em>*</em>
              </span>

              <div class="password-field">
                <input
                  v-model="form.password_confirmation"
                  :type="showPasswordConfirmation ? 'text' : 'password'"
                  autocomplete="new-password"
                  placeholder="Repeat password"
                  :class="{
                    invalid: hasError('password_confirmation'),
                  }"
                  @input="clearFieldError('password_confirmation')"
                />

                <button
                  class="password-toggle"
                  type="button"
                  :aria-label="
                    showPasswordConfirmation
                      ? 'Hide password confirmation'
                      : 'Show password confirmation'
                  "
                  @click="showPasswordConfirmation = !showPasswordConfirmation"
                >
                  <svg
                    class="ui-icon"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                  >
                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                    <circle cx="12" cy="12" r="2.8" />
                  </svg>
                </button>
              </div>

              <small v-if="formErrors.password_confirmation">
                {{ formErrors.password_confirmation }}
              </small>
            </label>

            <!-- Status -->
            <label class="field">
              <span>Status</span>

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
          </div>
        </section>

        <!-- =====================================================
                     ACCESS CONTROL
                     ===================================================== -->
        <section class="panel roles-panel">
          <div class="panel-heading">
            <div>
              <span class="section-kicker"> Access Control </span>

              <h2>Assign roles</h2>
            </div>

            <span class="selection-count"> {{ selectedRoleCount }} selected </span>
          </div>

          <p class="panel-description">
            Only active non-system roles are available through normal user management.
          </p>

          <div v-if="isLoadingRoles" class="loading-state" role="status">
            <div class="loading-icon">
              <svg
                class="ui-icon"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M12 3a9 9 0 1 0 9 9" />
              </svg>
            </div>

            <span> Loading available roles... </span>
          </div>

          <div v-else-if="availableRoles.length === 0" class="empty-roles">
            <div class="empty-role-icon">
              <Icon name="users" :size="18" />
            </div>

            <div>
              <strong> No assignable roles </strong>

              <p>
                Create or activate a non-system role before assigning access to a user.
              </p>
            </div>
          </div>

          <div v-else class="role-grid">
            <button
              v-for="role in availableRoles"
              :key="role.id"
              type="button"
              class="role-option"
              :class="{
                selected: form.role_ids.includes(role.id),
              }"
              :aria-pressed="form.role_ids.includes(role.id)"
              @click="toggleRole(role.id)"
            >
              <span class="role-check">
                <svg
                  v-if="form.role_ids.includes(role.id)"
                  class="ui-icon"
                  width="13"
                  height="13"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="m5 12 4 4L19 6" />
                </svg>
              </span>

              <span class="role-copy">
                <strong>
                  {{ role.name }}
                </strong>

                <small>
                  {{ role.slug }}
                </small>
              </span>
            </button>
          </div>

          <small v-if="formErrors.role_ids" class="field-error">
            {{ formErrors.role_ids }}
          </small>
        </section>

        <!-- =====================================================
                     FORM FOOTER
                     ===================================================== -->
        <footer class="form-footer">
          <span>
            <i></i>
            Authorization is enforced server-side
          </span>

          <span> UI visibility is not a security boundary. </span>

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
              <Icon name="plus" :size="15" />

              {{ isSubmitting ? "Creating..." : "Create User" }}
            </button>
          </div>
        </footer>
      </form>
    </div>
  </ErpLayout>
</template>

<style scoped>
.user-create-page {
  width: 100%;
  min-width: 0;
  padding: 0;
  color: var(--text-primary, #17202a);
}

.ui-icon {
  display: block;
  flex: 0 0 auto;
}

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 22px;
}

.page-enter-header {
  animation: access-enter 500ms cubic-bezier(0.22, 1, 0.36, 1) 20ms both;
}

.heading-copy {
  min-width: 0;
}

.eyebrow {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-bottom: 8px;
  color: var(--text-muted, #7b8794);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.page-header h1 {
  margin: 0;
  font-size: clamp(24px, 3vw, 32px);
  line-height: 1.1;
  letter-spacing: -0.035em;
}

.page-header p {
  max-width: 620px;
  margin: 9px 0 0;
  color: var(--text-muted, #7b8794);
  font-size: 14px;
  line-height: 1.6;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 9px;
  flex: 0 0 auto;
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

.operation-message {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 18px;
  padding: 11px 14px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 10px;
  font-size: 13px;
  animation: access-enter 400ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

.operation-message.success {
  color: #17714e;
  border-color: #bfe3d3;
  background: #f0faf5;
}

.operation-message.error {
  color: #a52d2d;
  border-color: #f1b8b8;
  background: #fff6f6;
}

.content-grid {
  display: grid;
  grid-template-columns:
    minmax(0, 1.45fr)
    minmax(320px, 0.85fr);
  gap: 16px;
}

.panel {
  min-width: 0;
  padding: 20px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 15px;
  background: var(--surface, #fff);
  box-shadow: 0 8px 28px rgb(15 23 42 / 4%);
}

.roles-panel {
  min-width: 0;
}

.panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 15px;
  margin-bottom: 18px;
}

.section-kicker {
  display: block;
  margin-bottom: 4px;
  color: #7a8495;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.panel-heading h2 {
  margin: 0;
  font-size: 16px;
  letter-spacing: -0.015em;
}

.required-note,
.selection-count {
  color: #7a8495;
  font-size: 11px;
  font-weight: 700;
}

.selection-count {
  color: var(--primary, #1f8f68);
}

.panel-description {
  margin: -5px 0 17px;
  color: #7a8495;
  font-size: 12px;
  line-height: 1.55;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
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
.field select {
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

.field input:focus,
.field select:focus {
  border-color: var(--primary, #1f8f68);
  box-shadow: 0 0 0 3px rgb(31 143 104 / 10%);
}

.field input.invalid,
.field select.invalid {
  border-color: #d85c5c;
}

.field small,
.field-error {
  color: #b33d3d;
  font-size: 11px;
}

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
  color: #7a8495;
  background: transparent;
  cursor: pointer;
}

.password-toggle:hover {
  color: var(--primary, #1f8f68);
  background: var(--surface-muted, #f3f5f7);
}

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
  color: #7a8495;
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
  color: #7a8495;
  background: var(--surface-muted, #fafbfc);
}

.empty-role-icon {
  display: grid;
  width: 36px;
  height: 36px;
  flex: 0 0 36px;
  place-items: center;
  border-radius: 9px;
  color: var(--text-muted, #7b8794);
  background: var(--surface, #fff);
}

.empty-roles strong {
  display: block;
  color: #394457;
  font-size: 12px;
}

.empty-roles p {
  margin: 3px 0 0;
  font-size: 11px;
  line-height: 1.5;
}

.loading-state {
  display: flex;
  align-items: center;
  gap: 9px;
  min-height: 86px;
  color: #7a8495;
  font-size: 12px;
}

.loading-icon {
  display: grid;
  width: 34px;
  height: 34px;
  place-items: center;
  border-radius: 9px;
  color: var(--primary, #1f8f68);
  background: var(--primary-soft, #eef9f4);
}

.loading-icon .ui-icon {
  animation: spin 900ms linear infinite;
}

.form-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  grid-column: 1 / -1;
  gap: 14px;
  padding: 4px 2px 0;
  color: #7a8495;
  font-size: 11px;
}

.form-footer > span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.form-footer > span:first-child i {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #55a56b;
}

.footer-actions {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-left: auto;
}

@keyframes access-enter {
  from {
    opacity: 0;
    transform: translateY(8px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1000px) {
  .content-grid {
    grid-template-columns: 1fr;
  }

  .roles-panel {
    grid-column: auto;
  }

  .role-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
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

  .form-footer {
    align-items: flex-start;
    flex-direction: column;
  }

  .footer-actions {
    width: 100%;
    margin-left: 0;
  }

  .footer-actions .btn {
    flex: 1;
  }
}

@media (max-width: 600px) {
  .form-grid,
  .role-grid {
    grid-template-columns: 1fr;
  }

  .field.full {
    grid-column: auto;
  }

  .header-actions,
  .header-actions .btn,
  .footer-actions,
  .footer-actions .btn {
    width: 100%;
  }

  .header-actions,
  .footer-actions {
    flex-direction: column;
  }

  .panel {
    padding: 16px;
  }
}

@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    scroll-behavior: auto !important;
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}
</style>
