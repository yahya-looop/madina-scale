<script setup lang="ts">
import { computed, onMounted, ref } from "vue";

import { Head, router } from "@inertiajs/vue3";

import ErpLayout from "@/layouts/ErpLayout.vue";

import Icon from "@/components/dashboard/Icon.vue";

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

  created_at: string;

  updated_at: string;

  category?: {
    id: number;

    name: string;
  } | null;

  brand?: {
    id: number;

    name: string;
  } | null;

  unit?: {
    id: number;

    name: string;

    short_name: string;
  } | null;

  default_variant?: {
    id: number;
    name?: string | null;
    sku?: string | null;
    model_number?: string | null;
    barcode?: string | null;
    selling_price: string | number;
    promotional_price: string | number | null;
    wholesale_price: string | number | null;
    is_default: boolean;
    status: string;
  } | null;

  primary_media?: {
    id: number;

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
  } | null;
}

interface Category {
  id: number;

  name: string;
}

interface Brand {
  id: number;

  name: string;
}

interface Unit {
  id: number;

  name: string;

  short_name: string;
}

interface ApiPayload<T> {
  data?: T;

  message?: string;
}

const props = withDefaults(
  defineProps<{
    userName?: string;

    userEmail?: string;
  }>(),

  {
    userName: "Yahya Admin",

    userEmail: "",
  }
);

const products = ref<Product[]>([]);

const categories = ref<Category[]>([]);

const brands = ref<Brand[]>([]);

const units = ref<Unit[]>([]);

const isLoading = ref(false);

const isDeleting = ref<number | null>(null);

const deleteTarget = ref<Product | null>(null);

const errorMessage = ref("");

const search = ref("");

const categoryFilter = ref("");

const brandFilter = ref("");

const unitFilter = ref("");

const typeFilter = ref("");

const statusFilter = ref("");

const visibilityFilter = ref("");

const filtersOpen = ref(false);

const filteredProducts = computed(() => {
  const term = search.value.trim().toLowerCase();

  return products.value

    .filter((product) => {
      if (term) {
        const searchable = [
          product.name,

          product.slug,

          product.sku,

          product.model_number,

          product.barcode,
        ]

          .filter(Boolean)

          .join(" ")

          .toLowerCase();

        if (!searchable.includes(term)) {
          return false;
        }
      }

      if (categoryFilter.value && String(product.category_id) !== categoryFilter.value) {
        return false;
      }

      if (brandFilter.value && String(product.brand_id ?? "") !== brandFilter.value) {
        return false;
      }

      if (unitFilter.value && String(product.unit_id) !== unitFilter.value) {
        return false;
      }

      if (typeFilter.value && product.product_type !== typeFilter.value) {
        return false;
      }

      if (statusFilter.value && product.status !== statusFilter.value) {
        return false;
      }

      if (visibilityFilter.value && product.visibility !== visibilityFilter.value) {
        return false;
      }

      return true;
    })

    .sort((a, b) => {
      const dateA = new Date(a.created_at).getTime();

      const dateB = new Date(b.created_at).getTime();

      if (dateB !== dateA) {
        return dateB - dateA;
      }

      return b.id - a.id;
    });
});

const totalProducts = computed(() => products.value.length);

const activeProducts = computed(
  () => products.value.filter((product) => product.status === "active").length
);

const draftProducts = computed(
  () => products.value.filter((product) => product.status === "draft").length
);

const variableProducts = computed(
  () => products.value.filter((product) => product.product_type === "variable").length
);

const activeFilterCount = computed(() => {
  return [
    categoryFilter.value,

    brandFilter.value,

    unitFilter.value,

    typeFilter.value,

    statusFilter.value,

    visibilityFilter.value,
  ].filter(Boolean).length;
});

async function apiRequest<T>(url: string, method: "GET" | "DELETE" = "GET"): Promise<T> {
  const csrfToken = document

    .querySelector('meta[name="csrf-token"]')

    ?.getAttribute("content");

  const response = await fetch(url, {
    method,

    headers: {
      Accept: "application/json",

      ...(csrfToken
        ? {
            "X-CSRF-TOKEN": csrfToken,
          }
        : {}),
    },

    credentials: "same-origin",
  });

  const payload = (await response.json()) as ApiPayload<T>;

  if (!response.ok) {
    throw new Error(payload.message ?? "Unable to complete the request.");
  }

  return payload.data as T;
}

async function loadProducts(): Promise<void> {
  isLoading.value = true;

  errorMessage.value = "";

  try {
    products.value = await apiRequest<Product[]>("/api/products");
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Unable to load products.";
  } finally {
    isLoading.value = false;
  }
}

async function loadCatalogOptions(): Promise<void> {
  try {
    const [categoryData, brandData, unitData] = await Promise.all([
      apiRequest<Category[]>("/api/product-categories"),

      apiRequest<Brand[]>("/api/brands"),

      apiRequest<Unit[]>("/api/units"),
    ]);

    categories.value = categoryData;

    brands.value = brandData;

    units.value = unitData;
  } catch {
    /*

     * Product data remains usable even if one of the

     * optional filter sources fails.

     */
  }
}

function refresh(): void {
  void Promise.all([loadProducts(), loadCatalogOptions()]);
}

function goToCreate(): void {
  router.visit("/products/create");
}

function goToShow(product: Product): void {
  router.visit(`/products/${product.id}`);
}

function goToEdit(product: Product): void {
  router.visit(`/products/${product.id}/edit`);
}

function deleteProduct(product: Product): void {
  if (isDeleting.value !== null) {
    return;
  }

  deleteTarget.value = product;
}

function closeDeleteModal(): void {
  if (isDeleting.value !== null) {
    return;
  }

  deleteTarget.value = null;
}

async function confirmDeleteProduct(): Promise<void> {
  const product = deleteTarget.value;

  if (!product || isDeleting.value !== null) {
    return;
  }

  isDeleting.value = product.id;

  errorMessage.value = "";

  try {
    await apiRequest(`/api/products/${product.id}`, "DELETE");

    products.value = products.value.filter((item) => item.id !== product.id);

    deleteTarget.value = null;
  } catch (error) {
    errorMessage.value =
      error instanceof Error ? error.message : "Unable to delete the product.";
  } finally {
    isDeleting.value = null;
  }
}

function clearFilters(): void {
  categoryFilter.value = "";

  brandFilter.value = "";

  unitFilter.value = "";

  typeFilter.value = "";

  statusFilter.value = "";

  visibilityFilter.value = "";
}

function formatDate(value: string): string {
  if (!value) {
    return "—";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",

    month: "short",

    year: "numeric",
  }).format(date);
}

function productImageUrl(product: Product): string | null {
  const path = product.primary_media?.path?.trim();

  if (!path) {
    return null;
  }

  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  return `/storage/${path.replace(/^\/+/, "")}`;
}

function formatMoney(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === "") return "—";
  const amount = Number(value);
  if (!Number.isFinite(amount)) return "—";
  return new Intl.NumberFormat("en-BD", {
    style: "currency",
    currency: "BDT",
    maximumFractionDigits: 2,
  }).format(amount);
}

function defaultVariantLabel(product: Product): string {
  if (product.product_type === "simple") {
    return product.sku?.trim() || "No SKU";
  }

  const variant = product.default_variant;
  if (!variant) return "Default variant not set";

  return variant.sku?.trim() || "No SKU";
}

function identitySecondaryLabel(product: Product): string {
  if (product.product_type === "simple") {
    if (product.model_number?.trim()) {
      return `Model: ${product.model_number.trim()}`;
    }

    if (product.barcode?.trim()) {
      return `Barcode: ${product.barcode.trim()}`;
    }

    return "Model not set";
  }

  const variant = product.default_variant;
  if (!variant) return "Variant not set";

  if (variant.name?.trim()) {
    return variant.name.trim();
  }

  if (variant.model_number?.trim()) {
    return `Model: ${variant.model_number.trim()}`;
  }

  if (variant.barcode?.trim()) {
    return `Barcode: ${variant.barcode.trim()}`;
  }

  return `Variant #${variant.id}`;
}

function productSellingPrice(product: Product): string {
  const variant = product.default_variant;
  if (!variant) return "Not set";

  const promo =
    variant.promotional_price === null ? null : Number(variant.promotional_price);
  const selling = Number(variant.selling_price);

  return promo !== null && Number.isFinite(promo) && promo > 0 && promo < selling
    ? formatMoney(promo)
    : formatMoney(variant.selling_price);
}

function productWholesalePrice(product: Product): string {
  return product.default_variant
    ? formatMoney(product.default_variant.wholesale_price)
    : "Not set";
}

function statusLabel(status: Product["status"]): string {
  return status.charAt(0).toUpperCase() + status.slice(1);
}

function typeLabel(type: Product["product_type"]): string {
  return type === "variable" ? "Variable" : "Simple";
}

onMounted(() => {
  refresh();
});
</script>

<template>
  <Head title="Products" />

  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <div class="products-page">
      <!-- =========================================================

                 PAGE HEADER

                 ========================================================= -->

      <div class="page-header">
        <div class="page-heading">
          <div class="breadcrumb">
            <span>Products</span>

            <span class="breadcrumb-separator">/</span>

            <span>Catalog</span>
          </div>

          <div class="title-row">
            <div class="title-icon">
              <Icon name="products" :size="25" />
            </div>

            <div>
              <h1>Products</h1>

              <p>Manage your product catalog, identity, classification and lifecycle.</p>
            </div>
          </div>
        </div>

        <div class="page-actions">
          <button
            class="btn secondary"
            type="button"
            :disabled="isLoading"
            @click="refresh"
          >
            <svg
              class="refresh-icon"
              :class="{ spinning: isLoading }"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <path
                d="M20 11a8.1 8.1 0 0 0-15.5-2"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
              />

              <path
                d="M4 4v5h5"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
              />

              <path
                d="M4 13a8.1 8.1 0 0 0 15.5 2"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
              />

              <path
                d="M20 20v-5h-5"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>

            <span>Refresh</span>
          </button>

          <button class="btn primary" type="button" @click="goToCreate">
            <Icon name="plus" :size="17" />

            <span>Add Product</span>
          </button>
        </div>
      </div>

      <!-- =========================================================

                 ERROR

                 ========================================================= -->

      <div v-if="errorMessage" class="error-banner" role="alert">
        <Icon name="info" :size="16" />

        <span>{{ errorMessage }}</span>

        <button type="button" aria-label="Dismiss error" @click="errorMessage = ''">
          <Icon name="close" :size="15" />
        </button>
      </div>

      <!-- =========================================================

                 KPI CARDS

                 ========================================================= -->

      <div class="stats-grid">
        <!-- Total -->

        <article class="stat-card">
          <div class="stat-icon stat-icon-product">
            <Icon name="products" :size="20" />
          </div>

          <div class="stat-content">
            <span>Total Products</span>

            <strong>{{ totalProducts }}</strong>
          </div>
        </article>

        <!-- Active -->

        <article class="stat-card">
          <div class="stat-icon stat-icon-active">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" />

              <path
                d="m8 12 2.5 2.5L16 9"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </div>

          <div class="stat-content">
            <span>Active</span>

            <strong>{{ activeProducts }}</strong>
          </div>
        </article>

        <!-- Draft -->

        <article class="stat-card">
          <div class="stat-icon stat-icon-draft">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path
                d="M7 3.5h7l4 4V20.5H7z"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linejoin="round"
              />

              <path
                d="M14 3.5v4h4"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linejoin="round"
              />

              <path
                d="M10 12h5M10 15.5h5"
                stroke="currentColor"
                stroke-width="1.7"
                stroke-linecap="round"
              />
            </svg>
          </div>

          <div class="stat-content">
            <span>Draft</span>

            <strong>{{ draftProducts }}</strong>
          </div>
        </article>

        <!-- Variable -->

        <article class="stat-card">
          <div class="stat-icon stat-icon-variable">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <rect
                x="4"
                y="4"
                width="10"
                height="10"
                rx="2"
                stroke="currentColor"
                stroke-width="1.8"
              />

              <rect
                x="10"
                y="10"
                width="10"
                height="10"
                rx="2"
                fill="currentColor"
                fill-opacity="0.12"
                stroke="currentColor"
                stroke-width="1.8"
              />
            </svg>
          </div>

          <div class="stat-content">
            <span>Variable</span>

            <strong>{{ variableProducts }}</strong>
          </div>
        </article>
      </div>

      <!-- =========================================================

                 PRODUCT DIRECTORY

                 ========================================================= -->

      <section class="catalog-card">
        <div class="catalog-header">
          <div>
            <span class="section-eyebrow">CATALOG</span>

            <h2>Product Directory</h2>

            <p>Search and filter products across your catalog.</p>
          </div>

          <div class="catalog-count">
            <strong>{{ filteredProducts.length }}</strong>

            <span>of {{ totalProducts }}</span>
          </div>
        </div>

        <!-- =====================================================

                     COMPACT SEARCH + FILTER BAR

                     ===================================================== -->

        <div class="toolbar">
          <div class="search-wrap">
            <Icon name="search" :size="17" />

            <input
              v-model="search"
              type="search"
              placeholder="Search by product, SKU, model or barcode"
              aria-label="Search products"
            />

            <button
              v-if="search"
              class="search-clear"
              type="button"
              aria-label="Clear search"
              @click="search = ''"
            >
              <Icon name="close" :size="14" />
            </button>
          </div>

          <div class="filter-control">
            <button
              class="filter-trigger"
              :class="{ active: activeFilterCount > 0 }"
              type="button"
              :aria-expanded="filtersOpen"
              @click="filtersOpen = !filtersOpen"
            >
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path
                  d="M4 6h16M7 12h10M10 18h4"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                />
              </svg>

              <span>Filters</span>

              <span v-if="activeFilterCount > 0" class="filter-count">
                {{ activeFilterCount }}
              </span>

              <svg
                class="filter-chevron"
                :class="{ open: filtersOpen }"
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
              >
                <path
                  d="m7 10 5 5 5-5"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </button>

            <!-- Compact filter panel -->

            <div v-if="filtersOpen" class="filter-panel">
              <div class="filter-panel-header">
                <div>
                  <strong>Filter Products</strong>

                  <span> Narrow down your product directory. </span>
                </div>

                <button
                  v-if="activeFilterCount > 0"
                  class="clear-filter-btn"
                  type="button"
                  @click="clearFilters"
                >
                  Clear all
                </button>
              </div>

              <div class="filter-grid">
                <label class="filter-field">
                  <span>Category</span>

                  <select v-model="categoryFilter">
                    <option value="">All categories</option>

                    <option
                      v-for="category in categories"
                      :key="category.id"
                      :value="String(category.id)"
                    >
                      {{ category.name }}
                    </option>
                  </select>
                </label>

                <label class="filter-field">
                  <span>Brand</span>

                  <select v-model="brandFilter">
                    <option value="">All brands</option>

                    <option
                      v-for="brand in brands"
                      :key="brand.id"
                      :value="String(brand.id)"
                    >
                      {{ brand.name }}
                    </option>
                  </select>
                </label>

                <label class="filter-field">
                  <span>Unit</span>

                  <select v-model="unitFilter">
                    <option value="">All units</option>

                    <option v-for="unit in units" :key="unit.id" :value="String(unit.id)">
                      {{ unit.name }}

                      <template v-if="unit.short_name">
                        ({{ unit.short_name }})
                      </template>
                    </option>
                  </select>
                </label>

                <label class="filter-field">
                  <span>Type</span>

                  <select v-model="typeFilter">
                    <option value="">All types</option>

                    <option value="simple">Simple</option>

                    <option value="variable">Variable</option>
                  </select>
                </label>

                <label class="filter-field">
                  <span>Status</span>

                  <select v-model="statusFilter">
                    <option value="">All statuses</option>

                    <option value="draft">Draft</option>

                    <option value="active">Active</option>

                    <option value="inactive">Inactive</option>

                    <option value="archived">Archived</option>
                  </select>
                </label>

                <label class="filter-field">
                  <span>Visibility</span>

                  <select v-model="visibilityFilter">
                    <option value="">All visibility</option>
                    <option value="visible">Visible</option>

                    <option value="hidden">Hidden</option>
                  </select>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- =====================================================

                     TABLE

                     ===================================================== -->

        <div class="table-wrap">
          <table class="products-table">
            <thead>
              <tr>
                <th>PRODUCT</th>

                <th>DEFAULT VARIANT / SKU</th>

                <th>CATEGORY</th>

                <th>BRAND</th>

                <th>SELLING PRICE</th>

                <th>WHOLESALE PRICE</th>

                <th>TYPE</th>

                <th>STATUS</th>

                <th>VISIBILITY</th>

                <th>UPDATED</th>

                <th class="actions-heading">ACTIONS</th>
              </tr>
            </thead>

            <tbody>
              <!-- Loading -->

              <tr v-if="isLoading">
                <td colspan="11" class="table-state">
                  <div class="loading-state">
                    <span class="loading-spinner"></span>

                    <span>Loading products...</span>
                  </div>
                </td>
              </tr>

              <!-- Empty -->

              <tr v-else-if="filteredProducts.length === 0">
                <td colspan="11" class="table-state">
                  <div class="empty-state">
                    <div class="empty-icon">
                      <Icon name="products" :size="24" />
                    </div>

                    <strong> No products found </strong>

                    <span> Try changing your search or filters. </span>

                    <button
                      v-if="activeFilterCount > 0"
                      type="button"
                      @click="clearFilters"
                    >
                      Clear filters
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Products -->

              <tr
                v-for="product in filteredProducts"
                v-else
                :key="product.id"
                class="product-row"
              >
                <td>
                  <button class="product-cell" type="button" @click="goToShow(product)">
                    <span class="product-mini-icon">
                      <img
                        v-if="productImageUrl(product)"
                        :src="productImageUrl(product)!"
                        :alt="product.primary_media?.alt_text || product.name"
                        loading="lazy"
                      />

                      <Icon v-else name="products" :size="17" />
                    </span>

                    <span class="product-copy">
                      <strong>
                        {{ product.name }}
                      </strong>

                      <small>
                        {{ product.slug }}
                      </small>
                    </span>
                  </button>
                </td>

                <td>
                  <div class="identity-cell">
                    <strong class="variant-name">
                      {{ defaultVariantLabel(product) }}
                    </strong>

                    <small>
                      {{ identitySecondaryLabel(product) }}
                    </small>
                  </div>
                </td>

                <td>
                  <span class="plain-value">
                    {{ product.category?.name || "—" }}
                  </span>
                </td>

                <td>
                  <span class="brand-value">
                    {{ product.brand?.name || "—" }}
                  </span>
                </td>

                <td>
                  <div class="price-cell">
                    <strong class="plain-value price-value">
                      {{ productSellingPrice(product) }}
                    </strong>
                    <small v-if="product.default_variant?.promotional_price">
                      Regular: {{ formatMoney(product.default_variant.selling_price) }}
                    </small>
                  </div>
                </td>

                <td>
                  <span class="plain-value price-value">
                    {{ productWholesalePrice(product) }}
                  </span>
                </td>

                <td>
                  <span
                    class="type-badge"
                    :class="product.product_type === 'variable' ? 'variable' : 'simple'"
                  >
                    {{ typeLabel(product.product_type) }}
                  </span>
                </td>

                <td>
                  <span class="status-badge" :class="product.status">
                    <span class="status-dot"></span>

                    {{ statusLabel(product.status) }}
                  </span>
                </td>

                <td>
                  <span class="visibility-badge" :class="product.visibility">
                    {{ product.visibility === "visible" ? "Visible" : "Hidden" }}
                  </span>
                </td>

                <td>
                  <span class="date-value">
                    {{ formatDate(product.updated_at) }}
                  </span>
                </td>

                <td>
                  <div class="row-actions">
                    <button
                      class="row-action"
                      type="button"
                      title="View product"
                      aria-label="View product"
                      @click="goToShow(product)"
                    >
                      <Icon name="eye" :size="16" />
                    </button>

                    <button
                      class="row-action"
                      type="button"
                      title="Edit product"
                      aria-label="Edit product"
                      @click="goToEdit(product)"
                    >
                      <Icon name="edit" :size="16" />
                    </button>

                    <button
                      class="row-action danger"
                      type="button"
                      title="Delete product"
                      aria-label="Delete product"
                      :disabled="isDeleting === product.id"
                      @click="deleteProduct(product)"
                    >
                      <Icon name="trash" :size="16" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- =========================================================

       DELETE PRODUCT CONFIRMATION MODAL

       ========================================================= -->

    <div
      v-if="deleteTarget"
      class="delete-modal-backdrop"
      role="presentation"
      @click.self="closeDeleteModal"
    >
      <section
        class="delete-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-product-title"
      >
        <div class="delete-modal-header">
          <div class="delete-modal-icon">
            <Icon name="trash" :size="21" />
          </div>

          <button
            class="delete-modal-close"
            type="button"
            aria-label="Close delete confirmation"
            :disabled="isDeleting !== null"
            @click="closeDeleteModal"
          >
            <Icon name="close" :size="16" />
          </button>
        </div>

        <div class="delete-modal-content">
          <span class="delete-modal-eyebrow">DELETE PRODUCT</span>

          <h2 id="delete-product-title">Delete this product?</h2>

          <p class="delete-modal-description">
            You are about to move the following product to trash. Please review the
            product before continuing.
          </p>

          <div class="delete-product-preview">
            <div class="delete-product-image">
              <img
                v-if="productImageUrl(deleteTarget)"
                :src="productImageUrl(deleteTarget)!"
                :alt="deleteTarget.primary_media?.alt_text || deleteTarget.name"
              />

              <Icon v-else name="products" :size="22" />
            </div>

            <div class="delete-product-info">
              <strong>{{ deleteTarget.name }}</strong>

              <span>{{ deleteTarget.slug }}</span>
            </div>
          </div>

          <div class="delete-product-meta">
            <div class="delete-meta-item">
              <span>SKU</span>

              <strong>{{ deleteTarget.sku || "—" }}</strong>
            </div>

            <div class="delete-meta-item">
              <span>Model</span>

              <strong>{{ deleteTarget.model_number || "—" }}</strong>
            </div>

            <div class="delete-meta-item">
              <span>Category</span>

              <strong>{{ deleteTarget.category?.name || "—" }}</strong>
            </div>

            <div class="delete-meta-item">
              <span>Brand</span>

              <strong>{{ deleteTarget.brand?.name || "—" }}</strong>
            </div>

            <div class="delete-meta-item">
              <span>Type</span>

              <strong>{{ typeLabel(deleteTarget.product_type) }}</strong>
            </div>

            <div class="delete-meta-item">
              <span>Status</span>

              <strong>{{ statusLabel(deleteTarget.status) }}</strong>
            </div>
          </div>

          <div class="delete-warning">
            <Icon name="info" :size="16" />

            <span>
              This product will be moved to trash and removed from the active product
              catalog.
            </span>
          </div>
        </div>

        <div class="delete-modal-footer">
          <button
            class="delete-cancel-btn"
            type="button"
            :disabled="isDeleting !== null"
            @click="closeDeleteModal"
          >
            Cancel
          </button>

          <button
            class="delete-confirm-btn"
            type="button"
            :disabled="isDeleting === deleteTarget.id"
            @click="confirmDeleteProduct"
          >
            <span
              v-if="isDeleting === deleteTarget.id"
              class="delete-button-spinner"
            ></span>

            <Icon v-else name="trash" :size="15" />

            <span>
              {{ isDeleting === deleteTarget.id ? "Deleting..." : "Delete Product" }}
            </span>
          </button>
        </div>
      </section>
    </div>
  </ErpLayout>
</template>

<style scoped>
.price-value {
  font-weight: 700;
  white-space: nowrap;
}
.variant-name {
  max-width: 190px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.price-cell {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;
}
.price-cell small {
  color: var(--text-muted);
  font-size: 8px;
  white-space: nowrap;
}
/* ==========================================================================

   PRODUCTS

   Shared Madina Scale enterprise design language

   Aligned with Roles & Permissions + System Settings

   ========================================================================== */

.products-page {
  --product-radius: 14px;

  --product-radius-sm: 9px;

  --product-accent: var(--primary);

  min-height: 100%;

  padding: 0;

  color: var(--text-primary);
}

/* ==========================================================================

   SHARED FLEX PATTERN

   ========================================================================== */

.page-header,
.title-row,
.page-actions,
.catalog-header,
.toolbar,
.filter-panel-header,
.product-cell,
.identity-cell,
.row-actions {
  display: flex;

  align-items: center;
}

/* ==========================================================================

   PAGE HEADER

   ========================================================================== */

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

  display: inline-flex;

  align-items: center;

  justify-content: center;

  flex: 0 0 auto;

  border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));

  border-radius: 12px;

  background: color-mix(in srgb, var(--primary) 10%, var(--surface));

  color: var(--primary);

  box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 7%, transparent);
}

.title-row h1 {
  margin: 0;

  color: var(--text-primary);

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
  flex-shrink: 0;

  gap: 8px;
}

/* ==========================================================================

   BUTTONS

   ========================================================================== */

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

  cursor: pointer;

  transition: border-color 0.16s ease, background 0.16s ease, color 0.16s ease,
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

.btn.secondary:hover:not(:disabled) {
  background: var(--surface-muted);

  color: var(--text-primary);
}

.btn.primary {
  border-color: color-mix(in srgb, var(--primary) 88%, transparent);

  background: linear-gradient(
    135deg,
    color-mix(in srgb, var(--primary) 96%, white),
    color-mix(in srgb, var(--primary) 86%, black)
  );

  color: #fff;

  box-shadow: 0 5px 14px color-mix(in srgb, var(--primary) 15%, transparent);
}

.btn.primary:hover:not(:disabled) {
  box-shadow: 0 7px 18px color-mix(in srgb, var(--primary) 20%, transparent);
}

.refresh-icon {
  width: 15px;

  height: 15px;
}

.refresh-icon.spinning {
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* ==========================================================================

   ERROR

   ========================================================================== */

.error-banner {
  display: flex;

  align-items: center;

  gap: 8px;

  margin-bottom: 14px;

  padding: 9px 11px;

  border: 1px solid color-mix(in srgb, #dc2626 18%, var(--border));

  border-radius: 9px;

  background: color-mix(in srgb, #dc2626 5%, var(--surface));

  color: #b91c1c;

  font-size: 11px;
}

.error-banner > span {
  flex: 1;
}

.error-banner button {
  width: 26px;

  height: 26px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  border: 0;

  border-radius: 7px;

  background: transparent;

  color: inherit;

  cursor: pointer;
}

.error-banner button:hover {
  background: color-mix(in srgb, #dc2626 8%, transparent);
}

/* ==========================================================================

   KPI / STAT CARDS

   ========================================================================== */

.stats-grid {
  display: grid;

  grid-template-columns: repeat(4, minmax(0, 1fr));

  gap: 12px;

  margin-bottom: 14px;
}

.stat-card {
  display: flex;

  align-items: center;

  gap: 11px;

  min-height: 78px;

  padding: 12px 14px;

  border: 1px solid var(--border);

  border-radius: 12px;

  background: color-mix(in srgb, var(--surface) 96%, transparent);

  box-shadow: 0 4px 16px rgba(26, 47, 72, 0.035);
}

.stat-icon {
  width: 34px;

  height: 34px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  flex: 0 0 34px;

  border-radius: 9px;
}

.stat-icon svg {
  width: 17px;

  height: 17px;
}

.stat-icon-product {
  background: color-mix(in srgb, var(--primary) 10%, var(--surface));

  color: var(--primary);
}

.stat-icon-active {
  background: color-mix(in srgb, var(--success) 12%, var(--surface));

  color: var(--success);
}

.stat-icon-draft {
  background: color-mix(in srgb, var(--warning) 13%, var(--surface));

  color: var(--warning);
}

.stat-icon-variable {
  background: color-mix(in srgb, #0f9b8e 10%, var(--surface));

  color: #0f9b8e;
}

.stat-content {
  min-width: 0;
}

.stat-content span {
  display: block;

  color: var(--text-muted);

  font-size: 10px;

  line-height: 1.3;
}

.stat-content strong {
  display: block;

  margin: 1px 0;

  color: var(--text-primary);

  font-size: 21px;

  line-height: 1.1;

  letter-spacing: -0.02em;

  font-weight: 700;

  font-variant-numeric: tabular-nums;
}

/* ==========================================================================

   PRODUCT DIRECTORY CARD

   ========================================================================== */

.catalog-card {
  overflow: visible;

  border: 1px solid var(--border);

  border-radius: 14px;

  background: var(--surface);

  box-shadow: 0 8px 28px rgba(26, 47, 72, 0.045);
}

.catalog-header {
  justify-content: space-between;

  gap: 20px;

  padding: 16px 16px 13px;

  border-bottom: 1px solid var(--border);
}

.section-eyebrow {
  display: block;

  margin-bottom: 4px;

  color: var(--primary);

  font-size: 10px;

  line-height: 1.2;

  font-weight: 750;

  letter-spacing: 0.55px;
}

.catalog-header h2 {
  margin: 0;

  color: var(--text-primary);

  font-size: 14px;

  line-height: 1.3;

  letter-spacing: -0.01em;

  font-weight: 700;
}

.catalog-header p {
  margin: 4px 0 0;

  color: var(--text-secondary);

  font-size: 11px;

  line-height: 1.45;
}

.catalog-count {
  display: flex;

  align-items: baseline;

  gap: 4px;

  color: var(--text-muted);

  font-size: 10px;

  white-space: nowrap;
}

.catalog-count strong {
  color: var(--text-primary);

  font-size: 11px;

  font-weight: 700;
}

/* ==========================================================================

   TOOLBAR

   ========================================================================== */

.toolbar {
  position: relative;

  z-index: 5;

  gap: 8px;

  padding: 11px 12px;

  border-bottom: 1px solid var(--border);

  background: var(--surface);
}

.search-wrap {
  min-width: 0;

  flex: 1;

  height: 36px;

  display: flex;

  align-items: center;

  gap: 8px;

  padding: 0 10px;

  border: 1px solid var(--border);

  border-radius: 8px;

  background: var(--surface);

  color: var(--text-muted);

  transition: border-color 0.16s ease, box-shadow 0.16s ease;
}

.search-wrap:focus-within {
  border-color: color-mix(in srgb, var(--primary) 42%, var(--border));

  box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 7%, transparent);
}

.search-wrap input {
  width: 100%;

  min-width: 0;

  height: 100%;

  padding: 0;

  border: 0;

  outline: 0;

  background: transparent;

  color: var(--text-primary);

  font-family: inherit;

  font-size: 11px;

  line-height: 1.4;
}

.search-wrap input::placeholder {
  color: var(--text-muted);
}

.search-clear {
  width: 23px;

  height: 23px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  flex: 0 0 23px;

  border: 0;

  border-radius: 6px;

  background: transparent;

  color: var(--text-muted);

  cursor: pointer;
}

.search-clear:hover {
  background: var(--surface-muted);

  color: var(--text-primary);
}

/* ==========================================================================

   FILTER

   ========================================================================== */

.filter-control {
  position: relative;

  flex: 0 0 auto;
}

.filter-trigger {
  height: 36px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  gap: 6px;

  padding: 0 10px;

  border: 1px solid var(--border);

  border-radius: 8px;

  background: var(--surface);

  color: var(--text-secondary);

  font-size: 11px;

  font-weight: 650;

  cursor: pointer;

  transition: border-color 0.16s ease, background 0.16s ease, color 0.16s ease;
}

.filter-trigger > svg:first-child {
  width: 14px;

  height: 14px;
}

.filter-trigger:hover,
.filter-trigger.active {
  border-color: color-mix(in srgb, var(--primary) 30%, var(--border));

  background: color-mix(in srgb, var(--primary) 5%, var(--surface));

  color: var(--primary);
}

.filter-count {
  min-width: 17px;

  height: 17px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  padding: 0 4px;

  border-radius: 999px;

  background: var(--primary);

  color: #fff;

  font-size: 9px;

  line-height: 1;

  font-weight: 750;
}

.filter-chevron {
  width: 12px;

  height: 12px;

  margin-left: 1px;

  transition: transform 0.16s ease;
}

.filter-chevron.open {
  transform: rotate(180deg);
}

/* ==========================================================================

   FILTER PANEL

   ========================================================================== */

.filter-panel {
  position: absolute;

  top: calc(100% + 7px);

  right: 0;

  width: min(610px, calc(100vw - 40px));

  padding: 12px;

  border: 1px solid var(--border);

  border-radius: 11px;

  background: var(--surface);

  box-shadow: 0 14px 36px rgba(26, 47, 72, 0.1), 0 4px 12px rgba(26, 47, 72, 0.045);
}

.filter-panel-header {
  justify-content: space-between;

  gap: 12px;

  margin-bottom: 10px;
}

.filter-panel-header strong {
  display: block;

  color: var(--text-primary);

  font-size: 11px;

  line-height: 1.3;

  font-weight: 700;
}

.filter-panel-header span {
  display: block;

  margin-top: 2px;

  color: var(--text-muted);

  font-size: 9px;

  line-height: 1.4;
}

.clear-filter-btn {
  padding: 2px 0;

  border: 0;

  background: transparent;

  color: var(--primary);

  font-size: 10px;

  font-weight: 650;

  cursor: pointer;
}

.clear-filter-btn:hover {
  text-decoration: underline;
}

.filter-grid {
  display: grid;

  grid-template-columns: repeat(3, minmax(0, 1fr));

  gap: 8px;
}

.filter-field {
  min-width: 0;
}

.filter-field > span {
  display: block;

  margin-bottom: 4px;

  color: var(--text-muted);

  font-size: 9px;

  line-height: 1.2;

  font-weight: 650;
}

.filter-field select {
  width: 100%;

  height: 32px;

  padding: 0 8px;

  border: 1px solid var(--border);

  border-radius: 7px;

  outline: 0;

  background: var(--surface);

  color: var(--text-secondary);

  font-family: inherit;

  font-size: 10px;

  cursor: pointer;
}

.filter-field select:focus {
  border-color: color-mix(in srgb, var(--primary) 38%, var(--border));

  box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 6%, transparent);
}

/* ==========================================================================

   TABLE

   ========================================================================== */

.table-wrap {
  width: 100%;

  overflow-x: auto;
}

.products-table {
  width: 100%;

  min-width: 1190px;

  border-collapse: collapse;
}

.products-table th {
  height: 38px;

  padding: 0 10px;

  border-bottom: 1px solid var(--border);

  background: color-mix(in srgb, var(--surface-muted) 45%, var(--surface));

  color: var(--text-muted);

  font-size: 9px;

  line-height: 1;

  font-weight: 750;

  letter-spacing: 0.35px;

  text-align: left;

  white-space: nowrap;
}

.products-table th:first-child,
.products-table td:first-child {
  padding-left: 14px;
}

.products-table th:last-child,
.products-table td:last-child {
  padding-right: 14px;
}

.products-table td {
  height: 62px;

  padding: 7px 10px;

  border-bottom: 1px solid var(--border);

  color: var(--text-secondary);

  font-size: 10px;

  line-height: 1.4;

  vertical-align: middle;
}

.product-row {
  transition: background 0.14s ease;
}

.product-row:hover {
  background: color-mix(in srgb, var(--primary) 2.5%, var(--surface));
}

.product-row:last-child td {
  border-bottom: 0;
}

/* ==========================================================================

   PRODUCT CELL

   ========================================================================== */

.product-cell {
  gap: 8px;

  padding: 0;

  border: 0;

  background: transparent;

  text-align: left;

  cursor: pointer;
}

.product-mini-icon {
  width: 34px;

  height: 34px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  flex: 0 0 34px;

  border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));

  border-radius: 9px;

  background: color-mix(in srgb, var(--primary) 6%, var(--surface));

  color: var(--primary);
}

.product-mini-icon img {
  width: 100%;

  height: 100%;

  display: block;

  object-fit: cover;

  border-radius: 8px;
}

.product-copy {
  min-width: 0;
}

.product-copy strong,
.product-copy small,
.identity-cell strong,
.identity-cell small {
  display: block;
}

.product-copy strong {
  max-width: 230px;

  overflow: hidden;

  color: var(--text-primary);

  font-size: 11px;

  line-height: 1.3;

  font-weight: 650;

  text-overflow: ellipsis;

  white-space: nowrap;
}

.product-copy small {
  max-width: 230px;

  margin-top: 2px;

  overflow: hidden;

  color: var(--text-muted);

  font-size: 9px;

  line-height: 1.3;

  text-overflow: ellipsis;

  white-space: nowrap;
}

.identity-cell {
  display: block;

  min-width: 0;
}

.identity-cell strong {
  color: var(--text-primary);

  font-size: 10px;

  line-height: 1.3;

  font-weight: 650;
}

.identity-cell small {
  margin-top: 2px;

  color: var(--primary);

  font-size: 7.8px;

  line-height: 1.3;
}

/* ==========================================================================

   SIMPLE VALUES

   ========================================================================== */

.plain-value {
  color: var(--text-primary);

  font-size: 10px;

  line-height: 1.3;

  font-weight: 600;
}

.brand-value {
  color: var(--text-secondary);

  font-size: 10px;

  line-height: 1.3;
}

/* ==========================================================================

   TYPE BADGES

   ========================================================================== */

.type-badge,
.status-badge,
.visibility-badge {
  display: inline-flex;

  align-items: center;

  white-space: nowrap;

  font-size: 9px;

  line-height: 1;

  font-weight: 650;
}

.type-badge {
  min-height: 23px;

  padding: 0 8px;

  border-radius: 7px;
}

.type-badge.simple {
  border: 1px solid var(--border);

  background: var(--surface-muted);

  color: var(--text-secondary);
}

.type-badge.variable {
  border: 1px solid color-mix(in srgb, #8250e8 18%, var(--border));

  background: color-mix(in srgb, #8250e8 6%, var(--surface));

  color: #8250e8;
}

/* ==========================================================================

   STATUS BADGES

   ========================================================================== */

.status-badge {
  min-height: 23px;

  gap: 5px;

  padding: 0 8px;

  border: 1px solid transparent;

  border-radius: 999px;
}

.status-dot {
  width: 5px;

  height: 5px;

  flex: 0 0 5px;

  border-radius: 50%;
}

.status-badge.active {
  border-color: color-mix(in srgb, var(--success) 20%, var(--border));

  background: color-mix(in srgb, var(--success) 6%, var(--surface));

  color: var(--success);
}

.status-badge.active .status-dot {
  background: var(--success);
}

.status-badge.draft {
  border-color: color-mix(in srgb, var(--warning) 22%, var(--border));

  background: color-mix(in srgb, var(--warning) 7%, var(--surface));

  color: var(--warning);
}

.status-badge.draft .status-dot {
  background: var(--warning);
}

.status-badge.inactive {
  border-color: var(--border);

  background: var(--surface-muted);

  color: var(--text-secondary);
}

.status-badge.inactive .status-dot {
  background: var(--text-muted);
}

.status-badge.archived {
  border-color: color-mix(in srgb, #8250e8 18%, var(--border));

  background: color-mix(in srgb, #8250e8 6%, var(--surface));

  color: #8250e8;
}

.status-badge.archived .status-dot {
  background: #8250e8;
}

/* ==========================================================================

   VISIBILITY

   ========================================================================== */

.visibility-badge {
  min-height: 23px;

  padding: 0 8px;

  border: 1px solid var(--border);

  border-radius: 7px;
}

.visibility-badge.visible {
  background: color-mix(in srgb, var(--primary) 3%, var(--surface));

  color: var(--text-secondary);
}

.visibility-badge.hidden {
  background: var(--surface-muted);

  color: var(--text-muted);
}

/* ==========================================================================

   DATE

   ========================================================================== */

.date-value {
  color: var(--text-muted);

  font-size: 9px;

  line-height: 1.3;

  white-space: nowrap;
}

/* ==========================================================================

   ROW ACTIONS

   ========================================================================== */

.actions-heading {
  text-align: right !important;
}

.row-actions {
  justify-content: flex-end;

  gap: 4px;
}

.row-action {
  width: 29px;

  height: 29px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  border: 1px solid var(--border);

  border-radius: 8px;

  background: var(--surface);

  color: var(--text-secondary);

  cursor: pointer;

  transition: border-color 0.14s ease, background 0.14s ease, color 0.14s ease;
}

.row-action:hover:not(:disabled) {
  border-color: color-mix(in srgb, var(--primary) 28%, var(--border));

  background: color-mix(in srgb, var(--primary) 4%, var(--surface));

  color: var(--primary);
}

.row-action.danger:hover:not(:disabled) {
  border-color: color-mix(in srgb, #dc2626 22%, var(--border));

  background: color-mix(in srgb, #dc2626 4%, var(--surface));

  color: #dc2626;
}

.row-action:disabled {
  opacity: 0.45;

  cursor: not-allowed;
}

/* ==========================================================================

   TABLE STATES

   ========================================================================== */

.table-state {
  height: 210px !important;

  text-align: center !important;
}

.loading-state,
.empty-state {
  display: flex;

  flex-direction: column;

  align-items: center;

  justify-content: center;

  gap: 6px;
}

.loading-state {
  color: var(--text-secondary);

  font-size: 11px;
}

.loading-spinner {
  width: 23px;

  height: 23px;

  border: 2px solid color-mix(in srgb, var(--primary) 14%, transparent);

  border-top-color: var(--primary);

  border-radius: 50%;

  animation: spin 0.75s linear infinite;
}

.empty-icon {
  width: 44px;

  height: 44px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  margin-bottom: 2px;

  border: 1px solid var(--border);

  border-radius: 11px;

  background: var(--surface-muted);

  color: var(--text-muted);
}

.empty-state strong {
  color: var(--text-primary);

  font-size: 12px;

  line-height: 1.3;

  font-weight: 650;
}

.empty-state span {
  color: var(--text-muted);

  font-size: 10px;

  line-height: 1.4;
}

.empty-state button {
  margin-top: 4px;

  padding: 0;

  border: 0;

  background: transparent;

  color: var(--primary);

  font-size: 10px;

  font-weight: 650;

  cursor: pointer;
}

/* ==========================================================================

   DARK THEME

   Semantic variables do the heavy lifting.

   Only table/card depth needs a small adjustment.

   ========================================================================== */

:global(.dark) .stat-card,
:global(html.dark) .stat-card,
:global(body.dark) .stat-card,
:global([data-theme="dark"]) .stat-card,
:global(.dark) .catalog-card,
:global(html.dark) .catalog-card,
:global(body.dark) .catalog-card,
:global([data-theme="dark"]) .catalog-card,
:global(.dark) .filter-panel,
:global(html.dark) .filter-panel,
:global(body.dark) .filter-panel,
:global([data-theme="dark"]) .filter-panel {
  box-shadow: 0 8px 28px rgba(0, 0, 0, 0.14);
}

/* ==========================================================================

   RESPONSIVE

   ========================================================================== */

@media (max-width: 1100px) {
  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 760px) {
  .products-page {
    padding: 0;
  }

  .page-header {
    align-items: flex-start;

    flex-direction: column;
  }

  .page-actions {
    width: 100%;
  }

  .page-actions .btn {
    flex: 1;
  }

  .toolbar {
    align-items: stretch;

    flex-direction: column;
  }

  .filter-control {
    width: 100%;
  }

  .filter-trigger {
    width: 100%;
  }

  .filter-panel {
    left: 0;

    right: auto;

    width: 100%;
  }

  .filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .catalog-header {
    align-items: flex-start;
  }
}

@media (max-width: 520px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }

  .filter-grid {
    grid-template-columns: 1fr;
  }

  .filter-panel {
    width: calc(100vw - 42px);
  }

  .catalog-count {
    display: none;
  }

  .title-row h1 {
    font-size: 22px;
  }
}

/* ==========================================================================

   DELETE PRODUCT MODAL

   ========================================================================== */

.delete-modal-backdrop {
  position: fixed;

  inset: 0;

  z-index: 1200;

  display: flex;

  align-items: center;

  justify-content: center;

  padding: 20px;

  background: rgba(15, 23, 42, 0.48);

  backdrop-filter: blur(7px);

  -webkit-backdrop-filter: blur(7px);

  animation: delete-modal-backdrop-in 0.18s ease-out;
}

.delete-modal {
  width: min(500px, 100%);

  overflow: hidden;

  border: 1px solid var(--border);

  border-radius: 18px;

  background: var(--surface);

  box-shadow: 0 30px 80px rgba(15, 23, 42, 0.18), 0 8px 28px rgba(15, 23, 42, 0.08);

  animation: delete-modal-in 0.2s ease-out;
}

.delete-modal-header {
  display: flex;

  align-items: center;

  justify-content: space-between;

  padding: 18px 18px 0;
}

.delete-modal-icon {
  width: 42px;

  height: 42px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  border: 1px solid color-mix(in srgb, #dc2626 20%, var(--border));

  border-radius: 12px;

  background: color-mix(in srgb, #dc2626 8%, var(--surface));

  color: #dc2626;
}

.delete-modal-close {
  width: 32px;

  height: 32px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  border: 1px solid transparent;

  border-radius: 8px;

  background: transparent;

  color: var(--text-muted);

  cursor: pointer;

  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.delete-modal-close:hover:not(:disabled) {
  border-color: var(--border);

  background: var(--surface-muted);

  color: var(--text-primary);
}

.delete-modal-close:disabled {
  opacity: 0.45;

  cursor: not-allowed;
}

.delete-modal-content {
  padding: 16px 18px 18px;
}

.delete-modal-eyebrow {
  display: block;

  margin-bottom: 5px;

  color: #dc2626;

  font-size: 9px;

  line-height: 1.2;

  font-weight: 800;

  letter-spacing: 0.7px;
}

.delete-modal-content h2 {
  margin: 0;

  color: var(--text-primary);

  font-size: 20px;

  line-height: 1.2;

  font-weight: 750;

  letter-spacing: -0.025em;
}

.delete-modal-description {
  margin: 7px 0 15px;

  color: var(--text-secondary);

  font-size: 11px;

  line-height: 1.55;
}

.delete-product-preview {
  display: flex;

  align-items: center;

  gap: 11px;

  padding: 11px;

  border: 1px solid var(--border);

  border-radius: 11px;

  background: color-mix(in srgb, var(--surface-muted) 48%, var(--surface));
}

.delete-product-image {
  width: 46px;

  height: 46px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  flex: 0 0 46px;

  overflow: hidden;

  border: 1px solid color-mix(in srgb, var(--primary) 16%, var(--border));

  border-radius: 10px;

  background: color-mix(in srgb, var(--primary) 6%, var(--surface));

  color: var(--primary);
}

.delete-product-image img {
  width: 100%;

  height: 100%;

  display: block;

  object-fit: cover;
}

.delete-product-info {
  min-width: 0;
}

.delete-product-info strong,
.delete-product-info span {
  display: block;

  overflow: hidden;

  text-overflow: ellipsis;

  white-space: nowrap;
}

.delete-product-info strong {
  color: var(--text-primary);

  font-size: 12px;

  line-height: 1.35;

  font-weight: 700;
}

.delete-product-info span {
  margin-top: 3px;

  color: var(--text-muted);

  font-size: 9px;

  line-height: 1.3;
}

.delete-product-meta {
  display: grid;

  grid-template-columns: repeat(2, minmax(0, 1fr));

  gap: 7px;

  margin-top: 10px;
}

.delete-meta-item {
  min-width: 0;

  padding: 8px 9px;

  border: 1px solid var(--border);

  border-radius: 8px;

  background: var(--surface);
}

.delete-meta-item span,
.delete-meta-item strong {
  display: block;
}

.delete-meta-item span {
  margin-bottom: 3px;

  color: var(--text-muted);

  font-size: 8px;

  line-height: 1.2;

  font-weight: 650;

  text-transform: uppercase;

  letter-spacing: 0.25px;
}

.delete-meta-item strong {
  overflow: hidden;

  color: var(--text-secondary);

  font-size: 9px;

  line-height: 1.3;

  font-weight: 650;

  text-overflow: ellipsis;

  white-space: nowrap;
}

.delete-warning {
  display: flex;

  align-items: flex-start;

  gap: 8px;

  margin-top: 11px;

  padding: 9px 10px;

  border: 1px solid color-mix(in srgb, #dc2626 16%, var(--border));

  border-radius: 9px;

  background: color-mix(in srgb, #dc2626 5%, var(--surface));

  color: #b91c1c;

  font-size: 9px;

  line-height: 1.45;
}

.delete-warning :deep(svg) {
  flex: 0 0 auto;

  margin-top: 1px;
}

.delete-modal-footer {
  display: flex;

  justify-content: flex-end;

  gap: 8px;

  padding: 12px 18px 17px;

  border-top: 1px solid var(--border);

  background: color-mix(in srgb, var(--surface-muted) 30%, var(--surface));
}

.delete-cancel-btn,
.delete-confirm-btn {
  min-height: 36px;

  display: inline-flex;

  align-items: center;

  justify-content: center;

  gap: 7px;

  padding: 0 13px;

  border-radius: 8px;

  font-family: inherit;

  font-size: 10px;

  font-weight: 700;

  cursor: pointer;

  transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease,
    border-color 0.15s ease;
}

.delete-cancel-btn {
  border: 1px solid var(--border);

  background: var(--surface);

  color: var(--text-secondary);
}

.delete-cancel-btn:hover:not(:disabled) {
  border-color: var(--border-strong);

  background: var(--surface-muted);

  color: var(--text-primary);

  transform: translateY(-1px);
}

.delete-confirm-btn {
  border: 1px solid color-mix(in srgb, #dc2626 85%, transparent);

  background: linear-gradient(135deg, #dc2626, #b91c1c);

  color: #fff;

  box-shadow: 0 5px 15px rgba(220, 38, 38, 0.18);
}

.delete-confirm-btn:hover:not(:disabled) {
  transform: translateY(-1px);

  box-shadow: 0 7px 20px rgba(220, 38, 38, 0.24);
}

.delete-cancel-btn:disabled,
.delete-confirm-btn:disabled {
  opacity: 0.55;

  cursor: not-allowed;

  transform: none;
}

.delete-button-spinner {
  width: 13px;

  height: 13px;

  border: 2px solid rgba(255, 255, 255, 0.35);

  border-top-color: #fff;

  border-radius: 50%;

  animation: delete-spin 0.7s linear infinite;
}

@keyframes delete-modal-backdrop-in {
  from {
    opacity: 0;
  }

  to {
    opacity: 1;
  }
}

@keyframes delete-modal-in {
  from {
    opacity: 0;

    transform: translateY(8px) scale(0.985);
  }

  to {
    opacity: 1;

    transform: translateY(0) scale(1);
  }
}

@keyframes delete-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 520px) {
  .delete-modal-backdrop {
    align-items: flex-end;

    padding: 10px;
  }

  .delete-modal {
    border-radius: 16px;
  }

  .delete-product-meta {
    grid-template-columns: 1fr;
  }

  .delete-modal-footer {
    display: grid;

    grid-template-columns: 1fr 1fr;
  }

  .delete-cancel-btn,
  .delete-confirm-btn {
    width: 100%;
  }
}
</style>
