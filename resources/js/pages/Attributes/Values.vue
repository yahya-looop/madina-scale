<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface Props {
    userName?: string;
    userEmail?: string;
    attributeId?: number | string;
}
const props = withDefaults(defineProps<Props>(), {
    userName: '',
    userEmail: '',
    attributeId: 0,
});
interface Attribute {
    id: number;
    name: string;
    slug: string;
    type: string;
    description: string | null;
    status: string;
    sort_order: number;
}
interface AttributeValue {
    id: number;
    attribute_id: number;
    name: string;
    slug: string;
    value: string;
    color_code: string | null;
    status: 'active' | 'inactive' | 'archived';
    sort_order: number;
    created_at: string;
    updated_at: string;
    attribute?: { id: number; name: string; slug: string; type: string };
}
interface ApiPayload<T> {
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
}
type ValueForm = {
    attribute_id: number;
    name: string;
    value: string;
    color_code: string;
    status: AttributeValue['status'];
    sort_order: number;
};
const attribute = ref<Attribute | null>(null);
const attributes = ref<Attribute[]>([]);
const values = ref<AttributeValue[]>([]);
const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref<number | null>(null);
const deleteTarget = ref<AttributeValue | null>(null);
const search = ref('');
const statusFilter = ref('');
const errorMessage = ref('');
const successMessage = ref('');
const modalOpen = ref(false);
const editing = ref<AttributeValue | null>(null);
const validationErrors = ref<Record<string, string>>({});
const form = reactive<ValueForm>({
    attribute_id: Number(props.attributeId) || 0,
    name: '',
    value: '',
    color_code: '',
    status: 'active',
    sort_order: 0,
});
const totalCount = computed(() => values.value.length);
const activeCount = computed(
    () => values.value.filter((item) => item.status === 'active').length,
);
const filteredValues = computed(() => {
    const term = search.value.trim().toLowerCase();
    return values.value
        .filter((item) => {
            const matches =
                !term ||
                [item.name, item.slug, item.value, item.color_code ?? ''].some(
                    (text) => text.toLowerCase().includes(term),
                );
            return (
                matches &&
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
        if (payload.errors)
            validationErrors.value = Object.fromEntries(
                Object.entries(payload.errors).map(([key, messages]) => [
                    key,
                    messages[0] ?? 'Invalid value.',
                ]),
            );
        throw new Error(payload.message ?? 'Unable to complete the request.');
    }
    return payload.data as T;
}
async function loadPage(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';
    try {
        const [attributeData, attributeList, valueData] = await Promise.all([
            apiRequest<Attribute>(
                `/api/v1/backend/attributes/${Number(props.attributeId)}`,
            ),
            apiRequest<Attribute[]>('/api/v1/backend/attributes'),
            apiRequest<AttributeValue[]>(
                `/api/v1/backend/attribute-values?attribute_id=${Number(props.attributeId)}`,
            ),
        ]);
        attribute.value = attributeData;
        attributes.value = attributeList;
        values.value = valueData.filter(
            (item) => item.attribute_id === Number(props.attributeId),
        );
        form.attribute_id = Number(props.attributeId);
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to load attribute values.';
    } finally {
        isLoading.value = false;
    }
}
function resetForm(): void {
    Object.assign(form, {
        attribute_id: Number(props.attributeId),
        name: '',
        value: '',
        color_code: '',
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
function openEdit(item: AttributeValue): void {
    editing.value = item;
    Object.assign(form, {
        attribute_id: item.attribute_id,
        name: item.name,
        value: item.value,
        color_code: item.color_code ?? '',
        status: item.status,
        sort_order: item.sort_order,
    });
    validationErrors.value = {};
    modalOpen.value = true;
}
function closeModal(): void {
    if (isSaving.value) return;
    modalOpen.value = false;
    editing.value = null;
}
async function saveValue(): Promise<void> {
    isSaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    validationErrors.value = {};
    const payload = {
        attribute_id: Number(form.attribute_id),
        name: form.name.trim(),
        value: form.value.trim() || form.name.trim(),
        color_code: form.color_code.trim() || null,
        status: form.status,
        sort_order: Number(form.sort_order) || 0,
    };
    try {
        if (editing.value) {
            await apiRequest<AttributeValue>(
                `/api/v1/backend/attribute-values/${editing.value.id}`,
                'PUT',
                payload,
            );
            successMessage.value = 'Attribute value updated successfully.';
        } else {
            await apiRequest<AttributeValue>(
                '/api/v1/backend/attribute-values',
                'POST',
                payload,
            );
            successMessage.value = 'Attribute value created successfully.';
        }
        modalOpen.value = false;
        editing.value = null;
        await loadPage();
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to save attribute value.';
    } finally {
        isSaving.value = false;
    }
}
function deleteValue(item: AttributeValue): void {
    deleteTarget.value = item;
}
async function confirmDelete(): Promise<void> {
    const item = deleteTarget.value;
    if (!item || deletingId.value !== null) return;
    deletingId.value = item.id;
    errorMessage.value = '';
    successMessage.value = '';
    try {
        await apiRequest(`/api/v1/backend/attribute-values/${item.id}`, 'DELETE');
        values.value = values.value.filter((value) => value.id !== item.id);
        deleteTarget.value = null;
        successMessage.value = `"${item.name}" deleted successfully.`;
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to delete attribute value.';
    } finally {
        deletingId.value = null;
    }
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
onMounted(loadPage);
</script>

<template>
    <Head
        :title="attribute ? `${attribute.name} Values` : 'Attribute Values'"
    />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <div class="attributes-page">
            <header class="page-header">
                <div class="page-heading">
                    <div class="breadcrumb">
                        <button
                            type="button"
                            @click="router.visit('/backend/attributes')"
                        >
                            Products</button
                        ><span class="breadcrumb-separator">/</span
                        ><button
                            type="button"
                            @click="router.visit('/backend/attributes')"
                        >
                            Attributes</button
                        ><span class="breadcrumb-separator">/</span
                        ><span>Values</span>
                    </div>
                    <div class="title-row">
                        <span class="title-icon"
                            ><Icon name="list-checks" :size="21"
                        /></span>
                        <div>
                            <h1>{{ attribute?.name || 'Attribute Values' }}</h1>
                            <p>
                                Manage the available values for this product
                                attribute.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="page-actions">
                    <button
                        class="btn secondary"
                        type="button"
                        :disabled="isLoading"
                        @click="loadPage"
                    >
                        <Icon name="refresh-cw" :size="14" /> Refresh</button
                    ><button
                        class="btn secondary"
                        type="button"
                        @click="router.visit('/backend/attributes')"
                    >
                        <Icon name="arrow-left" :size="14" /> Attributes</button
                    ><button
                        class="btn primary"
                        type="button"
                        @click="openCreate"
                    >
                        <Icon name="plus" :size="15" /> Add Value
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
                    <span class="stat-label">Total values</span
                    ><strong>{{ totalCount }}</strong
                    ><small>Values assigned to this attribute</small>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Active values</span
                    ><strong>{{ activeCount }}</strong
                    ><small>Available for product selection</small>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Attribute type</span
                    ><strong class="type-stat">{{
                        titleCase(attribute?.type || '—')
                    }}</strong
                    ><small>{{ attribute?.slug || 'Attribute slug' }}</small>
                </article>
            </section>
            <section class="table-card">
                <div class="toolbar">
                    <label class="search-field"
                        ><Icon name="search" :size="16" /><input
                            v-model="search"
                            type="search"
                            placeholder="Search value, slug or color code..."
                    /></label>
                    <div class="toolbar-actions">
                        <select
                            v-model="statusFilter"
                            aria-label="Filter by status"
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option></select
                        ><button
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
                                <th>VALUE</th>
                                <th>SLUG</th>
                                <th>COLOR</th>
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
                                    attribute values…
                                </td>
                            </tr>
                            <tr v-else-if="filteredValues.length === 0">
                                <td colspan="7" class="state-cell">
                                    <Icon
                                        name="list-checks"
                                        :size="22"
                                    /><strong>No values found</strong
                                    ><span
                                        >Create the first value for this
                                        attribute.</span
                                    >
                                </td>
                            </tr>
                            <tr
                                v-for="item in filteredValues"
                                v-else
                                :key="item.id"
                            >
                                <td>
                                    <div class="identity-cell">
                                        <span
                                            class="color-swatch"
                                            :style="{
                                                background:
                                                    item.color_code ||
                                                    'var(--surface-muted)',
                                            }"
                                            ><Icon
                                                v-if="!item.color_code"
                                                name="list-checks"
                                                :size="14"
                                        /></span>
                                        <div>
                                            <strong>{{ item.name }}</strong
                                            ><small>{{ item.value }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="slug-cell">{{ item.slug }}</td>
                                <td>
                                    <span
                                        v-if="item.color_code"
                                        class="color-code"
                                        ><i
                                            :style="{
                                                background: item.color_code,
                                            }"
                                        ></i
                                        >{{ item.color_code }}</span
                                    ><span v-else>—</span>
                                </td>
                                <td>
                                    <span
                                        class="status-pill"
                                        :class="item.status"
                                        >{{ titleCase(item.status) }}</span
                                    >
                                </td>
                                <td class="numeric-cell">
                                    {{ item.sort_order }}
                                </td>
                                <td class="date-cell">
                                    {{
                                        formatDate(
                                            item.updated_at || item.created_at,
                                        )
                                    }}
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button
                                            class="icon-btn"
                                            type="button"
                                            title="Edit value"
                                            aria-label="Edit value"
                                            @click="openEdit(item)"
                                        >
                                            <Icon
                                                name="pencil"
                                                :size="15"
                                            /></button
                                        ><button
                                            class="icon-btn danger"
                                            type="button"
                                            title="Delete value"
                                            aria-label="Delete value"
                                            :disabled="deletingId === item.id"
                                            @click="deleteValue(item)"
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
                        >Showing <strong>{{ filteredValues.length }}</strong> of
                        <strong>{{ totalCount }}</strong> values</span
                    ><span
                        >{{ attribute?.name || 'Attribute' }}
                        <b>•</b> Values</span
                    >
                </footer>
            </section>
            <Transition name="modal"
                ><div
                    v-if="modalOpen"
                    class="modal-backdrop"
                    @click.self="closeModal"
                >
                    <section
                        class="modal-panel"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="value-modal-title"
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
                                        ? 'UPDATE ATTRIBUTE VALUE'
                                        : 'NEW ATTRIBUTE VALUE'
                                }}</span>
                                <h2 id="value-modal-title">
                                    {{
                                        editing ? 'Edit Value' : 'Create Value'
                                    }}
                                </h2>
                                <p>
                                    {{
                                        editing
                                            ? 'Update the value details and save your changes.'
                                            : `Add a value to ${attribute?.name || 'this attribute'}.`
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
                            ><span
                                :class="{
                                    active:
                                        form.value.trim() || form.name.trim(),
                                }"
                            ></span
                            ><span :class="{ active: form.status }"></span>
                        </div>
                        <form class="modal-body" @submit.prevent="saveValue">
                            <div class="form-section-title">
                                <Icon name="fingerprint" :size="15" />
                                <div>
                                    <strong>Value identity</strong
                                    ><small
                                        >Define the label and stored
                                        value</small
                                    >
                                </div>
                            </div>
                            <label class="field"
                                ><span>Attribute <b>*</b></span
                                ><input
                                    :value="attribute?.name || 'Loading…'"
                                    readonly
                                    disabled
                            /></label>
                            <div class="form-grid two">
                                <label class="field"
                                    ><span>Value name <b>*</b></span
                                    ><input
                                        v-model="form.name"
                                        maxlength="150"
                                        required
                                        placeholder="e.g. Black"
                                        :class="{
                                            invalid: validationErrors.name,
                                        }"
                                    /><small
                                        v-if="validationErrors.name"
                                        class="field-error"
                                        >{{ validationErrors.name }}</small
                                    ></label
                                ><label class="field"
                                    ><span>Stored value</span
                                    ><input
                                        v-model="form.value"
                                        maxlength="180"
                                        placeholder="Defaults to value name"
                                        :class="{
                                            invalid: validationErrors.value,
                                        }"
                                    /><small
                                        v-if="validationErrors.value"
                                        class="field-error"
                                        >{{ validationErrors.value }}</small
                                    ></label
                                >
                            </div>
                            <div class="form-grid two">
                                <label class="field"
                                    ><span
                                        >Color code
                                        <small>(optional)</small></span
                                    >
                                    <div class="color-input">
                                        <input
                                            v-model="form.color_code"
                                            type="color"
                                            aria-label="Choose color"
                                        /><input
                                            v-model="form.color_code"
                                            maxlength="7"
                                            placeholder="#000000"
                                            :class="{
                                                invalid:
                                                    validationErrors.color_code,
                                            }"
                                        />
                                    </div>
                                    <small
                                        v-if="validationErrors.color_code"
                                        class="field-error"
                                        >{{
                                            validationErrors.color_code
                                        }}</small
                                    ></label
                                ><label class="field"
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
                            </div>
                            <label class="field"
                                ><span>Sort order</span
                                ><input
                                    v-model.number="form.sort_order"
                                    type="number"
                                    min="0"
                                    step="1"
                                    required
                            /></label>
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
                                              : 'Create value'
                                    }}
                                </button>
                            </div>
                        </form>
                    </section>
                </div></Transition
            >
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
                            <h2 id="delete-dialog-title">Delete this value?</h2>
                            <p id="delete-dialog-description">
                                This value will be removed from the available
                                options for this attribute. This action cannot
                                be undone.
                            </p>
                        </div>
                        <div class="delete-record">
                            <span class="record-label">Attribute value</span
                            ><strong>{{ deleteTarget.name }}</strong
                            ><span class="record-meta"
                                >Slug: {{ deleteTarget.slug }}</span
                            ><span class="record-meta"
                                >Attribute: {{ attribute?.name || '—' }}</span
                            >
                        </div>
                        <div class="delete-warning">
                            <Icon name="info" :size="15" /><span
                                >Products using this value may be affected. Make
                                sure it is no longer needed before
                                deleting.</span
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
                                class="btn danger-solid"
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
                                        : 'Delete value'
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
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 600;
}
.breadcrumb button {
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--text-secondary);
    font: inherit;
    cursor: pointer;
}
.breadcrumb button:hover {
    color: var(--primary);
}
.breadcrumb-separator {
    color: var(--border-strong);
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
    font-size: 25px;
    line-height: 1.1;
    font-weight: 750;
    letter-spacing: -0.03em;
}
.stat-card strong.type-stat {
    font-size: 20px;
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
.color-swatch {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 1px solid var(--border);
    border-radius: 9px;
    color: var(--text-secondary);
}
.slug-cell {
    font-size: 11px;
}
.color-code {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
}
.color-code i {
    width: 13px;
    height: 13px;
    border: 1px solid var(--border);
    border-radius: 4px;
}
.status-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
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
.numeric-cell {
    font-variant-numeric: tabular-nums;
}
.date-cell {
    font-size: 11px;
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
.field input:disabled {
    opacity: 0.75;
}
.color-input {
    display: flex;
    gap: 7px;
}
.color-input input[type='color'] {
    width: 48px;
    min-width: 48px;
    padding: 4px;
}
.color-input input[type='text'] {
    flex: 1;
    min-width: 0;
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
        flex-wrap: wrap;
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
    }
}

/* Keep the attribute values page aligned with Product Categories, Brands and Units. */
.attributes-page {
    padding: 0;
}
.page-header {
    gap: 24px;
    margin-bottom: 18px;
}
.breadcrumb {
    gap: 8px;
    margin-bottom: 8px;
    line-height: 1.2;
}
.title-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
}
.title-row h1 {
    font-size: 24px;
}
.stats-grid {
    gap: 12px;
    margin-bottom: 14px;
}
.stat-card {
    padding: 13px 15px;
    border-radius: var(--product-radius, 14px);
}
.table-card {
    border-radius: var(--product-radius, 14px);
}
.toolbar {
    gap: 12px;
    padding: 12px;
}
th {
    padding: 12px 14px;
}
td {
    padding: 11px 14px;
}
@media (max-width: 900px) {
    .page-header {
        align-items: flex-start;
    }
}
@media (max-width: 620px) {
    .page-header {
        flex-direction: column;
        gap: 12px;
    }
    .page-actions {
        width: 100%;
        flex-wrap: wrap;
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

/* Consistent, accessible destructive-action confirmation used across master-data pages. */
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
.btn.danger-solid {
    border-color: #b91c1c;
    background: #dc2626;
    color: #fff;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.18);
}
.btn.danger-solid:hover:not(:disabled) {
    border-color: #991b1b;
    background: #b91c1c;
}
.btn.danger-solid:disabled {
    opacity: 0.65;
    cursor: wait;
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
    transform: translateY(6px) scale(0.985);
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
</style>
