<script setup lang="ts">
import { computed } from 'vue';

type IconName = string;
const props = withDefaults(defineProps<{ name: IconName; size?: number }>(), {
    size: 20,
});
const size = computed(() => `${props.size}px`);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <template v-if="name === 'dashboard'">
            <rect x="3" y="3" width="7" height="7" rx="1" />
            <rect x="14" y="3" width="7" height="7" rx="1" />
            <rect x="3" y="14" width="7" height="7" rx="1" />
            <rect x="14" y="14" width="7" height="7" rx="1" />
        </template>
        <template v-else-if="name === 'search'">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
        </template>
        <template v-else-if="name === 'plus'">
            <path d="M12 5v14M5 12h14" />
        </template>
        <template v-else-if="name === 'bell'">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
            <path d="M10 21h4" />
        </template>
        <template v-else-if="name === 'calendar'">
            <rect x="3" y="4" width="18" height="17" rx="2" />
            <path d="M16 2v4M8 2v4M3 10h18" />
        </template>
        <template v-else-if="name === 'sun'">
            <circle cx="12" cy="12" r="4" />
            <path
                d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"
            />
        </template>
        <template v-else-if="name === 'moon'">
            <path d="M21 12.8A8.5 8.5 0 1 1 11.2 3 6.5 6.5 0 0 0 21 12.8Z" />
        </template>
        <template v-else-if="name === 'settings'">
            <circle cx="12" cy="12" r="3" />
            <path
                d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.41 1.41-.06-.06A1.7 1.7 0 0 0 16.45 18a1.7 1.7 0 0 0-1 .62 1.7 1.7 0 0 0-.38 1.09V20h-2v-.29a1.7 1.7 0 0 0-.38-1.09 1.7 1.7 0 0 0-1-.62 1.7 1.7 0 0 0-1.88.34l-.06.06-1.41-1.41.06-.06A1.7 1.7 0 0 0 8.55 15a1.7 1.7 0 0 0-.62-1 1.7 1.7 0 0 0-1.09-.38H6v-2h.29a1.7 1.7 0 0 0 1.09-.38 1.7 1.7 0 0 0 .62-1 1.7 1.7 0 0 0-.34-1.88L7.6 8.3l1.41-1.41.06.06A1.7 1.7 0 0 0 10.95 7a1.7 1.7 0 0 0 1-.62 1.7 1.7 0 0 0 .38-1.09V5h2v.29a1.7 1.7 0 0 0 .38 1.09 1.7 1.7 0 0 0 1 .62 1.7 1.7 0 0 0 1.88-.34l.06-.06 1.41 1.41-.06.06A1.7 1.7 0 0 0 18.45 10a1.7 1.7 0 0 0 .62 1 1.7 1.7 0 0 0 1.09.38h.29v2h-.29a1.7 1.7 0 0 0-1.09.38 1.7 1.7 0 0 0-.62 1Z"
            />
        </template>
        <template v-else-if="name === 'chevron-down'">
            <path d="m6 9 6 6 6-6" />
        </template>
        <template v-else-if="name === 'chevron-left'">
            <path d="m15 18-6-6 6-6" />
        </template>
        <template v-else-if="name === 'chevron-right'">
            <path d="m9 18 6-6-6-6" />
        </template>
        <template v-else-if="name === 'expand'">
            <path d="M8 3H3v5M16 3h5v5M21 16v5h-5M3 16v5h5" />
        </template>
        <template v-else-if="name === 'check'">
            <path d="m5 12 4 4L19 6" />
        </template>
        <template v-else-if="name === 'dots'">
            <circle cx="5" cy="12" r="1" fill="currentColor" />
            <circle cx="12" cy="12" r="1" fill="currentColor" />
            <circle cx="19" cy="12" r="1" fill="currentColor" />
        </template>
        <template v-else-if="name === 'chat'">
            <path
                d="M20 11.5a7.5 7.5 0 0 1-8 7.5 9 9 0 0 1-4-.9L4 20l1.9-3.4A7.2 7.2 0 0 1 4.5 12 7.5 7.5 0 0 1 12 4.5a7.5 7.5 0 0 1 8 7Z"
            />
        </template>
        <template
            v-else-if="name === 'cart' || name === 'orders' || name === 'sales'"
        >
            <path
                d="M3 4h2l2.1 10.1a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 1.9-1.4L21 8H7"
            />
            <circle cx="10" cy="19" r="1.5" />
            <circle cx="18" cy="19" r="1.5" />
        </template>
        <template v-else-if="name === 'customers' || name === 'users'">
            <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" />
            <circle cx="9.5" cy="7" r="4" />
            <path d="M17 11a4 4 0 0 0 0-8M21 21v-2a4 4 0 0 0-3-3.87" />
        </template>
        <template v-else-if="name === 'products' || name === 'inventory'">
            <path d="m21 8-9 5-9-5 9-5 9 5Z" />
            <path d="M3 8v8l9 5 9-5V8M12 13v8" />
        </template>
        <template v-else-if="name === 'wholesale'">
            <path d="M4 7h16v13H4zM8 7V5a4 4 0 0 1 8 0v2M8 13h8" />
        </template>
        <template v-else-if="name === 'pricing'">
            <path d="M20 12 12 20 4 12V4h8l8 8Z" />
            <circle cx="8" cy="8" r="1" />
        </template>
        <template v-else-if="name === 'shipping'">
            <path d="M3 6h11v10H3zM14 10h4l3 3v3h-7z" />
            <circle cx="7" cy="19" r="2" />
            <circle cx="17" cy="19" r="2" />
        </template>
        <template v-else-if="name === 'payments' || name === 'revenue'">
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="M3 10h18M7 15h3" />
        </template>
        <template v-else-if="name === 'service'">
            <path d="M12 2a10 10 0 1 0 10 10" />
            <path d="M12 6v6l4 2" />
        </template>
        <template v-else-if="name === 'marketing'">
            <path d="M4 11v2M7 8v8M10 5v14M13 8v8M16 4v16M19 10v4" />
        </template>
        <template v-else-if="name === 'analytics' || name === 'conversion'">
            <path d="M4 19V5M4 19h16" />
            <path d="m7 15 3-4 3 2 5-7" />
        </template>
        <template
            v-else-if="
                name === 'reports' ||
                name === 'cms' ||
                name === 'seo' ||
                name === 'developer'
            "
        >
            <path d="M6 3h9l3 3v15H6z" />
            <path d="M14 3v4h4M9 12h6M9 16h6" />
        </template>
        <template v-else-if="name === 'info'">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 10v6M12 7h.01" />
        </template>
        <template v-else><circle cx="12" cy="12" r="9" /></template>
    </svg>
</template>
