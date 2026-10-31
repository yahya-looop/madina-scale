<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
defineProps<{ page: { title: string; slug: string; excerpt: string | null; content: string | null; featuredImage: string | null; sections: Array<{key:string;name:string;type:string;content:Record<string, unknown>}>; seo: { title: string; description: string | null; canonical: string | null; index: boolean; follow: boolean } } }>();
</script>
<template>
    <Head :title="page.seo.title">
        <meta v-if="page.seo.description" head-key="description" name="description" :content="page.seo.description">
        <meta head-key="robots" name="robots" :content="`${page.seo.index ? 'index' : 'noindex'},${page.seo.follow ? 'follow' : 'nofollow'}`">
        <link v-if="page.seo.canonical" head-key="canonical" rel="canonical" :href="page.seo.canonical">
        <meta head-key="og:type" property="og:type" content="website">
        <meta head-key="og:title" property="og:title" :content="page.seo.title">
        <meta v-if="page.seo.description" head-key="og:description" property="og:description" :content="page.seo.description">
        <meta v-if="page.featuredImage" head-key="og:image" property="og:image" :content="page.featuredImage">
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image">
        <meta head-key="twitter:title" name="twitter:title" :content="page.seo.title">
        <meta v-if="page.seo.description" head-key="twitter:description" name="twitter:description" :content="page.seo.description">
    </Head>
    <main class="public-page">
        <img v-if="page.featuredImage" class="hero-image" :src="page.featuredImage" :alt="page.title">
        <article><p class="eyebrow">MADINA SCALE</p><h1>{{ page.title }}</h1><p v-if="page.excerpt" class="excerpt">{{ page.excerpt }}</p><div v-if="page.content" class="page-content">{{ page.content }}</div>
            <section v-for="(section,index) in page.sections" :key="`${section.key}-${index}`" class="content-section">
                <h2 v-if="typeof section.content.heading === 'string'">{{ section.content.heading }}</h2>
                <p v-if="typeof (section.content.body ?? section.content.text ?? section.content.description) === 'string'">{{ section.content.body ?? section.content.text ?? section.content.description }}</p>
                <img v-if="typeof (section.content.imageUrl ?? section.content.image) === 'string'" :src="String(section.content.imageUrl ?? section.content.image)" :alt="typeof section.content.alt === 'string' ? section.content.alt : section.name">
                <a v-if="typeof section.content.buttonLabel === 'string' && typeof section.content.buttonUrl === 'string'" :href="String(section.content.buttonUrl)">{{ section.content.buttonLabel }}</a>
            </section></article>
    </main>
</template>
<style scoped>
.public-page{max-width:1040px;margin:0 auto;padding:48px 24px 80px;color:#172033}.hero-image{width:100%;max-height:440px;object-fit:cover;border-radius:18px;margin-bottom:30px}.public-page article{max-width:820px;margin:0 auto}.eyebrow{font-size:11px;letter-spacing:.14em;font-weight:700;color:#64748b}.public-page h1{font-size:clamp(30px,5vw,48px);line-height:1.12;margin:10px 0 16px}.excerpt{font-size:19px;line-height:1.6;color:#64748b}.page-content{font-size:16px;line-height:1.85;overflow-wrap:anywhere}.page-content :deep(img){max-width:100%;height:auto}.page-content :deep(h2),.page-content :deep(h3){margin-top:1.8em}.content-section{padding:28px 0;border-top:1px solid #e2e8f0}.content-section img{max-width:100%;height:auto;border-radius:12px;margin-top:12px}.content-section a{display:inline-block;margin-top:12px;color:#17634c;text-decoration:underline}
</style>
