<script setup lang="ts">
import { ref, watch } from 'vue';
import Icon from '@/components/dashboard/Icon.vue';

const props = withDefaults(defineProps<{
    src: string | null;
    alt: string;
    kind?: string;
    aspect?: string;
    dimensions?: string;
    imageClass?: string;
}>(), {
    kind: 'product',
    aspect: '1 / 1',
    dimensions: '',
    imageClass: 'h-full w-full object-contain',
});
const failed = ref(false);
const isDevelopment = import.meta.env.DEV;
watch(() => props.src, () => { failed.value = false; });
</script>

<template>
    <div class="relative overflow-hidden bg-slate-50" :style="{ aspectRatio: aspect }">
        <img
            v-if="src && !failed"
            :src="src"
            :alt="alt"
            :class="imageClass"
            loading="lazy"
            decoding="async"
            @error="failed = true"
        />
        <div v-else class="flex h-full min-h-20 w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-slate-50 to-emerald-50 px-3 text-center text-slate-400" role="img" :aria-label="alt || 'Image unavailable'">
            <Icon :name="kind === 'hero' ? 'image' : 'package'" :size="kind === 'hero' ? 42 : 30" />
            <span class="text-[11px] font-semibold">{{ kind === 'hero' ? 'Hero Banner' : 'Image unavailable' }}</span>
            <span v-if="isDevelopment && dimensions" class="text-[9px] text-slate-400">{{ dimensions }}</span>
        </div>
    </div>
</template>
