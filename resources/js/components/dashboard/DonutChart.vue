<script setup lang="ts">
const props = defineProps<{
    total: string;
    centerLabel: string;
    segments: { value: number; tone: string }[];
}>();
const circumference = 2 * Math.PI * 38;
let cursor = 0;
function dash(value: number) {
    const length =
        (circumference * value) /
        props.segments.reduce((a, s) => a + s.value, 0);
    const offset = -cursor;
    cursor += length;
    return `${length} ${circumference - length} ${offset}`;
}
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
                v-for="(segment, index) in segments"
                :key="index"
                cx="50"
                cy="50"
                r="38"
                fill="none"
                :class="`chart-${segment.tone}`"
                stroke-width="13"
                :stroke-dasharray="dash(segment.value)"
                transform="rotate(-90 50 50)"
            />
        </svg>
        <div class="donut-center">
            <strong>{{ total }}</strong
            ><span>{{ centerLabel }}</span>
        </div>
    </div>
</template>
