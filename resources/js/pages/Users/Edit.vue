<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';

import { Head, router, usePage } from '@inertiajs/vue3';

import ErpLayout from '@/layouts/ErpLayout.vue';

import Icon from '@/components/dashboard/Icon.vue';

interface Role {
    id: number;

    name: string;

    slug: string;

    isSystem: boolean;

    isActive: boolean;
}

interface LocationOption { id: number; name: string; code: string; type: string; }
interface LocationAssignment { location_id: number; access_level: 'view' | 'receive' | 'manage'; }

interface RoleApiPayload {
    id: number;
    name: string;
    slug: string;
    isSystem?: boolean;
    isActive?: boolean;
    is_system?: boolean;
    is_active?: boolean;
}

interface UserApiPayload {
    id: number;

    name: string;

    email: string;

    status: 'active' | 'inactive' | string;

    last_login_at?: string | null;

    last_login_ip?: string | null;

    failed_login_attempts?: number;

    locked_until?: string | null;

    password_changed_at?: string | null;

    email_verified_at?: string | null;

    created_at?: string | null;

    updated_at?: string | null;

    roles?: Role[];
    designation?: string | null;
    locations?: Array<{ id: number; name: string; code: string; type: string; access_level: LocationAssignment['access_level'] }>;
}

interface User {
    id: number;

    name: string;

    email: string;

    status: 'active' | 'inactive' | string;

    lastLoginAt: string | null;

    lastLoginIp: string | null;

    failedLoginAttempts: number;

    lockedUntil: string | null;

    passwordChangedAt: string | null;

    emailVerifiedAt: string | null;

    createdAt: string | null;

    updatedAt: string | null;

    roles: Role[];

    designation: string;

    locations: Array<{ id: number; name: string; code: string; type: string; access_level: LocationAssignment['access_level'] }>;
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

        validationErrors: Record<string, string> = {},
    ) {
        super(message);

        this.name = 'ApiRequestError';

        this.status = status;

        this.validationErrors = validationErrors;
    }
}

const page = usePage<PageProps>();

const authUser = computed<AuthUser>(() => {
    return (
        page.props.auth?.user ?? {
            id: 0,

            name: 'Administrator',

            email: '',
        }
    );
});

const userId = Number(
    window.location.pathname.match(/\/users\/(\d+)(?:\/edit|\/|$)/)?.[1] ?? 0,
);

const user = ref<User | null>(null);

const roleCatalog = ref<Role[]>([]);
const locations = ref<LocationOption[]>([]);
const canManageLocations = ref(false);
const locationAssignments = ref<LocationAssignment[]>([]);

const isLoadingRoles = ref(false);

const isLoading = ref(true);

const isSaving = ref(false);

const showPassword = ref(false);

const showPasswordConfirmation = ref(false);

const formErrors = ref<Record<string, string>>({});

const operationMessage = ref('');

const operationMessageType = ref<'success' | 'error'>('success');

const form = reactive({
    name: '',
    designation: '',

    email: '',

    password: '',

    password_confirmation: '',

    status: 'active',

    role_ids: [] as number[],
});

function normalizeUser(payload: UserApiPayload): User {
    return {
        id: payload.id,

        name: payload.name,
        designation: payload.designation ?? '',
        locations: payload.locations ?? [],

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

const activeRoles = computed(() => {
    return user.value?.roles.filter((role) => role.isActive) ?? [];
});

const systemRoles = computed(() => {
    return user.value?.roles.filter((role) => role.isSystem) ?? [];
});

const assignableRoles = computed(() =>
    roleCatalog.value
        .filter((role) => role.isActive && !role.isSystem)
        .sort((a, b) => a.name.localeCompare(b.name)),
);

const accountLocked = computed(() => {
    if (!user.value?.lockedUntil) {
        return false;
    }

    const timestamp = new Date(user.value.lockedUntil).getTime();

    return !Number.isNaN(timestamp) && timestamp > Date.now();
});

const accountStatusLabel = computed(() => {
    if (!user.value) {
        return 'Unknown';
    }

    return (
        user.value.status.charAt(0).toUpperCase() + user.value.status.slice(1)
    );
});

async function apiRequest<T>(
    url: string,

    method: 'GET' | 'PUT',

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

    const requestInit: RequestInit = {
        method,

        headers,

        credentials: 'same-origin',
    };

    if (body !== undefined && method !== 'GET') {
        requestInit.body = JSON.stringify(body);
    }

    const response = await fetch(url, requestInit);

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
            errorPayload.message ??
                `Request failed with status ${response.status}.`,

            response.status,

            validationErrors,
        );
    }

    if (payload && typeof payload === 'object' && 'data' in payload) {
        return (payload as ApiSuccessPayload<T>).data;
    }

    return payload as T;
}

async function loadRoles(): Promise<void> {
    isLoadingRoles.value = true;

    try {
        const response = await apiRequest<RoleApiPayload[]>(
            '/api/roles',
            'GET',
        );
        roleCatalog.value = response.map((item) => ({
            id: Number(item.id),
            name: String(item.name ?? ''),
            slug: String(item.slug ?? ''),
            isSystem: Boolean(item.isSystem ?? item.is_system),
            isActive: Boolean(item.isActive ?? item.is_active),
        }));
    } catch (error) {
        operationMessageType.value = 'error';
        operationMessage.value =
            error instanceof Error
                ? `Unable to load available roles: ${error.message}`
                : 'Unable to load available roles.';
    } finally {
        isLoadingRoles.value = false;
    }
}

function syncFormFromUser(currentUser: User): void {
    form.name = currentUser.name;
    form.designation = currentUser.designation ?? '';
    locationAssignments.value = (currentUser.locations ?? []).map((item) => ({ location_id: item.id, access_level: item.access_level }));

    form.email = currentUser.email;

    form.status = currentUser.status;

    form.role_ids = currentUser.roles

        .filter((role) => role.isActive && !role.isSystem)

        .map((role) => role.id);

    form.password = '';

    form.password_confirmation = '';
}

async function loadUser(): Promise<void> {
    if (!userId) {
        isLoading.value = false;

        operationMessageType.value = 'error';

        operationMessage.value = 'A valid user ID is required.';

        return;
    }

    isLoading.value = true;

    operationMessage.value = '';

    try {
        const payload = await apiRequest<UserApiPayload>(
            `/api/users/${userId}`,

            'GET',
        );

        const normalizedUser = normalizeUser(payload);

        user.value = normalizedUser;

        syncFormFromUser(normalizedUser);
    } catch (error) {
        operationMessageType.value = 'error';

        operationMessage.value =
            error instanceof Error ? error.message : 'Unable to load the user.';
    } finally {
        isLoading.value = false;
    }
}

async function loadLocations(): Promise<void> {
    try {
        const response = await apiRequest<LocationOption[] | { data: LocationOption[] }>('/api/users/options/locations', 'GET');
        locations.value = Array.isArray(response) ? response : response.data;
        canManageLocations.value = true;
    } catch {
        locations.value = [];
        canManageLocations.value = false;
    }
}

function setLocationAccess(locationId: number, accessLevel: LocationAssignment['access_level'] | ''): void {
    locationAssignments.value = locationAssignments.value.filter((item) => item.location_id !== locationId);
    if (accessLevel) locationAssignments.value.push({ location_id: locationId, access_level: accessLevel });
}

function locationAccess(locationId: number): LocationAssignment['access_level'] | '' {
    return locationAssignments.value.find((item) => item.location_id === locationId)?.access_level ?? '';
}

function toggleRole(roleId: number): void {
    form.role_ids = form.role_ids.includes(roleId)
        ? form.role_ids.filter((id) => id !== roleId)
        : [...form.role_ids, roleId];

    delete formErrors.value.role_ids;
}

function clearFieldError(field: string): void {
    delete formErrors.value[field];
}

async function save(): Promise<void> {
    if (!user.value || isSaving.value) {
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

    if (form.password && form.password !== form.password_confirmation) {
        formErrors.value.password_confirmation = 'Passwords do not match.';
    }

    if (Object.keys(formErrors.value).length > 0) {
        operationMessageType.value = 'error';

        operationMessage.value = 'Please correct the highlighted fields.';

        return;
    }

    isSaving.value = true;

    const payload: Record<string, unknown> = {
        name: form.name.trim(),
        designation: form.designation.trim() || null,
        ...(canManageLocations.value ? { location_assignments: locationAssignments.value } : {}),

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

            'PUT',

            payload,
        );

        const normalizedUser = normalizeUser(response);

        user.value = normalizedUser;

        syncFormFromUser(normalizedUser);

        operationMessageType.value = 'success';

        operationMessage.value = 'User updated successfully.';
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

            operationMessage.value =
                'Unable to update the user. Please try again.';
        }
    } finally {
        isSaving.value = false;
    }
}

function back(): void {
    router.get('/users');
}

function showUser(): void {
    if (!user.value) {
        return;
    }

    router.get(`/users/${user.value.id}`);
}

function formatDate(value?: string | null, fallback = 'Never'): string {
    if (!value) {
        return fallback;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',

        month: 'short',

        day: '2-digit',

        hour: '2-digit',

        minute: '2-digit',
    }).format(date);
}

function formatDateOnly(value?: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',

        month: 'short',

        day: '2-digit',
    }).format(date);
}

onMounted(() => {
    void loadUser();
    void loadRoles();
    void loadLocations();
});
</script>

<template>
    <Head :title="user ? `Edit — ${user.name}` : 'Edit User'" />

    <ErpLayout :user-name="authUser.name" :user-email="authUser.email">
        <div class="user-edit-page">
            <section
                v-if="isLoading"
                class="state-page page-enter"
                aria-live="polite"
            >
                <div class="state-icon">
                    <Icon name="users" :size="24" />
                </div>

                <strong>Loading user...</strong>

                <span>
                    Retrieving account information and access details.
                </span>
            </section>

            <template v-else-if="user">
                <div class="page-enter">
                    <header class="page-header">
                        <div class="heading-copy">
                            <nav class="breadcrumb" aria-label="Breadcrumb">
                                <button
                                    class="breadcrumb-link"
                                    type="button"
                                    @click="back"
                                >
                                    <Icon name="users" :size="14" />

                                    <span>Users</span>
                                </button>

                                <span
                                    class="breadcrumb-separator"
                                    aria-hidden="true"
                                >
                                    /
                                </span>

                                <button
                                    class="breadcrumb-link"
                                    type="button"
                                    @click="showUser"
                                >
                                    <span>{{ user.name }}</span>
                                </button>

                                <span
                                    class="breadcrumb-separator"
                                    aria-hidden="true"
                                >
                                    /
                                </span>

                                <span class="breadcrumb-current"> Edit </span>
                            </nav>

                            <div class="title-row">
                                <div class="page-title-icon" aria-hidden="true">
                                    <Icon name="edit" :size="22" />
                                </div>

                                <div class="title-copy">
                                    <span class="eyebrow">
                                        Identity Management
                                    </span>

                                    <h1>Edit User</h1>

                                    <p>
                                        Update account identity, security
                                        settings and assigned access.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="header-actions">
                            <button
                                class="btn btn-secondary"
                                type="button"
                                :disabled="isSaving"
                                @click="showUser"
                            >
                                <Icon name="arrow-left" :size="15" />

                                Cancel
                            </button>

                            <button
                                class="btn btn-primary"
                                type="button"
                                :disabled="isSaving"
                                @click="save"
                            >
                                <Icon
                                    v-if="!isSaving"
                                    name="check"
                                    :size="15"
                                />

                                {{ isSaving ? 'Saving...' : 'Save Changes' }}
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
                            :name="
                                operationMessageType === 'success'
                                    ? 'check'
                                    : 'lock'
                            "
                            :size="15"
                        />

                        <span>{{ operationMessage }}</span>
                    </div>

                    <section
                        class="summary-grid"
                        aria-label="User account summary"
                    >
                        <article class="summary-card">
                            <div class="summary-icon">
                                <Icon name="check" :size="16" />
                            </div>

                            <div>
                                <span>Status</span>

                                <strong
                                    class="status-value"
                                    :class="user.status"
                                >
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
                                    {{ formatDate(user.lastLoginAt, 'Never') }}
                                </strong>
                            </div>
                        </article>
                    </section>

                    <form class="content-grid" @submit.prevent="save">
                        <section class="panel">
                            <header class="panel-heading">
                                <div>
                                    <span class="section-kicker">
                                        Account
                                    </span>

                                    <h2>Profile &amp; security</h2>

                                    <p>
                                        Update identity and authentication
                                        settings.
                                    </p>
                                </div>

                                <div class="panel-icon" aria-hidden="true">
                                    <Icon name="settings" :size="17" />
                                </div>
                            </header>

                            <div class="form-grid">
                                <label class="field">
                                    <span>
                                        Name

                                        <em>*</em>
                                    </span>

                                    <input
                                        v-model="form.name"
                                        type="text"
                                        autocomplete="name"
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
                                    <span>
                                        Email

                                        <em>*</em>
                                    </span>

                                    <input
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="email"
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

                                    <select v-model="form.status">
                                        <option value="active">Active</option>

                                        <option value="inactive">
                                            Inactive
                                        </option>
                                    </select>
                                </label>

                                <div class="field">
                                    <span>Account ID</span>

                                    <div class="readonly-value">
                                        #{{ user.id }}
                                    </div>
                                </div>

                                <label class="field">
                                    <span>New password</span>

                                    <div class="input-with-action">
                                        <input
                                            v-model="form.password"
                                            :type="
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            autocomplete="new-password"
                                            placeholder="Leave blank to keep current password"
                                            :class="{
                                                invalid: formErrors.password,
                                            }"
                                            @input="clearFieldError('password')"
                                        />

                                        <button
                                            class="input-action"
                                            type="button"
                                            @click="
                                                showPassword = !showPassword
                                            "
                                        >
                                            {{ showPassword ? 'Hide' : 'Show' }}
                                        </button>
                                    </div>

                                    <small v-if="formErrors.password">
                                        {{ formErrors.password }}
                                    </small>
                                </label>

                                <label class="field">
                                    <span> Confirm new password </span>

                                    <div class="input-with-action">
                                        <input
                                            v-model="form.password_confirmation"
                                            :type="
                                                showPasswordConfirmation
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            autocomplete="new-password"
                                            placeholder="Repeat new password"
                                            :class="{
                                                invalid:
                                                    formErrors.password_confirmation,
                                            }"
                                            @input="
                                                clearFieldError(
                                                    'password_confirmation',
                                                )
                                            "
                                        />

                                        <button
                                            class="input-action"
                                            type="button"
                                            @click="
                                                showPasswordConfirmation =
                                                    !showPasswordConfirmation
                                            "
                                        >
                                            {{
                                                showPasswordConfirmation
                                                    ? 'Hide'
                                                    : 'Show'
                                            }}
                                        </button>
                                    </div>

                                    <small
                                        v-if="formErrors.password_confirmation"
                                    >
                                        {{ formErrors.password_confirmation }}
                                    </small>
                                </label>
                            </div>

                            <div class="security-grid">
                                <article class="security-item">
                                    <span>Last login</span>

                                    <strong>
                                        {{
                                            formatDate(
                                                user.lastLoginAt,

                                                'Never',
                                            )
                                        }}
                                    </strong>
                                </article>

                                <article class="security-item">
                                    <span>Login IP</span>

                                    <strong>
                                        {{
                                            user.lastLoginIp ?? 'Not available'
                                        }}
                                    </strong>
                                </article>

                                <article class="security-item">
                                    <span>Password changed</span>

                                    <strong>
                                        {{
                                            formatDate(
                                                user.passwordChangedAt,

                                                'Not recorded',
                                            )
                                        }}
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
                                                : 'Not locked'
                                        }}
                                    </strong>
                                </article>
                            </div>
                        </section>

                        <section v-if="canManageLocations" class="panel" aria-labelledby="location-access-title">
                            <header class="panel-heading"><div><span class="section-kicker">Operational scope</span><h2 id="location-access-title">Location access</h2><p>Choose which locations this user can access.</p></div></header>
                            <div v-if="locations.length === 0" class="empty-state"><strong>No locations available</strong><span>Ensure you have Location Management permission and active locations exist.</span></div>
                            <div v-else class="form-grid">
                                <label v-for="location in locations" :key="location.id" class="field full">
                                    <span>{{ location.name }} <small>({{ location.code }} · {{ location.type }})</small></span>
                                    <select :value="locationAccess(location.id)" @change="setLocationAccess(location.id, ($event.target as HTMLSelectElement).value as LocationAssignment['access_level'] | '')">
                                        <option value="">No access</option><option value="view">View only</option><option value="receive">Receive transfers</option><option value="manage">Manage &amp; receive</option>
                                    </select>
                                </label>
                            </div>
                            <small v-if="formErrors.location_assignments" class="form-error">{{ formErrors.location_assignments }}</small>
                        </section>

                        <section class="panel">
                            <header class="panel-heading">
                                <div>
                                    <span class="section-kicker">
                                        Access Control
                                    </span>

                                    <h2>Assigned roles</h2>

                                    <p>
                                        Choose one or more active roles. System
                                        roles are protected.
                                    </p>
                                </div>

                                <span class="selection-count">
                                    {{ form.role_ids.length }}

                                    selected
                                </span>
                            </header>

                            <div
                                v-if="isLoadingRoles"
                                class="role-list"
                                role="status"
                                aria-live="polite"
                            >
                                <div class="empty-state">
                                    <strong>Loading available roles…</strong>
                                    <span
                                        >Please wait while role permissions are
                                        retrieved.</span
                                    >
                                </div>
                            </div>

                            <div v-else class="role-list">
                                <button
                                    v-for="role in assignableRoles"
                                    :key="role.id"
                                    type="button"
                                    class="role-row role-button"
                                    :class="{
                                        selected: form.role_ids.includes(
                                            role.id,
                                        ),
                                    }"
                                    @click="toggleRole(role.id)"
                                >
                                    <span class="role-icon" aria-hidden="true">
                                        <Icon name="users" :size="14" />
                                    </span>

                                    <span class="role-copy">
                                        <strong>
                                            {{ role.name }}
                                        </strong>

                                        <small>
                                            {{ role.slug }}
                                        </small>
                                    </span>

                                    <span class="role-check" aria-hidden="true">
                                        <Icon
                                            v-if="
                                                form.role_ids.includes(role.id)
                                            "
                                            name="check"
                                            :size="13"
                                        />
                                    </span>
                                </button>

                                <div
                                    v-if="assignableRoles.length === 0"
                                    class="empty-state"
                                >
                                    <div class="empty-icon">
                                        <Icon name="users" :size="19" />
                                    </div>

                                    <strong> No assignable roles </strong>

                                    <span
                                        >No active non-system roles are
                                        available. Create or activate a role
                                        first.</span
                                    >
                                </div>
                            </div>

                            <small
                                v-if="formErrors.role_ids"
                                class="form-error"
                                >{{ formErrors.role_ids }}</small
                            >

                            <div
                                v-if="systemRoles.length > 0"
                                class="system-role-note"
                            >
                                <Icon name="developer" :size="14" />

                                <span>
                                    {{ systemRoles.length }}

                                    system role{{
                                        systemRoles.length === 1 ? '' : 's'
                                    }}

                                    protected from normal user management.
                                </span>
                            </div>
                        </section>
                    </form>

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
                                            ? formatDateOnly(
                                                  user.emailVerifiedAt,
                                              )
                                            : 'Not verified'
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
                    {{
                        operationMessage ||
                        'The requested user could not be loaded.'
                    }}
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
.user-edit-page {
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

    margin-bottom: 18px;
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

    color: var(--text-tertiary, #7b8794);

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
    color: var(--text-tertiary, #8a94a5);
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

    border: 1px solid var(--border, #dfe5ec);

    border-radius: 13px;

    color: var(--primary, #1f8f68);

    background: color-mix(
        in srgb,
        var(--primary, #1f8f68) 8%,
        var(--surface, #ffffff)
    );
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

    border: 1px solid var(--border, #dfe4ec);

    border-radius: 10px;

    font: inherit;

    font-size: 13px;

    font-weight: 750;

    line-height: 1;

    cursor: pointer;

    transition:
        border-color 160ms ease,
        background 160ms ease,
        transform 160ms ease;
}

.btn:hover:not(:disabled) {
    transform: translateY(-1px);
}

.btn:disabled {
    opacity: 0.55;

    cursor: not-allowed;
}

.btn-primary {
    color: #ffffff;

    border-color: var(--primary, #1f8f68);

    background: var(--primary, #1f8f68);
}

.btn-secondary {
    color: #384356;

    background: var(--surface, #ffffff);
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

    border: 1px solid var(--border, var(--border, #e7ebf0));

    border-radius: 13px;

    background: var(--surface, #fff);

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

.summary-card span {
    display: block;

    margin-bottom: 3px;

    color: var(--text-tertiary, #7b8794);

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

    border: 1px solid var(--border, var(--border, #e7ebf0));

    border-radius: 15px;

    background: var(--surface, #fff);

    box-shadow: 0 6px 22px rgba(28, 39, 56, 0.035);
}

.panel-heading {
    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 14px;

    padding: 18px 18px 15px;

    border-bottom: 1px solid var(--border, var(--border, #edf0f3));
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

.form-grid {
    display: grid;

    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 16px;

    padding: 18px;
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

.field em {
    color: #bd4545;

    font-style: normal;
}

.field input,
.field select {
    width: 100%;

    min-height: 38px;

    box-sizing: border-box;

    padding: 9px 11px;

    border: 1px solid #dfe4eb;

    border-radius: 10px;

    outline: none;

    color: #202b3a;

    background: var(--surface, #ffffff);

    font: inherit;

    font-size: 13px;

    transition:
        border-color 160ms ease,
        box-shadow 160ms ease;
}

.field input:focus,
.field select:focus {
    border-color: #8995a8;

    box-shadow: 0 0 0 3px rgba(137, 149, 168, 0.12);
}

.field input.invalid {
    border-color: #d96565;
}

.field small {
    color: #b13d3d;

    font-size: 11px;
}

.readonly-value {
    display: flex;

    min-height: 38px;

    box-sizing: border-box;

    align-items: center;

    padding: 9px 11px;

    border: 1px solid var(--border, #e7ebf0);

    border-radius: 10px;

    color: #667186;

    background: #f7f8fa;

    font-size: 13px;

    font-weight: 700;
}

.input-with-action {
    position: relative;
}

.input-with-action input {
    padding-right: 62px;
}

.input-action {
    position: absolute;

    top: 50%;

    right: 9px;

    padding: 3px;

    border: 0;

    transform: translateY(-50%);

    color: #667186;

    background: transparent;

    font: inherit;

    font-size: 11px;

    font-weight: 800;

    cursor: pointer;
}

.security-grid {
    display: grid;

    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 1px;

    background: var(--border, #edf0f3);
}

.security-item {
    min-width: 0;

    padding: 14px 18px;

    background: var(--surface, #fff);
}

.security-item span {
    display: block;

    margin-bottom: 4px;

    color: var(--text-tertiary, #7b8794);

    font-size: 11px;

    font-weight: 650;
}

.security-item strong {
    display: block;

    overflow: hidden;

    color: #202a38;

    font-size: 12px;

    font-weight: 700;

    line-height: 1.45;

    text-overflow: ellipsis;
}

.security-warning {
    color: #b2761e !important;
}

.selection-count {
    flex-shrink: 0;

    color: #8a94a5;

    font-size: 11px;

    font-weight: 700;
}

.role-list {
    display: grid;
}

.role-button {
    width: 100%;

    text-align: left;

    cursor: pointer;
}

.role-row {
    display: flex;

    align-items: center;

    gap: 11px;

    min-width: 0;

    padding: 13px 16px;

    border: 0;

    border-bottom: 1px solid var(--border, #edf0f3);

    color: inherit;

    background: var(--surface, #ffffff);

    font: inherit;

    transition:
        background 150ms ease,
        border-color 150ms ease;
}

.role-row:last-child {
    border-bottom: 0;
}

.role-row:hover {
    background: #fafcfb;
}

.role-row.selected {
    background: #f3faf7;
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

.role-check {
    display: grid;

    width: 25px;

    height: 25px;

    flex: 0 0 25px;

    place-items: center;

    border: 1px solid #dfe4eb;

    border-radius: 7px;

    color: #fff;

    background: var(--surface, #ffffff);
}

.role-row.selected .role-check {
    border-color: #1f8f68;

    background: #1f8f68;
}

.system-role-note {
    display: flex;

    align-items: flex-start;

    gap: 7px;

    margin: 0 16px 16px;

    padding: 10px 11px;

    border: 1px solid #eadff4;

    border-radius: 9px;

    color: #6c4b9e;

    background: #faf7fd;

    font-size: 11px;

    line-height: 1.5;
}

.empty-state {
    display: grid;

    place-items: center;

    gap: 7px;

    min-height: 170px;

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

    background: var(--surface-muted, var(--surface, #f3f5f7));
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

    border: 1px solid var(--border, var(--border, #e7ebf0));

    border-radius: 12px;

    background: var(--surface, #fff);
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

.metadata-card span {
    display: block;

    margin-bottom: 4px;

    color: #7b8794;

    font-size: 11px;

    font-weight: 650;
}

.metadata-card strong {
    display: block;

    overflow: hidden;

    color: #202a38;

    font-size: 12px;

    font-weight: 700;

    text-overflow: ellipsis;

    white-space: nowrap;
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

.state-page h2 {
    margin: 0;

    color: #273243;

    font-size: 18px;
}

.state-icon {
    display: grid;

    width: 48px;

    height: 48px;

    margin-bottom: 3px;

    place-items: center;

    border-radius: 13px;

    color: #657184;

    background: var(--surface-muted, var(--surface, #f3f5f7));
}

.state-icon.error {
    color: #ad4a4a;

    background: #fff0f0;
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

    .form-grid,
    .summary-grid,
    .metadata-grid,
    .security-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 520px) {
    h1 {
        font-size: 24px;
    }

    .title-row {
        align-items: flex-start;
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
