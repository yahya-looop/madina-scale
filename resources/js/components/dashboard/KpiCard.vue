<script setup lang="ts">
import Icon from './Icon.vue';
import type { Kpi } from '@/config/dashboard';

defineProps<{ kpi: Kpi }>();
</script>

<template>
    <article class="kpi-card">
        <div class="kpi-head">
            <span class="kpi-icon" :class="`tone-${kpi.tone}`"
                ><Icon :name="kpi.icon" :size="20" /></span
            ><button class="card-dots"><Icon name="dots" :size="18" /></button>
        </div>
        <p>{{ kpi.label }}</p>
        <strong>{{ kpi.value }}</strong>
        <div class="kpi-bottom">
            <span class="growth" :class="{ negative: !kpi.positive }"
                >↗ {{ kpi.change }}</span
            ><small>vs last 30 days</small
            ><svg class="spark" viewBox="0 0 100 34" preserveAspectRatio="none">
                <polyline
                    :points="
                        kpi.spark
                            .map((v, i) => `${i * 11},${34 - v / 2}`)
                            .join(' ')
                    "
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                />
            </svg>
        </div>
    </article>
</template>
