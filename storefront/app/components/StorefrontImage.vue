<script setup lang="ts">
const props = withDefaults(defineProps<{
  src?: string | null
  alt: string
  kind?: 'hero' | 'product' | 'category' | 'banner' | 'generic'
  label: string
  dimensions: string
  ratio?: string
  width?: number
  height?: number
  loading?: 'eager' | 'lazy'
  priority?: 'high' | 'low' | 'auto'
  fit?: 'contain' | 'cover'
}>(), {
  src: null,
  kind: 'generic',
  ratio: '1 / 1',
  width: 800,
  height: 800,
  loading: 'lazy',
  priority: 'auto',
  fit: 'contain',
})
const failed = ref(false)
watch(() => props.src, () => { failed.value = false })
</script>

<template>
  <img
    v-if="src && !failed"
    :src="src"
    :alt="alt"
    :width="width"
    :height="height"
    :loading="loading"
    :fetchpriority="priority"
    :class="`storefront-image storefront-image--${fit}`"
    @error="failed = true"
  >
  <ImagePlaceholder v-else :kind="kind" :label="label" :dimensions="dimensions" :ratio="ratio" />
</template>
