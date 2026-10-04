<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';

import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Permission {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    enabled: boolean;
}

interface PermissionModule {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    isActive: boolean;
    isSystem: boolean;
    permissions: Permission[];
}

interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    isSystem: boolean;
    isActive: boolean;
    userCount: number;
    permissionCount: number;
}

interface Capabilities {
    canCreateRole: boolean;
    canUpdateRole: boolean;
    canDeleteRole?: boolean;
    canManagePermissions: boolean;
    canViewPermissions?: boolean;
    canManageModules?: boolean;
    isDeveloper?: boolean;
}

interface Props {
    roles: Role[];
    modules: PermissionModule[];
    selectedRoleId: number | null;
    capabilities: Capabilities;
}

type ActiveTab = 'permissions' | 'users' | 'audit';
type RoleStatusFilter = 'all' | 'active' | 'inactive';
type RoleModalMode = 'create' | 'edit' | 'show';

interface RoleForm {
    name: string;
    slug: string;
    description: string;
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

const page = usePage();
const props = defineProps<Props>();

/* -------------------------------------------------------------------------- */
/* API helper                                                                 */
/* -------------------------------------------------------------------------- */

async function apiRequest<T>(
    url: string,
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
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

    const responseText = await response.text();

    let payload: unknown = null;

    if (responseText.trim() !== '') {
        try {
            payload = JSON.parse(responseText) as unknown;
        } catch {
            payload = null;
        }
    }

    if (!response.ok) {
        const errorPayload = (
            payload && typeof payload === 'object' ? payload : {}
        ) as ApiErrorPayload;

        const validationErrors: Record<string, string> = {};

        if (errorPayload.errors) {
            for (const [field, messages] of Object.entries(
                errorPayload.errors,
            )) {
                validationErrors[field] = Array.isArray(messages)
                    ? messages.join(' ')
                    : messages;
            }
        }

        throw new ApiRequestError(
            errorPayload.message ||
                `Request failed with status ${response.status}.`,
            response.status,
            validationErrors,
        );
    }

    /*
     * Laravel controllers return:
     *
     * {
     *     data: ...
     * }
     *
     * Automatically unwrap the data property so callers can use:
     *
     * const role = await apiRequest<Role>(...)
     *
     * instead of:
     *
     * const response = await ...
     * response.data
     */
    if (payload && typeof payload === 'object' && 'data' in payload) {
        return (payload as ApiSuccessPayload<T>).data;
    }

    return payload as T;
}

function errorMessage(error: unknown, fallback: string): string {
    if (error instanceof ApiRequestError && error.message) {
        return error.message;
    }

    if (error instanceof Error && error.message) {
        return error.message;
    }

    return fallback;
}

/* -------------------------------------------------------------------------- */
/* Auth user                                                                  */
/* -------------------------------------------------------------------------- */

const authUser = computed<User>(() => {
    const user = page.props.auth?.user as User | undefined;

    return (
        user ?? {
            id: 0,
            name: 'Yahya Admin',
            email: 'admin@madinascale.local',
        }
    );
});

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const search = ref('');
const moduleSearch = ref('');
const statusFilter = ref<RoleStatusFilter>('all');

const activeTab = ref<ActiveTab>('permissions');

const showRoleModal = ref(false);
const roleModalMode = ref<RoleModalMode>('create');

const expandedModules = ref<number[]>([]);

const isSelectingRole = ref(false);
const isSavingRole = ref(false);
const isSavingPermissions = ref(false);

const roleFormErrors = ref<Record<string, string>>({});

const operationMessage = ref('');
const operationMessageType = ref<'success' | 'error' | 'info'>('info');

const permissionDirty = ref(false);

const localPermissionState = ref<Record<number, boolean>>({});

const roleForm = reactive<RoleForm>({
    name: '',
    slug: '',
    description: '',
    isActive: true,
});

/* -------------------------------------------------------------------------- */
/* Icon mapping                                                               */
/* -------------------------------------------------------------------------- */

const moduleIconMap: Record<string, string> = {
    dashboard: 'dashboard',
    system: 'settings',
    roles: 'users',
    permissions: 'developer',
    modules: 'settings',
    users: 'users',
    analytics: 'analytics',
    reports: 'reports',
    marketing: 'analytics',
    settings: 'settings',
};

/* -------------------------------------------------------------------------- */
/* Base computed state                                                        */
/* -------------------------------------------------------------------------- */

const roles = computed<Role[]>(() => props.roles ?? []);

const modules = computed<PermissionModule[]>(() => props.modules ?? []);

const selectedRoleId = computed<number | null>(() => {
    if (props.selectedRoleId !== null && props.selectedRoleId !== undefined) {
        return props.selectedRoleId;
    }

    return props.roles[0]?.id ?? null;
});

const selectedRole = computed<Role | null>(() => {
    if (selectedRoleId.value === null) {
        return null;
    }

    return props.roles.find((role) => role.id === selectedRoleId.value) ?? null;
});

const hasSelectedRole = computed<boolean>(() => selectedRole.value !== null);

const selectedRoleIsSystem = computed<boolean>(
    () => selectedRole.value?.isSystem === true,
);

const selectedRoleIsActive = computed<boolean>(
    () => selectedRole.value?.isActive === true,
);

const selectedRoleUserCount = computed<number>(
    () => selectedRole.value?.userCount ?? 0,
);

const selectedRolePermissionCount = computed<number>(
    () => selectedRole.value?.permissionCount ?? 0,
);

const canEditSelectedRole = computed<boolean>(
    () =>
        hasSelectedRole.value &&
        !selectedRoleIsSystem.value &&
        props.capabilities.canUpdateRole,
);

const canManageSelectedPermissions = computed<boolean>(
    () =>
        hasSelectedRole.value &&
        !selectedRoleIsSystem.value &&
        props.capabilities.canManagePermissions,
);

/* -------------------------------------------------------------------------- */
/* Role filtering                                                             */
/* -------------------------------------------------------------------------- */

const filteredRoles = computed<Role[]>(() => {
    const query = search.value.trim().toLowerCase();

    return roles.value.filter((role) => {
        const searchableText = [role.name, role.slug, role.description ?? '']
            .join(' ')
            .toLowerCase();

        const matchesSearch =
            query.length === 0 || searchableText.includes(query);

        const matchesStatus =
            statusFilter.value === 'all' ||
            (statusFilter.value === 'active' && role.isActive) ||
            (statusFilter.value === 'inactive' && !role.isActive);

        return matchesSearch && matchesStatus;
    });
});

/* -------------------------------------------------------------------------- */
/* Permission state                                                           */
/* -------------------------------------------------------------------------- */

function isPermissionEnabled(permissionId: number): boolean {
    return localPermissionState.value[permissionId] === true;
}

function initializePermissionState(): void {
    const state: Record<number, boolean> = {};

    for (const module of modules.value) {
        for (const permission of module.permissions) {
            state[permission.id] = permission.enabled;
        }
    }

    localPermissionState.value = state;
    permissionDirty.value = false;
}

watch(
    modules,
    () => {
        initializePermissionState();
    },
    {
        immediate: true,
        deep: true,
    },
);

const filteredModules = computed<PermissionModule[]>(() => {
    const query = moduleSearch.value.trim().toLowerCase();

    if (!query) {
        return modules.value;
    }

    return modules.value
        .map((module) => {
            const moduleMatches = [module.name, module.slug, module.icon ?? '']
                .join(' ')
                .toLowerCase()
                .includes(query);

            const permissions = moduleMatches
                ? module.permissions
                : module.permissions.filter((permission) => {
                      const searchableText = [
                          permission.name,
                          permission.slug,
                          permission.description ?? '',
                      ]
                          .join(' ')
                          .toLowerCase();

                      return searchableText.includes(query);
                  });

            return {
                ...module,
                permissions,
            };
        })
        .filter(
            (module) =>
                module.permissions.length > 0 ||
                module.name.toLowerCase().includes(query),
        );
});

/* -------------------------------------------------------------------------- */
/* Dynamic KPI calculations                                                   */
/* -------------------------------------------------------------------------- */

const totalRoles = computed<number>(() => roles.value.length);

const activeRoles = computed<number>(
    () => roles.value.filter((role) => role.isActive).length,
);

const totalPermissions = computed<number>(() =>
    modules.value.reduce(
        (total, module) => total + module.permissions.length,
        0,
    ),
);

const enabledPermissions = computed<number>(() =>
    modules.value.reduce(
        (total, module) =>
            total +
            module.permissions.filter((permission) =>
                isPermissionEnabled(permission.id),
            ).length,
        0,
    ),
);

const permissionCoverage = computed<number>(() => {
    if (!totalPermissions.value || !hasSelectedRole.value) {
        return 0;
    }

    return Math.min(
        100,
        Math.round((enabledPermissions.value / totalPermissions.value) * 100),
    );
});

const enabledModuleCount = computed<number>(
    () =>
        modules.value.filter((module) =>
            module.permissions.some((permission) =>
                isPermissionEnabled(permission.id),
            ),
        ).length,
);

const kpiCards = computed(() => [
    {
        label: 'Total Roles',
        value: totalRoles.value,
        helper: 'Across the platform',
        icon: 'users',
        tone: 'green',
    },
    {
        label: 'Active Roles',
        value: activeRoles.value,
        helper: 'Currently available',
        icon: 'users',
        tone: 'blue',
    },
    {
        label: 'Permissions',
        value: totalPermissions.value,
        helper: `Across ${modules.value.length} modules`,
        icon: 'developer',
        tone: 'purple',
    },
    {
        label: 'Coverage',
        value: `${permissionCoverage.value}%`,
        helper: `${enabledModuleCount.value} active modules in scope`,
        icon: 'analytics',
        tone: 'orange',
    },
]);

/* -------------------------------------------------------------------------- */
/* Module helpers                                                             */
/* -------------------------------------------------------------------------- */

function isModuleExpanded(moduleId: number): boolean {
    return expandedModules.value.includes(moduleId);
}

function toggleModule(moduleId: number): void {
    expandedModules.value = isModuleExpanded(moduleId)
        ? expandedModules.value.filter((id) => id !== moduleId)
        : [...expandedModules.value, moduleId];
}

function expandAllModules(): void {
    expandedModules.value = filteredModules.value.map((module) => module.id);
}

function collapseAllModules(): void {
    expandedModules.value = [];
}

function moduleEnabledCount(module: PermissionModule): number {
    return module.permissions.filter((permission) =>
        isPermissionEnabled(permission.id),
    ).length;
}

function moduleIcon(module: PermissionModule): string {
    return module.icon || moduleIconMap[module.slug] || 'settings';
}

/* -------------------------------------------------------------------------- */
/* Role selection                                                             */
/* -------------------------------------------------------------------------- */

function selectRole(roleId: number): void {
    if (roleId === selectedRoleId.value) {
        return;
    }

    /*
     * Never silently discard permission changes.
     */
    if (permissionDirty.value) {
        const shouldDiscard = window.confirm(
            'You have unsaved permission changes. Discard them and switch roles?',
        );

        if (!shouldDiscard) {
            return;
        }
    }

    activeTab.value = 'permissions';
    moduleSearch.value = '';
    expandedModules.value = [];
    permissionDirty.value = false;
    operationMessage.value = '';
    isSelectingRole.value = true;

    router.get(
        '/settings/roles-permissions',
        {
            role: roleId,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['roles', 'modules', 'selectedRoleId', 'capabilities'],
            onFinish: () => {
                isSelectingRole.value = false;
            },
        },
    );
}

/* -------------------------------------------------------------------------- */
/* Inertia page refresh                                                       */
/* -------------------------------------------------------------------------- */

function reloadAccessPage(): void {
    router.get(
        '/settings/roles-permissions',
        selectedRoleId.value !== null
            ? {
                  role: selectedRoleId.value,
              }
            : {},
        {
            only: ['roles', 'modules', 'selectedRoleId', 'capabilities'],
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

/* -------------------------------------------------------------------------- */
/* Permission mutation                                                        */
/* -------------------------------------------------------------------------- */

function togglePermission(permission: Permission): void {
    if (!canManageSelectedPermissions.value) {
        return;
    }

    localPermissionState.value = {
        ...localPermissionState.value,
        [permission.id]: !isPermissionEnabled(permission.id),
    };

    permissionDirty.value = true;
    operationMessage.value = '';
}

const selectedPermissionIds = computed<number[]>(() =>
    Object.entries(localPermissionState.value)
        .filter(([, enabled]) => enabled)
        .map(([permissionId]) => Number(permissionId)),
);

function resetPermissions(): void {
    initializePermissionState();

    operationMessage.value = '';
    operationMessageType.value = 'info';
}

async function savePermissions(): Promise<void> {
    if (
        !selectedRole.value ||
        !canManageSelectedPermissions.value ||
        isSavingPermissions.value
    ) {
        return;
    }

    isSavingPermissions.value = true;
    operationMessage.value = '';

    try {
        await apiRequest<Permission[]>(
            `/api/roles/${selectedRole.value.id}/permissions`,
            'PUT',
            {
                permission_ids: selectedPermissionIds.value,
            },
        );

        permissionDirty.value = false;

        operationMessageType.value = 'success';
        operationMessage.value = 'Permissions updated successfully.';

        reloadAccessPage();
    } catch (error) {
        operationMessageType.value = 'error';

        operationMessage.value = errorMessage(
            error,
            'Unable to update permissions. Please try again.',
        );
    } finally {
        isSavingPermissions.value = false;
    }
}

/* -------------------------------------------------------------------------- */
/* Role form                                                                  */
/* -------------------------------------------------------------------------- */

function resetRoleForm(): void {
    roleForm.name = '';
    roleForm.slug = '';
    roleForm.description = '';
    roleForm.isActive = true;

    roleFormErrors.value = {};
}

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function handleRoleNameInput(): void {
    if (!roleForm.slug) {
        roleForm.slug = slugify(roleForm.name);
    }
}

function openCreateRole(): void {
    if (!props.capabilities.canCreateRole) {
        return;
    }

    resetRoleForm();

    roleModalMode.value = 'create';
    showRoleModal.value = true;
}

function openEditRole(): void {
    if (!selectedRole.value || !canEditSelectedRole.value) {
        return;
    }

    roleForm.name = selectedRole.value.name;
    roleForm.slug = selectedRole.value.slug;
    roleForm.description = selectedRole.value.description ?? '';
    roleForm.isActive = selectedRole.value.isActive;

    roleFormErrors.value = {};
    roleModalMode.value = 'edit';
    showRoleModal.value = true;
}

function openRoleDetails(): void {
    if (!selectedRole.value) {
        return;
    }

    roleModalMode.value = 'show';
    showRoleModal.value = true;
}

function closeRoleModal(): void {
    if (isSavingRole.value) {
        return;
    }

    showRoleModal.value = false;
    roleFormErrors.value = {};
}

async function submitRole(): Promise<void> {
    if (roleModalMode.value === 'show') {
        return;
    }

    if (roleModalMode.value === 'create' && !props.capabilities.canCreateRole) {
        return;
    }

    if (roleModalMode.value === 'edit' && !canEditSelectedRole.value) {
        return;
    }

    const name = roleForm.name.trim();
    const slug = roleForm.slug.trim() || slugify(name);
    const description = roleForm.description.trim();

    roleFormErrors.value = {};

    if (!name) {
        roleFormErrors.value.name = 'Role name is required.';
        return;
    }

    if (!slug) {
        roleFormErrors.value.slug = 'Role slug is required.';
        return;
    }

    /*
     * Keep frontend validation aligned with:
     *
     * /^[a-z0-9]+(?:[._-][a-z0-9]+)*$/
     *
     * used by Laravel StoreRoleRequest and
     * UpdateRoleRequest.
     */
    if (!/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/.test(slug)) {
        roleFormErrors.value.slug =
            'Use lowercase letters, numbers, dots, underscores, or hyphens.';
        return;
    }

    isSavingRole.value = true;
    operationMessage.value = '';

    const payload = {
        name,
        slug,
        description: description || null,
        is_active: roleForm.isActive,
    };

    try {
        if (roleModalMode.value === 'create') {
            /*
             * apiRequest automatically unwraps:
             *
             * { data: role }
             *
             * into:
             *
             * role
             */
            const created = await apiRequest<Role>(
                '/api/roles',
                'POST',
                payload,
            );

            showRoleModal.value = false;

            operationMessageType.value = 'success';

            operationMessage.value = 'Role created successfully.';

            router.get(
                '/settings/roles-permissions',
                {
                    role: created.id,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                    only: [
                        'roles',
                        'modules',
                        'selectedRoleId',
                        'capabilities',
                    ],
                },
            );

            return;
        }

        if (!selectedRole.value) {
            return;
        }

        await apiRequest<Role>(
            `/api/roles/${selectedRole.value.id}`,
            'PUT',
            payload,
        );

        showRoleModal.value = false;

        operationMessageType.value = 'success';

        operationMessage.value = 'Role updated successfully.';

        reloadAccessPage();
    } catch (error) {
        const typedError = error as ApiRequestError;

        roleFormErrors.value = typedError.validationErrors ?? {};

        operationMessageType.value = 'error';

        operationMessage.value =
            Object.keys(typedError.validationErrors ?? {}).length > 0
                ? 'Please correct the highlighted fields.'
                : errorMessage(
                      error,
                      'Unable to save the role. Please try again.',
                  );
    } finally {
        isSavingRole.value = false;
    }
}

/* -------------------------------------------------------------------------- */
/* Export                                                                     */
/* -------------------------------------------------------------------------- */

function exportRoles(): void {
    const rows = [
        ['ID', 'Name', 'Slug', 'Status', 'System Role', 'Users', 'Permissions'],
        ...roles.value.map((role) => [
            String(role.id),
            role.name,
            role.slug,
            role.isActive ? 'Active' : 'Inactive',
            role.isSystem ? 'Yes' : 'No',
            String(role.userCount),
            String(role.permissionCount),
        ]),
    ];

    const csv = rows
        .map((row) =>
            row
                .map((value) => `"${String(value).replace(/"/g, '""')}"`)
                .join(','),
        )
        .join('\r\n');

    const blob = new Blob([csv], {
        type: 'text/csv;charset=utf-8;',
    });

    const url = URL.createObjectURL(blob);

    const link = document.createElement('a');

    link.href = url;

    link.download = `madina-scale-roles-${new Date().toISOString().slice(0, 10)}.csv`;

    document.body.appendChild(link);

    link.click();

    link.remove();

    URL.revokeObjectURL(url);
}

/* -------------------------------------------------------------------------- */
/* Modal computed helpers                                                     */
/* -------------------------------------------------------------------------- */

const roleModalTitle = computed<string>(() => {
    if (roleModalMode.value === 'create') {
        return 'Create New Role';
    }

    if (roleModalMode.value === 'edit') {
        return 'Edit Role';
    }

    return 'Role Details';
});

const roleModalDescription = computed<string>(() => {
    if (roleModalMode.value === 'create') {
        return 'Create a new access role for your organization.';
    }

    if (roleModalMode.value === 'edit') {
        return 'Update role identity, description, and availability.';
    }

    return 'Review the selected role and its current access scope.';
});

const roleModalIsReadonly = computed<boolean>(
    () => roleModalMode.value === 'show',
);

const roleSubmitLabel = computed<string>(() => {
    if (isSavingRole.value) {
        return roleModalMode.value === 'create' ? 'Creating...' : 'Saving...';
    }

    return roleModalMode.value === 'create' ? 'Create Role' : 'Save Changes';
});
</script>

<template>
    <Head title="Roles & Permissions" />

    <ErpLayout :user-name="authUser.name" :user-email="authUser.email">
        <div class="access-page page-enter">
            <!-- ---------------------------------------------------------------- -->
            <!-- Header                                                            -->
            <!-- ---------------------------------------------------------------- -->

            <header class="access-header">
                <div class="access-header-copy">
                    <div class="access-breadcrumb">
                        <span>System</span>
                        <span class="access-breadcrumb-separator">/</span>
                        <span class="is-current">Access Control</span>
                    </div>

                    <div class="access-title-row">
                        <div class="access-title-icon" aria-hidden="true">
                            <Icon name="developer" :size="21" />
                        </div>

                        <div>
                            <h1>Roles &amp; Permissions</h1>

                            <p>
                                Control who can access each part of Madina Scale
                                and what they can do.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="access-header-actions">
                    <button
                        class="access-btn access-btn-secondary"
                        type="button"
                        @click="exportRoles"
                    >
                        <Icon name="reports" :size="15" />

                        Export
                    </button>

                    <button
                        v-if="props.capabilities.canCreateRole"
                        class="access-btn access-btn-primary"
                        type="button"
                        @click="openCreateRole"
                    >
                        <Icon name="plus" :size="15" />

                        Create Role
                    </button>
                </div>
            </header>

            <!-- ---------------------------------------------------------------- -->
            <!-- Operation feedback                                                -->
            <!-- ---------------------------------------------------------------- -->

            <Transition name="message">
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
                                : operationMessageType === 'error'
                                  ? 'close'
                                  : 'settings'
                        "
                        :size="14"
                    />

                    <span>{{ operationMessage }}</span>

                    <button
                        type="button"
                        aria-label="Dismiss message"
                        @click="operationMessage = ''"
                    >
                        ×
                    </button>
                </div>
            </Transition>

            <!-- ---------------------------------------------------------------- -->
            <!-- KPI cards                                                         -->
            <!-- ---------------------------------------------------------------- -->

            <section
                class="access-stat-grid"
                aria-label="Access control overview"
            >
                <article
                    v-for="(card, index) in kpiCards"
                    :key="card.label"
                    class="access-stat-card"
                    :style="{ '--card-index': index }"
                >
                    <div
                        :class="['access-stat-icon', card.tone]"
                        aria-hidden="true"
                    >
                        <Icon :name="card.icon" :size="17" />
                    </div>

                    <div class="access-stat-content">
                        <span>{{ card.label }}</span>
                        <strong>{{ card.value }}</strong>
                        <small>{{ card.helper }}</small>
                    </div>
                </article>
            </section>

            <!-- ---------------------------------------------------------------- -->
            <!-- Main workspace                                                    -->
            <!-- ---------------------------------------------------------------- -->

            <section
                class="access-workspace"
                :class="{ loading: isSelectingRole }"
            >
                <div
                    v-if="isSelectingRole"
                    class="workspace-loading"
                    aria-live="polite"
                >
                    <span class="loading-spinner" aria-hidden="true"></span>

                    <span>Loading access scope...</span>
                </div>

                <!-- ------------------------------------------------------------ -->
                <!-- Role sidebar                                                   -->
                <!-- ------------------------------------------------------------ -->

                <aside class="role-panel">
                    <div class="role-panel-header">
                        <div>
                            <h2>Roles</h2>

                            <span>
                                {{ filteredRoles.length }}
                                {{
                                    filteredRoles.length === 1
                                        ? 'role'
                                        : 'roles'
                                }}
                            </span>
                        </div>

                        <button
                            class="icon-only-btn"
                            type="button"
                            aria-label="Expand all modules"
                            title="Expand all modules"
                            @click="expandAllModules"
                        >
                            <Icon name="plus" :size="14" aria-hidden="true" />
                        </button>
                    </div>

                    <div class="role-search">
                        <Icon name="search" :size="14" aria-hidden="true" />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search roles..."
                            aria-label="Search roles"
                        />
                    </div>

                    <div class="role-filter-row">
                        <button
                            :class="[
                                'role-filter',
                                {
                                    active: statusFilter === 'all',
                                },
                            ]"
                            type="button"
                            @click="statusFilter = 'all'"
                        >
                            All
                        </button>

                        <button
                            :class="[
                                'role-filter',
                                {
                                    active: statusFilter === 'active',
                                },
                            ]"
                            type="button"
                            @click="statusFilter = 'active'"
                        >
                            Active
                        </button>

                        <button
                            :class="[
                                'role-filter',
                                {
                                    active: statusFilter === 'inactive',
                                },
                            ]"
                            type="button"
                            @click="statusFilter = 'inactive'"
                        >
                            Inactive
                        </button>
                    </div>

                    <div class="role-list">
                        <button
                            v-for="role in filteredRoles"
                            :key="role.id"
                            :class="[
                                'role-item',
                                {
                                    selected: role.id === selectedRoleId,
                                },
                            ]"
                            type="button"
                            @click="selectRole(role.id)"
                        >
                            <span
                                class="role-avatar"
                                :class="{ system: role.isSystem }"
                                aria-hidden="true"
                            >
                                <Icon
                                    :name="
                                        role.isSystem ? 'developer' : 'users'
                                    "
                                    :size="14"
                                />
                            </span>

                            <span class="role-item-copy">
                                <strong>{{ role.name }}</strong>

                                <small>
                                    {{ role.userCount }}
                                    {{
                                        role.userCount === 1 ? 'user' : 'users'
                                    }}
                                    ·
                                    {{ role.permissionCount }}
                                    {{
                                        role.permissionCount === 1
                                            ? 'permission'
                                            : 'permissions'
                                    }}
                                </small>
                            </span>

                            <span
                                v-if="role.isSystem"
                                class="system-dot"
                                title="System role"
                                aria-label="System role"
                            ></span>
                        </button>

                        <div
                            v-if="filteredRoles.length === 0"
                            class="role-empty"
                        >
                            <span class="empty-search-icon" aria-hidden="true">
                                <Icon name="search" :size="20" />
                            </span>

                            <strong>No roles found</strong>

                            <span> Try another search term. </span>
                        </div>
                    </div>
                </aside>

                <!-- ------------------------------------------------------------ -->
                <!-- Permission panel                                               -->
                <!-- ------------------------------------------------------------ -->

                <main class="permission-panel">
                    <template v-if="hasSelectedRole">
                        <!-- Role heading -->
                        <div class="permission-header">
                            <div class="selected-role-heading">
                                <div
                                    class="selected-role-avatar"
                                    :class="{
                                        system: selectedRoleIsSystem,
                                    }"
                                    aria-hidden="true"
                                >
                                    <Icon
                                        :name="
                                            selectedRoleIsSystem
                                                ? 'developer'
                                                : 'users'
                                        "
                                        :size="17"
                                    />
                                </div>

                                <div class="selected-role-details">
                                    <div class="selected-role-name-row">
                                        <h2>
                                            {{ selectedRole?.name }}
                                        </h2>

                                        <span
                                            v-if="selectedRoleIsSystem"
                                            class="role-badge system"
                                        >
                                            System Role
                                        </span>

                                        <span
                                            v-if="selectedRoleIsActive"
                                            class="role-badge active"
                                        >
                                            Active
                                        </span>

                                        <span
                                            v-else
                                            class="role-badge inactive"
                                        >
                                            Inactive
                                        </span>
                                    </div>

                                    <p>
                                        {{
                                            selectedRole?.description ||
                                            'No description has been provided for this role.'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="selected-role-actions">
                                <button
                                    class="access-btn access-btn-secondary compact"
                                    type="button"
                                    @click="openRoleDetails"
                                >
                                    <Icon name="reports" :size="14" />

                                    Details
                                </button>

                                <button
                                    class="access-btn access-btn-secondary compact"
                                    type="button"
                                    @click="openEditRole"
                                    :disabled="!canEditSelectedRole"
                                    :title="
                                        selectedRoleIsSystem
                                            ? 'Protected system role'
                                            : !props.capabilities.canUpdateRole
                                              ? 'You do not have permission to update roles'
                                              : 'Edit role'
                                    "
                                >
                                    <Icon name="settings" :size="14" />

                                    Edit Role
                                </button>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <div class="permission-tabs">
                            <button
                                :class="[
                                    'permission-tab',
                                    {
                                        active: activeTab === 'permissions',
                                    },
                                ]"
                                type="button"
                                @click="activeTab = 'permissions'"
                            >
                                <Icon name="developer" :size="14" />

                                Permissions

                                <span>
                                    {{ enabledPermissions }}
                                </span>
                            </button>

                            <button
                                :class="[
                                    'permission-tab',
                                    {
                                        active: activeTab === 'users',
                                    },
                                ]"
                                type="button"
                                @click="activeTab = 'users'"
                            >
                                <Icon name="users" :size="14" />

                                Assigned Users

                                <span>
                                    {{ selectedRoleUserCount }}
                                </span>
                            </button>

                            <button
                                :class="[
                                    'permission-tab',
                                    {
                                        active: activeTab === 'audit',
                                    },
                                ]"
                                type="button"
                                @click="activeTab = 'audit'"
                            >
                                <Icon name="reports" :size="14" />

                                Access Audit
                            </button>
                        </div>

                        <!-- Permissions tab -->
                        <div
                            v-if="activeTab === 'permissions'"
                            class="permission-content"
                        >
                            <div class="permission-toolbar">
                                <div class="permission-search">
                                    <Icon
                                        name="search"
                                        :size="14"
                                        aria-hidden="true"
                                    />

                                    <input
                                        v-model="moduleSearch"
                                        type="search"
                                        placeholder="Search permissions..."
                                        aria-label="Search permissions"
                                    />
                                </div>

                                <div class="permission-toolbar-actions">
                                    <div class="permission-toolbar-meta">
                                        <span>
                                            <i></i>

                                            {{ enabledPermissions }}
                                            enabled
                                        </span>

                                        <span>
                                            {{ totalPermissions }}
                                            total
                                        </span>
                                    </div>

                                    <button
                                        class="toolbar-text-btn"
                                        type="button"
                                        @click="expandAllModules"
                                    >
                                        Expand
                                    </button>

                                    <button
                                        class="toolbar-text-btn"
                                        type="button"
                                        @click="collapseAllModules"
                                    >
                                        Collapse
                                    </button>
                                </div>
                            </div>

                            <!-- Permission save bar -->
                            <Transition name="save-bar">
                                <div
                                    v-if="permissionDirty"
                                    class="permission-save-bar"
                                >
                                    <div>
                                        <span class="save-bar-dot"></span>

                                        <div>
                                            <strong>
                                                Unsaved permission changes
                                            </strong>

                                            <small>
                                                Save the updated access scope to
                                                apply it.
                                            </small>
                                        </div>
                                    </div>

                                    <div>
                                        <button
                                            class="save-bar-reset"
                                            type="button"
                                            :disabled="isSavingPermissions"
                                            @click="resetPermissions"
                                        >
                                            Reset
                                        </button>

                                        <button
                                            class="access-btn access-btn-primary compact"
                                            type="button"
                                            :disabled="isSavingPermissions"
                                            @click="savePermissions"
                                        >
                                            <span
                                                v-if="isSavingPermissions"
                                                class="mini-spinner"
                                            ></span>

                                            <Icon
                                                v-else
                                                name="check"
                                                :size="13"
                                            />

                                            {{
                                                isSavingPermissions
                                                    ? 'Saving...'
                                                    : 'Save Permissions'
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </Transition>

                            <!-- System role notice -->
                            <div
                                v-if="selectedRoleIsSystem"
                                class="system-notice"
                            >
                                <span
                                    class="system-notice-icon"
                                    aria-hidden="true"
                                >
                                    <Icon name="developer" :size="14" />
                                </span>

                                <div>
                                    <strong>
                                        Developer is a protected system role
                                    </strong>

                                    <span>
                                        System roles are controlled by the
                                        platform and cannot be modified through
                                        normal role management.
                                    </span>
                                </div>
                            </div>

                            <!-- Permission groups -->
                            <div class="permission-groups">
                                <section
                                    v-for="module in filteredModules"
                                    :key="module.id"
                                    class="permission-group"
                                >
                                    <button
                                        class="permission-group-header"
                                        type="button"
                                        :aria-expanded="
                                            isModuleExpanded(module.id)
                                        "
                                        @click="toggleModule(module.id)"
                                    >
                                        <span class="module-heading">
                                            <span
                                                class="module-icon"
                                                aria-hidden="true"
                                            >
                                                <Icon
                                                    :name="moduleIcon(module)"
                                                    :size="14"
                                                />
                                            </span>

                                            <span>
                                                <strong>
                                                    {{ module.name }}
                                                </strong>

                                                <small>
                                                    {{
                                                        module.permissions
                                                            .length
                                                    }}
                                                    {{
                                                        module.permissions
                                                            .length === 1
                                                            ? 'permission'
                                                            : 'permissions'
                                                    }}
                                                </small>
                                            </span>
                                        </span>

                                        <span class="module-heading-actions">
                                            <span class="module-coverage">
                                                {{
                                                    moduleEnabledCount(module)
                                                }}/{{
                                                    module.permissions.length
                                                }}
                                            </span>

                                            <span
                                                class="module-chevron"
                                                :class="{
                                                    expanded: isModuleExpanded(
                                                        module.id,
                                                    ),
                                                }"
                                                aria-hidden="true"
                                            >
                                                <Icon
                                                    name="chevron-down"
                                                    :size="14"
                                                />
                                            </span>
                                        </span>
                                    </button>

                                    <Transition name="permission-expand">
                                        <div
                                            v-if="isModuleExpanded(module.id)"
                                            class="permission-list"
                                        >
                                            <div
                                                v-for="permission in module.permissions"
                                                :key="permission.id"
                                                class="permission-row"
                                            >
                                                <div class="permission-copy">
                                                    <strong>
                                                        {{ permission.name }}
                                                    </strong>

                                                    <span>
                                                        {{ permission.slug }}
                                                    </span>

                                                    <small>
                                                        {{
                                                            permission.description ||
                                                            'No description available.'
                                                        }}
                                                    </small>
                                                </div>

                                                <button
                                                    :class="[
                                                        'permission-toggle',
                                                        {
                                                            on: isPermissionEnabled(
                                                                permission.id,
                                                            ),
                                                        },
                                                    ]"
                                                    type="button"
                                                    :aria-pressed="
                                                        isPermissionEnabled(
                                                            permission.id,
                                                        )
                                                    "
                                                    :disabled="
                                                        !canManageSelectedPermissions
                                                    "
                                                    :title="
                                                        selectedRoleIsSystem
                                                            ? 'Protected system role'
                                                            : !props
                                                                    .capabilities
                                                                    .canManagePermissions
                                                              ? 'You do not have permission to modify access'
                                                              : 'Toggle permission'
                                                    "
                                                    @click="
                                                        togglePermission(
                                                            permission,
                                                        )
                                                    "
                                                >
                                                    <span></span>
                                                </button>
                                            </div>
                                        </div>
                                    </Transition>
                                </section>
                            </div>

                            <div
                                v-if="filteredModules.length === 0"
                                class="empty-state"
                            >
                                <div
                                    class="empty-state-icon"
                                    aria-hidden="true"
                                >
                                    <Icon name="search" :size="22" />
                                </div>

                                <h3>No permissions found</h3>

                                <p>
                                    Try a different permission or module search
                                    term.
                                </p>
                            </div>
                        </div>

                        <!-- Users tab -->
                        <div
                            v-else-if="activeTab === 'users'"
                            class="empty-tab-state"
                        >
                            <span class="empty-tab-icon">
                                <Icon name="users" :size="19" />
                            </span>

                            <h3>Assigned Users</h3>

                            <strong class="tab-stat">
                                {{ selectedRoleUserCount }}
                                {{
                                    selectedRoleUserCount === 1
                                        ? 'user'
                                        : 'users'
                                }}
                            </strong>

                            <p>
                                User assignment will be connected to the
                                Identity module. The role currently has
                                {{ selectedRoleUserCount }}
                                assigned users.
                            </p>
                        </div>

                        <!-- Audit tab -->
                        <div v-else class="empty-tab-state">
                            <span class="empty-tab-icon">
                                <Icon name="reports" :size="19" />
                            </span>

                            <h3>Access Audit</h3>

                            <p>
                                Role and permission changes will appear here
                                once the audit event stream is connected.
                            </p>
                        </div>
                    </template>

                    <!-- No role selected -->
                    <div v-else class="no-role-selected">
                        <div class="empty-tab-icon" aria-hidden="true">
                            <Icon name="users" :size="22" />
                        </div>

                        <h2>No role selected</h2>

                        <p>
                            Create or select a role to inspect its access scope.
                        </p>

                        <button
                            v-if="props.capabilities.canCreateRole"
                            class="access-btn access-btn-primary"
                            type="button"
                            @click="openCreateRole"
                        >
                            <Icon name="plus" :size="14" />

                            Create Role
                        </button>
                    </div>
                </main>
            </section>

            <!-- ---------------------------------------------------------------- -->
            <!-- Footer                                                            -->
            <!-- ---------------------------------------------------------------- -->

            <footer class="access-footer">
                <div>
                    <span class="footer-status-dot"></span>

                    Authorization is enforced server-side
                </div>

                <span> UI visibility is not a security boundary. </span>
            </footer>
        </div>

        <!-- ------------------------------------------------------------------ -->
        <!-- Role modal                                                          -->
        <!-- ------------------------------------------------------------------ -->

        <Transition name="modal">
            <div
                v-if="showRoleModal"
                class="modal-backdrop"
                @click.self="closeRoleModal"
            >
                <section
                    class="role-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="role-modal-title"
                >
                    <div class="role-modal-header">
                        <div>
                            <span class="modal-kicker"> Access Control </span>

                            <h2 id="role-modal-title">
                                {{ roleModalTitle }}
                            </h2>

                            <p>
                                {{ roleModalDescription }}
                            </p>
                        </div>

                        <button
                            class="icon-only-btn"
                            type="button"
                            aria-label="Close"
                            :disabled="isSavingRole"
                            @click="closeRoleModal"
                        >
                            <Icon
                                name="plus"
                                :size="15"
                                aria-hidden="true"
                                class="close-icon"
                            />
                        </button>
                    </div>

                    <!-- Show mode -->
                    <div
                        v-if="roleModalMode === 'show' && selectedRole"
                        class="role-details-body"
                    >
                        <div class="role-detail-hero">
                            <div
                                class="role-detail-avatar"
                                :class="{
                                    system: selectedRole.isSystem,
                                }"
                            >
                                <Icon
                                    :name="
                                        selectedRole.isSystem
                                            ? 'developer'
                                            : 'users'
                                    "
                                    :size="22"
                                />
                            </div>

                            <div>
                                <div class="role-detail-name-row">
                                    <h3>
                                        {{ selectedRole.name }}
                                    </h3>

                                    <span
                                        v-if="selectedRole.isSystem"
                                        class="role-badge system"
                                    >
                                        System
                                    </span>

                                    <span
                                        v-if="selectedRole.isActive"
                                        class="role-badge active"
                                    >
                                        Active
                                    </span>

                                    <span v-else class="role-badge inactive">
                                        Inactive
                                    </span>
                                </div>

                                <p>
                                    {{
                                        selectedRole.description ||
                                        'No description has been provided.'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="role-detail-grid">
                            <article>
                                <span>Role ID</span>
                                <strong> #{{ selectedRole.id }} </strong>
                            </article>

                            <article>
                                <span>Slug</span>
                                <strong class="mono">
                                    {{ selectedRole.slug }}
                                </strong>
                            </article>

                            <article>
                                <span>Assigned Users</span>
                                <strong>
                                    {{ selectedRole.userCount }}
                                </strong>
                            </article>

                            <article>
                                <span>Permissions</span>
                                <strong>
                                    {{ selectedRole.permissionCount }}
                                </strong>
                            </article>
                        </div>
                    </div>

                    <!-- Create/Edit mode -->
                    <div v-else class="role-modal-body">
                        <label class="form-field">
                            <span>
                                Role name
                                <em>*</em>
                            </span>

                            <input
                                v-model="roleForm.name"
                                type="text"
                                autocomplete="off"
                                placeholder="e.g. Warehouse Supervisor"
                                :disabled="isSavingRole"
                                @input="handleRoleNameInput"
                            />

                            <small
                                v-if="roleFormErrors.name"
                                class="field-error"
                            >
                                {{ roleFormErrors.name }}
                            </small>
                        </label>

                        <label class="form-field">
                            <span>
                                Slug
                                <em>*</em>
                            </span>

                            <input
                                v-model="roleForm.slug"
                                type="text"
                                autocomplete="off"
                                placeholder="warehouse-supervisor"
                                :disabled="isSavingRole"
                            />

                            <small
                                v-if="roleFormErrors.slug"
                                class="field-error"
                            >
                                {{ roleFormErrors.slug }}
                            </small>
                        </label>

                        <label class="form-field">
                            <span>Description</span>

                            <textarea
                                v-model="roleForm.description"
                                rows="4"
                                placeholder="Describe what this role is responsible for..."
                                :disabled="isSavingRole"
                            ></textarea>

                            <small
                                v-if="roleFormErrors.description"
                                class="field-error"
                            >
                                {{ roleFormErrors.description }}
                            </small>
                        </label>

                        <label class="form-switch-field">
                            <span>
                                <strong>Active role</strong>
                                <small>
                                    Inactive roles cannot be used for
                                    authorization.
                                </small>
                            </span>

                            <button
                                :class="[
                                    'permission-toggle',
                                    {
                                        on: roleForm.isActive,
                                    },
                                ]"
                                type="button"
                                :aria-pressed="roleForm.isActive"
                                :disabled="isSavingRole"
                                @click="roleForm.isActive = !roleForm.isActive"
                            >
                                <span></span>
                            </button>
                        </label>
                    </div>

                    <div class="role-modal-footer">
                        <button
                            class="access-btn access-btn-secondary"
                            type="button"
                            :disabled="isSavingRole"
                            @click="closeRoleModal"
                        >
                            Close
                        </button>

                        <button
                            v-if="!roleModalIsReadonly"
                            class="access-btn access-btn-primary"
                            type="button"
                            :disabled="isSavingRole"
                            @click="submitRole"
                        >
                            <span
                                v-if="isSavingRole"
                                class="mini-spinner"
                            ></span>

                            <Icon
                                v-else
                                :name="
                                    roleModalMode === 'create'
                                        ? 'plus'
                                        : 'check'
                                "
                                :size="14"
                            />

                            {{ roleSubmitLabel }}
                        </button>

                        <button
                            v-else-if="canEditSelectedRole"
                            class="access-btn access-btn-primary"
                            type="button"
                            @click="openEditRole"
                        >
                            <Icon name="settings" :size="14" />

                            Edit Role
                        </button>
                    </div>
                </section>
            </div>
        </Transition>
    </ErpLayout>
</template>

<style scoped>
.access-page {
    --access-radius: 14px;
    --access-radius-sm: 10px;
    --access-accent: var(--primary);
    --access-success: var(--success);

    min-height: 100%;
    padding: 0;
    color: var(--text-primary);
}

.access-header,
.access-title-row,
.access-header-actions,
.selected-role-heading,
.selected-role-name-row,
.selected-role-actions,
.module-heading,
.module-heading-actions,
.permission-toolbar,
.permission-toolbar-actions,
.permission-toolbar-meta,
.permission-tabs,
.role-modal-header,
.role-modal-footer,
.form-switch-field {
    display: flex;
    align-items: center;
}

.access-header {
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 18px;
}

.access-header-copy {
    min-width: 0;
}

.access-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
}

.access-breadcrumb .is-current {
    color: var(--text-secondary);
}

.access-breadcrumb-separator {
    color: var(--border-strong);
}

.access-title-row {
    gap: 12px;
}

.access-title-icon,
.access-stat-icon,
.selected-role-avatar,
.module-icon,
.empty-tab-icon,
.role-avatar,
.empty-state-icon,
.role-detail-avatar {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
}

.access-title-icon {
    width: 42px;
    height: 42px;
    border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
    border-radius: 12px;
    background: color-mix(in srgb, var(--primary) 10%, var(--surface));
    color: var(--primary);
    box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 9%, transparent);
}

.access-header h1 {
    margin: 0;
    font-size: 24px;
    line-height: 1.15;
    letter-spacing: -0.025em;
    font-weight: 750;
}

.access-header p {
    margin: 5px 0 0;
    color: var(--text-secondary);
    font-size: 12px;
}

.access-header-actions {
    flex-shrink: 0;
    gap: 8px;
}

.access-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 38px;
    padding: 0 13px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 650;
    cursor: pointer;
    transition:
        transform 180ms ease,
        border-color 180ms ease,
        background-color 180ms ease,
        color 180ms ease,
        box-shadow 180ms ease;
}

.access-btn:hover:not(:disabled) {
    border-color: var(--border-strong);
    color: var(--text-primary);
    transform: translateY(-1px);
}

.access-btn:focus-visible,
.icon-only-btn:focus-visible,
.role-filter:focus-visible,
.role-item:focus-visible,
.permission-tab:focus-visible,
.permission-group-header:focus-visible,
.permission-toggle:focus-visible,
.toolbar-text-btn:focus-visible,
.save-bar-reset:focus-visible {
    outline: 2px solid color-mix(in srgb, var(--primary) 55%, transparent);
    outline-offset: 2px;
}

.access-btn:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.access-btn-primary {
    border-color: color-mix(in srgb, var(--primary) 80%, var(--border));
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--primary) 92%, white),
        var(--primary)
    );
    color: #fff;
    box-shadow: 0 9px 22px color-mix(in srgb, var(--primary) 17%, transparent);
}

.access-btn-primary:hover:not(:disabled) {
    border-color: var(--primary);
    box-shadow: 0 12px 26px color-mix(in srgb, var(--primary) 22%, transparent);
}

.access-btn.compact {
    min-height: 34px;
    padding: 0 10px;
    font-size: 11px;
}

/* -------------------------------------------------------------------------- */
/* Feedback                                                                   */
/* -------------------------------------------------------------------------- */

.operation-message {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text-secondary);
    font-size: 11px;
    animation: access-enter 280ms ease both;
}

.operation-message span {
    flex: 1;
}

.operation-message button {
    width: 24px;
    height: 24px;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 18px;
}

.operation-message.success {
    border-color: color-mix(in srgb, var(--success) 28%, var(--border));
    background: color-mix(in srgb, var(--success) 7%, var(--surface));
    color: var(--success);
}

.operation-message.error {
    border-color: color-mix(in srgb, #dc4f4f 28%, var(--border));
    background: color-mix(in srgb, #dc4f4f 7%, var(--surface));
    color: #dc4f4f;
}

.operation-message.info {
    color: var(--text-secondary);
}

/* -------------------------------------------------------------------------- */
/* KPI                                                                         */
/* -------------------------------------------------------------------------- */

.access-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}

.access-stat-card {
    display: flex;
    align-items: center;
    gap: 11px;
    min-height: 78px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: color-mix(in srgb, var(--surface) 96%, var(--surface-muted));
    box-shadow: var(--shadow);
    animation: access-enter 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(var(--card-index, 0) * 55ms + 80ms);
    transition:
        transform 220ms ease,
        border-color 220ms ease,
        box-shadow 220ms ease;
}

.access-stat-card:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--primary) 28%, var(--border));
    box-shadow: 0 16px 34px color-mix(in srgb, var(--primary) 9%, transparent);
}

.access-stat-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    transition:
        transform 220ms ease,
        box-shadow 220ms ease;
}

.access-stat-card:hover .access-stat-icon {
    transform: scale(1.08) rotate(-3deg);
}

.access-stat-icon.green {
    background: color-mix(in srgb, var(--success) 12%, var(--surface));
    color: var(--success);
}

.access-stat-icon.blue {
    background: color-mix(in srgb, #3976e8 11%, var(--surface));
    color: #3976e8;
}

.access-stat-icon.purple {
    background: color-mix(in srgb, #8250e8 11%, var(--surface));
    color: #8250e8;
}

.access-stat-icon.orange {
    background: color-mix(in srgb, #e68b13 11%, var(--surface));
    color: #e68b13;
}

.access-stat-content {
    min-width: 0;
}

.access-stat-card span,
.access-stat-card small {
    display: block;
    color: var(--text-muted);
    font-size: 10px;
}

.access-stat-card strong {
    display: block;
    margin: 1px 0;
    color: var(--text-primary);
    font-size: 21px;
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.access-stat-card small {
    color: var(--text-muted);
}

/* -------------------------------------------------------------------------- */
/* Workspace                                                                   */
/* -------------------------------------------------------------------------- */

.access-workspace {
    position: relative;
    display: grid;
    grid-template-columns: 286px minmax(0, 1fr);
    min-height: 660px;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: var(--access-radius);
    background: var(--surface);
    box-shadow: var(--shadow);
    animation: access-enter 500ms cubic-bezier(0.22, 1, 0.36, 1) 300ms both;
}

.workspace-loading {
    position: absolute;
    inset: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    background: color-mix(in srgb, var(--surface) 78%, transparent);
    backdrop-filter: blur(4px);
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 650;
}

.loading-spinner,
.mini-spinner {
    display: inline-block;
    border-radius: 50%;
    animation: access-spin 750ms linear infinite;
}

.loading-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid color-mix(in srgb, var(--primary) 20%, transparent);
    border-top-color: var(--primary);
}

.mini-spinner {
    width: 12px;
    height: 12px;
    border: 2px solid rgb(255 255 255 / 30%);
    border-top-color: #fff;
}

/* -------------------------------------------------------------------------- */
/* Role panel                                                                  */
/* -------------------------------------------------------------------------- */

.role-panel {
    display: flex;
    min-width: 0;
    flex-direction: column;
    border-right: 1px solid var(--border);
    background: var(--surface-muted);
}

.role-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 15px 12px;
}

.role-panel-header h2 {
    margin: 0;
    color: var(--text-primary);
    font-size: 14px;
    letter-spacing: -0.01em;
}

.role-panel-header span {
    color: var(--text-muted);
    font-size: 10px;
}

.icon-only-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 31px;
    height: 31px;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text-secondary);
    cursor: pointer;
    transition:
        transform 180ms ease,
        border-color 180ms ease,
        color 180ms ease;
}

.icon-only-btn:hover:not(:disabled) {
    border-color: var(--border-strong);
    color: var(--text-primary);
    transform: translateY(-1px);
}

.close-icon {
    transform: rotate(45deg);
}

.icon-only-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.role-search,
.permission-search {
    display: flex;
    align-items: center;
    gap: 7px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text-muted);
}

.role-search {
    height: 35px;
    margin: 0 12px 9px;
    padding: 0 10px;
}

.role-search input,
.permission-search input {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text-primary);
    font-size: 11px;
}

.role-search input::placeholder,
.permission-search input::placeholder,
.form-field input::placeholder,
.form-field textarea::placeholder {
    color: var(--text-muted);
}

.role-filter-row {
    display: flex;
    gap: 4px;
    padding: 0 12px 9px;
}

.role-filter {
    padding: 5px 9px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 650;
    cursor: pointer;
    transition:
        background-color 180ms ease,
        color 180ms ease;
}

.role-filter:hover {
    color: var(--text-primary);
}

.role-filter.active {
    background: color-mix(in srgb, var(--primary) 12%, transparent);
    color: var(--primary);
}

.role-list {
    overflow: auto;
    padding: 2px 8px 12px;
}

.role-item {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 9px;
    padding: 9px 8px;
    border: 1px solid transparent;
    border-radius: 9px;
    background: transparent;
    color: var(--text-primary);
    text-align: left;
    cursor: pointer;
    transition:
        background-color 180ms ease,
        border-color 180ms ease,
        transform 180ms ease;
}

.role-item:hover {
    background: color-mix(in srgb, var(--surface-soft) 75%, transparent);
    transform: translateX(1px);
}

.role-item.selected {
    border-color: color-mix(in srgb, var(--primary) 24%, var(--border));
    background: color-mix(in srgb, var(--primary) 8%, var(--surface));
    box-shadow: inset 3px 0 0 var(--primary);
}

.role-avatar {
    width: 31px;
    height: 31px;
    border-radius: 8px;
    background: var(--surface-soft);
    color: var(--text-secondary);
}

.role-avatar.system {
    background: color-mix(in srgb, var(--success) 12%, var(--surface));
    color: var(--success);
}

.role-item-copy {
    min-width: 0;
    flex: 1;
}

.role-item-copy strong {
    display: block;
    overflow: hidden;
    color: var(--text-primary);
    font-size: 11px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.role-item-copy small {
    display: block;
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 9px;
}

.system-dot,
.footer-status-dot {
    flex: 0 0 auto;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--success);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--success) 10%, transparent);
}

.role-empty {
    display: grid;
    justify-items: center;
    gap: 5px;
    padding: 40px 20px;
    color: var(--text-muted);
    text-align: center;
}

.empty-search-icon {
    font-size: 22px;
    opacity: 0.65;
}

.role-empty strong {
    color: var(--text-secondary);
    font-size: 11px;
}

.role-empty span {
    font-size: 10px;
}

/* -------------------------------------------------------------------------- */
/* Permission panel                                                            */
/* -------------------------------------------------------------------------- */

.permission-panel {
    min-width: 0;
    background: var(--surface);
}

.permission-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 17px 18px 14px;
    border-bottom: 1px solid var(--border);
}

.selected-role-heading {
    min-width: 0;
    gap: 11px;
}

.selected-role-avatar {
    width: 42px;
    height: 42px;
    border-radius: 11px;
    background: var(--surface-soft);
    color: var(--text-secondary);
}

.selected-role-avatar.system {
    background: color-mix(in srgb, var(--success) 12%, var(--surface));
    color: var(--success);
}

.selected-role-details {
    min-width: 0;
}

.selected-role-name-row {
    flex-wrap: wrap;
    gap: 6px;
}

.selected-role-name-row h2 {
    margin: 0;
    color: var(--text-primary);
    font-size: 17px;
    line-height: 1.2;
    letter-spacing: -0.015em;
}

.selected-role-heading p {
    max-width: 650px;
    margin: 4px 0 0;
    overflow: hidden;
    color: var(--text-secondary);
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.selected-role-actions {
    flex-shrink: 0;
    gap: 6px;
}

.role-badge {
    display: inline-flex;
    align-items: center;
    min-height: 19px;
    padding: 0 7px;
    border-radius: 999px;
    font-size: 8px;
    font-weight: 750;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.role-badge.system,
.role-badge.active {
    background: color-mix(in srgb, var(--success) 11%, var(--surface));
    color: var(--success);
}

.role-badge.inactive {
    background: var(--surface-muted);
    color: var(--text-muted);
}

.permission-tabs {
    gap: 4px;
    padding: 0 18px;
    overflow-x: auto;
    border-bottom: 1px solid var(--border);
}

.permission-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 42px;
    margin-bottom: -1px;
    padding: 0 9px;
    border: 0;
    border-bottom: 2px solid transparent;
    background: transparent;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 650;
    white-space: nowrap;
    cursor: pointer;
    transition:
        color 180ms ease,
        border-color 180ms ease;
}

.permission-tab span {
    min-width: 17px;
    padding: 2px 5px;
    border-radius: 999px;
    background: var(--surface-muted);
    color: var(--text-muted);
    font-size: 8px;
    text-align: center;
}

.permission-tab:hover {
    color: var(--text-primary);
}

.permission-tab.active {
    border-bottom-color: var(--primary);
    color: var(--primary);
}

.permission-tab.active span {
    background: color-mix(in srgb, var(--primary) 12%, transparent);
    color: var(--primary);
}

.permission-content {
    padding: 14px 18px 20px;
}

.permission-toolbar {
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
}

.permission-search {
    width: min(360px, 100%);
    height: 34px;
    padding: 0 10px;
}

.permission-toolbar-actions {
    justify-content: flex-end;
    gap: 10px;
}

.permission-toolbar-meta {
    gap: 12px;
    color: var(--text-muted);
    font-size: 9px;
    white-space: nowrap;
}

.permission-toolbar-meta i {
    display: inline-block;
    width: 5px;
    height: 5px;
    margin-right: 4px;
    border-radius: 50%;
    background: var(--success);
}

.toolbar-text-btn {
    padding: 4px 0;
    border: 0;
    background: transparent;
    color: var(--primary);
    font-size: 9px;
    font-weight: 700;
    cursor: pointer;
}

.toolbar-text-btn:hover {
    text-decoration: underline;
}

/* -------------------------------------------------------------------------- */
/* Permission save bar                                                         */
/* -------------------------------------------------------------------------- */

.permission-save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 11px;
    padding: 10px 12px;
    border: 1px solid color-mix(in srgb, var(--primary) 24%, var(--border));
    border-radius: 10px;
    background: color-mix(in srgb, var(--primary) 6%, var(--surface));
}

.permission-save-bar > div:first-child {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.permission-save-bar > div:last-child {
    display: flex;
    align-items: center;
    gap: 7px;
}

.save-bar-dot {
    flex: 0 0 auto;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--primary);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 10%, transparent);
}

.permission-save-bar strong,
.permission-save-bar small {
    display: block;
}

.permission-save-bar strong {
    color: var(--text-primary);
    font-size: 10px;
}

.permission-save-bar small {
    margin-top: 2px;
    color: var(--text-secondary);
    font-size: 8px;
}

.save-bar-reset {
    min-height: 32px;
    padding: 0 9px;
    border: 0;
    background: transparent;
    color: var(--text-secondary);
    font-size: 10px;
    font-weight: 650;
    cursor: pointer;
}

.save-bar-reset:hover:not(:disabled) {
    color: var(--text-primary);
}

.save-bar-reset:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

/* -------------------------------------------------------------------------- */
/* System notice                                                              */
/* -------------------------------------------------------------------------- */

.system-notice {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-bottom: 11px;
    padding: 10px 11px;
    border: 1px solid color-mix(in srgb, var(--success) 24%, var(--border));
    border-radius: 9px;
    background: color-mix(in srgb, var(--success) 7%, var(--surface));
}

.system-notice-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 7px;
    background: color-mix(in srgb, var(--success) 10%, var(--surface));
    color: var(--success);
}

.system-notice strong,
.system-notice span {
    display: block;
}

.system-notice strong {
    color: var(--text-primary);
    font-size: 10px;
}

.system-notice span {
    margin-top: 2px;
    color: var(--text-secondary);
    font-size: 9px;
    line-height: 1.45;
}

/* -------------------------------------------------------------------------- */
/* Permission groups                                                          */
/* -------------------------------------------------------------------------- */

.permission-groups {
    display: grid;
    gap: 8px;
}

.permission-group {
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    transition:
        border-color 180ms ease,
        box-shadow 180ms ease,
        transform 180ms ease;
}

.permission-group:hover {
    border-color: color-mix(in srgb, var(--primary) 22%, var(--border));
    box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 5%, transparent);
}

.permission-group-header {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    min-height: 48px;
    padding: 7px 11px;
    border: 0;
    background: var(--surface-muted);
    color: var(--text-secondary);
    cursor: pointer;
    text-align: left;
    transition:
        background-color 180ms ease,
        transform 180ms ease;
}

.permission-group-header:hover {
    background: var(--surface-soft);
}

.permission-group-header:active {
    transform: scale(0.995);
}

.module-heading {
    min-width: 0;
    gap: 8px;
}

.module-icon {
    width: 29px;
    height: 29px;
    border-radius: 7px;
    background: color-mix(in srgb, var(--primary) 10%, var(--surface-muted));
    color: var(--primary);
}

.module-heading strong,
.module-heading small {
    display: block;
}

.module-heading strong {
    color: var(--text-primary);
    font-size: 10px;
}

.module-heading small {
    margin-top: 1px;
    color: var(--text-muted);
    font-size: 8px;
}

.module-heading-actions {
    gap: 9px;
    color: var(--text-muted);
}

.module-coverage {
    min-width: 28px;
    color: var(--text-secondary);
    font-size: 9px;
    text-align: right;
}

.module-chevron {
    display: inline-flex;
    transition: transform 180ms ease;
}

.module-chevron.expanded {
    transform: rotate(180deg);
}

.permission-list {
    border-top: 1px solid var(--border);
    background: var(--surface);
}

.permission-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 10px 11px;
    border-bottom: 1px solid var(--border);
    transition: background-color 160ms ease;
}

.permission-row:last-child {
    border-bottom: 0;
}

.permission-row:hover {
    background: color-mix(in srgb, var(--surface-muted) 55%, transparent);
}

.permission-copy {
    min-width: 0;
}

.permission-copy strong {
    display: block;
    color: var(--text-primary);
    font-size: 10px;
}

.permission-copy span {
    display: block;
    margin-top: 2px;
    overflow: hidden;
    color: var(--text-muted);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 8px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.permission-copy small {
    display: block;
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 8px;
}

.permission-toggle {
    position: relative;
    flex: 0 0 auto;
    width: 34px;
    height: 20px;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: color-mix(in srgb, var(--text-muted) 25%, var(--surface));
    cursor: pointer;
    transition:
        background-color 180ms ease,
        opacity 180ms ease,
        transform 180ms ease;
}

.permission-toggle:hover:not(:disabled) {
    transform: scale(1.04);
}

.permission-toggle span {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgb(23 39 56 / 18%);
    transition: transform 180ms ease;
}

.permission-toggle.on {
    background: var(--success);
}

.permission-toggle.on span {
    transform: translateX(14px);
}

.permission-toggle:disabled {
    cursor: not-allowed;
    opacity: 0.62;
}

/* -------------------------------------------------------------------------- */
/* Empty states                                                               */
/* -------------------------------------------------------------------------- */

.empty-state,
.empty-tab-state,
.no-role-selected {
    display: grid;
    justify-items: center;
    align-content: center;
    min-height: 420px;
    padding: 40px;
    text-align: center;
    color: var(--text-muted);
}

.empty-state-icon,
.empty-tab-icon {
    width: 52px;
    height: 52px;
    margin-bottom: 11px;
    border-radius: 14px;
    background: var(--surface-muted);
    color: var(--text-secondary);
}

.empty-state h3,
.empty-tab-state h3,
.no-role-selected h2 {
    margin: 0;
    color: var(--text-primary);
    font-size: 14px;
}

.empty-state p,
.empty-tab-state p,
.no-role-selected p {
    max-width: 420px;
    margin: 5px 0 0;
    color: var(--text-secondary);
    font-size: 10px;
    line-height: 1.5;
}

.empty-tab-state .tab-stat {
    margin-top: 4px;
    color: var(--primary);
    font-size: 12px;
}

.no-role-selected {
    min-height: 600px;
    gap: 8px;
}

.no-role-selected h2 {
    font-size: 17px;
}

/* -------------------------------------------------------------------------- */
/* Footer                                                                     */
/* -------------------------------------------------------------------------- */

.access-footer {
    justify-content: space-between;
    gap: 12px;
    padding: 9px 2px 0;
    color: var(--text-muted);
    font-size: 9px;
    animation: access-enter 500ms cubic-bezier(0.22, 1, 0.36, 1) 380ms both;
}

.footer-status-dot {
    display: inline-block;
    margin-right: 5px;
}

/* -------------------------------------------------------------------------- */
/* Modal                                                                      */
/* -------------------------------------------------------------------------- */

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: grid;
    place-items: center;
    padding: 20px;
    background: rgb(7 17 29 / 62%);
    backdrop-filter: blur(6px);
}

.role-modal {
    width: min(500px, 100%);
    max-height: min(760px, calc(100vh - 40px));
    overflow: auto;
    border: 1px solid var(--border);
    border-radius: 15px;
    background: var(--surface);
    box-shadow: 0 24px 70px rgb(11 27 44 / 20%);
}

.role-modal-header {
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
    padding: 18px 18px 14px;
    border-bottom: 1px solid var(--border);
}

.modal-kicker {
    display: block;
    margin-bottom: 4px;
    color: var(--primary);
    font-size: 8px;
    font-weight: 750;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.role-modal-header h2 {
    margin: 0;
    color: var(--text-primary);
    font-size: 17px;
}

.role-modal-header p {
    margin: 4px 0 0;
    color: var(--text-secondary);
    font-size: 10px;
}

.role-modal-body,
.role-details-body {
    display: grid;
    gap: 13px;
    padding: 17px 18px;
}

.form-field {
    display: block;
}

.form-field > span {
    display: block;
    margin-bottom: 5px;
    color: var(--text-secondary);
    font-size: 10px;
    font-weight: 650;
}

.form-field em {
    color: #dc4f4f;
    font-style: normal;
}

.form-field input,
.form-field textarea {
    width: 100%;
    box-sizing: border-box;
    resize: vertical;
    border: 1px solid var(--border);
    border-radius: 8px;
    outline: 0;
    background: var(--surface-muted);
    color: var(--text-primary);
    padding: 9px 10px;
    font: inherit;
    font-size: 11px;
    transition:
        border-color 180ms ease,
        box-shadow 180ms ease,
        opacity 180ms ease;
}

.form-field input:focus,
.form-field textarea:focus {
    border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 9%, transparent);
}

.form-field input:disabled,
.form-field textarea:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.field-error {
    display: block;
    margin-top: 4px;
    color: #dc4f4f;
    font-size: 9px;
}

.form-switch-field {
    justify-content: space-between;
    gap: 15px;
    padding: 10px 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface-muted);
}

.form-switch-field > span {
    min-width: 0;
}

.form-switch-field strong,
.form-switch-field small {
    display: block;
}

.form-switch-field strong {
    color: var(--text-primary);
    font-size: 10px;
}

.form-switch-field small {
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 8px;
}

.role-details-body {
    gap: 16px;
}

.role-detail-hero {
    display: flex;
    align-items: flex-start;
    gap: 11px;
}

.role-detail-avatar {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: var(--surface-muted);
    color: var(--text-secondary);
}

.role-detail-avatar.system {
    background: color-mix(in srgb, var(--success) 12%, var(--surface));
    color: var(--success);
}

.role-detail-name-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}

.role-detail-name-row h3 {
    margin: 0;
    color: var(--text-primary);
    font-size: 16px;
}

.role-detail-hero p {
    max-width: 390px;
    margin: 5px 0 0;
    color: var(--text-secondary);
    font-size: 10px;
    line-height: 1.5;
}

.role-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.role-detail-grid article {
    min-width: 0;
    padding: 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface-muted);
}

.role-detail-grid span,
.role-detail-grid strong {
    display: block;
}

.role-detail-grid span {
    color: var(--text-muted);
    font-size: 8px;
}

.role-detail-grid strong {
    margin-top: 4px;
    overflow: hidden;
    color: var(--text-primary);
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.role-detail-grid .mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 9px;
}

.role-modal-footer {
    justify-content: flex-end;
    gap: 7px;
    padding: 12px 18px;
    border-top: 1px solid var(--border);
    background: var(--surface-muted);
}

/* -------------------------------------------------------------------------- */
/* Transitions                                                                */
/* -------------------------------------------------------------------------- */

.permission-expand-enter-active,
.permission-expand-leave-active {
    overflow: hidden;
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}

.permission-expand-enter-from,
.permission-expand-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity 200ms ease;
}

.modal-enter-active .role-modal,
.modal-leave-active .role-modal {
    transition:
        transform 240ms cubic-bezier(0.22, 1, 0.36, 1),
        opacity 200ms ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .role-modal,
.modal-leave-to .role-modal {
    opacity: 0;
    transform: translateY(10px) scale(0.985);
}

.message-enter-active,
.message-leave-active,
.save-bar-enter-active,
.save-bar-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}

.message-enter-from,
.message-leave-to,
.save-bar-enter-from,
.save-bar-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}

.ui-glyph {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1em;
    line-height: 1;
    font-weight: 750;
}

/* -------------------------------------------------------------------------- */
/* Animations                                                                 */
/* -------------------------------------------------------------------------- */

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

@keyframes access-spin {
    to {
        transform: rotate(360deg);
    }
}

/* -------------------------------------------------------------------------- */
/* Responsive                                                                 */
/* -------------------------------------------------------------------------- */

@media (max-width: 1100px) {
    .access-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .access-workspace {
        grid-template-columns: 250px minmax(0, 1fr);
    }

    .selected-role-actions .access-btn:first-child {
        display: none;
    }
}

@media (max-width: 820px) {
    .access-page {
        padding: 0;
    }

    .access-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .access-header-actions {
        width: 100%;
    }

    .access-header-actions .access-btn {
        flex: 1;
    }

    .access-workspace {
        grid-template-columns: 1fr;
    }

    .role-panel {
        max-height: 340px;
        border-right: 0;
        border-bottom: 1px solid var(--border);
    }

    .permission-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .selected-role-actions {
        width: 100%;
    }

    .selected-role-actions .access-btn {
        flex: 1;
    }
}

@media (max-width: 700px) {
    .permission-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .permission-search {
        width: 100%;
    }

    .permission-toolbar-actions {
        justify-content: space-between;
    }

    .permission-save-bar {
        align-items: flex-start;
        flex-direction: column;
    }

    .permission-save-bar > div:last-child {
        width: 100%;
        justify-content: flex-end;
    }
}

@media (max-width: 560px) {
    .access-stat-grid {
        grid-template-columns: 1fr;
    }

    .access-stat-card {
        min-height: 68px;
    }

    .permission-toolbar-actions {
        align-items: center;
        flex-wrap: wrap;
    }

    .permission-toolbar-meta {
        width: 100%;
        justify-content: space-between;
    }

    .permission-tabs {
        padding: 0 10px;
    }

    .permission-tab {
        white-space: nowrap;
    }

    .permission-row {
        align-items: flex-start;
    }

    .permission-copy small {
        display: none;
    }

    .access-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .selected-role-heading {
        width: 100%;
    }

    .selected-role-heading p {
        white-space: normal;
    }

    .role-detail-grid {
        grid-template-columns: 1fr;
    }

    .role-modal {
        max-height: calc(100vh - 24px);
    }

    .modal-backdrop {
        padding: 12px;
    }
}

/* -------------------------------------------------------------------------- */
/* Reduced motion                                                             */
/* -------------------------------------------------------------------------- */

@media (prefers-reduced-motion: reduce) {
    .access-stat-card,
    .access-workspace,
    .access-footer,
    .operation-message {
        animation: none;
    }

    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
    }
}
</style>
