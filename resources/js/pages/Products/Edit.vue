<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";
import ErpLayout from "@/layouts/ErpLayout.vue";
import Icon from "@/components/dashboard/Icon.vue";
import ProductVariantsPanel from "@/components/products/ProductVariantsPanel.vue";

interface Option {
  id: number;
  name: string;
  short_name?: string;
  slug?: string;
}

interface ProductMedia {
  id: number;
  product_id: number;
  disk: string;
  path: string;
  collection: string;
  original_name: string | null;
  mime_type: string | null;
  size: number | null;
  width: number | null;
  height: number | null;
  alt_text: string | null;
  sort_order: number;
  is_primary: boolean;
}

interface Product {
  id: number;

  category_id: number;
  brand_id: number | null;
  unit_id: number;

  name: string;
  slug: string;
  sku: string | null;
  model_number: string | null;
  barcode: string | null;

  product_type: "simple" | "variable";

  short_description: string | null;
  description: string | null;

  status: "draft" | "active" | "inactive" | "archived";
  visibility: "visible" | "hidden";

  sort_order: number;

  category?: Option | null;
  brand?: Option | null;
  unit?: Option | null;

  media?: ProductMedia[];
  primary_media?: ProductMedia | null;

  created_at: string;
  updated_at: string;
}

interface ApiResponse<T> {
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
}

interface ApiError extends Error {
  errors?: Record<string, string[]>;
}

const props = withDefaults(
  defineProps<{
    userName?: string;
    userEmail?: string;
    productId?: number | string;
  }>(),
  {
    userName: "Yahya Admin",
    userEmail: "",
    productId: undefined,
  }
);

/* ==========================================================================
   PRODUCT ID
   ========================================================================== */

const productId = computed<number>(() => {
  if (props.productId !== undefined) {
    return Number(props.productId);
  }

  const match = window.location.pathname.match(/\/products\/(\d+)\/edit/);

  return Number(match?.[1] ?? 0);
});

/* ==========================================================================
   STATE
   ========================================================================== */

const product = ref<Product | null>(null);

const categories = ref<Option[]>([]);
const brands = ref<Option[]>([]);
const units = ref<Option[]>([]);

const isLoading = ref(true);
const isLoadingOptions = ref(true);
const isSaving = ref(false);

const errorMessage = ref("");
const successMessage = ref("");

const errors = ref<Record<string, string[]>>({});

const slugManuallyEdited = ref(false);

const selectedImage = ref<File | null>(null);
const imagePreviewUrl = ref<string | null>(null);
const galleryImages = ref<Array<{ file: File; preview: string }>>([]);
const galleryInput = ref<HTMLInputElement | null>(null);

const form = ref({
  category_id: "",
  brand_id: "",
  unit_id: "",

  name: "",
  slug: "",
  sku: "",
  model_number: "",
  barcode: "",

  product_type: "simple" as "simple" | "variable",

  short_description: "",
  description: "",

  status: "draft" as "draft" | "active" | "inactive" | "archived",

  visibility: "visible" as "visible" | "hidden",

  sort_order: 0,
});

/* ==========================================================================
   COMPUTED
   ========================================================================== */

const selectedCategoryName = computed(() => {
  return (
    categories.value.find((item) => String(item.id) === form.value.category_id)?.name ??
    "Not selected"
  );
});

const selectedBrandName = computed(() => {
  if (!form.value.brand_id) {
    return "No brand";
  }

  return (
    brands.value.find((item) => String(item.id) === form.value.brand_id)?.name ??
    "No brand"
  );
});

const selectedUnitName = computed(() => {
  return (
    units.value.find((item) => String(item.id) === form.value.unit_id)?.name ??
    "Not selected"
  );
});

const productTypeLabel = computed(() => {
  return form.value.product_type === "variable" ? "Variable Product" : "Simple Product";
});

const statusLabel = computed(() => {
  return form.value.status.charAt(0).toUpperCase() + form.value.status.slice(1);
});

const hasRequiredFields = computed(() => {
  if (!form.value.category_id) {
    return false;
  }

  if (!form.value.unit_id) {
    return false;
  }

  if (!form.value.name.trim()) {
    return false;
  }

  if (form.value.product_type === "simple" && !form.value.sku.trim()) {
    return false;
  }

  return true;
});

const primaryMedia = computed<ProductMedia | null>(() => {
  if (!product.value) {
    return null;
  }

  if (product.value.primary_media) {
    return product.value.primary_media;
  }

  return (
    product.value.media?.find((media) => media.is_primary) ??
    product.value.media?.[0] ??
    null
  );
});

const existingGalleryMedia = computed<ProductMedia[]>(() => {
  return (product.value?.media ?? []).filter(
    (media) => media.collection === "images" && !media.is_primary
  );
});

function productMediaUrl(media: ProductMedia): string {
  if (/^https?:\/\//i.test(media.path)) return media.path;
  return `/storage/${media.path.replace(/^\/+/, "")}`;
}

const existingImageUrl = computed(() => {
  if (imagePreviewUrl.value) {
    return imagePreviewUrl.value;
  }

  const media = primaryMedia.value;

  if (!media?.path) {
    return null;
  }

  if (/^https?:\/\//i.test(media.path)) {
    return media.path;
  }

  return `/storage/${media.path.replace(/^\/+/, "")}`;
});

const existingImageName = computed(() => {
  if (selectedImage.value) {
    return selectedImage.value.name;
  }

  return primaryMedia.value?.original_name ?? "Product image";
});

const existingImageSize = computed(() => {
  if (selectedImage.value) {
    return formatFileSize(selectedImage.value.size);
  }

  return formatFileSize(primaryMedia.value?.size ?? null);
});

/* ==========================================================================
   HELPERS
   ========================================================================== */

function csrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
}

function clearMessages(): void {
  errorMessage.value = "";
  successMessage.value = "";
}

function clearFieldError(field: string): void {
  if (errors.value[field]) {
    delete errors.value[field];
  }

  if (errorMessage.value) {
    errorMessage.value = "";
  }
}

function fieldError(field: string): string {
  return errors.value[field]?.[0] ?? "";
}

function normalizeNullable(value: string): string | null {
  const trimmed = value.trim();

  return trimmed === "" ? null : trimmed;
}

function slugify(value: string): string {
  return value
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .replace(/-{2,}/g, "-");
}

function formatFileSize(size: number | null): string {
  if (!size || size <= 0) {
    return "—";
  }

  if (size < 1024) {
    return `${size} B`;
  }

  if (size < 1024 * 1024) {
    return `${(size / 1024).toFixed(1)} KB`;
  }

  return `${(size / 1024 / 1024).toFixed(2)} MB`;
}

/* ==========================================================================
   API
   ========================================================================== */

async function apiRequest<T>(url: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(url, {
    credentials: "same-origin",
    ...options,
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      ...(csrfToken()
        ? {
            "X-CSRF-TOKEN": csrfToken(),
          }
        : {}),
      ...(options.headers ?? {}),
    },
  });

  const contentType = response.headers.get("content-type") ?? "";

  let payload: ApiResponse<T> = {};

  if (contentType.includes("application/json")) {
    payload = (await response.json()) as ApiResponse<T>;
  }

  if (!response.ok) {
    const error = new Error(
      payload.message ?? "Unable to complete the request."
    ) as ApiError;

    error.errors = payload.errors;

    throw error;
  }

  return payload.data as T;
}

/* ==========================================================================
   LOAD OPTIONS
   ========================================================================== */

async function loadOptions(): Promise<void> {
  isLoadingOptions.value = true;

  try {
    const [categoryData, brandData, unitData] = await Promise.all([
      apiRequest<Option[]>("/api/product-categories"),
      apiRequest<Option[]>("/api/brands"),
      apiRequest<Option[]>("/api/units"),
    ]);

    categories.value = Array.isArray(categoryData) ? categoryData : [];

    brands.value = Array.isArray(brandData) ? brandData : [];

    units.value = Array.isArray(unitData) ? unitData : [];
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Unable to load product options.";
  } finally {
    isLoadingOptions.value = false;
  }
}

/* ==========================================================================
   LOAD PRODUCT
   ========================================================================== */

async function loadProduct(): Promise<void> {
  if (!productId.value) {
    errorMessage.value = "Invalid product ID.";
    isLoading.value = false;
    return;
  }

  isLoading.value = true;

  try {
    const data = await apiRequest<Product>(`/api/products/${productId.value}`);

    product.value = data;

    form.value = {
      category_id: String(data.category_id ?? ""),

      brand_id:
        data.brand_id !== null && data.brand_id !== undefined
          ? String(data.brand_id)
          : "",

      unit_id: String(data.unit_id ?? ""),

      name: data.name ?? "",
      slug: data.slug ?? "",

      sku: data.sku ?? "",
      model_number: data.model_number ?? "",
      barcode: data.barcode ?? "",

      product_type: data.product_type ?? "simple",

      short_description: data.short_description ?? "",

      description: data.description ?? "",

      status: data.status ?? "draft",

      visibility: data.visibility ?? "visible",

      sort_order: data.sort_order ?? 0,
    };

    slugManuallyEdited.value = true;
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Unable to load product.";
  } finally {
    isLoading.value = false;
  }
}

/* ==========================================================================
   SLUG
   ========================================================================== */

function onNameInput(): void {
  clearFieldError("name");

  if (!slugManuallyEdited.value) {
    form.value.slug = slugify(form.value.name);
  }
}

function onSlugInput(): void {
  slugManuallyEdited.value = true;

  form.value.slug = slugify(form.value.slug);

  clearFieldError("slug");
}

function autoGenerateSlug(): void {
  form.value.slug = slugify(form.value.name);

  slugManuallyEdited.value = false;

  clearFieldError("slug");
}

/* ==========================================================================
   IMAGE PREVIEW
   ========================================================================== */

function validateImage(file: File): string | null {
  const allowedTypes = ["image/jpeg", "image/png", "image/webp"];

  if (!allowedTypes.includes(file.type)) {
    return "Only JPG, PNG or WebP images are allowed.";
  }

  if (file.size > 5 * 1024 * 1024) {
    return "Image size must not exceed 5 MB.";
  }

  return null;
}

function onImageSelected(event: Event): void {
  const input = event.target as HTMLInputElement;

  const file = input.files?.[0];

  if (!file) {
    return;
  }

  const validationError = validateImage(file);

  if (validationError) {
    errorMessage.value = validationError;
    input.value = "";
    return;
  }

  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value);
  }
  clearMessages();
  selectedImage.value = file;
  imagePreviewUrl.value = URL.createObjectURL(file);
}

function removeSelectedImage(): void {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value);
    imagePreviewUrl.value = null;
  }

  selectedImage.value = null;
}

function onGallerySelected(event: Event): void {
  const input = event.target as HTMLInputElement;
  const files = Array.from(input.files ?? []);
  const existingCount =
    product.value?.media?.filter((media) => media.collection === "images").length ?? 0;
  const available = Math.max(0, 10 - existingCount - galleryImages.value.length);

  for (const file of files.slice(0, available)) {
    const validationError = validateImage(file);
    if (validationError) {
      errorMessage.value = `${file.name}: ${validationError}`;
      continue;
    }
    galleryImages.value.push({ file, preview: URL.createObjectURL(file) });
  }
  if (files.length > available)
    errorMessage.value = "A product can have up to 10 images in total.";
  input.value = "";
}

function removeGalleryImage(index: number): void {
  const [removed] = galleryImages.value.splice(index, 1);
  if (removed) URL.revokeObjectURL(removed.preview);
}

/* ==========================================================================
   UPDATE
   ========================================================================== */

async function updateProduct(): Promise<void> {
  clearMessages();

  errors.value = {};

  /*
   * Frontend validation
   */

  if (!form.value.category_id) {
    errors.value.category_id = ["Please select a category."];
  }

  if (!form.value.unit_id) {
    errors.value.unit_id = ["Please select a base unit."];
  }

  if (!form.value.name.trim()) {
    errors.value.name = ["Product name is required."];
  }

  if (form.value.product_type === "simple" && !form.value.sku.trim()) {
    errors.value.sku = ["SKU is required for simple products."];
  }

  if (Object.keys(errors.value).length > 0) {
    errorMessage.value = "Please review the highlighted fields before saving.";

    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });

    return;
  }

  isSaving.value = true;

  try {
    const payload = {
      category_id: Number(form.value.category_id),

      brand_id: form.value.brand_id ? Number(form.value.brand_id) : null,

      unit_id: Number(form.value.unit_id),

      name: form.value.name.trim(),

      slug: normalizeNullable(form.value.slug),

      sku: normalizeNullable(form.value.sku),

      model_number: normalizeNullable(form.value.model_number),

      barcode: normalizeNullable(form.value.barcode),

      product_type: form.value.product_type,

      short_description: normalizeNullable(form.value.short_description),

      description: normalizeNullable(form.value.description),

      status: form.value.status,

      visibility: form.value.visibility,

      sort_order: Number(form.value.sort_order) || 0,
    };

    const formData = new FormData();

    Object.entries(payload).forEach(([key, value]) => {
      formData.append(key, value === null ? "" : String(value));
    });

    if (selectedImage.value) {
      formData.append("image", selectedImage.value);
    }
    galleryImages.value.forEach(({ file }) => formData.append("images[]", file));

    // Laravel multipart update-এর জন্য method spoofing
    formData.append("_method", "PUT");

    const updatedProduct = await apiRequest<Product>(`/api/products/${productId.value}`, {
      method: "POST",
      body: formData,
      // Content-Type manually set করবে না।
      // Browser নিজে multipart boundary যোগ করবে।
    });

    product.value = updatedProduct;

    successMessage.value = "Product updated successfully.";

    window.setTimeout(() => {
      router.visit(`/products/${productId.value}`);
    }, 900);
  } catch (error) {
    const apiError = error as ApiError;

    errors.value = apiError.errors ?? {};

    errorMessage.value = apiError.message || "Unable to update the product.";

    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  } finally {
    isSaving.value = false;
  }
}

/* ==========================================================================
   NAVIGATION
   ========================================================================== */

function cancel(): void {
  router.visit(`/products/${productId.value}`);
}

/** Pricing and variant management lives on the product details page. */
function managePricingAndVariants(): void {
  router.visit(`/products/${productId.value}`);
}

function backToProducts(): void {
  router.visit("/products");
}

/* ==========================================================================
   LIFECYCLE
   ========================================================================== */

onMounted(() => {
  void Promise.all([loadOptions(), loadProduct()]);
});

onBeforeUnmount(() => {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value);
  }
  galleryImages.value.forEach(({ preview }) => URL.revokeObjectURL(preview));
});
</script>

<template>
  <Head
    :title="
      product ? `Edit ${product.name} - Madina Scale` : 'Edit Product - Madina Scale'
    "
  />

  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <div class="product-edit-page">
      <!-- =========================================================
           HEADER
           ========================================================= -->

      <header class="page-header">
        <div class="header-main">
          <button
            type="button"
            class="back-button"
            title="Back"
            aria-label="Back"
            @click="cancel"
          >
            <Icon name="arrow-left" :size="18" />
          </button>

          <div class="header-copy">
            <div class="breadcrumb">
              <button type="button" @click="backToProducts">Products</button>

              <span>/</span>

              <button v-if="product" type="button" @click="cancel">
                {{ product.name }}
              </button>

              <span>/</span>

              <strong>Edit</strong>
            </div>

            <div class="title-row">
              <div class="title-icon">
                <Icon name="edit" :size="22" />
              </div>

              <div>
                <h1>Edit Product</h1>

                <p>Update product identity, classification and lifecycle information.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="header-actions">
          <button
            type="button"
            class="secondary-button"
            :disabled="isSaving"
            @click="cancel"
          >
            Cancel
          </button>

          <button
            type="button"
            class="primary-button"
            :disabled="isSaving || isLoading || !hasRequiredFields"
            @click="updateProduct"
          >
            <Icon name="loader-2" v-if="isSaving" class="spin" :size="16" />

            <Icon name="device-floppy" v-else :size="16" />

            {{ isSaving ? "Saving..." : "Save Changes" }}
          </button>
        </div>
      </header>

      <!-- =========================================================
           ALERTS
           ========================================================= -->

      <div v-if="errorMessage" class="alert-banner error" role="alert">
        <Icon name="alert-circle" :size="16" />

        <span>
          {{ errorMessage }}
        </span>

        <button type="button" aria-label="Close" @click="errorMessage = ''">
          <Icon name="x" :size="14" />
        </button>
      </div>

      <div v-if="successMessage" class="alert-banner success" role="status">
        <Icon name="circle-check" :size="16" />

        <span>
          {{ successMessage }}
        </span>
      </div>

      <!-- =========================================================
           LOADING
           ========================================================= -->

      <section v-if="isLoading" class="loading-card">
        <Icon name="loader-2" class="spin" :size="23" />

        <div>
          <strong> Loading product... </strong>

          <span> Preparing product information for editing. </span>
        </div>
      </section>

      <!-- =========================================================
           PRODUCT FORM
           ========================================================= -->

      <template v-else-if="product">
        <div class="content-grid">
          <!-- =====================================================
               MAIN COLUMN
               ===================================================== -->

          <main class="main-column">
            <!-- MEDIA -->

            <section class="card">
              <div class="section-heading">
                <div class="section-icon">
                  <Icon name="package" :size="18" />
                </div>

                <div>
                  <h2>Product Media</h2>

                  <p>Current primary image for this product.</p>
                </div>

                <span class="optional-badge"> MEDIA </span>
              </div>

              <div class="media-content">
                <div v-if="existingImageUrl" class="existing-image-wrapper">
                  <img
                    :src="existingImageUrl"
                    :alt="primaryMedia?.alt_text || product.name"
                    class="existing-image"
                  />

                  <div class="image-overlay">
                    <div class="image-file-info">
                      <strong>
                        {{ existingImageName }}
                      </strong>

                      <span>
                        {{ existingImageSize }}
                      </span>
                    </div>

                    <span class="image-status"> Primary Image </span>
                  </div>
                </div>

                <div v-else class="no-image">
                  <div class="no-image-icon">
                    <Icon name="photo-off" :size="24" />
                  </div>

                  <strong> No product image </strong>

                  <span> No primary media is currently attached. </span>
                </div>

                <div v-if="existingGalleryMedia.length" class="existing-gallery">
                  <strong
                    >Current gallery · {{ existingGalleryMedia.length }} additional
                    {{ existingGalleryMedia.length === 1 ? "image" : "images" }}</strong
                  >
                  <div class="gallery-preview-grid">
                    <div
                      v-for="media in existingGalleryMedia"
                      :key="media.id"
                      class="gallery-preview-item existing-gallery-item"
                    >
                      <img
                        :src="productMediaUrl(media)"
                        :alt="media.alt_text || media.original_name || product.name"
                        loading="lazy"
                      />
                      <small>{{ media.original_name || "Product image" }}</small>
                    </div>
                  </div>
                </div>

                <label class="media-upload">
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    @change="onImageSelected"
                  />

                  <span> Choose replacement image </span>

                  <small> JPG, PNG or WebP · Max 5 MB </small>
                </label>

                <div v-if="selectedImage" class="selected-file">
                  <div>
                    <strong>
                      {{ selectedImage.name }}
                    </strong>

                    <span>
                      {{ formatFileSize(selectedImage.size) }}
                    </span>
                  </div>

                  <button type="button" @click="removeSelectedImage">
                    <Icon name="x" :size="14" />
                  </button>
                </div>

                <div class="gallery-upload-panel">
                  <div class="gallery-upload-heading">
                    <div>
                      <strong>Add gallery images</strong>
                      <p>Existing images stay intact. Up to 10 images per product.</p>
                    </div>
                    <button
                      type="button"
                      class="browse-link"
                      @click="galleryInput?.click()"
                    >
                      Add images
                    </button>
                  </div>
                  <input
                    ref="galleryInput"
                    class="gallery-hidden-input"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    @change="onGallerySelected"
                  />
                  <div v-if="galleryImages.length" class="gallery-preview-grid">
                    <div
                      v-for="(item, index) in galleryImages"
                      :key="`${item.file.name}-${item.file.lastModified}`"
                      class="gallery-preview-item"
                    >
                      <img :src="item.preview" :alt="item.file.name" />
                      <button
                        type="button"
                        :aria-label="`Remove ${item.file.name}`"
                        @click="removeGalleryImage(index)"
                      >
                        <Icon name="x" :size="13" />
                      </button>
                      <small>{{ item.file.name }}</small>
                    </div>
                  </div>
                </div>

                <div class="media-note">
                  <Icon name="info-circle" :size="14" />

                  <span>
                    Select a replacement image and save your changes to upload it. The
                    current image remains unchanged unless the product update succeeds.
                  </span>
                </div>
              </div>
            </section>

            <!-- BASIC INFORMATION -->

            <section class="card">
              <div class="section-heading">
                <div class="section-icon">
                  <Icon name="stars" :size="18" />
                </div>

                <div>
                  <h2>Basic Information</h2>

                  <p>Define the product's core identity.</p>
                </div>
              </div>

              <div class="form-grid">
                <!-- NAME -->

                <div class="field field-full">
                  <label for="product-name">
                    Product Name
                    <span>*</span>
                  </label>

                  <input
                    id="product-name"
                    v-model="form.name"
                    type="text"
                    maxlength="200"
                    placeholder="e.g. Madina Digital Weight Scale D5"
                    :class="{
                      invalid: !!errors.name,
                    }"
                    @input="onNameInput"
                  />

                  <small v-if="errors.name" class="field-error">
                    {{ fieldError("name") }}
                  </small>
                </div>

                <!-- CATEGORY -->

                <div class="field">
                  <label for="category">
                    Category
                    <span>*</span>
                  </label>

                  <div class="select-wrapper">
                    <select
                      id="category"
                      v-model="form.category_id"
                      :class="{
                        invalid: !!errors.category_id,
                      }"
                      @change="clearFieldError('category_id')"
                    >
                      <option value="">Select category</option>

                      <option
                        v-for="item in categories"
                        :key="item.id"
                        :value="String(item.id)"
                      >
                        {{ item.name }}
                      </option>
                    </select>

                    <Icon name="chevron-down" :size="15" />
                  </div>

                  <small v-if="errors.category_id" class="field-error">
                    {{ fieldError("category_id") }}
                  </small>
                </div>

                <!-- BRAND -->

                <div class="field">
                  <label for="brand"> Brand </label>

                  <div class="select-wrapper">
                    <select
                      id="brand"
                      v-model="form.brand_id"
                      :class="{
                        invalid: !!errors.brand_id,
                      }"
                      @change="clearFieldError('brand_id')"
                    >
                      <option value="">No brand</option>

                      <option
                        v-for="item in brands"
                        :key="item.id"
                        :value="String(item.id)"
                      >
                        {{ item.name }}
                      </option>
                    </select>

                    <Icon name="chevron-down" :size="15" />
                  </div>

                  <small v-if="errors.brand_id" class="field-error">
                    {{ fieldError("brand_id") }}
                  </small>
                </div>

                <!-- UNIT -->

                <div class="field">
                  <label for="unit">
                    Base Unit
                    <span>*</span>
                  </label>

                  <div class="select-wrapper">
                    <select
                      id="unit"
                      v-model="form.unit_id"
                      :class="{
                        invalid: !!errors.unit_id,
                      }"
                      @change="clearFieldError('unit_id')"
                    >
                      <option value="">Select base unit</option>

                      <option
                        v-for="item in units"
                        :key="item.id"
                        :value="String(item.id)"
                      >
                        {{ item.name }}
                        {{ item.short_name ? `(${item.short_name})` : "" }}
                      </option>
                    </select>

                    <Icon name="chevron-down" :size="15" />
                  </div>

                  <small v-if="errors.unit_id" class="field-error">
                    {{ fieldError("unit_id") }}
                  </small>
                </div>

                <!-- PRODUCT TYPE -->

                <div class="field field-full">
                  <label> Product Type </label>

                  <div class="product-type-grid">
                    <button
                      type="button"
                      class="product-type-card"
                      :class="{
                        selected: form.product_type === 'simple',
                      }"
                      @click="form.product_type = 'simple'"
                    >
                      <span class="type-card-icon">
                        <Icon name="package" :size="17" />
                      </span>

                      <span class="type-card-content">
                        <strong> Simple Product </strong>

                        <small> One product SKU without variants. </small>
                      </span>

                      <span class="type-check">
                        <Icon
                          name="circle-check"
                          v-if="form.product_type === 'simple'"
                          :size="16"
                        />
                      </span>
                    </button>

                    <button
                      type="button"
                      class="product-type-card"
                      :class="{
                        selected: form.product_type === 'variable',
                      }"
                      @click="form.product_type = 'variable'"
                    >
                      <span class="type-card-icon">
                        <Icon name="stack-2" :size="17" />
                      </span>

                      <span class="type-card-content">
                        <strong> Variable Product </strong>

                        <small>
                          Variants can be configured in the variant manager.
                        </small>
                      </span>

                      <span class="type-check">
                        <Icon
                          name="circle-check"
                          v-if="form.product_type === 'variable'"
                          :size="16"
                        />
                      </span>
                    </button>
                  </div>
                </div>
                <p class="type-change-note">
                  {{
                    form.product_type === "simple"
                      ? "Simple products use one base pricing record and product-level identifiers."
                      : "Variable products use variant-specific pricing and identifiers."
                  }}
                </p>
              </div>
            </section>

            <!-- PRODUCT IDENTITY -->

            <section v-if="form.product_type === 'simple'" class="card">
              <div class="section-heading">
                <div class="section-icon">
                  <Icon name="tag" :size="18" />
                </div>

                <div>
                  <h2>Product Identity</h2>

                  <p>SKU, model and catalog identifiers.</p>
                </div>
              </div>

              <div class="form-grid">
                <!-- SKU -->

                <div class="field">
                  <label for="sku">
                    SKU

                    <span v-if="form.product_type === 'simple'"> * </span>
                  </label>

                  <input
                    id="sku"
                    v-model="form.sku"
                    type="text"
                    maxlength="100"
                    placeholder="e.g. MBWS-D5"
                    :class="{
                      invalid: !!errors.sku,
                    }"
                    @input="clearFieldError('sku')"
                  />

                  <small class="field-hint">
                    {{
                      form.product_type === "simple"
                        ? "Required for simple products."
                        : "Optional for variable parent products."
                    }}
                  </small>

                  <small v-if="errors.sku" class="field-error">
                    {{ fieldError("sku") }}
                  </small>
                </div>

                <!-- MODEL -->

                <div class="field">
                  <label for="model-number"> Model Number </label>

                  <input
                    id="model-number"
                    v-model="form.model_number"
                    type="text"
                    maxlength="120"
                    placeholder="e.g. MBWS-D5"
                    :class="{
                      invalid: !!errors.model_number,
                    }"
                    @input="clearFieldError('model_number')"
                  />

                  <small v-if="errors.model_number" class="field-error">
                    {{ fieldError("model_number") }}
                  </small>
                </div>

                <!-- BARCODE -->

                <div class="field">
                  <label for="barcode"> Barcode </label>

                  <input
                    id="barcode"
                    v-model="form.barcode"
                    type="text"
                    maxlength="100"
                    placeholder="e.g. 890000000001"
                    :class="{
                      invalid: !!errors.barcode,
                    }"
                    @input="clearFieldError('barcode')"
                  />

                  <small v-if="errors.barcode" class="field-error">
                    {{ fieldError("barcode") }}
                  </small>
                </div>

                <!-- SORT ORDER -->

                <div class="field">
                  <label for="sort-order"> Sort Order </label>

                  <input
                    id="sort-order"
                    v-model.number="form.sort_order"
                    type="number"
                    min="0"
                    max="4294967295"
                    placeholder="0"
                    :class="{
                      invalid: !!errors.sort_order,
                    }"
                    @input="clearFieldError('sort_order')"
                  />

                  <small v-if="errors.sort_order" class="field-error">
                    {{ fieldError("sort_order") }}
                  </small>
                </div>

                <!-- SLUG -->

                <div class="field field-full">
                  <label for="slug"> Product Slug </label>

                  <div class="slug-field">
                    <span> /products/ </span>

                    <input
                      id="slug"
                      v-model="form.slug"
                      type="text"
                      maxlength="220"
                      placeholder="product-slug"
                      :class="{
                        invalid: !!errors.slug,
                      }"
                      @input="onSlugInput"
                    />

                    <button type="button" @click="autoGenerateSlug">Auto Generate</button>
                  </div>

                  <small class="field-hint">
                    {{
                      slugManuallyEdited
                        ? "Custom slug is active."
                        : "Slug follows the product name."
                    }}
                  </small>

                  <small v-if="errors.slug" class="field-error">
                    {{ fieldError("slug") }}
                  </small>
                </div>
              </div>
            </section>

            <ProductVariantsPanel
              v-if="product"
              :key="`${product.id}-${form.product_type}`"
              :product-id="product.id"
              :product-type="form.product_type"
            />

            <!-- DESCRIPTION -->

            <section class="card">
              <div class="section-heading">
                <div class="section-icon">
                  <Icon name="file-text" :size="18" />
                </div>

                <div>
                  <h2>Product Description</h2>

                  <p>Product content and customer-facing information.</p>
                </div>
              </div>

              <div class="description-fields">
                <div class="field">
                  <label for="short-description"> Short Description </label>

                  <textarea
                    id="short-description"
                    v-model="form.short_description"
                    rows="4"
                    maxlength="1000"
                    placeholder="Write a concise product summary..."
                    :class="{
                      invalid: !!errors.short_description,
                    }"
                    @input="clearFieldError('short_description')"
                  ></textarea>

                  <div class="textarea-meta">
                    <small v-if="errors.short_description" class="field-error">
                      {{ fieldError("short_description") }}
                    </small>

                    <span> {{ form.short_description.length }}/1000 </span>
                  </div>
                </div>

                <div class="field">
                  <label for="description"> Full Description </label>

                  <textarea
                    id="description"
                    v-model="form.description"
                    rows="8"
                    placeholder="Write the complete product description..."
                    :class="{
                      invalid: !!errors.description,
                    }"
                    @input="clearFieldError('description')"
                  ></textarea>

                  <small v-if="errors.description" class="field-error">
                    {{ fieldError("description") }}
                  </small>
                </div>
              </div>
            </section>
          </main>

          <!-- =====================================================
               SIDEBAR
               ===================================================== -->

          <aside class="side-column">
            <!-- SUMMARY -->

            <section class="card">
              <div class="section-heading">
                <div class="section-icon">
                  <Icon name="eye" :size="18" />
                </div>

                <div>
                  <h2>Product Summary</h2>

                  <p>Live overview of your changes.</p>
                </div>
              </div>

              <div class="summary-body">
                <div class="summary-identity">
                  <div class="summary-product-icon">
                    <Icon name="products" :size="23" />
                  </div>

                  <div>
                    <strong>
                      {{ form.name.trim() || "Product Name" }}
                    </strong>

                    <span>
                      {{ productTypeLabel }}
                    </span>
                  </div>
                </div>

                <div class="summary-list">
                  <div class="summary-row">
                    <span> Category </span>

                    <strong>
                      {{ selectedCategoryName }}
                    </strong>
                  </div>

                  <div class="summary-row">
                    <span> Brand </span>

                    <strong>
                      {{ selectedBrandName }}
                    </strong>
                  </div>

                  <div class="summary-row">
                    <span> Base Unit </span>

                    <strong>
                      {{ selectedUnitName }}
                    </strong>
                  </div>

                  <div class="summary-row">
                    <span> Status </span>

                    <strong class="summary-status" :class="form.status">
                      <span class="status-dot"></span>

                      {{ statusLabel }}
                    </strong>
                  </div>

                  <div class="summary-row">
                    <span> Visibility </span>

                    <strong>
                      {{ form.visibility === "visible" ? "Visible" : "Hidden" }}
                    </strong>
                  </div>
                </div>
              </div>
            </section>

            <!-- PUBLISHING -->

            <section class="card">
              <div class="section-heading">
                <div class="section-icon">
                  <Icon name="circle-check" :size="18" />
                </div>

                <div>
                  <h2>Publishing</h2>

                  <p>Product lifecycle and visibility.</p>
                </div>
              </div>

              <div class="publishing-body">
                <div class="field">
                  <label for="status"> Status </label>

                  <div class="select-wrapper">
                    <select
                      id="status"
                      v-model="form.status"
                      :class="{
                        invalid: !!errors.status,
                      }"
                      @change="clearFieldError('status')"
                    >
                      <option value="draft">Draft</option>

                      <option value="active">Active</option>

                      <option value="inactive">Inactive</option>

                      <option value="archived">Archived</option>
                    </select>

                    <Icon name="chevron-down" :size="15" />
                  </div>

                  <small v-if="errors.status" class="field-error">
                    {{ fieldError("status") }}
                  </small>
                </div>

                <div class="field">
                  <label> Visibility </label>

                  <div class="visibility-grid">
                    <button
                      type="button"
                      class="visibility-card"
                      :class="{
                        selected: form.visibility === 'visible',
                      }"
                      @click="form.visibility = 'visible'"
                    >
                      <Icon name="eye" :size="16" />

                      <span>
                        <strong> Visible </strong>

                        <small> Available in catalog </small>
                      </span>

                      <Icon
                        name="circle-check"
                        v-if="form.visibility === 'visible'"
                        :size="15"
                      />
                    </button>

                    <button
                      type="button"
                      class="visibility-card"
                      :class="{
                        selected: form.visibility === 'hidden',
                      }"
                      @click="form.visibility = 'hidden'"
                    >
                      <Icon name="stack-2" :size="16" />

                      <span>
                        <strong> Hidden </strong>

                        <small> Keep out of catalog </small>
                      </span>

                      <Icon
                        name="circle-check"
                        v-if="form.visibility === 'hidden'"
                        :size="15"
                      />
                    </button>
                  </div>
                </div>
              </div>
            </section>

            <!-- SAVE -->

            <section class="save-card">
              <div class="save-status">
                <span
                  class="save-status-dot"
                  :class="{
                    ready: hasRequiredFields,
                  }"
                ></span>

                <span>
                  {{
                    hasRequiredFields
                      ? "Ready to save changes"
                      : "Complete required fields"
                  }}
                </span>
              </div>

              <button
                type="button"
                class="save-button"
                :disabled="isSaving || isLoading || !hasRequiredFields"
                @click="updateProduct"
              >
                <Icon name="loader-2" v-if="isSaving" class="spin" :size="17" />

                <Icon name="device-floppy" v-else :size="17" />

                {{ isSaving ? "Saving Changes..." : "Save Changes" }}
              </button>

              <button
                type="button"
                class="cancel-link"
                :disabled="isSaving"
                @click="cancel"
              >
                Cancel and return to product
              </button>
            </section>
          </aside>
        </div>

        <!-- MOBILE ACTION BAR -->

        <div class="mobile-save-bar">
          <button
            type="button"
            class="secondary-button"
            :disabled="isSaving"
            @click="cancel"
          >
            Cancel
          </button>

          <button
            type="button"
            class="primary-button"
            :disabled="isSaving || isLoading || !hasRequiredFields"
            @click="updateProduct"
          >
            <Icon name="loader-2" v-if="isSaving" class="spin" :size="15" />

            <Icon name="device-floppy" v-else :size="15" />

            {{ isSaving ? "Saving..." : "Save Changes" }}
          </button>
        </div>
      </template>
    </div>
  </ErpLayout>
</template>

<style scoped>
.product-edit-page {
  --edit-radius: 14px;

  width: 100%;
  min-width: 0;
  padding-bottom: 24px;
  color: var(--text-primary);
}

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
}
.header-main {
  display: flex;
  align-items: flex-start;
}
.title-row,
.header-actions,
.breadcrumb,
.section-heading,
.summary-identity,
.summary-row,
.save-status {
  display: flex;
}
.title-row {
  align-items: center;
}
.header-actions {
  align-items: center;
}
.breadcrumb {
  align-items: center;
}
.section-heading {
  align-items: center;
}
.summary-identity {
  align-items: center;
}
.summary-row {
  align-items: center;
}
.save-status {
  align-items: center;
}
.existing-gallery {
  margin: 14px 0 18px;
}
.existing-gallery > strong {
  display: block;
  margin-bottom: 10px;
  font-size: 12px;
}
.existing-gallery-item img {
  cursor: default;
}
.gallery-hidden-input {
  display: none;
}

.product-edit-page button:focus-visible,
.product-edit-page input:focus-visible,
.product-edit-page select:focus-visible,
.product-edit-page textarea:focus-visible,
.product-edit-page label.media-upload:focus-within {
  outline: 2px solid color-mix(in srgb, var(--primary) 70%, white);
  outline-offset: 2px;
}
.gallery-preview-item small {
  overflow-wrap: anywhere;
}
.browse-link {
  padding: 7px 10px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  color: var(--primary);
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}
.browse-link:hover {
  background: var(--surface-muted);
}

.gallery-upload-panel {
  margin-top: 18px;
  padding-top: 18px;
  border-top: 1px solid var(--border, #e5e7eb);
}
.gallery-upload-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}
.gallery-upload-heading strong {
  display: block;
  font-size: 13px;
}
.gallery-upload-heading p {
  margin: 4px 0 0;
  color: var(--muted-foreground, #6b7280);
  font-size: 12px;
}
.gallery-preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
  gap: 10px;
}
.gallery-preview-item {
  position: relative;
  min-width: 0;
}
.gallery-preview-item img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 10px;
}
.gallery-preview-item button {
  position: absolute;
  top: 5px;
  right: 5px;
  display: grid;
  place-items: center;
  width: 25px;
  height: 25px;
  border: 0;
  border-radius: 50%;
  background: #111827d9;
  color: white;
  cursor: pointer;
}
.gallery-preview-item small {
  display: block;
  margin-top: 4px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--muted-foreground, #6b7280);
  font-size: 10px;
}

.media-note,
.image-overlay,
.image-file-info,
.visibility-card,
.product-type-card {
  display: flex;
  align-items: center;
}

/* ==========================================================================
   HEADER
   ========================================================================== */

.page-header {
  align-items: flex-end;
  justify-content: space-between;

  gap: 24px;

  margin-bottom: 18px;
}

.header-main {
  align-items: flex-start;

  min-width: 0;

  gap: 12px;
}

.back-button {
  width: 36px;
  height: 36px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 36px;

  margin-top: 20px;

  border: 1px solid var(--border);
  border-radius: 9px;

  background: var(--surface);
  color: var(--text-secondary);

  cursor: pointer;

  transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
}

.back-button:hover {
  border-color: var(--border-strong);
  background: var(--surface-muted);
  color: var(--text-primary);
}

.header-copy {
  min-width: 0;
}

.breadcrumb {
  gap: 7px;

  margin-bottom: 8px;

  color: var(--text-muted);

  font-size: 10px;
  font-weight: 600;
}

.breadcrumb button {
  padding: 0;

  border: 0;

  background: transparent;

  color: var(--text-muted);

  font: inherit;

  cursor: pointer;
}

.breadcrumb button:hover {
  color: var(--primary);
}

.breadcrumb strong {
  color: var(--text-secondary);
}

.title-row {
  gap: 12px;
}

.title-icon {
  width: 42px;
  height: 42px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 42px;

  border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));

  border-radius: 12px;

  background: color-mix(in srgb, var(--primary) 10%, var(--surface));

  color: var(--primary);
}

.title-row h1 {
  margin: 0;

  font-size: 24px;
  line-height: 1.15;
  font-weight: 750;

  letter-spacing: -0.025em;
}

.title-row p {
  margin: 5px 0 0;

  color: var(--text-secondary);

  font-size: 12px;
  line-height: 1.5;
}

.header-actions {
  flex-shrink: 0;

  gap: 8px;
}

/* ==========================================================================
   BUTTONS
   ========================================================================== */

.primary-button,
.secondary-button {
  min-height: 38px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  gap: 7px;

  padding: 0 13px;

  border-radius: 9px;

  font-size: 12px;
  font-weight: 650;

  cursor: pointer;

  transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
}

.secondary-button {
  border: 1px solid var(--border);

  background: var(--surface);
  color: var(--text-secondary);
}

.secondary-button:hover:not(:disabled) {
  border-color: var(--border-strong);

  background: var(--surface-muted);

  color: var(--text-primary);
}

.primary-button {
  border: 1px solid var(--primary);

  background: var(--primary);

  color: #fff;

  box-shadow: 0 5px 14px color-mix(in srgb, var(--primary) 15%, transparent);
}

.primary-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.primary-button:disabled,
.secondary-button:disabled {
  opacity: 0.5;

  cursor: not-allowed;
}

/* ==========================================================================
   ALERT
   ========================================================================== */

.alert-banner {
  min-height: 42px;

  display: flex;
  align-items: center;

  gap: 9px;

  margin-bottom: 12px;
  padding: 9px 11px;

  border-radius: 9px;

  font-size: 11px;
}

.alert-banner span {
  flex: 1;
}

.alert-banner button {
  width: 26px;
  height: 26px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  border: 0;
  border-radius: 6px;

  background: transparent;

  cursor: pointer;
}

.alert-banner.error {
  border: 1px solid rgb(220 38 38 / 18%);

  background: rgb(220 38 38 / 5%);

  color: #b91c1c;
}

.alert-banner.error button {
  color: #b91c1c;
}

.alert-banner.success {
  border: 1px solid color-mix(in srgb, var(--success) 18%, var(--border));

  background: color-mix(in srgb, var(--success) 6%, var(--surface));

  color: var(--success);
}

/* ==========================================================================
   LOADING
   ========================================================================== */

.loading-card {
  min-height: 160px;

  display: flex;
  align-items: center;
  justify-content: center;

  gap: 12px;

  border: 1px solid var(--border);
  border-radius: var(--edit-radius);

  background: var(--surface);

  color: var(--primary);
}

.loading-card div {
  display: flex;
  flex-direction: column;

  gap: 3px;
}

.loading-card strong {
  color: var(--text-primary);

  font-size: 12px;
}

.loading-card span {
  color: var(--text-muted);

  font-size: 10px;
}

/* ==========================================================================
   WORKFLOW
   ========================================================================== */

.workflow-card {
  min-height: 68px;

  display: flex;
  align-items: center;

  gap: 16px;

  margin-bottom: 14px;
  padding: 11px 14px;

  border: 1px solid var(--border);
  border-radius: var(--edit-radius);

  background: var(--surface);

  box-shadow: 0 4px 16px rgb(26 47 72 / 4%);
}

.workflow-step {
  min-width: 0;

  display: flex;
  align-items: center;

  gap: 9px;

  flex: 1;
}

.workflow-number {
  width: 32px;
  height: 32px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 32px;

  border: 1px solid var(--border);
  border-radius: 9px;

  background: var(--surface-muted);

  color: var(--text-muted);

  font-size: 10px;
  font-weight: 700;
}

.workflow-step.active .workflow-number {
  border-color: color-mix(in srgb, var(--primary) 18%, var(--border));

  background: color-mix(in srgb, var(--primary) 8%, var(--surface));

  color: var(--primary);
}

.workflow-step div:last-child {
  min-width: 0;

  display: flex;
  flex-direction: column;

  gap: 2px;
}

.workflow-step strong {
  color: var(--text-primary);

  font-size: 11px;
}

.workflow-step span {
  color: var(--text-muted);

  font-size: 9px;
}

.workflow-divider {
  width: 1px;
  height: 30px;

  background: var(--border);
}

/* ==========================================================================
   GRID
   ========================================================================== */

.content-grid {
  display: grid;

  grid-template-columns:
    minmax(0, 1fr)
    minmax(290px, 335px);

  gap: 14px;

  align-items: start;
}

.main-column,
.side-column {
  min-width: 0;

  display: flex;
  flex-direction: column;

  gap: 14px;
}

/* ==========================================================================
   CARDS
   ========================================================================== */

.card,
.info-card,
.save-card {
  border: 1px solid var(--border);
  border-radius: var(--edit-radius);

  background: var(--surface);

  box-shadow: 0 8px 28px rgb(26 47 72 / 4.5%);
}

.card {
  overflow: hidden;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.section-heading {
  gap: 10px;

  padding: 15px 16px 13px;

  border-bottom: 1px solid var(--border);
}

.section-icon {
  width: 34px;
  height: 34px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 34px;

  border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));

  border-radius: 9px;

  background: color-mix(in srgb, var(--primary) 7%, var(--surface));

  color: var(--primary);
}

.section-heading > div:nth-child(2) {
  min-width: 0;
  flex: 1;
}

.section-heading h2 {
  margin: 0;

  color: var(--text-primary);

  font-size: 14px;
  line-height: 1.3;
}

.section-heading p {
  margin: 3px 0 0;

  color: var(--text-secondary);

  font-size: 10px;
  line-height: 1.4;
}

.optional-badge {
  padding: 5px 7px;

  border: 1px solid var(--border);
  border-radius: 7px;

  background: var(--surface-muted);

  color: var(--text-muted);

  font-size: 8px;
  font-weight: 700;
}

/* ==========================================================================
   MEDIA
   ========================================================================== */

.media-content {
  padding: 14px 16px 16px;
}

.existing-image-wrapper {
  position: relative;

  overflow: hidden;

  min-height: 285px;

  border: 1px solid var(--border);
  border-radius: 12px;

  background: var(--surface-muted);
}

.existing-image {
  width: 100%;
  height: 285px;

  display: block;

  object-fit: contain;
}

.image-overlay {
  position: absolute;

  right: 0;
  bottom: 0;
  left: 0;

  justify-content: space-between;

  gap: 12px;

  padding: 13px 14px;

  background: linear-gradient(180deg, transparent, rgb(15 23 42 / 82%));
}

.image-file-info {
  min-width: 0;

  align-items: flex-start;

  flex-direction: column;

  gap: 2px;
}

.image-file-info strong {
  max-width: 420px;

  overflow: hidden;

  color: #fff;

  font-size: 11px;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.image-file-info span {
  color: rgb(255 255 255 / 70%);

  font-size: 9px;
}

.image-status {
  flex-shrink: 0;

  padding: 5px 7px;

  border: 1px solid rgb(255 255 255 / 20%);
  border-radius: 6px;

  background: rgb(255 255 255 / 10%);

  color: #fff;

  font-size: 8px;
}

.no-image {
  min-height: 285px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-direction: column;

  gap: 6px;

  border: 1px dashed var(--border);
  border-radius: 12px;

  background: var(--surface-muted);

  text-align: center;
}

.no-image-icon {
  width: 52px;
  height: 52px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  border: 1px solid var(--border);
  border-radius: 13px;

  background: var(--surface);

  color: var(--text-muted);
}

.no-image strong {
  color: var(--text-primary);

  font-size: 12px;
}

.no-image span {
  color: var(--text-muted);

  font-size: 9px;
}

.media-upload {
  min-height: 58px;

  display: flex;
  align-items: center;
  justify-content: center;

  flex-direction: column;

  gap: 3px;

  margin-top: 10px;

  border: 1px dashed color-mix(in srgb, var(--primary) 25%, var(--border));

  border-radius: 9px;

  background: color-mix(in srgb, var(--primary) 3%, var(--surface));

  color: var(--primary);

  cursor: pointer;
}

.media-upload input {
  display: none;
}

.media-upload span {
  font-size: 10px;
  font-weight: 700;
}

.media-upload small {
  color: var(--text-muted);

  font-size: 8px;
}

.selected-file {
  display: flex;
  align-items: center;
  justify-content: space-between;

  gap: 10px;

  margin-top: 8px;
  padding: 8px 10px;

  border: 1px solid var(--border);
  border-radius: 8px;

  background: var(--surface-muted);
}

.selected-file div {
  min-width: 0;

  display: flex;
  flex-direction: column;

  gap: 2px;
}

.selected-file strong {
  overflow: hidden;

  color: var(--text-primary);

  font-size: 9px;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.selected-file span {
  color: var(--text-muted);

  font-size: 8px;
}

.selected-file button {
  width: 25px;
  height: 25px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 25px;

  border: 1px solid var(--border);
  border-radius: 6px;

  background: var(--surface);

  color: var(--text-muted);

  cursor: pointer;
}

.media-note {
  gap: 7px;

  margin-top: 9px;
  padding: 9px 10px;

  border: 1px solid color-mix(in srgb, var(--primary) 10%, var(--border));

  border-radius: 8px;

  background: color-mix(in srgb, var(--primary) 3%, var(--surface));

  color: var(--text-muted);

  font-size: 9px;
  line-height: 1.45;
}

.media-note svg {
  flex-shrink: 0;
  color: var(--primary);
}

/* ==========================================================================
   FORM
   ========================================================================== */

.form-grid {
  display: grid;

  grid-template-columns: repeat(2, minmax(0, 1fr));

  gap: 15px;

  padding: 16px;
}

.field {
  min-width: 0;

  display: flex;
  flex-direction: column;

  gap: 6px;
}

.field-full {
  grid-column: 1 / -1;
}

.field label {
  display: flex;
  align-items: center;

  gap: 3px;

  color: var(--text-primary);

  font-size: 10px;
  font-weight: 700;
}

.field label span {
  color: var(--primary);
}

.field input,
.field textarea,
.field select {
  width: 100%;

  box-sizing: border-box;

  border: 1px solid var(--border);
  border-radius: 8px;

  background: var(--surface);
  color: var(--text-primary);

  outline: none;

  font-family: inherit;
  font-size: 11px;

  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.field input,
.field select {
  height: 38px;
  padding: 0 10px;
}

.field textarea {
  min-height: 100px;

  padding: 10px;

  resize: vertical;

  line-height: 1.55;
}

.field input::placeholder,
.field textarea::placeholder {
  color: var(--text-muted);
}

.field input:focus,
.field textarea:focus,
.field select:focus {
  border-color: color-mix(in srgb, var(--primary) 48%, var(--border));

  box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 8%, transparent);
}

.field input.invalid,
.field textarea.invalid,
.field select.invalid {
  border-color: #ef4444;
}

.select-wrapper {
  position: relative;
}

.select-wrapper select {
  appearance: none;
  padding-right: 32px;
}

.select-wrapper svg {
  position: absolute;

  top: 50%;
  right: 10px;

  pointer-events: none;

  color: var(--text-muted);

  transform: translateY(-50%);
}

.field-hint {
  color: var(--text-muted);

  font-size: 8.5px;
  line-height: 1.4;
}

.field-error {
  color: #dc2626;

  font-size: 9px;
  line-height: 1.4;
}

/* ==========================================================================
   PRODUCT TYPE
   ========================================================================== */

.product-type-grid {
  display: grid;

  grid-template-columns: repeat(2, minmax(0, 1fr));

  gap: 9px;
}

.product-type-card {
  min-width: 0;

  gap: 9px;

  padding: 10px;

  border: 1px solid var(--border);
  border-radius: 9px;

  background: var(--surface);

  text-align: left;

  cursor: pointer;

  transition: border-color 0.15s ease, background 0.15s ease;
}

.product-type-card:hover {
  border-color: color-mix(in srgb, var(--primary) 25%, var(--border));
}

.product-type-card.selected {
  border-color: color-mix(in srgb, var(--primary) 38%, var(--border));

  background: color-mix(in srgb, var(--primary) 5%, var(--surface));
}

.type-card-icon {
  width: 31px;
  height: 31px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 31px;

  border: 1px solid var(--border);
  border-radius: 8px;

  background: var(--surface-muted);

  color: var(--text-muted);
}

.product-type-card.selected .type-card-icon {
  color: var(--primary);
}

.type-card-content {
  min-width: 0;

  flex: 1;

  display: flex;
  flex-direction: column;

  gap: 2px;
}

.product-type-card strong {
  color: var(--text-primary);

  font-size: 10px;
}

.product-type-card small {
  color: var(--text-muted);

  font-size: 8px;
  line-height: 1.35;
}

.type-check {
  width: 18px;
  height: 18px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 18px;

  color: var(--primary);
}

/* ==========================================================================
   SLUG
   ========================================================================== */

.slug-field {
  display: flex;
  align-items: center;

  overflow: hidden;

  min-height: 38px;

  border: 1px solid var(--border);
  border-radius: 8px;

  background: var(--surface);
}

.slug-field:focus-within {
  border-color: color-mix(in srgb, var(--primary) 48%, var(--border));

  box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 8%, transparent);
}

.slug-field > span {
  padding-left: 10px;

  color: var(--text-muted);

  font-size: 10px;
}

.slug-field input {
  height: 36px;

  flex: 1;

  border: 0 !important;

  border-radius: 0 !important;

  box-shadow: none !important;

  background: transparent;
}

.slug-field button {
  align-self: stretch;

  padding: 0 10px;

  border: 0;
  border-left: 1px solid var(--border);

  background: var(--surface-muted);

  color: var(--primary);

  font-size: 9px;
  font-weight: 700;

  cursor: pointer;
}

/* ==========================================================================
   DESCRIPTION
   ========================================================================== */

.description-fields {
  display: flex;
  flex-direction: column;

  gap: 15px;

  padding: 16px;
}

.description-fields textarea {
  min-height: 112px;
}

.textarea-meta {
  display: flex;
  justify-content: space-between;

  gap: 10px;
}

.textarea-meta > span {
  margin-left: auto;

  color: var(--text-muted);

  font-size: 8px;
}

/* ==========================================================================
   SUMMARY
   ========================================================================== */

.summary-body {
  padding: 0 16px 14px;
}

.summary-identity {
  gap: 10px;

  padding: 15px 0;
}

.summary-product-icon {
  width: 46px;
  height: 46px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  flex: 0 0 46px;

  border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));

  border-radius: 12px;

  background: color-mix(in srgb, var(--primary) 6%, var(--surface));

  color: var(--primary);
}

.summary-identity > div:last-child {
  min-width: 0;

  display: flex;
  flex-direction: column;

  gap: 3px;
}

.summary-identity strong {
  overflow: hidden;

  color: var(--text-primary);

  font-size: 12px;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.summary-identity span {
  color: var(--text-muted);

  font-size: 9px;
}

.summary-list {
  border-top: 1px solid var(--border);
}

.summary-row {
  min-height: 40px;

  justify-content: space-between;

  gap: 10px;

  border-bottom: 1px solid var(--border);
}

.summary-row:last-child {
  border-bottom: 0;
}

.summary-row > span {
  color: var(--text-muted);

  font-size: 9px;
}

.summary-row > strong {
  max-width: 60%;

  overflow: hidden;

  color: var(--text-secondary);

  font-size: 9px;

  text-align: right;

  text-overflow: ellipsis;
  white-space: nowrap;
}

.summary-status {
  display: inline-flex;

  align-items: center;

  gap: 5px;
}

.summary-status.active {
  color: var(--success);
}

.summary-status.draft {
  color: var(--warning);
}

.summary-status.inactive {
  color: var(--text-muted);
}

.summary-status.archived {
  color: #8250e8;
}

.status-dot {
  width: 5px;
  height: 5px;

  display: inline-block;

  border-radius: 50%;

  background: currentColor;
}

/* ==========================================================================
   PUBLISHING
   ========================================================================== */

.publishing-body {
  display: flex;
  flex-direction: column;

  gap: 16px;

  padding: 15px 16px 16px;
}

.visibility-grid {
  display: flex;
  flex-direction: column;

  gap: 7px;
}

.visibility-card {
  width: 100%;

  gap: 8px;

  padding: 9px;

  border: 1px solid var(--border);
  border-radius: 8px;

  background: var(--surface);

  color: var(--text-muted);

  text-align: left;

  cursor: pointer;
}

.visibility-card.selected {
  border-color: color-mix(in srgb, var(--primary) 35%, var(--border));

  background: color-mix(in srgb, var(--primary) 5%, var(--surface));

  color: var(--primary);
}

.visibility-card > span {
  min-width: 0;

  flex: 1;

  display: flex;
  flex-direction: column;

  gap: 2px;
}

.visibility-card strong {
  color: var(--text-primary);

  font-size: 9px;
}

.visibility-card small {
  color: var(--text-muted);

  font-size: 7.5px;
}

/* ==========================================================================
   SAVE
   ========================================================================== */

.save-card {
  position: sticky;

  top: 14px;

  padding: 13px;
}

.save-status {
  gap: 6px;

  margin-bottom: 9px;

  color: var(--text-muted);

  font-size: 9px;
}

.save-status-dot {
  width: 5px;
  height: 5px;

  border-radius: 50%;

  background: var(--warning);
}

.save-status-dot.ready {
  background: var(--success);
}

.save-button {
  width: 100%;
  min-height: 40px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  gap: 7px;

  border: 1px solid var(--primary);
  border-radius: 9px;

  background: var(--primary);

  color: #fff;

  font-size: 11px;
  font-weight: 700;

  cursor: pointer;
}

.save-button:disabled {
  opacity: 0.45;

  cursor: not-allowed;
}

.cancel-link {
  width: 100%;

  margin-top: 8px;

  padding: 5px;

  border: 0;

  background: transparent;

  color: var(--text-muted);

  font-size: 9px;

  cursor: pointer;
}

/* ==========================================================================
   INFO
   ========================================================================== */

.info-card {
  display: flex;
  align-items: flex-start;

  gap: 8px;

  padding: 11px;

  color: var(--text-muted);
}

.info-card > svg {
  flex: 0 0 auto;

  margin-top: 1px;

  color: var(--primary);
}

.info-card div {
  display: flex;
  flex-direction: column;

  gap: 3px;
}

.info-card strong {
  color: var(--text-secondary);

  font-size: 9px;
}

.info-card span {
  color: var(--text-muted);

  font-size: 8px;
  line-height: 1.45;
}

.info-manage-button {
  align-self: flex-start;
  margin-top: 8px;
  min-height: 34px;
  font-size: 11px;
}

/* ==========================================================================
   MOBILE
   ========================================================================== */

.mobile-save-bar {
  display: none;
}

/* ==========================================================================
   ANIMATION
   ========================================================================== */

.spin {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* ==========================================================================
   RESPONSIVE
   ========================================================================== */

@media (max-width: 1050px) {
  .content-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .side-column {
    display: grid;

    grid-template-columns: repeat(2, minmax(0, 1fr));

    align-items: start;
  }

  .save-card {
    position: static;
  }

  .info-card {
    grid-column: 1 / -1;
  }
}

@media (max-width: 820px) {
  .page-header {
    align-items: flex-start;

    flex-direction: column;
  }

  .header-actions {
    width: 100%;
  }

  .header-actions button {
    flex: 1;
  }

  .side-column {
    display: flex;
  }

  .workflow-card {
    align-items: flex-start;

    flex-direction: column;
  }

  .workflow-divider {
    width: 100%;
    height: 1px;
  }
}

@media (max-width: 650px) {
  .form-grid,
  .product-type-grid {
    grid-template-columns: 1fr;
  }

  .field-full {
    grid-column: auto;
  }

  .title-row h1 {
    font-size: 21px;
  }

  .title-row p {
    font-size: 11px;
  }

  .existing-image-wrapper,
  .no-image {
    min-height: 235px;
  }

  .existing-image {
    height: 235px;
  }

  .mobile-save-bar {
    position: sticky;

    right: 0;
    bottom: 0;
    left: 0;

    z-index: 20;

    display: flex;

    gap: 8px;

    margin-top: 14px;
    padding: 10px;

    border: 1px solid var(--border);
    border-radius: 12px;

    background: color-mix(in srgb, var(--surface) 94%, transparent);

    box-shadow: 0 -8px 24px rgb(26 47 72 / 8%);

    backdrop-filter: blur(14px);
  }

  .mobile-save-bar button {
    flex: 1;
  }
}

@media (max-width: 450px) {
  .breadcrumb {
    flex-wrap: wrap;
  }

  .slug-field > span {
    display: none;
  }

  .slug-field button {
    padding: 0 8px;
  }

  .header-actions {
    flex-direction: column;
  }

  .header-actions button {
    width: 100%;
  }
}

/* Create-page visual alignment: clean header, consistent cards and a docked summary. */
.product-edit-page {
  --edit-radius: 14px;
  width: 100%;
  min-width: 0;
  padding: 0 0 28px;
  color: var(--text-primary, #111827);
  background: var(--background, #f6f8fb);
}
.product-edit-page .page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin: 0 auto 20px;
  padding: 0 0 2px;
}
.product-edit-page .header-main {
  display: flex;
  align-items: flex-start;
  gap: 13px;
  min-width: 0;
  margin: 0;
}
.product-edit-page .back-button {
  margin-top: 26px;
}
.product-edit-page .header-copy {
  min-width: 0;
}
.product-edit-page .breadcrumb {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0 0 10px;
  color: var(--text-muted, #98a2b3);
  font-size: 12px;
}
.product-edit-page .title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
}
.product-edit-page .title-icon {
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  border-radius: 13px;
}
.product-edit-page .title-row h1 {
  margin: 0;
  font-size: clamp(22px, 2vw, 28px);
  line-height: 1.2;
  font-weight: 750;
  letter-spacing: -0.035em;
}
.product-edit-page .title-row p {
  max-width: 650px;
  margin: 6px 0 0;
  color: var(--text-secondary, #667085);
  font-size: 12px;
  line-height: 1.55;
}
.product-edit-page .header-actions {
  display: flex;
  align-items: center;
  gap: 9px;
  flex-shrink: 0;
  margin: 26px 0 0;
}
.product-edit-page .content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(285px, 320px);
  align-items: start;
  gap: 22px;
  width: 100%;
  margin: 0 auto;
}
.product-edit-page .main-column,
.product-edit-page .side-column {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-width: 0;
  margin: 0;
}
.product-edit-page .side-column {
  position: sticky;
  top: 24px;
  max-height: calc(100vh - 40px);
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  padding: 0 2px 5px 0;
}
.product-edit-page .card,
.product-edit-page .info-card,
.product-edit-page .save-card {
  overflow: hidden;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 14px;
  background: var(--surface, #fff);
  box-shadow: 0 5px 22px rgb(15 23 42 / 4%);
}
.product-edit-page .section-heading {
  display: flex;
  align-items: center;
  gap: 11px;
  margin: 0;
  padding: 16px 18px;
  border-bottom: 1px solid var(--border, #e5e7eb);
}
.product-edit-page .section-heading h2 {
  font-size: 14px;
  letter-spacing: -0.01em;
}
.product-edit-page .section-heading p {
  font-size: 11px;
}
.product-edit-page .form-grid,
.product-edit-page .description-fields {
  padding: 18px;
  gap: 16px;
}
.product-edit-page .media-content {
  padding: 18px;
}
.product-edit-page .field {
  gap: 7px;
}
.product-edit-page .field label {
  font-size: 11px;
}
.product-edit-page .field input,
.product-edit-page .field textarea,
.product-edit-page .field select {
  font-size: 12px;
  border-radius: 9px;
}
.product-edit-page .field input,
.product-edit-page .field select {
  height: 41px;
}
.product-edit-page .field textarea {
  line-height: 1.6;
}
.product-edit-page .product-type-card {
  min-height: 76px;
  padding: 12px;
}
.product-edit-page .product-type-card strong {
  font-size: 11px;
}
.product-edit-page .product-type-card small {
  font-size: 9px;
}
.product-edit-page .summary-body {
  padding: 0 18px 16px;
}
.product-edit-page .summary-identity {
  margin: 0;
  padding: 17px 0;
}
.product-edit-page .summary-row {
  margin: 0;
  min-height: 42px;
}
.product-edit-page .publishing-body {
  padding: 17px 18px 18px;
}
.product-edit-page .save-card {
  position: static;
  top: auto;
  padding: 15px;
}
.product-edit-page .save-status {
  margin: 0 0 11px;
}
.product-edit-page .info-card {
  padding: 14px;
}
.product-edit-page .mobile-save-bar {
  display: none;
}
.product-edit-page button:focus-visible,
.product-edit-page input:focus-visible,
.product-edit-page select:focus-visible,
.product-edit-page textarea:focus-visible {
  outline: 3px solid color-mix(in srgb, var(--primary, #2563eb) 24%, transparent);
  outline-offset: 2px;
}
@media (max-width: 1180px) {
  .product-edit-page .content-grid {
    grid-template-columns: minmax(0, 1fr) 290px;
    gap: 16px;
  }
}
@media (max-width: 980px) {
  .product-edit-page .content-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .product-edit-page .side-column {
    position: static;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    max-height: none;
    overflow: visible;
  }
  .product-edit-page .info-card {
    grid-column: 1 / -1;
  }
}
@media (max-width: 760px) {
  .product-edit-page .page-header {
    flex-direction: column;
    gap: 12px;
  }
  .product-edit-page .header-actions {
    width: 100%;
    margin-top: 0;
  }
  .product-edit-page .header-actions button {
    flex: 1;
  }
  .product-edit-page .side-column {
    display: flex;
  }
}
@media (max-width: 650px) {
  .product-edit-page .form-grid,
  .product-edit-page .product-type-grid {
    grid-template-columns: minmax(0, 1fr);
  }
  .product-edit-page .field-full {
    grid-column: auto;
  }
  .product-edit-page .form-grid,
  .product-edit-page .description-fields,
  .product-edit-page .media-content {
    padding: 14px;
  }
  .product-edit-page .section-heading {
    padding: 14px;
  }
  .product-edit-page .existing-image-wrapper,
  .product-edit-page .no-image {
    min-height: 220px;
  }
  .product-edit-page .existing-image {
    height: 220px;
  }
  .product-edit-page .mobile-save-bar {
    position: sticky;
    bottom: 8px;
    z-index: 20;
    display: flex;
    gap: 8px;
    margin-top: 14px;
    padding: 10px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 12px;
    background: color-mix(in srgb, var(--surface, #fff) 94%, transparent);
    box-shadow: 0 -8px 24px rgb(26 47 72 / 8%);
    backdrop-filter: blur(14px);
  }
  .product-edit-page .mobile-save-bar button {
    flex: 1;
  }
}
@media (prefers-reduced-motion: reduce) {
  .product-edit-page *,
  .product-edit-page *::before,
  .product-edit-page *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}

/* Product type guidance and stable sidebar behavior */
.product-edit-page .type-change-note {
  margin: 10px 0 0;
  padding: 10px 12px;
  border: 1px solid
    color-mix(in srgb, var(--primary, #2563eb) 16%, var(--border, #e5e7eb));
  border-radius: 9px;
  background: color-mix(in srgb, var(--primary, #2563eb) 4%, var(--surface, #fff));
  color: var(--text-secondary, #667085);
  font-size: 11px;
  line-height: 1.5;
}
/* Keep the complete sidebar below the fixed application toolbar while the
   document scrolls. Sticky belongs to the whole sidebar, not individual cards. */
.product-edit-page .side-column {
  position: sticky;
  top: 88px;
  align-self: start;
  height: max-content;
  max-height: none;
  overflow: visible;
  overscroll-behavior: auto;
  scrollbar-width: auto;
  z-index: 5;
}
.product-edit-page .side-column > .card,
.product-edit-page .side-column > .save-card {
  flex: 0 0 auto;
  position: static;
}
.product-edit-page .save-card {
  position: static;
}
@media (max-width: 980px) {
  .product-edit-page .side-column {
    position: static;
    max-height: none;
    overflow: visible;
  }
}
</style>
