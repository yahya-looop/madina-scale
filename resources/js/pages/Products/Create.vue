<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { Head, router } from '@inertiajs/vue3';

import ErpLayout from '@/layouts/ErpLayout.vue';

import Icon from '@/components/dashboard/Icon.vue';

interface Props {
    userName?: string;

    userEmail?: string;
}

interface VariantDraft {
    name: string;
    sku: string;
    barcode: string;
    cost_price: string;
    selling_price: string;
    wholesale_price: string;
    promotional_price: string;
    is_default: boolean;
}

function emptyVariantDraft(index = 0): VariantDraft {
    return {
        name: index === 0 ? 'Standard' : `Variant ${index + 1}`,
        sku: '',
        barcode: '',
        cost_price: '0.00',
        selling_price: '',
        wholesale_price: '',
        promotional_price: '',
        is_default: index === 0,
    };
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
    userName: '',

    userEmail: '',
});

const categories = ref<Option[]>([]);

const brands = ref<Option[]>([]);

const units = ref<Option[]>([]);

const isLoadingOptions = ref(true);

const isSaving = ref(false);

const errors = ref<Record<string, string>>({});

const toast = ref({
    visible: false,

    type: 'success' as 'success' | 'error',

    message: '',
});

let toastTimer: ReturnType<typeof setTimeout> | null = null;

const form = ref({
    category_id: '',

    brand_id: '',

    unit_id: '',

    name: '',

    slug: '',

    sku: '',

    model_number: '',

    barcode: '',

    product_type: 'simple',

    short_description: '',

    description: '',

    status: 'draft',

    visibility: 'visible',

    sort_order: 0,

    cost_price: '0.00',

    selling_price: '0.00',

    wholesale_price: '',

    promotional_price: '',
});

const variantDrafts = ref<VariantDraft[]>([emptyVariantDraft()]);

function addVariantDraft(): void {
    if (variantDrafts.value.length >= 100) {
        showToast('You can add up to 100 variants at a time.', 'error');
        return;
    }
    variantDrafts.value.push({
        ...emptyVariantDraft(variantDrafts.value.length),
        is_default: false,
    });
}

function setDefaultVariant(index: number): void {
    variantDrafts.value.forEach((variant, variantIndex) => {
        variant.is_default = variantIndex === index;
    });
}

function removeVariantDraft(index: number): void {
    if (variantDrafts.value.length <= 1) {
        showToast(
            'A variable product must have at least one variant.',
            'error',
        );
        return;
    }
    const removedWasDefault = variantDrafts.value[index]?.is_default ?? false;
    variantDrafts.value.splice(index, 1);
    if (removedWasDefault && variantDrafts.value.length > 0) {
        variantDrafts.value.forEach((variant, i) => {
            variant.is_default = i === 0;
        });
    }
    variantDrafts.value.forEach((variant, i) => {
        if (!variant.name.trim() || /^Variant \d+$/.test(variant.name)) {
            variant.name = i === 0 ? 'Standard' : `Variant ${i + 1}`;
        }
    });
}

const selectedImage = ref<File | null>(null);

const imagePreview = ref('');

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
        (form.value.product_type !== 'simple' || form.value.sku.trim()) &&
        (form.value.product_type !== 'variable' ||
            (variantDrafts.value.length > 0 &&
                variantDrafts.value.every(
                    (variant) =>
                        variant.name.trim() && variant.selling_price !== '',
                ))),
    );
});

const selectedCategoryName = computed(() => {
    return (
        categories.value.find(
            (item) => String(item.id) === form.value.category_id,
        )?.name ?? 'Not selected'
    );
});

const selectedBrandName = computed(() => {
    return (
        brands.value.find((item) => String(item.id) === form.value.brand_id)
            ?.name ?? 'No brand'
    );
});

const selectedUnitName = computed(() => {
    const unit = units.value.find(
        (item) => String(item.id) === form.value.unit_id,
    );

    return unit
        ? `${unit.name}${unit.short_name ? ` (${unit.short_name})` : ''}`
        : 'Not selected';
});

const productTypeLabel = computed(() => {
    return form.value.product_type === 'variable'
        ? 'Variable Product'
        : 'Simple Product';
});

const statusLabel = computed(() => {
    const labels: Record<string, string> = {
        draft: 'Draft',

        active: 'Active',

        inactive: 'Inactive',

        archived: 'Archived',
    };

    return labels[form.value.status] ?? form.value.status;
});

function showToast(message: string, type: 'success' | 'error' = 'success') {
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
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function loadOptions() {
    isLoadingOptions.value = true;

    try {
        const [categoryResponse, brandResponse, unitResponse] =
            await Promise.all([
                fetch('/api/product-categories'),

                fetch('/api/brands'),

                fetch('/api/units'),
            ]);

        if (!categoryResponse.ok) {
            throw new Error('Unable to load product categories.');
        }

        if (!brandResponse.ok) {
            throw new Error('Unable to load brands.');
        }

        if (!unitResponse.ok) {
            throw new Error('Unable to load units.');
        }

        const categoryPayload = (await categoryResponse.json()) as ApiPayload<
            Option[]
        >;

        const brandPayload = (await brandResponse.json()) as ApiPayload<
            Option[]
        >;

        const unitPayload = (await unitResponse.json()) as ApiPayload<Option[]>;

        categories.value = categoryPayload.data ?? [];

        brands.value = brandPayload.data ?? [];

        units.value = unitPayload.data ?? [];
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : 'Unable to load product options.',

            'error',
        );
    } finally {
        isLoadingOptions.value = false;
    }
}

function slugify(value: string): string {
    return value

        .toLowerCase()

        .trim()

        .replace(/[^a-z0-9\s-]/g, '')

        .replace(/\s+/g, '-')

        .replace(/-+/g, '-')

        .replace(/^-|-$/g, '');
}

watch(
    () => form.value.name,

    (value) => {
        if (!slugManuallyEdited.value) {
            form.value.slug = slugify(value);
        }
    },
);

function onSlugInput() {
    slugManuallyEdited.value = true;

    form.value.slug = slugify(form.value.slug);

    clearError('slug');
}

function generateSlug() {
    form.value.slug = slugify(form.value.name);

    slugManuallyEdited.value = false;

    clearError('slug');
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
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    if (!allowedTypes.includes(file.type)) {
        showToast('Please select a JPG, PNG, or WebP image.', 'error');

        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        showToast('Image size must be 5 MB or smaller.', 'error');

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

    imagePreview.value = '';

    if (imageInput.value) {
        imageInput.value.value = '';
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
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!allowedTypes.includes(file.type) || file.size > 5 * 1024 * 1024) {
            showToast(
                `${file.name}: use JPG, PNG or WebP up to 5 MB.`,
                'error',
            );

            continue;
        }

        galleryImages.value.push({ file, preview: URL.createObjectURL(file) });
    }

    if (files.length > available) {
        showToast('A product can have up to 10 images in total.', 'error');
    }

    input.value = '';
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
        errors.value.category_id = 'Please select a category.';
    }

    if (!form.value.unit_id) {
        errors.value.unit_id = 'Please select a unit.';
    }

    if (!form.value.name.trim()) {
        errors.value.name = 'Product name is required.';
    }

    if (form.value.product_type === 'simple' && !form.value.sku.trim()) {
        errors.value.sku = 'SKU is required for a simple product.';
    }

    if (form.value.product_type === 'variable') {
        if (variantDrafts.value.length === 0) {
            errors.value.variants = 'Add at least one variant.';
        }
        variantDrafts.value.forEach((variant, index) => {
            if (!variant.name.trim())
                errors.value[`variants.${index}.name`] =
                    'Variant name is required.';
            if (
                variant.selling_price === '' ||
                Number(variant.selling_price) < 0
            ) {
                errors.value[`variants.${index}.selling_price`] =
                    'Enter a valid selling price.';
            }
        });
    }

    if (Object.keys(errors.value).length > 0) {
        showToast('Please complete the required fields.', 'error');

        return;
    }

    isSaving.value = true;

    try {
        const payload = new FormData();

        payload.append('category_id', form.value.category_id);

        if (form.value.brand_id) {
            payload.append('brand_id', form.value.brand_id);
        }

        payload.append('unit_id', form.value.unit_id);

        payload.append('name', form.value.name.trim());

        if (form.value.slug) {
            payload.append('slug', form.value.slug);
        }

        payload.append('product_type', form.value.product_type);

        // Product-level identifiers belong only to simple products.
        // Variable products send identifiers with each variant below.
        if (form.value.product_type === 'simple') {
            payload.append('sku', form.value.sku.trim());

            if (form.value.model_number.trim()) {
                payload.append('model_number', form.value.model_number.trim());
            }

            if (form.value.barcode.trim()) {
                payload.append('barcode', form.value.barcode.trim());
            }
        }

        if (form.value.short_description.trim()) {
            payload.append(
                'short_description',
                form.value.short_description.trim(),
            );
        }

        if (form.value.description.trim()) {
            payload.append('description', form.value.description.trim());
        }

        payload.append('status', form.value.status);

        payload.append('visibility', form.value.visibility);

        payload.append('sort_order', String(form.value.sort_order));

        if (form.value.product_type === 'simple') {
            payload.append('cost_price', form.value.cost_price || '0');
            payload.append('selling_price', form.value.selling_price || '0');
            if (form.value.wholesale_price !== '')
                payload.append('wholesale_price', form.value.wholesale_price);
            if (form.value.promotional_price !== '')
                payload.append(
                    'promotional_price',
                    form.value.promotional_price,
                );
        } else {
            variantDrafts.value.forEach((variant, index) => {
                payload.append(`variants[${index}][name]`, variant.name.trim());
                if (variant.sku.trim())
                    payload.append(
                        `variants[${index}][sku]`,
                        variant.sku.trim(),
                    );
                if (variant.barcode.trim())
                    payload.append(
                        `variants[${index}][barcode]`,
                        variant.barcode.trim(),
                    );
                payload.append(
                    `variants[${index}][cost_price]`,
                    variant.cost_price || '0',
                );
                payload.append(
                    `variants[${index}][selling_price]`,
                    variant.selling_price || '0',
                );
                if (variant.wholesale_price !== '')
                    payload.append(
                        `variants[${index}][wholesale_price]`,
                        variant.wholesale_price,
                    );
                if (variant.promotional_price !== '')
                    payload.append(
                        `variants[${index}][promotional_price]`,
                        variant.promotional_price,
                    );
                payload.append(
                    `variants[${index}][is_default]`,
                    variant.is_default ? '1' : '0',
                );
                payload.append(`variants[${index}][status]`, 'active');
                payload.append(`variants[${index}][sort_order]`, String(index));
            });
        }

        if (selectedImage.value) {
            payload.append('image', selectedImage.value);
        }

        galleryImages.value.forEach(({ file }) =>
            payload.append('images[]', file),
        );

        const response = await fetch('/api/products', {
            method: 'POST',

            headers: {
                Accept: 'application/json',

                'X-CSRF-TOKEN': getCsrfToken(),

                'X-Requested-With': 'XMLHttpRequest',
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
                firstError ??
                    responsePayload.message ??
                    'Unable to create product.',
            );
        }

        showToast(
            responsePayload.message ?? 'Product created successfully.',
            'success',
        );

        setTimeout(() => {
            router.visit('/products');
        }, 900);
    } catch (error) {
        showToast(
            error instanceof Error
                ? error.message
                : 'Unable to create product.',

            'error',
        );
    } finally {
        isSaving.value = false;
    }
}

function cancel() {
    router.visit('/products');
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
                        <button
                            type="button"
                            class="breadcrumb-back"
                            @click="cancel"
                        >
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
                                Build a structured product record for your
                                catalog, pricing, variants and inventory.
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
                        <Icon
                            name="loader-2"
                            v-if="isSaving"
                            class="spin"
                            :size="16"
                        />

                        <Icon name="device-floppy" v-else :size="16" />

                        {{ isSaving ? 'Creating...' : 'Create Product' }}
                    </button>
                </div>
            </header>

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

                                <p>
                                    Add a professional primary image for your
                                    product catalog.
                                </p>
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

                                <button
                                    type="button"
                                    class="browse-link"
                                    @click.stop="selectImage"
                                >
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
                            <img
                                :src="imagePreview"
                                alt="Product preview"
                                class="image-preview"
                            />

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
                                            selectedImage
                                                ? (
                                                      selectedImage.size /
                                                      1024 /
                                                      1024
                                                  ).toFixed(2)
                                                : '0'
                                        }}

                                        MB
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    class="preview-action"
                                    @click="selectImage"
                                >
                                    Replace image
                                </button>
                            </div>
                        </div>

                        <div class="gallery-upload-panel">
                            <div class="gallery-upload-heading">
                                <div>
                                    <strong>Additional gallery images</strong>
                                    <p>
                                        Upload up to 9 extra views. The primary
                                        image stays first.
                                    </p>
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
                                type="file"
                                class="hidden-input"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                @change="onGallerySelected"
                            />

                            <div
                                v-if="galleryImages.length"
                                class="gallery-preview-grid"
                            >
                                <div
                                    v-for="(item, index) in galleryImages"
                                    :key="`${item.file.name}-${item.file.lastModified}`"
                                    class="gallery-preview-item"
                                >
                                    <img
                                        :src="item.preview"
                                        :alt="item.file.name"
                                    />

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
                    </section>

                    <!-- Basic Information -->

                    <section class="card basic-information-card">
                        <div class="section-heading">
                            <div class="section-icon">
                                <Icon name="stars" :size="18" />
                            </div>

                            <div>
                                <h2>Basic Information</h2>

                                <p>
                                    Define the product's core catalog identity.
                                </p>
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

                                    <button
                                        type="button"
                                        class="generate-button"
                                        @click="generateSlug"
                                    >
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
                                        <option value="">
                                            Select category
                                        </option>

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

                                <small
                                    v-if="errors.category_id"
                                    class="field-error"
                                >
                                    <Icon name="alert-circle" :size="12" />

                                    {{ errors.category_id }}
                                </small>
                            </div>

                            <div class="field">
                                <label>Brand</label>

                                <div class="select-wrap">
                                    <select
                                        v-model="form.brand_id"
                                        :disabled="isLoadingOptions"
                                    >
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

                                <small class="field-hint">
                                    Optional product brand.
                                </small>
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

                                        <option
                                            v-for="unit in units"
                                            :key="unit.id"
                                            :value="String(unit.id)"
                                        >
                                            {{ unit.name }}

                                            {{
                                                unit.short_name
                                                    ? `(${unit.short_name})`
                                                    : ''
                                            }}
                                        </option>
                                    </select>

                                    <Icon name="chevron-down" :size="15" />
                                </div>

                                <small
                                    v-if="errors.unit_id"
                                    class="field-error"
                                >
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
                                            selected:
                                                form.product_type === 'simple',
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

                                        <Icon
                                            name="circle-check"
                                            v-if="
                                                form.product_type === 'simple'
                                            "
                                            :size="15"
                                        />
                                    </button>

                                    <button
                                        type="button"
                                        :class="{
                                            selected:
                                                form.product_type ===
                                                'variable',
                                        }"
                                        @click="
                                            form.product_type = 'variable';

                                            clearError('product_type');
                                            clearError('sku');
                                            clearError('model_number');
                                            clearError('barcode');
                                        "
                                    >
                                        <Icon name="stack-2" :size="16" />

                                        <span>
                                            <strong>Variable</strong>

                                            <small> Supports variants </small>
                                        </span>

                                        <Icon
                                            name="circle-check"
                                            v-if="
                                                form.product_type === 'variable'
                                            "
                                            :size="15"
                                        />
                                    </button>
                                </div>

                                <small class="field-hint">
                                    Simple products use one product identity.
                                    Variable products keep SKU, barcode and
                                    pricing at the variant level.
                                </small>
                            </div>
                        </div>
                    </section>

                    <Transition name="pricing-reveal" mode="out-in">
                        <section
                            class="card pricing-card"
                            :key="form.product_type"
                        >
                            <div class="section-heading">
                                <div class="section-icon">
                                    <Icon name="coins" :size="18" />
                                </div>
                                <div>
                                    <h2>
                                        {{
                                            form.product_type === 'simple'
                                                ? 'Product Pricing'
                                                : 'Variant Pricing & Setup'
                                        }}
                                    </h2>
                                    <p>
                                        {{
                                            form.product_type === 'simple'
                                                ? 'Set the cost and selling prices for this single-SKU product.'
                                                : 'Add every sellable variant now. Each variant can have its own identifiers and prices.'
                                        }}
                                    </p>
                                </div>
                                <span class="section-badge">BDT</span>
                            </div>

                            <div
                                v-if="form.product_type === 'simple'"
                                class="form-grid pricing-grid"
                            >
                                <div class="field">
                                    <label>Cost price</label>
                                    <div class="price-input">
                                        <span>৳</span
                                        ><input
                                            v-model="form.cost_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                        />
                                    </div>
                                    <small class="field-hint"
                                        >Your purchase / acquisition
                                        cost.</small
                                    >
                                </div>
                                <div class="field">
                                    <label>Selling price <span>*</span></label>
                                    <div class="price-input">
                                        <span>৳</span
                                        ><input
                                            v-model="form.selling_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            required
                                            placeholder="0.00"
                                        />
                                    </div>
                                    <small class="field-hint"
                                        >The standard customer price.</small
                                    >
                                </div>
                                <div class="field">
                                    <label
                                        >Wholesale price
                                        <em>Optional</em></label
                                    >
                                    <div class="price-input">
                                        <span>৳</span
                                        ><input
                                            v-model="form.wholesale_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="Not set"
                                        />
                                    </div>
                                </div>
                                <div class="field">
                                    <label
                                        >Promotional price
                                        <em>Optional</em></label
                                    >
                                    <div class="price-input">
                                        <span>৳</span
                                        ><input
                                            v-model="form.promotional_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="Not set"
                                        />
                                    </div>
                                    <small class="field-hint"
                                        >A lower price for an active
                                        promotion.</small
                                    >
                                </div>
                            </div>

                            <div v-else class="variant-builder">
                                <div class="variant-builder-intro">
                                    <div>
                                        <strong
                                            >{{ variantDrafts.length }}
                                            {{
                                                variantDrafts.length === 1
                                                    ? 'variant'
                                                    : 'variants'
                                            }}</strong
                                        ><span
                                            >Each row becomes a real product
                                            variant after saving.</span
                                        >
                                    </div>
                                    <button
                                        type="button"
                                        class="add-variant-button"
                                        @click="addVariantDraft"
                                    >
                                        <Icon name="plus" :size="15" /> Add
                                        variant
                                    </button>
                                </div>
                                <p
                                    v-if="errors.variants"
                                    class="field-error variant-general-error"
                                >
                                    {{ errors.variants }}
                                </p>
                                <div
                                    v-for="(variant, index) in variantDrafts"
                                    :key="index"
                                    class="variant-draft-card"
                                >
                                    <div class="variant-draft-heading">
                                        <div class="variant-number">
                                            {{
                                                String(index + 1).padStart(
                                                    2,
                                                    '0',
                                                )
                                            }}
                                        </div>
                                        <div class="variant-draft-title">
                                            <strong>{{
                                                variant.name ||
                                                `Variant ${index + 1}`
                                            }}</strong
                                            ><span>{{
                                                variant.is_default
                                                    ? 'Default variant'
                                                    : 'Additional variant'
                                            }}</span>
                                        </div>
                                        <button
                                            type="button"
                                            class="default-variant-badge"
                                            :class="{
                                                'is-default':
                                                    variant.is_default,
                                            }"
                                            :aria-pressed="variant.is_default"
                                            @click="setDefaultVariant(index)"
                                        >
                                            {{
                                                variant.is_default
                                                    ? '✓ Default'
                                                    : 'Set as default'
                                            }}
                                        </button>
                                        <button
                                            v-if="variantDrafts.length > 1"
                                            type="button"
                                            class="remove-variant-button"
                                            :aria-label="`Remove variant ${index + 1}`"
                                            @click="removeVariantDraft(index)"
                                        >
                                            <Icon name="trash" :size="14" />
                                        </button>
                                    </div>
                                    <div class="form-grid variant-fields-grid">
                                        <div class="field">
                                            <label
                                                >Variant name
                                                <span>*</span></label
                                            ><input
                                                v-model="variant.name"
                                                maxlength="200"
                                                placeholder="e.g. 10 kg / Black / Standard"
                                            /><small
                                                v-if="
                                                    errors[
                                                        `variants.${index}.name`
                                                    ]
                                                "
                                                class="field-error"
                                                >{{
                                                    errors[
                                                        `variants.${index}.name`
                                                    ]
                                                }}</small
                                            >
                                        </div>
                                        <div class="field">
                                            <label
                                                >Variant SKU
                                                <em>Optional</em></label
                                            ><input
                                                v-model="variant.sku"
                                                maxlength="100"
                                                placeholder="e.g. SCALE-10KG"
                                            />
                                        </div>
                                        <div class="field">
                                            <label
                                                >Barcode
                                                <em>Optional</em></label
                                            ><input
                                                v-model="variant.barcode"
                                                maxlength="100"
                                                placeholder="Barcode / EAN"
                                            />
                                        </div>
                                        <div class="field">
                                            <label>Cost price</label>
                                            <div class="price-input">
                                                <span>৳</span
                                                ><input
                                                    v-model="variant.cost_price"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="0.00"
                                                />
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label
                                                >Selling price
                                                <span>*</span></label
                                            >
                                            <div class="price-input">
                                                <span>৳</span
                                                ><input
                                                    v-model="
                                                        variant.selling_price
                                                    "
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="0.00"
                                                />
                                            </div>
                                            <small
                                                v-if="
                                                    errors[
                                                        `variants.${index}.selling_price`
                                                    ]
                                                "
                                                class="field-error"
                                                >{{
                                                    errors[
                                                        `variants.${index}.selling_price`
                                                    ]
                                                }}</small
                                            >
                                        </div>
                                        <div class="field">
                                            <label
                                                >Wholesale price
                                                <em>Optional</em></label
                                            >
                                            <div class="price-input">
                                                <span>৳</span
                                                ><input
                                                    v-model="
                                                        variant.wholesale_price
                                                    "
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="Not set"
                                                />
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label
                                                >Promotional price
                                                <em>Optional</em></label
                                            >
                                            <div class="price-input">
                                                <span>৳</span
                                                ><input
                                                    v-model="
                                                        variant.promotional_price
                                                    "
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="Not set"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="price-summary">
                                    <Icon name="info-circle" :size="16" /><span
                                        >The first variant is the default. You
                                        can manage attributes, promotion dates,
                                        and variant status after creation.
                                        Inventory quantities remain managed in
                                        Inventory.</span
                                    >
                                </div>
                            </div>

                            <div
                                v-if="form.product_type === 'simple'"
                                class="price-summary"
                            >
                                <Icon name="info-circle" :size="16" /><span
                                    >These prices will be saved to the product's
                                    single price record.</span
                                >
                            </div>
                        </section>
                    </Transition>

                    <!-- Product Identity: only applicable to simple products -->

                    <section
                        v-if="form.product_type === 'simple'"
                        class="card identity-card"
                    >
                        <div class="section-heading">
                            <div class="section-icon">
                                <Icon name="tag" :size="18" />
                            </div>

                            <div>
                                <h2>Product Identity</h2>

                                <p>
                                    Internal identifiers used across operations
                                    and inventory.
                                </p>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="field">
                                <label>
                                    SKU

                                    <span v-if="form.product_type === 'simple'">
                                        *
                                    </span>
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

                                <small v-else class="field-hint">
                                    Required for simple products.
                                </small>
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

                                <small
                                    v-if="errors.model_number"
                                    class="field-error"
                                >
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

                                <small
                                    v-if="errors.barcode"
                                    class="field-error"
                                >
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

                    <section class="card description-card">
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
                                    Ideal for product cards, search results and
                                    quick previews.
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
                                    Detailed information for customers and
                                    internal teams.
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
                                <img
                                    v-if="imagePreview"
                                    :src="imagePreview"
                                    alt=""
                                />

                                <Icon name="package" v-else :size="28" />
                            </div>

                            <div class="summary-content">
                                <strong>
                                    {{ form.name.trim() || 'New Product' }}
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

                    <div class="publishing-stack">
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

                                            <option value="active">
                                                Active
                                            </option>

                                            <option value="inactive">
                                                Inactive
                                            </option>

                                            <option value="archived">
                                                Archived
                                            </option>
                                        </select>

                                        <Icon name="chevron-down" :size="15" />
                                    </div>
                                </div>

                                <div class="field">
                                    <label>Visibility</label>

                                    <div class="select-wrap">
                                        <select v-model="form.visibility">
                                            <option value="visible">
                                                Visible
                                            </option>

                                            <option value="hidden">
                                                Hidden
                                            </option>
                                        </select>

                                        <Icon name="chevron-down" :size="15" />
                                    </div>
                                </div>

                                <div class="field">
                                    <label>Sort Order</label>

                                    <input
                                        v-model.number="form.sort_order"
                                        type="number"
                                        min="0"
                                        step="1"
                                    />

                                    <small class="field-hint">
                                        Lower values appear first.
                                    </small>
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
                                    Pricing, variants, inventory and product
                                    details stay organized in one workflow.
                                </p>
                            </div>
                        </section>
                    </div>
                </aside>
            </div>

            <!-- Bottom action bar: accessible at the end of the long form. The header action remains available at the top. -->
            <div class="bottom-action-wrap">
                <div class="bottom-action-bar">
                    <div class="bottom-action-copy">
                        <span
                            :class="['ready-dot', { ready: isFormReady }]"
                        ></span>
                        <div>
                            <strong>{{
                                isFormReady
                                    ? 'Ready to create'
                                    : 'Complete required fields'
                            }}</strong>
                            <span>{{
                                isFormReady
                                    ? 'Review the details, then save this product.'
                                    : 'Fill in the required fields to continue.'
                            }}</span>
                        </div>
                    </div>
                    <div class="bottom-action-buttons">
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
                            class="button button-primary bottom-create-button"
                            :disabled="isSaving || !isFormReady"
                            @click="createProduct"
                        >
                            <Icon
                                name="loader-2"
                                v-if="isSaving"
                                class="spin"
                                :size="16"
                            />
                            <Icon name="device-floppy" v-else :size="16" />
                            {{
                                isSaving
                                    ? 'Creating Product...'
                                    : 'Create Product'
                            }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Toast -->

            <Transition name="toast">
                <div
                    v-if="toast.visible"
                    class="toast"
                    :class="`toast-${toast.type}`"
                >
                    <Icon
                        name="circle-check"
                        v-if="toast.type === 'success'"
                        :size="18"
                    />

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

    transition:
        border-color 0.2s ease,
        background 0.2s ease,
        color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
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

/* Two-column create layout with a sticky right-hand summary sidebar. */
.content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(285px, 320px);
    align-items: start;
    column-gap: 22px;
    row-gap: 18px;
    width: 100%;
    max-width: none;
    margin: 0 auto;
}

/* The left form scrolls naturally with the page. */
.main-column {
    display: flex;
    flex-direction: column;
    gap: 18px;
    min-width: 0;
}

/* Desktop sidebar: starts directly below the application top bar */
.side-column {
    position: sticky;
    top: 76px;
    align-self: start;
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-width: 0;
    height: fit-content;
    max-height: none;
    overflow: visible;
    margin-top: 0;
    padding: 0;
    z-index: 5;
}

.preview-card {
    min-width: 0;
    padding: 15px;
}

.side-column .section-heading.compact {
    align-items: center;
    gap: 9px;
    margin-bottom: 11px;
}

.side-column .section-icon {
    width: 31px;
    height: 31px;
    border-radius: 9px;
}

.side-column .section-heading h2 {
    font-size: 13px;
}
.side-column .section-heading p {
    font-size: 10px;
    line-height: 1.35;
}
.side-column .summary-preview {
    gap: 9px;
    padding-bottom: 10px;
}
.side-column .summary-image {
    width: 44px;
    height: 44px;
}
.side-column .summary-list > div {
    padding: 8px 0;
}

.side-column .publishing-stack {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-width: 0;
}

.side-column .publishing-stack > .card {
    padding: 15px;
}
.side-column .publishing-stack .field,
.side-column .publishing-stack .form-field {
    margin-bottom: 10px;
}

.side-column .publishing-stack input,
.side-column .publishing-stack select,
.side-column .publishing-stack textarea {
    min-height: 38px;
    padding-top: 8px;
    padding-bottom: 8px;
}

.side-column .info-card {
    gap: 9px;
    padding: 11px;
}
.side-column .info-icon {
    width: 27px;
    height: 27px;
}

.card {
    padding: 23px;

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

    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease,
        background 0.18s ease;
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

    transition:
        border-color 0.18s ease,
        box-shadow 0.18s ease;
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

    transition:
        border-color 0.18s ease,
        background 0.18s ease,
        box-shadow 0.18s ease;
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

.upload-zone {
    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    min-height: 245px;

    padding: 30px;

    border: 1.5px dashed #cbd5e1;

    border-radius: 12px;

    background:
        radial-gradient(circle at top, rgb(37 99 235 / 4%), transparent 42%),
        #fafbfc;

    text-align: center;

    cursor: pointer;

    transition:
        border-color 0.2s ease,
        background 0.2s ease,
        transform 0.2s ease;
}

.upload-zone:hover {
    border-color: var(--primary-color);

    background:
        radial-gradient(circle at top, rgb(37 99 235 / 7%), transparent 45%),
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

/* Bottom action bar */
.bottom-action-wrap {
    position: sticky;
    bottom: 12px;
    z-index: 30;
    margin: 22px 0 8px;
    padding-bottom: env(safe-area-inset-bottom, 0px);
    pointer-events: none;
}
.bottom-action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    width: 100%;
    padding: 13px 16px;
    border: 1px solid color-mix(in srgb, var(--border-color) 82%, #94a3b8);
    border-radius: 15px;
    background: color-mix(in srgb, var(--surface-color) 92%, transparent);
    box-shadow:
        0 12px 34px rgb(15 23 42 / 12%),
        0 2px 8px rgb(15 23 42 / 5%);
    backdrop-filter: blur(16px);
    pointer-events: auto;
}
.bottom-action-copy {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}
.bottom-action-copy > div {
    display: grid;
    gap: 3px;
    min-width: 0;
}
.bottom-action-copy strong {
    color: var(--text-primary-color);
    font-size: 12px;
    font-weight: 700;
}
.bottom-action-copy span:not(.ready-dot) {
    color: var(--text-secondary-color);
    font-size: 11px;
    line-height: 1.4;
}
.bottom-action-buttons {
    display: flex;
    align-items: center;
    gap: 9px;
    flex: 0 0 auto;
}
.bottom-create-button {
    min-width: 158px;
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
    transition:
        opacity 0.2s ease,
        transform 0.2s ease;
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

/* Pricing section enters smoothly after product type selection. */
.pricing-reveal-enter-active,
.pricing-reveal-leave-active {
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}
.pricing-reveal-enter-from,
.pricing-reveal-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

/* Inline variable-product variant builder */
.variant-builder {
    display: grid;
    gap: 12px;
}
.variant-builder-intro {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.variant-builder-intro > div {
    display: grid;
    gap: 3px;
}
.variant-builder-intro strong {
    color: var(--text-primary, #0f172a);
    font-size: 12px;
}
.variant-builder-intro span {
    color: var(--text-secondary, #64748b);
    font-size: 11px;
}
.add-variant-button,
.remove-variant-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 1px solid var(--border, #dbe3ed);
    border-radius: 8px;
    background: var(--surface, #fff);
    color: var(--primary, #059669);
    cursor: pointer;
    font-size: 11px;
    font-weight: 700;
}
.add-variant-button {
    min-height: 34px;
    padding: 0 11px;
}
.remove-variant-button {
    width: 30px;
    height: 30px;
    color: #dc2626;
}
.variant-draft-card {
    min-width: 0;
    padding: 14px;
    border: 1px solid var(--border, #dbe3ed);
    border-radius: 11px;
    background: var(--surface-muted, #f8fafc);
}
.variant-draft-heading {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 13px;
}
.variant-number {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    flex: 0 0 30px;
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary, #059669) 10%, white);
    color: var(--primary, #059669);
    font-size: 10px;
    font-weight: 800;
}
.variant-draft-title {
    min-width: 0;
    flex: 1;
    display: grid;
    gap: 2px;
}
.variant-draft-title strong {
    overflow: hidden;
    color: var(--text-primary, #0f172a);
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.variant-draft-title span {
    color: var(--text-secondary, #64748b);
    font-size: 10px;
}
.default-variant-badge {
    padding: 5px 8px;
    border: 1px solid var(--border, #dbe3ed);
    cursor: pointer;
    background: var(--surface, #fff);
    color: var(--text-secondary, #64748b);
    border-radius: 999px;
    background: color-mix(in srgb, var(--primary, #059669) 9%, white);
    color: var(--primary, #059669);
    font-size: 9px;
    font-weight: 700;
}
.default-variant-badge.is-default {
    border-color: color-mix(in srgb, var(--primary, #059669) 25%, white);
    background: color-mix(in srgb, var(--primary, #059669) 9%, white);
    color: var(--primary, #059669);
}
.variant-fields-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.variant-general-error {
    margin: 0;
}

/* Responsive */

@media (min-width: 981px) and (max-width: 1180px) {
    .content-grid {
        grid-template-columns: minmax(0, 1fr) minmax(250px, 280px);
        gap: 16px;
    }

    .side-column {
        top: 76px;
    }
}

@media (max-width: 980px) {
    .card {
        padding: 20px;
    }

    .content-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
    }

    .side-column {
        position: static;
        top: auto;
        max-height: none;
        height: auto;
        overflow: visible;
    }

    .bottom-action-bar {
        gap: 12px;
    }

    .bottom-action-copy span:not(.ready-dot) {
        max-width: 240px;
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
    .bottom-action-wrap {
        bottom: 8px;
        margin-top: 16px;
    }
    .bottom-action-bar {
        align-items: stretch;
        flex-direction: column;
        padding: 12px;
        border-radius: 13px;
    }
    .bottom-action-buttons {
        width: 100%;
    }
    .bottom-action-buttons .button {
        flex: 1;
    }
    .bottom-create-button {
        min-width: 0;
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
    .bottom-action-copy span:not(.ready-dot) {
        max-width: none;
    }
    .bottom-action-buttons {
        flex-direction: column-reverse;
    }
    .bottom-action-buttons .button {
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

/* Pricing controls follow the Inventory page's compact, neutral form language. */

.pricing-card .section-heading p {
    margin: 4px 0 0;
    color: var(--text-secondary, #64748b);
    font-size: 12px;
}

.pricing-grid {
    margin-top: 6px;
}

.price-input {
    display: flex;
    align-items: center;
    gap: 9px;
    min-height: 44px;
    padding: 0 12px;
    border: 1px solid var(--border, #dbe2ea);
    border-radius: 10px;
    background: var(--surface, #fff);
}

.price-input > span {
    color: var(--text-secondary, #64748b);
    font-weight: 700;
}

.price-input input {
    width: 100%;
    min-width: 0;
    border: 0 !important;
    outline: 0;
    background: transparent;
    box-shadow: none !important;
}

.price-summary {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-top: 18px;
    padding: 12px 14px;
    border-radius: 10px;
    color: var(--text-secondary, #64748b);
    background: var(--surface-muted, #f8fafc);
    font-size: 12px;
    line-height: 1.5;
}

.price-summary :deep(svg) {
    flex: 0 0 auto;
    margin-top: 1px;
}

@media (max-width: 700px) {
    .pricing-grid {
        grid-template-columns: 1fr;
    }
}

/* Compact sidebar layout: no independent scrollbar */
@media (min-width: 981px) {
    .side-column {
        gap: 10px;
    }

    .side-column .preview-card,
    .side-column .publishing-stack > .card {
        padding: 12px;
    }

    .side-column .section-heading.compact {
        margin-bottom: 9px;
    }

    .side-column .summary-preview {
        padding-bottom: 8px;
    }

    .side-column .summary-list > div {
        padding: 6px 0;
    }

    .side-column .publishing-stack {
        gap: 10px;
    }

    .side-column .publishing-stack .form-stack {
        gap: 10px;
    }

    .side-column .publishing-stack .field,
    .side-column .publishing-stack .form-field {
        margin-bottom: 0;
    }

    .side-column .info-card {
        padding: 10px;
    }
}
</style>
