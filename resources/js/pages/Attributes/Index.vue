<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
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

interface Attribute {
    id: number;
    name: string;
    slug: string;
    type: string;
    description: string | null;
    status: 'active' | 'inactive' | 'archived';
    sort_order: number;
    created_at: string;
    updated_at: string;
}
interface ApiPayload<T> {
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
}
type AttributeForm = {
    name: string;
    slug: string;
    type: string;
    description: string;
    status: Attribute['status'];
    sort_order: number;
};
const attributes = ref<Attribute[]>([]);
const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref<number | null>(null);
const deleteTarget = ref<Attribute | null>(null);
const search = ref('');
const statusFilter = ref('');
const errorMessage = ref('');
const successMessage = ref('');
const modalOpen = ref(false);
const editing = ref<Attribute | null>(null);
const validationErrors = ref<Record<string, string>>({});
const form = reactive<AttributeForm>({
    name: '',
    slug: '',
    type: 'select',
    description: '',
    status: 'active',
    sort_order: 0,
});

const typeOptions = [
    {
        value: 'select',
        label: 'Select',
        description: 'Choose one predefined value',
    },
    {
        value: 'multiselect',
        label: 'Multi-select',
        description: 'Choose multiple values',
    },
    { value: 'text', label: 'Text', description: 'Free-text value' },
    { value: 'number', label: 'Number', description: 'Numeric value' },
    { value: 'color', label: 'Color', description: 'Color value' },
    { value: 'boolean', label: 'Boolean', description: 'Yes / No value' },
];
const totalCount = computed(() => attributes.value.length);
const activeCount = computed(
    () => attributes.value.filter((item) => item.status === 'active').length,
);
const selectableCount = computed(
    () =>
        attributes.value.filter((item) =>
            ['select', 'multiselect'].includes(item.type),
        ).length,
);
const filteredAttributes = computed(() => {
    const term = search.value.trim().toLowerCase();
    return attributes.value
        .filter((item) => {
            const matchesSearch =
                !term ||
                [item.name, item.slug, item.type, item.description ?? ''].some(
                    (value) => value.toLowerCase().includes(term),
                );
            return (
                matchesSearch &&
                (!statusFilter.value || item.status === statusFilter.value)
            );
        })
        .sort(
            (a, b) =>
                a.sort_order - b.sort_order || a.name.localeCompare(b.name),
        );
});

async function apiRequest<T>(
    url: string,
    method = 'GET',
    body?: Record<string, unknown>,
): Promise<T> {
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(body ? { 'Content-Type': 'application/json' } : {}),
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    const payload = (await response.json()) as ApiPayload<T>;
    if (!response.ok) {
        if (payload.errors) {
            validationErrors.value = Object.fromEntries(
                Object.entries(payload.errors).map(([key, messages]) => [
                    key,
                    messages[0] ?? 'Invalid value.',
                ]),
            );
        }
        throw new Error(payload.message ?? 'Unable to complete the request.');
    }
    return payload.data as T;
}

async function loadAttributes(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';
    try {
        attributes.value = await apiRequest<Attribute[]>('/api/v1/backend/attributes');
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to load attributes.';
    } finally {
        isLoading.value = false;
    }
}
function resetForm(): void {
    Object.assign(form, {
        name: '',
        slug: '',
        type: 'select',
        description: '',
        status: 'active',
        sort_order: 0,
    });
    validationErrors.value = {};
}
function openCreate(): void {
    editing.value = null;
    resetForm();
    modalOpen.value = true;
}
function openEdit(attribute: Attribute): void {
    editing.value = attribute;
    Object.assign(form, {
        name: attribute.name,
        slug: attribute.slug,
        type: attribute.type,
        description: attribute.description ?? '',
        status: attribute.status,
        sort_order: attribute.sort_order,
    });
    validationErrors.value = {};
    modalOpen.value = true;
}
function closeModal(): void {
    if (isSaving.value) return;
    modalOpen.value = false;
    editing.value = null;
    validationErrors.value = {};
}
async function saveAttribute(): Promise<void> {
    isSaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    validationErrors.value = {};
    const payload = {
        name: form.name.trim(),
        slug: form.slug.trim() || null,
        type: form.type,
        description: form.description.trim() || null,
        status: form.status,
        sort_order: Number(form.sort_order) || 0,
    };
    try {
        if (editing.value) {
            const updated = await apiRequest<Attribute>(
                `/api/v1/backend/attributes/${editing.value.id}`,
                'PUT',
                payload,
            );
            attributes.value = attributes.value.map((item) =>
                item.id === updated.id ? updated : item,
            );
            successMessage.value = 'Attribute updated successfully.';
        } else {
            const created = await apiRequest<Attribute>(
                '/api/v1/backend/attributes',
                'POST',
                payload,
            );
            attributes.value = [...attributes.value, created];
            successMessage.value = 'Attribute created successfully.';
        }
        modalOpen.value = false;
        editing.value = null;
        await loadAttributes();
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to save attribute.';
    } finally {
        isSaving.value = false;
    }
}
function deleteAttribute(attribute: Attribute): void {
    deleteTarget.value = attribute;
    errorMessage.value = '';
    successMessage.value = '';
}
async function confirmDelete(): Promise<void> {
    const attribute = deleteTarget.value;
    if (!attribute || deletingId.value !== null) return;
    deletingId.value = attribute.id;
    errorMessage.value = '';
    successMessage.value = '';
    try {
        await apiRequest(`/api/v1/backend/attributes/${attribute.id}`, 'DELETE');
        attributes.value = attributes.value.filter(
            (item) => item.id !== attribute.id,
        );
        deleteTarget.value = null;
        successMessage.value = `"${attribute.name}" deleted successfully.`;
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to delete attribute.';
    } finally {
        deletingId.value = null;
    }
}
function openValues(attribute: Attribute): void {
    router.visit(`/backend/attributes/${attribute.id}/values`);
}
function formatDate(value: string): string {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? '—'
        : new Intl.DateTimeFormat('en-GB', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          }).format(date);
}
function titleCase(value: string): string {
    return value
        .replace(/[-_]/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
onMounted(loadAttributes);
</script>

<template>
    <Head title="Product Attributes" />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <div class="attributes-page">
            <header class="page-header">
                <div class="page-heading">
                    <div class="breadcrumb">
                        <span>Products</span
                        ><span class="breadcrumb-separator">/</span
                        ><span>Attributes</span>
                    </div>
                    <div class="title-row">
                        <span class="title-icon"
                            ><Icon name="sliders-horizontal" :size="21"
                        /></span>
                        <div>
                            <h1>Product Attributes</h1>
                            <p>
                                Define reusable product properties and manage
                                their selectable values.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="page-actions">
                    <button
                        class="btn secondary"
                        type="button"
                        :disabled="isLoading"
                        @click="loadAttributes"
                    >
                        <Icon name="refresh-cw" :size="14" /> Refresh
                    </button>
                    <button
                        class="btn primary"
                        type="button"
                        @click="openCreate"
                    >
                        <Icon name="plus" :size="15" /> Add Attribute
                    </button>
                </div>
            </header>

            <div v-if="errorMessage" class="notice error">
                <Icon name="alert-circle" :size="16" /><span>{{
                    errorMessage
                }}</span
                ><button type="button" @click="errorMessage = ''">
                    <Icon name="x" :size="14" />
                </button>
            </div>
            <div v-if="successMessage" class="notice success">
                <Icon name="check-circle-2" :size="16" /><span>{{
                    successMessage
                }}</span
                ><button type="button" @click="successMessage = ''">
                    <Icon name="x" :size="14" />
                </button>
            </div>

            <section class="stats-grid">
                <article class="stat-card">
                    <span class="stat-label">Total attributes</span
                    ><strong>{{ totalCount }}</strong
                    ><small>All configured properties</small>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Active attributes</span
                    ><strong>{{ activeCount }}</strong
                    ><small>Available for product setup</small>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Value-based types</span
                    ><strong>{{ selectableCount }}</strong
                    ><small>Select and multi-select</small>
                </article>
            </section>

            <section class="table-card">
                <div class="toolbar">
                    <label class="search-field"
                        ><Icon name="search" :size="16" /><input
                            v-model="search"
                            type="search"
                            placeholder="Search name, slug, type or description..."
                    /></label>
                    <div class="toolbar-actions">
                        <select
                            v-model="statusFilter"
                            aria-label="Filter by status"
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                        </select>
                        <button
                            class="btn secondary"
                            type="button"
                            @click="
                                search = '';
                                statusFilter = '';
                            "
                        >
                            <Icon name="x" :size="14" /> Clear
                        </button>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ATTRIBUTE</th>
                                <th>TYPE</th>
                                <th>DESCRIPTION</th>
                                <th>STATUS</th>
                                <th>SORT ORDER</th>
                                <th>UPDATED</th>
                                <th class="align-right">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="7" class="state-cell">
                                    <span class="spinner"></span> Loading
                                    attributes…
                                </td>
                            </tr>
                            <tr v-else-if="filteredAttributes.length === 0">
                                <td colspan="7" class="state-cell">
                                    <Icon name="search-x" :size="22" /><strong
                                        >No attributes found</strong
                                    ><span
                                        >Try another search or create an
                                        attribute.</span
                                    >
                                </td>
                            </tr>
                            <tr
                                v-for="attribute in filteredAttributes"
                                v-else
                                :key="attribute.id"
                            >
                                <td>
                                    <div class="identity-cell">
                                        <span class="identity-icon"
                                            ><Icon
                                                name="sliders-horizontal"
                                                :size="16"
                                        /></span>
                                        <div>
                                            <strong>{{ attribute.name }}</strong
                                            ><small>{{ attribute.slug }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="type-pill">{{
                                        titleCase(attribute.type)
                                    }}</span>
                                </td>
                                <td class="description-cell">
                                    {{ attribute.description || '—' }}
                                </td>
                                <td>
                                    <span
                                        class="status-pill"
                                        :class="attribute.status"
                                        >{{ titleCase(attribute.status) }}</span
                                    >
                                </td>
                                <td class="numeric-cell">
                                    {{ attribute.sort_order }}
                                </td>
                                <td class="date-cell">
                                    {{
                                        formatDate(
                                            attribute.updated_at ||
                                                attribute.created_at,
                                        )
                                    }}
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button
                                            class="icon-btn manage"
                                            type="button"
                                            title="Manage values"
                                            aria-label="Manage values"
                                            @click="openValues(attribute)"
                                        >
                                            <Icon
                                                name="list-checks"
                                                :size="15"
                                            />
                                        </button>
                                        <button
                                            class="icon-btn"
                                            type="button"
                                            title="Edit attribute"
                                            aria-label="Edit attribute"
                                            @click="openEdit(attribute)"
                                        >
                                            <Icon name="pencil" :size="15" />
                                        </button>
                                        <button
                                            class="icon-btn danger"
                                            type="button"
                                            title="Delete attribute"
                                            aria-label="Delete attribute"
                                            :disabled="
                                                deletingId === attribute.id
                                            "
                                            @click="deleteAttribute(attribute)"
                                        >
                                            <Icon name="trash-2" :size="15" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <footer class="table-footer">
                    <span
                        >Showing
                        <strong>{{ filteredAttributes.length }}</strong> of
                        <strong>{{ totalCount }}</strong> attributes</span
                    ><span>Master Data <b>•</b> Attributes</span>
                </footer>
            </section>

            <Transition name="modal">
                <div
                    v-if="modalOpen"
                    class="modal-backdrop"
                    @click.self="closeModal"
                >
                    <section
                        class="modal-panel"
                        role="dialog"
                        aria-modal="true"
                        :aria-labelledby="'attribute-modal-title'"
                    >
                        <header class="modal-header">
                            <div class="modal-heading-icon">
                                <Icon
                                    :name="editing ? 'pencil' : 'plus'"
                                    :size="19"
                                />
                            </div>
                            <div class="modal-heading-copy">
                                <span class="modal-kicker">{{
                                    editing
                                        ? 'UPDATE MASTER DATA'
                                        : 'NEW MASTER DATA'
                                }}</span>
                                <h2 id="attribute-modal-title">
                                    {{
                                        editing
                                            ? 'Edit Attribute'
                                            : 'Create Attribute'
                                    }}
                                </h2>
                                <p>
                                    {{
                                        editing
                                            ? 'Update the attribute configuration and save your changes.'
                                            : 'Create a reusable property for product variants and catalog filtering.'
                                    }}
                                </p>
                            </div>
                            <button
                                class="modal-close"
                                type="button"
                                :disabled="isSaving"
                                aria-label="Close modal"
                                @click="closeModal"
                            >
                                <Icon name="x" :size="17" />
                            </button>
                        </header>
                        <div class="modal-progress">
                            <span :class="{ active: form.name.trim() }"></span
                            ><span :class="{ active: form.type }"></span
                            ><span :class="{ active: form.status }"></span>
                        </div>
                        <form
                            class="modal-body"
                            @submit.prevent="saveAttribute"
                        >
                            <div class="form-section-title">
                                <Icon name="fingerprint" :size="15" />
                                <div>
                                    <strong>Attribute identity</strong
                                    ><small>Name, slug and data type</small>
                                </div>
                            </div>
                            <div class="form-grid two">
                                <label class="field"
                                    ><span>Attribute name <b>*</b></span
                                    ><input
                                        v-model="form.name"
                                        maxlength="150"
                                        required
                                        placeholder="e.g. Color"
                                        :class="{
                                            invalid: validationErrors.name,
                                        }"
                                    /><small
                                        v-if="validationErrors.name"
                                        class="field-error"
                                        >{{ validationErrors.name }}</small
                                    ></label
                                >
                                <label class="field"
                                    ><span>Slug <small>(optional)</small></span
                                    ><input
                                        v-model="form.slug"
                                        maxlength="180"
                                        placeholder="auto-generated-from-name"
                                        :class="{
                                            invalid: validationErrors.slug,
                                        }"
                                    /><small
                                        v-if="validationErrors.slug"
                                        class="field-error"
                                        >{{ validationErrors.slug }}</small
                                    ></label
                                >
                            </div>
                            <label class="field"
                                ><span>Attribute type <b>*</b></span
                                ><select
                                    v-model="form.type"
                                    required
                                    :class="{ invalid: validationErrors.type }"
                                >
                                    <option
                                        v-for="option in typeOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }} —
                                        {{ option.description }}
                                    </option></select
                                ><small
                                    v-if="validationErrors.type"
                                    class="field-error"
                                    >{{ validationErrors.type }}</small
                                ></label
                            >
                            <label class="field"
                                ><span>Description</span
                                ><textarea
                                    v-model="form.description"
                                    rows="3"
                                    maxlength="1000"
                                    placeholder="Describe how this attribute is used…"
                                    :class="{
                                        invalid: validationErrors.description,
                                    }"
                                ></textarea
                                ><small
                                    v-if="validationErrors.description"
                                    class="field-error"
                                    >{{ validationErrors.description }}</small
                                ></label
                            >
                            <div class="form-section-title compact">
                                <Icon name="settings-2" :size="15" />
                                <div>
                                    <strong>Availability & ordering</strong
                                    ><small
                                        >Control attribute visibility to
                                        workflows</small
                                    >
                                </div>
                            </div>
                            <div class="form-grid two">
                                <label class="field"
                                    ><span>Status <b>*</b></span
                                    ><select v-model="form.status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">
                                            Inactive
                                        </option>
                                        <option value="archived">
                                            Archived
                                        </option>
                                    </select></label
                                >
                                <label class="field"
                                    ><span>Sort order</span
                                    ><input
                                        v-model.number="form.sort_order"
                                        type="number"
                                        min="0"
                                        step="1"
                                        required
                                /></label>
                            </div>
                            <div class="modal-footer">
                                <button
                                    class="btn secondary"
                                    type="button"
                                    :disabled="isSaving"
                                    @click="closeModal"
                                >
                                    Cancel</button
                                ><button
                                    class="btn primary"
                                    type="submit"
                                    :disabled="isSaving"
                                >
                                    <span
                                        v-if="isSaving"
                                        class="spinner small"
                                    ></span
                                    ><Icon v-else name="check" :size="15" />{{
                                        isSaving
                                            ? 'Saving…'
                                            : editing
                                              ? 'Save changes'
                                              : 'Create attribute'
                                    }}
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </Transition>

            <Transition name="delete-modal">
                <div
                    v-if="deleteTarget"
                    class="delete-backdrop"
                    @click.self="deletingId === null && (deleteTarget = null)"
                    @keydown.esc="deletingId === null && (deleteTarget = null)"
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
                            <h2 id="delete-dialog-title">
                                Delete this attribute?
                            </h2>
                            <p id="delete-dialog-description">
                                Please review the selected attribute before
                                confirming. This action cannot be undone.
                            </p>
                        </div>
                        <div class="delete-record">
                            <span class="record-label">Attribute name</span
                            ><strong>{{ deleteTarget.name }}</strong
                            ><span class="record-meta"
                                >{{ deleteTarget.slug }} ·
                                {{ titleCase(deleteTarget.type) }}</span
                            >
                        </div>
                        <div class="delete-warning">
                            <Icon name="info" :size="15" /><span
                                >Deletion may be blocked if this attribute has
                                values or is already used by products.</span
                            >
                        </div>
                        <footer class="delete-actions">
                            <button
                                class="btn secondary"
                                type="button"
                                :disabled="deletingId !== null"
                                @click="deleteTarget = null"
                            >
                                Cancel</button
                            ><button
                                class="btn btn-danger-solid"
                                type="button"
                                :disabled="deletingId !== null"
                                @click="confirmDelete"
                            >
                                <span
                                    v-if="deletingId !== null"
                                    class="spinner small"
                                ></span
                                ><Icon v-else name="trash-2" :size="14" />{{
                                    deletingId !== null
                                        ? 'Deleting…'
                                        : 'Delete attribute'
                                }}
                            </button>
                        </footer>
                    </section>
                </div>
            </Transition>
        </div>
    </ErpLayout>
</template>

<style scoped>
.attributes-page {
    --radius: 12px;
    min-height: 100%;
    padding: 0;
    color: var(--text-primary);
}
.page-header,
.title-row,
.page-actions,
.toolbar,
.toolbar-actions,
.identity-cell,
.row-actions {
    display: flex;
    align-items: center;
}
.page-header {
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 18px;
}
.page-heading {
    min-width: 0;
}
.breadcrumb {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
}
.breadcrumb-separator {
    color: var(--border-strong);
}
.breadcrumb span:last-child {
    color: var(--text-secondary);
}
.title-row {
    gap: 12px;
}
.title-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
    border-radius: 12px;
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
    color: var(--primary);
}
.title-row h1 {
    margin: 0;
    font-size: 24px;
    line-height: 1.15;
    letter-spacing: -0.025em;
    font-weight: 750;
}
.title-row p {
    margin: 5px 0 0;
    color: var(--text-secondary);
    font-size: 12px;
    line-height: 1.5;
}
.page-actions {
    gap: 8px;
    flex-shrink: 0;
}
.btn {
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 0 12px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 650;
    cursor: pointer;
    transition: all 0.16s ease;
}
.btn:hover:not(:disabled) {
    transform: translateY(-1px);
    border-color: var(--border-strong);
}
.btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.btn.primary {
    border-color: color-mix(in srgb, var(--primary) 85%, transparent);
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--primary) 96%, white),
        color-mix(in srgb, var(--primary) 85%, black)
    );
    color: #fff;
    box-shadow: 0 5px 14px color-mix(in srgb, var(--primary) 15%, transparent);
}
.btn.secondary:hover {
    background: var(--surface-muted);
    color: var(--text-primary);
}
.notice {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 9px;
    font-size: 12px;
}
.notice span {
    flex: 1;
}
.notice button {
    display: grid;
    place-items: center;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
}
.notice.error {
    border-color: color-mix(in srgb, #dc2626 22%, var(--border));
    background: color-mix(in srgb, #dc2626 5%, var(--surface));
    color: #dc2626;
}
.notice.success {
    border-color: color-mix(in srgb, #059669 22%, var(--border));
    background: color-mix(in srgb, #059669 5%, var(--surface));
    color: #059669;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 16px;
}
.stat-card {
    min-height: 102px;
    padding: 15px 17px;
    border: 1px solid var(--border);
    border-radius: 13px;
    background: var(--surface);
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.025);
}
.stat-label,
.stat-card small {
    display: block;
    color: var(--text-secondary);
    font-size: 11px;
}
.stat-card strong {
    display: block;
    margin: 5px 0 3px;
    color: var(--text-primary);
    font-size: 25px;
    line-height: 1.1;
    font-weight: 750;
    letter-spacing: -0.03em;
}
.stat-card small {
    font-size: 10px;
    color: var(--text-muted);
}
.table-card {
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.025);
}
.toolbar {
    justify-content: space-between;
    gap: 12px;
    padding: 14px;
    border-bottom: 1px solid var(--border);
}
.search-field {
    height: 38px;
    max-width: 470px;
    min-width: 220px;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 0 11px;
    border: 1px solid var(--border);
    border-radius: 9px;
    color: var(--text-muted);
    background: var(--surface);
}
.search-field:focus-within {
    border-color: color-mix(in srgb, var(--primary) 48%, var(--border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 8%, transparent);
}
.search-field input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text-primary);
    font: inherit;
    font-size: 12px;
}
.toolbar-actions {
    gap: 8px;
}
.toolbar select,
.field select,
.field input,
.field textarea {
    width: 100%;
    min-height: 38px;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    outline: 0;
    background: var(--surface);
    color: var(--text-primary);
    font: inherit;
    font-size: 12px;
    transition:
        border-color 0.16s,
        box-shadow 0.16s;
}
.toolbar select {
    width: auto;
    min-width: 125px;
}
.field input:focus,
.field select:focus,
.field textarea:focus,
.toolbar select:focus {
    border-color: color-mix(in srgb, var(--primary) 50%, var(--border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 8%, transparent);
}
.table-wrap {
    width: 100%;
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}
thead {
    background: var(--surface-muted);
}
th {
    padding: 12px 14px;
    color: var(--text-muted);
    font-size: 10px;
    letter-spacing: 0.035em;
    font-weight: 750;
    text-align: left;
    border-bottom: 1px solid var(--border);
}
td {
    padding: 11px 14px;
    border-bottom: 1px solid color-mix(in srgb, var(--border) 75%, transparent);
    font-size: 12px;
    color: var(--text-secondary);
}
tbody tr:last-child td {
    border-bottom: 0;
}
tbody tr:hover td {
    background: color-mix(in srgb, var(--primary) 2.5%, var(--surface));
}
.identity-cell {
    gap: 10px;
}
.identity-icon {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));
    border-radius: 9px;
    background: color-mix(in srgb, var(--primary) 6%, var(--surface));
    color: var(--primary);
}
.identity-cell strong,
.identity-cell small {
    display: block;
}
.identity-cell strong {
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 700;
}
.identity-cell small {
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 10px;
}
.type-pill,
.status-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
}
.type-pill {
    border: 1px solid var(--border);
    background: var(--surface-muted);
    color: var(--text-secondary);
}
.status-pill.active {
    background: color-mix(in srgb, #059669 10%, var(--surface));
    color: #059669;
}
.status-pill.inactive {
    background: color-mix(in srgb, #64748b 11%, var(--surface));
    color: #64748b;
}
.status-pill.archived {
    background: color-mix(in srgb, #d97706 10%, var(--surface));
    color: #b45309;
}
.description-cell {
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.numeric-cell {
    font-variant-numeric: tabular-nums;
}
.date-cell {
    font-size: 11px;
    white-space: nowrap;
}
.align-right {
    text-align: right;
}
.row-actions {
    justify-content: flex-end;
    gap: 6px;
}
.icon-btn {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s;
}
.icon-btn:hover {
    border-color: color-mix(in srgb, var(--primary) 35%, var(--border));
    background: color-mix(in srgb, var(--primary) 5%, var(--surface));
    color: var(--primary);
}
.icon-btn.manage {
    color: var(--primary);
}
.icon-btn.danger:hover {
    border-color: #ef4444;
    background: #fef2f2;
    color: #dc2626;
}
.icon-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.state-cell {
    height: 150px;
    text-align: center;
    color: var(--text-muted);
}
.state-cell > * {
    display: block;
    margin: 5px auto;
}
.state-cell strong {
    color: var(--text-primary);
}
.table-footer {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 14px;
    border-top: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 10px;
}
.table-footer strong {
    color: var(--text-secondary);
}
.table-footer b {
    padding: 0 5px;
    color: var(--primary);
}
.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: grid;
    place-items: center;
    padding: 20px;
    background: rgba(8, 15, 27, 0.56);
    backdrop-filter: blur(7px);
}
.modal-panel {
    width: min(620px, 100%);
    max-height: min(90vh, 860px);
    overflow: auto;
    border: 1px solid color-mix(in srgb, var(--border) 70%, transparent);
    border-radius: 17px;
    background: var(--surface);
    color: var(--text-primary);
    box-shadow: 0 28px 90px rgba(0, 0, 0, 0.3);
}
.modal-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 20px 22px 16px;
}
.modal-heading-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
    border-radius: 12px;
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
    color: var(--primary);
}
.modal-heading-copy {
    flex: 1;
    min-width: 0;
}
.modal-kicker {
    color: var(--primary);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.12em;
}
.modal-heading-copy h2 {
    margin: 4px 0 0;
    font-size: 20px;
    font-weight: 760;
    letter-spacing: -0.025em;
}
.modal-heading-copy p {
    margin: 5px 0 0;
    color: var(--text-secondary);
    font-size: 11px;
    line-height: 1.5;
}
.modal-close {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    color: var(--text-secondary);
    cursor: pointer;
}
.modal-progress {
    height: 3px;
    display: flex;
    gap: 3px;
    padding: 0 22px;
}
.modal-progress span {
    height: 3px;
    flex: 1;
    border-radius: 99px;
    background: var(--border);
    transition: background 0.2s;
}
.modal-progress span.active {
    background: var(--primary);
}
.modal-body {
    padding: 18px 22px 20px;
}
.form-section-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 13px;
    color: var(--primary);
}
.form-section-title > div {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.form-section-title strong {
    color: var(--text-primary);
    font-size: 12px;
}
.form-section-title small {
    color: var(--text-muted);
    font-size: 10px;
}
.form-section-title.compact {
    margin-top: 18px;
}
.form-grid {
    display: grid;
    gap: 12px;
}
.form-grid.two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 13px;
}
.field > span {
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 700;
}
.field > span b {
    color: #dc2626;
}
.field > span small {
    color: var(--text-muted);
    font-weight: 500;
}
.field textarea {
    resize: vertical;
    min-height: 78px;
}
.field input.invalid,
.field select.invalid,
.field textarea.invalid {
    border-color: #dc2626;
}
.field-error {
    color: #dc2626;
    font-size: 10px;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 14px;
    margin-top: 5px;
    border-top: 1px solid var(--border);
}
.spinner {
    width: 15px;
    height: 15px;
    display: inline-block;
    border: 2px solid color-mix(in srgb, var(--primary) 20%, transparent);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 0.75s linear infinite;
}
.spinner.small {
    width: 13px;
    height: 13px;
    border-color: rgba(255, 255, 255, 0.35);
    border-top-color: #fff;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.18s ease;
}
.modal-enter-active .modal-panel,
.modal-leave-active .modal-panel {
    transition:
        transform 0.2s ease,
        opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-from .modal-panel,
.modal-leave-to .modal-panel {
    transform: translateY(10px) scale(0.985);
    opacity: 0;
}
@media (max-width: 900px) {
    .page-header {
        align-items: flex-start;
    }
    .stats-grid {
        gap: 10px;
    }
    .stat-card {
        padding: 13px;
    }
    .toolbar {
        align-items: stretch;
        flex-direction: column;
    }
    .search-field {
        max-width: none;
    }
    .toolbar-actions {
        justify-content: flex-end;
    }
}
@media (max-width: 620px) {
    .page-header {
        flex-direction: column;
    }
    .page-actions {
        width: 100%;
    }
    .page-actions .btn {
        flex: 1;
    }
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .form-grid.two {
        grid-template-columns: 1fr;
        gap: 0;
    }
    .modal-backdrop {
        padding: 10px;
    }
    .modal-header {
        padding: 16px;
    }
    .modal-body {
        padding: 16px;
    }
    .modal-progress {
        padding: 0 16px;
    }
    .table-footer {
        flex-direction: column;
    }
}
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}

/* Match the shared Product Categories / Brands / Units page rhythm. */
.attributes-page {
    padding: 0;
}
.page-header {
    gap: 24px;
    margin-bottom: 18px;
}
.breadcrumb {
    align-items: center;
    line-height: 1.2;
}
.stats-grid {
    gap: 12px;
    margin-bottom: 16px;
}
.stat-card {
    min-height: 102px;
    padding: 14px 16px;
    border-radius: 12px;
}
.table-card {
    border-radius: 12px;
}
.toolbar {
    padding: 13px 14px;
    gap: 12px;
}
th {
    padding: 12px 14px;
}
td {
    padding: 11px 14px;
}
.row-actions {
    gap: 5px;
}
.icon-btn {
    width: 30px;
    height: 30px;
}

/* Consistent premium destructive-action confirmation dialog. */
.delete-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(10, 18, 32, 0.58);
    backdrop-filter: blur(6px);
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
.delete-modal-enter-active,
.delete-modal-leave-active {
    transition: opacity 0.16s ease;
}
.delete-modal-enter-active .delete-dialog,
.delete-modal-leave-active .delete-dialog {
    transition:
        transform 0.18s ease,
        opacity 0.18s ease;
}
.delete-modal-enter-from,
.delete-modal-leave-to {
    opacity: 0;
}
.delete-modal-enter-from .delete-dialog,
.delete-modal-leave-to .delete-dialog {
    transform: translateY(10px) scale(0.98);
    opacity: 0;
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
        transform: translateY(10px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
@media (max-width: 760px) {
    .page-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 12px;
    }
    .page-actions {
        width: 100%;
    }
    .page-actions .btn {
        flex: 1;
    }
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .stat-card {
        padding: 12px 14px;
    }
}
@media (max-width: 600px) {
    .delete-backdrop {
        align-items: flex-end;
        padding: 10px;
    }
    .delete-dialog {
        width: 100%;
        padding: 20px;
        border-radius: 18px 18px 12px 12px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .delete-backdrop,
    .delete-dialog {
        animation: none;
    }
    .delete-modal-enter-active,
    .delete-modal-leave-active,
    .delete-modal-enter-active .delete-dialog,
    .delete-modal-leave-active .delete-dialog {
        transition: none;
    }
}
</style>
