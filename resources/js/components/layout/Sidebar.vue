<script setup lang="ts">
import { computed } from 'vue';
import Icon from '@/components/dashboard/Icon.vue';
import { primaryNavigation } from '@/config/navigation';

defineProps<{ collapsed: boolean; mobileOpen: boolean }>();
const emit = defineEmits<{ closeMobile: []; toggle: [] }>();
const items = computed(() => primaryNavigation);
</script>

<template>
    <aside
        class="erp-sidebar"
        :class="{ 'is-collapsed': collapsed, 'is-mobile-open': mobileOpen }"
    >
        <div class="sidebar-brand">
            <div class="brand-mark">M<span>↗</span></div>
            <div class="brand-copy">
                <strong>MADINA SCALE</strong
                ><small>WEIGHING A BETTER TOMORROW</small>
            </div>
            <button
                class="sidebar-toggle"
                type="button"
                aria-label="Collapse sidebar"
                @click="emit('toggle')"
            >
                <Icon name="chevron-left" :size="16" />
            </button>
        </div>
        <nav class="sidebar-nav" aria-label="Main navigation">
            <a
                v-for="item in items"
                :key="item.label"
                class="nav-item"
                :class="{ active: item.label === 'Dashboard' }"
                :href="item.href || '#'"
                @click="emit('closeMobile')"
            >
                <Icon :name="item.icon" :size="19" /><span class="nav-label">{{
                    item.label
                }}</span>
                <span v-if="item.badge" class="nav-badge">{{
                    item.badge
                }}</span>
                <Icon
                    v-if="item.expandable"
                    class="nav-chevron"
                    name="chevron-right"
                    :size="14"
                />
            </a>
        </nav>
        <div class="sidebar-status">
            <span class="status-dot"></span>
            <div>
                <strong>System Status</strong
                ><span>All Systems Operational</span>
            </div>
        </div>
        <div class="sidebar-footer">
            <div class="footer-mark">M</div>
            <div class="footer-copy">
                <strong>Madina Scale</strong><span>Enterprise Dashboard</span>
            </div>
            <small>v1.0.0</small>
        </div>
    </aside>
    <button
        v-if="mobileOpen"
        class="sidebar-scrim"
        type="button"
        aria-label="Close menu"
        @click="emit('closeMobile')"
    ></button>
</template>
