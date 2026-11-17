<script setup lang="ts">
withDefaults(defineProps<{
  kind?: 'hero' | 'product' | 'category' | 'banner' | 'generic'
  label: string
  dimensions: string
  ratio?: string
}>(), {
  kind: 'generic',
  ratio: '1 / 1',
})
const showDimensions = import.meta.dev
</script>

<template>
  <div
    class="image-placeholder"
    :class="`image-placeholder--${kind}`"
    :style="{ aspectRatio: ratio }"
    role="img"
    :aria-label="`${label} placeholder${showDimensions ? `. Recommended image dimensions ${dimensions}.` : '.'}`"
  >
    <div class="image-placeholder__mark" aria-hidden="true">
      <img src="/brand-mark.png" alt="" width="54" height="54">
    </div>
    <span class="image-placeholder__label">{{ showDimensions ? label : 'Image unavailable' }}</span>
    <span v-if="showDimensions" class="image-placeholder__dimensions">{{ dimensions }}</span>
  </div>
</template>
