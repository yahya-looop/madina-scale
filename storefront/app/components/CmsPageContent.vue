<script setup lang="ts">
defineProps<{
  page: {
    title: string
    excerpt?: string | null
    content?: string | null
    featuredImage?: string | null
    updatedAt?: string | null
  }
}>()
</script>

<template>
  <main class="container max-w-4xl py-10 md:py-16">
      <article class="surface p-6 md:p-10">
        <StorefrontImage
          v-if="page.featuredImage"
          :src="page.featuredImage"
          :alt="page.title"
          kind="banner"
          label="Editorial Image"
          dimensions="1600 × 900 px"
          ratio="16 / 9"
          :width="1600"
          :height="900"
          fit="cover"
        />
        <h1 class="text-3xl font-extrabold">{{ page.title }}</h1>
        <p v-if="page.excerpt" class="mt-4 text-lg muted">{{ page.excerpt }}</p>
        <div v-if="page.content" class="cms-content mt-8" v-html="page.content" />
      </article>
    </main>
</template>

<style scoped>
.cms-content :deep(h2) { margin: 1.75rem 0 .75rem; font-size: 1.5rem; font-weight: 800; }
.cms-content :deep(h3) { margin: 1.5rem 0 .5rem; font-size: 1.2rem; font-weight: 700; }
.cms-content :deep(p) { margin: .9rem 0; line-height: 1.8; }
.cms-content :deep(ul), .cms-content :deep(ol) { margin: 1rem 0; padding-left: 1.5rem; }
.cms-content :deep(ul) { list-style: disc; }
.cms-content :deep(ol) { list-style: decimal; }
.cms-content :deep(a) { color: #087d3b; text-decoration: underline; }
</style>
