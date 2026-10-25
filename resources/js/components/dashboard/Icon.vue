<script setup lang="ts">
import { computed } from 'vue';
import { Icon as IconifyIcon } from '@iconify/vue';
import { ICON_REGISTRY, resolveIconName } from '@/icons';

/**
 * Shared, offline-first icon renderer.
 *
 * Icon data is imported locally from @iconify/icons-tabler and bundled with the
 * application. This component never requests icon data from the public Iconify API.
 * Keep aliases in resources/js/icons/aliases and icon data in the small registry
 * modules instead of growing this component.
 */
const props = withDefaults(
    defineProps<{
        name?: string | null;
        size?: number;
        strokeWidth?: number;
        absoluteStrokeWidth?: boolean;
    }>(),
    {
        name: '',
        size: 20,
        strokeWidth: 1.8,
        absoluteStrokeWidth: false,
    },
);

const safeSize = computed(() =>
    Number.isFinite(props.size) && props.size > 0 ? props.size : 20,
);
const safeStrokeWidth = computed(() => {
    const width =
        Number.isFinite(props.strokeWidth) && props.strokeWidth > 0
            ? props.strokeWidth
            : 1.8;
    return props.absoluteStrokeWidth ? width * (24 / safeSize.value) : width;
});

const icon = computed(() => {
    const name = resolveIconName(props.name);
    const source = ICON_REGISTRY[name] ?? ICON_REGISTRY['help-circle'];
    const body =
        typeof source.body === 'string'
            ? source.body.replace(
                  /stroke-width="[^"]+"/g,
                  `stroke-width="${safeStrokeWidth.value}"`,
              )
            : source.body;

    return { ...source, body };
});
</script>

<template>
    <IconifyIcon
        :icon="icon"
        :width="safeSize"
        :height="safeSize"
        aria-hidden="true"
        focusable="false"
    />
</template>
