<script setup lang="ts">
withDefaults(defineProps<{
  name:
    | 'search' | 'shopping-cart' | 'menu' | 'x' | 'layout-grid' | 'scale'
    | 'zap' | 'arrow-right' | 'chevron-down' | 'truck' | 'shield-check'
    | 'target' | 'layers' | 'headset' | 'package-search' | 'mail' | 'message-circle' | 'phone'
  size?: number
}>(), { size: 20 })
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
    focusable="false"
  >
    <template v-if="name === 'search'">
      <circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" />
    </template>
    <template v-else-if="name === 'shopping-cart'">
      <circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" /><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
    </template>
    <template v-else-if="name === 'menu'">
      <path d="M4 6h16M4 12h16M4 18h16" />
    </template>
    <template v-else-if="name === 'x'">
      <path d="m18 6-12 12M6 6l12 12" />
    </template>
    <template v-else-if="name === 'layout-grid'">
      <rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" />
    </template>
    <template v-else-if="name === 'scale'">
      <path d="M12 3v18M5 7h14M7 7l-4 7h8L7 7Zm10 0-4 7h8l-4-7ZM5 21h14" />
    </template>
    <template v-else-if="name === 'zap'">
      <path d="m13 2-3 8H5l6 12 3-8h5L13 2Z" />
    </template>
    <template v-else-if="name === 'arrow-right'">
      <path d="M5 12h14M12 5l7 7-7 7" />
    </template>
    <template v-else-if="name === 'chevron-down'">
      <path d="m6 9 6 6 6-6" />
    </template>
    <template v-else-if="name === 'truck'">
      <path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z" /><circle cx="7.5" cy="19" r="1.8" /><circle cx="18" cy="19" r="1.8" />
    </template>
    <template v-else-if="name === 'shield-check'">
      <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" /><path d="m9 12 2 2 4-4" />
    </template>
    <template v-else-if="name === 'target'">
      <circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="5" /><circle cx="12" cy="12" r="1" /><path d="m16 8 5-5" />
    </template>
    <template v-else-if="name === 'layers'">
      <path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="m3 12 9 5 9-5M3 16l9 5 9-5" />
    </template>
    <template v-else-if="name === 'headset'">
      <path d="M3 13v-2a9 9 0 0 1 18 0v2" /><path d="M3 13h4v7H5a2 2 0 0 1-2-2v-5ZM21 13h-4v7h2a2 2 0 0 0 2-2v-5Z" /><path d="M17 20c-1 1-2.5 1.5-5 1.5" />
    </template>
    <template v-else-if="name === 'package-search'">
      <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" /><path d="M4.5 7.7 12 12l7.5-4.3M12 12v9" /><circle cx="17.5" cy="15.5" r="2.5" /><path d="m19.4 17.4 2 2" />
    </template>
    <template v-else-if="name === 'mail'">
      <rect x="3" y="5" width="18" height="14" rx="2" /><path d="m4 7 8 6 8-6" />
    </template>
    <template v-else-if="name === 'message-circle'">
      <path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z" />
    </template>
    <template v-else-if="name === 'phone'">
      <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z" />
    </template>
  </svg>
</template>
