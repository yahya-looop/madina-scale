<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import Icon from "@/components/dashboard/Icon.vue";

interface ApiPayload<T> {
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
}

interface VariantAttributeSelection {
  id?: number;
  attribute_id: number;
  attribute_value_id: number;
  attribute?: { id: number; name: string } | null;
  attributeValue?: { id: number; value: string } | null;
  attribute_value?: { id: number; value: string } | null;
}

interface CatalogAttribute {
  id: number;
  name: string;
  type: string;
  status: string;
}

interface CatalogAttributeValue {
  id: number;
  attribute_id: number;
  name: string;
  value: string;
  color_code?: string | null;
  status: string;
}

interface ProductVariant {
  id: number;
  product_id: number;
  name: string | null;
  sku: string | null;
  barcode: string | null;
  cost_price: string | number;
  selling_price: string | number;
  wholesale_price: string | number | null;
  promotional_price: string | number | null;
  promotion_starts_at: string | null;
  promotion_ends_at: string | null;
  is_default: boolean;
  status: "active" | "inactive" | "archived";
  sort_order: number;
  attribute_values?: VariantAttributeSelection[];
}

interface VariantForm {
  name: string;
  sku: string;
  barcode: string;
  cost_price: string;
  selling_price: string;
  wholesale_price: string;
  promotional_price: string;
  promotion_starts_at: string;
  promotion_ends_at: string;
  is_default: boolean;
  status: "active" | "inactive" | "archived";
  sort_order: number;
}

const props = defineProps<{ productId: number }>();

const variants = ref<ProductVariant[]>([]);
const attributes = ref<CatalogAttribute[]>([]);
const attributeValues = ref<Record<number, CatalogAttributeValue[]>>({});
const selectedAttributeValues = ref<Record<number, number | "">>({});
const isLoadingAttributes = ref(false);
const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref<number | null>(null);
const pendingDeleteVariant = ref<ProductVariant | null>(null);
const errorMessage = ref("");
const successMessage = ref("");
const isFormOpen = ref(false);
const editingId = ref<number | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});
const form = ref<VariantForm>(emptyForm());

function emptyForm(): VariantForm {
  return {
    name: "",
    sku: "",
    barcode: "",
    cost_price: "0",
    selling_price: "0",
    wholesale_price: "",
    promotional_price: "",
    promotion_starts_at: "",
    promotion_ends_at: "",
    is_default: false,
    status: "active",
    sort_order: 0,
  };
}

function csrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
}

async function request<T>(
  url: string,
  method: "GET" | "POST" | "PUT" | "DELETE" = "GET",
  body?: Record<string, unknown>,
): Promise<T> {
  const token = csrfToken();
  const response = await fetch(url, {
    method,
    headers: {
      Accept: "application/json",
      ...(body ? { "Content-Type": "application/json" } : {}),
      ...(token ? { "X-CSRF-TOKEN": token } : {}),
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
    ...(body ? { body: JSON.stringify(body) } : {}),
  });

  const payload = (await response.json().catch(() => ({}))) as ApiPayload<T>;
  if (!response.ok) {
    if (response.status === 422 && payload.errors) fieldErrors.value = payload.errors;
    throw new Error(payload.message ?? "Unable to complete the request.");
  }
  return payload.data as T;
}

async function loadAttributes(): Promise<void> {
  isLoadingAttributes.value = true;
  try {
    const result = await request<CatalogAttribute[]>("/api/attributes?status=active");
    // The backend only accepts selectable attributes for variants. These are
    // attributes whose values represent a concrete variant choice.
    attributes.value = result.filter((attribute) =>
      ["select", "multiselect", "color", "boolean"].includes(attribute.type)
      && attribute.status === "active"
    );
    await Promise.all(attributes.value.map((attribute) => loadAttributeValues(attribute.id)));
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "Unable to load attributes.";
  } finally {
    isLoadingAttributes.value = false;
  }
}

async function loadAttributeValues(attributeId: number): Promise<void> {
  try {
    const values = await request<CatalogAttributeValue[]>(
      `/api/attribute-values?attribute_id=${attributeId}&status=active`,
    );
    attributeValues.value[attributeId] = values.filter((value) => value.status === "active");
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "Unable to load attribute values.";
  }
}

function syncSelectedAttributes(selections: VariantAttributeSelection[] = []): void {
  const next: Record<number, number | ""> = {};
  for (const attribute of attributes.value) next[attribute.id] = "";
  for (const selection of selections) {
    if (selection.attribute_id in next) next[selection.attribute_id] = selection.attribute_value_id;
  }
  selectedAttributeValues.value = next;
}

async function loadVariants(): Promise<void> {
  isLoading.value = true;
  errorMessage.value = "";
  try {
    variants.value = await request<ProductVariant[]>(`/api/products/${props.productId}/variants`);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "Unable to load variants.";
  } finally {
    isLoading.value = false;
  }
}

function openCreate(): void {
  editingId.value = null;
  form.value = emptyForm();
  fieldErrors.value = {};
  errorMessage.value = "";
  successMessage.value = "";
  syncSelectedAttributes();
  isFormOpen.value = true;
}

function toLocalDateTime(value: string | null): string {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value.slice(0, 16);
  return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
}

function openEdit(variant: ProductVariant): void {
  editingId.value = variant.id;
  form.value = {
    name: variant.name ?? "",
    sku: variant.sku ?? "",
    barcode: variant.barcode ?? "",
    cost_price: String(variant.cost_price ?? 0),
    selling_price: String(variant.selling_price ?? 0),
    wholesale_price: variant.wholesale_price == null ? "" : String(variant.wholesale_price),
    promotional_price: variant.promotional_price == null ? "" : String(variant.promotional_price),
    promotion_starts_at: toLocalDateTime(variant.promotion_starts_at),
    promotion_ends_at: toLocalDateTime(variant.promotion_ends_at),
    is_default: Boolean(variant.is_default),
    status: variant.status ?? "active",
    sort_order: Number(variant.sort_order ?? 0),
  };
  fieldErrors.value = {};
  errorMessage.value = "";
  successMessage.value = "";
  syncSelectedAttributes(variant.attribute_values ?? []);
  isFormOpen.value = true;
}

function fieldError(field: string): string {
  return fieldErrors.value[field]?.[0] ?? "";
}

function payload(): Record<string, unknown> {
  return {
    name: form.value.name.trim() || null,
    sku: form.value.sku.trim() || null,
    barcode: form.value.barcode.trim() || null,
    cost_price: Number(form.value.cost_price || 0),
    selling_price: Number(form.value.selling_price || 0),
    wholesale_price: form.value.wholesale_price === "" ? null : Number(form.value.wholesale_price),
    promotional_price: form.value.promotional_price === "" ? null : Number(form.value.promotional_price),
    promotion_starts_at: form.value.promotion_starts_at || null,
    promotion_ends_at: form.value.promotion_ends_at || null,
    is_default: form.value.is_default,
    status: form.value.status,
    sort_order: Number(form.value.sort_order || 0),
    attribute_values: Object.entries(selectedAttributeValues.value)
      .filter(([, valueId]) => valueId !== "")
      .map(([attributeId, valueId]) => ({
        attribute_id: Number(attributeId),
        attribute_value_id: Number(valueId),
      })),
  };
}

async function saveVariant(): Promise<void> {
  if (isSaving.value) return;
  isSaving.value = true;
  errorMessage.value = "";
  successMessage.value = "";
  fieldErrors.value = {};
  const editing = editingId.value !== null;
  const url = editing
    ? `/api/products/${props.productId}/variants/${editingId.value}`
    : `/api/products/${props.productId}/variants`;
  try {
    await request<ProductVariant>(url, editing ? "PUT" : "POST", payload());
    isFormOpen.value = false;
    successMessage.value = editing ? "Variant updated successfully." : "Variant created successfully.";
    await loadVariants();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "Unable to save the variant.";
  } finally {
    isSaving.value = false;
  }
}

function deleteVariant(variant: ProductVariant): void {
  if (deletingId.value !== null) return;
  errorMessage.value = "";
  pendingDeleteVariant.value = variant;
}

function closeDeleteModal(): void {
  if (deletingId.value !== null) return;
  pendingDeleteVariant.value = null;
}

async function confirmDeleteVariant(): Promise<void> {
  const variant = pendingDeleteVariant.value;
  if (!variant || deletingId.value !== null) return;

  deletingId.value = variant.id;
  errorMessage.value = "";
  successMessage.value = "";
  try {
    await request(`/api/products/${props.productId}/variants/${variant.id}`, "DELETE");
    pendingDeleteVariant.value = null;
    successMessage.value = "Variant moved to trash.";
    await loadVariants();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "Unable to delete the variant.";
  } finally {
    deletingId.value = null;
  }
}

function money(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === "") return "—";
  const amount = Number(value);
  return Number.isFinite(amount)
    ? new Intl.NumberFormat("en-BD", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount)
    : String(value);
}

function dateTime(value: string | null): string {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" }).format(date);
}

onMounted(() => { void loadAttributes(); void loadVariants(); });
watch(() => props.productId, () => void loadVariants());
</script>

<template>
  <section class="card variants-card">
    <div class="section-heading variants-heading">
      <div class="section-icon"><Icon name="stack-2" :size="18" /></div>
      <div>
        <h2>Product Variants</h2>
        <p>Manage variant identifiers, pricing, promotions and the default selection.</p>
      </div>
      <span class="section-count">{{ variants.length }} {{ variants.length === 1 ? "variant" : "variants" }}</span>
      <button type="button" class="primary-button variant-add-button" @click="openCreate">
        <Icon name="plus" :size="15" /> Add Variant
      </button>
    </div>

    <div v-if="successMessage" class="variant-success" role="status">
      <Icon name="check" :size="15" /> <span>{{ successMessage }}</span>
      <button type="button" aria-label="Dismiss message" @click="successMessage = ''">×</button>
    </div>
    <div v-if="errorMessage" class="variant-error" role="alert">
      <Icon name="info" :size="15" /><span>{{ errorMessage }}</span>
      <button type="button" @click="loadVariants">Retry</button>
    </div>

    <div v-if="isLoading" class="variants-state">
      <Icon name="loader-2" class="spin" :size="21" /> Loading variants…
    </div>
    <div v-else-if="variants.length === 0" class="variants-state variants-empty">
      <div class="no-variants-icon"><Icon name="package" :size="25" /></div>
      <strong>No variants yet</strong>
      <span>Create the first variant to assign a SKU, barcode and variant-specific prices.</span>
      <button type="button" class="primary-button" @click="openCreate"><Icon name="plus" :size="15" /> Create first variant</button>
    </div>
    <div v-else class="variants-table-wrap">
      <table class="variants-table">
        <thead>
          <tr><th>Variant</th><th>SKU / Barcode</th><th>Cost</th><th>Selling</th><th>Promotion</th><th>Status</th><th class="actions-heading">Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="variant in variants" :key="variant.id">
            <td>
              <div class="variant-name-cell">
                <strong>{{ variant.name || `Variant #${variant.id}` }}</strong>
                <span v-if="variant.is_default" class="default-badge"><Icon name="check" :size="11" /> Default</span>
                <small v-if="variant.attribute_values?.length">
                  {{ variant.attribute_values.map((item) => `${item.attribute?.name ?? "Attribute"}: ${item.attributeValue?.value ?? item.attribute_value?.value ?? "—"}`).join(" · ") }}
                </small>
              </div>
            </td>
            <td><div class="identifiers"><span><b>SKU</b>{{ variant.sku || "—" }}</span><span><b>EAN</b>{{ variant.barcode || "—" }}</span></div></td>
            <td class="money">{{ money(variant.cost_price) }}</td>
            <td class="money selling">{{ money(variant.selling_price) }}</td>
            <td><div class="promotion"><strong>{{ money(variant.promotional_price) }}</strong><small v-if="variant.promotion_starts_at || variant.promotion_ends_at">{{ dateTime(variant.promotion_starts_at) }} – {{ dateTime(variant.promotion_ends_at) }}</small></div></td>
            <td><span class="variant-status" :class="variant.status">{{ variant.status }}</span></td>
            <td><div class="row-actions">
              <button type="button" class="icon-action" title="Edit variant" :aria-label="`Edit ${variant.name || variant.sku || 'variant'}`" @click="openEdit(variant)"><Icon name="edit" :size="14" /></button>
              <button type="button" class="icon-action delete-action" title="Move variant to trash" :aria-label="`Delete ${variant.name || variant.sku || 'variant'}`" :disabled="deletingId === variant.id" @click="deleteVariant(variant)">
                <Icon v-if="deletingId === variant.id" name="loader-2" class="spin" :size="14" /><Icon v-else name="trash" :size="14" />
              </button>
            </div></td>
          </tr>
        </tbody>
      </table>
    </div>

    <form v-if="isFormOpen" class="variant-form" @submit.prevent="saveVariant">
      <div class="form-heading">
        <div><h3>{{ editingId ? "Edit variant" : "Create variant" }}</h3><p>SKU and barcode must be unique across active products and variants.</p></div>
        <button type="button" class="close-form" aria-label="Close form" :disabled="isSaving" @click="isFormOpen = false">×</button>
      </div>
      <div class="form-grid">
        <label class="field"><span>Variant name</span><input v-model="form.name" type="text" maxlength="200" placeholder="e.g. 500 ml / Blue / XL" /><small v-if="fieldError('name')" class="field-error">{{ fieldError("name") }}</small></label>
        <label class="field"><span>SKU</span><input v-model="form.sku" type="text" maxlength="100" placeholder="Unique SKU" /><small v-if="fieldError('sku')" class="field-error">{{ fieldError("sku") }}</small></label>
        <label class="field"><span>Barcode</span><input v-model="form.barcode" type="text" maxlength="100" placeholder="Barcode / EAN" /><small v-if="fieldError('barcode')" class="field-error">{{ fieldError("barcode") }}</small></label>
        <label class="field"><span>Status</span><select v-model="form.status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="archived">Archived</option></select><small v-if="fieldError('status')" class="field-error">{{ fieldError("status") }}</small></label>
        <label class="field"><span>Cost price</span><input v-model="form.cost_price" type="number" min="0" step="0.01" required /><small v-if="fieldError('cost_price')" class="field-error">{{ fieldError("cost_price") }}</small></label>
        <label class="field"><span>Selling price</span><input v-model="form.selling_price" type="number" min="0" step="0.01" required /><small v-if="fieldError('selling_price')" class="field-error">{{ fieldError("selling_price") }}</small></label>
        <label class="field"><span>Wholesale price <em>Optional</em></span><input v-model="form.wholesale_price" type="number" min="0" step="0.01" /><small v-if="fieldError('wholesale_price')" class="field-error">{{ fieldError("wholesale_price") }}</small></label>
        <label class="field"><span>Promotional price <em>Optional</em></span><input v-model="form.promotional_price" type="number" min="0" step="0.01" /><small v-if="fieldError('promotional_price')" class="field-error">{{ fieldError("promotional_price") }}</small></label>
        <label class="field"><span>Promotion starts <em>Optional</em></span><input v-model="form.promotion_starts_at" type="datetime-local" /><small v-if="fieldError('promotion_starts_at')" class="field-error">{{ fieldError("promotion_starts_at") }}</small></label>
        <label class="field"><span>Promotion ends <em>Optional</em></span><input v-model="form.promotion_ends_at" type="datetime-local" /><small v-if="fieldError('promotion_ends_at')" class="field-error">{{ fieldError("promotion_ends_at") }}</small></label>
        <label class="field"><span>Sort order</span><input v-model.number="form.sort_order" type="number" min="0" step="1" /><small v-if="fieldError('sort_order')" class="field-error">{{ fieldError("sort_order") }}</small></label>
        <div v-if="isLoadingAttributes" class="attribute-loading">Loading selectable attributes…</div>
        <template v-else-if="attributes.length">
          <label v-for="attribute in attributes" :key="attribute.id" class="field">
            <span>{{ attribute.name }} <em>Variant attribute</em></span>
            <select v-model="selectedAttributeValues[attribute.id]">
              <option value="">No selection</option>
              <option v-for="value in (attributeValues[attribute.id] ?? [])" :key="value.id" :value="value.id">
                {{ value.name }}{{ value.value && value.value !== value.name ? ` (${value.value})` : "" }}
              </option>
            </select>
          </label>
        </template>
        <p v-else class="attribute-loading">No active selectable attributes are configured. You can still create a variant without attributes.</p>
        <label class="default-field"><input v-model="form.is_default" type="checkbox" /><span><strong>Default variant</strong><small>Only one active variant can be the default for this product.</small></span></label>
      </div>
      <div class="form-actions">
        <button type="button" class="secondary-button" :disabled="isSaving" @click="isFormOpen = false">Cancel</button>
        <button type="submit" class="primary-button" :disabled="isSaving"><Icon v-if="isSaving" name="loader-2" class="spin" :size="15" /><Icon v-else name="check" :size="15" />{{ isSaving ? "Saving…" : editingId ? "Save changes" : "Create variant" }}</button>
      </div>
    </form>

    <Teleport to="body">
      <div
        v-if="pendingDeleteVariant"
        class="delete-modal-backdrop"
        @click.self="closeDeleteModal"
        @keydown.esc="closeDeleteModal"
      >
        <section
          class="delete-modal"
          role="alertdialog"
          aria-modal="true"
          aria-labelledby="delete-variant-title"
          aria-describedby="delete-variant-description"
        >
          <div class="delete-modal-topline">
            <div class="delete-modal-icon"><Icon name="trash" :size="22" /></div>
            <button
              type="button"
              class="delete-modal-close"
              aria-label="Close delete confirmation"
              :disabled="deletingId !== null"
              @click="closeDeleteModal"
            >×</button>
          </div>
          <div class="delete-modal-copy">
            <span class="delete-modal-eyebrow">Confirm deletion</span>
            <h3 id="delete-variant-title">Move this variant to trash?</h3>
            <p id="delete-variant-description">
              You are about to move this variant out of the active product catalog. Please review the details before continuing.
            </p>
          </div>

          <div class="delete-variant-summary">
            <div class="delete-variant-summary-heading">
              <div class="delete-variant-product-icon"><Icon name="package" :size="20" /></div>
              <div class="delete-variant-title-group">
                <strong>{{ pendingDeleteVariant.name || `Variant #${pendingDeleteVariant.id}` }}</strong>
                <span>Variant ID · {{ pendingDeleteVariant.id }}</span>
              </div>
              <span class="variant-status" :class="pendingDeleteVariant.status">{{ pendingDeleteVariant.status }}</span>
            </div>
            <div class="delete-variant-details">
              <div><span>SKU</span><strong>{{ pendingDeleteVariant.sku || "—" }}</strong></div>
              <div><span>Barcode / EAN</span><strong>{{ pendingDeleteVariant.barcode || "—" }}</strong></div>
              <div><span>Cost price</span><strong>{{ money(pendingDeleteVariant.cost_price) }}</strong></div>
              <div><span>Selling price</span><strong>{{ money(pendingDeleteVariant.selling_price) }}</strong></div>
              <div v-if="pendingDeleteVariant.attribute_values?.length" class="delete-variant-attributes">
                <span>Attributes</span>
                <strong>{{ pendingDeleteVariant.attribute_values.map((item) => `${item.attribute?.name ?? "Attribute"}: ${item.attributeValue?.value ?? item.attribute_value?.value ?? "—"}`).join(" · ") }}</strong>
              </div>
            </div>
            <div v-if="pendingDeleteVariant.is_default" class="delete-default-warning">
              <Icon name="alert-triangle" :size="15" />
              <span>This is currently the default variant for this product.</span>
            </div>
          </div>

          <div class="delete-modal-note">
            <Icon name="info" :size="15" />
            <span>This action moves the variant to trash. Confirm only if you intend to remove this variant from active use.</span>
          </div>
          <div class="delete-modal-actions">
            <button type="button" class="secondary-button" :disabled="deletingId !== null" @click="closeDeleteModal">Cancel</button>
            <button type="button" class="danger-button" :disabled="deletingId !== null" @click="confirmDeleteVariant">
              <Icon v-if="deletingId === pendingDeleteVariant.id" name="loader-2" class="spin" :size="15" />
              <Icon v-else name="trash" :size="15" />
              {{ deletingId === pendingDeleteVariant.id ? "Moving to trash…" : "Move to trash" }}
            </button>
          </div>
        </section>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
/* Component-owned equivalents of the page's scoped design primitives.
   Vue scoped CSS does not cross the parent/child component boundary. */
.variants-card {
  margin: 0 0 18px;
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 16px;
  background: var(--surface, #fff);
  box-shadow: 0 2px 8px rgb(15 23 42 / 3%);
  color: var(--text-primary, #0f172a);
}
.variants-heading {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 18px 20px;
  border-bottom: 1px solid var(--border, #e2e8f0);
}
.variants-heading > div:nth-child(2) { min-width: 0; flex: 1 1 220px; }
.variants-heading h2 { margin: 0; color: var(--text-primary, #0f172a); font-size: 14px; font-weight: 750; letter-spacing: -.02em; }
.variants-heading p { margin: 4px 0 0; color: var(--text-muted, #64748b); font-size: 11px; line-height: 1.55; }
.section-icon {
  width: 38px; height: 38px; flex: 0 0 38px;
  display: grid; place-items: center;
  border: 1px solid color-mix(in srgb, var(--primary, #059669) 22%, var(--border, #e2e8f0));
  border-radius: 11px;
  background: color-mix(in srgb, var(--primary, #059669) 7%, var(--surface, #fff));
  color: var(--primary, #059669);
}
.section-count {
  display: inline-flex; align-items: center; justify-content: center;
  min-height: 27px; padding: 0 9px; white-space: nowrap;
  border: 1px solid var(--border, #e2e8f0); border-radius: 8px;
  background: var(--surface-muted, #f8fafc);
  color: var(--text-muted, #64748b); font-size: 10px; font-weight: 650;
}
.primary-button, .secondary-button {
  min-height: 36px; display: inline-flex; align-items: center; justify-content: center;
  gap: 7px; padding: 0 12px; border: 1px solid transparent; border-radius: 9px;
  font: inherit; font-size: 11px; font-weight: 700; cursor: pointer;
  transition: background .16s ease, border-color .16s ease, transform .16s ease;
}
.primary-button { background: var(--primary, #059669); color: #fff; box-shadow: 0 3px 8px color-mix(in srgb, var(--primary, #059669) 18%, transparent); }
.primary-button:hover:not(:disabled) { filter: brightness(.96); transform: translateY(-1px); }
.secondary-button { border-color: var(--border, #e2e8f0); background: var(--surface, #fff); color: var(--text-secondary, #475569); }
.secondary-button:hover:not(:disabled) { background: var(--surface-muted, #f8fafc); }
.primary-button:disabled, .secondary-button:disabled { opacity: .55; cursor: not-allowed; }
.variant-add-button { margin-left: auto; white-space: nowrap; }
.variants-table-wrap { width: 100%; overflow-x: auto; }
.variants-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 11px; }
.variants-table th { padding: 12px 16px; border-bottom: 1px solid var(--border, #e2e8f0); background: var(--surface-muted, #f8fafc); color: var(--text-muted, #64748b); font-size: 10px; font-weight: 750; white-space: nowrap; }
.variants-table td { padding: 13px 16px; border-bottom: 1px solid var(--border, #e2e8f0); color: var(--text-secondary, #475569); vertical-align: middle; }
.variants-table tbody tr:last-child td { border-bottom: 0; }
.variants-table tbody tr:hover { background: color-mix(in srgb, var(--surface-muted, #f8fafc) 60%, transparent); }
.variant-name-cell, .identifiers, .promotion { min-width: 100px; display: flex; flex-direction: column; gap: 5px; }
.variant-name-cell strong { color: var(--text-primary, #0f172a); font-size: 12px; font-weight: 750; }
.variant-name-cell small, .promotion small { max-width: 260px; color: var(--text-muted, #64748b); font-size: 10px; line-height: 1.5; }
.default-badge { width: fit-content; display: inline-flex; align-items: center; gap: 4px; padding: 3px 7px; border: 1px solid color-mix(in srgb, var(--success, #059669) 25%, var(--border, #e2e8f0)); border-radius: 6px; background: color-mix(in srgb, var(--success, #059669) 7%, var(--surface, #fff)); color: var(--success, #059669); font-size: 9px; font-weight: 750; }
.identifiers span { color: var(--text-secondary, #475569); font-size: 10px; white-space: nowrap; }
.identifiers b { display: inline-block; width: 34px; color: var(--text-muted, #64748b); font-size: 9px; }
.money { font-variant-numeric: tabular-nums; white-space: nowrap; }
.selling { color: var(--text-primary, #0f172a) !important; font-weight: 750; }
.promotion strong { color: var(--text-primary, #0f172a); font-size: 11px; }
.variant-status { display: inline-flex; padding: 5px 8px; border-radius: 7px; background: var(--surface-muted, #f8fafc); color: var(--text-secondary, #475569); font-size: 9px; font-weight: 750; text-transform: capitalize; }
.variant-status.active { background: color-mix(in srgb, var(--success, #059669) 9%, var(--surface, #fff)); color: var(--success, #059669); }
.variant-status.inactive { color: var(--text-muted, #64748b); }
.variant-status.archived { background: color-mix(in srgb, #8250e8 8%, var(--surface, #fff)); color: #8250e8; }
.actions-heading { text-align: right; }
.row-actions { display: flex; justify-content: flex-end; gap: 6px; }
.icon-action, .close-form { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border, #e2e8f0); border-radius: 8px; background: var(--surface, #fff); color: var(--text-secondary, #475569); cursor: pointer; }
.icon-action:hover { border-color: color-mix(in srgb, var(--primary, #059669) 35%, var(--border, #e2e8f0)); color: var(--primary, #059669); }
.delete-action:hover:not(:disabled) { border-color: rgb(239 68 68 / 30%); color: #dc2626; }
.icon-action:disabled { opacity: .5; cursor: not-allowed; }
.variants-state { min-height: 140px; display: flex; align-items: center; justify-content: center; gap: 9px; color: var(--text-muted, #64748b); font-size: 12px; }
.variants-empty { flex-direction: column; gap: 9px; padding: 30px 22px; text-align: center; }
.no-variants-icon { width: 50px; height: 50px; display: grid; place-items: center; border: 1px solid var(--border, #e2e8f0); border-radius: 14px; background: var(--surface-muted, #f8fafc); color: var(--primary, #059669); }
.variants-empty strong { color: var(--text-primary, #0f172a); font-size: 13px; }
.variants-empty > span { max-width: 430px; color: var(--text-muted, #64748b); font-size: 11px; line-height: 1.55; }
.variants-empty .primary-button { margin-top: 4px; }
.variant-error, .variant-success { display: flex; align-items: center; gap: 8px; margin: 12px 16px 0; padding: 10px 12px; border: 1px solid rgb(220 38 38 / 18%); border-radius: 9px; background: rgb(220 38 38 / 5%); color: #b91c1c; font-size: 11px; }
.variant-error span, .variant-success span { flex: 1; }
.variant-error button, .variant-success button { border: 0; background: transparent; color: inherit; font: inherit; font-weight: 750; cursor: pointer; }
.variant-success { border-color: color-mix(in srgb, var(--success, #059669) 22%, var(--border, #e2e8f0)); background: color-mix(in srgb, var(--success, #059669) 6%, var(--surface, #fff)); color: var(--success, #059669); }
.variant-success button { margin-left: auto; font-size: 17px; }
.variant-form { padding: 18px 20px; border-top: 1px solid var(--border, #e2e8f0); background: color-mix(in srgb, var(--surface-muted, #f8fafc) 45%, var(--surface, #fff)); }
.form-heading { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 17px; }
.form-heading h3 { margin: 0; color: var(--text-primary, #0f172a); font-size: 14px; font-weight: 750; }
.form-heading p { margin: 5px 0 0; color: var(--text-muted, #64748b); font-size: 11px; line-height: 1.5; }
.close-form { flex: 0 0 32px; font-size: 19px; }
.form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 15px; }
.field { min-width: 0; display: flex; flex-direction: column; gap: 7px; }
.field > span { color: var(--text-secondary, #475569); font-size: 11px; font-weight: 700; }
.field em { color: var(--text-muted, #64748b); font-size: 10px; font-style: normal; font-weight: 500; }
.field input, .field select { width: 100%; min-height: 39px; padding: 9px 11px; border: 1px solid var(--border, #e2e8f0); border-radius: 9px; outline: none; background: var(--surface, #fff); color: var(--text-primary, #0f172a); font: inherit; font-size: 11px; }
.field input:focus, .field select:focus { border-color: color-mix(in srgb, var(--primary, #059669) 60%, var(--border, #e2e8f0)); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #059669) 10%, transparent); }
.field-error { color: #dc2626; font-size: 10px; line-height: 1.4; }
.attribute-loading { grid-column: 1 / -1; margin: 0; padding: 10px 12px; border: 1px dashed var(--border, #e2e8f0); border-radius: 9px; color: var(--text-muted, #64748b); font-size: 11px; }\n.default-field { display: flex; align-items: center; gap: 10px; padding: 11px; border: 1px solid var(--border, #e2e8f0); border-radius: 9px; background: var(--surface, #fff); }
.default-field input { width: 16px; height: 16px; accent-color: var(--primary, #059669); }
.default-field span { display: flex; flex-direction: column; gap: 4px; }
.default-field strong { color: var(--text-primary, #0f172a); font-size: 11px; }
.default-field small { color: var(--text-muted, #64748b); font-size: 10px; line-height: 1.4; }
.form-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 18px; }
.form-actions button:disabled, .close-form:disabled { opacity: .55; cursor: not-allowed; }
.spin { animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
:global(html.dark) .variants-card, :global(body.dark) .variants-card, :global([data-theme="dark"]) .variants-card {
  box-shadow: 0 2px 12px rgb(0 0 0 / 12%);
}
@media (max-width: 1000px) {
  .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 700px) {
  .variants-heading { padding: 15px; }
  .variants-heading .section-count { margin-left: auto; }
  .variant-add-button { width: 100%; margin-left: 0; }
  .variants-table { min-width: 760px; }
  .variant-form { padding: 15px; }
  .form-grid { grid-template-columns: 1fr; }
}


/* Focused, accessible delete confirmation modal. */
.delete-modal-backdrop {
  position: fixed;
  z-index: 9999;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow-y: auto;
  padding: 24px;
  background: rgb(15 23 42 / 58%);
  backdrop-filter: blur(5px);
  -webkit-backdrop-filter: blur(5px);
}
.delete-modal {
  width: min(100%, 510px);
  max-height: min(90vh, 820px);
  overflow-y: auto;
  padding: 24px;
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 18px;
  background: var(--surface, #fff);
  color: var(--text-primary, #0f172a);
  box-shadow: 0 24px 80px rgb(2 6 23 / 28%);
  animation: delete-modal-enter .18s ease-out;
}
.delete-modal-topline { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.delete-modal-icon {
  width: 48px; height: 48px; display: grid; place-items: center;
  border: 1px solid rgb(220 38 38 / 20%); border-radius: 14px;
  background: rgb(220 38 38 / 8%); color: #dc2626;
}
.delete-modal-close {
  width: 34px; height: 34px; display: inline-grid; place-items: center;
  border: 1px solid var(--border, #e2e8f0); border-radius: 9px;
  background: var(--surface, #fff); color: var(--text-muted, #64748b);
  font-size: 21px; line-height: 1; cursor: pointer;
}
.delete-modal-close:hover:not(:disabled) { background: var(--surface-muted, #f8fafc); color: var(--text-primary, #0f172a); }
.delete-modal-copy { margin-top: 17px; }
.delete-modal-eyebrow { color: #dc2626; font-size: 10px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
.delete-modal-copy h3 { margin: 6px 0 0; color: var(--text-primary, #0f172a); font-size: 19px; font-weight: 800; letter-spacing: -.025em; }
.delete-modal-copy p { margin: 9px 0 0; color: var(--text-muted, #64748b); font-size: 12px; line-height: 1.65; }
.delete-variant-summary { margin-top: 19px; overflow: hidden; border: 1px solid var(--border, #e2e8f0); border-radius: 12px; background: var(--surface-muted, #f8fafc); }
.delete-variant-summary-heading { display: flex; align-items: center; gap: 11px; padding: 13px; border-bottom: 1px solid var(--border, #e2e8f0); }
.delete-variant-product-icon { width: 38px; height: 38px; flex: 0 0 38px; display: grid; place-items: center; border: 1px solid var(--border, #e2e8f0); border-radius: 10px; background: var(--surface, #fff); color: var(--primary, #059669); }
.delete-variant-title-group { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 4px; }
.delete-variant-title-group strong { overflow-wrap: anywhere; color: var(--text-primary, #0f172a); font-size: 12px; font-weight: 800; }
.delete-variant-title-group span { color: var(--text-muted, #64748b); font-size: 10px; }
.delete-variant-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 13px 16px; padding: 14px; }
.delete-variant-details > div { min-width: 0; display: flex; flex-direction: column; gap: 5px; }
.delete-variant-details > div > span { color: var(--text-muted, #64748b); font-size: 10px; }
.delete-variant-details > div > strong { overflow-wrap: anywhere; color: var(--text-primary, #0f172a); font-size: 11px; font-weight: 750; }
.delete-variant-details .delete-variant-attributes { grid-column: 1 / -1; padding-top: 11px; border-top: 1px solid var(--border, #e2e8f0); }
.delete-default-warning { display: flex; align-items: flex-start; gap: 8px; margin: 0 13px 13px; padding: 10px; border: 1px solid rgb(217 119 6 / 24%); border-radius: 9px; background: rgb(245 158 11 / 8%); color: #b45309; font-size: 10px; line-height: 1.5; }
.delete-default-warning :deep(svg) { flex: 0 0 auto; }
.delete-modal-note { display: flex; align-items: flex-start; gap: 8px; margin-top: 14px; color: var(--text-muted, #64748b); font-size: 10px; line-height: 1.55; }
.delete-modal-note :deep(svg) { flex: 0 0 auto; margin-top: 1px; }
.delete-modal-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 22px; }
.danger-button { min-height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0 14px; border: 1px solid #dc2626; border-radius: 9px; background: #dc2626; color: #fff; font: inherit; font-size: 11px; font-weight: 750; cursor: pointer; transition: background .16s ease, transform .16s ease; }
.danger-button:hover:not(:disabled) { background: #b91c1c; transform: translateY(-1px); }
.danger-button:disabled { opacity: .6; cursor: not-allowed; }
.delete-modal-close:disabled { opacity: .5; cursor: not-allowed; }
@keyframes delete-modal-enter { from { opacity: 0; transform: translateY(7px) scale(.99); } to { opacity: 1; transform: translateY(0) scale(1); } }
:global(html.dark) .delete-modal, :global(body.dark) .delete-modal, :global([data-theme="dark"]) .delete-modal { box-shadow: 0 24px 80px rgb(0 0 0 / 40%); }
@media (max-width: 540px) {
  .delete-modal-backdrop { align-items: flex-end; padding: 12px; }
  .delete-modal { max-height: 92vh; padding: 19px; border-radius: 17px; }
  .delete-modal-copy h3 { font-size: 17px; }
  .delete-variant-details { gap: 12px; padding: 12px; }
  .delete-modal-actions { flex-direction: column-reverse; }
  .delete-modal-actions button { width: 100%; }
}

</style>
