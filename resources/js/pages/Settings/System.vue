<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';

import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface Props {
    userName?: string;
    userEmail?: string;
}

type SettingGroup =
    | 'general'
    | 'localization'
    | 'currency'
    | 'business'
    | 'security'
    | 'system';

type SettingType = 'string' | 'boolean' | 'integer' | 'json';

interface SettingDefinition {
    key: string;
    label: string;
    description: string;
    group: SettingGroup;
    type: SettingType;
    icon: string;
}

interface SettingValue {
    key: string;
    value: unknown;
    type: string;
}

interface ApiSettingsResponse {
    data?: Record<string, unknown>;
    message?: string;
}

interface CategoryDefinition {
    id: SettingGroup | 'overview';
    label: string;
    description: string;
    icon: string;
}

interface BusinessHour {
    enabled: boolean;
    open: string;
    close: string;
}

type BusinessHours = Record<string, BusinessHour>;

const props = withDefaults(defineProps<Props>(), {
    userName: '',
    userEmail: '',
});

const categories: CategoryDefinition[] = [
    {
        id: 'overview',
        label: 'Overview',
        description: 'Settings health and configuration summary.',
        icon: 'activity',
    },
    {
        id: 'general',
        label: 'General',
        description: 'Application identity, contact and support.',
        icon: 'settings',
    },
    {
        id: 'localization',
        label: 'Localization',
        description: 'Language, timezone and date/time behavior.',
        icon: 'settings',
    },
    {
        id: 'currency',
        label: 'Currency',
        description: 'Currency and number formatting.',
        icon: 'settings',
    },
    {
        id: 'business',
        label: 'Business',
        description: 'Business identity and operating information.',
        icon: 'settings',
    },
    {
        id: 'security',
        label: 'Security',
        description: 'Authentication protection and password policy.',
        icon: 'security',
    },
    {
        id: 'system',
        label: 'System',
        description: 'Core platform behavior and operations.',
        icon: 'developer',
    },
];

const definitions: SettingDefinition[] = [
    {
        key: 'general.site_name',
        label: 'Site Name',
        description: 'Application name displayed across the platform.',
        group: 'general',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'general.site_description',
        label: 'Site Description',
        description: 'Short description used to identify the platform.',
        group: 'general',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'general.contact_email',
        label: 'Contact Email',
        description: 'Primary platform contact email address.',
        group: 'general',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'general.contact_phone',
        label: 'Contact Phone',
        description: 'Primary platform contact phone number.',
        group: 'general',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'general.support_channels',
        label: 'Support Channels',
        description: 'Customer support channels available across the platform.',
        group: 'general',
        type: 'json',
        icon: 'activity',
    },

    {
        key: 'localization.timezone',
        label: 'Timezone',
        description: 'Default timezone used for application dates and times.',
        group: 'localization',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'localization.locale',
        label: 'Locale',
        description: 'Default application language and locale code.',
        group: 'localization',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'localization.date_format',
        label: 'Date Format',
        description: 'Default format used when displaying calendar dates.',
        group: 'localization',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'localization.time_format',
        label: 'Time Format',
        description: 'Default 12-hour or 24-hour time display.',
        group: 'localization',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'localization.week_starts_on',
        label: 'Week Starts On',
        description: 'First day used by calendars and weekly views.',
        group: 'localization',
        type: 'integer',
        icon: 'settings',
    },

    {
        key: 'currency.default',
        label: 'Default Currency',
        description: 'Primary currency code used for monetary values.',
        group: 'currency',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'currency.symbol',
        label: 'Currency Symbol',
        description: 'Symbol displayed alongside monetary amounts.',
        group: 'currency',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'currency.decimal_places',
        label: 'Decimal Places',
        description: 'Number of decimal places used for monetary values.',
        group: 'currency',
        type: 'integer',
        icon: 'settings',
    },
    {
        key: 'currency.thousand_separator',
        label: 'Thousands Separator',
        description: 'Character used to separate groups of thousands.',
        group: 'currency',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'currency.decimal_separator',
        label: 'Decimal Separator',
        description: 'Character used to separate the decimal portion.',
        group: 'currency',
        type: 'string',
        icon: 'settings',
    },

    {
        key: 'business.legal_name',
        label: 'Legal Business Name',
        description: 'Legal or registered business name.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.name',
        label: 'Trading / Display Name',
        description: 'Primary business name shown throughout the platform.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.email',
        label: 'Business Email',
        description: 'Primary business email address.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.phone',
        label: 'Business Phone',
        description: 'Primary business phone number.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.website',
        label: 'Business Website',
        description:
            'Public website used for business identity and communications.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.address',
        label: 'Business Address',
        description: 'Primary registered or operating business address.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.country_code',
        label: 'Country',
        description:
            'Primary country using an ISO 3166-1 alpha-2 country code.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.division',
        label: 'Division / State',
        description: 'Primary administrative division, state or province.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.district',
        label: 'District / City',
        description: 'Primary district, city or equivalent local area.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.postal_code',
        label: 'Postal Code',
        description: 'Primary business postal or ZIP code.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.type',
        label: 'Business Type',
        description: 'Primary legal or organizational business type.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.industry',
        label: 'Industry',
        description: 'Primary industry or economic activity.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.registration_number',
        label: 'Registration Number',
        description: 'Business registration or incorporation number.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.bin',
        label: 'BIN',
        description:
            'Bangladesh Business Identification Number, where applicable.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.tin',
        label: 'TIN',
        description: 'Taxpayer Identification Number, where applicable.',
        group: 'business',
        type: 'string',
        icon: 'settings',
    },
    {
        key: 'business.business_hours',
        label: 'Business Hours',
        description: 'Operating schedule used by the business.',
        group: 'business',
        type: 'json',
        icon: 'activity',
    },

    {
        key: 'security.login_max_attempts',
        label: 'Maximum Login Attempts',
        description:
            'Maximum consecutive failed login attempts before temporary account lockout.',
        group: 'security',
        type: 'integer',
        icon: 'security',
    },
    {
        key: 'security.lockout_duration_minutes',
        label: 'Lockout Duration',
        description:
            'Temporary account lockout duration after the configured failed-attempt threshold.',
        group: 'security',
        type: 'integer',
        icon: 'security',
    },
    {
        key: 'security.session_timeout_minutes',
        label: 'Session Timeout',
        description:
            'Maximum inactive session lifetime before the user must authenticate again.',
        group: 'security',
        type: 'integer',
        icon: 'security',
    },
    {
        key: 'security.password_min_length',
        label: 'Minimum Password Length',
        description:
            'Minimum number of characters required for a user password.',
        group: 'security',
        type: 'integer',
        icon: 'security',
    },
    {
        key: 'security.password_require_mixed_case',
        label: 'Require Mixed Case',
        description:
            'Require both uppercase and lowercase characters in passwords.',
        group: 'security',
        type: 'boolean',
        icon: 'security',
    },
    {
        key: 'security.password_require_numbers',
        label: 'Require Numbers',
        description: 'Require at least one numeric character in passwords.',
        group: 'security',
        type: 'boolean',
        icon: 'security',
    },
    {
        key: 'security.password_require_symbols',
        label: 'Require Symbols',
        description: 'Require at least one symbol character in passwords.',
        group: 'security',
        type: 'boolean',
        icon: 'security',
    },
    {
        key: 'security.password_expiry_days',
        label: 'Password Expiry',
        description:
            'Number of days before a password is considered expired. Use 0 to disable expiry.',
        group: 'security',
        type: 'integer',
        icon: 'security',
    },
    {
        key: 'security.force_password_change',
        label: 'Force Password Change',
        description:
            'Require users to change their password when the security policy demands it.',
        group: 'security',
        type: 'boolean',
        icon: 'security',
    },
    {
        key: 'security.require_email_verification',
        label: 'Require Email Verification',
        description:
            'Require users to verify their email address before normal account access.',
        group: 'security',
        type: 'boolean',
        icon: 'security',
    },

    {
        key: 'system.maintenance_mode',
        label: 'Maintenance Mode',
        description: 'Temporarily place the platform into maintenance mode.',
        group: 'system',
        type: 'boolean',
        icon: 'developer',
    },
    {
        key: 'system.items_per_page',
        label: 'Items Per Page',
        description: 'Default number of records displayed in paginated lists.',
        group: 'system',
        type: 'integer',
        icon: 'settings',
    },
    {
        key: 'system.support_channels',
        label: 'System Support Channels',
        description: 'Legacy system-level support channel configuration.',
        group: 'system',
        type: 'json',
        icon: 'activity',
    },
];

const dayDefinitions = [
    { key: 'saturday', label: 'Saturday' },
    { key: 'sunday', label: 'Sunday' },
    { key: 'monday', label: 'Monday' },
    { key: 'tuesday', label: 'Tuesday' },
    { key: 'wednesday', label: 'Wednesday' },
    { key: 'thursday', label: 'Thursday' },
    { key: 'friday', label: 'Friday' },
];

const supportChannels = ['chat', 'email', 'phone'];

const countryOptions = [
    {
        value: 'BD',
        label: 'Bangladesh — BD',
    },
    {
        value: 'IN',
        label: 'India — IN',
    },
    {
        value: 'AE',
        label: 'United Arab Emirates — AE',
    },
    {
        value: 'MY',
        label: 'Malaysia — MY',
    },
    {
        value: 'SG',
        label: 'Singapore — SG',
    },
    {
        value: 'GB',
        label: 'United Kingdom — GB',
    },
    {
        value: 'US',
        label: 'United States — US',
    },
];

const bangladeshDivisionOptions = [
    { value: 'Dhaka', label: 'Dhaka' },
    { value: 'Chattogram', label: 'Chattogram' },
    { value: 'Rajshahi', label: 'Rajshahi' },
    { value: 'Khulna', label: 'Khulna' },
    { value: 'Barishal', label: 'Barishal' },
    { value: 'Sylhet', label: 'Sylhet' },
    { value: 'Rangpur', label: 'Rangpur' },
    { value: 'Mymensingh', label: 'Mymensingh' },
];

const businessTypeOptions = [
    {
        value: 'sole_proprietorship',
        label: 'Sole Proprietorship',
    },
    {
        value: 'partnership',
        label: 'Partnership',
    },
    {
        value: 'private_limited',
        label: 'Private Limited Company',
    },
    {
        value: 'public_limited',
        label: 'Public Limited Company',
    },
    {
        value: 'cooperative',
        label: 'Cooperative',
    },
    {
        value: 'nonprofit',
        label: 'Nonprofit / NGO',
    },
    {
        value: 'other',
        label: 'Other',
    },
];

const industryOptions = [
    'Retail',
    'Wholesale',
    'Manufacturing',
    'Distribution',
    'E-commerce',
    'Food & Beverage',
    'Services',
    'Technology',
    'Healthcare',
    'Education',
    'Construction',
    'Real Estate',
    'Import & Export',
    'Other',
];

const timezoneOptions = [
    {
        value: 'Asia/Dhaka',
        label: 'Asia/Dhaka — Bangladesh Standard Time (UTC+06:00)',
    },
    {
        value: 'Asia/Kolkata',
        label: 'Asia/Kolkata — India Standard Time (UTC+05:30)',
    },
    {
        value: 'Asia/Dubai',
        label: 'Asia/Dubai — Gulf Standard Time (UTC+04:00)',
    },
    {
        value: 'Asia/Singapore',
        label: 'Asia/Singapore — Singapore Standard Time (UTC+08:00)',
    },
    {
        value: 'UTC',
        label: 'UTC — Coordinated Universal Time',
    },
];

const localeOptions = [
    {
        value: 'en-BD',
        label: 'English (Bangladesh) — en-BD',
    },
    {
        value: 'bn-BD',
        label: 'বাংলা (বাংলাদেশ) — bn-BD',
    },
    {
        value: 'en',
        label: 'English — en',
    },
    {
        value: 'bn',
        label: 'বাংলা — bn',
    },
];

const dateFormatOptions = [
    {
        value: 'DD/MM/YYYY',
        label: 'DD/MM/YYYY — 07/10/2026',
    },
    {
        value: 'DD MMM YYYY',
        label: 'DD MMM YYYY — 07 Oct 2026',
    },
    {
        value: 'DD MMMM YYYY',
        label: 'DD MMMM YYYY — 07 October 2026',
    },
    {
        value: 'YYYY-MM-DD',
        label: 'YYYY-MM-DD — 2026-10-07',
    },
    {
        value: 'MM/DD/YYYY',
        label: 'MM/DD/YYYY — 10/07/2026',
    },
];

const currencyOptions = [
    {
        value: 'BDT',
        label: 'BDT — Bangladeshi Taka',
        symbol: '৳',
    },
    {
        value: 'USD',
        label: 'USD — US Dollar',
        symbol: '$',
    },
    {
        value: 'EUR',
        label: 'EUR — Euro',
        symbol: '€',
    },
    {
        value: 'GBP',
        label: 'GBP — British Pound',
        symbol: '£',
    },
    {
        value: 'INR',
        label: 'INR — Indian Rupee',
        symbol: '₹',
    },
    {
        value: 'SAR',
        label: 'SAR — Saudi Riyal',
        symbol: '﷼',
    },
    {
        value: 'AED',
        label: 'AED — UAE Dirham',
        symbol: 'د.إ',
    },
];

const currencySymbolOptions = [
    {
        value: '৳',
        label: '৳ — Bangladeshi Taka',
    },
    {
        value: '$',
        label: '$ — US Dollar',
    },
    {
        value: '€',
        label: '€ — Euro',
    },
    {
        value: '£',
        label: '£ — British Pound',
    },
    {
        value: '₹',
        label: '₹ — Indian Rupee',
    },
    {
        value: '﷼',
        label: '﷼ — Saudi Riyal',
    },
    {
        value: 'د.إ',
        label: 'د.إ — UAE Dirham',
    },
];

const decimalPlaceOptions = [
    {
        value: 0,
        label: '0 — No decimal',
    },
    {
        value: 1,
        label: '1 — 123.4',
    },
    {
        value: 2,
        label: '2 — 123.45',
    },
    {
        value: 3,
        label: '3 — 123.456',
    },
    {
        value: 4,
        label: '4 — 123.4567',
    },
];

const thousandSeparatorOptions = [
    {
        value: ',',
        label: ', — 1,234,567.89',
    },
    {
        value: '.',
        label: '. — 1.234.567,89',
    },
    {
        value: ' ',
        label: 'Space — 1 234 567.89',
    },
];

const decimalSeparatorOptions = [
    {
        value: '.',
        label: '. — 123.45',
    },
    {
        value: ',',
        label: ', — 123,45',
    },
];

const settings = ref<Record<string, unknown>>({});
const originalSettings = ref<Record<string, unknown>>({});

const activeCategory = ref<SettingGroup | 'overview'>('overview');
const isLoading = ref(false);
const isSaving = ref(false);
const operationMessage = ref('');
const operationMessageType = ref<'success' | 'error'>('success');

const activeCategoryDefinition = computed(() =>
    categories.find((category) => category.id === activeCategory.value),
);

const activeDefinitions = computed(() =>
    definitions.filter(
        (definition) => definition.group === activeCategory.value,
    ),
);

const configuredCount = computed(
    () =>
        definitions.filter((definition) =>
            Object.prototype.hasOwnProperty.call(
                settings.value,
                definition.key,
            ),
        ).length,
);

const totalCount = computed(() => definitions.length);

const changedCount = computed(
    () =>
        definitions.filter(
            (definition) =>
                JSON.stringify(settings.value[definition.key]) !==
                JSON.stringify(originalSettings.value[definition.key]),
        ).length,
);

const hasChanges = computed(() => changedCount.value > 0);

const categoryStats = computed(() =>
    categories
        .filter(
            (category): category is CategoryDefinition & { id: SettingGroup } =>
                category.id !== 'overview',
        )
        .map((category) => {
            const categoryDefinitions = definitions.filter(
                (definition) => definition.group === category.id,
            );

            const configured = categoryDefinitions.filter((definition) =>
                Object.prototype.hasOwnProperty.call(
                    settings.value,
                    definition.key,
                ),
            ).length;

            const changed = categoryDefinitions.filter(
                (definition) =>
                    JSON.stringify(settings.value[definition.key]) !==
                    JSON.stringify(originalSettings.value[definition.key]),
            ).length;

            return {
                ...category,
                total: categoryDefinitions.length,
                configured,
                changed,
            };
        }),
);

function displayValue(key: string): unknown {
    return settings.value[key];
}

function setValue(key: string, value: unknown): void {
    settings.value = {
        ...settings.value,
        [key]: value,
    };

    operationMessage.value = '';
}

function stringValue(key: string): string {
    const value = displayValue(key);

    return typeof value === 'string' ? value : '';
}

function numberValue(key: string, fallback = 0): number {
    const value = displayValue(key);

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : fallback;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : fallback;
}

function booleanValue(key: string): boolean {
    return displayValue(key) === true;
}

function arrayValue(key: string): string[] {
    const value = displayValue(key);

    return Array.isArray(value)
        ? value.filter((item): item is string => typeof item === 'string')
        : [];
}

function businessHoursValue(): BusinessHours {
    const value = displayValue('business.business_hours');

    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return {};
    }

    return value as BusinessHours;
}

function updateBusinessHour(
    day: string,
    field: keyof BusinessHour,
    value: boolean | string,
): void {
    const current = businessHoursValue();

    const next: BusinessHours = {
        ...current,
        [day]: {
            enabled: current[day]?.enabled ?? false,
            open: current[day]?.open ?? '09:00',
            close: current[day]?.close ?? '18:00',
            [field]: value,
        },
    };

    setValue('business.business_hours', next);
}

function businessHour(day: string): BusinessHour {
    const hours = businessHoursValue();

    return (
        hours[day] ?? {
            enabled: day !== 'friday',
            open: '09:00',
            close: '18:00',
        }
    );
}

function toggleSupportChannel(
    key: 'general.support_channels' | 'system.support_channels',
    channel: string,
): void {
    const channels = arrayValue(key);
    const exists = channels.includes(channel);

    const nextChannels = exists
        ? channels.filter((item) => item !== channel)
        : [...channels, channel];

    setValue(key, nextChannels);
}

function formatSettingValue(key: string): string {
    const value = displayValue(key);

    if (Array.isArray(value)) {
        return value.join(', ');
    }

    if (typeof value === 'boolean') {
        return value ? 'Enabled' : 'Disabled';
    }

    if (value === null || value === undefined || value === '') {
        return 'Not configured';
    }

    return String(value);
}

function categoryConfigured(group: SettingGroup | 'overview'): number {
    if (group === 'overview') {
        return 0;
    }

    return definitions.filter(
        (definition) =>
            definition.group === group &&
            Object.prototype.hasOwnProperty.call(
                settings.value,
                definition.key,
            ),
    ).length;
}

function selectCategory(category: SettingGroup | 'overview'): void {
    activeCategory.value = category;
    operationMessage.value = '';
}

async function apiRequest<T>(
    url: string,
    method: 'GET' | 'PUT',
    body?: unknown,
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const csrfToken = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');

    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    });

    const responseText = await response.text();
    let payload: unknown = null;

    if (responseText.trim() !== '') {
        try {
            payload = JSON.parse(responseText) as unknown;
        } catch {
            payload = null;
        }
    }

    if (!response.ok) {
        const errorPayload = payload as {
            message?: string;
            errors?: Record<string, string | string[]>;
        } | null;

        const firstValidationError = Object.values(
            errorPayload?.errors ?? {},
        )[0];

        const message =
            typeof firstValidationError === 'string'
                ? firstValidationError
                : Array.isArray(firstValidationError)
                  ? firstValidationError[0]
                  : errorPayload?.message;

        throw new Error(
            message || `Request failed with status ${response.status}.`,
        );
    }

    return payload as T;
}

async function fetchSettings(): Promise<void> {
    isLoading.value = true;
    operationMessage.value = '';

    try {
        const response = await apiRequest<ApiSettingsResponse>(
            '/api/settings',
            'GET',
        );

        const data = response.data ?? {};

        settings.value = { ...data };
        originalSettings.value = { ...data };
    } catch (error) {
        operationMessageType.value = 'error';
        operationMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to load system settings.';
    } finally {
        isLoading.value = false;
    }
}

function resetChanges(): void {
    settings.value = {
        ...originalSettings.value,
    };

    operationMessage.value = '';
}

function buildChangedSettings(): SettingValue[] {
    return definitions
        .filter((definition) => {
            const current = settings.value[definition.key];
            const original = originalSettings.value[definition.key];

            return JSON.stringify(current) !== JSON.stringify(original);
        })
        .map((definition) => ({
            key: definition.key,
            value: settings.value[definition.key],
            type: definition.type,
        }));
}

async function saveSettings(): Promise<void> {
    if (!hasChanges.value || isSaving.value) {
        return;
    }

    const changedSettings = buildChangedSettings();

    if (changedSettings.length === 0) {
        return;
    }

    isSaving.value = true;
    operationMessage.value = '';

    try {
        const response = await apiRequest<ApiSettingsResponse>(
            '/api/settings',
            'PUT',
            {
                settings: changedSettings,
            },
        );

        const refreshedData = response.data ?? {};

        settings.value = {
            ...settings.value,
            ...refreshedData,
        };

        originalSettings.value = {
            ...settings.value,
        };

        operationMessageType.value = 'success';
        operationMessage.value =
            changedSettings.length === 1
                ? 'Setting updated successfully.'
                : `${changedSettings.length} settings updated successfully.`;
    } catch (error) {
        operationMessageType.value = 'error';
        operationMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to update system settings.';
    } finally {
        isSaving.value = false;
    }
}

onMounted(() => {
    void fetchSettings();
});
</script>

<template>
    <Head title="Settings Center" />

    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <div class="settings-page">
            <header class="page-header">
                <div class="page-heading">
                    <div class="breadcrumb">
                        <span>Settings</span>
                        <span class="breadcrumb-separator">/</span>
                        <strong>{{ activeCategoryDefinition?.label }}</strong>
                    </div>

                    <div class="heading-row">
                        <div class="heading-icon">
                            <Icon name="settings" :size="17" />
                        </div>

                        <div>
                            <h1>Settings Center</h1>
                            <p>
                                Configure Madina Scale from one centralized,
                                permission-protected control center.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="header-actions">
                    <button
                        class="btn secondary"
                        type="button"
                        :disabled="isLoading || isSaving"
                        @click="fetchSettings"
                    >
                        <Icon name="refresh" :size="14" />
                        <span>{{
                            isLoading ? 'Refreshing...' : 'Refresh'
                        }}</span>
                    </button>

                    <button
                        class="btn primary"
                        type="button"
                        :disabled="!hasChanges || isSaving"
                        @click="saveSettings"
                    >
                        <Icon name="settings" :size="14" />
                        <span>{{
                            isSaving ? 'Saving...' : 'Save Changes'
                        }}</span>
                    </button>
                </div>
            </header>

            <Transition name="message">
                <div
                    v-if="operationMessage"
                    class="operation-message"
                    :class="operationMessageType"
                    role="status"
                >
                    <span class="message-icon">
                        <Icon
                            :name="
                                operationMessageType === 'success'
                                    ? 'activity'
                                    : 'settings'
                            "
                            :size="14"
                        />
                    </span>

                    <div>
                        <strong>
                            {{
                                operationMessageType === 'success'
                                    ? 'Settings saved'
                                    : 'Unable to complete request'
                            }}
                        </strong>
                        <span>{{ operationMessage }}</span>
                    </div>
                </div>
            </Transition>

            <section class="stat-grid">
                <article class="stat-card">
                    <div class="stat-icon">
                        <Icon name="settings" :size="16" />
                    </div>
                    <div>
                        <span>Configured</span>
                        <strong>{{ configuredCount }}/{{ totalCount }}</strong>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon">
                        <Icon name="activity" :size="16" />
                    </div>
                    <div>
                        <span>Categories</span>
                        <strong>{{ categories.length - 1 }}</strong>
                    </div>
                </article>

                <article class="stat-card">
                    <div class="stat-icon">
                        <Icon name="developer" :size="16" />
                    </div>
                    <div>
                        <span>Unsaved</span>
                        <strong>{{ changedCount }}</strong>
                    </div>
                </article>
            </section>

            <div class="settings-layout">
                <aside class="settings-sidebar">
                    <div class="sidebar-heading">
                        <span class="section-kicker">CONFIGURATION</span>
                        <strong>Settings</strong>
                        <small>Choose an area to configure.</small>
                    </div>

                    <nav class="category-nav" aria-label="Settings categories">
                        <button
                            v-for="category in categories"
                            :key="category.id"
                            type="button"
                            class="category-item"
                            :class="{ active: activeCategory === category.id }"
                            @click="selectCategory(category.id)"
                        >
                            <span class="category-icon">
                                <Icon :name="category.icon" :size="14" />
                            </span>

                            <span class="category-copy">
                                <strong>{{ category.label }}</strong>
                                <small>{{ category.description }}</small>
                            </span>

                            <span
                                v-if="category.id !== 'overview'"
                                class="category-count"
                            >
                                {{ categoryConfigured(category.id) }}
                            </span>
                        </button>
                    </nav>

                    <div class="sidebar-security">
                        <span class="security-dot"></span>
                        <div>
                            <strong>Protected</strong>
                            <small>
                                Authorization is enforced server-side.
                            </small>
                        </div>
                    </div>
                </aside>

                <main class="settings-content">
                    <section
                        v-if="isLoading"
                        class="settings-card loading-card"
                    >
                        <div class="loading-indicator">
                            <span class="loading-spinner"></span>
                            <div>
                                <strong>Loading settings</strong>
                                <span>
                                    Retrieving the latest configuration...
                                </span>
                            </div>
                        </div>
                    </section>

                    <template v-else>
                        <section
                            v-if="activeCategory === 'overview'"
                            class="overview-panel"
                        >
                            <div class="content-heading">
                                <div>
                                    <span class="section-kicker">OVERVIEW</span>
                                    <h2>Settings Center</h2>
                                    <p>
                                        A centralized view of your platform
                                        configuration and settings health.
                                    </p>
                                </div>

                                <span class="section-badge">
                                    {{ totalCount }} registered settings
                                </span>
                            </div>

                            <div class="overview-hero">
                                <div class="overview-hero-icon">
                                    <Icon name="settings" :size="22" />
                                </div>

                                <div>
                                    <strong
                                        >Platform configuration is ready</strong
                                    >
                                    <span>
                                        All active settings are loaded from the
                                        server and changes are protected by
                                        authorization and activity logging.
                                    </span>
                                </div>
                            </div>

                            <div class="category-grid">
                                <button
                                    v-for="category in categoryStats"
                                    :key="category.id"
                                    type="button"
                                    class="category-card"
                                    @click="selectCategory(category.id)"
                                >
                                    <div class="category-card-top">
                                        <span class="category-card-icon">
                                            <Icon
                                                :name="category.icon"
                                                :size="16"
                                            />
                                        </span>

                                        <span
                                            v-if="category.changed"
                                            class="changed-badge"
                                        >
                                            {{ category.changed }} changed
                                        </span>
                                    </div>

                                    <strong>{{ category.label }}</strong>
                                    <span>{{ category.description }}</span>

                                    <div class="category-progress">
                                        <div class="progress-track">
                                            <span
                                                :style="{
                                                    width:
                                                        category.total > 0
                                                            ? `${(category.configured / category.total) * 100}%`
                                                            : '0%',
                                                }"
                                            ></span>
                                        </div>
                                        <small>
                                            {{ category.configured }}/{{
                                                category.total
                                            }}
                                            configured
                                        </small>
                                    </div>
                                </button>
                            </div>

                            <div class="overview-note">
                                <Icon name="activity" :size="14" />
                                <div>
                                    <strong>Operational note</strong>
                                    <span>
                                        Settings changes are recorded in
                                        Activity Logs. Additional areas such as
                                        security, email, integrations, SEO and
                                        advanced controls can be added without
                                        changing this settings architecture.
                                    </span>
                                </div>
                            </div>
                        </section>

                        <section v-else class="category-panel">
                            <div class="content-heading">
                                <div>
                                    <span class="section-kicker">
                                        {{
                                            activeCategoryDefinition?.label?.toUpperCase()
                                        }}
                                    </span>
                                    <h2>
                                        {{ activeCategoryDefinition?.label }}
                                        Settings
                                    </h2>
                                    <p>
                                        {{
                                            activeCategoryDefinition?.description
                                        }}
                                    </p>
                                </div>

                                <span class="section-badge">
                                    {{ activeDefinitions.length }} settings
                                </span>
                            </div>

                            <div
                                v-if="activeCategory === 'general'"
                                class="settings-card setting-group-card"
                            >
                                <div class="subsection-heading">
                                    <div>
                                        <strong>Site Identity</strong>
                                        <span>
                                            Core information displayed across
                                            the platform.
                                        </span>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <label class="field full">
                                        <span>Site Name</span>
                                        <small>
                                            Application name displayed across
                                            the platform.
                                        </small>
                                        <input
                                            :value="
                                                stringValue('general.site_name')
                                            "
                                            type="text"
                                            @input="
                                                setValue(
                                                    'general.site_name',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field full">
                                        <span>Site Description</span>
                                        <small>
                                            Short description used to identify
                                            the platform.
                                        </small>
                                        <textarea
                                            :value="
                                                stringValue(
                                                    'general.site_description',
                                                )
                                            "
                                            rows="3"
                                            @input="
                                                setValue(
                                                    'general.site_description',
                                                    (
                                                        $event.target as HTMLTextAreaElement
                                                    ).value,
                                                )
                                            "
                                        ></textarea>
                                    </label>
                                </div>
                            </div>

                            <div
                                v-if="activeCategory === 'general'"
                                class="settings-card setting-group-card"
                            >
                                <div class="subsection-heading">
                                    <div>
                                        <strong>Contact Information</strong>
                                        <span>
                                            Primary platform contact details.
                                        </span>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <label class="field">
                                        <span>Contact Email</span>
                                        <small>Primary contact email.</small>
                                        <input
                                            :value="
                                                stringValue(
                                                    'general.contact_email',
                                                )
                                            "
                                            type="email"
                                            @input="
                                                setValue(
                                                    'general.contact_email',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field">
                                        <span>Contact Phone</span>
                                        <small>Primary contact phone.</small>
                                        <input
                                            :value="
                                                stringValue(
                                                    'general.contact_phone',
                                                )
                                            "
                                            type="text"
                                            @input="
                                                setValue(
                                                    'general.contact_phone',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>
                                </div>
                            </div>

                            <div
                                v-if="activeCategory === 'general'"
                                class="settings-card setting-group-card"
                            >
                                <div class="subsection-heading">
                                    <div>
                                        <strong>Support Channels</strong>
                                        <span>
                                            Select channels currently available
                                            to customers.
                                        </span>
                                    </div>
                                </div>

                                <div class="channel-control large">
                                    <button
                                        v-for="channel in supportChannels"
                                        :key="channel"
                                        type="button"
                                        class="channel-chip"
                                        :class="{
                                            active: arrayValue(
                                                'general.support_channels',
                                            ).includes(channel),
                                        }"
                                        @click="
                                            toggleSupportChannel(
                                                'general.support_channels',
                                                channel,
                                            )
                                        "
                                    >
                                        {{
                                            channel.charAt(0).toUpperCase() +
                                            channel.slice(1)
                                        }}
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="activeCategory === 'localization'"
                                class="settings-card setting-group-card"
                            >
                                <div class="subsection-heading">
                                    <div>
                                        <strong>Language & Time</strong>
                                        <span>
                                            Configure the application's regional
                                            behavior.
                                        </span>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <label class="field">
                                        <span>Timezone</span>
                                        <small>
                                            Default timezone used by the
                                            application.
                                        </small>

                                        <select
                                            :value="
                                                stringValue(
                                                    'localization.timezone',
                                                ) || 'Asia/Dhaka'
                                            "
                                            @change="
                                                setValue(
                                                    'localization.timezone',
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option
                                                v-for="option in timezoneOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span>Locale</span>
                                        <small>
                                            Default application language and
                                            regional locale.
                                        </small>

                                        <select
                                            :value="
                                                stringValue(
                                                    'localization.locale',
                                                ) || 'en-BD'
                                            "
                                            @change="
                                                setValue(
                                                    'localization.locale',
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option
                                                v-for="option in localeOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span>Date Format</span>
                                        <small>
                                            Default calendar date display
                                            format.
                                        </small>

                                        <select
                                            :value="
                                                stringValue(
                                                    'localization.date_format',
                                                ) || 'DD/MM/YYYY'
                                            "
                                            @change="
                                                setValue(
                                                    'localization.date_format',
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option
                                                v-for="option in dateFormatOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span>Time Format</span>
                                        <small
                                            >12-hour or 24-hour display.</small
                                        >

                                        <select
                                            :value="
                                                stringValue(
                                                    'localization.time_format',
                                                ) || '12-hour'
                                            "
                                            @change="
                                                setValue(
                                                    'localization.time_format',
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option value="12-hour">
                                                12-hour
                                            </option>
                                            <option value="24-hour">
                                                24-hour
                                            </option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span>Week Starts On</span>
                                        <small>
                                            First day used in weekly views.
                                        </small>

                                        <select
                                            :value="
                                                numberValue(
                                                    'localization.week_starts_on',
                                                    0,
                                                )
                                            "
                                            @change="
                                                setValue(
                                                    'localization.week_starts_on',
                                                    Number(
                                                        (
                                                            $event.target as HTMLSelectElement
                                                        ).value,
                                                    ),
                                                )
                                            "
                                        >
                                            <option :value="0">Sunday</option>
                                            <option :value="1">Monday</option>
                                            <option :value="2">Tuesday</option>
                                            <option :value="3">
                                                Wednesday
                                            </option>
                                            <option :value="4">Thursday</option>
                                            <option :value="5">Friday</option>
                                            <option :value="6">Saturday</option>
                                        </select>
                                    </label>
                                </div>
                            </div>
                            <div
                                v-if="activeCategory === 'currency'"
                                class="settings-card setting-group-card"
                            >
                                <div class="subsection-heading">
                                    <div>
                                        <strong
                                            >Currency & Number
                                            Formatting</strong
                                        >
                                        <span>
                                            Configure monetary display rules for
                                            the platform.
                                        </span>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <label class="field">
                                        <span>Default Currency</span>
                                        <small>
                                            Primary currency used for monetary
                                            values.
                                        </small>

                                        <select
                                            :value="
                                                stringValue(
                                                    'currency.default',
                                                ) || 'BDT'
                                            "
                                            @change="
                                                setValue(
                                                    'currency.default',
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option
                                                v-for="currency in currencyOptions"
                                                :key="currency.value"
                                                :value="currency.value"
                                            >
                                                {{ currency.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span>Currency Symbol</span>
                                        <small>
                                            Symbol displayed alongside monetary
                                            values.
                                        </small>

                                        <select
                                            :value="
                                                stringValue(
                                                    'currency.symbol',
                                                ) || '৳'
                                            "
                                            @change="
                                                setValue(
                                                    'currency.symbol',
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option
                                                v-for="option in currencySymbolOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span>Decimal Places</span>
                                        <small>
                                            Digits displayed after the decimal
                                            separator.
                                        </small>

                                        <select
                                            :value="
                                                numberValue(
                                                    'currency.decimal_places',
                                                    2,
                                                )
                                            "
                                            @change="
                                                setValue(
                                                    'currency.decimal_places',
                                                    Number(
                                                        (
                                                            $event.target as HTMLSelectElement
                                                        ).value,
                                                    ),
                                                )
                                            "
                                        >
                                            <option
                                                v-for="option in decimalPlaceOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span>Thousand Separator</span>
                                        <small>
                                            Character used to group thousands.
                                        </small>

                                        <select
                                            :value="
                                                stringValue(
                                                    'currency.thousand_separator',
                                                ) || ','
                                            "
                                            @change="
                                                setValue(
                                                    'currency.thousand_separator',
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option
                                                v-for="option in thousandSeparatorOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span>Decimal Separator</span>
                                        <small>
                                            Character used before the decimal
                                            portion.
                                        </small>

                                        <select
                                            :value="
                                                stringValue(
                                                    'currency.decimal_separator',
                                                ) || '.'
                                            "
                                            @change="
                                                setValue(
                                                    'currency.decimal_separator',
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option
                                                v-for="option in decimalSeparatorOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </label>
                                </div>
                            </div>
                            <div
                                v-if="activeCategory === 'business'"
                                class="settings-card setting-group-card"
                            >
                                <div class="subsection-heading">
                                    <div>
                                        <strong>Business Identity</strong>
                                        <span>
                                            Core legal, public and contact
                                            information used across the
                                            platform.
                                        </span>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <label class="field">
                                        <span>Legal Business Name</span>
                                        <small>
                                            Official registered or legal name of
                                            the business.
                                        </small>
                                        <input
                                            :value="
                                                stringValue(
                                                    'business.legal_name',
                                                )
                                            "
                                            type="text"
                                            maxlength="200"
                                            placeholder="e.g. Madina Scale & Co."
                                            @input="
                                                setValue(
                                                    'business.legal_name',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field">
                                        <span>Business Name</span>
                                        <small>
                                            Primary public-facing business name.
                                        </small>
                                        <input
                                            :value="
                                                stringValue('business.name')
                                            "
                                            type="text"
                                            maxlength="200"
                                            placeholder="e.g. Madina Scale"
                                            @input="
                                                setValue(
                                                    'business.name',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field">
                                        <span>Business Email</span>
                                        <small>
                                            Primary business email address.
                                        </small>
                                        <input
                                            :value="
                                                stringValue('business.email')
                                            "
                                            type="email"
                                            maxlength="254"
                                            placeholder="e.g. info@madinascale.com"
                                            @input="
                                                setValue(
                                                    'business.email',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field">
                                        <span>Business Phone</span>
                                        <small>
                                            Primary business contact number.
                                        </small>
                                        <input
                                            :value="
                                                stringValue('business.phone')
                                            "
                                            type="tel"
                                            maxlength="30"
                                            placeholder="e.g. +880 1XXX-XXXXXX"
                                            @input="
                                                setValue(
                                                    'business.phone',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field full">
                                        <span>Business Website</span>
                                        <small>
                                            Official website or primary public
                                            web address.
                                        </small>
                                        <input
                                            :value="
                                                stringValue('business.website')
                                            "
                                            type="url"
                                            maxlength="255"
                                            placeholder="https://www.example.com"
                                            @input="
                                                setValue(
                                                    'business.website',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>
                                </div>
                            </div>

                            <div
                                v-if="activeCategory === 'business'"
                                class="settings-card setting-group-card"
                            >
                                <div class="subsection-heading">
                                    <div>
                                        <strong>Location & Address</strong>
                                        <span>
                                            Define the primary registered or
                                            operating business location.
                                        </span>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <label class="field">
                                        <span>Country</span>
                                        <small>
                                            ISO 3166-1 alpha-2 country code.
                                        </small>
                                        <select
                                            :value="
                                                stringValue(
                                                    'business.country_code',
                                                ) || 'BD'
                                            "
                                            @change="
                                                setValue(
                                                    'business.country_code',
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option value="BD">
                                                Bangladesh — BD
                                            </option>
                                            <option value="IN">
                                                India — IN
                                            </option>
                                            <option value="AE">
                                                United Arab Emirates — AE
                                            </option>
                                            <option value="SA">
                                                Saudi Arabia — SA
                                            </option>
                                            <option value="SG">
                                                Singapore — SG
                                            </option>
                                            <option value="MY">
                                                Malaysia — MY
                                            </option>
                                            <option value="GB">
                                                United Kingdom — GB
                                            </option>
                                            <option value="US">
                                                United States — US
                                            </option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span>Division / State / Region</span>
                                        <small>
                                            Primary administrative region.
                                        </small>
                                        <input
                                            :value="
                                                stringValue('business.division')
                                            "
                                            type="text"
                                            maxlength="120"
                                            list="madina-business-divisions"
                                            placeholder="e.g. Dhaka"
                                            @input="
                                                setValue(
                                                    'business.division',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field">
                                        <span>District / City</span>
                                        <small>
                                            Primary district, city or
                                            administrative area.
                                        </small>
                                        <input
                                            :value="
                                                stringValue('business.district')
                                            "
                                            type="text"
                                            maxlength="120"
                                            list="madina-business-districts"
                                            placeholder="e.g. Dhaka"
                                            @input="
                                                setValue(
                                                    'business.district',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field">
                                        <span>Postal Code</span>
                                        <small>
                                            Postal or ZIP code for the primary
                                            address.
                                        </small>
                                        <input
                                            :value="
                                                stringValue(
                                                    'business.postal_code',
                                                )
                                            "
                                            type="text"
                                            maxlength="20"
                                            inputmode="numeric"
                                            placeholder="e.g. 1205"
                                            @input="
                                                setValue(
                                                    'business.postal_code',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field full">
                                        <span
                                            >Registered / Operating
                                            Address</span
                                        >
                                        <small>
                                            Full registered office, headquarters
                                            or primary operating address.
                                        </small>
                                        <textarea
                                            :value="
                                                stringValue('business.address')
                                            "
                                            rows="4"
                                            maxlength="1000"
                                            placeholder="House / Road, Area, District, Division, Country"
                                            @input="
                                                setValue(
                                                    'business.address',
                                                    (
                                                        $event.target as HTMLTextAreaElement
                                                    ).value,
                                                )
                                            "
                                        ></textarea>
                                    </label>
                                </div>

                                <datalist id="madina-business-divisions">
                                    <option value="Dhaka"></option>
                                    <option value="Chattogram"></option>
                                    <option value="Rajshahi"></option>
                                    <option value="Khulna"></option>
                                    <option value="Barishal"></option>
                                    <option value="Sylhet"></option>
                                    <option value="Rangpur"></option>
                                    <option value="Mymensingh"></option>
                                </datalist>

                                <datalist id="madina-business-districts">
                                    <option value="Dhaka"></option>
                                    <option value="Gazipur"></option>
                                    <option value="Narayanganj"></option>
                                    <option value="Narsingdi"></option>
                                    <option value="Tangail"></option>
                                    <option value="Chattogram"></option>
                                    <option value="Cumilla"></option>
                                    <option value="Rajshahi"></option>
                                    <option value="Khulna"></option>
                                    <option value="Sylhet"></option>
                                </datalist>
                            </div>

                            <div
                                v-if="activeCategory === 'business'"
                                class="settings-card setting-group-card"
                            >
                                <div class="subsection-heading">
                                    <div>
                                        <strong
                                            >Classification & Compliance</strong
                                        >
                                        <span>
                                            Identify the business structure and
                                            core registration information.
                                        </span>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <label class="field">
                                        <span>Business Type</span>
                                        <small>
                                            Legal or organizational structure of
                                            the business.
                                        </small>
                                        <select
                                            :value="
                                                stringValue('business.type')
                                            "
                                            @change="
                                                setValue(
                                                    'business.type',
                                                    (
                                                        $event.target as HTMLSelectElement
                                                    ).value,
                                                )
                                            "
                                        >
                                            <option value="">
                                                Select business type
                                            </option>
                                            <option value="sole_proprietorship">
                                                Sole Proprietorship
                                            </option>
                                            <option value="partnership">
                                                Partnership
                                            </option>
                                            <option value="private_limited">
                                                Private Limited
                                            </option>
                                            <option value="public_limited">
                                                Public Limited
                                            </option>
                                            <option value="llc">
                                                Limited Liability Company
                                            </option>
                                            <option value="ngo">
                                                NGO / Non-Profit
                                            </option>
                                            <option value="government">
                                                Government / Public Organization
                                            </option>
                                            <option value="other">Other</option>
                                        </select>
                                    </label>

                                    <label class="field">
                                        <span>Industry</span>
                                        <small>
                                            Primary industry or operating
                                            sector.
                                        </small>
                                        <input
                                            :value="
                                                stringValue('business.industry')
                                            "
                                            type="text"
                                            maxlength="120"
                                            list="madina-business-industries"
                                            placeholder="e.g. Retail & E-commerce"
                                            @input="
                                                setValue(
                                                    'business.industry',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field">
                                        <span>Registration Number</span>
                                        <small>
                                            Government or authority-issued
                                            business registration number.
                                        </small>
                                        <input
                                            :value="
                                                stringValue(
                                                    'business.registration_number',
                                                )
                                            "
                                            type="text"
                                            maxlength="100"
                                            placeholder="Business registration number"
                                            @input="
                                                setValue(
                                                    'business.registration_number',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field">
                                        <span>BIN</span>
                                        <small>
                                            Business Identification Number / VAT
                                            registration.
                                        </small>
                                        <input
                                            :value="stringValue('business.bin')"
                                            type="text"
                                            maxlength="50"
                                            inputmode="numeric"
                                            placeholder="BIN / VAT number"
                                            @input="
                                                setValue(
                                                    'business.bin',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <label class="field">
                                        <span>TIN</span>
                                        <small>
                                            Tax Identification Number.
                                        </small>
                                        <input
                                            :value="stringValue('business.tin')"
                                            type="text"
                                            maxlength="50"
                                            inputmode="numeric"
                                            placeholder="TIN"
                                            @input="
                                                setValue(
                                                    'business.tin',
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).value,
                                                )
                                            "
                                        />
                                    </label>

                                    <div class="field field-info">
                                        <span>Compliance Readiness</span>
                                        <small>
                                            Keep official registration and tax
                                            identifiers here for future
                                            invoicing, taxation and reporting
                                            workflows.
                                        </small>

                                        <div class="business-compliance-note">
                                            <Icon name="security" :size="14" />
                                            <span>
                                                Sensitive compliance identifiers
                                                are protected by the existing
                                                settings authorization layer.
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <datalist id="madina-business-industries">
                                    <option
                                        value="Retail & E-commerce"
                                    ></option>
                                    <option
                                        value="Wholesale & Distribution"
                                    ></option>
                                    <option value="Manufacturing"></option>
                                    <option value="Import & Export"></option>
                                    <option
                                        value="Information Technology"
                                    ></option>
                                    <option value="Software & SaaS"></option>
                                    <option
                                        value="Professional Services"
                                    ></option>
                                    <option
                                        value="Logistics & Transportation"
                                    ></option>
                                    <option value="Food & Beverage"></option>
                                    <option value="Healthcare"></option>
                                    <option value="Education"></option>
                                    <option value="Construction"></option>
                                    <option value="Real Estate"></option>
                                    <option value="Other"></option>
                                </datalist>
                            </div>

                            <div
                                v-if="activeCategory === 'business'"
                                class="settings-card setting-group-card"
                            >
                                <div class="subsection-heading">
                                    <div>
                                        <strong>Business Hours</strong>
                                        <span>
                                            Configure the weekly operating
                                            schedule for the business.
                                        </span>
                                    </div>
                                </div>

                                <div class="hours-list">
                                    <div
                                        v-for="day in dayDefinitions"
                                        :key="day.key"
                                        class="hours-row"
                                    >
                                        <div class="day-copy">
                                            <strong>{{ day.label }}</strong>
                                            <small>
                                                {{
                                                    businessHour(day.key)
                                                        .enabled
                                                        ? 'Open'
                                                        : 'Closed'
                                                }}
                                            </small>
                                        </div>

                                        <label class="switch-control compact">
                                            <input
                                                :checked="
                                                    businessHour(day.key)
                                                        .enabled
                                                "
                                                type="checkbox"
                                                :aria-label="`${day.label} enabled`"
                                                @change="
                                                    updateBusinessHour(
                                                        day.key,
                                                        'enabled',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).checked,
                                                    )
                                                "
                                            />
                                            <span class="switch-track">
                                                <span
                                                    class="switch-thumb"
                                                ></span>
                                            </span>
                                        </label>

                                        <div class="time-controls">
                                            <input
                                                :value="
                                                    businessHour(day.key).open
                                                "
                                                type="time"
                                                :disabled="
                                                    !businessHour(day.key)
                                                        .enabled
                                                "
                                                :aria-label="`${day.label} opening time`"
                                                @input="
                                                    updateBusinessHour(
                                                        day.key,
                                                        'open',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                                            />

                                            <span>to</span>

                                            <input
                                                :value="
                                                    businessHour(day.key).close
                                                "
                                                type="time"
                                                :disabled="
                                                    !businessHour(day.key)
                                                        .enabled
                                                "
                                                :aria-label="`${day.label} closing time`"
                                                @input="
                                                    updateBusinessHour(
                                                        day.key,
                                                        'close',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="activeCategory === 'security'"
                                class="security-section"
                            >
                                <div
                                    class="settings-card setting-group-card security-policy-card"
                                >
                                    <div class="security-section-heading">
                                        <div
                                            class="security-section-heading-copy"
                                        >
                                            <div class="security-section-icon">
                                                <Icon
                                                    name="security"
                                                    :size="17"
                                                />
                                            </div>

                                            <div>
                                                <strong
                                                    >Login Protection</strong
                                                >
                                                <span>
                                                    Configure account lockout
                                                    and session protection
                                                    policies.
                                                </span>
                                            </div>
                                        </div>

                                        <div class="security-status-badge">
                                            <span
                                                class="security-status-dot"
                                            ></span>
                                            <span>Protected</span>
                                        </div>
                                    </div>

                                    <div class="security-policy-grid">
                                        <div class="security-policy-field">
                                            <label
                                                for="security-login-max-attempts"
                                            >
                                                Maximum Login Attempts
                                            </label>

                                            <div class="security-input-wrapper">
                                                <input
                                                    id="security-login-max-attempts"
                                                    :value="
                                                        numberValue(
                                                            'security.login_max_attempts',
                                                            5,
                                                        )
                                                    "
                                                    type="number"
                                                    min="1"
                                                    max="20"
                                                    step="1"
                                                    @input="
                                                        setValue(
                                                            'security.login_max_attempts',
                                                            Number(
                                                                (
                                                                    $event.target as HTMLInputElement
                                                                ).value,
                                                            ),
                                                        )
                                                    "
                                                />

                                                <span>attempts</span>
                                            </div>

                                            <small>
                                                Failed attempts allowed before
                                                temporary lockout.
                                            </small>
                                        </div>

                                        <div class="security-policy-field">
                                            <label
                                                for="security-lockout-duration"
                                            >
                                                Lockout Duration
                                            </label>

                                            <div class="security-input-wrapper">
                                                <input
                                                    id="security-lockout-duration"
                                                    :value="
                                                        numberValue(
                                                            'security.lockout_duration_minutes',
                                                            15,
                                                        )
                                                    "
                                                    type="number"
                                                    min="1"
                                                    max="1440"
                                                    step="1"
                                                    @input="
                                                        setValue(
                                                            'security.lockout_duration_minutes',
                                                            Number(
                                                                (
                                                                    $event.target as HTMLInputElement
                                                                ).value,
                                                            ),
                                                        )
                                                    "
                                                />

                                                <span>minutes</span>
                                            </div>

                                            <small>
                                                Duration of the temporary
                                                account lockout.
                                            </small>
                                        </div>

                                        <div class="security-policy-field">
                                            <label
                                                for="security-session-timeout"
                                            >
                                                Session Timeout
                                            </label>

                                            <div class="security-input-wrapper">
                                                <input
                                                    id="security-session-timeout"
                                                    :value="
                                                        numberValue(
                                                            'security.session_timeout_minutes',
                                                            120,
                                                        )
                                                    "
                                                    type="number"
                                                    min="5"
                                                    max="10080"
                                                    step="5"
                                                    @input="
                                                        setValue(
                                                            'security.session_timeout_minutes',
                                                            Number(
                                                                (
                                                                    $event.target as HTMLInputElement
                                                                ).value,
                                                            ),
                                                        )
                                                    "
                                                />

                                                <span>minutes</span>
                                            </div>

                                            <small>
                                                Maximum inactive session
                                                lifetime.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="security-policy-note">
                                        <Icon name="activity" :size="14" />

                                        <div>
                                            <strong>Account protection</strong>
                                            <span>
                                                These controls define the
                                                baseline protection applied to
                                                authentication attempts and
                                                inactive sessions.
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="settings-card setting-group-card security-policy-card"
                                >
                                    <div class="security-section-heading">
                                        <div
                                            class="security-section-heading-copy"
                                        >
                                            <div class="security-section-icon">
                                                <Icon
                                                    name="security"
                                                    :size="17"
                                                />
                                            </div>

                                            <div>
                                                <strong>Password Policy</strong>
                                                <span>
                                                    Establish baseline password
                                                    complexity and lifecycle
                                                    rules.
                                                </span>
                                            </div>
                                        </div>

                                        <div class="security-status-badge">
                                            <span
                                                class="security-status-dot"
                                            ></span>
                                            <span>Policy</span>
                                        </div>
                                    </div>

                                    <div class="security-password-grid">
                                        <div class="security-policy-field">
                                            <label
                                                for="security-password-min-length"
                                            >
                                                Minimum Password Length
                                            </label>

                                            <div class="security-input-wrapper">
                                                <input
                                                    id="security-password-min-length"
                                                    :value="
                                                        numberValue(
                                                            'security.password_min_length',
                                                            12,
                                                        )
                                                    "
                                                    type="number"
                                                    min="8"
                                                    max="128"
                                                    step="1"
                                                    @input="
                                                        setValue(
                                                            'security.password_min_length',
                                                            Number(
                                                                (
                                                                    $event.target as HTMLInputElement
                                                                ).value,
                                                            ),
                                                        )
                                                    "
                                                />

                                                <span>characters</span>
                                            </div>

                                            <small>
                                                Minimum number of password
                                                characters.
                                            </small>
                                        </div>

                                        <div class="security-policy-field">
                                            <label
                                                for="security-password-expiry"
                                            >
                                                Password Expiry
                                            </label>

                                            <div class="security-input-wrapper">
                                                <input
                                                    id="security-password-expiry"
                                                    :value="
                                                        numberValue(
                                                            'security.password_expiry_days',
                                                            0,
                                                        )
                                                    "
                                                    type="number"
                                                    min="0"
                                                    max="3650"
                                                    step="1"
                                                    @input="
                                                        setValue(
                                                            'security.password_expiry_days',
                                                            Number(
                                                                (
                                                                    $event.target as HTMLInputElement
                                                                ).value,
                                                            ),
                                                        )
                                                    "
                                                />

                                                <span>days</span>
                                            </div>

                                            <small>
                                                Use 0 to keep password expiry
                                                disabled.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="security-control-list">
                                        <div class="security-control-row">
                                            <div class="security-control-info">
                                                <div
                                                    class="security-control-icon"
                                                >
                                                    <Icon
                                                        name="security"
                                                        :size="15"
                                                    />
                                                </div>

                                                <div>
                                                    <strong
                                                        >Require Mixed
                                                        Case</strong
                                                    >
                                                    <span>
                                                        Require both uppercase
                                                        and lowercase
                                                        characters.
                                                    </span>
                                                    <small
                                                        >security.password_require_mixed_case</small
                                                    >
                                                </div>
                                            </div>

                                            <label class="security-switch">
                                                <input
                                                    :checked="
                                                        booleanValue(
                                                            'security.password_require_mixed_case',
                                                        )
                                                    "
                                                    type="checkbox"
                                                    @change="
                                                        setValue(
                                                            'security.password_require_mixed_case',
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).checked,
                                                        )
                                                    "
                                                />

                                                <span
                                                    class="security-switch-track"
                                                >
                                                    <span
                                                        class="security-switch-thumb"
                                                    ></span>
                                                </span>

                                                <span
                                                    class="security-switch-text"
                                                >
                                                    {{
                                                        booleanValue(
                                                            'security.password_require_mixed_case',
                                                        )
                                                            ? 'Required'
                                                            : 'Optional'
                                                    }}
                                                </span>
                                            </label>
                                        </div>

                                        <div class="security-control-row">
                                            <div class="security-control-info">
                                                <div
                                                    class="security-control-icon"
                                                >
                                                    <Icon
                                                        name="security"
                                                        :size="15"
                                                    />
                                                </div>

                                                <div>
                                                    <strong
                                                        >Require Numbers</strong
                                                    >
                                                    <span>
                                                        Require at least one
                                                        numeric character.
                                                    </span>
                                                    <small
                                                        >security.password_require_numbers</small
                                                    >
                                                </div>
                                            </div>

                                            <label class="security-switch">
                                                <input
                                                    :checked="
                                                        booleanValue(
                                                            'security.password_require_numbers',
                                                        )
                                                    "
                                                    type="checkbox"
                                                    @change="
                                                        setValue(
                                                            'security.password_require_numbers',
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).checked,
                                                        )
                                                    "
                                                />

                                                <span
                                                    class="security-switch-track"
                                                >
                                                    <span
                                                        class="security-switch-thumb"
                                                    ></span>
                                                </span>

                                                <span
                                                    class="security-switch-text"
                                                >
                                                    {{
                                                        booleanValue(
                                                            'security.password_require_numbers',
                                                        )
                                                            ? 'Required'
                                                            : 'Optional'
                                                    }}
                                                </span>
                                            </label>
                                        </div>

                                        <div class="security-control-row">
                                            <div class="security-control-info">
                                                <div
                                                    class="security-control-icon"
                                                >
                                                    <Icon
                                                        name="security"
                                                        :size="15"
                                                    />
                                                </div>

                                                <div>
                                                    <strong
                                                        >Require Symbols</strong
                                                    >
                                                    <span>
                                                        Require at least one
                                                        symbol character.
                                                    </span>
                                                    <small
                                                        >security.password_require_symbols</small
                                                    >
                                                </div>
                                            </div>

                                            <label class="security-switch">
                                                <input
                                                    :checked="
                                                        booleanValue(
                                                            'security.password_require_symbols',
                                                        )
                                                    "
                                                    type="checkbox"
                                                    @change="
                                                        setValue(
                                                            'security.password_require_symbols',
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).checked,
                                                        )
                                                    "
                                                />

                                                <span
                                                    class="security-switch-track"
                                                >
                                                    <span
                                                        class="security-switch-thumb"
                                                    ></span>
                                                </span>

                                                <span
                                                    class="security-switch-text"
                                                >
                                                    {{
                                                        booleanValue(
                                                            'security.password_require_symbols',
                                                        )
                                                            ? 'Required'
                                                            : 'Optional'
                                                    }}
                                                </span>
                                            </label>
                                        </div>

                                        <div class="security-control-row">
                                            <div class="security-control-info">
                                                <div
                                                    class="security-control-icon"
                                                >
                                                    <Icon
                                                        name="security"
                                                        :size="15"
                                                    />
                                                </div>

                                                <div>
                                                    <strong
                                                        >Force Password
                                                        Change</strong
                                                    >
                                                    <span>
                                                        Require users to change
                                                        their password when
                                                        enabled.
                                                    </span>
                                                    <small
                                                        >security.force_password_change</small
                                                    >
                                                </div>
                                            </div>

                                            <label class="security-switch">
                                                <input
                                                    :checked="
                                                        booleanValue(
                                                            'security.force_password_change',
                                                        )
                                                    "
                                                    type="checkbox"
                                                    @change="
                                                        setValue(
                                                            'security.force_password_change',
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).checked,
                                                        )
                                                    "
                                                />

                                                <span
                                                    class="security-switch-track"
                                                >
                                                    <span
                                                        class="security-switch-thumb"
                                                    ></span>
                                                </span>

                                                <span
                                                    class="security-switch-text"
                                                >
                                                    {{
                                                        booleanValue(
                                                            'security.force_password_change',
                                                        )
                                                            ? 'Enabled'
                                                            : 'Disabled'
                                                    }}
                                                </span>
                                            </label>
                                        </div>

                                        <div class="security-control-row">
                                            <div class="security-control-info">
                                                <div
                                                    class="security-control-icon"
                                                >
                                                    <Icon
                                                        name="security"
                                                        :size="15"
                                                    />
                                                </div>

                                                <div>
                                                    <strong
                                                        >Require Email
                                                        Verification</strong
                                                    >
                                                    <span>
                                                        Require verified email
                                                        addresses for normal
                                                        access.
                                                    </span>
                                                    <small
                                                        >security.require_email_verification</small
                                                    >
                                                </div>
                                            </div>

                                            <label class="security-switch">
                                                <input
                                                    :checked="
                                                        booleanValue(
                                                            'security.require_email_verification',
                                                        )
                                                    "
                                                    type="checkbox"
                                                    @change="
                                                        setValue(
                                                            'security.require_email_verification',
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).checked,
                                                        )
                                                    "
                                                />

                                                <span
                                                    class="security-switch-track"
                                                >
                                                    <span
                                                        class="security-switch-thumb"
                                                    ></span>
                                                </span>

                                                <span
                                                    class="security-switch-text"
                                                >
                                                    {{
                                                        booleanValue(
                                                            'security.require_email_verification',
                                                        )
                                                            ? 'Required'
                                                            : 'Optional'
                                                    }}
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="activeCategory === 'system'"
                                class="settings-card setting-group-card"
                            >
                                <div class="subsection-heading">
                                    <div>
                                        <strong>Platform Behaviour</strong>
                                        <span>
                                            Core operational controls for the
                                            application.
                                        </span>
                                    </div>
                                </div>

                                <div class="system-setting-list">
                                    <div class="system-setting-row">
                                        <div class="setting-copy">
                                            <div class="setting-icon">
                                                <Icon
                                                    name="developer"
                                                    :size="15"
                                                />
                                            </div>
                                            <div>
                                                <strong
                                                    >Maintenance Mode</strong
                                                >
                                                <span>
                                                    Temporarily place the
                                                    platform into maintenance
                                                    mode.
                                                </span>
                                                <small>
                                                    system.maintenance_mode
                                                </small>
                                            </div>
                                        </div>

                                        <label class="switch-control">
                                            <input
                                                :checked="
                                                    booleanValue(
                                                        'system.maintenance_mode',
                                                    )
                                                "
                                                type="checkbox"
                                                aria-label="Maintenance Mode"
                                                @change="
                                                    setValue(
                                                        'system.maintenance_mode',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).checked,
                                                    )
                                                "
                                            />
                                            <span class="switch-track">
                                                <span
                                                    class="switch-thumb"
                                                ></span>
                                            </span>
                                            <span class="switch-label">
                                                {{
                                                    booleanValue(
                                                        'system.maintenance_mode',
                                                    )
                                                        ? 'Enabled'
                                                        : 'Disabled'
                                                }}
                                            </span>
                                        </label>
                                    </div>

                                    <div class="system-setting-row">
                                        <div class="setting-copy">
                                            <div class="setting-icon">
                                                <Icon
                                                    name="settings"
                                                    :size="15"
                                                />
                                            </div>
                                            <div>
                                                <strong>Items Per Page</strong>
                                                <span>
                                                    Default number of records
                                                    shown in paginated lists.
                                                </span>
                                                <small>
                                                    system.items_per_page
                                                </small>
                                            </div>
                                        </div>

                                        <div class="number-control">
                                            <input
                                                :value="
                                                    numberValue(
                                                        'system.items_per_page',
                                                        25,
                                                    )
                                                "
                                                type="number"
                                                min="10"
                                                max="100"
                                                step="5"
                                                aria-label="Items Per Page"
                                                @input="
                                                    setValue(
                                                        'system.items_per_page',
                                                        Number(
                                                            (
                                                                $event.target as HTMLInputElement
                                                            ).value,
                                                        ),
                                                    )
                                                "
                                            />
                                            <span>per page</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="activeCategory === 'system'"
                                class="settings-card setting-group-card"
                            >
                                <div class="subsection-heading">
                                    <div>
                                        <strong>System Support Channels</strong>
                                        <span>
                                            Legacy system-level support
                                            configuration.
                                        </span>
                                    </div>
                                </div>

                                <div class="channel-control large">
                                    <button
                                        v-for="channel in supportChannels"
                                        :key="channel"
                                        type="button"
                                        class="channel-chip"
                                        :class="{
                                            active: arrayValue(
                                                'system.support_channels',
                                            ).includes(channel),
                                        }"
                                        @click="
                                            toggleSupportChannel(
                                                'system.support_channels',
                                                channel,
                                            )
                                        "
                                    >
                                        {{
                                            channel.charAt(0).toUpperCase() +
                                            channel.slice(1)
                                        }}
                                    </button>
                                </div>
                            </div>
                        </section>
                    </template>

                    <footer class="save-bar">
                        <div>
                            <span
                                class="save-status-dot"
                                :class="{ dirty: hasChanges }"
                            ></span>

                            <div>
                                <strong>
                                    {{
                                        hasChanges
                                            ? `${changedCount} unsaved ${
                                                  changedCount === 1
                                                      ? 'change'
                                                      : 'changes'
                                              }`
                                            : 'All changes saved'
                                    }}
                                </strong>
                                <small>
                                    {{
                                        hasChanges
                                            ? 'Review your changes before saving.'
                                            : 'Configuration is synchronized with the server.'
                                    }}
                                </small>
                            </div>
                        </div>

                        <div class="save-actions">
                            <button
                                class="btn secondary"
                                type="button"
                                :disabled="!hasChanges || isSaving"
                                @click="resetChanges"
                            >
                                Reset
                            </button>

                            <button
                                class="btn primary"
                                type="button"
                                :disabled="!hasChanges || isSaving"
                                @click="saveSettings"
                            >
                                <Icon name="settings" :size="13" />
                                {{ isSaving ? 'Saving...' : 'Save Changes' }}
                            </button>
                        </div>
                    </footer>

                    <footer class="access-footer">
                        <div>
                            <span class="footer-status-dot"></span>
                            Authorization is enforced server-side
                        </div>

                        <span>
                            Settings changes are recorded in Activity Logs.
                        </span>
                    </footer>
                </main>
            </div>
        </div>
    </ErpLayout>
</template>

<style scoped>
.settings-page {
    --settings-radius: 14px;
    --settings-radius-sm: 10px;

    width: 100%;
    min-width: 0;
    min-height: 100%;
    box-sizing: border-box;
    color: var(--text-primary);
}

.page-header {
    display: flex;
    align-items: flex-end;
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
    gap: 7px;
    margin-bottom: 8px;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 600;
}

.breadcrumb strong {
    color: var(--text-secondary);
}

.breadcrumb-separator {
    opacity: 0.55;
}

.heading-row {
    display: flex;
    align-items: center;
    gap: 11px;
}

.heading-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    flex: 0 0 auto;
    border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));
    border-radius: 10px;
    background: color-mix(in srgb, var(--primary) 7%, var(--surface));
    color: var(--primary);
}

.heading-row h1 {
    margin: 0;
    color: var(--text-primary);
    font-size: 20px;
    font-weight: 760;
    letter-spacing: -0.025em;
}

.heading-row p {
    max-width: 620px;
    margin: 4px 0 0;
    color: var(--text-secondary);
    font-size: 10px;
    line-height: 1.5;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-shrink: 0;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 34px;
    padding: 0 11px;
    border: 1px solid transparent;
    border-radius: 9px;
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
    transition:
        transform 160ms ease,
        border-color 160ms ease,
        background 160ms ease,
        color 160ms ease,
        opacity 160ms ease;
}

.btn:hover:not(:disabled) {
    transform: translateY(-1px);
}

.btn:disabled {
    cursor: not-allowed;
    opacity: 0.48;
}

.btn.primary {
    border-color: var(--primary);
    background: var(--primary);
    color: var(--primary-contrast, #fff);
}

.btn.secondary {
    border-color: var(--border);
    background: var(--surface);
    color: var(--text-secondary);
}

.btn.secondary:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--primary) 22%, var(--border));
    color: var(--text-primary);
}

.operation-message {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 12px;
    padding: 10px 11px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface);
}

.operation-message.success {
    border-color: color-mix(in srgb, var(--success) 28%, var(--border));
}

.operation-message.error {
    border-color: color-mix(in srgb, var(--danger) 28%, var(--border));
}

.message-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 27px;
    height: 27px;
    flex: 0 0 auto;
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary) 8%, var(--surface));
    color: var(--primary);
}

.operation-message.success .message-icon {
    color: var(--success);
}

.operation-message.error .message-icon {
    color: var(--danger);
}

.operation-message strong,
.operation-message span {
    display: block;
}

.operation-message strong {
    font-size: 10px;
    font-weight: 700;
}

.operation-message span {
    margin-top: 2px;
    color: var(--text-secondary);
    font-size: 9px;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 9px;
    margin-bottom: 14px;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
    min-height: 68px;
    padding: 10px 11px;
    border: 1px solid var(--border);
    border-radius: var(--settings-radius-sm);
    background: var(--surface);
    box-shadow: var(--shadow-xs, 0 1px 2px rgb(0 0 0 / 3%));
}

.stat-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 29px;
    height: 29px;
    flex: 0 0 auto;
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary) 8%, var(--surface));
    color: var(--primary);
}

.stat-card span,
.stat-card strong {
    display: block;
}

.stat-card span {
    color: var(--text-muted);
    font-size: 8px;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: 0.045em;
}

.stat-card strong {
    margin-top: 3px;
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 750;
}

.settings-layout {
    display: grid;
    grid-template-columns: 218px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
}

.settings-sidebar {
    position: sticky;
    top: 16px;
    min-width: 0;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: var(--settings-radius);
    background: var(--surface);
    box-shadow: var(--shadow-xs, 0 1px 2px rgb(0 0 0 / 3%));
}

.sidebar-heading {
    padding: 4px 5px 10px;
}

.sidebar-heading strong,
.sidebar-heading small {
    display: block;
}

.sidebar-heading strong {
    margin-top: 3px;
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 750;
}

.sidebar-heading small {
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 8px;
    line-height: 1.4;
}

.category-nav {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.category-item {
    display: grid;
    grid-template-columns: 29px minmax(0, 1fr) auto;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-width: 0;
    padding: 8px 7px;
    border: 1px solid transparent;
    border-radius: 9px;
    background: transparent;
    color: var(--text-secondary);
    text-align: left;
    cursor: pointer;
    transition:
        background 160ms ease,
        border-color 160ms ease,
        color 160ms ease,
        transform 160ms ease;
}

.category-item:hover {
    border-color: var(--border);
    background: color-mix(in srgb, var(--primary) 4%, var(--surface));
}

.category-item.active {
    border-color: color-mix(in srgb, var(--primary) 18%, var(--border));
    background: color-mix(in srgb, var(--primary) 8%, var(--surface));
    color: var(--primary);
}

.category-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 29px;
    height: 29px;
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary) 6%, var(--surface));
}

.category-copy {
    min-width: 0;
}

.category-copy strong,
.category-copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.category-copy strong {
    color: inherit;
    font-size: 9px;
    font-weight: 720;
}

.category-copy small {
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 7px;
    line-height: 1.35;
}

.category-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 19px;
    height: 19px;
    padding: 0 4px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--primary) 7%, var(--surface));
    color: var(--text-muted);
    font-size: 7px;
    font-weight: 750;
}

.category-item.active .category-count {
    background: color-mix(in srgb, var(--primary) 12%, var(--surface));
    color: var(--primary);
}

.sidebar-security {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    margin: 9px 2px 1px;
    padding: 8px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: color-mix(in srgb, var(--primary) 3%, var(--surface));
}

.security-dot {
    width: 6px;
    height: 6px;
    margin-top: 4px;
    flex: 0 0 auto;
    border-radius: 50%;
    background: var(--success);
}

.sidebar-security strong,
.sidebar-security small {
    display: block;
}

.sidebar-security strong {
    color: var(--text-primary);
    font-size: 8px;
    font-weight: 700;
}

.sidebar-security small {
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 7px;
    line-height: 1.4;
}

.settings-content {
    min-width: 0;
}

.content-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 9px;
}

.section-kicker {
    display: block;
    color: var(--primary);
    font-size: 8px;
    font-weight: 750;
    letter-spacing: 0.09em;
}

.content-heading h2 {
    margin: 3px 0 0;
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 750;
}

.content-heading p {
    max-width: 700px;
    margin: 3px 0 0;
    color: var(--text-secondary);
    font-size: 9px;
    line-height: 1.45;
}

.section-badge {
    flex: 0 0 auto;
    padding: 5px 8px;
    border: 1px solid var(--border);
    border-radius: 999px;
    color: var(--text-muted);
    background: var(--surface);
    font-size: 8px;
    font-weight: 700;
}

.security-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.security-policy-card {
    overflow: hidden;
}

.security-section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 13px 14px 10px;
    border-bottom: 1px solid var(--border);
}

.security-section-heading-copy {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.security-section-icon {
    width: 29px;
    height: 29px;
    flex: 0 0 29px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary) 5%, var(--surface));
    color: var(--primary);
}

.security-section-heading-copy strong {
    display: block;
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 700;
    line-height: 1.35;
}

.security-section-heading-copy span {
    display: block;
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 8px;
    line-height: 1.4;
}

.security-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    flex: 0 0 auto;
    padding: 4px 8px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-muted);
    font-size: 8px;
    font-weight: 700;
    white-space: nowrap;
}

.security-status-dot {
    width: 5px;
    height: 5px;
    flex: 0 0 5px;
    border-radius: 50%;
    background: var(--success, #16a34a);
    box-shadow: 0 0 0 2px rgb(22 163 74 / 8%);
}

.security-policy-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1px;
    background: var(--border);
}

.security-password-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1px;
    background: var(--border);
}

.security-policy-field {
    min-width: 0;
    padding: 13px;
    background: var(--surface);
}

.security-policy-field > label {
    display: block;
    margin-bottom: 4px;
    color: var(--text-primary);
    font-size: 9px;
    font-weight: 700;
    line-height: 1.35;
}

.security-input-wrapper {
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 34px;
    padding: 0 9px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    transition:
        border-color 160ms ease,
        box-shadow 160ms ease;
}

.security-input-wrapper:focus-within {
    border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 9%, transparent);
}

.security-input-wrapper input {
    width: 100%;
    min-width: 0;
    min-height: 32px;
    padding: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text-primary);
    font-family: inherit;
    font-size: 9px;
    font-weight: 600;
}

.security-input-wrapper span {
    flex: 0 0 auto;
    color: var(--text-muted);
    font-size: 8px;
    font-weight: 650;
    white-space: nowrap;
}

.security-policy-field small {
    display: block;
    min-height: 20px;
    margin-top: 4px;
    color: var(--text-muted);
    font-size: 7px;
    line-height: 1.4;
}

.security-policy-note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin: 10px 13px 13px;
    padding: 9px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary) 3%, var(--surface));
    color: var(--text-muted);
}

.security-policy-note > svg {
    flex: 0 0 auto;
    margin-top: 1px;
    color: var(--primary);
}

.security-policy-note strong {
    display: block;
    margin-bottom: 2px;
    color: var(--text-primary);
    font-size: 9px;
    font-weight: 700;
    line-height: 1.35;
}

.security-policy-note span {
    display: block;
    font-size: 7px;
    line-height: 1.45;
}

.security-control-list {
    display: flex;
    flex-direction: column;
    margin: 10px 13px 13px;
    border: 1px solid var(--border);
    border-radius: 9px;
    overflow: hidden;
}

.security-control-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    min-height: 58px;
    padding: 10px 12px;
    background: var(--surface);
}

.security-control-row + .security-control-row {
    border-top: 1px solid var(--border);
}

.security-control-info {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.security-control-icon {
    width: 29px;
    height: 29px;
    flex: 0 0 29px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary) 5%, var(--surface));
    color: var(--primary);
}

.security-control-info > div:last-child {
    min-width: 0;
}

.security-control-info strong {
    display: block;
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 700;
    line-height: 1.35;
}

.security-control-info span {
    display: block;
    margin-top: 2px;
    color: var(--text-secondary);
    font-size: 9px;
    line-height: 1.4;
}

.security-control-info small {
    display: block;
    margin-top: 3px;
    color: var(--text-muted);
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 7px;
    line-height: 1.35;
}

.security-switch {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    flex: 0 0 auto;
    cursor: pointer;
    user-select: none;
}

.security-switch input {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.security-switch-track {
    position: relative;
    width: 38px;
    height: 21px;
    flex: 0 0 38px;
    border-radius: 999px;
    background: var(--border);
    transition: background 160ms ease;
}

.security-switch-thumb {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: var(--surface);
    box-shadow: 0 1px 3px rgb(0 0 0 / 18%);
    transition: transform 160ms ease;
}

.security-switch input:checked + .security-switch-track {
    background: var(--primary);
}

.security-switch input:checked + .security-switch-track .security-switch-thumb {
    transform: translateX(17px);
}

.security-switch input:focus-visible + .security-switch-track {
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 9%, transparent);
}

.security-switch-text {
    min-width: 52px;
    color: var(--text-secondary);
    font-size: 9px;
    font-weight: 650;
}

@media (max-width: 1100px) {
    .security-policy-grid,
    .security-password-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .security-section-heading {
        align-items: flex-start;
        flex-direction: column;
        padding: 13px 14px 10px;
    }

    .security-control-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .security-switch {
        align-self: flex-end;
    }
}
.settings-card {
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: var(--settings-radius);
    background: var(--surface);
    box-shadow: var(--shadow-xs, 0 1px 2px rgb(0 0 0 / 3%));
}

.loading-card {
    min-height: 260px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.loading-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
}

.loading-spinner {
    width: 22px;
    height: 22px;
    border: 2px solid var(--border);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 800ms linear infinite;
}

.loading-indicator strong,
.loading-indicator span {
    display: block;
}

.loading-indicator strong {
    color: var(--text-primary);
    font-size: 10px;
}

.loading-indicator span {
    margin-top: 2px;
    color: var(--text-secondary);
    font-size: 8px;
}

.setting-group-card {
    margin-bottom: 10px;
}

.subsection-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 13px 14px 10px;
    border-bottom: 1px solid var(--border);
}

.subsection-heading strong,
.subsection-heading span {
    display: block;
}

.subsection-heading strong {
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 720;
}

.subsection-heading span {
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 8px;
    line-height: 1.4;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1px;
    background: var(--border);
}

.field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    padding: 13px;
    background: var(--surface);
}

.field.full {
    grid-column: 1 / -1;
}

.field > span {
    color: var(--text-primary);
    font-size: 9px;
    font-weight: 700;
}

.field > small {
    min-height: 20px;
    color: var(--text-muted);
    font-size: 7px;
    line-height: 1.4;
}

.field-info {
    justify-content: flex-start;
}

.business-compliance-note {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    margin-top: 2px;
    padding: 9px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface-muted, var(--surface));
    color: var(--text-secondary);
    font-size: 8px;
    font-weight: 500;
    line-height: 1.45;
}

.business-compliance-note svg {
    flex: 0 0 auto;
    margin-top: 1px;
    color: var(--text-secondary);
}

.business-compliance-note span {
    min-width: 0;
    color: var(--text-secondary);
    font-size: 8px;
    font-weight: 500;
    line-height: 1.45;
}

.field input,
.field textarea,
.field select,
.time-controls input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid var(--border);
    border-radius: 8px;
    outline: none;
    background: var(--surface);
    color: var(--text-primary);
    font-family: inherit;
    font-size: 9px;
    font-weight: 600;
    transition:
        border-color 160ms ease,
        box-shadow 160ms ease;
}

.field input,
.field select {
    min-height: 34px;
    padding: 0 9px;
}

.field select {
    appearance: auto;
    cursor: pointer;
}

.field select:disabled,
.time-controls input:disabled {
    cursor: not-allowed;
    opacity: 0.65;
}

.field textarea {
    padding: 8px 9px;
    resize: vertical;
    line-height: 1.5;
}

.field input:focus,
.field textarea:focus,
.field select:focus,
.time-controls input:focus {
    border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 9%, transparent);
}

.channel-control {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 6px;
    flex-wrap: wrap;
    padding: 13px;
}

.channel-control.large {
    min-height: 58px;
}

.channel-chip {
    min-height: 29px;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--surface);
    color: var(--text-secondary);
    font-size: 8px;
    font-weight: 700;
    cursor: pointer;
    transition:
        border-color 160ms ease,
        background 160ms ease,
        color 160ms ease,
        transform 160ms ease;
}

.channel-chip:hover {
    border-color: color-mix(in srgb, var(--primary) 28%, var(--border));
    transform: translateY(-1px);
}

.channel-chip.active {
    border-color: color-mix(in srgb, var(--primary) 35%, var(--border));
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
    color: var(--primary);
}

.hours-list {
    display: flex;
    flex-direction: column;
}

.hours-row {
    display: grid;
    grid-template-columns: minmax(130px, 1fr) auto minmax(230px, 0.75fr);
    align-items: center;
    gap: 14px;
    min-height: 58px;
    padding: 9px 13px;
    border-bottom: 1px solid var(--border);
}

.hours-row:last-child {
    border-bottom: 0;
}

.day-copy strong,
.day-copy small {
    display: block;
}

.day-copy strong {
    color: var(--text-primary);
    font-size: 9px;
    font-weight: 700;
}

.day-copy small {
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 7px;
}

.time-controls {
    display: flex;
    align-items: center;
    gap: 6px;
}

.time-controls input {
    min-height: 31px;
    padding: 0 7px;
}

.time-controls span {
    flex: 0 0 auto;
    color: var(--text-muted);
    font-size: 8px;
}

.switch-control {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.switch-control input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}

.switch-track {
    position: relative;
    width: 38px;
    height: 21px;
    flex: 0 0 auto;
    border-radius: 999px;
    background: var(--border);
    transition: background 160ms ease;
}

.switch-thumb {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: var(--surface);
    box-shadow: 0 1px 3px rgb(0 0 0 / 18%);
    transition: transform 160ms ease;
}

.switch-control input:checked + .switch-track {
    background: var(--primary);
}

.switch-control input:checked + .switch-track .switch-thumb {
    transform: translateX(17px);
}

.switch-label {
    color: var(--text-secondary);
    font-size: 9px;
    font-weight: 650;
}

.switch-control.compact {
    gap: 0;
}

.switch-control.compact .switch-track {
    transform: scale(0.85);
    transform-origin: center;
}

.system-setting-list {
    display: flex;
    flex-direction: column;
}

.system-setting-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    min-height: 76px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
}

.system-setting-row:last-child {
    border-bottom: 0;
}

.setting-copy {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.setting-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 29px;
    height: 29px;
    flex: 0 0 auto;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary) 5%, var(--surface));
    color: var(--primary);
}

.setting-copy > div:last-child {
    min-width: 0;
}

.setting-copy strong,
.setting-copy span,
.setting-copy small {
    display: block;
}

.setting-copy strong {
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 700;
}

.setting-copy span {
    margin-top: 2px;
    color: var(--text-secondary);
    font-size: 9px;
    line-height: 1.45;
}

.setting-copy small {
    margin-top: 3px;
    color: var(--text-muted);
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 7px;
}

.number-control {
    display: flex;
    align-items: center;
    gap: 7px;
    flex: 0 0 auto;
}

.number-control input {
    width: 82px;
    min-height: 34px;
    padding: 0 9px;
    box-sizing: border-box;
    border: 1px solid var(--border);
    border-radius: 8px;
    outline: none;
    background: var(--surface);
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 650;
}

.number-control input:focus {
    border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 9%, transparent);
}

.number-control span {
    color: var(--text-muted);
    font-size: 8px;
    font-weight: 650;
}

.overview-panel,
.category-panel {
    min-width: 0;
}

.overview-hero {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 10px;
    padding: 13px;
    border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));
    border-radius: var(--settings-radius);
    background: color-mix(in srgb, var(--primary) 5%, var(--surface));
}

.overview-hero-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    flex: 0 0 auto;
    border-radius: 11px;
    background: color-mix(in srgb, var(--primary) 10%, var(--surface));
    color: var(--primary);
}

.overview-hero strong,
.overview-hero span {
    display: block;
}

.overview-hero strong {
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 750;
}

.overview-hero span {
    max-width: 760px;
    margin-top: 3px;
    color: var(--text-secondary);
    font-size: 8px;
    line-height: 1.5;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.category-card {
    min-width: 0;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 11px;
    background: var(--surface);
    color: var(--text-primary);
    text-align: left;
    cursor: pointer;
    box-shadow: var(--shadow-xs, 0 1px 2px rgb(0 0 0 / 3%));
    transition:
        transform 160ms ease,
        border-color 160ms ease,
        background 160ms ease;
}

.category-card:hover {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--primary) 24%, var(--border));
    background: color-mix(in srgb, var(--primary) 2%, var(--surface));
}

.category-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 9px;
}

.category-card-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: color-mix(in srgb, var(--primary) 7%, var(--surface));
    color: var(--primary);
}

.category-card > strong,
.category-card > span {
    display: block;
}

.category-card > strong {
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 720;
}

.category-card > span {
    min-height: 25px;
    margin-top: 3px;
    color: var(--text-secondary);
    font-size: 8px;
    line-height: 1.45;
}

.changed-badge {
    padding: 3px 6px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
    color: var(--primary) !important;
    font-size: 7px !important;
    font-weight: 700;
}

.category-progress {
    margin-top: 11px;
}

.progress-track {
    height: 4px;
    overflow: hidden;
    border-radius: 999px;
    background: color-mix(in srgb, var(--border) 75%, var(--surface));
}

.progress-track span {
    display: block;
    height: 100%;
    min-height: 4px;
    border-radius: inherit;
    background: var(--primary);
    transition: width 200ms ease;
}

.category-progress small {
    display: block;
    margin-top: 5px;
    color: var(--text-muted);
    font-size: 7px;
}

.overview-note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 10px;
    padding: 10px 11px;
    border: 1px dashed var(--border);
    border-radius: 10px;
    color: var(--text-muted);
}

.overview-note > svg {
    flex: 0 0 auto;
    margin-top: 1px;
    color: var(--primary);
}

.overview-note strong,
.overview-note span {
    display: block;
}

.overview-note strong {
    color: var(--text-primary);
    font-size: 8px;
    font-weight: 700;
}

.overview-note span {
    margin-top: 2px;
    font-size: 8px;
    line-height: 1.45;
}

.save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 12px;
    padding: 10px 11px;
    border: 1px solid color-mix(in srgb, var(--primary) 20%, var(--border));
    border-radius: 10px;
    background: color-mix(in srgb, var(--primary) 5%, var(--surface));
}

.save-bar > div:first-child {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.save-status-dot {
    width: 7px;
    height: 7px;
    flex: 0 0 auto;
    border-radius: 50%;
    background: var(--success);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--success) 10%, transparent);
}

.save-status-dot.dirty {
    background: var(--primary);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 10%, transparent);
}

.save-bar strong,
.save-bar small {
    display: block;
}

.save-bar strong {
    color: var(--text-primary);
    font-size: 10px;
}

.save-bar small {
    margin-top: 2px;
    color: var(--text-secondary);
    font-size: 8px;
}

.save-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.access-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 12px;
    padding: 9px 1px 2px;
    color: var(--text-muted);
    font-size: 8px;
}

.access-footer > div {
    display: flex;
    align-items: center;
    gap: 6px;
}

.footer-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--success);
}

.message-enter-active,
.message-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}

.message-enter-from,
.message-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 1050px) {
    .settings-layout {
        grid-template-columns: 190px minmax(0, 1fr);
    }

    .category-copy small {
        display: none;
    }

    .category-item {
        grid-template-columns: 29px minmax(0, 1fr) auto;
    }
}

@media (max-width: 900px) {
    .settings-layout {
        grid-template-columns: 1fr;
    }

    .settings-sidebar {
        position: static;
    }

    .category-nav {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .category-copy small {
        display: block;
    }

    .category-grid {
        grid-template-columns: 1fr;
    }

    .hours-row {
        grid-template-columns: minmax(120px, 1fr) auto;
    }

    .time-controls {
        grid-column: 1 / -1;
    }
}

@media (max-width: 760px) {
    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .header-actions {
        width: 100%;
    }

    .header-actions .btn {
        flex: 1;
    }

    .stat-grid {
        grid-template-columns: 1fr;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }

    .system-setting-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .save-bar {
        align-items: flex-start;
        flex-direction: column;
    }

    .save-actions {
        width: 100%;
        justify-content: flex-end;
    }
}

@media (max-width: 560px) {
    .category-nav {
        grid-template-columns: 1fr;
    }

    .content-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .section-badge {
        align-self: flex-start;
    }

    .hours-row {
        grid-template-columns: 1fr auto;
    }

    .time-controls {
        width: 100%;
    }

    .access-footer {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (prefers-reduced-motion: reduce) {
    .loading-spinner {
        animation: none;
    }

    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
    }
}
</style>
