<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

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
        validationErrors: Record<string, string> = {},
    ) {
        super(message);
        this.name = 'ApiRequestError';
        this.status = status;
        this.validationErrors = validationErrors;
    }
}

const roles = ref<Role[]>([]);
const isLoadingRoles = ref(false);
const isSubmitting = ref(false);
const formErrors = ref<Record<string, string>>({});
const operationMessage = ref('');
const operationMessageType = ref<'error' | 'info'>('info');
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    status: 'active',
    role_ids: [] as number[],
});

const availableRoles = computed(() =>
    roles.value
        .filter((role) => role.isActive && !role.isSystem)
        .sort((a, b) => a.name.localeCompare(b.name)),
);

async function apiRequest<T>(
    url: string,
    method: 'GET' | 'POST',
    body?: unknown,
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const csrfToken = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    const text = await response.text();
    let payload: unknown = null;

    if (text.trim() !== '') {
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
                ? String(value[0] ?? '')
                : String(value);
        });

        throw new ApiRequestError(
            errorPayload.message ?? `Request failed with status ${response.status}.`,
            response.status,
            validationErrors,
        );
    }

    if (
        payload &&
        typeof payload === 'object' &&
        'data' in payload
    ) {
        return (payload as ApiSuccessPayload<T>).data;
    }

    return payload as T;
}

async function loadRoles(): Promise<void> {
    isLoadingRoles.value = true;

    try {
        roles.value = await apiRequest<Role[]>('/api/roles', 'GET');
    } catch (error) {
        operationMessageType.value = 'error';
        operationMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to load available roles.';
    } finally {
        isLoadingRoles.value = false;
    }
}

function toggleRole(roleId: number): void {
    form.role_ids = form.role_ids.includes(roleId)
        ? form.role_ids.filter((id) => id !== roleId)
        : [...form.role_ids, roleId];
}

function hasError(field: string): boolean {
    return Boolean(formErrors.value[field]);
}

function clearFieldError(field: string): void {
    delete formErrors.value[field];
}

async function submit(): Promise<void> {
    if (isSubmitting.value) {
        return;
    }

    formErrors.value = {};
    operationMessage.value = '';

    if (!form.name.trim()) {
        formErrors.value.name = 'Name is required.';
    }

    if (!form.email.trim()) {
        formErrors.value.email = 'Email is required.';
    }

    if (!form.password) {
        formErrors.value.password = 'Password is required.';
    }

    if (form.password !== form.password_confirmation) {
        formErrors.value.password_confirmation = 'Passwords do not match.';
    }

    if (Object.keys(formErrors.value).length > 0) {
        return;
    }

    isSubmitting.value = true;

    try {
        await apiRequest('/api/users', 'POST', {
            name: form.name.trim(),
            email: form.email.trim(),
            password: form.password,
            password_confirmation: form.password_confirmation,
            status: form.status,
            role_ids: form.role_ids,
        });

        router.get('/users');
    } catch (error) {
        if (error instanceof ApiRequestError) {
            formErrors.value = error.validationErrors;
            operationMessageType.value = 'error';
            operationMessage.value =
                Object.keys(error.validationErrors).length > 0
                    ? 'Please correct the highlighted fields.'
                    : error.message;
        } else {
            operationMessageType.value = 'error';
            operationMessage.value = 'Unable to create the user. Please try again.';
        }
    } finally {
        isSubmitting.value = false;
    }
}

function cancel(): void {
    router.get('/users');
}

onMounted(loadRoles);
</script>

<template>
    <Head title="Create User" />

    <ErpLayout :user-name="form.name" :user-email="form.email">
        <div class="user-create-page">
            <header class="page-header">
                <div class="heading-copy">
                    <button class="back-button" type="button" @click="cancel">
                        <Icon name="arrow-left" :size="16" />
                        <span>Users</span>
                    </button>

                    <span class="eyebrow">Identity Management</span>
                    <h1>Create User</h1>
                    <p>
                        Create an application user and assign the appropriate
                        non-system roles.
                    </p>
                </div>

                <div class="header-actions">
                    <button class="btn secondary" type="button" @click="cancel">
                        Cancel
                    </button>
                    <button
                        class="btn primary"
                        type="button"
                        :disabled="isSubmitting"
                        @click="submit"
                    >
                        <Icon name="plus" :size="15" />
                        {{ isSubmitting ? 'Creating...' : 'Create User' }}
                    </button>
                </div>
            </header>

            <div
                v-if="operationMessage"
                class="notice error"
                role="alert"
            >
                {{ operationMessage }}
            </div>

            <form class="content-grid" @submit.prevent="submit">
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <span class="section-kicker">Profile</span>
                            <h2>Basic information</h2>
                        </div>
                        <span class="required-note">* Required</span>
                    </div>

                    <div class="form-grid">
                        <label class="field full">
                            <span>Full name <em>*</em></span>
                            <input
                                v-model="form.name"
                                type="text"
                                autocomplete="name"
                                placeholder="e.g. John Doe"
                                :class="{ invalid: hasError('name') }"
                                @input="clearFieldError('name')"
                            />
                            <small v-if="formErrors.name">{{ formErrors.name }}</small>
                        </label>

                        <label class="field full">
                            <span>Email address <em>*</em></span>
                            <input
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                placeholder="name@example.com"
                                :class="{ invalid: hasError('email') }"
                                @input="clearFieldError('email')"
                            />
                            <small v-if="formErrors.email">{{ formErrors.email }}</small>
                        </label>

                        <label class="field">
                            <span>Password <em>*</em></span>
                            <div class="input-with-action">
                                <input
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    placeholder="Minimum 12 characters"
                                    :class="{ invalid: hasError('password') }"
                                    @input="clearFieldError('password')"
                                />
                                <button
                                    type="button"
                                    class="input-action"
                                    @click="showPassword = !showPassword"
                                >
                                    {{ showPassword ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                            <small v-if="formErrors.password">{{ formErrors.password }}</small>
                        </label>

                        <label class="field">
                            <span>Confirm password <em>*</em></span>
                            <div class="input-with-action">
                                <input
                                    v-model="form.password_confirmation"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    placeholder="Repeat password"
                                    :class="{ invalid: hasError('password_confirmation') }"
                                    @input="clearFieldError('password_confirmation')"
                                />
                                <button
                                    type="button"
                                    class="input-action"
                                    @click="showPasswordConfirmation = !showPasswordConfirmation"
                                >
                                    {{ showPasswordConfirmation ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                            <small v-if="formErrors.password_confirmation">
                                {{ formErrors.password_confirmation }}
                            </small>
                        </label>

                        <label class="field">
                            <span>Status</span>
                            <select v-model="form.status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </label>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <span class="section-kicker">Access Control</span>
                            <h2>Assign roles</h2>
                        </div>
                        <span class="selection-count">
                            {{ form.role_ids.length }} selected
                        </span>
                    </div>

                    <p class="panel-description">
                        Only active non-system roles are available through normal
                        user management.
                    </p>

                    <div v-if="isLoadingRoles" class="loading-state">
                        Loading available roles...
                    </div>

                    <div v-else-if="availableRoles.length === 0" class="empty-state">
                        <Icon name="users" :size="22" />
                        <strong>No assignable roles</strong>
                        <span>
                            Create or activate a non-system role before assigning
                            access to a user.
                        </span>
                    </div>

                    <div v-else class="role-grid">
                        <button
                            v-for="role in availableRoles"
                            :key="role.id"
                            type="button"
                            class="role-card"
                            :class="{ selected: form.role_ids.includes(role.id) }"
                            @click="toggleRole(role.id)"
                        >
                            <span class="role-check">
                                {{ form.role_ids.includes(role.id) ? '✓' : '' }}
                            </span>
                            <span>
                                <strong>{{ role.name }}</strong>
                                <small>{{ role.slug }}</small>
                            </span>
                        </button>
                    </div>

                    <small v-if="formErrors.role_ids" class="form-error">
                        {{ formErrors.role_ids }}
                    </small>
                </section>
            </form>

            <footer class="access-footer">
                <span><i></i> Authorization is enforced server-side</span>
                <span>UI visibility is not a security boundary.</span>
            </footer>
        </div>
    </ErpLayout>
</template>

<style scoped>
.user-create-page {
    min-height: 100%;
    padding: 30px;
    color: var(--text-primary, #18212f);
}
.page-header {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    align-items: flex-end;
    margin-bottom: 24px;
}
.heading-copy { max-width: 760px; }
.back-button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border: 0;
    background: transparent;
    color: var(--text-secondary, #687386);
    padding: 0;
    margin-bottom: 16px;
    cursor: pointer;
}
.eyebrow, .section-kicker {
    display: block;
    color: #7a8495;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}
h1 { margin: 5px 0 7px; font-size: 30px; letter-spacing: -.03em; }
.heading-copy p, .panel-description {
    margin: 0;
    color: var(--text-secondary, #6d7788);
    line-height: 1.6;
}
.header-actions { display: flex; gap: 10px; }
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
.btn:disabled { opacity: .55; cursor: not-allowed; }
.btn.secondary { background: #fff; color: #384356; }
.btn.primary { background: #18212f; color: #fff; border-color: #18212f; }
.notice {
    border-radius: 10px;
    padding: 12px 15px;
    margin-bottom: 18px;
    font-size: 13px;
}
.notice.error { background: #fff1f1; color: #a53030; border: 1px solid #ffd5d5; }
.content-grid { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(320px, .85fr); gap: 18px; }
.panel {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border-color, #e7ebf0);
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 8px 28px rgba(28, 39, 56, .04);
}
.panel-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}
.panel h2 { margin: 5px 0 0; font-size: 18px; }
.required-note, .selection-count { color: #8a94a5; font-size: 12px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.field { display: flex; flex-direction: column; gap: 7px; }
.field.full { grid-column: 1 / -1; }
.field > span { font-size: 13px; font-weight: 750; color: #394457; }
.field em { color: #bd4545; font-style: normal; }
input, select {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #dfe4eb;
    border-radius: 10px;
    background: #fff;
    color: #202b3a;
    padding: 11px 12px;
    outline: none;
}
input:focus, select:focus { border-color: #8995a8; box-shadow: 0 0 0 3px rgba(137,149,168,.12); }
input.invalid { border-color: #d96565; }
.field small, .form-error { color: #b13d3d; font-size: 12px; }
.input-with-action { position: relative; }
.input-with-action input { padding-right: 62px; }
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
.role-grid { display: grid; gap: 10px; margin-top: 18px; }
.role-card {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    text-align: left;
    border: 1px solid #e2e7ee;
    border-radius: 11px;
    background: #fff;
    padding: 13px;
    cursor: pointer;
}
.role-card.selected { border-color: #8c98a9; box-shadow: 0 0 0 2px rgba(140,152,169,.12); }
.role-card strong, .role-card small { display: block; }
.role-card strong { font-size: 13px; color: #293548; }
.role-card small { margin-top: 3px; color: #8a94a5; font-size: 11px; }
.role-check {
    width: 22px; height: 22px; flex: 0 0 22px;
    display: grid; place-items: center;
    border: 1px solid #d8dee7; border-radius: 7px;
    color: #fff; font-size: 12px; font-weight: 900;
}
.role-card.selected .role-check { background: #18212f; border-color: #18212f; }
.loading-state, .empty-state {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; text-align: center; gap: 8px;
    min-height: 180px; color: #7b8596;
}
.empty-state strong { color: #394457; }
.empty-state span { max-width: 280px; font-size: 12px; line-height: 1.6; }
.access-footer {
    display: flex; justify-content: space-between; gap: 16px;
    margin-top: 18px; color: #8a94a5; font-size: 11px;
}
.access-footer div, .access-footer span { display: inline-flex; align-items: center; gap: 7px; }
.access-footer i { width: 7px; height: 7px; border-radius: 50%; background: #55a56b; }
@media (max-width: 980px) {
    .content-grid { grid-template-columns: 1fr; }
}
@media (max-width: 720px) {
    .user-create-page { padding: 18px; }
    .page-header { align-items: flex-start; flex-direction: column; }
    .header-actions { width: 100%; }
    .header-actions .btn { flex: 1; justify-content: center; }
    .form-grid { grid-template-columns: 1fr; }
    .field.full { grid-column: auto; }
    .access-footer { flex-direction: column; }
}
</style>
