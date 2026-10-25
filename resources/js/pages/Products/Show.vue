<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import { Head, router } from '@inertiajs/vue3';

import ErpLayout from '@/layouts/ErpLayout.vue';

import Icon from '@/components/dashboard/Icon.vue';

import ProductVariantsPanel from '@/components/products/ProductVariantsPanel.vue';

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

    created_at: string;

    updated_at: string;
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

    product_type: 'simple' | 'variable';

    short_description: string | null;

    description: string | null;

    status: 'draft' | 'active' | 'inactive' | 'archived';

    visibility: 'visible' | 'hidden';

    sort_order: number;

    created_at: string;

    updated_at: string;

    category?: {
        id: number;

        name: string;

        slug: string;
    } | null;

    brand?: {
        id: number;

        name: string;

        slug: string;
    } | null;

    unit?: {
        id: number;

        name: string;

        short_name: string;

        slug: string;
    } | null;

    media?: ProductMedia[];

    primary_media?: ProductMedia | null;
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
        userName: 'Yahya Admin',

        userEmail: '',
    },
);

const product = ref<Product | null>(null);

const isLoading = ref(true);

const isDeleting = ref(false);

const errorMessage = ref('');

const copiedField = ref('');

const activeMediaIndex = ref(0);

const productId = computed(() => {
    const match = window.location.pathname.match(/\/products\/(\d+)/);

    return Number(match?.[1] ?? 0);
});

const mediaItems = computed<ProductMedia[]>(() => {
    if (!product.value) {
        return [];
    }

    const items = Array.isArray(product.value.media)
        ? [...product.value.media]
        : [];

    return items.sort((a, b) => {
        if (a.is_primary !== b.is_primary) {
            return a.is_primary ? -1 : 1;
        }

        if (a.sort_order !== b.sort_order) {
            return a.sort_order - b.sort_order;
        }

        return a.id - b.id;
    });
});

const activeMedia = computed<ProductMedia | null>(() => {
    return mediaItems.value[activeMediaIndex.value] ?? null;
});

const activeImageUrl = computed<string | null>(() => {
    const media = activeMedia.value;

    if (!media?.path) {
        return null;
    }

    if (/^https?:\/\//i.test(media.path)) {
        return media.path;
    }

    return `/storage/${media.path.replace(/^\/+/, '')}`;
});

const productTypeLabel = computed(() => {
    return product.value?.product_type === 'variable' ? 'Variable' : 'Simple';
});

const statusLabel = computed(() => {
    const status = product.value?.status;

    if (!status) {
        return '—';
    }

    return status.charAt(0).toUpperCase() + status.slice(1);
});

const visibilityLabel = computed(() => {
    return product.value?.visibility === 'visible' ? 'Visible' : 'Hidden';
});

const hasDescription = computed(() => {
    return Boolean(
        product.value?.short_description?.trim() ||
        product.value?.description?.trim(),
    );
});

const createdDate = computed(() => {
    return product.value ? formatDateTime(product.value.created_at) : '—';
});

const updatedDate = computed(() => {
    return product.value ? formatDateTime(product.value.updated_at) : '—';
});

function getCsrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function apiRequest<T>(
    url: string,
    method: 'GET' | 'DELETE' = 'GET',
): Promise<T> {
    const response = await fetch(url, {
        method,

        headers: {
            Accept: 'application/json',

            ...(getCsrfToken()
                ? {
                      'X-CSRF-TOKEN': getCsrfToken(),
                  }
                : {}),

            'X-Requested-With': 'XMLHttpRequest',
        },

        credentials: 'same-origin',
    });

    const payload = (await response.json()) as ApiPayload<T>;

    if (!response.ok) {
        throw new Error(payload.message ?? 'Unable to complete the request.');
    }

    return payload.data as T;
}

async function loadProduct(): Promise<void> {
    if (!productId.value) {
        errorMessage.value = 'Invalid product ID.';

        isLoading.value = false;

        return;
    }

    isLoading.value = true;

    errorMessage.value = '';

    try {
        product.value = await apiRequest<Product>(
            `/api/products/${productId.value}`,
        );

        activeMediaIndex.value = 0;
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : 'Unable to load product.';
    } finally {
        isLoading.value = false;
    }
}

function goBack(): void {
    router.visit('/products');
}

function goToEdit(): void {
    if (!product.value) {
        return;
    }

    router.visit(`/products/${product.value.id}/edit`);
}

function nextMedia(): void {
    if (mediaItems.value.length <= 1) {
        return;
    }

    activeMediaIndex.value =
        (activeMediaIndex.value + 1) % mediaItems.value.length;
}

function previousMedia(): void {
    if (mediaItems.value.length <= 1) {
        return;
    }

    activeMediaIndex.value =
        (activeMediaIndex.value - 1 + mediaItems.value.length) %
        mediaItems.value.length;
}

function selectMedia(index: number): void {
    activeMediaIndex.value = index;
}

function formatDateTime(value: string): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',

        month: 'short',

        year: 'numeric',

        hour: '2-digit',

        minute: '2-digit',
    }).format(date);
}

function formatFileSize(size: number | null): string {
    if (!size || size <= 0) {
        return '—';
    }

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / 1024 / 1024).toFixed(2)} MB`;
}

function mediaUrl(path: string): string {
    if (/^https?:\/\//i.test(path)) {
        return path;
    }

    return `/storage/${path.replace(/^\/+/, '')}`;
}

function copyValue(value: string | null, field: string): void {
    if (!value) {
        return;
    }

    navigator.clipboard

        ?.writeText(value)

        .then(() => {
            copiedField.value = field;

            window.setTimeout(() => {
                if (copiedField.value === field) {
                    copiedField.value = '';
                }
            }, 1200);
        })

        .catch(() => {
            copiedField.value = '';
        });
}

async function deleteProduct(): Promise<void> {
    if (!product.value || isDeleting.value) {
        return;
    }

    const confirmed = window.confirm(
        `Delete "${product.value.name}"? This product will be moved to trash.`,
    );

    if (!confirmed) {
        return;
    }

    isDeleting.value = true;

    errorMessage.value = '';

    try {
        await apiRequest(`/api/products/${product.value.id}`, 'DELETE');

        router.visit('/products');
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to delete the product.';

        isDeleting.value = false;
    }
}

onMounted(() => {
    void loadProduct();
});
</script>

<template>
    <Head :title="product ? `${product.name} - Product` : 'Product'" />

    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <div class="product-show-page">
            <!-- =========================================================

           PAGE HEADER

           ========================================================= -->

            <header class="page-header">
                <div class="header-main">
                    <button
                        type="button"
                        class="back-button"
                        aria-label="Back to products"
                        @click="goBack"
                    >
                        <Icon name="arrow-left" :size="18" />
                    </button>

                    <div class="header-copy">
                        <div class="breadcrumb">
                            <button type="button" @click="goBack">
                                Products
                            </button>

                            <span>/</span>

                            <span>Product Details</span>
                        </div>

                        <div v-if="product" class="title-row">
                            <div class="title-icon">
                                <Icon name="products" :size="24" />
                            </div>

                            <div class="title-content">
                                <div class="title-line">
                                    <h1>{{ product.name }}</h1>

                                    <span
                                        class="status-badge"
                                        :class="product.status"
                                    >
                                        <span class="status-dot"></span>

                                        {{ statusLabel }}
                                    </span>
                                </div>

                                <p>
                                    {{
                                        product.short_description ||
                                        'Product catalog record and structured product information.'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div v-else class="loading-heading">
                            <div class="loading-line loading-title"></div>

                            <div class="loading-line loading-subtitle"></div>
                        </div>
                    </div>
                </div>

                <div class="header-actions">
                    <button
                        type="button"
                        class="secondary-button"
                        @click="goBack"
                    >
                        <Icon name="arrow-left" :size="16" />

                        Back
                    </button>

                    <button
                        v-if="product"
                        type="button"
                        class="primary-button"
                        @click="goToEdit"
                    >
                        <Icon name="edit" :size="16" />

                        Edit Product
                    </button>
                </div>
            </header>

            <!-- =========================================================

           ERROR

           ========================================================= -->

            <div v-if="errorMessage" class="error-banner" role="alert">
                <Icon name="info" :size="16" />

                <span>{{ errorMessage }}</span>

                <button
                    type="button"
                    aria-label="Reload product"
                    @click="loadProduct"
                >
                    Try again
                </button>
            </div>

            <!-- =========================================================

           LOADING

           ========================================================= -->

            <section v-if="isLoading" class="loading-card">
                <Icon name="loader-2" class="spin" :size="24" />

                <div>
                    <strong>Loading product...</strong>

                    <span>Preparing product details and media.</span>
                </div>
            </section>

            <!-- =========================================================

           PRODUCT CONTENT

           ========================================================= -->

            <template v-else-if="product">
                <!-- =======================================================

             HERO GRID

             ======================================================= -->

                <div class="hero-grid">
                    <!-- Product media -->

                    <section class="card media-card">
                        <div class="section-heading">
                            <div class="section-icon">
                                <Icon name="package" :size="18" />
                            </div>

                            <div>
                                <h2>Product Media</h2>

                                <p>Visual identity of this product.</p>
                            </div>

                            <span class="section-count">
                                {{ mediaItems.length }}

                                {{
                                    mediaItems.length === 1 ? 'image' : 'images'
                                }}
                            </span>
                        </div>

                        <div class="media-viewer">
                            <template v-if="activeImageUrl">
                                <img
                                    :src="activeImageUrl"
                                    :alt="activeMedia?.alt_text || product.name"
                                    class="main-product-image"
                                />

                                <button
                                    v-if="mediaItems.length > 1"
                                    type="button"
                                    class="media-nav media-nav-left"
                                    aria-label="Previous image"
                                    @click="previousMedia"
                                >
                                    <Icon name="chevron-left" :size="18" />
                                </button>

                                <button
                                    v-if="mediaItems.length > 1"
                                    type="button"
                                    class="media-nav media-nav-right"
                                    aria-label="Next image"
                                    @click="nextMedia"
                                >
                                    <Icon name="chevron-right" :size="18" />
                                </button>
                            </template>

                            <div v-else class="no-media">
                                <div class="no-media-icon">
                                    <Icon name="photo-off" :size="28" />
                                </div>

                                <strong>No product image</strong>

                                <span>
                                    Add product media from the edit page.
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="mediaItems.length > 0"
                            class="media-thumbnails"
                        >
                            <button
                                v-for="(media, index) in mediaItems"
                                :key="media.id"
                                type="button"
                                class="media-thumbnail"
                                :class="{ active: activeMediaIndex === index }"
                                @click="selectMedia(index)"
                            >
                                <img
                                    :src="mediaUrl(media.path)"
                                    :alt="media.alt_text || product.name"
                                    loading="lazy"
                                />

                                <span
                                    v-if="media.is_primary"
                                    class="primary-media-badge"
                                >
                                    Primary
                                </span>
                            </button>
                        </div>

                        <div v-if="activeMedia" class="media-meta">
                            <span>
                                {{
                                    activeMedia.original_name || 'Product image'
                                }}
                            </span>

                            <span>
                                {{ formatFileSize(activeMedia.size) }}
                            </span>

                            <span
                                v-if="activeMedia.width && activeMedia.height"
                            >
                                {{ activeMedia.width }} ×

                                {{ activeMedia.height }}
                            </span>
                        </div>
                    </section>

                    <!-- Product summary -->

                    <aside class="card summary-card">
                        <div class="section-heading">
                            <div class="section-icon">
                                <Icon name="eye" :size="18" />
                            </div>

                            <div>
                                <h2>Product Summary</h2>

                                <p>Live overview of this product.</p>
                            </div>
                        </div>

                        <div class="summary-identity">
                            <div class="summary-product-icon">
                                <Icon name="products" :size="25" />
                            </div>

                            <div>
                                <strong>{{ product.name }}</strong>

                                <span>{{ productTypeLabel }} Product</span>
                            </div>
                        </div>

                        <div class="summary-list">
                            <div class="summary-row">
                                <span>Category</span>

                                <strong>
                                    {{
                                        product.category?.name || 'Not selected'
                                    }}
                                </strong>
                            </div>

                            <div class="summary-row">
                                <span>Brand</span>

                                <strong>
                                    {{ product.brand?.name || 'No brand' }}
                                </strong>
                            </div>

                            <div class="summary-row">
                                <span>Base Unit</span>

                                <strong>
                                    {{ product.unit?.name || 'Not selected' }}
                                </strong>
                            </div>

                            <div class="summary-row">
                                <span>Visibility</span>

                                <strong>
                                    {{ visibilityLabel }}
                                </strong>
                            </div>

                            <div class="summary-row">
                                <span>Status</span>

                                <strong
                                    class="summary-status"
                                    :class="product.status"
                                >
                                    <span class="status-dot"></span>

                                    {{ statusLabel }}
                                </strong>
                            </div>
                        </div>

                        <div class="summary-actions">
                            <button
                                type="button"
                                class="summary-edit-button"
                                @click="goToEdit"
                            >
                                <Icon name="edit" :size="15" />

                                Edit Product
                            </button>
                        </div>
                    </aside>
                </div>

                <!-- =======================================================

             IDENTITY + CLASSIFICATION

             ======================================================= -->

                <div
                    class="details-grid"
                    :class="{
                        'details-grid-variable':
                            product.product_type === 'variable',
                    }"
                >
                    <!-- Identity -->

                    <section
                        v-if="product.product_type === 'simple'"
                        class="card detail-card"
                    >
                        <div class="section-heading">
                            <div class="section-icon">
                                <Icon name="hash" :size="18" />
                            </div>

                            <div>
                                <h2>Product Identity</h2>

                                <p>Core identifiers used across the ERP.</p>
                            </div>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">
                                    <Icon name="hash" :size="13" />

                                    SKU
                                </span>

                                <div class="info-value-row">
                                    <strong>{{ product.sku || '—' }}</strong>

                                    <button
                                        v-if="product.sku"
                                        type="button"
                                        class="copy-button"
                                        title="Copy SKU"
                                        @click="copyValue(product.sku, 'sku')"
                                    >
                                        <Icon
                                            name="check"
                                            v-if="copiedField === 'sku'"
                                            :size="13"
                                        />

                                        <Icon name="copy" v-else :size="13" />
                                    </button>
                                </div>
                            </div>

                            <div class="info-item">
                                <span class="info-label">
                                    <Icon name="package" :size="13" />

                                    Model Number
                                </span>

                                <div class="info-value-row">
                                    <strong>
                                        {{ product.model_number || '—' }}
                                    </strong>

                                    <button
                                        v-if="product.model_number"
                                        type="button"
                                        class="copy-button"
                                        title="Copy model number"
                                        @click="
                                            copyValue(
                                                product.model_number,
                                                'model',
                                            )
                                        "
                                    >
                                        <Icon
                                            name="check"
                                            v-if="copiedField === 'model'"
                                            :size="13"
                                        />

                                        <Icon name="copy" v-else :size="13" />
                                    </button>
                                </div>
                            </div>

                            <div class="info-item">
                                <span class="info-label">
                                    <Icon name="barcode" :size="13" />

                                    Barcode
                                </span>

                                <div class="info-value-row">
                                    <strong>
                                        {{ product.barcode || '—' }}
                                    </strong>

                                    <button
                                        v-if="product.barcode"
                                        type="button"
                                        class="copy-button"
                                        title="Copy barcode"
                                        @click="
                                            copyValue(
                                                product.barcode,
                                                'barcode',
                                            )
                                        "
                                    >
                                        <Icon
                                            name="check"
                                            v-if="copiedField === 'barcode'"
                                            :size="13"
                                        />

                                        <Icon name="copy" v-else :size="13" />
                                    </button>
                                </div>
                            </div>

                            <div class="info-item">
                                <span class="info-label">
                                    <Icon name="tag" :size="13" />

                                    Product Type
                                </span>

                                <strong>
                                    <span
                                        class="type-badge"
                                        :class="product.product_type"
                                    >
                                        {{ productTypeLabel }}
                                    </span>
                                </strong>
                            </div>

                            <div class="info-item info-item-wide">
                                <span class="info-label">
                                    <Icon name="world" :size="13" />

                                    Product Slug
                                </span>

                                <div class="info-value-row">
                                    <strong class="slug-value">
                                        {{ product.slug }}
                                    </strong>

                                    <button
                                        type="button"
                                        class="copy-button"
                                        title="Copy slug"
                                        @click="copyValue(product.slug, 'slug')"
                                    >
                                        <Icon
                                            name="check"
                                            v-if="copiedField === 'slug'"
                                            :size="13"
                                        />

                                        <Icon name="copy" v-else :size="13" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Classification -->

                    <section class="card detail-card">
                        <div class="section-heading">
                            <div class="section-icon">
                                <Icon name="stack-2" :size="18" />
                            </div>

                            <div>
                                <h2>Catalog Classification</h2>

                                <p>Where this product belongs.</p>
                            </div>
                        </div>

                        <div class="classification-list">
                            <div class="classification-item">
                                <div class="classification-icon">
                                    <Icon name="stack-2" :size="15" />
                                </div>

                                <div>
                                    <span>Category</span>

                                    <strong>
                                        {{
                                            product.category?.name ||
                                            'Not selected'
                                        }}
                                    </strong>

                                    <small>
                                        {{
                                            product.category?.slug
                                                ? `/${product.category.slug}`
                                                : 'No category slug'
                                        }}
                                    </small>
                                </div>
                            </div>

                            <div class="classification-item">
                                <div class="classification-icon">
                                    <Icon name="tag" :size="15" />
                                </div>

                                <div>
                                    <span>Brand</span>

                                    <strong>
                                        {{ product.brand?.name || 'No brand' }}
                                    </strong>

                                    <small>
                                        {{
                                            product.brand?.slug
                                                ? product.brand.slug
                                                : 'No brand assigned'
                                        }}
                                    </small>
                                </div>
                            </div>

                            <div class="classification-item">
                                <div class="classification-icon">
                                    <Icon name="ruler" :size="15" />
                                </div>

                                <div>
                                    <span>Base Unit</span>

                                    <strong>
                                        {{
                                            product.unit?.name || 'Not selected'
                                        }}
                                    </strong>

                                    <small>
                                        {{
                                            product.unit?.short_name
                                                ? product.unit.short_name
                                                : 'No short name'
                                        }}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- =======================================================

             DESCRIPTION

             ======================================================= -->

                <section v-if="hasDescription" class="card description-card">
                    <div class="section-heading">
                        <div class="section-icon">
                            <Icon name="file-text" :size="18" />
                        </div>

                        <div>
                            <h2>Product Description</h2>

                            <p>Customer-facing and internal product content.</p>
                        </div>
                    </div>

                    <div class="description-content">
                        <div
                            v-if="product.short_description"
                            class="description-block"
                        >
                            <span class="description-label">
                                Short Description
                            </span>

                            <p class="short-description">
                                {{ product.short_description }}
                            </p>
                        </div>

                        <div
                            v-if="product.description"
                            class="description-block"
                        >
                            <span class="description-label">
                                Full Description
                            </span>

                            <div
                                class="rich-description"
                                v-text="product.description"
                            ></div>
                        </div>
                    </div>
                </section>

                <!-- =======================================================

             VARIANT MANAGEMENT

             Keep this after core product information and before lifecycle.

             ======================================================= -->

                <ProductVariantsPanel
                    :product-id="product.id"
                    :product-type="product.product_type"
                />

                <!-- =======================================================

             LIFECYCLE

             ======================================================= -->

                <section class="card lifecycle-card">
                    <div class="section-heading">
                        <div class="section-icon">
                            <Icon name="clock" :size="18" />
                        </div>

                        <div>
                            <h2>Product Lifecycle</h2>

                            <p>Record and publication state.</p>
                        </div>
                    </div>

                    <div class="lifecycle-grid">
                        <div class="lifecycle-item">
                            <div class="lifecycle-icon">
                                <Icon name="calendar" :size="15" />
                            </div>

                            <div>
                                <span>Created</span>

                                <strong>{{ createdDate }}</strong>
                            </div>
                        </div>

                        <div class="lifecycle-item">
                            <div class="lifecycle-icon">
                                <Icon name="clock" :size="15" />
                            </div>

                            <div>
                                <span>Last Updated</span>

                                <strong>{{ updatedDate }}</strong>
                            </div>
                        </div>

                        <div class="lifecycle-item">
                            <div class="lifecycle-icon">
                                <Icon name="eye" :size="15" />
                            </div>

                            <div>
                                <span>Visibility</span>

                                <strong>{{ visibilityLabel }}</strong>
                            </div>
                        </div>

                        <div class="lifecycle-item">
                            <div class="lifecycle-icon">
                                <Icon name="box" :size="15" />
                            </div>

                            <div>
                                <span>Sort Order</span>

                                <strong>{{ product.sort_order }}</strong>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- =======================================================

             BOTTOM ACTION BAR

             ======================================================= -->

                <div class="bottom-actions">
                    <button
                        type="button"
                        class="danger-button"
                        :disabled="isDeleting"
                        @click="deleteProduct"
                    >
                        <Icon
                            name="loader-2"
                            v-if="isDeleting"
                            class="spin"
                            :size="15"
                        />

                        <Icon name="trash" v-else :size="15" />

                        {{ isDeleting ? 'Deleting...' : 'Move to Trash' }}
                    </button>

                    <div class="bottom-actions-right">
                        <button
                            type="button"
                            class="secondary-button"
                            @click="goBack"
                        >
                            <Icon name="arrow-left" :size="15" />

                            Back to Products
                        </button>

                        <button
                            type="button"
                            class="primary-button"
                            @click="goToEdit"
                        >
                            <Icon name="edit" :size="15" />

                            Edit Product
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </ErpLayout>
</template>

<style scoped>
/* ==========================================================================

   PRODUCT SHOW

   Madina Scale enterprise product detail design

   ========================================================================== */

.product-show-page {
    --product-radius: 14px;

    --product-radius-sm: 9px;

    min-height: 100%;

    padding: 2px 0 18px;

    color: var(--text-primary);
}

/* ==========================================================================

   SHARED

   ========================================================================== */

.page-header,
.title-row,
.header-actions,
.breadcrumb,
.section-heading,
.summary-identity,
.info-value-row,
.classification-item,
.lifecycle-item,
.bottom-actions,
.bottom-actions-right {
    display: flex;

    align-items: center;
}

/* ==========================================================================

   HEADER

   ========================================================================== */

.page-header {
    justify-content: space-between;

    gap: 24px;

    margin-bottom: 20px;
}

.header-main {
    min-width: 0;

    display: flex;

    align-items: flex-start;

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

    transition:
        border-color 0.16s ease,
        background 0.16s ease,
        color 0.16s ease,
        transform 0.16s ease;
}

.back-button:hover {
    border-color: var(--border-strong);

    background: var(--surface-muted);

    color: var(--text-primary);

    transform: translateX(-1px);
}

.header-copy {
    min-width: 0;
}

.breadcrumb {
    gap: 8px;

    margin-bottom: 8px;

    color: var(--text-muted);

    font-size: 11px;

    line-height: 1.2;

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

    flex: 0 0 42px;

    border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));

    border-radius: 12px;

    background: color-mix(in srgb, var(--primary) 10%, var(--surface));

    color: var(--primary);

    box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 7%, transparent);
}

.title-content {
    min-width: 0;
}

.title-line {
    display: flex;

    align-items: center;

    flex-wrap: wrap;

    gap: 9px;
}

.title-line h1 {
    margin: 0;

    color: var(--text-primary);

    font-size: 24px;

    line-height: 1.15;

    letter-spacing: -0.025em;

    font-weight: 750;
}

.title-content > p {
    max-width: 760px;

    margin: 5px 0 0;

    overflow: hidden;

    color: var(--text-secondary);

    font-size: 12px;

    line-height: 1.5;

    text-overflow: ellipsis;

    white-space: nowrap;
}

.header-actions {
    flex-shrink: 0;

    gap: 8px;
}

/* ==========================================================================

   BUTTONS

   ========================================================================== */

.secondary-button,
.primary-button,
.danger-button {
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

    transition:
        border-color 0.16s ease,
        background 0.16s ease,
        color 0.16s ease,
        transform 0.16s ease;
}

.secondary-button {
    border: 1px solid var(--border);

    background: var(--surface);

    color: var(--text-secondary);
}

.secondary-button:hover {
    border-color: var(--border-strong);

    background: var(--surface-muted);

    color: var(--text-primary);
}

.primary-button {
    border: 1px solid color-mix(in srgb, var(--primary) 88%, transparent);

    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--primary) 96%, white),
        color-mix(in srgb, var(--primary) 86%, black)
    );

    color: #fff;

    box-shadow: 0 5px 14px color-mix(in srgb, var(--primary) 15%, transparent);
}

.primary-button:hover {
    transform: translateY(-1px);

    box-shadow: 0 7px 18px color-mix(in srgb, var(--primary) 20%, transparent);
}

.danger-button {
    border: 1px solid rgb(239 68 68 / 18%);

    background: color-mix(in srgb, #ef4444 4%, var(--surface));

    color: #b91c1c;
}

.danger-button:hover:not(:disabled) {
    border-color: rgb(239 68 68 / 28%);

    background: rgb(239 68 68 / 7%);
}

.secondary-button:disabled,
.primary-button:disabled,
.danger-button:disabled {
    opacity: 0.5;

    cursor: not-allowed;
}

/* ==========================================================================

   ERROR

   ========================================================================== */

.error-banner {
    min-height: 42px;

    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 14px;

    padding: 9px 11px;

    border: 1px solid rgb(220 38 38 / 18%);

    border-radius: 9px;

    background: rgb(220 38 38 / 5%);

    color: #b91c1c;

    font-size: 11px;
}

.error-banner span {
    flex: 1;
}

.error-banner button {
    padding: 4px 8px;

    border: 1px solid rgb(220 38 38 / 18%);

    border-radius: 7px;

    background: transparent;

    color: inherit;

    font-size: 10px;

    font-weight: 650;

    cursor: pointer;
}

/* ==========================================================================

   LOADING

   ========================================================================== */

.loading-card {
    min-height: 150px;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 12px;

    border: 1px solid var(--border);

    border-radius: var(--product-radius);

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

.loading-heading {
    padding-top: 2px;
}

.loading-line {
    border-radius: 6px;

    background: var(--surface-muted);
}

.loading-title {
    width: 280px;

    height: 26px;
}

.loading-subtitle {
    width: 410px;

    height: 12px;

    margin-top: 7px;
}

/* ==========================================================================

   CARDS

   ========================================================================== */

.card {
    border: 1px solid var(--border);

    border-radius: var(--product-radius);

    background: var(--surface);

    box-shadow: 0 5px 20px rgba(26, 47, 72, 0.035);
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

    font-weight: 700;
}

.section-heading p {
    margin: 3px 0 0;

    color: var(--text-secondary);

    font-size: 10px;

    line-height: 1.4;
}

.section-count {
    flex-shrink: 0;

    padding: 5px 7px;

    border: 1px solid var(--border);

    border-radius: 7px;

    background: var(--surface-muted);

    color: var(--text-muted);

    font-size: 9px;

    font-weight: 650;
}

/* ==========================================================================

   HERO

   ========================================================================== */

.hero-grid {
    display: grid;

    grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.8fr);

    gap: 14px;

    margin-bottom: 14px;
}

/* ==========================================================================

   MEDIA

   ========================================================================== */

.media-card {
    overflow: hidden;
}

.media-viewer {
    position: relative;

    min-height: 360px;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 18px;

    background:
        radial-gradient(
            circle at 50% 35%,
            color-mix(in srgb, var(--primary) 6%, transparent),
            transparent 48%
        ),
        var(--surface-muted);
}

.main-product-image {
    width: 100%;

    height: 340px;

    display: block;

    object-fit: contain;

    border-radius: 10px;
}

.media-nav {
    position: absolute;

    top: 50%;

    width: 34px;

    height: 34px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border: 1px solid var(--border);

    border-radius: 50%;

    background: color-mix(in srgb, var(--surface) 92%, transparent);

    color: var(--text-secondary);

    box-shadow: 0 5px 16px rgba(26, 47, 72, 0.08);

    cursor: pointer;

    transform: translateY(-50%);
}

.media-nav:hover {
    border-color: color-mix(in srgb, var(--primary) 25%, var(--border));

    color: var(--primary);
}

.media-nav-left {
    left: 12px;
}

.media-nav-right {
    right: 12px;
}

.no-media {
    min-height: 350px;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 6px;

    color: var(--text-muted);

    text-align: center;
}

.no-media-icon {
    width: 56px;

    height: 56px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 4px;

    border: 1px solid var(--border);

    border-radius: 14px;

    background: var(--surface);

    color: var(--text-muted);
}

.no-media strong {
    color: var(--text-primary);

    font-size: 12px;
}

.no-media span {
    color: var(--text-muted);

    font-size: 10px;
}

.media-thumbnails {
    display: flex;

    gap: 7px;

    overflow-x: auto;

    padding: 10px 12px;

    border-top: 1px solid var(--border);
}

.media-thumbnail {
    position: relative;

    width: 58px;

    height: 58px;

    flex: 0 0 58px;

    padding: 2px;

    border: 1px solid var(--border);

    border-radius: 8px;

    background: var(--surface);

    cursor: pointer;

    overflow: hidden;
}

.media-thumbnail.active {
    border-color: color-mix(in srgb, var(--primary) 55%, var(--border));

    box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 9%, transparent);
}

.media-thumbnail img {
    width: 100%;

    height: 100%;

    display: block;

    object-fit: cover;

    border-radius: 6px;
}

.primary-media-badge {
    position: absolute;

    right: 3px;

    bottom: 3px;

    padding: 2px 4px;

    border-radius: 4px;

    background: var(--primary);

    color: #fff;

    font-size: 6px;

    font-weight: 700;
}

.media-meta {
    display: flex;

    align-items: center;

    gap: 8px;

    padding: 9px 13px;

    border-top: 1px solid var(--border);

    color: var(--text-muted);

    font-size: 9px;
}

.media-meta span:first-child {
    min-width: 0;

    flex: 1;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;
}

/* ==========================================================================

   SUMMARY

   ========================================================================== */

.summary-card {
    overflow: hidden;
}

.summary-identity {
    gap: 10px;

    padding: 17px 16px;
}

.summary-product-icon {
    width: 48px;

    height: 48px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    flex: 0 0 48px;

    border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));

    border-radius: 12px;

    background: color-mix(in srgb, var(--primary) 6%, var(--surface));

    color: var(--primary);
}

.summary-identity div:last-child {
    min-width: 0;

    display: flex;

    flex-direction: column;

    gap: 3px;
}

.summary-identity strong {
    color: var(--text-primary);

    font-size: 13px;

    line-height: 1.3;

    font-weight: 700;
}

.summary-identity span {
    color: var(--text-muted);

    font-size: 9px;
}

.summary-list {
    padding: 0 16px;
}

.summary-row {
    min-height: 43px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    border-top: 1px solid var(--border);
}

.summary-row > span {
    color: var(--text-muted);

    font-size: 10px;
}

.summary-row > strong {
    max-width: 58%;

    overflow: hidden;

    color: var(--text-secondary);

    font-size: 10px;

    font-weight: 650;

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

.summary-actions {
    padding: 13px 16px;
}

.summary-edit-button {
    width: 100%;

    min-height: 36px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));

    border-radius: 8px;

    background: color-mix(in srgb, var(--primary) 5%, var(--surface));

    color: var(--primary);

    font-size: 10px;

    font-weight: 700;

    cursor: pointer;
}

.summary-edit-button:hover {
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
}

/* ==========================================================================

   STATUS

   ========================================================================== */

.status-badge {
    min-height: 23px;

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 0 8px;

    border: 1px solid transparent;

    border-radius: 999px;

    font-size: 9px;

    line-height: 1;

    font-weight: 650;
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

    color: var(--text-muted);
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

   DETAILS

   ========================================================================== */

.details-grid {
    display: grid;

    grid-template-columns: minmax(0, 1.15fr) minmax(300px, 0.85fr);

    gap: 14px;

    margin-bottom: 14px;
}

.details-grid-variable {
    grid-template-columns: minmax(0, 1fr);
}

.detail-card {
    overflow: hidden;
}

.info-grid {
    display: grid;

    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 0;
}

.info-item {
    min-width: 0;

    min-height: 75px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    gap: 6px;

    padding: 12px 15px;

    border-bottom: 1px solid var(--border);
}

.info-item:nth-child(odd) {
    border-right: 1px solid var(--border);
}

.info-item:nth-last-child(-n + 2) {
    border-bottom: 0;
}

.info-item-wide {
    grid-column: 1 / -1;

    border-right: 0 !important;

    border-bottom: 0 !important;
}

.info-label {
    display: flex;

    align-items: center;

    gap: 5px;

    color: var(--text-muted);

    font-size: 9px;

    font-weight: 650;
}

.info-value-row {
    min-width: 0;

    gap: 5px;
}

.info-value-row strong {
    min-width: 0;

    overflow: hidden;

    color: var(--text-primary);

    font-size: 11px;

    font-weight: 650;

    text-overflow: ellipsis;

    white-space: nowrap;
}

.slug-value {
    color: var(--text-secondary) !important;

    font-size: 10px !important;
}

.copy-button {
    width: 23px;

    height: 23px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    flex: 0 0 23px;

    border: 1px solid var(--border);

    border-radius: 6px;

    background: var(--surface);

    color: var(--text-muted);

    cursor: pointer;
}

.copy-button:hover {
    border-color: color-mix(in srgb, var(--primary) 25%, var(--border));

    color: var(--primary);
}

.type-badge {
    min-height: 23px;

    display: inline-flex;

    align-items: center;

    padding: 0 8px;

    border-radius: 7px;

    font-size: 9px;

    font-weight: 650;
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

   CLASSIFICATION

   ========================================================================== */

.classification-list {
    padding: 2px 16px 8px;
}

.classification-item {
    gap: 10px;

    min-height: 72px;

    border-bottom: 1px solid var(--border);
}

.classification-item:last-child {
    border-bottom: 0;
}

.classification-icon {
    width: 32px;

    height: 32px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    flex: 0 0 32px;

    border: 1px solid var(--border);

    border-radius: 8px;

    background: var(--surface-muted);

    color: var(--primary);
}

.classification-item > div:last-child {
    min-width: 0;

    display: flex;

    flex-direction: column;

    gap: 2px;
}

.classification-item span {
    color: var(--text-muted);

    font-size: 9px;
}

.classification-item strong {
    color: var(--text-primary);

    font-size: 11px;

    font-weight: 650;
}

.classification-item small {
    color: var(--text-muted);

    font-size: 8.5px;
}

/* ==========================================================================

   DESCRIPTION

   ========================================================================== */

.description-card {
    margin-bottom: 14px;

    overflow: hidden;
}

.description-content {
    padding: 15px 16px;
}

.description-block + .description-block {
    margin-top: 17px;

    padding-top: 16px;

    border-top: 1px solid var(--border);
}

.description-label {
    display: block;

    margin-bottom: 6px;

    color: var(--text-muted);

    font-size: 9px;

    font-weight: 700;

    letter-spacing: 0.3px;

    text-transform: uppercase;
}

.short-description {
    margin: 0;

    color: var(--text-secondary);

    font-size: 11px;

    line-height: 1.65;
}

.rich-description {
    color: var(--text-secondary);

    font-size: 11px;

    line-height: 1.7;

    white-space: pre-wrap;
}

/* ==========================================================================

   LIFECYCLE

   ========================================================================== */

.lifecycle-card {
    margin-bottom: 14px;

    overflow: hidden;
}

.lifecycle-grid {
    display: grid;

    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.lifecycle-item {
    min-width: 0;

    gap: 9px;

    min-height: 76px;

    padding: 13px 15px;

    border-right: 1px solid var(--border);
}

.lifecycle-item:last-child {
    border-right: 0;
}

.lifecycle-icon {
    width: 30px;

    height: 30px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    flex: 0 0 30px;

    border: 1px solid var(--border);

    border-radius: 8px;

    background: var(--surface-muted);

    color: var(--text-muted);
}

.lifecycle-item > div:last-child {
    min-width: 0;

    display: flex;

    flex-direction: column;

    gap: 3px;
}

.lifecycle-item span {
    color: var(--text-muted);

    font-size: 9px;
}

.lifecycle-item strong {
    overflow: hidden;

    color: var(--text-primary);

    font-size: 10px;

    font-weight: 650;

    text-overflow: ellipsis;

    white-space: nowrap;
}

/* ==========================================================================

   BOTTOM ACTIONS

   ========================================================================== */

.bottom-actions {
    justify-content: space-between;

    gap: 12px;

    padding: 13px 0 2px;
}

.bottom-actions-right {
    gap: 8px;
}

/* ==========================================================================

   DARK THEME

   ========================================================================== */

:global(.dark) .card,
:global(html.dark) .card,
:global(body.dark) .card,
:global([data-theme='dark']) .card {
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.14);
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
    .hero-grid,
    .details-grid {
        grid-template-columns: 1fr;
    }

    .summary-card {
        order: -1;
    }

    .lifecycle-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .lifecycle-item:nth-child(2) {
        border-right: 0;
    }

    .lifecycle-item:nth-child(-n + 2) {
        border-bottom: 1px solid var(--border);
    }
}

@media (max-width: 800px) {
    .hero-grid,
    .details-grid {
        gap: 12px;
    }

    .section-heading {
        padding: 13px 14px 12px;
    }

    .media-viewer {
        min-height: 320px;
    }

    .main-product-image {
        height: 300px;
    }
}

@media (max-width: 700px) {
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

    .title-content > p {
        white-space: normal;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .info-item,
    .info-item:nth-child(odd) {
        border-right: 0;

        border-bottom: 1px solid var(--border);
    }

    .info-item:last-child {
        border-bottom: 0;
    }

    .lifecycle-grid {
        grid-template-columns: 1fr;
    }

    .lifecycle-item {
        border-right: 0;

        border-bottom: 1px solid var(--border);
    }

    .lifecycle-item:last-child {
        border-bottom: 0;
    }

    .bottom-actions {
        align-items: stretch;

        flex-direction: column;
    }

    .bottom-actions-right {
        width: 100%;
    }

    .bottom-actions-right button {
        flex: 1;
    }
}

@media (max-width: 520px) {
    .title-line h1 {
        font-size: 21px;
    }

    .media-viewer {
        min-height: 280px;

        padding: 12px;
    }

    .main-product-image {
        height: 250px;
    }

    .no-media {
        min-height: 270px;
    }

    .summary-row {
        min-height: 40px;
    }
}
</style>
