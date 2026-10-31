<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';

import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

interface AuthUser {
    id: number;
    name: string;
    email: string;
}

interface ActivityActor {
    id: number;
    name: string;
    email: string;
}

interface ActivitySubject {
    id?: number;
    [key: string]: unknown;
}

interface ActivityLog {
    id: number;

    actor_user_id: number | null;
    actor_type: 'user' | 'system';

    actor?: ActivityActor | null;

    action: string;
    module: string;
    description: string;

    subject_type?: string | null;
    subject_id?: number | string | null;
    subject?: ActivitySubject | null;

    old_values?: Record<string, unknown> | null;
    new_values?: Record<string, unknown> | null;

    ip_address?: string | null;
    user_agent?: string | null;
    request_id?: string | null;

    metadata?: Record<string, unknown> | null;

    created_at: string;
    integrity_hash?: string | null;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface ActivityLogResponse {
    data: ActivityLog[];
    meta: PaginationMeta;
}

interface Props {
    userName?: string;
    userEmail?: string;
}

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = withDefaults(defineProps<Props>(), {
    userName: '',
    userEmail: '',
});

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const logs = ref<ActivityLog[]>([]);

const isLoading = ref(false);
const isRefreshing = ref(false);

const search = ref('');
const moduleFilter = ref('');
const actorFilter = ref('');
const fromDate = ref('');
const toDate = ref('');

const currentPage = ref(1);
const perPage = ref(25);

const pagination = ref<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 0,
    from: null,
    to: null,
});

const operationMessage = ref('');
const operationMessageType = ref<'success' | 'error' | 'info'>('info');

const selectedLog = ref<ActivityLog | null>(null);
const showDetailsModal = ref(false);

/*
|--------------------------------------------------------------------------
| API helper
|--------------------------------------------------------------------------
*/

async function apiRequest<T>(
    url: string,
    method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE' = 'GET',
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

    const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
    });

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
        const errorPayload =
            payload && typeof payload === 'object'
                ? (payload as {
                      message?: string;
                  })
                : {};

        throw new Error(
            errorPayload.message ||
                `Request failed with status ${response.status}.`,
        );
    }

    return payload as T;
}

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const totalActivities = computed<number>(() => pagination.value.total);

const visibleFrom = computed<number>(() => pagination.value.from ?? 0);

const visibleTo = computed<number>(() => pagination.value.to ?? 0);

const filteredLogs = computed<ActivityLog[]>(() => {
    const query = search.value.trim().toLocaleLowerCase();

    if (!query) {
        return logs.value;
    }

    return logs.value.filter((log) => {
        const searchableText = [
            log.description,
            log.action,
            log.module,
            log.request_id ?? '',
            actorName(log),
            actorEmail(log),
            log.ip_address ?? '',
            subjectLabel(log),
        ]
            .join(' ')
            .toLocaleLowerCase();

        return searchableText.includes(query);
    });
});

const hasLogs = computed<boolean>(() => filteredLogs.value.length > 0);

const filteredCount = computed<number>(() => filteredLogs.value.length);

const moduleOptions = computed<string[]>(() => {
    const modules = new Set<string>();

    for (const log of logs.value) {
        if (log.module.trim() !== '') {
            modules.add(log.module);
        }
    }

    return Array.from(modules).sort((a, b) => a.localeCompare(b));
});

const actorOptions = computed<
    Array<{
        value: 'user' | 'system';
        label: string;
    }>
>(() => [
    {
        value: 'user',
        label: 'Users',
    },
    {
        value: 'system',
        label: 'System',
    },
]);

const pageNumbers = computed<number[]>(() => {
    const lastPage = pagination.value.last_page;
    const current = pagination.value.current_page;

    if (lastPage <= 1) {
        return [1];
    }

    const pages = new Set<number>();

    pages.add(1);
    pages.add(lastPage);

    for (let page = current - 2; page <= current + 2; page += 1) {
        if (page >= 1 && page <= lastPage) {
            pages.add(page);
        }
    }

    return Array.from(pages).sort((a, b) => a - b);
});

/*
|--------------------------------------------------------------------------
| Formatting
|--------------------------------------------------------------------------
*/

function formatDate(value?: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    }).format(date);
}

function formatTime(value?: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
}

function formatDateTime(value?: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
}

function actorName(log: ActivityLog): string {
    if (log.actor_type === 'system') {
        return 'System';
    }

    return log.actor?.name || 'Unknown user';
}

function actorEmail(log: ActivityLog): string {
    if (log.actor_type === 'system') {
        return 'system';
    }

    return log.actor?.email || 'Unknown user';
}

function actionLabel(action: string): string {
    return action
        .replace(/[._-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function moduleLabel(module: string): string {
    return module
        .replace(/[._-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function subjectLabel(log: ActivityLog): string {
    if (
        log.subject_type &&
        log.subject_id !== null &&
        log.subject_id !== undefined
    ) {
        const type = log.subject_type
            .split('\\')
            .pop()
            ?.replace(/([a-z])([A-Z])/g, '$1 $2');

        return `${type || 'Record'} #${log.subject_id}`;
    }

    return '—';
}

function jsonPreview(
    value: Record<string, unknown> | null | undefined,
): string {
    if (!value) {
        return 'No data recorded.';
    }

    try {
        return JSON.stringify(value, null, 2);
    } catch {
        return 'Unable to display data.';
    }
}

function hasSnapshot(
    value: Record<string, unknown> | null | undefined,
): boolean {
    return (
        value !== null && value !== undefined && Object.keys(value).length > 0
    );
}

function exportAuditLogs(): void {
    if (!fromDate.value || !toDate.value) {
        operationMessage.value = 'Choose both date boundaries before exporting (maximum 90 days).';
        operationMessageType.value = 'error';
        return;
    }
    const params = new URLSearchParams(buildQuery());
    params.delete('page');
    params.delete('per_page');
    window.location.assign(`/api/activity-logs/export?${params.toString()}`);
}

/*
|--------------------------------------------------------------------------
| API query
|--------------------------------------------------------------------------
*/

function buildQuery(): string {
    const params = new URLSearchParams();

    const searchValue = search.value.trim();

    if (searchValue) {
        params.set('search', searchValue);
    }

    if (moduleFilter.value) {
        params.set('module', moduleFilter.value);
    }

    if (actorFilter.value) {
        params.set('actor_type', actorFilter.value);
    }
    if (fromDate.value) params.set('from', fromDate.value);
    if (toDate.value) params.set('to', toDate.value);

    params.set('per_page', String(perPage.value));

    params.set('page', String(currentPage.value));

    return params.toString();
}

async function fetchLogs(
    options: {
        refresh?: boolean;
        silent?: boolean;
    } = {},
): Promise<void> {
    const { refresh = false, silent = false } = options;

    if (refresh) {
        isRefreshing.value = true;
    } else if (!silent) {
        isLoading.value = true;
    }

    operationMessage.value = '';

    try {
        const query = buildQuery();

        const response = await apiRequest<ActivityLogResponse>(
            `/api/activity-logs?${query}`,
        );

        logs.value = response.data ?? [];

        pagination.value = response.meta ?? {
            current_page: currentPage.value,
            last_page: 1,
            per_page: perPage.value,
            total: logs.value.length,
            from: logs.value.length > 0 ? 1 : null,
            to: logs.value.length > 0 ? logs.value.length : null,
        };

        currentPage.value = pagination.value.current_page;

        perPage.value = pagination.value.per_page;
    } catch (error) {
        operationMessageType.value = 'error';

        operationMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to load activity logs.';
    } finally {
        isLoading.value = false;
        isRefreshing.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

function applyFilters(): void {
    currentPage.value = 1;

    void fetchLogs();
}

function resetFilters(): void {
    search.value = '';
    moduleFilter.value = '';
    actorFilter.value = '';
    fromDate.value = '';
    toDate.value = '';
    currentPage.value = 1;

    void fetchLogs();
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

function goToPage(page: number): void {
    if (
        page < 1 ||
        page > pagination.value.last_page ||
        page === currentPage.value
    ) {
        return;
    }

    currentPage.value = page;

    void fetchLogs();
}

function previousPage(): void {
    goToPage(pagination.value.current_page - 1);
}

function nextPage(): void {
    goToPage(pagination.value.current_page + 1);
}

function changePerPage(value: string): void {
    const parsed = Number(value);

    if (!Number.isFinite(parsed) || parsed < 10) {
        return;
    }

    perPage.value = parsed;
    currentPage.value = 1;

    void fetchLogs();
}

/*
|--------------------------------------------------------------------------
| Details
|--------------------------------------------------------------------------
*/

function openDetails(log: ActivityLog): void {
    selectedLog.value = log;
    showDetailsModal.value = true;
}

function closeDetails(): void {
    showDetailsModal.value = false;
    selectedLog.value = null;
}

/*
|--------------------------------------------------------------------------
| Keyboard / refresh
|--------------------------------------------------------------------------
*/

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && showDetailsModal.value) {
        closeDetails();
    }
}

/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(() => {
    void fetchLogs();

    window.addEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Head title="Activity Logs" />

    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <div class="activity-page">
            <!-- ============================================================ -->
            <!-- Page Header                                                   -->
            <!-- ============================================================ -->

            <header class="activity-header">
                <div class="activity-header-copy">
                    <div class="activity-breadcrumb">
                        <Icon name="settings" :size="13" />

                        <span>System</span>

                        <span class="activity-breadcrumb-separator"> / </span>

                        <span class="is-current"> Activity Logs </span>
                    </div>

                    <div class="activity-title-row">
                        <div class="activity-title-icon">
                            <Icon name="settings" :size="20" />
                        </div>

                        <div>
                            <h1>Activity Logs</h1>

                            <p>
                                Review immutable user, administrative and system
                                activity across Madina Scale.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="activity-header-actions">
                    <button
                        class="activity-btn activity-btn-secondary"
                        type="button"
                        :disabled="isLoading || isRefreshing"
                        @click="
                            fetchLogs({
                                refresh: true,
                            })
                        "
                    >
                        <Icon
                            name="refresh"
                            :size="14"
                            :class="{ 'is-spinning': isRefreshing }"
                        />

                        <span>Refresh</span>
                    </button>
                </div>
            </header>

            <!-- ============================================================ -->
            <!-- Feedback                                                      -->
            <!-- ============================================================ -->

            <Transition name="message">
                <div
                    v-if="operationMessage"
                    class="operation-message"
                    :class="operationMessageType"
                    role="status"
                >
                    <Icon
                        :name="
                            operationMessageType === 'error'
                                ? 'settings'
                                : 'check'
                        "
                        :size="14"
                    />

                    <span>
                        {{ operationMessage }}
                    </span>

                    <button
                        type="button"
                        aria-label="Dismiss"
                        @click="operationMessage = ''"
                    >
                        ×
                    </button>
                </div>
            </Transition>

            <!-- ============================================================ -->
            <!-- KPI Cards                                                     -->
            <!-- ============================================================ -->

            <section class="activity-stat-grid">
                <article class="activity-stat-card" style="--card-index: 0">
                    <div class="activity-stat-icon green">
                        <Icon name="analytics" :size="17" />
                    </div>

                    <div class="activity-stat-content">
                        <span> Total activities </span>

                        <strong>
                            {{ totalActivities }}
                        </strong>

                        <small> Recorded audit events </small>
                    </div>
                </article>

                <article class="activity-stat-card" style="--card-index: 1">
                    <div class="activity-stat-icon blue">
                        <Icon name="developer" :size="17" />
                    </div>

                    <div class="activity-stat-content">
                        <span> Audit status </span>

                        <strong> Immutable </strong>

                        <small> Records cannot be edited </small>
                    </div>
                </article>

                <article class="activity-stat-card" style="--card-index: 2">
                    <div class="activity-stat-icon purple">
                        <Icon name="reports" :size="17" />
                    </div>

                    <div class="activity-stat-content">
                        <span> Showing </span>

                        <strong> {{ visibleFrom }}–{{ visibleTo }} </strong>

                        <small> of {{ totalActivities }} records </small>
                    </div>
                </article>
            </section>

            <!-- ============================================================ -->
            <!-- Filter Toolbar                                                 -->
            <!-- ============================================================ -->

            <section class="activity-toolbar-card">
                <div class="activity-toolbar">
                    <label class="activity-search">
                        <Icon name="search" :size="15" />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search activity..."
                            autocomplete="off"
                        />
                    </label>

                    <div class="activity-toolbar-actions">
                        <input v-model="fromDate" class="activity-select" type="date" aria-label="From date" />
                        <input v-model="toDate" class="activity-select" type="date" aria-label="To date" />
                        <select
                            v-model="moduleFilter"
                            class="activity-select"
                            aria-label="Filter by module"
                        >
                            <option value="">All modules</option>

                            <option
                                v-for="module in moduleOptions"
                                :key="module"
                                :value="module"
                            >
                                {{ moduleLabel(module) }}
                            </option>
                        </select>

                        <select
                            v-model="actorFilter"
                            class="activity-select"
                            aria-label="Filter by actor"
                        >
                            <option value="">All actors</option>

                            <option
                                v-for="actor in actorOptions"
                                :key="actor.value"
                                :value="actor.value"
                            >
                                {{ actor.label }}
                            </option>
                        </select>

                        <button
                            class="activity-btn activity-btn-primary"
                            type="button"
                            :disabled="isLoading"
                            @click="applyFilters"
                        >
                            <Icon name="search" :size="14" />

                            Apply
                        </button>
                        <button class="activity-btn" type="button" @click="exportAuditLogs">
                            Export CSV
                        </button>
                    </div>
                </div>

                <div class="activity-toolbar-meta">
                    <span>
                        {{ filteredCount }}
                        {{ filteredCount === 1 ? 'record' : 'records' }}
                        <template v-if="search.trim()">
                            of {{ totalActivities }}
                        </template>
                    </span>

                    <button
                        v-if="search || moduleFilter || actorFilter || fromDate || toDate"
                        class="toolbar-text-btn"
                        type="button"
                        @click="resetFilters"
                    >
                        Clear filters
                    </button>
                </div>
            </section>

            <!-- ============================================================ -->
            <!-- Activity Table                                                 -->
            <!-- ============================================================ -->

            <section class="activity-table-card">
                <header class="activity-table-header">
                    <div>
                        <div class="activity-section-title">
                            <div class="activity-section-icon">
                                <Icon name="analytics" :size="16" />
                            </div>

                            <div>
                                <h2>Recent activity</h2>

                                <p>Latest recorded application events.</p>
                            </div>
                        </div>
                    </div>

                    <span class="activity-record-count">
                        {{ filteredCount }}
                        {{ filteredCount === 1 ? 'record' : 'records' }}
                    </span>
                </header>

                <div v-if="isLoading" class="activity-loading">
                    <span class="loading-spinner"></span>

                    <span> Loading activity logs... </span>
                </div>

                <div v-else-if="!hasLogs" class="activity-empty">
                    <div class="activity-empty-icon">
                        <Icon name="analytics" :size="22" />
                    </div>

                    <h3>No activity found</h3>

                    <p>
                        There are no activity records matching the current
                        filters.
                    </p>

                    <button
                        v-if="search || moduleFilter || actorFilter || fromDate || toDate"
                        class="activity-btn activity-btn-secondary"
                        type="button"
                        @click="resetFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <div v-else class="activity-table-wrap">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>Activity</th>

                                <th>Actor</th>

                                <th>Module</th>

                                <th>Action</th>

                                <th>IP Address</th>

                                <th>Time</th>

                                <th class="activity-action-column">
                                    <span class="sr-only"> Details </span>
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="log in filteredLogs" :key="log.id">
                                <td class="activity-description-cell">
                                    <div class="activity-description">
                                        <strong>
                                            {{ log.description }}
                                        </strong>

                                        <span v-if="subjectLabel(log) !== '—'">
                                            {{ subjectLabel(log) }}
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <div class="activity-actor">
                                        <div
                                            class="actor-avatar"
                                            :class="{
                                                system:
                                                    log.actor_type === 'system',
                                            }"
                                        >
                                            <Icon
                                                :name="
                                                    log.actor_type === 'system'
                                                        ? 'developer'
                                                        : 'users'
                                                "
                                                :size="14"
                                            />
                                        </div>

                                        <div class="actor-copy">
                                            <strong>
                                                {{ actorName(log) }}
                                            </strong>

                                            <small>
                                                {{ actorEmail(log) }}
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="activity-badge module">
                                        {{ moduleLabel(log.module) }}
                                    </span>
                                </td>

                                <td>
                                    <span class="activity-badge action">
                                        {{ actionLabel(log.action) }}
                                    </span>
                                </td>

                                <td>
                                    <span class="activity-ip">
                                        {{ log.ip_address || '—' }}
                                    </span>
                                </td>

                                <td>
                                    <div class="activity-time">
                                        <strong>
                                            {{ formatDate(log.created_at) }}
                                        </strong>

                                        <small>
                                            {{ formatTime(log.created_at) }}
                                        </small>
                                    </div>
                                </td>

                                <td class="activity-action-column">
                                    <button
                                        class="activity-view-btn"
                                        type="button"
                                        aria-label="View activity details"
                                        @click="openDetails(log)"
                                    >
                                        <Icon name="eye" :size="15" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ======================================================== -->
                <!-- Pagination                                                  -->
                <!-- ======================================================== -->

                <footer
                    v-if="!isLoading && hasLogs && pagination.last_page > 1"
                    class="activity-table-footer"
                >
                    <div class="activity-page-summary">
                        Showing
                        <strong>
                            {{ visibleFrom }}
                        </strong>
                        –
                        <strong>
                            {{ visibleTo }}
                        </strong>
                        of
                        <strong>
                            {{ totalActivities }}
                        </strong>
                    </div>

                    <div class="activity-pagination">
                        <button
                            class="pagination-btn"
                            type="button"
                            :disabled="currentPage <= 1"
                            @click="previousPage"
                        >
                            <Icon name="chevron-left" :size="13" />
                        </button>

                        <button
                            v-for="pageNumber in pageNumbers"
                            :key="pageNumber"
                            class="pagination-btn"
                            :class="{
                                active: pageNumber === currentPage,
                            }"
                            type="button"
                            @click="goToPage(pageNumber)"
                        >
                            {{ pageNumber }}
                        </button>

                        <button
                            class="pagination-btn"
                            type="button"
                            :disabled="currentPage >= pagination.last_page"
                            @click="nextPage"
                        >
                            <Icon name="chevron-right" :size="13" />
                        </button>
                    </div>

                    <select
                        :value="String(perPage)"
                        class="pagination-size"
                        aria-label="Rows per page"
                        @change="
                            changePerPage(
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="10">10 / page</option>

                        <option value="25">25 / page</option>

                        <option value="50">50 / page</option>

                        <option value="100">100 / page</option>
                    </select>
                </footer>
            </section>

            <!-- ============================================================ -->
            <!-- Footer                                                        -->
            <!-- ============================================================ -->

            <footer class="activity-footer">
                <div>
                    <span class="footer-status-dot"></span>

                    Authorization is enforced server-side
                </div>

                <span> Activity logs are immutable and read-only. </span>
            </footer>
        </div>

        <!-- ================================================================ -->
        <!-- Activity Details Modal                                            -->
        <!-- ================================================================ -->

        <Transition name="modal">
            <div
                v-if="showDetailsModal && selectedLog"
                class="modal-backdrop"
                @click.self="closeDetails"
            >
                <section
                    class="activity-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="activity-modal-title"
                >
                    <header class="activity-modal-header">
                        <div>
                            <span class="modal-kicker"> System Audit </span>

                            <h2 id="activity-modal-title">Activity Details</h2>

                            <p>Immutable audit record #{{ selectedLog.id }}</p>
                        </div>

                        <button
                            class="icon-only-btn"
                            type="button"
                            aria-label="Close"
                            @click="closeDetails"
                        >
                            <span class="ui-glyph"> × </span>
                        </button>
                    </header>

                    <div class="activity-modal-body">
                        <!-- Activity hero -->

                        <div class="activity-detail-hero">
                            <div
                                class="activity-detail-avatar"
                                :class="{
                                    system: selectedLog.actor_type === 'system',
                                }"
                            >
                                <Icon
                                    :name="
                                        selectedLog.actor_type === 'system'
                                            ? 'developer'
                                            : 'users'
                                    "
                                    :size="20"
                                />
                            </div>

                            <div>
                                <div class="activity-detail-title-row">
                                    <h3>
                                        {{ selectedLog.description }}
                                    </h3>

                                    <span class="activity-badge module">
                                        {{ moduleLabel(selectedLog.module) }}
                                    </span>
                                </div>

                                <p>
                                    {{ formatDateTime(selectedLog.created_at) }}
                                </p>
                            </div>
                        </div>

                        <!-- Metadata grid -->

                        <div class="activity-detail-grid">
                            <article>
                                <span> Actor </span>

                                <strong>
                                    {{ actorName(selectedLog) }}
                                </strong>

                                <small>
                                    {{ actorEmail(selectedLog) }}
                                </small>
                            </article>

                            <article>
                                <span> Actor type </span>

                                <strong>
                                    {{
                                        selectedLog.actor_type === 'system'
                                            ? 'System'
                                            : 'User'
                                    }}
                                </strong>

                                <small>
                                    {{
                                        selectedLog.actor_user_id
                                            ? `User #${selectedLog.actor_user_id}`
                                            : 'No user actor'
                                    }}
                                </small>
                            </article>

                            <article>
                                <span> Action </span>

                                <strong>
                                    {{ actionLabel(selectedLog.action) }}
                                </strong>

                                <small>
                                    {{ selectedLog.action }}
                                </small>
                            </article>

                            <article>
                                <span> Subject </span>

                                <strong>
                                    {{ subjectLabel(selectedLog) }}
                                </strong>

                                <small>
                                    {{
                                        selectedLog.subject_type || 'No subject'
                                    }}
                                </small>
                            </article>

                            <article>
                                <span> IP address </span>

                                <strong class="mono">
                                    {{ selectedLog.ip_address || '—' }}
                                </strong>

                                <small> Client address </small>
                            </article>

                            <article>
                                <span> Request ID </span>

                                <strong class="mono request-id">
                                    {{ selectedLog.request_id || '—' }}
                                </strong>

                                <small> Request trace </small>
                            </article>
                        </div>

                        <!-- Old / new values -->

                        <div
                            v-if="
                                hasSnapshot(selectedLog.old_values) ||
                                hasSnapshot(selectedLog.new_values)
                            "
                            class="snapshot-grid"
                        >
                            <article
                                v-if="hasSnapshot(selectedLog.old_values)"
                                class="snapshot-card"
                            >
                                <header>
                                    <div>
                                        <span> Before </span>

                                        <strong> Old values </strong>
                                    </div>
                                </header>

                                <pre>{{
                                    jsonPreview(selectedLog.old_values)
                                }}</pre>
                            </article>

                            <article
                                v-if="hasSnapshot(selectedLog.new_values)"
                                class="snapshot-card"
                            >
                                <header>
                                    <div>
                                        <span> After </span>

                                        <strong> New values </strong>
                                    </div>
                                </header>

                                <pre>{{
                                    jsonPreview(selectedLog.new_values)
                                }}</pre>
                            </article>
                        </div>

                        <!-- User agent -->

                        <div
                            v-if="selectedLog.user_agent"
                            class="activity-user-agent"
                        >
                            <span> User agent </span>

                            <code>
                                {{ selectedLog.user_agent }}
                            </code>
                        </div>
                    </div>

                    <footer class="activity-modal-footer">
                        <div>
                            <span class="footer-status-dot"></span>

                            Read-only audit record
                        </div>

                        <button
                            class="activity-btn activity-btn-secondary"
                            type="button"
                            @click="closeDetails"
                        >
                            Close
                        </button>
                    </footer>
                </section>
            </div>
        </Transition>
    </ErpLayout>
</template>

<style scoped>
/*
|--------------------------------------------------------------------------
| Activity page
|--------------------------------------------------------------------------
*/

.activity-page {
    --activity-radius: 14px;
    --activity-radius-sm: 10px;
    --activity-accent: var(--primary);
    --activity-success: var(--success);

    width: 100%;
    min-width: 0;
    min-height: 100%;
    box-sizing: border-box;
    padding: 0;
    color: var(--text-primary);
}

.activity-header,
.activity-title-row,
.activity-header-actions,
.activity-toolbar,
.activity-toolbar-actions,
.activity-toolbar-meta,
.activity-section-title,
.activity-detail-title-row,
.activity-modal-header,
.activity-modal-footer,
.activity-footer {
    display: flex;
    align-items: center;
}

.activity-header {
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 18px;
}

.activity-header-copy {
    min-width: 0;
}

.activity-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
}

.activity-breadcrumb .is-current {
    color: var(--text-secondary);
}

.activity-breadcrumb-separator {
    color: var(--border-strong);
}

.activity-title-row {
    gap: 12px;
}

.activity-title-icon,
.activity-stat-icon,
.activity-section-icon,
.activity-empty-icon,
.activity-detail-avatar,
.actor-avatar {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
}

.activity-title-icon {
    width: 42px;
    height: 42px;
    border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
    border-radius: 12px;
    background: color-mix(in srgb, var(--primary) 10%, var(--surface));
    color: var(--primary);
    box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 9%, transparent);
}

.activity-header h1 {
    margin: 0;
    font-size: 24px;
    line-height: 1.15;
    letter-spacing: -0.025em;
    font-weight: 750;
}

.activity-header p {
    margin: 5px 0 0;
    color: var(--text-secondary);
    font-size: 12px;
}

.activity-header-actions {
    flex-shrink: 0;
    gap: 8px;
}

/*
|--------------------------------------------------------------------------
| Buttons
|--------------------------------------------------------------------------
*/

.activity-btn {
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

.activity-btn:hover:not(:disabled) {
    border-color: var(--border-strong);
    color: var(--text-primary);
    transform: translateY(-1px);
}

.activity-btn:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.activity-btn-primary {
    border-color: color-mix(in srgb, var(--primary) 80%, var(--border));
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--primary) 92%, white),
        var(--primary)
    );
    color: #fff;
    box-shadow: 0 9px 22px color-mix(in srgb, var(--primary) 17%, transparent);
}

.activity-btn-primary:hover:not(:disabled) {
    border-color: var(--primary);
    box-shadow: 0 12px 26px color-mix(in srgb, var(--primary) 22%, transparent);
}

.activity-btn-secondary {
    background: var(--surface);
}

/*
|--------------------------------------------------------------------------
| Feedback
|--------------------------------------------------------------------------
*/

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
    animation: activity-enter 280ms ease both;
}

.operation-message > span {
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

.operation-message.error {
    border-color: color-mix(in srgb, #dc4f4f 28%, var(--border));
    background: color-mix(in srgb, #dc4f4f 7%, var(--surface));
    color: #dc4f4f;
}

/*
|--------------------------------------------------------------------------
| KPI
|--------------------------------------------------------------------------
*/

.activity-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}

.activity-stat-card {
    display: flex;
    align-items: center;
    gap: 11px;
    min-height: 78px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: color-mix(in srgb, var(--surface) 96%, var(--surface-muted));
    box-shadow: var(--shadow);
    animation: activity-enter 480ms cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: calc(var(--card-index, 0) * 55ms + 80ms);
    transition:
        transform 220ms ease,
        border-color 220ms ease,
        box-shadow 220ms ease;
}

.activity-stat-card:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--primary) 28%, var(--border));
    box-shadow: 0 16px 34px color-mix(in srgb, var(--primary) 9%, transparent);
}

.activity-stat-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    transition: transform 220ms ease;
}

.activity-stat-card:hover .activity-stat-icon {
    transform: scale(1.08) rotate(-3deg);
}

.activity-stat-icon.green {
    background: color-mix(in srgb, var(--success) 12%, var(--surface));
    color: var(--success);
}

.activity-stat-icon.blue {
    background: color-mix(in srgb, #3976e8 11%, var(--surface));
    color: #3976e8;
}

.activity-stat-icon.purple {
    background: color-mix(in srgb, #8250e8 11%, var(--surface));
    color: #8250e8;
}

.activity-stat-content {
    min-width: 0;
}

.activity-stat-card span,
.activity-stat-card small {
    display: block;
    color: var(--text-muted);
    font-size: 10px;
}

.activity-stat-card strong {
    display: block;
    margin: 1px 0;
    color: var(--text-primary);
    font-size: 21px;
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.activity-stat-card small {
    color: var(--text-muted);
}

/*
|--------------------------------------------------------------------------
| Toolbar
|--------------------------------------------------------------------------
*/

.activity-toolbar-card {
    margin-bottom: 14px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: var(--activity-radius);
    background: var(--surface);
    box-shadow: var(--shadow);
}

.activity-toolbar {
    justify-content: space-between;
    gap: 12px;
}

.activity-search {
    display: flex;
    align-items: center;
    gap: 8px;
    width: min(520px, 100%);
    height: 40px;
    padding: 0 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface-muted);
    color: var(--text-muted);
    transition:
        border-color 180ms ease,
        box-shadow 180ms ease;
}

.activity-search:focus-within {
    border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 8%, transparent);
}

.activity-search input {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text-primary);
    font-size: 11px;
}

.activity-search input::placeholder {
    color: var(--text-muted);
}

.activity-toolbar-actions {
    justify-content: flex-end;
    gap: 7px;
}

.activity-select {
    min-width: 130px;
    height: 40px;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: 9px;
    outline: 0;
    background: var(--surface);
    color: var(--text-secondary);
    font-size: 11px;
    cursor: pointer;
}

.activity-select:focus {
    border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
}

.activity-toolbar-meta {
    justify-content: space-between;
    margin-top: 8px;
    color: var(--text-muted);
    font-size: 9px;
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

/*
|--------------------------------------------------------------------------
| Table card
|--------------------------------------------------------------------------
*/

.activity-table-card {
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: var(--activity-radius);
    background: var(--surface);
    box-shadow: var(--shadow);
    animation: activity-enter 500ms cubic-bezier(0.22, 1, 0.36, 1) 220ms both;
}

.activity-table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 16px 18px 14px;
    border-bottom: 1px solid var(--border);
}

.activity-section-title {
    gap: 10px;
}

.activity-section-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: color-mix(in srgb, var(--primary) 10%, var(--surface));
    color: var(--primary);
}

.activity-section-title h2 {
    margin: 0;
    color: var(--text-primary);
    font-size: 14px;
    letter-spacing: -0.01em;
}

.activity-section-title p {
    margin: 3px 0 0;
    color: var(--text-secondary);
    font-size: 10px;
}

.activity-record-count {
    color: var(--text-muted);
    font-size: 10px;
}

/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.activity-table-wrap {
    overflow-x: auto;
}

.activity-table {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
}

.activity-table th {
    padding: 11px 12px;
    border-bottom: 1px solid var(--border);
    background: var(--surface-muted);
    color: var(--text-muted);
    font-size: 8px;
    font-weight: 750;
    letter-spacing: 0.055em;
    text-align: left;
    text-transform: uppercase;
    white-space: nowrap;
}

.activity-table td {
    padding: 11px 12px;
    border-bottom: 1px solid var(--border);
    color: var(--text-secondary);
    font-size: 10px;
    vertical-align: middle;
}

.activity-table tbody tr {
    transition: background-color 160ms ease;
}

.activity-table tbody tr:hover {
    background: color-mix(in srgb, var(--surface-muted) 65%, transparent);
}

.activity-table tbody tr:last-child td {
    border-bottom: 0;
}

.activity-description-cell {
    width: 32%;
    min-width: 260px;
}

.activity-description {
    min-width: 0;
}

.activity-description strong {
    display: block;
    overflow: hidden;
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 700;
    line-height: 1.45;
    text-overflow: ellipsis;
}

.activity-description span {
    display: block;
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 8px;
}

.activity-actor {
    display: flex;
    align-items: center;
    gap: 8px;
}

.actor-avatar {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: var(--surface-soft);
    color: var(--text-secondary);
}

.actor-avatar.system {
    background: color-mix(in srgb, var(--success) 11%, var(--surface));
    color: var(--success);
}

.actor-copy {
    min-width: 0;
}

.actor-copy strong,
.actor-copy small {
    display: block;
}

.actor-copy strong {
    overflow: hidden;
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.actor-copy small {
    max-width: 150px;
    margin-top: 2px;
    overflow: hidden;
    color: var(--text-muted);
    font-size: 8px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.activity-badge {
    display: inline-flex;
    align-items: center;
    min-height: 20px;
    padding: 0 7px;
    border-radius: 999px;
    font-size: 8px;
    font-weight: 750;
    white-space: nowrap;
}

.activity-badge.module {
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
    color: var(--primary);
}

.activity-badge.action {
    background: var(--surface-muted);
    color: var(--text-secondary);
}

.activity-ip {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    color: var(--text-secondary);
    font-size: 9px;
}

.activity-time strong,
.activity-time small {
    display: block;
}

.activity-time strong {
    color: var(--text-secondary);
    font-size: 9px;
    font-weight: 650;
    white-space: nowrap;
}

.activity-time small {
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 8px;
    white-space: nowrap;
}

.activity-action-column {
    width: 52px;
    text-align: right !important;
}

.activity-view-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-secondary);
    cursor: pointer;
    transition:
        transform 180ms ease,
        border-color 180ms ease,
        color 180ms ease,
        background-color 180ms ease;
}

.activity-view-btn:hover {
    border-color: color-mix(in srgb, var(--primary) 35%, var(--border));
    background: color-mix(in srgb, var(--primary) 7%, var(--surface));
    color: var(--primary);
    transform: translateY(-1px);
}

/*
|--------------------------------------------------------------------------
| Loading / Empty
|--------------------------------------------------------------------------
*/

.activity-loading {
    display: flex;
    min-height: 260px;
    align-items: center;
    justify-content: center;
    gap: 9px;
    color: var(--text-secondary);
    font-size: 11px;
}

.loading-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid color-mix(in srgb, var(--primary) 20%, transparent);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: activity-spin 750ms linear infinite;
}

.activity-empty {
    display: grid;
    justify-items: center;
    min-height: 300px;
    padding: 40px;
    text-align: center;
}

.activity-empty-icon {
    width: 52px;
    height: 52px;
    margin-bottom: 11px;
    border-radius: 14px;
    background: var(--surface-muted);
    color: var(--text-secondary);
}

.activity-empty h3 {
    margin: 0;
    color: var(--text-primary);
    font-size: 14px;
}

.activity-empty p {
    max-width: 400px;
    margin: 5px 0 13px;
    color: var(--text-secondary);
    font-size: 10px;
    line-height: 1.5;
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

.activity-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 14px;
    border-top: 1px solid var(--border);
    background: var(--surface-muted);
}

.activity-page-summary {
    color: var(--text-muted);
    font-size: 9px;
}

.activity-page-summary strong {
    color: var(--text-secondary);
}

.activity-pagination {
    display: flex;
    align-items: center;
    gap: 4px;
}

.pagination-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 29px;
    height: 29px;
    padding: 0 7px;
    border: 1px solid var(--border);
    border-radius: 7px;
    background: var(--surface);
    color: var(--text-secondary);
    font-size: 9px;
    cursor: pointer;
    transition:
        border-color 180ms ease,
        background-color 180ms ease,
        color 180ms ease;
}

.pagination-btn:hover:not(:disabled) {
    border-color: var(--border-strong);
    color: var(--text-primary);
}

.pagination-btn.active {
    border-color: var(--primary);
    background: var(--primary);
    color: #fff;
}

.pagination-btn:disabled {
    cursor: not-allowed;
    opacity: 0.4;
}

.pagination-size {
    height: 29px;
    padding: 0 8px;
    border: 1px solid var(--border);
    border-radius: 7px;
    outline: 0;
    background: var(--surface);
    color: var(--text-secondary);
    font-size: 9px;
}

/*
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
*/

.activity-footer {
    justify-content: space-between;
    gap: 12px;
    padding: 9px 2px 0;
    color: var(--text-muted);
    font-size: 9px;
}

.footer-status-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    margin-right: 5px;
    border-radius: 50%;
    background: var(--success);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--success) 10%, transparent);
}

/*
|--------------------------------------------------------------------------
| Modal
|--------------------------------------------------------------------------
*/

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

.activity-modal {
    width: min(760px, 100%);
    max-height: min(800px, calc(100vh - 40px));
    overflow: auto;
    border: 1px solid var(--border);
    border-radius: 15px;
    background: var(--surface);
    box-shadow: 0 24px 70px rgb(11 27 44 / 20%);
}

.activity-modal-header {
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
    padding: 18px;
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

.activity-modal-header h2 {
    margin: 0;
    color: var(--text-primary);
    font-size: 17px;
}

.activity-modal-header p {
    margin: 4px 0 0;
    color: var(--text-secondary);
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

.icon-only-btn:hover {
    border-color: var(--border-strong);
    color: var(--text-primary);
    transform: translateY(-1px);
}

.activity-modal-body {
    display: grid;
    gap: 16px;
    padding: 18px;
}

.activity-detail-hero {
    display: flex;
    align-items: flex-start;
    gap: 11px;
}

.activity-detail-avatar {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: var(--surface-muted);
    color: var(--text-secondary);
}

.activity-detail-avatar.system {
    background: color-mix(in srgb, var(--success) 12%, var(--surface));
    color: var(--success);
}

.activity-detail-title-row {
    flex-wrap: wrap;
    gap: 7px;
}

.activity-detail-title-row h3 {
    margin: 0;
    color: var(--text-primary);
    font-size: 14px;
    line-height: 1.4;
}

.activity-detail-hero p {
    margin: 5px 0 0;
    color: var(--text-secondary);
    font-size: 9px;
}

.activity-detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.activity-detail-grid article {
    min-width: 0;
    padding: 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface-muted);
}

.activity-detail-grid span,
.activity-detail-grid strong,
.activity-detail-grid small {
    display: block;
}

.activity-detail-grid span {
    color: var(--text-muted);
    font-size: 8px;
}

.activity-detail-grid strong {
    margin-top: 4px;
    overflow: hidden;
    color: var(--text-primary);
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.activity-detail-grid small {
    margin-top: 3px;
    overflow: hidden;
    color: var(--text-muted);
    font-size: 8px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.request-id {
    font-size: 8px !important;
}

.snapshot-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.snapshot-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface-muted);
}

.snapshot-card header {
    padding: 10px 11px;
    border-bottom: 1px solid var(--border);
}

.snapshot-card header span,
.snapshot-card header strong {
    display: block;
}

.snapshot-card header span {
    color: var(--text-muted);
    font-size: 8px;
}

.snapshot-card header strong {
    margin-top: 2px;
    color: var(--text-primary);
    font-size: 10px;
}

.snapshot-card pre {
    max-height: 220px;
    margin: 0;
    overflow: auto;
    padding: 11px;
    color: var(--text-secondary);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 8px;
    line-height: 1.55;
    white-space: pre-wrap;
    word-break: break-word;
}

.activity-user-agent {
    padding: 10px 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface-muted);
}

.activity-user-agent span {
    display: block;
    margin-bottom: 5px;
    color: var(--text-muted);
    font-size: 8px;
}

.activity-user-agent code {
    display: block;
    overflow: auto;
    color: var(--text-secondary);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 8px;
    line-height: 1.5;
}

.activity-modal-footer {
    justify-content: space-between;
    gap: 10px;
    padding: 12px 18px;
    border-top: 1px solid var(--border);
    background: var(--surface-muted);
    color: var(--text-muted);
    font-size: 9px;
}

.ui-glyph {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1em;
    line-height: 1;
    font-weight: 750;
}

/*
|--------------------------------------------------------------------------
| Transitions
|--------------------------------------------------------------------------
*/

.message-enter-active,
.message-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}

.message-enter-from,
.message-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity 200ms ease;
}

.modal-enter-active .activity-modal,
.modal-leave-active .activity-modal {
    transition:
        transform 240ms cubic-bezier(0.22, 1, 0.36, 1),
        opacity 200ms ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .activity-modal,
.modal-leave-to .activity-modal {
    opacity: 0;
    transform: translateY(10px) scale(0.985);
}

/*
|--------------------------------------------------------------------------
| Utility
|--------------------------------------------------------------------------
*/

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.is-spinning {
    display: inline-block;
    animation: activity-spin 750ms linear infinite;
}

/*
|--------------------------------------------------------------------------
| Animations
|--------------------------------------------------------------------------
*/

@keyframes activity-enter {
    from {
        opacity: 0;
        transform: translateY(8px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes activity-spin {
    to {
        transform: rotate(360deg);
    }
}

/*
|--------------------------------------------------------------------------
| Focus
|--------------------------------------------------------------------------
*/

.activity-btn:focus-visible,
.activity-select:focus-visible,
.activity-view-btn:focus-visible,
.pagination-btn:focus-visible,
.pagination-size:focus-visible,
.icon-only-btn:focus-visible,
.toolbar-text-btn:focus-visible {
    outline: 2px solid color-mix(in srgb, var(--primary) 55%, transparent);
    outline-offset: 2px;
}

/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 1100px) {
    .activity-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .activity-search {
        width: 100%;
    }

    .activity-toolbar-actions {
        justify-content: flex-end;
    }

    .activity-detail-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 820px) {
    .activity-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .activity-header-actions {
        width: 100%;
    }

    .activity-header-actions .activity-btn {
        width: 100%;
    }

    .activity-table-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .activity-pagination {
        width: 100%;
        justify-content: center;
    }

    .pagination-size {
        width: 100%;
    }
}

@media (max-width: 700px) {
    .activity-stat-grid {
        grid-template-columns: 1fr;
    }

    .activity-toolbar-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .activity-select,
    .activity-toolbar-actions .activity-btn {
        width: 100%;
    }

    .activity-table-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .activity-detail-grid,
    .snapshot-grid {
        grid-template-columns: 1fr;
    }

    .activity-modal {
        max-height: calc(100vh - 24px);
    }

    .modal-backdrop {
        padding: 12px;
    }
}

@media (max-width: 560px) {
    .activity-page {
        padding: 16px 12px 24px;
    }

    .activity-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .activity-modal-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .activity-modal-footer .activity-btn {
        width: 100%;
    }
}

/*
|--------------------------------------------------------------------------
| Reduced motion
|--------------------------------------------------------------------------
*/

@media (prefers-reduced-motion: reduce) {
    .activity-stat-card,
    .activity-table-card,
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
