<script setup lang="ts">
import { computed } from 'vue';

interface Segment {
    value: number;
    tone: string;
}

const props = defineProps<{
    total: string;
    centerLabel: string;
    segments: Segment[];
}>();

const circumference = 2 * Math.PI * 38;

const totalValue = computed(() =>
    props.segments.reduce((sum, segment) => sum + segment.value, 0),
);

const segmentStyles = computed(() => {
    let cursor = 0;

    return props.segments.map((segment) => {
        const length =
            totalValue.value > 0
                ? (circumference * segment.value) / totalValue.value
                : 0;

        const offset = -cursor;

        cursor += length;

        return {
            ...segment,
            dasharray: `${length} ${circumference - length}`,
            offset,
        };
    });
});
</script>

<template>
    <div class="donut-wrap">
        <svg viewBox="0 0 100 100" class="donut">
            <circle
                cx="50"
                cy="50"
                r="38"
                fill="none"
                stroke="var(--chart-track)"
                stroke-width="13"
            />

            <circle
                v-for="(segment, index) in segmentStyles"
                :key="index"
                cx="50"
                cy="50"
                r="38"
                fill="none"
                :class="`chart-${segment.tone}`"
                stroke-width="13"
                :stroke-dasharray="segment.dasharray"
                :stroke-dashoffset="segment.offset"
                transform="rotate(-90 50 50)"
            />
        </svg>

        <div class="donut-center">
            <strong>{{ total }}</strong>
            <span>{{ centerLabel }}</span>
        </div>
    </div>
</template>
