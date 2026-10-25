<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
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

type LocationType =
    | 'head_office'
    | 'branch'
    | 'store'
    | 'warehouse'
    | 'distribution_hub'
    | 'service_center'
    | 'factory'
    | 'other';

type BusinessHoursDay = {
    enabled: boolean;
    open: string | null;
    close: string | null;
};

type BusinessHours = Record<string, BusinessHoursDay>;

type Location = {
    id: number;
    name: string;
    code: string;
    type: LocationType;
    is_active: boolean;
    is_primary: boolean;
    country_code: string;
    division: string | null;
    district: string | null;
    postal_code: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    business_hours: BusinessHours | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
};

type ApiPayload<T> = {
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
};

type ValidationErrors = Record<string, string>;

type LocationForm = {
    name: string;
    code: string;
    type: LocationType;
    is_active: boolean;
    is_primary: boolean;
    country_code: string;
    division: string;
    district: string;
    postal_code: string;
    address: string;
    phone: string;
    email: string;
    business_hours: BusinessHours;
};

type ModalMode = 'create' | 'edit';

const locationTypes: Array<{
    value: LocationType;
    label: string;
    description: string;
    icon: string;
}> = [
    {
        value: 'head_office',
        label: 'Head Office',
        description: 'Primary corporate or administrative headquarters.',
        icon: 'building-complex',
    },
    {
        value: 'branch',
        label: 'Branch Office',
        description: 'Operational business branch office.',
        icon: 'map-pin-house',
    },
    {
        value: 'warehouse',
        label: 'Warehouse',
        description: 'Inventory storage and handling facility.',
        icon: 'shelving-unit',
    },
    {
        value: 'distribution_hub',
        label: 'Distribution Hub',
        description: 'Distribution, fulfillment and logistics hub.',
        icon: 'map-pin-house',
    },
    {
        value: 'service_center',
        label: 'Service Center',
        description: 'Customer service, repair or support facility.',
        icon: 'house-cog',
    },
    {
        value: 'factory',
        label: 'Factory',
        description: 'Manufacturing or production facility.',
        icon: 'factory',
    },
    {
        value: 'other',
        label: 'Other',
        description: 'Other approved operational location.',
        icon: 'map-pin',
    },
];

const countries = [
    { value: 'BD', label: 'Bangladesh' },
    { value: 'IN', label: 'India' },
    { value: 'AE', label: 'United Arab Emirates' },
    { value: 'SA', label: 'Saudi Arabia' },
    { value: 'SG', label: 'Singapore' },
    { value: 'MY', label: 'Malaysia' },
    { value: 'GB', label: 'United Kingdom' },
    { value: 'US', label: 'United States' },
];

const divisions = [
    'Barishal',
    'Chattogram',
    'Dhaka',
    'Khulna',
    'Mymensingh',
    'Rajshahi',
    'Rangpur',
    'Sylhet',
];

const districtsByDivision: Record<string, string[]> = {
    Barishal: [
        'Barguna',
        'Barishal',
        'Bhola',
        'Jhalokati',
        'Patuakhali',
        'Pirojpur',
    ],
    Chattogram: [
        'Bandarban',
        'Brahmanbaria',
        'Chandpur',
        'Chattogram',
        'Cox’s Bazar',
        'Cumilla',
        'Feni',
        'Khagrachhari',
        'Lakshmipur',
        'Noakhali',
        'Rangamati',
    ],
    Dhaka: [
        'Dhaka',
        'Faridpur',
        'Gazipur',
        'Gopalganj',
        'Kishoreganj',
        'Madaripur',
        'Manikganj',
        'Munshiganj',
        'Narayanganj',
        'Narsingdi',
        'Rajbari',
        'Shariatpur',
        'Tangail',
    ],
    Khulna: [
        'Bagerhat',
        'Chuadanga',
        'Jashore',
        'Jhenaidah',
        'Khulna',
        'Kushtia',
        'Magura',
        'Meherpur',
        'Narail',
        'Satkhira',
    ],
    Mymensingh: ['Jamalpur', 'Mymensingh', 'Netrokona', 'Sherpur'],
    Rajshahi: [
        'Bogura',
        'Joypurhat',
        'Naogaon',
        'Natore',
        'Chapainawabganj',
        'Pabna',
        'Rajshahi',
        'Sirajganj',
    ],
    Rangpur: [
        'Dinajpur',
        'Gaibandha',
        'Kurigram',
        'Lalmonirhat',
        'Nilphamari',
        'Panchagarh',
        'Rangpur',
        'Thakurgaon',
    ],
    Sylhet: ['Habiganj', 'Moulvibazar', 'Sunamganj', 'Sylhet'],
};

const days = [
    { key: 'saturday', label: 'Saturday' },
    { key: 'sunday', label: 'Sunday' },
    { key: 'monday', label: 'Monday' },
    { key: 'tuesday', label: 'Tuesday' },
    { key: 'wednesday', label: 'Wednesday' },
    { key: 'thursday', label: 'Thursday' },
    { key: 'friday', label: 'Friday' },
];

function emptyBusinessHours(): BusinessHours {
    return Object.fromEntries(
        days.map((day) => [
            day.key,
            {
                enabled: true,
                open: '09:00',
                close: '18:00',
            },
        ]),
    ) as BusinessHours;
}

function emptyForm(): LocationForm {
    return {
        name: '',
        code: '',
        type: 'head_office',
        is_active: true,
        is_primary: false,
        country_code: 'BD',
        division: 'Dhaka',
        district: 'Dhaka',
        postal_code: '',
        address: '',
        phone: '',
        email: '',
        business_hours: emptyBusinessHours(),
    };
}

const locations = ref<Location[]>([]);
const isLoading = ref(false);
const isSaving = ref(false);
const isChangingStatus = ref<number | null>(null);

const search = ref('');
const typeFilter = ref<'all' | LocationType>('all');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');

const showModal = ref(false);
const modalMode = ref<ModalMode>('create');
const editingLocation = ref<Location | null>(null);

const form = reactive<LocationForm>(emptyForm());

const validationErrors = ref<ValidationErrors>({});
const operationMessage = ref('');
const operationMessageType = ref<'success' | 'error' | 'info'>('info');

const districts = computed(() => {
    return districtsByDivision[form.division] ?? [];
});

const filteredLocations = computed(() => {
    const query = search.value.trim().toLowerCase();

    return locations.value.filter((location) => {
        const searchableText = [
            location.name,
            location.code,
            location.type,
            location.division ?? '',
            location.district ?? '',
            location.address ?? '',
            location.phone ?? '',
            location.email ?? '',
        ]
            .join(' ')
            .toLowerCase();

        const matchesSearch =
            query.length === 0 || searchableText.includes(query);

        const matchesType =
            typeFilter.value === 'all' || location.type === typeFilter.value;

        const matchesStatus =
            statusFilter.value === 'all' ||
            (statusFilter.value === 'active' && location.is_active) ||
            (statusFilter.value === 'inactive' && !location.is_active);

        return matchesSearch && matchesType && matchesStatus;
    });
});

const totalLocations = computed(() => locations.value.length);

const activeLocations = computed(
    () => locations.value.filter((location) => location.is_active).length,
);

const inactiveLocations = computed(
    () => locations.value.filter((location) => !location.is_active).length,
);

const primaryLocations = computed(
    () => locations.value.filter((location) => location.is_primary).length,
);

const warehouseLocations = computed(
    () =>
        locations.value.filter(
            (location) =>
                location.type === 'warehouse' ||
                location.type === 'distribution_hub',
        ).length,
);

const modalTitle = computed(() =>
    modalMode.value === 'create' ? 'Create Location' : 'Edit Location',
);

const modalDescription = computed(() =>
    modalMode.value === 'create'
        ? 'Create a new head office, branch, warehouse, hub, service center, factory or other location.'
        : 'Update the location profile and operational configuration.',
);

const submitLabel = computed(() =>
    modalMode.value === 'create' ? 'Create Location' : 'Save Changes',
);

const modalProgress = computed(() => {
    const fields = [
        form.name,
        form.code,
        form.type,
        form.country_code,
        form.division,
        form.district,
        form.address,
        form.phone,
        form.email,
    ];

    const completed = fields.filter(
        (value) => String(value ?? '').trim().length > 0,
    ).length;

    return Math.round((completed / fields.length) * 100);
});

function locationTypeLabel(type: LocationType): string {
    return locationTypes.find((item) => item.value === type)?.label ?? 'Other';
}

function locationTypeIcon(type: LocationType): string {
    return locationTypes.find((item) => item.value === type)?.icon ?? 'map-pin';
}

function countryLabel(code: string): string {
    return countries.find((country) => country.value === code)?.label ?? code;
}

function showSuccess(message: string): void {
    operationMessageType.value = 'success';
    operationMessage.value = message;
}

function showError(message: string): void {
    operationMessageType.value = 'error';
    operationMessage.value = message;
}

function clearMessage(): void {
    operationMessage.value = '';
}

function csrfHeaders(): Record<string, string> {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token
            ? {
                  'X-CSRF-TOKEN': token,
              }
            : {}),
    };
}

async function readJsonResponse<T>(response: Response): Promise<ApiPayload<T>> {
    const contentType = response.headers.get('content-type') ?? '';

    if (!contentType.includes('application/json')) {
        return {};
    }

    return (await response.json()) as ApiPayload<T>;
}

function errorMessage(error: unknown, fallback: string): string {
    if (error instanceof Error && error.message.trim()) {
        return error.message;
    }

    return fallback;
}

function validationMessage(
    errors: Record<string, string[]> | undefined,
): string {
    if (!errors) {
        return '';
    }

    const firstKey = Object.keys(errors)[0];

    if (!firstKey) {
        return '';
    }

    return errors[firstKey]?.[0] ?? '';
}

function resetForm(): void {
    Object.assign(form, emptyForm());
    validationErrors.value = {};
}

function openCreateModal(): void {
    modalMode.value = 'create';
    editingLocation.value = null;
    resetForm();
    clearMessage();
    showModal.value = true;
}

function openShowPage(location: Location): void {
    router.get(`/locations/${location.id}`);
}

function openEditModal(location: Location): void {
    modalMode.value = 'edit';
    editingLocation.value = location;

    Object.assign(form, {
        name: location.name,
        code: location.code,
        type: location.type,
        is_active: location.is_active,
        is_primary: location.is_primary,
        country_code: location.country_code || 'BD',
        division: location.division || 'Dhaka',
        district:
            location.district ||
            districtsByDivision[location.division || 'Dhaka']?.[0] ||
            '',
        postal_code: location.postal_code || '',
        address: location.address || '',
        phone: location.phone || '',
        email: location.email || '',
        business_hours:
            location.business_hours &&
            Object.keys(location.business_hours).length > 0
                ? location.business_hours
                : emptyBusinessHours(),
    });

    validationErrors.value = {};
    clearMessage();
    showModal.value = true;
}

function closeModal(): void {
    if (isSaving.value) {
        return;
    }

    showModal.value = false;
    editingLocation.value = null;
    validationErrors.value = {};
}

function forceCloseModal(): void {
    showModal.value = false;
    editingLocation.value = null;
    validationErrors.value = {};
}

function setFieldError(field: string, message: string): void {
    validationErrors.value[field] = message;
}

function validateForm(): boolean {
    validationErrors.value = {};

    if (!form.name.trim()) {
        setFieldError('name', 'The location name is required.');
    }

    if (!form.code.trim()) {
        setFieldError('code', 'The location code is required.');
    } else if (!/^[A-Za-z0-9][A-Za-z0-9_-]*$/.test(form.code.trim())) {
        setFieldError(
            'code',
            'Use letters, numbers, hyphens or underscores only.',
        );
    }

    if (!form.country_code.trim()) {
        setFieldError('country_code', 'Country is required.');
    }

    if (
        form.country_code.toUpperCase() === 'BD' &&
        form.postal_code &&
        !/^\d{4}$/.test(form.postal_code.trim())
    ) {
        setFieldError(
            'postal_code',
            'Bangladesh postal code must contain exactly 4 digits.',
        );
    }

    if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
        setFieldError('email', 'Enter a valid email address.');
    }

    for (const day of days) {
        const schedule = form.business_hours[day.key];

        if (!schedule?.enabled) {
            continue;
        }

        if (!schedule.open || !schedule.close) {
            setFieldError(
                `business_hours.${day.key}`,
                'Opening and closing times are required.',
            );

            continue;
        }

        if (schedule.close <= schedule.open) {
            setFieldError(
                `business_hours.${day.key}`,
                'Closing time must be later than opening time.',
            );
        }
    }

    return Object.keys(validationErrors.value).length === 0;
}

function handleDivisionChange(): void {
    const availableDistricts = districtsByDivision[form.division] ?? [];

    if (!availableDistricts.includes(form.district)) {
        form.district = availableDistricts[0] ?? '';
    }
}

function formatLocationAddress(location: Location): string {
    return [
        location.district,
        location.division,
        countryLabel(location.country_code),
    ]
        .filter(Boolean)
        .join(', ');
}

async function fetchLocations(): Promise<boolean> {
    isLoading.value = true;
    clearMessage();

    try {
        const response = await fetch('/api/locations', {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const payload = await readJsonResponse<Location[]>(response);

        if (!response.ok) {
            throw new Error(
                payload.message ??
                    `Unable to load locations. HTTP ${response.status}.`,
            );
        }

        locations.value = Array.isArray(payload.data) ? payload.data : [];

        return true;
    } catch (error) {
        showError(
            errorMessage(error, 'Unable to load locations. Please try again.'),
        );

        return false;
    } finally {
        isLoading.value = false;
    }
}

async function saveLocation(): Promise<void> {
    if (isSaving.value) {
        return;
    }

    if (!validateForm()) {
        showError('Please review the highlighted fields.');

        return;
    }

    isSaving.value = true;
    clearMessage();

    try {
        const isEdit =
            modalMode.value === 'edit' && editingLocation.value !== null;

        const url = isEdit
            ? `/api/locations/${editingLocation.value!.id}`
            : '/api/locations';

        const method = isEdit ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({
                name: form.name.trim(),
                code: form.code.trim().toUpperCase(),
                type: form.type,
                is_active: form.is_active,
                is_primary: form.is_primary,
                country_code: form.country_code.trim().toUpperCase(),
                division: form.division.trim() || null,
                district: form.district.trim() || null,
                postal_code: form.postal_code.trim() || null,
                address: form.address.trim() || null,
                phone: form.phone.trim() || null,
                email: form.email.trim() || null,
                business_hours: form.business_hours,
            }),
        });

        const payload = await readJsonResponse<Location>(response);

        if (!response.ok) {
            const firstValidationError = validationMessage(payload.errors);

            if (payload.errors && Object.keys(payload.errors).length > 0) {
                validationErrors.value = Object.fromEntries(
                    Object.entries(payload.errors).map(([key, messages]) => [
                        key,
                        messages[0] ?? 'Invalid value.',
                    ]),
                );
            }

            throw new Error(
                firstValidationError ||
                    payload.message ||
                    `Unable to save location. HTTP ${response.status}.`,
            );
        }

        const refreshed = await fetchLocations();

        if (!refreshed) {
            return;
        }

        forceCloseModal();

        if (!isEdit) {
            showSuccess('Location created successfully.');
            return;
        }

        showSuccess('Location updated successfully.');
    } catch (error) {
        showError(
            errorMessage(
                error,
                'Unable to save location. Please review the form.',
            ),
        );
    } finally {
        isSaving.value = false;
    }
}

async function changeStatus(location: Location): Promise<void> {
    if (isChangingStatus.value !== null) {
        return;
    }

    const action = location.is_active ? 'deactivate' : 'activate';

    if (action === 'deactivate' && location.is_primary) {
        showError(
            'The primary location cannot be deactivated. Assign another primary location first.',
        );

        return;
    }

    isChangingStatus.value = location.id;

    clearMessage();

    try {
        const response = await fetch(
            `/api/locations/${location.id}/${action}`,
            {
                method: 'PATCH',
                headers: csrfHeaders(),
                credentials: 'same-origin',
            },
        );

        const payload = await readJsonResponse<Location>(response);

        if (!response.ok) {
            throw new Error(
                payload.message ??
                    `Unable to ${action} location. HTTP ${response.status}.`,
            );
        }

        const refreshed = await fetchLocations();

        if (!refreshed) {
            return;
        }

        showSuccess(
            action === 'activate'
                ? 'Location activated successfully.'
                : 'Location deactivated successfully.',
        );
    } catch (error) {
        showError(errorMessage(error, `Unable to ${action} location.`));
    } finally {
        isChangingStatus.value = null;
    }
}

function clearFilters(): void {
    search.value = '';
    typeFilter.value = 'all';
    statusFilter.value = 'all';
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && showModal.value && !isSaving.value) {
        closeModal();
    }
}

onMounted(() => {
    void fetchLocations();

    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Head title="Locations" />

    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <div class="locations-page">
            <header class="page-header">
                <div class="page-heading">
                    <div class="breadcrumb">
                        <span>Settings</span>

                        <Icon name="chevron-right" :size="12" />

                        <strong> Locations </strong>
                    </div>

                    <div class="title-row">
                        <div class="page-title-icon" aria-hidden="true">
                            <Icon name="map-pin" :size="21" />
                        </div>

                        <div>
                            <h1>Locations</h1>

                            <p>
                                Manage branches, warehouses and operational
                                locations across your business.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="page-header-actions">
                    <button
                        class="access-btn access-btn-primary"
                        type="button"
                        @click="openCreateModal"
                    >
                        <Icon name="plus" :size="15" />

                        Add Location
                    </button>
                </div>
            </header>

            <div
                v-if="operationMessage"
                class="operation-message"
                :class="operationMessageType"
                role="status"
            >
                <Icon
                    :name="
                        operationMessageType === 'success'
                            ? 'check'
                            : operationMessageType === 'error'
                              ? 'close'
                              : 'settings'
                    "
                    :size="14"
                />

                <span>
                    {{ operationMessage }}
                </span>

                <button
                    type="button"
                    aria-label="Dismiss message"
                    @click="clearMessage"
                >
                    <Icon name="close" :size="13" />
                </button>
            </div>

            <section class="stat-grid" aria-label="Location statistics">
                <article class="stat-card">
                    <div class="stat-icon">
                        <Icon name="map-pin" :size="17" />
                    </div>

                    <div>
                        <span> Total Locations </span>

                        <strong>
                            {{ totalLocations }}
                        </strong>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon success">
                        <Icon name="check" :size="17" />
                    </div>

                    <div>
                        <span>Active</span>

                        <strong>
                            {{ activeLocations }}
                        </strong>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon warning">
                        <ShieldX :size="17" :stroke-width="1.8" />
                    </div>

                    <div>
                        <span> Inactive </span>

                        <strong>
                            {{ inactiveLocations }}
                        </strong>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon primary">
                        <Icon name="map-pin" :size="17" />
                    </div>

                    <div>
                        <span>Primary</span>

                        <strong>
                            {{ primaryLocations }}
                        </strong>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon">
                        <Icon
                            name="building-warehouse"
                            :size="17"
                            :stroke-width="1.8"
                        />
                    </div>

                    <div>
                        <span> Storage &amp; Hubs </span>

                        <strong>
                            {{ warehouseLocations }}
                        </strong>
                    </div>
                </article>
            </section>

            <section class="content-card">
                <div class="toolbar">
                    <label class="search-box">
                        <Icon name="search" :size="14" />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search locations..."
                            aria-label="Search locations"
                        />
                    </label>

                    <div class="toolbar-actions">
                        <select
                            v-model="typeFilter"
                            class="filter-control"
                            aria-label="Filter location type"
                        >
                            <option value="all">All Types</option>

                            <option
                                v-for="type in locationTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </select>

                        <select
                            v-model="statusFilter"
                            class="filter-control"
                            aria-label="Filter location status"
                        >
                            <option value="all">All Status</option>

                            <option value="active">Active</option>

                            <option value="inactive">Inactive</option>
                        </select>

                        <button
                            class="access-btn refresh-btn"
                            type="button"
                            :disabled="isLoading"
                            @click="fetchLocations"
                        >
                            <Icon name="refresh" :size="14" />

                            Refresh
                        </button>
                    </div>
                </div>

                <div class="table-meta">
                    <div>
                        <strong>
                            {{ filteredLocations.length }}
                        </strong>

                        <span>
                            {{
                                filteredLocations.length === 1
                                    ? 'location'
                                    : 'locations'
                            }}
                        </span>
                    </div>

                    <button
                        v-if="
                            search ||
                            typeFilter !== 'all' ||
                            statusFilter !== 'all'
                        "
                        class="toolbar-text-btn"
                        type="button"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <div v-if="isLoading" class="loading-state">
                    <div class="loading-spinner"></div>

                    <span> Loading locations... </span>
                </div>

                <div
                    v-else-if="filteredLocations.length === 0"
                    class="empty-state"
                >
                    <div class="empty-icon">
                        <Icon name="map-pin" :size="22" />
                    </div>

                    <h3>
                        {{
                            totalLocations === 0
                                ? 'No locations yet'
                                : 'No matching locations'
                        }}
                    </h3>

                    <p>
                        {{
                            totalLocations === 0
                                ? 'Create your first business location to start building the operational foundation.'
                                : 'Try changing your search or filter criteria.'
                        }}
                    </p>

                    <button
                        v-if="totalLocations === 0"
                        class="access-btn access-btn-primary empty-action"
                        type="button"
                        @click="openCreateModal"
                    >
                        <Icon name="plus" :size="14" />

                        Add First Location
                    </button>
                </div>

                <div v-else class="table-wrap">
                    <table class="locations-table">
                        <thead>
                            <tr>
                                <th>Location</th>

                                <th>Type</th>

                                <th>Address</th>

                                <th>Contact</th>

                                <th>Status</th>

                                <th class="actions-heading">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="location in filteredLocations"
                                :key="location.id"
                            >
                                <td>
                                    <div class="location-cell">
                                        <div
                                            class="location-avatar"
                                            :class="{
                                                primary: location.is_primary,
                                            }"
                                        >
                                            <Icon
                                                :name="
                                                    locationTypeIcon(
                                                        location.type,
                                                    )
                                                "
                                                :size="16"
                                            />
                                        </div>

                                        <div class="location-main">
                                            <div class="location-name-row">
                                                <button
                                                    class="location-name-link"
                                                    type="button"
                                                    title="View location"
                                                    @click.stop.prevent="
                                                        openShowPage(location)
                                                    "
                                                >
                                                    {{ location.name }}
                                                </button>

                                                <span
                                                    v-if="location.is_primary"
                                                    class="primary-badge"
                                                >
                                                    Primary
                                                </span>
                                            </div>

                                            <span class="location-code">
                                                {{ location.code }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="type-badge">
                                        {{ locationTypeLabel(location.type) }}
                                    </span>
                                </td>

                                <td>
                                    <div class="address-cell">
                                        <strong>
                                            {{
                                                formatLocationAddress(
                                                    location,
                                                ) || '—'
                                            }}
                                        </strong>

                                        <span>
                                            {{
                                                location.address ||
                                                'No operating address'
                                            }}
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <div class="contact-cell">
                                        <span v-if="location.phone">
                                            <Icon name="phone" :size="11" />

                                            {{ location.phone }}
                                        </span>

                                        <span v-if="location.email">
                                            <Icon name="mail" :size="11" />

                                            {{ location.email }}
                                        </span>

                                        <span
                                            v-if="
                                                !location.phone &&
                                                !location.email
                                            "
                                        >
                                            —
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span
                                        class="status-badge"
                                        :class="{
                                            active: location.is_active,
                                            inactive: !location.is_active,
                                        }"
                                    >
                                        {{
                                            location.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </span>
                                </td>

                                <td>
                                    <div class="row-actions">
                                        <button
                                            class="icon-only-btn"
                                            type="button"
                                            title="Edit location"
                                            @click="openEditModal(location)"
                                        >
                                            <Icon name="edit" :size="14" />
                                        </button>

                                        <button
                                            class="icon-only-btn status-action"
                                            :class="{
                                                danger: location.is_active,
                                            }"
                                            type="button"
                                            :disabled="
                                                isChangingStatus === location.id
                                            "
                                            :title="
                                                location.is_active
                                                    ? 'Deactivate location'
                                                    : 'Activate location'
                                            "
                                            @click="changeStatus(location)"
                                        >
                                            <ShieldX
                                                v-if="location.is_active"
                                                :size="14"
                                                :stroke-width="1.8"
                                            />
                                            <Icon
                                                v-else
                                                name="check"
                                                :size="14"
                                            />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showModal"
                    class="modal-backdrop"
                    @mousedown.self="closeModal"
                >
                    <section
                        class="location-modal"
                        role="dialog"
                        aria-modal="true"
                        :aria-label="modalTitle"
                    >
                        <header class="modal-header">
                            <div class="modal-heading">
                                <div class="modal-heading-icon">
                                    <Icon name="map-pin" :size="19" />
                                </div>

                                <div>
                                    <div class="modal-kicker">
                                        Business Location
                                    </div>

                                    <h2>
                                        {{ modalTitle }}
                                    </h2>

                                    <p>
                                        {{ modalDescription }}
                                    </p>
                                </div>
                            </div>

                            <button
                                class="modal-close icon-only-btn"
                                type="button"
                                aria-label="Close"
                                :disabled="isSaving"
                                @click="closeModal"
                            >
                                <Icon name="close" :size="16" />
                            </button>
                        </header>

                        <div class="modal-progress">
                            <div
                                class="modal-progress-bar"
                                :style="{
                                    width: modalProgress + '%',
                                }"
                            ></div>
                        </div>

                        <form class="modal-body" @submit.prevent="saveLocation">
                            <section class="form-section">
                                <div class="section-heading">
                                    <div class="section-icon">
                                        <Icon name="map-pin" :size="15" />
                                    </div>

                                    <div>
                                        <h3>Location Identity</h3>

                                        <p>
                                            Define the location name, code and
                                            operational type.
                                        </p>
                                    </div>
                                </div>

                                <div class="form-grid two">
                                    <label class="field">
                                        <span>
                                            Location Name
                                            <b>*</b>
                                        </span>

                                        <input
                                            v-model="form.name"
                                            type="text"
                                            maxlength="150"
                                            placeholder="e.g. Dhaka Head Office"
                                            :class="{
                                                invalid: validationErrors.name,
                                            }"
                                        />

                                        <small
                                            v-if="validationErrors.name"
                                            class="field-error"
                                        >
                                            {{ validationErrors.name }}
                                        </small>
                                    </label>

                                    <label class="field">
                                        <span>
                                            Location Code
                                            <b>*</b>
                                        </span>

                                        <input
                                            v-model="form.code"
                                            type="text"
                                            maxlength="50"
                                            placeholder="e.g. DHA-HQ"
                                            :class="{
                                                invalid: validationErrors.code,
                                            }"
                                        />

                                        <small
                                            v-if="validationErrors.code"
                                            class="field-error"
                                        >
                                            {{ validationErrors.code }}
                                        </small>
                                    </label>
                                </div>

                                <div class="type-selector">
                                    <div class="field-label">
                                        Location Type
                                        <b>*</b>
                                    </div>

                                    <div class="type-grid">
                                        <button
                                            v-for="type in locationTypes"
                                            :key="type.value"
                                            class="type-card"
                                            :class="{
                                                selected:
                                                    form.type === type.value,
                                            }"
                                            type="button"
                                            @click="form.type = type.value"
                                        >
                                            <span class="type-card-icon">
                                                <Icon
                                                    :name="type.icon"
                                                    :size="17"
                                                />
                                            </span>

                                            <span class="type-card-copy">
                                                <strong>
                                                    {{ type.label }}
                                                </strong>

                                                <small>
                                                    {{ type.description }}
                                                </small>
                                            </span>

                                            <span
                                                v-if="form.type === type.value"
                                                class="type-selected"
                                            >
                                                <Icon name="check" :size="11" />
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <button
                                    class="primary-location-card"
                                    :class="{
                                        selected: form.is_primary,
                                    }"
                                    type="button"
                                    @click="form.is_primary = !form.is_primary"
                                >
                                    <span class="primary-location-icon">
                                        <Icon name="map-pin" :size="17" />
                                    </span>

                                    <span class="setting-copy">
                                        <strong> Primary Location </strong>

                                        <small>
                                            Use this as the business's primary
                                            operating location.
                                        </small>
                                    </span>

                                    <span
                                        class="switch"
                                        :class="{
                                            on: form.is_primary,
                                        }"
                                        aria-hidden="true"
                                    >
                                        <span></span>
                                    </span>
                                </button>
                            </section>

                            <section class="form-section">
                                <div class="section-heading">
                                    <div class="section-icon">
                                        <Icon name="map-pin" :size="15" />
                                    </div>

                                    <div>
                                        <h3>Address &amp; Contact</h3>

                                        <p>
                                            Define the physical location and
                                            communication details.
                                        </p>
                                    </div>
                                </div>

                                <div class="form-grid three">
                                    <label class="field">
                                        <span> Country </span>

                                        <select v-model="form.country_code">
                                            <option
                                                v-for="country in countries"
                                                :key="country.value"
                                                :value="country.value"
                                            >
                                                {{ country.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span> Division </span>

                                        <select
                                            v-model="form.division"
                                            @change="handleDivisionChange"
                                        >
                                            <option
                                                v-for="division in divisions"
                                                :key="division"
                                                :value="division"
                                            >
                                                {{ division }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span> District </span>

                                        <select v-model="form.district">
                                            <option
                                                v-for="district in districts"
                                                :key="district"
                                                :value="district"
                                            >
                                                {{ district }}
                                            </option>
                                        </select>
                                    </label>
                                </div>

                                <div class="form-grid three">
                                    <label class="field">
                                        <span> Postal Code </span>

                                        <input
                                            v-model="form.postal_code"
                                            type="text"
                                            maxlength="20"
                                            placeholder="1205"
                                            :class="{
                                                invalid:
                                                    validationErrors.postal_code,
                                            }"
                                        />

                                        <small
                                            v-if="validationErrors.postal_code"
                                            class="field-error"
                                        >
                                            {{ validationErrors.postal_code }}
                                        </small>
                                    </label>

                                    <label class="field">
                                        <span> Phone </span>

                                        <input
                                            v-model="form.phone"
                                            type="tel"
                                            maxlength="30"
                                            placeholder="+880 1XXXXXXXXX"
                                        />
                                    </label>

                                    <label class="field">
                                        <span> Email </span>

                                        <input
                                            v-model="form.email"
                                            type="email"
                                            maxlength="150"
                                            placeholder="location@company.com"
                                            :class="{
                                                invalid: validationErrors.email,
                                            }"
                                        />

                                        <small
                                            v-if="validationErrors.email"
                                            class="field-error"
                                        >
                                            {{ validationErrors.email }}
                                        </small>
                                    </label>
                                </div>

                                <label class="field">
                                    <span> Operating Address </span>

                                    <textarea
                                        v-model="form.address"
                                        rows="3"
                                        maxlength="5000"
                                        placeholder="House, road, area, landmark..."
                                    ></textarea>
                                </label>
                            </section>

                            <section class="form-section">
                                <div class="section-heading">
                                    <div class="section-icon">
                                        <Icon name="calendar" :size="15" />
                                    </div>

                                    <div>
                                        <h3>Business Hours</h3>

                                        <p>
                                            Configure operating hours for each
                                            day.
                                        </p>
                                    </div>
                                </div>

                                <div class="hours-list">
                                    <div
                                        v-for="day in days"
                                        :key="day.key"
                                        class="hours-row"
                                        :class="{
                                            disabled:
                                                !form.business_hours[day.key]
                                                    .enabled,
                                        }"
                                    >
                                        <div class="hours-day">
                                            <span class="hours-day-icon">
                                                <Icon
                                                    name="calendar"
                                                    :size="13"
                                                />
                                            </span>

                                            <strong>
                                                {{ day.label }}
                                            </strong>
                                        </div>

                                        <button
                                            class="switch small"
                                            :class="{
                                                on: form.business_hours[day.key]
                                                    .enabled,
                                            }"
                                            type="button"
                                            :aria-pressed="
                                                form.business_hours[day.key]
                                                    .enabled
                                            "
                                            @click="
                                                form.business_hours[
                                                    day.key
                                                ].enabled =
                                                    !form.business_hours[
                                                        day.key
                                                    ].enabled
                                            "
                                        >
                                            <span></span>
                                        </button>

                                        <input
                                            v-model="
                                                form.business_hours[day.key]
                                                    .open
                                            "
                                            type="time"
                                            :disabled="
                                                !form.business_hours[day.key]
                                                    .enabled
                                            "
                                        />

                                        <span class="time-separator"> to </span>

                                        <input
                                            v-model="
                                                form.business_hours[day.key]
                                                    .close
                                            "
                                            type="time"
                                            :disabled="
                                                !form.business_hours[day.key]
                                                    .enabled
                                            "
                                        />
                                    </div>
                                </div>
                            </section>

                            <section class="form-section compact">
                                <div class="section-heading">
                                    <div
                                        class="section-icon"
                                        :class="{
                                            active: form.is_active,
                                        }"
                                    >
                                        <component
                                            v-if="form.is_active"
                                            :is="Icon"
                                            name="check"
                                            :size="15"
                                        />
                                        <ShieldX
                                            v-else
                                            :size="15"
                                            :stroke-width="1.8"
                                        />
                                    </div>

                                    <div>
                                        <h3>Operational Status</h3>

                                        <p>
                                            Control whether this location is
                                            currently active.
                                        </p>
                                    </div>
                                </div>

                                <button
                                    class="status-setting-card"
                                    :class="{
                                        selected: form.is_active,
                                    }"
                                    type="button"
                                    @click="form.is_active = !form.is_active"
                                >
                                    <span
                                        class="status-setting-icon"
                                        :class="{
                                            active: form.is_active,
                                        }"
                                    >
                                        <component
                                            v-if="form.is_active"
                                            :is="Icon"
                                            name="check"
                                            :size="16"
                                        />
                                        <ShieldX
                                            v-else
                                            :size="16"
                                            :stroke-width="1.8"
                                        />
                                    </span>

                                    <span class="setting-copy">
                                        <strong>
                                            {{
                                                form.is_active
                                                    ? 'Location Active'
                                                    : 'Location Inactive'
                                            }}
                                        </strong>

                                        <small>
                                            {{
                                                form.is_active
                                                    ? 'This location can participate in normal operations.'
                                                    : 'This location will remain unavailable for normal operations.'
                                            }}
                                        </small>
                                    </span>

                                    <span
                                        class="switch"
                                        :class="{
                                            on: form.is_active,
                                        }"
                                        aria-hidden="true"
                                    >
                                        <span></span>
                                    </span>
                                </button>
                            </section>

                            <div
                                v-if="Object.keys(validationErrors).length > 0"
                                class="modal-validation-summary"
                            >
                                <Icon name="alert" :size="14" />

                                <span>
                                    Please review the highlighted fields before
                                    saving.
                                </span>
                            </div>

                            <footer class="modal-footer">
                                <div class="modal-footer-info">
                                    <span> {{ modalProgress }}% complete </span>

                                    <small>
                                        Required fields are validated
                                        automatically.
                                    </small>
                                </div>

                                <div class="modal-footer-actions">
                                    <button
                                        class="access-btn"
                                        type="button"
                                        :disabled="isSaving"
                                        @click="closeModal"
                                    >
                                        Cancel
                                    </button>

                                    <button
                                        class="access-btn access-btn-primary modal-submit"
                                        type="submit"
                                        :disabled="isSaving"
                                    >
                                        <span
                                            v-if="isSaving"
                                            class="button-spinner"
                                        ></span>

                                        <Icon
                                            v-else
                                            :name="
                                                modalMode === 'create'
                                                    ? 'plus'
                                                    : 'check'
                                            "
                                            :size="14"
                                        />

                                        <span>
                                            {{
                                                isSaving
                                                    ? modalMode === 'create'
                                                        ? 'Creating...'
                                                        : 'Saving...'
                                                    : submitLabel
                                            }}
                                        </span>
                                    </button>
                                </div>
                            </footer>
                        </form>
                    </section>
                </div>
            </Transition>
        </Teleport>
    </ErpLayout>
</template>

<style scoped>
/* ==========================================================================
   PAGE
   ========================================================================== */

.locations-page {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 0;
    color: var(--text-primary);
}

/* ==========================================================================
   HEADER
   ========================================================================== */

.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 22px;
}

.page-heading {
    min-width: 0;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 9px;
    color: var(--text-tertiary);
    font-size: 10px;
    font-weight: 600;
}

.breadcrumb strong {
    color: var(--text-secondary);
    font-weight: 700;
}

.title-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
    border-radius: 12px;
    background: color-mix(in srgb, var(--primary) 10%, var(--surface));
    color: var(--primary);
    box-shadow: 0 8px 22px color-mix(in srgb, var(--primary) 9%, transparent);
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
}

.page-header-actions {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    gap: 8px;
}

/* ==========================================================================
   SHARED BUTTON SYSTEM
   Exact visual direction of Roles & Permissions
   ========================================================================== */

.access-btn {
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
    font-size: 11px;
    font-weight: 650;
    cursor: pointer;
    transition:
        transform 180ms ease,
        border-color 180ms ease,
        background-color 180ms ease,
        color 180ms ease,
        box-shadow 180ms ease;
}

.access-btn:hover:not(:disabled) {
    border-color: var(--border-strong);
    color: var(--text-primary);
    transform: translateY(-1px);
}

.access-btn:focus-visible,
.icon-only-btn:focus-visible,
.toolbar-text-btn:focus-visible {
    outline: 2px solid color-mix(in srgb, var(--primary) 55%, transparent);
    outline-offset: 2px;
}

.access-btn:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.access-btn-primary {
    border-color: color-mix(in srgb, var(--primary) 80%, var(--border));
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--primary) 92%, white),
        var(--primary)
    );
    color: #fff;
    box-shadow: 0 9px 22px color-mix(in srgb, var(--primary) 17%, transparent);
}

.access-btn-primary:hover:not(:disabled) {
    border-color: var(--primary);
    color: #fff;
    box-shadow: 0 12px 26px color-mix(in srgb, var(--primary) 22%, transparent);
}

.access-btn-primary:active:not(:disabled) {
    transform: translateY(0);
}

.refresh-btn {
    min-height: 38px;
}

/* ==========================================================================
   FEEDBACK
   ========================================================================== */

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
    font-size: 10px;
    animation: locations-enter 280ms ease both;
}

.operation-message span {
    flex: 1;
}

.operation-message button {
    width: 24px;
    height: 24px;
    display: grid;
    place-items: center;
    padding: 0;
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
}

.operation-message.success {
    border-color: color-mix(in srgb, var(--success) 28%, var(--border));
    background: color-mix(in srgb, var(--success) 7%, var(--surface));
    color: var(--success);
}

.operation-message.error {
    border-color: color-mix(in srgb, #dc4f4f 28%, var(--border));
    background: color-mix(in srgb, #dc4f4f 7%, var(--surface));
    color: #dc4f4f;
}

.operation-message.info {
    color: var(--text-secondary);
}

/* ==========================================================================
   KPI
   ========================================================================== */

.stat-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
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
    background: var(--surface);
    box-shadow: 0 2px 7px rgba(15, 23, 42, 0.025);
}

.stat-icon {
    width: 39px;
    height: 39px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface-muted, var(--surface));
    color: var(--text-secondary);
}

.stat-icon.success {
    color: var(--success);
}

.stat-icon.warning {
    color: #d97706;
}

.stat-icon.primary {
    color: var(--primary);
}

.stat-card span {
    display: block;
    color: var(--text-tertiary);
    font-size: 9px;
    font-weight: 500;
}

.stat-card strong {
    display: block;
    margin-top: 3px;
    color: var(--text-primary);
    font-size: 20px;
    line-height: 1;
    font-weight: 700;
}

/* ==========================================================================
   CONTENT
   ========================================================================== */

.content-card {
    overflow: hidden;
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.025);
}

.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
}

.search-box {
    width: min(410px, 100%);
    height: 38px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 11px;
    box-sizing: border-box;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface-muted, var(--surface));
    color: var(--text-tertiary);
}

.search-box:focus-within {
    border-color: color-mix(in srgb, var(--primary) 42%, var(--border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 7%, transparent);
}

.search-box input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text-primary);
    font-size: 10px;
}

.search-box input::placeholder {
    color: var(--text-tertiary);
}

.toolbar-actions {
    display: flex;
    align-items: center;
    gap: 7px;
}

.filter-control {
    height: 38px;
    min-width: 128px;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: 9px;
    outline: 0;
    background: var(--surface);
    color: var(--text-secondary);
    font-size: 9px;
}

.filter-control:focus {
    border-color: color-mix(in srgb, var(--primary) 42%, var(--border));
}

.table-meta {
    min-height: 42px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 15px;
    border-bottom: 1px solid var(--border);
    color: var(--text-tertiary);
    font-size: 9px;
}

.table-meta > div {
    display: flex;
    align-items: baseline;
    gap: 4px;
}

.table-meta strong {
    color: var(--text-primary);
    font-size: 11px;
}

.toolbar-text-btn {
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--primary);
    font-size: 9px;
    font-weight: 700;
    cursor: pointer;
}

/* ==========================================================================
   EMPTY / LOADING
   ========================================================================== */

.loading-state,
.empty-state {
    min-height: 320px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 35px;
}

.loading-state {
    color: var(--text-secondary);
    font-size: 10px;
}

.loading-spinner,
.button-spinner {
    width: 18px;
    height: 18px;
    border: 2px solid color-mix(in srgb, var(--primary) 18%, transparent);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: locations-spin 0.8s linear infinite;
}

.button-spinner {
    width: 13px;
    height: 13px;
    border-width: 2px;
    border-top-color: #fff;
}

.empty-icon {
    width: 54px;
    height: 54px;
    display: grid;
    place-items: center;
    margin-bottom: 4px;
    border: 1px solid var(--border);
    border-radius: 11px;
    background: var(--surface-muted, var(--surface));
    color: var(--text-tertiary);
}

.empty-state h3 {
    margin: 0;
    font-size: 13px;
}

.empty-state p {
    max-width: 420px;
    margin: 0;
    color: var(--text-tertiary);
    font-size: 9px;
    line-height: 1.5;
    text-align: center;
}

.empty-action {
    margin-top: 8px;
}

/* ==========================================================================
   TABLE
   ========================================================================== */

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

.locations-table {
    width: 100%;
    min-width: 920px;
    border-collapse: collapse;
}

.locations-table th {
    padding: 11px 14px;
    border-bottom: 1px solid var(--border);
    background: var(--surface-muted, var(--surface));
    color: var(--text-tertiary);
    font-size: 8px;
    font-weight: 700;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.locations-table td {
    padding: 13px 14px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    font-size: 9px;
}

.locations-table tbody tr:last-child td {
    border-bottom: 0;
}

.location-cell {
    display: flex;
    align-items: center;
    gap: 9px;
}

.location-avatar {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface-muted, var(--surface));
    color: var(--text-secondary);
}

.location-avatar.primary {
    border-color: color-mix(in srgb, var(--primary) 25%, var(--border));
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
    color: var(--primary);
}

.location-main {
    min-width: 0;
}

.location-name-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.location-name-row strong {
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 700;
}

.location-name-link {
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--text-primary);
    font: inherit;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
}

.location-code {
    display: block;
    margin-top: 3px;
    color: var(--text-tertiary);
    font-size: 8px;
}

.primary-badge {
    padding: 3px 5px;
    border-radius: 5px;
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
    color: var(--primary);
    font-size: 7px;
    font-weight: 700;
}

.type-badge,
.status-badge {
    display: inline-flex;
    align-items: center;
    min-height: 23px;
    padding: 0 8px;
    border-radius: 6px;
    font-size: 8px;
    font-weight: 700;
}

.type-badge {
    border: 1px solid var(--border);
    background: var(--surface-muted, var(--surface));
    color: var(--text-secondary);
}

.status-badge.active {
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
    color: var(--primary);
}

.status-badge.inactive {
    background: rgba(239, 68, 68, 0.07);
    color: #dc2626;
}

.address-cell strong,
.address-cell span,
.contact-cell span {
    display: block;
}

.address-cell strong {
    max-width: 230px;
    overflow: hidden;
    color: var(--text-secondary);
    font-size: 9px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.address-cell span {
    max-width: 230px;
    margin-top: 3px;
    overflow: hidden;
    color: var(--text-tertiary);
    font-size: 8px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.contact-cell {
    display: grid;
    gap: 5px;
}

.contact-cell span {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--text-secondary);
    font-size: 8px;
}

.actions-heading {
    text-align: right !important;
}

.row-actions {
    display: flex;
    justify-content: flex-end;
    gap: 5px;
}

.icon-only-btn {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 7px;
    background: var(--surface);
    color: var(--text-secondary);
    cursor: pointer;
    transition:
        transform 160ms ease,
        border-color 160ms ease,
        color 160ms ease;
}

.icon-only-btn:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--primary) 28%, var(--border));
    color: var(--primary);
    transform: translateY(-1px);
}

.icon-only-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.status-action.danger:hover:not(:disabled) {
    border-color: rgba(239, 68, 68, 0.22);
    color: #dc2626;
}

/* ==========================================================================
   MODAL
   ========================================================================== */

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(5, 12, 20, 0.55);
    backdrop-filter: blur(7px);
}

.location-modal {
    width: min(930px, 100%);
    max-height: min(92vh, 900px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--border);
    border-radius: 14px;
    background: var(--surface);
    box-shadow:
        0 30px 90px rgba(0, 0, 0, 0.25),
        0 10px 30px rgba(0, 0, 0, 0.08);
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    padding: 18px 20px 15px;
    border-bottom: 1px solid var(--border);
}

.modal-heading {
    display: flex;
    gap: 11px;
}

.modal-heading-icon {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
    border-radius: 9px;
    background: color-mix(in srgb, var(--primary) 10%, var(--surface));
    color: var(--primary);
}

.modal-kicker {
    min-height: 15px;
    margin-bottom: 3px;
    color: var(--primary);
    font-size: 8px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.modal-header h2 {
    margin: 0;
    font-size: 17px;
    line-height: 1.2;
}

.modal-header p {
    margin: 4px 0 0;
    color: var(--text-secondary);
    font-size: 9px;
    line-height: 1.45;
}

.modal-close {
    flex: 0 0 auto;
}

.modal-progress {
    height: 3px;
    background: var(--surface-muted, var(--surface));
}

.modal-progress-bar {
    height: 100%;
    background: linear-gradient(
        135deg,
        color-mix(in srgb, var(--primary) 92%, white),
        var(--primary)
    );
    transition: width 0.2s ease;
}

.modal-body {
    overflow-y: auto;
    min-height: 0;
}

.form-section {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
}

.form-section.compact {
    padding-bottom: 17px;
}

.section-heading {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-bottom: 15px;
}

.section-icon {
    width: 31px;
    height: 31px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface-muted, var(--surface));
    color: var(--text-secondary);
}

.section-icon.active {
    border-color: color-mix(in srgb, var(--primary) 22%, var(--border));
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
    color: var(--primary);
}

.section-heading h3 {
    margin: 1px 0 0;
    font-size: 11px;
    font-weight: 800;
}

.section-heading p {
    margin: 3px 0 0;
    color: var(--text-tertiary);
    font-size: 8px;
    line-height: 1.45;
}

.form-grid {
    display: grid;
    gap: 11px;
    margin-bottom: 12px;
}

.form-grid.two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.form-grid.three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.field {
    display: block;
    min-width: 0;
}

.field > span,
.field-label {
    display: block;
    margin-bottom: 5px;
    color: var(--text-secondary);
    font-size: 8px;
    font-weight: 700;
}

.field b,
.field-label b {
    color: #dc2626;
}

.field input,
.field select,
.field textarea,
.hours-row input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid var(--border);
    border-radius: 7px;
    outline: 0;
    background: var(--surface);
    color: var(--text-primary);
    font-family: inherit;
    font-size: 9px;
    transition:
        border-color 150ms ease,
        box-shadow 150ms ease;
}

.field input,
.field select {
    height: 37px;
    padding: 0 10px;
}

.field textarea {
    min-height: 76px;
    padding: 9px 10px;
    resize: vertical;
    line-height: 1.45;
}

.field input:focus,
.field select:focus,
.field textarea:focus,
.hours-row input:focus {
    border-color: color-mix(in srgb, var(--primary) 45%, var(--border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 7%, transparent);
}

.field input.invalid,
.field select.invalid,
.field textarea.invalid {
    border-color: rgba(239, 68, 68, 0.45);
}

.field-error {
    display: block;
    margin-top: 4px;
    color: #dc2626;
    font-size: 7px;
    line-height: 1.35;
}

/* ==========================================================================
   LOCATION TYPE
   ========================================================================== */

.type-selector {
    margin-top: 3px;
}

.type-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.type-card {
    position: relative;
    min-height: 76px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text-primary);
    text-align: left;
    cursor: pointer;
    transition:
        border-color 150ms ease,
        background 150ms ease,
        transform 150ms ease;
}

.type-card:hover {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--primary) 28%, var(--border));
}

.type-card.selected {
    border-color: color-mix(in srgb, var(--primary) 45%, var(--border));
    background: color-mix(in srgb, var(--primary) 7%, var(--surface));
}

.type-card-icon {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border: 1px solid var(--border);
    border-radius: 7px;
    background: var(--surface);
    color: var(--text-secondary);
}

.type-card.selected .type-card-icon {
    border-color: color-mix(in srgb, var(--primary) 22%, var(--border));
    color: var(--primary);
}

.type-card-copy {
    min-width: 0;
}

.type-card-copy strong,
.type-card-copy small {
    display: block;
}

.type-card-copy strong {
    color: var(--text-primary);
    font-size: 8px;
    font-weight: 800;
}

.type-card-copy small {
    margin-top: 3px;
    color: var(--text-tertiary);
    font-size: 7px;
    line-height: 1.35;
}

.type-selected {
    position: absolute;
    top: 7px;
    right: 7px;
    width: 17px;
    height: 17px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
}

/* ==========================================================================
   PRIMARY / STATUS SETTINGS
   ========================================================================== */

.primary-location-card,
.status-setting-card {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
    padding: 11px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text-primary);
    text-align: left;
    cursor: pointer;
    transition:
        border-color 160ms ease,
        background-color 160ms ease;
}

.primary-location-card.selected,
.status-setting-card.selected {
    border-color: color-mix(in srgb, var(--primary) 30%, var(--border));
    background: color-mix(in srgb, var(--primary) 7%, var(--surface));
}

.primary-location-icon,
.status-setting-icon {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 8px;
    background: var(--surface-muted, var(--surface));
    color: var(--text-secondary);
}

.primary-location-card.selected .primary-location-icon,
.status-setting-icon.active {
    color: var(--primary);
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
}

.setting-copy {
    flex: 1;
    min-width: 0;
}

.setting-copy strong,
.setting-copy small {
    display: block;
}

.setting-copy strong {
    font-size: 9px;
    font-weight: 800;
}

.setting-copy small {
    margin-top: 3px;
    color: var(--text-tertiary);
    font-size: 7px;
    line-height: 1.4;
}

/* ==========================================================================
   TOGGLE
   ========================================================================== */

.switch {
    position: relative;
    width: 36px;
    height: 20px;
    display: block;
    flex: 0 0 auto;
    padding: 2px;
    box-sizing: border-box;
    border: 0;
    border-radius: 999px;
    background: #cbd5df;
    cursor: pointer;
    transition:
        background-color 160ms ease,
        box-shadow 160ms ease;
}

.switch span {
    display: block;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #fff;
    box-shadow:
        0 1px 3px rgba(0, 0, 0, 0.24),
        0 0 0 1px rgba(0, 0, 0, 0.04);
    transition: transform 160ms ease;
}

.switch.on {
    background: var(--primary);
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--primary) 20%, transparent),
        0 4px 10px color-mix(in srgb, var(--primary) 20%, transparent);
}

.switch.on span {
    transform: translateX(16px);
}

.switch.small {
    width: 32px;
    height: 18px;
    padding: 2px;
}

.switch.small span {
    width: 14px;
    height: 14px;
}

.switch.small.on span {
    transform: translateX(14px);
}

.hours-row.disabled .switch {
    background: #d8dfe6;
    box-shadow: none;
}

/* ==========================================================================
   BUSINESS HOURS
   ========================================================================== */

.hours-list {
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 8px;
}

.hours-row {
    min-height: 48px;
    display: grid;
    grid-template-columns:
        minmax(125px, 1fr)
        35px
        105px
        24px
        105px;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    border-bottom: 1px solid var(--border);
}

.hours-row:last-child {
    border-bottom: 0;
}

.hours-row.disabled {
    opacity: 0.55;
}

.hours-day {
    display: flex;
    align-items: center;
    gap: 7px;
}

.hours-day-icon {
    width: 25px;
    height: 25px;
    display: grid;
    place-items: center;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--surface-muted, var(--surface));
    color: var(--text-tertiary);
}

.hours-day strong {
    font-size: 8px;
}

.hours-row input {
    height: 32px;
    padding: 0 7px;
    font-size: 8px;
}

.time-separator {
    color: var(--text-tertiary);
    font-size: 8px;
    text-align: center;
}

/* ==========================================================================
   MODAL VALIDATION + FOOTER
   ========================================================================== */

.modal-validation-summary {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0 20px 14px;
    padding: 9px 10px;
    border: 1px solid rgba(239, 68, 68, 0.18);
    border-radius: 7px;
    background: rgba(239, 68, 68, 0.05);
    color: #dc2626;
    font-size: 8px;
    font-weight: 600;
}

.modal-footer {
    position: sticky;
    bottom: 0;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 13px 20px;
    border-top: 1px solid var(--border);
    background: var(--surface);
    box-shadow: 0 -8px 18px rgba(15, 23, 42, 0.035);
}

.modal-footer-info span,
.modal-footer-info small {
    display: block;
}

.modal-footer-info span {
    color: var(--text-secondary);
    font-size: 8px;
    font-weight: 700;
}

.modal-footer-info small {
    margin-top: 3px;
    color: var(--text-tertiary);
    font-size: 7px;
}

.modal-footer-actions {
    display: flex;
    align-items: center;
    gap: 7px;
}

.modal-submit {
    min-width: 125px;
}

/* ==========================================================================
   ANIMATION
   ========================================================================== */

@keyframes locations-spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes locations-enter {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity 180ms ease;
}

.modal-enter-active .location-modal,
.modal-leave-active .location-modal {
    transition:
        transform 180ms ease,
        opacity 180ms ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .location-modal,
.modal-leave-to .location-modal {
    opacity: 0;
    transform: translateY(10px) scale(0.99);
}

/* ==========================================================================
   RESPONSIVE
   ========================================================================== */

@media (max-width: 1080px) {
    .stat-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .type-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 800px) {
    .locations-page {
        padding: 0;
    }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .page-header-actions {
        width: 100%;
    }

    .page-header-actions .access-btn {
        width: 100%;
    }

    .stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .search-box {
        width: 100%;
    }

    .toolbar-actions {
        width: 100%;
        flex-wrap: wrap;
    }

    .filter-control,
    .refresh-btn {
        flex: 1;
    }

    .form-grid.three {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .hours-row {
        grid-template-columns:
            minmax(110px, 1fr)
            32px
            1fr
            20px
            1fr;
    }
}

@media (max-width: 620px) {
    .locations-page {
        padding: 12px 10px 20px;
    }

    .stat-grid,
    .form-grid.two,
    .form-grid.three,
    .type-grid {
        grid-template-columns: 1fr;
    }

    .modal-backdrop {
        align-items: flex-end;
        padding: 0;
    }

    .location-modal {
        max-height: 94vh;
        border-radius: 14px 14px 0 0;
    }

    .modal-header,
    .form-section {
        padding-left: 15px;
        padding-right: 15px;
    }

    .hours-row {
        grid-template-columns:
            1fr
            32px;
        gap: 7px;
    }

    .hours-row input {
        width: 100%;
    }

    .time-separator {
        display: none;
    }

    .modal-footer {
        align-items: stretch;
        flex-direction: column;
        padding: 12px 15px;
    }

    .modal-footer-actions {
        width: 100%;
    }

    .modal-footer-actions .access-btn {
        flex: 1;
    }
}

@media (max-width: 440px) {
    .title-row h1 {
        font-size: 21px;
    }

    .stat-grid {
        gap: 8px;
    }

    .stat-card {
        min-height: 68px;
        padding: 10px;
    }

    .stat-icon {
        width: 34px;
        height: 34px;
    }

    .modal-heading-icon {
        width: 34px;
        height: 34px;
    }

    .modal-header h2 {
        font-size: 15px;
    }
}
</style>
