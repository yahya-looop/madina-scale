<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";


interface Props {
  userName?: string;
  userEmail?: string;
}

interface Option {
  id: number;
  name: string;
  short_name?: string;
}

interface ApiPayload<T> {
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
}

const props = withDefaults(defineProps<Props>(), {
  userName: "",
  userEmail: "",
});

const categories = ref<Option[]>([]);
const brands = ref<Option[]>([]);
const units = ref<Option[]>([]);

const isLoadingOptions = ref(true);
const isSaving = ref(false);

const errors = ref<Record<string, string>>({});

const toast = ref({
  visible: false,
  type: "success" as "success" | "error",
  message: "",
});

let toastTimer: ReturnType<typeof setTimeout> | null = null;

const form = ref({
  category_id: "",
  brand_id: "",
  unit_id: "",
  name: "",
  slug: "",
  sku: "",
  model_number: "",
  barcode: "",
  product_type: "simple",
  short_description: "",
  description: "",
  status: "draft",
  visibility: "visible",
  sort_order: 0,
});

const selectedImage = ref<File | null>(null);
const imagePreview = ref("");
const galleryImages = ref<Array<{ file: File; preview: string }>>([]);
const galleryInput = ref<HTMLInputElement | null>(null);
const slugManuallyEdited = ref(false);
const imageInput = ref<HTMLInputElement | null>(null);

const isFormReady = computed(() => {
  return Boolean(
    form.value.category_id &&
      form.value.unit_id &&
      form.value.name.trim() &&
      form.value.product_type &&
      (form.value.product_type !== "simple" || form.value.sku.trim())
  );
});

const selectedCategoryName = computed(() => {
  return (
    categories.value.find((item) => String(item.id) === form.value.category_id)?.name ??
    "Not selected"
  );
});

const selectedBrandName = computed(() => {
  return (
    brands.value.find((item) => String(item.id) === form.value.brand_id)?.name ??
    "No brand"
  );
});

const selectedUnitName = computed(() => {
  const unit = units.value.find((item) => String(item.id) === form.value.unit_id);

  return unit
    ? `${unit.name}${unit.short_name ? ` (${unit.short_name})` : ""}`
    : "Not selected";
});

const productTypeLabel = computed(() => {
  return form.value.product_type === "variable" ? "Variable Product" : "Simple Product";
});

const statusLabel = computed(() => {
  const labels: Record<string, string> = {
    draft: "Draft",
    active: "Active",
    inactive: "Inactive",
    archived: "Archived",
  };

  return labels[form.value.status] ?? form.value.status;
});

function showToast(message: string, type: "success" | "error" = "success") {
  toast.value = {
    visible: true,
    type,
    message,
  };

  if (toastTimer) {
    clearTimeout(toastTimer);
  }

  toastTimer = setTimeout(() => {
    toast.value.visible = false;
  }, 3200);
}

function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
}

async function loadOptions() {
  isLoadingOptions.value = true;

  try {
    const [categoryResponse, brandResponse, unitResponse] = await Promise.all([
      fetch("/api/product-categories"),
      fetch("/api/brands"),
      fetch("/api/units"),
    ]);

    if (!categoryResponse.ok) {
      throw new Error("Unable to load product categories.");
    }

    if (!brandResponse.ok) {
      throw new Error("Unable to load brands.");
    }

    if (!unitResponse.ok) {
      throw new Error("Unable to load units.");
    }

    const categoryPayload = (await categoryResponse.json()) as ApiPayload<Option[]>;

    const brandPayload = (await brandResponse.json()) as ApiPayload<Option[]>;

    const unitPayload = (await unitResponse.json()) as ApiPayload<Option[]>;

    categories.value = categoryPayload.data ?? [];
    brands.value = brandPayload.data ?? [];
    units.value = unitPayload.data ?? [];
  } catch (error) {
    showToast(
      error instanceof Error ? error.message : "Unable to load product options.",
      "error"
    );
  } finally {
    isLoadingOptions.value = false;
  }
}

function slugify(value: string): string {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s-]/g, "")
    .replace(/\s+/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "");
}

watch(
  () => form.value.name,
  (value) => {
    if (!slugManuallyEdited.value) {
      form.value.slug = slugify(value);
    }
  }
);

function onSlugInput() {
  slugManuallyEdited.value = true;
  form.value.slug = slugify(form.value.slug);
  clearError("slug");
}

function generateSlug() {
  form.value.slug = slugify(form.value.name);
  slugManuallyEdited.value = false;
  clearError("slug");
}

function selectImage() {
  imageInput.value?.click();
}

function onImageSelected(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];

  if (!file) {
    return;
  }

  setImage(file);
}

function setImage(file: File) {
  const allowedTypes = ["image/jpeg", "image/png", "image/webp"];

  if (!allowedTypes.includes(file.type)) {
    showToast("Please select a JPG, PNG, or WebP image.", "error");
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    showToast("Image size must be 5 MB or smaller.", "error");
    return;
  }

  if (imagePreview.value) {
    URL.revokeObjectURL(imagePreview.value);
  }

  selectedImage.value = file;
  imagePreview.value = URL.createObjectURL(file);
}

function removeImage() {
  if (imagePreview.value) {
    URL.revokeObjectURL(imagePreview.value);
  }

  selectedImage.value = null;
  imagePreview.value = "";

  if (imageInput.value) {
    imageInput.value.value = "";
  }
}

function onDrop(event: DragEvent) {
  event.preventDefault();

  const file = event.dataTransfer?.files?.[0];

  if (file) {
    setImage(file);
  }
}

function onGallerySelected(event: Event) {
  const input = event.target as HTMLInputElement;
  const files = Array.from(input.files ?? []);
  const available = Math.max(0, 9 - galleryImages.value.length);

  for (const file of files.slice(0, available)) {
    const allowedTypes = ["image/jpeg", "image/png", "image/webp"];
    if (!allowedTypes.includes(file.type) || file.size > 5 * 1024 * 1024) {
      showToast(`${file.name}: use JPG, PNG or WebP up to 5 MB.`, "error");
      continue;
    }
    galleryImages.value.push({ file, preview: URL.createObjectURL(file) });
  }

  if (files.length > available) {
    showToast("A product can have up to 10 images in total.", "error");
  }
  input.value = "";
}

function removeGalleryImage(index: number) {
  const [removed] = galleryImages.value.splice(index, 1);
  if (removed) URL.revokeObjectURL(removed.preview);
}

function onDragOver(event: DragEvent) {
  event.preventDefault();
}

function clearError(field: string) {
  if (errors.value[field]) {
    delete errors.value[field];
  }
}

function formatApiErrors(payload: ApiPayload<unknown>): Record<string, string> {
  const result: Record<string, string> = {};

  if (payload.errors) {
    Object.entries(payload.errors).forEach(([field, messages]) => {
      if (messages.length > 0) {
        result[field] = messages[0];
      }
    });
  }

  return result;
}

async function createProduct() {
  if (isSaving.value) {
    return;
  }

  errors.value = {};

  if (!form.value.category_id) {
    errors.value.category_id = "Please select a category.";
  }

  if (!form.value.unit_id) {
    errors.value.unit_id = "Please select a unit.";
  }

  if (!form.value.name.trim()) {
    errors.value.name = "Product name is required.";
  }

  if (form.value.product_type === "simple" && !form.value.sku.trim()) {
    errors.value.sku = "SKU is required for a simple product.";
  }

  if (Object.keys(errors.value).length > 0) {
    showToast("Please complete the required fields.", "error");
    return;
  }

  isSaving.value = true;

  try {
    const payload = new FormData();

    payload.append("category_id", form.value.category_id);

    if (form.value.brand_id) {
      payload.append("brand_id", form.value.brand_id);
    }

    payload.append("unit_id", form.value.unit_id);
    payload.append("name", form.value.name.trim());

    if (form.value.slug) {
      payload.append("slug", form.value.slug);
    }

    if (form.value.sku.trim()) {
      payload.append("sku", form.value.sku.trim());
    }

    if (form.value.model_number.trim()) {
      payload.append("model_number", form.value.model_number.trim());
    }

    if (form.value.barcode.trim()) {
      payload.append("barcode", form.value.barcode.trim());
    }

    payload.append("product_type", form.value.product_type);

    if (form.value.short_description.trim()) {
      payload.append("short_description", form.value.short_description.trim());
    }

    if (form.value.description.trim()) {
      payload.append("description", form.value.description.trim());
    }

    payload.append("status", form.value.status);
    payload.append("visibility", form.value.visibility);
    payload.append("sort_order", String(form.value.sort_order));

    if (selectedImage.value) {
      payload.append("image", selectedImage.value);
    }
    galleryImages.value.forEach(({ file }) => payload.append("images[]", file));

    const response = await fetch("/api/products", {
      method: "POST",
      headers: {
        Accept: "application/json",
        "X-CSRF-TOKEN": getCsrfToken(),
        "X-Requested-With": "XMLHttpRequest",
      },
      body: payload,
    });

    const responsePayload = (await response.json()) as ApiPayload<{
      id?: number;
      name?: string;
    }>;

    if (!response.ok) {
      errors.value = formatApiErrors(responsePayload);

      const firstError = Object.values(errors.value)[0];

      throw new Error(
        firstError ?? responsePayload.message ?? "Unable to create product."
      );
    }

    showToast(responsePayload.message ?? "Product created successfully.", "success");

    setTimeout(() => {
      router.visit("/products");
    }, 900);
  } catch (error) {
    showToast(
      error instanceof Error ? error.message : "Unable to create product.",
      "error"
    );
  } finally {
    isSaving.value = false;
  }
}

function cancel() {
  router.visit("/products");
}

onMounted(() => {
  loadOptions();
});

onBeforeUnmount(() => {
  if (toastTimer) {
    clearTimeout(toastTimer);
  }

  if (imagePreview.value) {
    URL.revokeObjectURL(imagePreview.value);
  }
  galleryImages.value.forEach(({ preview }) => URL.revokeObjectURL(preview));
});
</script>

<template>
  <Head title="Create Product" />

  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <div class="product-create-page">
      <!-- Page Header -->
      <header class="page-header">
        <div class="page-heading">
          <div class="breadcrumb">
            <button type="button" class="breadcrumb-back" @click="cancel">
              <Icon name="arrow-left" :size="14" />
              Products
            </button>

            <span class="breadcrumb-separator">/</span>

            <strong>Create Product</strong>
          </div>

          <div class="title-row">
            <div class="page-title-icon">
              <Icon name="package" :size="20" />
            </div>

            <div>
              <h1>Create Product</h1>

              <p>
                Build a structured product record for your catalog, pricing, variants and
                inventory.
              </p>
            </div>
          </div>
        </div>

        <div class="header-actions">
          <button
            type="button"
            class="button button-secondary"
            :disabled="isSaving"
            @click="cancel"
          >
            Cancel
          </button>

          <button
            type="button"
            class="button button-primary"
            :disabled="isSaving || !isFormReady"
            @click="createProduct"
          >
            <Icon name="loader-2" v-if="isSaving" class="spin" :size="16" />

            <Icon name="device-floppy" v-else :size="16" />

            {{ isSaving ? "Creating..." : "Create Product" }}
          </button>
        </div>
      </header>

      <!-- Progress / Context Strip -->
      <section class="context-strip">
        <div class="context-item">
          <span class="context-dot active"></span>

          <div>
            <strong>Product foundation</strong>
            <span>Core catalog information</span>
          </div>
        </div>

        <div class="context-divider"></div>

        <div class="context-item">
          <span class="context-number">02</span>

          <div>
            <strong>Pricing</strong>
            <span>Connected later</span>
          </div>
        </div>

        <div class="context-divider"></div>

        <div class="context-item">
          <span class="context-number">03</span>

          <div>
            <strong>Variants & Inventory</strong>
            <span>Connected later</span>
          </div>
        </div>
      </section>

      <div class="content-grid">
        <!-- Main -->
        <main class="main-column">
          <!-- Media -->
          <section class="card media-card">
            <div class="section-heading">
              <div class="section-icon">
                <Icon name="photo-plus" :size="18" />
              </div>

              <div>
                <h2>Product Media</h2>
                <p>Add a professional primary image for your product catalog.</p>
              </div>

              <span class="section-badge">Optional</span>
            </div>

            <input
              ref="imageInput"
              type="file"
              class="hidden-input"
              accept="image/jpeg,image/png,image/webp"
              @change="onImageSelected"
            />

            <div
              v-if="!imagePreview"
              class="upload-zone"
              @click="selectImage"
              @drop="onDrop"
              @dragover="onDragOver"
            >
              <div class="upload-visual">
                <div class="upload-icon">
                  <Icon name="cloud-upload" :size="25" />
                </div>

                <div class="upload-plus">
                  <Icon name="photo-plus" :size="13" />
                </div>
              </div>

              <h3>Upload product image</h3>

              <p>
                Drag and drop your image here or
                <button type="button" class="browse-link" @click.stop="selectImage">
                  browse files
                </button>
              </p>

              <div class="upload-meta">
                <span>JPG</span>
                <span>PNG</span>
                <span>WebP</span>
                <i></i>
                <span>Maximum 5 MB</span>
              </div>
            </div>

            <div v-else class="image-preview-wrapper">
              <img :src="imagePreview" alt="Product preview" class="image-preview" />

              <div class="preview-topbar">
                <span class="primary-label">
                  <Icon name="circle-check" :size="13" />
                  Primary image
                </span>

                <button
                  type="button"
                  class="preview-close"
                  aria-label="Remove image"
                  @click="removeImage"
                >
                  <Icon name="x" :size="15" />
                </button>
              </div>

              <div class="image-preview-overlay">
                <div class="image-file-info">
                  <strong>
                    {{ selectedImage?.name }}
                  </strong>

                  <span>
                    {{
                      selectedImage ? (selectedImage.size / 1024 / 1024).toFixed(2) : "0"
                    }}
                    MB
                  </span>
                </div>

                <button type="button" class="preview-action" @click="selectImage">
                  Replace image
                </button>
              </div>
            </div>

            <div class="gallery-upload-panel">
              <div class="gallery-upload-heading">
                <div><strong>Additional gallery images</strong><p>Upload up to 9 extra views. The primary image stays first.</p></div>
                <button type="button" class="browse-link" @click="galleryInput?.click()">Add images</button>
              </div>
              <input ref="galleryInput" type="file" class="hidden-input" accept="image/jpeg,image/png,image/webp" multiple @change="onGallerySelected" />
              <div v-if="galleryImages.length" class="gallery-preview-grid">
                <div v-for="(item, index) in galleryImages" :key="`${item.file.name}-${item.file.lastModified}`" class="gallery-preview-item">
                  <img :src="item.preview" :alt="item.file.name" />
                  <button type="button" :aria-label="`Remove ${item.file.name}`" @click="removeGalleryImage(index)"><Icon name="x" :size="13" /></button>
                  <small>{{ item.file.name }}</small>
                </div>
              </div>
            </div>
          </section>

          <!-- Basic Information -->
          <section class="card">
            <div class="section-heading">
              <div class="section-icon">
                <Icon name="stars" :size="18" />
              </div>

              <div>
                <h2>Basic Information</h2>
                <p>Define the product's core catalog identity.</p>
              </div>
            </div>

            <div class="form-grid">
              <div class="field field-full">
                <label>
                  Product Name
                  <span>*</span>
                </label>

                <div class="input-with-icon">
                  <Icon name="package" :size="16" />

                  <input
                    v-model="form.name"
                    type="text"
                    maxlength="200"
                    placeholder="e.g. Digital Platform Scale"
                    @input="clearError('name')"
                  />
                </div>

                <small v-if="errors.name" class="field-error">
                  <Icon name="alert-circle" :size="12" />
                  {{ errors.name }}
                </small>
              </div>

              <div class="field field-full">
                <div class="label-row">
                  <label>Slug</label>

                  <button type="button" class="generate-button" @click="generateSlug">
                    <Icon name="stars" :size="12" />
                    Auto Generate
                  </button>
                </div>

                <div class="slug-field">
                  <span>/products/</span>

                  <input
                    v-model="form.slug"
                    type="text"
                    maxlength="220"
                    placeholder="digital-platform-scale"
                    @input="onSlugInput"
                  />
                </div>

                <small v-if="errors.slug" class="field-error">
                  <Icon name="alert-circle" :size="12" />
                  {{ errors.slug }}
                </small>

                <small v-else class="field-hint">
                  SEO-friendly product URL identifier.
                </small>
              </div>

              <div class="field">
                <label>
                  Category
                  <span>*</span>
                </label>

                <div class="select-wrap">
                  <select
                    v-model="form.category_id"
                    :disabled="isLoadingOptions"
                    @change="clearError('category_id')"
                  >
                    <option value="">Select category</option>

                    <option
                      v-for="category in categories"
                      :key="category.id"
                      :value="String(category.id)"
                    >
                      {{ category.name }}
                    </option>
                  </select>

                  <Icon name="chevron-down" :size="15" />
                </div>

                <small v-if="errors.category_id" class="field-error">
                  <Icon name="alert-circle" :size="12" />
                  {{ errors.category_id }}
                </small>
              </div>

              <div class="field">
                <label>Brand</label>

                <div class="select-wrap">
                  <select v-model="form.brand_id" :disabled="isLoadingOptions">
                    <option value="">No brand</option>

                    <option
                      v-for="brand in brands"
                      :key="brand.id"
                      :value="String(brand.id)"
                    >
                      {{ brand.name }}
                    </option>
                  </select>

                  <Icon name="chevron-down" :size="15" />
                </div>

                <small class="field-hint"> Optional product brand. </small>
              </div>

              <div class="field">
                <label>
                  Base Unit
                  <span>*</span>
                </label>

                <div class="select-wrap">
                  <select
                    v-model="form.unit_id"
                    :disabled="isLoadingOptions"
                    @change="clearError('unit_id')"
                  >
                    <option value="">Select unit</option>

                    <option v-for="unit in units" :key="unit.id" :value="String(unit.id)">
                      {{ unit.name }}
                      {{ unit.short_name ? `(${unit.short_name})` : "" }}
                    </option>
                  </select>

                  <Icon name="chevron-down" :size="15" />
                </div>

                <small v-if="errors.unit_id" class="field-error">
                  <Icon name="alert-circle" :size="12" />
                  {{ errors.unit_id }}
                </small>
              </div>

              <div class="field">
                <label> Product Type </label>

                <div class="type-selector">
                  <button
                    type="button"
                    :class="{
                      selected: form.product_type === 'simple',
                    }"
                    @click="
                      form.product_type = 'simple';
                      clearError('product_type');
                    "
                  >
                    <Icon name="package" :size="16" />

                    <span>
                      <strong>Simple</strong>
                      <small> Single SKU product </small>
                    </span>

                    <Icon name="circle-check" v-if="form.product_type === 'simple'" :size="15" />
                  </button>

                  <button
                    type="button"
                    :class="{
                      selected: form.product_type === 'variable',
                    }"
                    @click="
                      form.product_type = 'variable';
                      clearError('product_type');
                    "
                  >
                    <Icon name="stack-2" :size="16" />

                    <span>
                      <strong>Variable</strong>
                      <small> Supports variants </small>
                    </span>

                    <Icon name="circle-check" v-if="form.product_type === 'variable'" :size="15" />
                  </button>
                </div>

                <small class="field-hint">
                  Variable products can receive attribute-based variants later.
                </small>
              </div>
            </div>
          </section>

          <!-- Product Identity -->
          <section class="card">
            <div class="section-heading">
              <div class="section-icon">
                <Icon name="tag" :size="18" />
              </div>

              <div>
                <h2>Product Identity</h2>
                <p>Internal identifiers used across operations and inventory.</p>
              </div>
            </div>

            <div class="form-grid">
              <div class="field">
                <label>
                  SKU
                  <span v-if="form.product_type === 'simple'"> * </span>
                </label>

                <input
                  v-model="form.sku"
                  type="text"
                  maxlength="100"
                  placeholder="MS-DS-001"
                  @input="clearError('sku')"
                />

                <small v-if="errors.sku" class="field-error">
                  <Icon name="alert-circle" :size="12" />
                  {{ errors.sku }}
                </small>

                <small v-else class="field-hint"> Required for simple products. </small>
              </div>

              <div class="field">
                <label>Model Number</label>

                <input
                  v-model="form.model_number"
                  type="text"
                  maxlength="120"
                  placeholder="OP-REGULAR"
                  @input="clearError('model_number')"
                />

                <small v-if="errors.model_number" class="field-error">
                  <Icon name="alert-circle" :size="12" />
                  {{ errors.model_number }}
                </small>
              </div>

              <div class="field field-full">
                <label>Barcode</label>

                <input
                  v-model="form.barcode"
                  type="text"
                  maxlength="100"
                  placeholder="8901234567890"
                  @input="clearError('barcode')"
                />

                <small v-if="errors.barcode" class="field-error">
                  <Icon name="alert-circle" :size="12" />
                  {{ errors.barcode }}
                </small>

                <small v-else class="field-hint">
                  Optional UPC, EAN or internal barcode.
                </small>
              </div>
            </div>
          </section>

          <!-- Description -->
          <section class="card">
            <div class="section-heading">
              <div class="section-icon">
                <Icon name="file-text" :size="18" />
              </div>

              <div>
                <h2>Product Description</h2>
                <p>Add customer-facing product information.</p>
              </div>
            </div>

            <div class="form-stack">
              <div class="field">
                <div class="label-row">
                  <label>Short Description</label>

                  <span class="character-count">
                    {{ form.short_description.length }}
                  </span>
                </div>

                <textarea
                  v-model="form.short_description"
                  rows="3"
                  maxlength="1000"
                  placeholder="Brief summary of the product..."
                  @input="clearError('short_description')"
                />

                <small class="field-hint">
                  Ideal for product cards, search results and quick previews.
                </small>
              </div>

              <div class="field">
                <div class="label-row">
                  <label>Full Description</label>

                  <span class="character-count">
                    {{ form.description.length }}
                  </span>
                </div>

                <textarea
                  v-model="form.description"
                  rows="7"
                  placeholder="Describe product features, specifications, usage and other important information..."
                  @input="clearError('description')"
                />

                <small class="field-hint">
                  Detailed information for customers and internal teams.
                </small>
              </div>
            </div>
          </section>
        </main>

        <!-- Sidebar -->
        <aside class="side-column">
          <!-- Product Preview -->
          <section class="card preview-card">
            <div class="section-heading compact">
              <div class="section-icon">
                <Icon name="eye" :size="18" />
              </div>

              <div>
                <h2>Product Summary</h2>
                <p>Live overview of this product.</p>
              </div>
            </div>

            <div class="summary-preview">
              <div
                class="summary-image"
                :class="{
                  'has-image': Boolean(imagePreview),
                }"
              >
                <img v-if="imagePreview" :src="imagePreview" alt="" />

                <Icon name="package" v-else :size="28" />
              </div>

              <div class="summary-content">
                <strong>
                  {{ form.name.trim() || "New Product" }}
                </strong>

                <span>
                  {{ productTypeLabel }}
                </span>
              </div>
            </div>

            <div class="summary-list">
              <div>
                <span>Category</span>
                <strong>
                  {{ selectedCategoryName }}
                </strong>
              </div>

              <div>
                <span>Brand</span>
                <strong>
                  {{ selectedBrandName }}
                </strong>
              </div>

              <div>
                <span>Base Unit</span>
                <strong>
                  {{ selectedUnitName }}
                </strong>
              </div>

              <div>
                <span>Status</span>
                <strong class="status-value">
                  <i :class="['status-dot', form.status]"></i>
                  {{ statusLabel }}
                </strong>
              </div>
            </div>
          </section>

          <!-- Publishing -->
          <section class="card">
            <div class="section-heading compact">
              <div class="section-icon">
                <Icon name="circle-check" :size="18" />
              </div>

              <div>
                <h2>Publishing</h2>
                <p>Control product availability.</p>
              </div>
            </div>

            <div class="form-stack">
              <div class="field">
                <label>Status</label>

                <div class="select-wrap">
                  <select v-model="form.status">
                    <option value="draft">Draft</option>

                    <option value="active">Active</option>

                    <option value="inactive">Inactive</option>

                    <option value="archived">Archived</option>
                  </select>

                  <Icon name="chevron-down" :size="15" />
                </div>
              </div>

              <div class="field">
                <label>Visibility</label>

                <div class="select-wrap">
                  <select v-model="form.visibility">
                    <option value="visible">Visible</option>

                    <option value="hidden">Hidden</option>
                  </select>

                  <Icon name="chevron-down" :size="15" />
                </div>
              </div>

              <div class="field">
                <label>Sort Order</label>

                <input v-model.number="form.sort_order" type="number" min="0" step="1" />

                <small class="field-hint"> Lower values appear first. </small>
              </div>
            </div>
          </section>

          <!-- Foundation Info -->
          <section class="info-card">
            <div class="info-icon">
              <Icon name="info-circle" :size="17" />
            </div>

            <div>
              <strong> Product foundation </strong>

              <p>
                Pricing, attributes, variants, inventory and additional media will connect
                to this product later.
              </p>
            </div>
          </section>

          <!-- Save -->
          <div class="save-panel">
            <div class="save-status">
              <span
                :class="[
                  'ready-dot',
                  {
                    ready: isFormReady,
                  },
                ]"
              ></span>

              <span>
                {{ isFormReady ? "Ready to create" : "Complete required fields" }}
              </span>
            </div>

            <button
              type="button"
              class="save-button"
              :disabled="isSaving || !isFormReady"
              @click="createProduct"
            >
              <Icon name="loader-2" v-if="isSaving" class="spin" :size="17" />

              <Icon name="device-floppy" v-else :size="17" />

              {{ isSaving ? "Creating Product..." : "Create Product" }}
            </button>
          </div>
        </aside>
      </div>

      <!-- Toast -->
      <Transition name="toast">
        <div v-if="toast.visible" class="toast" :class="`toast-${toast.type}`">
          <Icon name="circle-check" v-if="toast.type === 'success'" :size="18" />

          <Icon name="alert-circle" v-else :size="18" />

          <span>{{ toast.message }}</span>
        </div>
      </Transition>
    </div>
  </ErpLayout>
</template>

<style scoped>
.product-create-page {
  --page-bg: var(--background, #f6f8fb);
  --surface-color: var(--surface, #ffffff);
  --border-color: var(--border, #e5e7eb);
  --border-strong: #d8dee8;
  --text-primary-color: var(--text-primary, #111827);
  --text-secondary-color: var(--text-secondary, #667085);
  --text-tertiary-color: var(--text-tertiary, #98a2b3);
  --primary-color: var(--primary, #2563eb);
  --danger-color: #dc2626;
  --success-color: #16a34a;

  min-height: 100%;
  padding: 0;
  color: var(--text-primary-color);
  background: var(--page-bg);
}

/* Header */

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  max-width: 1480px;
  margin: 0 auto 18px;
}

.page-heading {
  min-width: 0;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-bottom: 9px;
  color: var(--text-tertiary-color);
  font-size: 12px;
}

.breadcrumb-back {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--text-secondary-color);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.breadcrumb-back:hover {
  color: var(--primary-color);
}

.breadcrumb-separator {
  color: var(--text-tertiary-color);
}

.breadcrumb strong {
  color: var(--text-secondary-color);
  font-weight: 600;
}

.title-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.page-title-icon {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 42px;
  height: 42px;
  margin-top: 2px;
  border: 1px solid rgb(37 99 235 / 11%);
  border-radius: 12px;
  background: rgb(37 99 235 / 8%);
  color: var(--primary-color);
}

h1 {
  margin: 0;
  color: var(--text-primary-color);
  font-size: 26px;
  line-height: 1.2;
  font-weight: 750;
  letter-spacing: -0.03em;
}

.page-header p {
  max-width: 680px;
  margin: 6px 0 0;
  color: var(--text-secondary-color);
  font-size: 13px;
  line-height: 1.5;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 9px;
  padding-top: 25px;
}

.button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 40px;
  padding: 0 14px;
  border-radius: 9px;
  font-size: 12px;
  font-weight: 650;
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease, color 0.2s ease,
    transform 0.2s ease, box-shadow 0.2s ease;
}

.button-secondary {
  border: 1px solid var(--border-color);
  background: var(--surface-color);
  color: var(--text-secondary-color);
}

.button-secondary:hover:not(:disabled) {
  border-color: var(--primary-color);
  color: var(--primary-color);
}

.button-primary {
  border: 1px solid var(--primary-color);
  background: var(--primary-color);
  color: #fff;
  box-shadow: 0 4px 13px rgb(37 99 235 / 15%);
}

.button-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 7px 18px rgb(37 99 235 / 20%);
}

.button:disabled,
.save-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

/* Context strip */

.context-strip {
  display: flex;
  align-items: center;
  gap: 17px;
  max-width: 1480px;
  margin: 0 auto 20px;
  padding: 12px 15px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: var(--surface-color);
  box-shadow: 0 2px 8px rgb(16 24 40 / 2%);
}

.context-item {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
}

.context-item > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.context-item strong {
  color: var(--text-primary-color);
  font-size: 11px;
  font-weight: 700;
}

.context-item span:last-child {
  color: var(--text-tertiary-color);
  font-size: 10px;
}

.context-dot,
.context-number {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 27px;
  height: 27px;
  border-radius: 8px;
}

.context-dot {
  position: relative;
  background: rgb(37 99 235 / 9%);
}

.context-dot::after {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--primary-color);
  content: "";
}

.context-number {
  border: 1px solid var(--border-color);
  background: #fafbfc;
  color: var(--text-tertiary-color);
  font-size: 9px;
  font-weight: 700;
}

.context-divider {
  width: 1px;
  height: 27px;
  background: var(--border-color);
}

/* Layout */

.content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 325px;
  gap: 20px;
  max-width: 1480px;
  margin: 0 auto;
}

.main-column,
.side-column {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.card {
  padding: 21px;
  border: 1px solid var(--border-color);
  border-radius: 14px;
  background: var(--surface-color);
  box-shadow: 0 2px 10px rgb(16 24 40 / 2.5%);
}

/* Section headers */

.section-heading {
  display: flex;
  align-items: flex-start;
  gap: 11px;
  margin-bottom: 20px;
}

.section-heading.compact {
  margin-bottom: 17px;
}

.section-icon {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 37px;
  height: 37px;
  border: 1px solid rgb(37 99 235 / 10%);
  border-radius: 10px;
  background: rgb(37 99 235 / 7%);
  color: var(--primary-color);
}

.section-heading > div:nth-child(2) {
  min-width: 0;
}

.section-heading h2 {
  margin: 1px 0 3px;
  color: var(--text-primary-color);
  font-size: 14px;
  font-weight: 700;
}

.section-heading p {
  margin: 0;
  color: var(--text-secondary-color);
  font-size: 11px;
  line-height: 1.45;
}

.section-badge {
  margin-left: auto;
  padding: 4px 7px;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  color: var(--text-tertiary-color);
  font-size: 9px;
  font-weight: 650;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* Form */

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 17px;
}

.form-stack {
  display: flex;
  flex-direction: column;
  gap: 17px;
}

.field {
  min-width: 0;
}

.field-full {
  grid-column: 1 / -1;
}

.field label {
  display: block;
  margin-bottom: 7px;
  color: var(--text-primary-color);
  font-size: 11px;
  font-weight: 650;
}

.field label span {
  margin-left: 2px;
  color: #ef4444;
}

.field input,
.field select,
.field textarea {
  width: 100%;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  outline: none;
  background: var(--surface-color);
  color: var(--text-primary-color);
  font: inherit;
  font-size: 12px;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}

.field input,
.field select {
  height: 41px;
  padding: 0 11px;
}

.field textarea {
  min-height: 91px;
  padding: 10px 11px;
  resize: vertical;
  line-height: 1.55;
}

.field input:hover,
.field select:hover,
.field textarea:hover {
  border-color: var(--border-strong);
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgb(37 99 235 / 7%);
}

.field input::placeholder,
.field textarea::placeholder {
  color: var(--text-tertiary-color);
}

.input-with-icon {
  position: relative;
}

.input-with-icon > svg {
  position: absolute;
  top: 50%;
  left: 12px;
  z-index: 1;
  color: var(--text-tertiary-color);
  transform: translateY(-50%);
  pointer-events: none;
}

.input-with-icon input {
  padding-left: 37px;
}

.select-wrap {
  position: relative;
}

.select-wrap select {
  appearance: none;
  padding-right: 35px;
}

.select-wrap svg {
  position: absolute;
  top: 50%;
  right: 11px;
  color: var(--text-tertiary-color);
  transform: translateY(-50%);
  pointer-events: none;
}

.slug-field {
  display: flex;
  align-items: center;
  overflow: hidden;
  height: 41px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--surface-color);
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.slug-field:focus-within {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgb(37 99 235 / 7%);
}

.slug-field > span {
  flex: 0 0 auto;
  padding-left: 11px;
  color: var(--text-tertiary-color);
  font-size: 11px;
}

.slug-field input {
  height: 39px;
  padding-left: 4px;
  border: 0;
  box-shadow: none !important;
}

.label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.generate-button {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--primary-color);
  font-size: 10px;
  font-weight: 650;
  cursor: pointer;
}

.generate-button:hover {
  text-decoration: underline;
}

.character-count {
  color: var(--text-tertiary-color);
  font-size: 9px;
}

.field-hint {
  display: block;
  margin-top: 5px;
  color: var(--text-tertiary-color);
  font-size: 10px;
  line-height: 1.45;
}

.field-error {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 5px;
  color: var(--danger-color);
  font-size: 10px;
  line-height: 1.4;
}

/* Product type */

.type-selector {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 7px;
}

.type-selector button {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  min-height: 52px;
  padding: 8px 9px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--surface-color);
  color: var(--text-secondary-color);
  text-align: left;
  cursor: pointer;
  transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
}

.type-selector button:hover {
  border-color: var(--border-strong);
}

.type-selector button.selected {
  border-color: rgb(37 99 235 / 40%);
  background: rgb(37 99 235 / 5%);
  color: var(--primary-color);
  box-shadow: 0 0 0 2px rgb(37 99 235 / 5%);
}

.type-selector button > span {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 2px;
}

.type-selector strong {
  color: var(--text-primary-color);
  font-size: 10px;
  font-weight: 700;
}

.type-selector small {
  overflow: hidden;
  color: var(--text-tertiary-color);
  font-size: 9px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.type-selector button > svg:last-child {
  margin-left: auto;
  flex: 0 0 auto;
  color: var(--primary-color);
}

/* Media */

.hidden-input {
  display: none;
}

.gallery-upload-panel { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--border, #e5e7eb); }
.gallery-upload-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.gallery-upload-heading strong { display: block; font-size: 13px; }
.gallery-upload-heading p { margin: 4px 0 0; color: var(--muted-foreground, #6b7280); font-size: 12px; }
.gallery-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(92px, 1fr)); gap: 10px; }
.gallery-preview-item { position: relative; min-width: 0; }
.gallery-preview-item img { width: 100%; aspect-ratio: 1; object-fit: cover; border: 1px solid var(--border, #e5e7eb); border-radius: 10px; }
.gallery-preview-item button { position: absolute; top: 5px; right: 5px; display: grid; place-items: center; width: 25px; height: 25px; border: 0; border-radius: 50%; background: #111827d9; color: white; cursor: pointer; }
.gallery-preview-item small { display: block; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted-foreground, #6b7280); font-size: 10px; }

.upload-zone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 245px;
  padding: 30px;
  border: 1.5px dashed #cbd5e1;
  border-radius: 12px;
  background: radial-gradient(circle at top, rgb(37 99 235 / 4%), transparent 42%),
    #fafbfc;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.2s ease, background 0.2s ease, transform 0.2s ease;
}

.upload-zone:hover {
  border-color: var(--primary-color);
  background: radial-gradient(circle at top, rgb(37 99 235 / 7%), transparent 45%),
    #fafbfc;
  transform: translateY(-1px);
}

.upload-visual {
  position: relative;
  margin-bottom: 15px;
}

.upload-icon {
  display: grid;
  place-items: center;
  width: 58px;
  height: 58px;
  border: 1px solid rgb(37 99 235 / 10%);
  border-radius: 17px;
  background: rgb(37 99 235 / 8%);
  color: var(--primary-color);
}

.upload-plus {
  position: absolute;
  right: -5px;
  bottom: -4px;
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border: 2px solid var(--surface-color);
  border-radius: 7px;
  background: var(--primary-color);
  color: #fff;
}

.upload-zone h3 {
  margin: 0 0 5px;
  color: var(--text-primary-color);
  font-size: 13px;
  font-weight: 700;
}

.upload-zone p {
  margin: 0 0 12px;
  color: var(--text-secondary-color);
  font-size: 11px;
}

.browse-link {
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--primary-color);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.upload-meta {
  display: flex;
  align-items: center;
  gap: 5px;
  color: var(--text-tertiary-color);
  font-size: 9px;
}

.upload-meta span {
  padding: 3px 6px;
  border: 1px solid var(--border-color);
  border-radius: 5px;
  background: var(--surface-color);
}

.upload-meta i {
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: var(--text-tertiary-color);
}

.image-preview-wrapper {
  position: relative;
  overflow: hidden;
  min-height: 315px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: #f8fafc;
}

.image-preview {
  display: block;
  width: 100%;
  height: 315px;
  object-fit: contain;
}

.preview-topbar {
  position: absolute;
  top: 11px;
  right: 11px;
  left: 11px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.primary-label {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 8px;
  border: 1px solid rgb(255 255 255 / 20%);
  border-radius: 6px;
  background: rgb(15 23 42 / 65%);
  color: #fff;
  font-size: 9px;
  font-weight: 650;
  backdrop-filter: blur(8px);
}

.preview-close {
  display: grid;
  place-items: center;
  width: 29px;
  height: 29px;
  border: 1px solid rgb(255 255 255 / 20%);
  border-radius: 7px;
  background: rgb(15 23 42 / 65%);
  color: #fff;
  cursor: pointer;
  backdrop-filter: blur(8px);
}

.preview-close:hover {
  background: rgb(239 68 68 / 70%);
}

.image-preview-overlay {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 30px 14px 13px;
  background: linear-gradient(180deg, transparent, rgb(15 23 42 / 91%));
}

.image-file-info {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 3px;
}

.image-file-info strong {
  overflow: hidden;
  max-width: 400px;
  color: #fff;
  font-size: 10px;
  font-weight: 650;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.image-file-info span {
  color: rgb(255 255 255 / 68%);
  font-size: 9px;
}

.preview-action {
  flex: 0 0 auto;
  min-height: 30px;
  padding: 0 10px;
  border: 1px solid rgb(255 255 255 / 20%);
  border-radius: 7px;
  background: rgb(255 255 255 / 10%);
  color: #fff;
  font-size: 10px;
  font-weight: 650;
  cursor: pointer;
  backdrop-filter: blur(8px);
}

.preview-action:hover {
  background: rgb(255 255 255 / 18%);
}

/* Summary */

.summary-preview {
  display: flex;
  align-items: center;
  gap: 11px;
  padding-bottom: 15px;
  border-bottom: 1px solid var(--border-color);
}

.summary-image {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 58px;
  height: 58px;
  overflow: hidden;
  border: 1px solid var(--border-color);
  border-radius: 9px;
  background: #f8fafc;
  color: var(--text-tertiary-color);
}

.summary-image img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.summary-content {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 4px;
}

.summary-content strong {
  overflow: hidden;
  color: var(--text-primary-color);
  font-size: 12px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.summary-content span {
  color: var(--text-tertiary-color);
  font-size: 10px;
}

.summary-list {
  display: flex;
  flex-direction: column;
}

.summary-list > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid rgb(229 231 235 / 70%);
}

.summary-list > div:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}

.summary-list span {
  color: var(--text-tertiary-color);
  font-size: 10px;
}

.summary-list strong {
  max-width: 170px;
  overflow: hidden;
  color: var(--text-secondary-color);
  font-size: 10px;
  font-weight: 650;
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status-value {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #98a2b3;
}

.status-dot.active {
  background: #22c55e;
}

.status-dot.draft {
  background: #f59e0b;
}

.status-dot.inactive {
  background: #94a3b8;
}

.status-dot.archived {
  background: #8b5cf6;
}

/* Info */

.info-card {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 14px;
  border: 1px solid rgb(37 99 235 / 10%);
  border-radius: 11px;
  background: rgb(37 99 235 / 4%);
}

.info-icon {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 31px;
  height: 31px;
  border-radius: 8px;
  background: rgb(37 99 235 / 9%);
  color: var(--primary-color);
}

.info-card strong {
  display: block;
  margin: 1px 0 4px;
  color: var(--text-primary-color);
  font-size: 11px;
}

.info-card p {
  margin: 0;
  color: var(--text-secondary-color);
  font-size: 10px;
  line-height: 1.5;
}

/* Save panel */

.save-panel {
  position: sticky;
  bottom: 18px;
  padding: 12px;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  background: rgb(255 255 255 / 92%);
  box-shadow: 0 12px 30px rgb(15 23 42 / 10%);
  backdrop-filter: blur(12px);
}

.save-status {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 9px;
  color: var(--text-tertiary-color);
  font-size: 9px;
  font-weight: 600;
}

.ready-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #f59e0b;
}

.ready-dot.ready {
  background: #22c55e;
}

.save-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  width: 100%;
  min-height: 42px;
  border: 1px solid var(--primary-color);
  border-radius: 8px;
  background: var(--primary-color);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 12px rgb(37 99 235 / 15%);
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.save-button:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 7px 17px rgb(37 99 235 / 20%);
}

/* Toast */

.toast {
  position: fixed;
  z-index: 1000;
  right: 25px;
  bottom: 25px;
  display: flex;
  align-items: center;
  gap: 9px;
  max-width: 420px;
  padding: 12px 15px;
  border: 1px solid;
  border-radius: 10px;
  background: var(--surface-color);
  box-shadow: 0 15px 40px rgb(15 23 42 / 15%);
  font-size: 11px;
  font-weight: 600;
}

.toast-success {
  border-color: rgb(34 197 94 / 22%);
  color: #15803d;
}

.toast-error {
  border-color: rgb(239 68 68 / 22%);
  color: #b91c1c;
}

.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(9px);
}

.spin {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Responsive */

@media (max-width: 1120px) {
  .content-grid {
    grid-template-columns: minmax(0, 1fr) 290px;
  }

  .context-strip {
    overflow-x: auto;
  }
}

@media (max-width: 980px) {
  .content-grid {
    grid-template-columns: 1fr;
  }

  .side-column {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
  }

  .preview-card {
    grid-column: 1 / -1;
  }

  .save-panel {
    position: static;
  }
}

@media (max-width: 760px) {
  .product-create-page {
    padding: 0;
  }

  .page-header {
    flex-direction: column;
    gap: 15px;
  }

  .header-actions {
    width: 100%;
    padding-top: 0;
  }

  .header-actions .button {
    flex: 1;
  }

  .context-strip {
    gap: 13px;
    padding: 10px 12px;
  }

  .context-divider {
    display: none;
  }

  .context-item {
    flex: 0 0 auto;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .field-full {
    grid-column: auto;
  }

  .card {
    padding: 17px;
  }

  .side-column {
    display: flex;
  }

  .upload-zone {
    min-height: 220px;
    padding: 23px;
  }

  .image-preview-wrapper,
  .image-preview {
    min-height: 240px;
    height: 240px;
  }

  .image-preview-overlay {
    align-items: flex-start;
    flex-direction: column;
    gap: 7px;
  }

  .preview-action {
    width: 100%;
  }

  .toast {
    right: 15px;
    bottom: 15px;
    left: 15px;
    max-width: none;
  }
}

@media (max-width: 480px) {
  .title-row {
    align-items: flex-start;
  }

  h1 {
    font-size: 22px;
  }

  .page-header p {
    font-size: 11px;
  }

  .header-actions {
    flex-direction: column-reverse;
  }

  .header-actions .button {
    width: 100%;
  }

  .type-selector {
    grid-template-columns: 1fr;
  }

  .upload-meta {
    flex-wrap: wrap;
    justify-content: center;
  }
}
</style>
