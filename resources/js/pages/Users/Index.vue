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

interface User {
    id: number;
    name: string;
    email: string;
    status: 'active' | 'inactive';
    roles?: Role[];
    created_at?: string;
    updated_at?: string;
}

interface Capabilities {
    canCreate: boolean;
    canUpdate: boolean;
    canView: boolean;
    isDeveloper?: boolean;
}

interface Props {
    users?: User[];
    roles?: Role[];
    capabilities?: Capabilities;
    userName?: string;
    userEmail?: string;
}

const props = withDefaults(defineProps<Props>(), {
    users: () => [],
    roles: () => [],
    capabilities: () => ({
        canCreate: false,
        canUpdate: false,
        canView: false,
        isDeveloper: false,
    }),
    userName: '',
    userEmail: '',
});

const search = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');
const isLoading = ref(false);
const operationMessage = ref('');
const operationMessageType = ref<'success' | 'error'>('success');

const localUsers = ref<User[]>([...props.users]);

const filteredUsers = computed(() => {
    const query = search.value.trim().toLowerCase();

    return localUsers.value.filter((user) => {
        const searchable = [
            user.name,
            user.email,
            ...(user.roles ?? []).map((role) => role.name),
        ]
            .join(' ')
            .toLowerCase();

        const matchesSearch =
            query.length === 0 || searchable.includes(query);

        const matchesStatus =
            statusFilter.value === 'all' ||
            user.status === statusFilter.value;

        return matchesSearch && matchesStatus;
    });
});

const totalUsers = computed(() => localUsers.value.length);
const activeUsers = computed(
    () => localUsers.value.filter((user) => user.status === 'active').length,
);
const inactiveUsers = computed(
    () => localUsers.value.filter((user) => user.status === 'inactive').length,
);

function roleNames(user: User): string {
    return (user.roles ?? [])
        .map((role) => role.name)
        .join(', ');
}

function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function formatDate(value?: string): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    }).format(date);
}

async function refreshUsers(): Promise<void> {
    isLoading.value = true;
    operationMessage.value = '';

    try {
        const response = await fetch('/api/users', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const payload = (await response.json()) as {
            data?: User[];
            message?: string;
        };

        if (!response.ok) {
            throw new Error(
                payload.message ?? 'Unable to load users.',
            );
        }

        localUsers.value = payload.data ?? [];
    } catch (error) {
        operationMessageType.value = 'error';
        operationMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to load users.';
    } finally {
        isLoading.value = false;
    }
}

function openCreate(): void {
    if (!props.capabilities.canCreate) {
        return;
    }

    router.visit('/users/create');
}

function openUser(user: User): void {
    router.visit(`/users/${user.id}`);
}

function editUser(user: User): void {
    if (!props.capabilities.canUpdate) {
        return;
    }

    router.visit(`/users/${user.id}/edit`);
}

function clearFilters(): void {
    search.value = '';
    statusFilter.value = 'all';
}

onMounted(() => {
    /*
     * The page may already be hydrated with users from the Inertia response.
     * Refresh only when no initial collection was supplied.
     */
    if (props.users.length === 0) {
        void refreshUsers();
    }
});
</script>

<template>
    <Head title="Users" />

    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <div class="users-page">
            <header class="page-header">
                <div>
                    <div class="eyebrow">
                        <Icon name="users" :size="14" />
                        Identity & Access
                    </div>

                    <h1>User Management</h1>

                    <p>
                        Manage platform users, account status and assigned
                        roles from one workspace.
                    </p>
                </div>

                <div class="header-actions">
                    <button
                        class="btn btn-secondary"
                        type="button"
                        :disabled="isLoading"
                        @click="refreshUsers"
                    >
                        <Icon name="refresh" :size="15" />
                        Refresh
                    </button>

                    <button
                        v-if="props.capabilities.canCreate"
                        class="btn btn-primary"
                        type="button"
                        @click="openCreate"
                    >
                        <Icon name="plus" :size="15" />
                        Add user
                    </button>
                </div>
            </header>

            <div
                v-if="operationMessage"
                class="operation-message"
                :class="operationMessageType"
                role="status"
            >
                {{ operationMessage }}
            </div>

            <section class="stat-grid">
                <article class="stat-card">
                    <div class="stat-icon">
                        <Icon name="users" :size="17" />
                    </div>
                    <div>
                        <span>Total users</span>
                        <strong>{{ totalUsers }}</strong>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon active">
                        <Icon name="check" :size="17" />
                    </div>
                    <div>
                        <span>Active</span>
                        <strong>{{ activeUsers }}</strong>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon inactive">
                        <Icon name="lock" :size="17" />
                    </div>
                    <div>
                        <span>Inactive</span>
                        <strong>{{ inactiveUsers }}</strong>
                    </div>
                </article>
            </section>

            <section class="workspace-card">
                <div class="toolbar">
                    <label class="search-field">
                        <Icon name="search" :size="16" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search by name, email or role..."
                        />
                    </label>

                    <div class="toolbar-actions">
                        <select v-model="statusFilter" aria-label="Filter users by status">
                            <option value="all">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>

                        <button
                            v-if="search || statusFilter !== 'all'"
                            class="btn btn-ghost"
                            type="button"
                            @click="clearFilters"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Status</th>
                                <th>Roles</th>
                                <th>Created</th>
                                <th class="actions-column">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="user in filteredUsers" :key="user.id">
                                <td>
                                    <button
                                        class="user-cell"
                                        type="button"
                                        @click="openUser(user)"
                                    >
                                        <span class="avatar">
                                            {{ initials(user.name) }}
                                        </span>

                                        <span class="user-copy">
                                            <strong>{{ user.name }}</strong>
                                            <small>{{ user.email }}</small>
                                        </span>
                                    </button>
                                </td>

                                <td>
                                    <span
                                        class="status-badge"
                                        :class="user.status"
                                    >
                                        <i></i>
                                        {{ user.status }}
                                    </span>
                                </td>

                                <td>
                                    <div class="roles-cell">
                                        <span
                                            v-for="role in (user.roles ?? []).slice(0, 2)"
                                            :key="role.id"
                                            class="role-chip"
                                            :class="{ system: role.isSystem }"
                                        >
                                            {{ role.name }}
                                        </span>

                                        <span
                                            v-if="(user.roles ?? []).length > 2"
                                            class="role-more"
                                        >
                                            +{{ (user.roles ?? []).length - 2 }}
                                        </span>

                                        <span
                                            v-if="(user.roles ?? []).length === 0"
                                            class="muted"
                                        >
                                            No role
                                        </span>
                                    </div>
                                </td>

                                <td class="date-cell">
                                    {{ formatDate(user.created_at) }}
                                </td>

                                <td class="actions-column">
                                    <div class="row-actions">
                                        <button
                                            class="icon-btn"
                                            type="button"
                                            title="View user"
                                            @click="openUser(user)"
                                        >
                                            <Icon name="eye" :size="15" />
                                        </button>

                                        <button
                                            v-if="props.capabilities.canUpdate"
                                            class="icon-btn"
                                            type="button"
                                            title="Edit user"
                                            @click="editUser(user)"
                                        >
                                            <Icon name="edit" :size="15" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="filteredUsers.length === 0">
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <Icon name="users" :size="20" />
                                        </div>

                                        <strong>No users found</strong>

                                        <p>
                                            Try another search or clear the
                                            current filters.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <footer class="table-footer">
                    <span>
                        Showing
                        <strong>{{ filteredUsers.length }}</strong>
                        of
                        <strong>{{ totalUsers }}</strong>
                        users
                    </span>

                    <span v-if="props.capabilities.isDeveloper" class="developer-note">
                        <Icon name="developer" :size="13" />
                        Developer access
                    </span>
                </footer>
            </section>
        </div>
    </ErpLayout>
</template>

<style scoped>
.users-page {
    min-width: 0;
    padding: 28px 30px 40px;
    color: var(--text-primary, #17202a);
}

.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 22px;
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

.header-actions,
.toolbar-actions,
.row-actions {
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
    transition:
        transform 140ms ease,
        border-color 140ms ease,
        background 140ms ease,
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

.btn-secondary,
.btn-ghost {
    color: var(--text-primary, #17202a);
    border-color: var(--border, #e5e9ee);
    background: var(--surface, #fff);
}

.btn-ghost {
    background: transparent;
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

.stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 82px;
    padding: 16px;
    border: 1px solid var(--border, #e5e9ee);
    border-radius: 14px;
    background: var(--surface, #fff);
    box-shadow: 0 5px 18px rgb(15 23 42 / 3%);
}

.stat-icon {
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: 10px;
    color: var(--text-secondary, #596573);
    background: var(--surface-muted, #f3f5f7);
}

.stat-icon.active {
    color: #19734f;
    background: #eaf7f1;
}

.stat-icon.inactive {
    color: #9a6518;
    background: #fff5e4;
}

.stat-card span {
    display: block;
    color: var(--text-muted, #7b8794);
    font-size: 12px;
}

.stat-card strong {
    display: block;
    margin-top: 3px;
    font-size: 22px;
    line-height: 1.1;
}

.workspace-card {
    overflow: hidden;
    border: 1px solid var(--border, #e5e9ee);
    border-radius: 15px;
    background: var(--surface, #fff);
    box-shadow: 0 8px 28px rgb(15 23 42 / 4%);
}

.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 15px;
    border-bottom: 1px solid var(--border, #e5e9ee);
}

.search-field {
    display: flex;
    align-items: center;
    gap: 9px;
    width: min(470px, 100%);
    height: 39px;
    padding: 0 12px;
    border: 1px solid var(--border, #e5e9ee);
    border-radius: 10px;
    color: var(--text-muted, #7b8794);
    background: var(--surface-muted, #f8fafb);
}

.search-field input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    color: inherit;
    background: transparent;
    font: inherit;
    font-size: 13px;
}

.toolbar select {
    height: 39px;
    padding: 0 34px 0 11px;
    border: 1px solid var(--border, #e5e9ee);
    border-radius: 10px;
    outline: 0;
    color: var(--text-primary, #17202a);
    background: var(--surface, #fff);
    font: inherit;
    font-size: 13px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    padding: 12px 16px;
    color: var(--text-muted, #7b8794);
    background: var(--surface-muted, #fafbfc);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-align: left;
    text-transform: uppercase;
}

td {
    padding: 13px 16px;
    border-top: 1px solid var(--border, #eef1f4);
    vertical-align: middle;
    font-size: 13px;
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 0;
    border: 0;
    color: inherit;
    background: transparent;
    text-align: left;
    cursor: pointer;
}

.avatar {
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    place-items: center;
    border-radius: 11px;
    color: var(--primary, #1f8f68);
    background: var(--primary-soft, #eaf7f1);
    font-size: 12px;
    font-weight: 800;
}

.user-copy {
    display: grid;
    gap: 3px;
    min-width: 0;
}

.user-copy strong {
    overflow: hidden;
    max-width: 280px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-copy small,
.muted {
    color: var(--text-muted, #7b8794);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    text-transform: capitalize;
}

.status-badge i {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.status-badge.active {
    color: #17714e;
    background: #eaf7f1;
}

.status-badge.inactive {
    color: #9a6518;
    background: #fff5e4;
}

.roles-cell {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    min-width: 190px;
}

.role-chip,
.role-more {
    display: inline-flex;
    align-items: center;
    min-height: 25px;
    padding: 0 8px;
    border: 1px solid var(--border, #e5e9ee);
    border-radius: 7px;
    color: var(--text-secondary, #596573);
    background: var(--surface-muted, #f8fafb);
    font-size: 11px;
    font-weight: 700;
}

.role-chip.system {
    color: #6c4b9e;
    border-color: #dfd3f0;
    background: #f7f2fc;
}

.role-more {
    color: var(--text-muted, #7b8794);
}

.date-cell {
    color: var(--text-secondary, #596573);
    white-space: nowrap;
}

.actions-column {
    width: 110px;
    text-align: right;
}

.icon-btn {
    display: grid;
    width: 32px;
    height: 32px;
    place-items: center;
    border: 1px solid var(--border, #e5e9ee);
    border-radius: 8px;
    color: var(--text-secondary, #596573);
    background: var(--surface, #fff);
    cursor: pointer;
}

.icon-btn:hover {
    color: var(--primary, #1f8f68);
    border-color: var(--primary, #1f8f68);
}

.empty-state {
    display: grid;
    min-height: 250px;
    place-items: center;
    align-content: center;
    padding: 40px 20px;
    text-align: center;
}

.empty-icon {
    display: grid;
    width: 44px;
    height: 44px;
    margin-bottom: 10px;
    place-items: center;
    border-radius: 12px;
    color: var(--text-muted, #7b8794);
    background: var(--surface-muted, #f3f5f7);
}

.empty-state strong {
    font-size: 14px;
}

.empty-state p {
    margin: 6px 0 0;
    color: var(--text-muted, #7b8794);
    font-size: 12px;
}

.table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 12px 16px;
    border-top: 1px solid var(--border, #e5e9ee);
    color: var(--text-muted, #7b8794);
    font-size: 12px;
}

.developer-note {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #6c4b9e;
    font-weight: 700;
}

@media (max-width: 900px) {
    .users-page {
        padding: 20px 16px 30px;
    }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .stat-grid {
        grid-template-columns: 1fr;
    }

    .toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .search-field {
        width: 100%;
    }

    .toolbar-actions {
        justify-content: space-between;
    }
}

@media (max-width: 560px) {
    .users-page {
        padding: 16px 12px 24px;
    }

    .header-actions,
    .header-actions .btn {
        width: 100%;
    }

    .header-actions {
        flex-direction: column;
    }

    .toolbar-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .toolbar select {
        width: 100%;
    }

    .table-footer {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
