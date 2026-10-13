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

    operationMessage.value = '';

    try {

        const response = await apiRequest<Role[] | { data: Role[] }>(
            '/api/roles',
            'GET',
        );

        // Accept both Laravel snake_case fields and frontend camelCase fields.
        const roleItems = Array.isArray(response) ? response : response.data;
        roles.value = roleItems.map((item) => {
            const raw = item as Role & {
                is_system?: boolean;
                is_active?: boolean;
            };

            return {
                id: Number(raw.id),
                name: String(raw.name ?? ''),
                slug: String(raw.slug ?? ''),
                isSystem: Boolean(raw.isSystem ?? raw.is_system),
                isActive: Boolean(raw.isActive ?? raw.is_active),
            };
        });

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

    if (operationMessageType.value === 'error') {

        operationMessage.value = '';

    }

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

    if (!form.password_confirmation) {

        formErrors.value.password_confirmation =

            'Password confirmation is required.';

    }

    if (

        form.password &&

        form.password_confirmation &&

        form.password !== form.password_confirmation

    ) {

        formErrors.value.password_confirmation = 'Passwords do not match.';

    }

    if (Object.keys(formErrors.value).length > 0) {

        operationMessageType.value = 'error';

        operationMessage.value = 'Please correct the highlighted fields.';

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

            operationMessage.value =

                'Unable to create the user. Please try again.';

        }

    } finally {

        isSubmitting.value = false;

    }

}

function backToUsers(): void {

    router.get('/users');

}

onMounted(() => {

    void loadRoles();

});

</script>

<template>

    <Head title="Create User" />

    <ErpLayout :user-name="form.name" :user-email="form.email">

        <div class="user-create-page">

            <header class="page-header">

                <div class="page-header-copy">

                    <nav class="page-breadcrumb" aria-label="Breadcrumb">

                        <span>Identity &amp; Access</span>

                        <Icon

                            name="chevron-right"

                            :size="12"

                            class="breadcrumb-icon"

                        />

                        <span>Users</span>

                        <Icon

                            name="chevron-right"

                            :size="12"

                            class="breadcrumb-icon"

                        />

                        <span class="is-current"> Create User </span>

                    </nav>

                    <div class="page-title-row">

                        <div class="page-title-icon" aria-hidden="true">

                            <Icon name="users" :size="21" />

                        </div>

                        <div class="page-title-copy">

                            <h1>Create User</h1>

                            <p>

                                Create an application user and assign the

                                appropriate non-system roles.

                            </p>

                        </div>

                    </div>

                </div>

                <div class="header-actions">

                    <button

                        class="btn btn-secondary"

                        type="button"

                        @click="backToUsers"

                    >

                        <Icon name="chevron-left" :size="15" />

                        <span>Back to Users</span>

                    </button>

                    <button

                        class="btn btn-primary"

                        type="submit"

                        form="create-user-form"

                        :disabled="isSubmitting"

                    >

                        <Icon name="plus" :size="15" />

                        <span>

                            {{ isSubmitting ? 'Creating...' : 'Create User' }}

                        </span>

                    </button>

                </div>

            </header>

            <div

                v-if="operationMessage"

                class="operation-message"

                :class="operationMessageType"

                role="alert"

            >

                {{ operationMessage }}

            </div>

            <form

                id="create-user-form"

                class="content-grid"

                novalidate

                @submit.prevent="submit"

            >

                <section

                    class="panel"

                    aria-labelledby="basic-information-title"

                >

                    <header class="panel-heading">

                        <div>

                            <span class="section-kicker"> Profile </span>

                            <h2 id="basic-information-title">

                                Basic information

                            </h2>

                        </div>

                        <span class="required-note"> \* Required </span>

                    </header>

                    <div class="form-grid">

                        <label class="field full">

                            <span>

                                Full name

                                <em>\*</em>

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

                                <em>\*</em>

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

                        <label class="field">

                            <span>

                                Password

                                <em>\*</em>

                            </span>

                            <div class="input-with-action">

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

                                    type="button"

                                    class="input-action"

                                    :aria-label="

                                        showPassword

                                            ? 'Hide password'

                                            : 'Show password'

                                    "

                                    @click="showPassword = !showPassword"

                                >

                                    {{ showPassword ? 'Hide' : 'Show' }}

                                </button>

                            </div>

                            <small v-if="formErrors.password">

                                {{ formErrors.password }}

                            </small>

                        </label>

                        <label class="field">

                            <span>

                                Confirm password

                                <em>\*</em>

                            </span>

                            <div class="input-with-action">

                                <input

                                    v-model="form.password_confirmation"

                                    :type="

                                        showPasswordConfirmation

                                            ? 'text'

                                            : 'password'

                                    "

                                    autocomplete="new-password"

                                    placeholder="Repeat password"

                                    :class="{

                                        invalid: hasError(

                                            'password_confirmation',

                                        ),

                                    }"

                                    @input="

                                        clearFieldError('password_confirmation')

                                    "

                                />

                                <button

                                    type="button"

                                    class="input-action"

                                    :aria-label="

                                        showPasswordConfirmation

                                            ? 'Hide password confirmation'

                                            : 'Show password confirmation'

                                    "

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

                <section class="panel" aria-labelledby="assign-roles-title">

                    <header class="panel-heading">

                        <div>

                            <span class="section-kicker"> Access Control </span>

                            <h2 id="assign-roles-title">Assign roles</h2>

                        </div>

                        <span class="selection-count">

                            {{ form.role_ids.length }} selected

                        </span>

                    </header>

                    <p class="panel-description">

                        Only active non-system roles are available through

                        normal user management.

                    </p>

                    <div

                        v-if="isLoadingRoles"

                        class="loading-state"

                        role="status"

                        aria-live="polite"

                    >

                        <div class="state-icon">

                            <Icon name="users" :size="19" />

                        </div>

                        <strong> Loading available roles... </strong>

                    </div>

                    <div

                        v-else-if="availableRoles.length === 0"

                        class="empty-state"

                    >

                        <div class="state-icon">

                            <Icon name="users" :size="21" />

                        </div>

                        <strong> No assignable roles </strong>

                        <span>

                            Create or activate a non-system role before

                            assigning access to a user.

                        </span>

                    </div>

                    <div v-else class="role-grid" aria-label="Available roles">

                        <button

                            v-for="role in availableRoles"

                            :key="role.id"

                            type="button"

                            class="role-card"

                            :class="{

                                selected: form.role_ids.includes(role.id),

                            }"

                            :aria-pressed="form.role_ids.includes(role.id)"

                            @click="toggleRole(role.id)"

                        >

                            <span class="role-check" aria-hidden="true">

                                <Icon

                                    v-if="form.role_ids.includes(role.id)"

                                    name="check"

                                    :size="13"

                                />

                            </span>

                            <span class="role-copy">

                                <strong>{{ role.name }}</strong>

                                <small>

                                    {{ role.slug }}

                                </small>

                            </span>

                        </button>

                    </div>

                    <small v-if="formErrors.role_ids" class="form-error">

                        {{ formErrors.role_ids }}

                    </small>

                </section>

            </form>

            <footer class="access-footer">

                <span>

                    <i aria-hidden="true"></i>

                    Authorization is enforced server-side

                </span>

                <span> UI visibility is not a security boundary. </span>

            </footer>

        </div>

    </ErpLayout>

</template>

<style scoped>

.user-create-page {

    width: 100%;

    min-width: 0;

    box-sizing: border-box;

    color: var(--text-primary, #17202a);

    animation: users-page-enter 500ms cubic-bezier(0.22, 1, 0.36, 1) both;

}

.page-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 24px;

    margin-bottom: 22px;

    animation: users-header-enter 480ms cubic-bezier(0.22, 1, 0.36, 1) 20ms both;

}

.page-header-copy {

    min-width: 0;

}

.page-breadcrumb {

    display: flex;

    align-items: center;

    flex-wrap: wrap;

    gap: 7px;

    margin-bottom: 9px;

    color: var(--text-tertiary, #7b8794);

    font-size: 11px;

    font-weight: 600;

    line-height: 1.2;

}

.page-breadcrumb .is-current {

    color: var(--text-secondary, #596573);

}

.breadcrumb-icon {

    flex: 0 0 auto;

    color: var(--border-strong, #cdd4dd);

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

    flex: 0 0 42px;

    align-items: center;

    justify-content: center;

    border: 1px solid

        color-mix(in srgb, var(--primary, #1f8f68) 22%, var(--border, #e5e9ee));

    border-radius: 12px;

    color: var(--primary, #1f8f68);

    background: color-mix(

        in srgb,

        var(--primary, #1f8f68) 10%,

        var(--surface, #ffffff)

    );

}

.page-title-copy {

    min-width: 0;

}

.page-title-copy h1 {

    margin: 0;

    color: var(--text-primary, #17202a);

    font-size: 23px;

    font-weight: 700;

    line-height: 1.12;

    letter-spacing: -0.035em;

}

.page-title-copy p {

    max-width: 620px;

    margin: 7px 0 0;

    color: var(--text-tertiary, #7b8794);

    font-size: 13px;

    line-height: 1.55;

}

.header-actions {

    display: flex;

    align-items: center;

    gap: 9px;

}

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

    transition:

        transform 140ms ease,

        border-color 140ms ease,

        background 140ms ease,

        color 140ms ease,

        opacity 140ms ease,

        box-shadow 140ms ease;

}

.btn:not(:disabled):hover {

    transform: translateY(-1px);

}

.btn:disabled {

    cursor: not-allowed;

    opacity: 0.55;

}

.btn-primary {

    color: #ffffff;

    background: var(--primary, #1f8f68);

    box-shadow: 0 5px 14px

        color-mix(in srgb, var(--primary, #1f8f68) 18%, transparent);

}

.btn-secondary {

    color: var(--text-primary, #17202a);

    border-color: var(--border, #e5e9ee);

    background: var(--surface, #fff);

}

.btn:focus-visible,

.input-action:focus-visible,

.role-card:focus-visible,

input:focus-visible,

select:focus-visible {

    outline: 2px solid

        color-mix(in srgb, var(--primary, #1f8f68) 65%, transparent);

    outline-offset: 2px;

}

.operation-message {

    margin-bottom: 18px;

    padding: 11px 14px;

    border: 1px solid var(--border, #e5e9ee);

    border-radius: 10px;

    font-size: 13px;

}

.operation-message.error {

    border-color: #f1b8b8;

    color: #a52d2d;

    background: #fff6f6;

}

.content-grid {

    display: grid;

    grid-template-columns:

        minmax(0, 1.45fr)

        minmax(320px, 0.85fr);

    gap: 18px;

    animation: users-workspace-enter 520ms cubic-bezier(0.22, 1, 0.36, 1) 80ms

        both;

}

.panel {

    min-width: 0;

    padding: 20px;

    border: 1px solid var(--border, #e5e9ee);

    border-radius: 15px;

    background: var(--surface, #fff);

    box-shadow: 0 8px 28px rgb(15 23 42 / 4%);

}

.panel-heading {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 14px;

    margin-bottom: 18px;

}

.section-kicker {

    display: block;

    color: var(--text-tertiary, #7b8794);

    font-size: 11px;

    font-weight: 800;

    letter-spacing: 0.08em;

    line-height: 1.2;

    text-transform: uppercase;

}

.panel h2 {

    margin: 5px 0 0;

    color: var(--text-primary, #17202a);

    font-size: 18px;

    font-weight: 700;

    line-height: 1.25;

    letter-spacing: -0.02em;

}

.required-note,

.selection-count {

    color: var(--text-tertiary, #7b8794);

    font-size: 12px;

    font-weight: 600;

}

.panel-description {

    margin: -3px 0 0;

    color: var(--text-tertiary, #7b8794);

    font-size: 13px;

    line-height: 1.55;

}

.form-grid {

    display: grid;

    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 16px;

}

.field {

    display: flex;

    min-width: 0;

    flex-direction: column;

    gap: 7px;

}

.field.full {

    grid-column: 1 / -1;

}

.field > span {

    color: var(--text-secondary, #596573);

    font-size: 13px;

    font-weight: 700;

}

.field em {

    color: #bd4545;

    font-style: normal;

}

.field small,

.form-error {

    color: #b13d3d;

    font-size: 12px;

    line-height: 1.45;

}

input,

select {

    width: 100%;

    min-height: 39px;

    box-sizing: border-box;

    padding: 0 12px;

    border: 1px solid var(--border, #e5e9ee);

    border-radius: 10px;

    outline: 0;

    color: var(--text-primary, #17202a);

    background: var(--surface, #fff);

    font: inherit;

    font-size: 13px;

    transition:

        border-color 140ms ease,

        box-shadow 140ms ease;

}

input::placeholder {

    color: #a0a8b4;

}

input:focus,

select:focus {

    border-color: color-mix(

        in srgb,

        var(--primary, #1f8f68) 45%,

        var(--border, #e5e9ee)

    );

    box-shadow: 0 0 0 3px

        color-mix(in srgb, var(--primary, #1f8f68) 10%, transparent);

}

input.invalid {

    border-color: #d96565;

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

    transform: translateY(-50%);

    padding: 4px;

    border: 0;

    background: transparent;

    color: var(--text-tertiary, #7b8794);

    font: inherit;

    font-size: 11px;

    font-weight: 800;

    cursor: pointer;

}

.role-grid {

    display: grid;

    gap: 9px;

    margin-top: 17px;

}

.role-card {

    width: 100%;

    display: flex;

    align-items: center;

    gap: 11px;

    padding: 12px;

    border: 1px solid var(--border, #e5e9ee);

    border-radius: 10px;

    color: inherit;

    background: var(--surface, #fff);

    text-align: left;

    cursor: pointer;

    transition:

        border-color 140ms ease,

        background 140ms ease,

        box-shadow 140ms ease,

        transform 140ms ease;

}

.role-card:hover {

    transform: translateY(-1px);

    border-color: #cfd6df;

}

.role-card.selected {

    border-color: color-mix(

        in srgb,

        var(--primary, #1f8f68) 55%,

        var(--border, #e5e9ee)

    );

    background: color-mix(

        in srgb,

        var(--primary, #1f8f68) 4%,

        var(--surface, #fff)

    );

    box-shadow: 0 0 0 2px

        color-mix(in srgb, var(--primary, #1f8f68) 9%, transparent);

}

.role-check {

    display: grid;

    width: 22px;

    height: 22px;

    flex: 0 0 22px;

    place-items: center;

    border: 1px solid #d8dee7;

    border-radius: 7px;

    color: #fff;

    background: transparent;

}

.role-card.selected .role-check {

    border-color: var(--primary, #1f8f68);

    background: var(--primary, #1f8f68);

}

.role-copy {

    display: block;

    min-width: 0;

}

.role-copy strong {

    display: block;

    overflow: hidden;

    color: var(--text-primary, #293548);

    font-size: 13px;

    font-weight: 700;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.role-copy small {

    display: block;

    margin-top: 3px;

    overflow: hidden;

    color: var(--text-tertiary, #7b8794);

    font-size: 11px;

    text-overflow: ellipsis;

    white-space: nowrap;

}

.loading-state,

.empty-state {

    display: flex;

    min-height: 180px;

    align-items: center;

    justify-content: center;

    flex-direction: column;

    gap: 8px;

    padding: 20px;

    color: var(--text-tertiary, #7b8794);

    text-align: center;

}

.state-icon {

    display: grid;

    width: 42px;

    height: 42px;

    margin-bottom: 3px;

    place-items: center;

    border-radius: 12px;

    color: var(--primary, #1f8f68);

    background: color-mix(

        in srgb,

        var(--primary, #1f8f68) 9%,

        var(--surface, #fff)

    );

}

.empty-state strong,

.loading-state strong {

    color: var(--text-secondary, #394457);

    font-size: 14px;

}

.empty-state span {

    max-width: 280px;

    color: var(--text-tertiary, #7b8794);

    font-size: 12px;

    line-height: 1.6;

}

.form-error {

    display: block;

    margin-top: 9px;

}

.access-footer {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 16px;

    margin-top: 18px;

    color: var(--text-tertiary, #8a94a5);

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

    flex: 0 0 7px;

    border-radius: 50%;

    background: #55a56b;

}

@keyframes users-page-enter {

    from {

        opacity: 0;

        transform: translateY(8px);

    }

    to {

        opacity: 1;

        transform: translateY(0);

    }

}

@keyframes users-header-enter {

    from {

        opacity: 0;

        transform: translateY(7px);

    }

    to {

        opacity: 1;

        transform: translateY(0);

    }

}

@keyframes users-workspace-enter {

    from {

        opacity: 0;

        transform: translateY(9px);

    }

    to {

        opacity: 1;

        transform: translateY(0);

    }

}

@media (max-width: 980px) {

    .content-grid {

        grid-template-columns: 1fr;

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

}

@media (max-width: 720px) {

    .page-title-icon {

        width: 38px;

        height: 38px;

        flex-basis: 38px;

    }

    .page-title-copy h1 {

        font-size: 20px;

    }

    .page-title-copy p {

        font-size: 12px;

    }

    .header-actions {

        align-items: stretch;

        flex-direction: column;

    }

    .header-actions .btn {

        width: 100%;

    }

    .form-grid {

        grid-template-columns: 1fr;

    }

    .field.full {

        grid-column: auto;

    }

    .access-footer {

        align-items: flex-start;

        flex-direction: column;

    }

}

@media (max-width: 560px) {

    .page-header {

        gap: 18px;

        margin-bottom: 18px;

    }

    .page-title-row {

        align-items: flex-start;

    }

    .panel {

        padding: 16px;

    }

    .content-grid {

        gap: 14px;

    }

    .panel-heading {

        gap: 10px;

    }

}

</style>

