<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface Props {
    userName?: string;
    userEmail?: string;
}

const props = withDefaults(defineProps<Props>(), {
    userName: '',
    userEmail: '',
});

type Brand = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    logo_path: string | null;
    website_url: string | null;
    status: 'active' | 'inactive' | 'archived';
    sort_order: number;
    created_at: string;
    updated_at: string;
};
type ApiResponse<T> = {
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
};
type FormState = {
    name: string;
    slug: string;
    description: string;
    logo_path: string;
    website_url: string;
    status: Brand['status'];
    sort_order: number;
};
const brands = ref<Brand[]>([]);
const loading = ref(false);
const saving = ref(false);
const search = ref('');
const statusFilter = ref('all');
const showModal = ref(false);
const deleteTarget = ref<Brand | null>(null);
const deleting = ref(false);
const editing = ref<Brand | null>(null);
const message = ref('');
const messageType = ref<'success' | 'error'>('success');
const errors = ref<Record<string, string>>({});
function emptyForm(): FormState {
    return {
        name: '',
        slug: '',
        description: '',
        logo_path: '',
        website_url: '',
        status: 'active',
        sort_order: 0,
    };
}
const form = reactive<FormState>(emptyForm());
const filtered = computed(() =>
    brands.value.filter(
        (x) =>
            (!search.value.trim() ||
                `${x.name} ${x.slug} ${x.website_url ?? ''}`
                    .toLowerCase()
                    .includes(search.value.trim().toLowerCase())) &&
            (statusFilter.value === 'all' || x.status === statusFilter.value),
    ),
);
const activeCount = computed(
    () => brands.value.filter((x) => x.status === 'active').length,
);
const withWebsite = computed(
    () => brands.value.filter((x) => !!x.website_url).length,
);
function csrfHeaders(): Record<string, string> {
    const token =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    };
}
async function readError(
    response: Response,
    fallback: string,
): Promise<string> {
    const p = (await response.json().catch(() => ({}))) as ApiResponse<unknown>;
    if (p.errors)
        errors.value = Object.fromEntries(
            Object.entries(p.errors).map(([k, v]) => [
                k,
                v[0] ?? 'Invalid value.',
            ]),
        );
    return p.message ?? Object.values(p.errors ?? {}).flat()[0] ?? fallback;
}
async function load(): Promise<void> {
    loading.value = true;
    try {
        const r = await fetch('/api/brands', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!r.ok)
            throw new Error(await readError(r, 'Could not load brands.'));
        brands.value = ((await r.json()) as ApiResponse<Brand[]>).data ?? [];
    } catch (e) {
        messageType.value = 'error';
        message.value =
            e instanceof Error ? e.message : 'Could not load brands.';
    } finally {
        loading.value = false;
    }
}
function openCreate(): void {
    editing.value = null;
    Object.assign(form, emptyForm());
    errors.value = {};
    message.value = '';
    showModal.value = true;
}
function openEdit(x: Brand): void {
    editing.value = x;
    Object.assign(form, {
        name: x.name,
        slug: x.slug,
        description: x.description ?? '',
        logo_path: x.logo_path ?? '',
        website_url: x.website_url ?? '',
        status: x.status,
        sort_order: x.sort_order,
    });
    errors.value = {};
    message.value = '';
    showModal.value = true;
}
async function save(): Promise<void> {
    saving.value = true;
    errors.value = {};
    const payload = {
        ...form,
        slug: form.slug.trim() || null,
        description: form.description.trim() || null,
        logo_path: form.logo_path.trim() || null,
        website_url: form.website_url.trim() || null,
        sort_order: Number(form.sort_order) || 0,
    };
    try {
        const r = await fetch(
            editing.value ? `/api/brands/${editing.value.id}` : '/api/brands',
            {
                method: editing.value ? 'PUT' : 'POST',
                headers: csrfHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            },
        );
        if (!r.ok) throw new Error(await readError(r, 'Could not save brand.'));
        const p = (await r.json()) as ApiResponse<Brand>;
        messageType.value = 'success';
        message.value = p.message ?? 'Brand saved successfully.';
        showModal.value = false;
        await load();
    } catch (e) {
        messageType.value = 'error';
        message.value =
            e instanceof Error ? e.message : 'Could not save brand.';
    } finally {
        saving.value = false;
    }
}
function remove(x: Brand): void {
    deleteTarget.value = x;
    message.value = '';
}
async function confirmDelete(): Promise<void> {
    const x = deleteTarget.value;
    if (!x || deleting.value) return;
    deleting.value = true;
    try {
        const r = await fetch(`/api/brands/${x.id}`, {
            method: 'DELETE',
            headers: csrfHeaders(),
            credentials: 'same-origin',
        });
        if (!r.ok)
            throw new Error(await readError(r, 'Could not delete brand.'));
        const p = (await r.json()) as ApiResponse<unknown>;
        deleteTarget.value = null;
        messageType.value = 'success';
        message.value = p.message ?? 'Brand deleted.';
        await load();
    } catch (e) {
        messageType.value = 'error';
        message.value =
            e instanceof Error ? e.message : 'Could not delete brand.';
    } finally {
        deleting.value = false;
    }
}
function statusClass(s: string): string {
    return `pill-${s}`;
}
onMounted(load);
</script>
<template>
    <Head title="Brands" />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail"
        ><main class="master-page">
            <header class="page-header">
                <div class="page-heading">
                    <div class="breadcrumb">
                        <span>Products</span
                        ><Icon name="chevron-right" :size="12" /><strong
                            >Brands</strong
                        >
                    </div>
                    <div class="title-row">
                        <div class="page-title-icon">
                            <Icon name="badge-check" :size="22" />
                        </div>
                        <div>
                            <h1>Brands</h1>
                            <p>
                                Maintain brand identity, web presence and
                                product associations.
                            </p>
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary" @click="openCreate">
                    <Icon name="plus" :size="15" /> Add Brand
                </button>
            </header>
            <div
                v-if="message"
                class="feedback"
                :class="messageType"
                role="status"
            >
                {{ message }}
            </div>
            <section class="stats">
                <article class="stat-card">
                    <div class="stat-label">Total brands</div>
                    <div class="stat-value">{{ brands.length }}</div>
                    <div class="stat-note">All non-deleted brands</div>
                </article>
                <article class="stat-card">
                    <div class="stat-label">Active brands</div>
                    <div class="stat-value">{{ activeCount }}</div>
                    <div class="stat-note">
                        Available for product assignment
                    </div>
                </article>
                <article class="stat-card">
                    <div class="stat-label">With website</div>
                    <div class="stat-value">{{ withWebsite }}</div>
                    <div class="stat-note">Brands with an external URL</div>
                </article>
            </section>
            <section class="panel">
                <div class="toolbar">
                    <div class="search-wrap">
                        <span class="search-icon"
                            ><Icon name="search" :size="15" /></span
                        ><input
                            v-model="search"
                            type="search"
                            placeholder="Search brand, slug or website..."
                        />
                    </div>
                    <div class="filters">
                        <select
                            v-model="statusFilter"
                            aria-label="Filter status"
                        >
                            <option value="all">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option></select
                        ><button class="btn" @click="load">
                            <Icon name="refresh-cw" :size="14" /> Refresh
                        </button>
                    </div>
                </div>
                <div v-if="loading" class="loading">Loading brands…</div>
                <div v-else-if="!filtered.length" class="empty">
                    No brands found. Change the filters or add a brand.
                </div>
                <div v-else class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Brand</th>
                                <th>Website</th>
                                <th>Status</th>
                                <th>Sort order</th>
                                <th>Created</th>
                                <th style="text-align: right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="x in filtered" :key="x.id">
                                <td>
                                    <span class="name-cell">{{ x.name }}</span
                                    ><span class="subtext"
                                        >{{ x.slug
                                        }}<template v-if="x.logo_path">
                                            · Logo: {{ x.logo_path }}</template
                                        ></span
                                    >
                                </td>
                                <td>
                                    <a
                                        v-if="x.website_url"
                                        :href="x.website_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        >{{ x.website_url }}</a
                                    ><span v-else>—</span>
                                </td>
                                <td>
                                    <span
                                        class="pill"
                                        :class="statusClass(x.status)"
                                        >{{ x.status }}</span
                                    >
                                </td>
                                <td>{{ x.sort_order }}</td>
                                <td>
                                    {{
                                        x.created_at
                                            ? new Date(
                                                  x.created_at,
                                              ).toLocaleDateString()
                                            : '—'
                                    }}
                                </td>
                                <td>
                                    <div class="actions">
                                        <button
                                            class="icon-btn"
                                            title="Edit brand"
                                            aria-label="Edit brand"
                                            @click="openEdit(x)"
                                        >
                                            <Icon
                                                name="pencil"
                                                :size="14"
                                            /></button
                                        ><button
                                            class="icon-btn"
                                            title="Delete brand"
                                            aria-label="Delete brand"
                                            @click="remove(x)"
                                        >
                                            <Icon name="trash-2" :size="14" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <div
                v-if="showModal"
                class="modal-backdrop"
                @click.self="showModal = false"
            >
                <section
                    class="modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="brand-modal-title"
                >
                    <header class="modal-header">
                        <div>
                            <h2 id="brand-modal-title">
                                {{ editing ? 'Edit brand' : 'Create brand' }}
                            </h2>
                            <p>
                                Slug is generated automatically when left blank.
                            </p>
                        </div>
                        <button
                            class="icon-btn"
                            aria-label="Close dialog"
                            @click="showModal = false"
                        >
                            <Icon name="x" :size="16" />
                        </button>
                    </header>
                    <form @submit.prevent="save">
                        <div class="modal-body">
                            <div class="form-grid">
                                <div class="field">
                                    <label for="brand-name">Name *</label
                                    ><input
                                        id="brand-name"
                                        v-model="form.name"
                                        maxlength="150"
                                        required
                                    /><span
                                        v-if="errors.name"
                                        class="field-error"
                                        >{{ errors.name }}</span
                                    >
                                </div>
                                <div class="field">
                                    <label for="brand-slug">Slug</label
                                    ><input
                                        id="brand-slug"
                                        v-model="form.slug"
                                        maxlength="180"
                                        placeholder="auto-generated-from-name"
                                    /><span
                                        v-if="errors.slug"
                                        class="field-error"
                                        >{{ errors.slug }}</span
                                    >
                                </div>
                                <div class="field">
                                    <label for="brand-website"
                                        >Website URL</label
                                    ><input
                                        id="brand-website"
                                        v-model="form.website_url"
                                        type="url"
                                        maxlength="500"
                                        placeholder="https://example.com"
                                    /><span
                                        v-if="errors.website_url"
                                        class="field-error"
                                        >{{ errors.website_url }}</span
                                    >
                                </div>
                                <div class="field">
                                    <label for="brand-logo">Logo path</label
                                    ><input
                                        id="brand-logo"
                                        v-model="form.logo_path"
                                        maxlength="500"
                                        placeholder="brands/example.svg"
                                    /><span
                                        v-if="errors.logo_path"
                                        class="field-error"
                                        >{{ errors.logo_path }}</span
                                    >
                                </div>
                                <div class="field">
                                    <label for="brand-status">Status</label
                                    ><select
                                        id="brand-status"
                                        v-model="form.status"
                                    >
                                        <option value="active">Active</option>
                                        <option value="inactive">
                                            Inactive
                                        </option>
                                        <option value="archived">
                                            Archived
                                        </option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="brand-sort">Sort order</label
                                    ><input
                                        id="brand-sort"
                                        v-model.number="form.sort_order"
                                        type="number"
                                        min="0"
                                        step="1"
                                    />
                                </div>
                                <div class="field full">
                                    <label for="brand-description"
                                        >Description</label
                                    ><textarea
                                        id="brand-description"
                                        v-model="form.description"
                                        rows="3"
                                    />
                                </div>
                            </div>
                        </div>
                        <footer class="modal-footer">
                            <button
                                class="btn"
                                type="button"
                                @click="showModal = false"
                            >
                                Cancel</button
                            ><button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="saving"
                            >
                                {{
                                    saving
                                        ? 'Saving…'
                                        : editing
                                          ? 'Save changes'
                                          : 'Create brand'
                                }}
                            </button>
                        </footer>
                    </form>
                </section>
            </div>
            <div
                v-if="deleteTarget"
                class="delete-backdrop"
                @click.self="!deleting && (deleteTarget = null)"
                @keydown.esc="!deleting && (deleteTarget = null)"
            >
                <section
                    class="delete-dialog"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="delete-dialog-title"
                    aria-describedby="delete-dialog-description"
                >
                    <div class="delete-icon">
                        <Icon name="trash-2" :size="22" />
                    </div>
                    <div class="delete-copy">
                        <p class="delete-eyebrow">Confirm deletion</p>
                        <h2 id="delete-dialog-title">Delete this brand?</h2>
                        <p id="delete-dialog-description">
                            Please review the selected record before confirming.
                            This action cannot be undone.
                        </p>
                    </div>
                    <div class="delete-record">
                        <span class="record-label">Brand name</span
                        ><strong>{{ deleteTarget.name }}</strong
                        ><span class="record-meta">{{
                            deleteTarget.slug
                        }}</span>
                    </div>
                    <div class="delete-warning">
                        <Icon name="info" :size="15" /><span
                            >This will remove the brand from the active brand
                            list. Products linked to this brand may prevent
                            deletion.</span
                        >
                    </div>
                    <footer class="delete-actions">
                        <button
                            class="btn"
                            type="button"
                            :disabled="deleting"
                            @click="deleteTarget = null"
                        >
                            Cancel</button
                        ><button
                            class="btn btn-danger-solid"
                            type="button"
                            :disabled="deleting"
                            @click="confirmDelete"
                        >
                            <Icon
                                v-if="!deleting"
                                name="trash-2"
                                :size="14"
                            />{{ deleting ? 'Deleting…' : 'Delete brand' }}
                        </button>
                    </footer>
                </section>
            </div>
        </main></ErpLayout
    >
</template>

<style scoped>
/* Compact Madina Scale product-page design system */
.master-page {
    min-height: 100%;
    padding: 0;
    color: var(--text-primary);
}
.page-header,
.title-row,
.toolbar,
.filters,
.actions {
    display: flex;
    align-items: center;
}
.page-header {
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 18px;
}
.page-heading {
    min-width: 0;
}
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
    line-height: 1.2;
}
.breadcrumb strong {
    color: var(--text-secondary);
    font-weight: 600;
}
.title-row {
    gap: 12px;
}
.page-title-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
    border-radius: 12px;
    background: color-mix(in srgb, var(--primary) 10%, var(--surface));
    color: var(--primary);
    box-shadow: 0 5px 14px color-mix(in srgb, var(--primary) 7%, transparent);
}
h1 {
    margin: 0;
    color: var(--text-primary);
    font-size: 24px;
    line-height: 1.15;
    letter-spacing: -0.025em;
    font-weight: 750;
}
.page-heading p {
    margin: 5px 0 0;
    color: var(--text-secondary);
    font-size: 12px;
    line-height: 1.5;
}
.btn {
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
    white-space: nowrap;
    cursor: pointer;
    transition:
        border-color 0.16s ease,
        background 0.16s ease,
        color 0.16s ease,
        transform 0.16s ease;
}
.btn:hover:not(:disabled) {
    border-color: var(--border-strong);
    transform: translateY(-1px);
}
.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.btn-primary {
    border-color: color-mix(in srgb, var(--primary) 88%, transparent);
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--primary) 96%, white),
        color-mix(in srgb, var(--primary) 86%, black)
    );
    color: #fff;
    box-shadow: 0 5px 14px color-mix(in srgb, var(--primary) 15%, transparent);
}
.btn-primary:hover:not(:disabled) {
    box-shadow: 0 7px 18px color-mix(in srgb, var(--primary) 20%, transparent);
}
.btn-danger {
    color: #c24141;
}
.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}
.stat-card {
    padding: 13px 15px;
    border: 1px solid var(--border);
    border-radius: var(--product-radius, 14px);
    background: var(--surface);
}
.stat-label {
    margin-bottom: 5px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
}
.stat-value {
    color: var(--text-primary);
    font-size: 22px;
    font-weight: 750;
    line-height: 1.15;
    letter-spacing: -0.025em;
}
.stat-note {
    margin-top: 4px;
    color: var(--text-secondary);
    font-size: 10px;
    line-height: 1.4;
}
.panel {
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: var(--product-radius, 14px);
    background: var(--surface);
}
.toolbar {
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid var(--border);
}
.search-wrap {
    position: relative;
    flex: 1;
    max-width: 390px;
    min-width: 180px;
}
.search-wrap input {
    box-sizing: border-box;
    width: 100%;
    min-height: 36px;
    padding: 0 11px 0 34px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-primary);
    font: inherit;
    font-size: 12px;
    outline: none;
}
.search-wrap input:focus,
.field input:focus,
.field select:focus,
.field textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 12%, transparent);
}
.search-icon {
    position: absolute;
    left: 11px;
    top: 50%;
    display: inline-flex;
    transform: translateY(-50%);
    color: var(--text-muted);
}
.filters {
    gap: 7px;
}
.filters select {
    min-height: 36px;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-primary);
    font: inherit;
    font-size: 12px;
}
.table-wrap {
    overflow: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}
th {
    padding: 11px 14px;
    background: var(--surface-muted);
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-align: left;
    text-transform: uppercase;
    white-space: nowrap;
}
td {
    padding: 11px 14px;
    border-top: 1px solid var(--border);
    color: var(--text-secondary);
    font-size: 12px;
    vertical-align: middle;
}
tbody tr {
    transition: background 0.15s ease;
}
tbody tr:hover {
    background: color-mix(in srgb, var(--surface-muted) 72%, transparent);
}
.name-cell {
    display: block;
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 650;
}
.subtext {
    display: block;
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 10px;
    overflow-wrap: anywhere;
}
td a {
    color: var(--primary);
    text-decoration: none;
}
td a:hover {
    text-decoration: underline;
}
.pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 4px 8px;
    font-size: 10px;
    font-weight: 700;
    text-transform: capitalize;
}
.pill-active,
.pill-visible {
    background: #e8f8ef;
    color: #177447;
}
.pill-inactive {
    background: #fff4df;
    color: #946200;
}
.pill-archived {
    background: #f0edf8;
    color: #6c5597;
}
.pill-hidden {
    background: #fce9e9;
    color: #a83b3b;
}
.actions {
    justify-content: flex-end;
    gap: 5px;
}
.icon-btn {
    display: inline-grid;
    place-items: center;
    width: 29px;
    height: 29px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text-muted);
    cursor: pointer;
    transition: 0.16s ease;
}
.icon-btn:hover {
    color: var(--primary);
    border-color: var(--primary);
}
.empty,
.loading {
    padding: 30px 18px;
    color: var(--text-secondary);
    font-size: 12px;
    text-align: center;
}
.feedback {
    display: flex;
    align-items: center;
    margin: 0 0 12px;
    padding: 9px 11px;
    border-radius: 9px;
    font-size: 11px;
}
.feedback.success {
    background: #e8f8ef;
    color: #177447;
}
.feedback.error {
    background: #fff0f0;
    color: #a83232;
}
.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, 0.48);
    backdrop-filter: blur(3px);
}
.modal {
    width: min(650px, 100%);
    max-height: min(90vh, 850px);
    overflow: auto;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
    color: var(--text-primary);
    box-shadow: 0 24px 80px #0f172a30;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
    padding: 17px 20px;
    border-bottom: 1px solid var(--border);
}
.modal-header h2 {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
}
.modal-header p {
    margin: 5px 0 0;
    color: var(--text-secondary);
    font-size: 11px;
    line-height: 1.45;
}
.modal-body {
    padding: 18px 20px;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px;
}
.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}
.field.full {
    grid-column: 1 / -1;
}
.field label {
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 650;
}
.field input,
.field select,
.field textarea {
    box-sizing: border-box;
    width: 100%;
    min-height: 36px;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-primary);
    font: inherit;
    font-size: 12px;
    outline: none;
}
.field textarea {
    min-height: 76px;
    resize: vertical;
}
.field-error {
    color: #c24141;
    font-size: 10px;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 20px;
    border-top: 1px solid var(--border);
}
@media (max-width: 760px) {
    .page-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 12px;
    }
    .page-header > .btn {
        align-self: flex-start;
    }
    .stats {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .stat-card {
        padding: 12px 14px;
    }
    .toolbar {
        align-items: stretch;
        flex-direction: column;
        gap: 9px;
    }
    .search-wrap {
        max-width: none;
    }
    .filters {
        flex-wrap: wrap;
    }
    .filters select {
        flex: 1 1 130px;
    }
    .form-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .field.full {
        grid-column: auto;
    }
    .modal-header,
    .modal-body {
        padding: 15px 16px;
    }
    .modal-footer {
        padding: 11px 16px;
    }
}

/* Premium animated create/edit modal */
.modal-backdrop {
    background: rgba(8, 15, 28, 0.62);
    backdrop-filter: blur(8px) saturate(115%);
    animation: modal-backdrop-in 0.2s ease-out both;
}
.modal {
    position: relative;
    isolation: isolate;
    border-color: color-mix(in srgb, var(--primary) 20%, var(--border));
    border-radius: 18px;
    box-shadow:
        0 32px 100px rgba(0, 0, 0, 0.3),
        0 0 0 1px rgba(255, 255, 255, 0.24) inset;
    animation: modal-panel-in 0.28s cubic-bezier(0.2, 0.8, 0.2, 1) both;
    scrollbar-width: thin;
}
.modal::before {
    content: '';
    position: absolute;
    z-index: 2;
    top: 0;
    left: 18px;
    right: 18px;
    height: 3px;
    border-radius: 0 0 999px 999px;
    background: linear-gradient(
        90deg,
        transparent,
        var(--primary),
        color-mix(in srgb, var(--primary) 30%, #67e8f9),
        transparent
    );
    pointer-events: none;
}
.modal-header {
    position: relative;
    padding: 22px 24px 18px;
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--primary) 7%, var(--surface)),
        var(--surface) 68%
    );
}
.modal-header h2 {
    font-size: 19px;
    letter-spacing: -0.025em;
    line-height: 1.25;
}
.modal-header p {
    max-width: 440px;
    margin-top: 7px;
    font-size: 12px;
}
.modal-body {
    padding: 21px 24px 23px;
}
.form-grid {
    gap: 16px 15px;
}
.field {
    min-width: 0;
}
.field label {
    display: block;
    margin-bottom: 7px;
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.015em;
}
.field input,
.field select,
.field textarea {
    width: 100%;
    min-height: 40px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text-primary);
    font: inherit;
    font-size: 12px;
    outline: none;
    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        background 0.18s ease,
        transform 0.18s ease;
}
.field input,
.field select {
    padding: 0 12px;
}
.field textarea {
    min-height: 88px;
    padding: 10px 12px;
    resize: vertical;
}
.field input::placeholder,
.field textarea::placeholder {
    color: var(--text-muted);
    opacity: 0.8;
}
.field input:hover,
.field select:hover,
.field textarea:hover {
    border-color: var(--border-strong);
}
.field input:focus,
.field select:focus,
.field textarea:focus {
    border-color: var(--primary);
    background: color-mix(in srgb, var(--primary) 2%, var(--surface));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 15%, transparent);
}
.field-error {
    display: block;
    margin-top: 5px;
    font-size: 11px;
    line-height: 1.4;
}
.modal-footer {
    position: sticky;
    bottom: 0;
    padding: 14px 24px 18px;
    background: color-mix(in srgb, var(--surface) 94%, transparent);
    backdrop-filter: blur(12px);
}
.modal-footer .btn {
    min-height: 40px;
    border-radius: 10px;
    padding-inline: 16px;
}
.modal-footer .btn-primary {
    box-shadow: 0 7px 20px color-mix(in srgb, var(--primary) 20%, transparent);
}
.modal-footer .btn-primary:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px color-mix(in srgb, var(--primary) 25%, transparent);
}
@keyframes modal-backdrop-in {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
@keyframes modal-panel-in {
    from {
        opacity: 0;
        transform: translateY(14px) scale(0.975);
        filter: blur(3px);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
        filter: blur(0);
    }
}
@media (max-width: 600px) {
    .modal-backdrop {
        align-items: flex-end;
        padding: 10px;
    }
    .modal {
        width: 100%;
        max-height: 92dvh;
        border-radius: 18px 18px 12px 12px;
    }
    .modal-header {
        padding: 19px 18px 15px;
    }
    .modal-body {
        padding: 18px;
    }
    .modal-footer {
        padding: 12px 18px 16px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .modal-backdrop,
    .modal {
        animation: none;
    }
    .field input,
    .field select,
    .field textarea,
    .modal-footer .btn {
        transition: none;
    }
}

/* Professional destructive-action confirmation dialog */
.delete-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(10, 18, 32, 0.58);
    backdrop-filter: blur(5px);
    animation: delete-fade-in 0.16s ease-out;
}
.delete-dialog {
    width: min(440px, 100%);
    padding: 24px;
    border: 1px solid var(--border);
    border-radius: 16px;
    background: var(--surface);
    color: var(--text-primary);
    box-shadow: 0 28px 90px rgba(0, 0, 0, 0.28);
    animation: delete-dialog-in 0.18s ease-out;
}
.delete-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    margin-bottom: 16px;
    border: 1px solid color-mix(in srgb, #dc2626 22%, var(--border));
    border-radius: 13px;
    background: color-mix(in srgb, #dc2626 10%, var(--surface));
    color: #dc2626;
}
.delete-eyebrow {
    margin: 0 0 5px;
    color: #dc2626;
    font-size: 10px;
    font-weight: 750;
    letter-spacing: 0.09em;
    text-transform: uppercase;
}
.delete-copy h2 {
    margin: 0;
    color: var(--text-primary);
    font-size: 19px;
    line-height: 1.3;
    font-weight: 750;
    letter-spacing: -0.02em;
}
.delete-copy > p:last-child {
    margin: 7px 0 0;
    color: var(--text-secondary);
    font-size: 12px;
    line-height: 1.55;
}
.delete-record {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 18px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface-muted, var(--surface));
}
.record-label {
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.delete-record strong {
    overflow-wrap: anywhere;
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 700;
}
.record-meta {
    overflow-wrap: anywhere;
    color: var(--text-secondary);
    font-size: 11px;
}
.delete-warning {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 12px;
    padding: 10px 11px;
    border: 1px solid color-mix(in srgb, #d97706 22%, var(--border));
    border-radius: 9px;
    background: color-mix(in srgb, #d97706 7%, var(--surface));
    color: var(--text-secondary);
    font-size: 11px;
    line-height: 1.5;
}
.delete-warning :deep(svg) {
    flex: 0 0 auto;
    margin-top: 1px;
    color: #d97706;
}
.delete-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 20px;
}
.btn-danger-solid {
    border-color: #b91c1c;
    background: #dc2626;
    color: #fff;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.18);
}
.btn-danger-solid:hover:not(:disabled) {
    border-color: #991b1b;
    background: #b91c1c;
}
.btn-danger-solid:disabled {
    opacity: 0.65;
    cursor: wait;
}
@keyframes delete-fade-in {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
@keyframes delete-dialog-in {
    from {
        opacity: 0;
        transform: translateY(6px) scale(0.985);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
@media (max-width: 480px) {
    .delete-dialog {
        padding: 19px;
    }
    .delete-actions {
        flex-direction: column-reverse;
    }
    .delete-actions .btn {
        width: 100%;
    }
}
@media (prefers-reduced-motion: reduce) {
    .delete-backdrop,
    .delete-dialog {
        animation: none;
    }
}
</style>
