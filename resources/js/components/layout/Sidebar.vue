<script setup lang="ts">
import { computed, ref } from 'vue';

import Icon from '@/components/dashboard/Icon.vue';
import { primaryNavigation, type NavigationItem } from '@/config/navigation';

defineProps<{
    collapsed: boolean;
    mobileOpen: boolean;
}>();

const emit = defineEmits<{
    closeMobile: [];
    toggle: [];
}>();

const currentPath = ref(
    typeof window !== 'undefined' ? window.location.pathname : '',
);
const manualOpenGroups = ref<string[]>([]);

const items = computed(() => primaryNavigation);

function isPathActive(item: NavigationItem): boolean {
    if (!item.href) {
        return false;
    }

    return (
        currentPath.value === item.href ||
        currentPath.value.startsWith(`${item.href}/`)
    );
}

function hasActiveChild(item: NavigationItem): boolean {
    return Boolean(item.children?.some((child) => isPathActive(child)));
}

function isGroupOpen(item: NavigationItem): boolean {
    return hasActiveChild(item) || manualOpenGroups.value.includes(item.label);
}

function toggleGroup(item: NavigationItem): void {
    if (!item.expandable) {
        return;
    }

    manualOpenGroups.value = isGroupOpen(item)
        ? manualOpenGroups.value.filter((label) => label !== item.label)
        : [...manualOpenGroups.value, item.label];
}

function handleNavigation(): void {
    currentPath.value =
        typeof window !== 'undefined' ? window.location.pathname : '';
    emit('closeMobile');
}
</script>

<template>
    <aside
        class="erp-sidebar"
        :class="{
            'is-collapsed': collapsed,
            'is-mobile-open': mobileOpen,
        }"
    >
        <button
            class="sidebar-brand"
            type="button"
            :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            :aria-expanded="!collapsed"
            @click="emit('toggle')"
        >
            <span class="brand-mark" aria-hidden="true"> M<span>↗</span> </span>

            <span class="brand-copy">
                <strong>MADINA SCALE</strong>
                <small>WEIGHING A BETTER TOMORROW</small>
            </span>
        </button>

        <nav class="sidebar-nav" aria-label="Main navigation" tabindex="0">
            <template v-for="item in items" :key="item.label">
                <button
                    v-if="item.expandable"
                    class="nav-item nav-group-trigger"
                    :class="{
                        active: hasActiveChild(item),
                        'is-open': isGroupOpen(item),
                    }"
                    type="button"
                    :aria-expanded="isGroupOpen(item)"
                    @click="toggleGroup(item)"
                >
                    <Icon :name="item.icon" :size="18" />
                    <span class="nav-label">{{ item.label }}</span>
                    <span v-if="item.badge" class="nav-badge">{{
                        item.badge
                    }}</span>
                    <Icon
                        class="nav-chevron"
                        :class="{ rotated: isGroupOpen(item) }"
                        name="chevron-right"
                        :size="13"
                    />
                </button>

                <a
                    v-else
                    class="nav-item"
                    :class="{ active: isPathActive(item) }"
                    :href="item.href || '#'"
                    :aria-current="isPathActive(item) ? 'page' : undefined"
                    @click="handleNavigation"
                >
                    <Icon :name="item.icon" :size="18" />
                    <span class="nav-label">{{ item.label }}</span>
                    <span v-if="item.badge" class="nav-badge">{{
                        item.badge
                    }}</span>
                </a>

                <Transition name="sidebar-subnav">
                    <div
                        v-if="
                            item.children?.length &&
                            isGroupOpen(item) &&
                            !collapsed
                        "
                        class="sidebar-subnav"
                    >
                        <a
                            v-for="child in item.children"
                            :key="child.label"
                            class="nav-subitem"
                            :class="{ active: isPathActive(child) }"
                            :href="child.href || '#'"
                            :aria-current="
                                isPathActive(child) ? 'page' : undefined
                            "
                            @click="handleNavigation"
                        >
                            <span class="nav-subline" aria-hidden="true"></span>
                            <Icon :name="child.icon" :size="15" />
                            <span>{{ child.label }}</span>
                        </a>
                    </div>
                </Transition>
            </template>
        </nav>

        <div class="sidebar-status">
            <span class="status-dot" aria-hidden="true"></span>
            <div>
                <strong>System Status</strong>
                <span>All Systems Operational</span>
            </div>
        </div>

        <div class="sidebar-footer">
            <div class="footer-mark">M</div>
            <div class="footer-copy">
                <strong>Madina Scale</strong>
                <span>Enterprise Dashboard</span>
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

<style scoped>
.nav-group-trigger {
    width: 100%;
    border: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: pointer;
    appearance: none;
}

.nav-group-trigger.is-open:not(.active) {
    background: color-mix(in srgb, #ffffff 4%, transparent);
}

.nav-group-trigger.active {
    color: #ffffff;
    background: color-mix(in srgb, #09a96e 14%, transparent);
}

.nav-chevron {
    transition: transform 0.2s ease;
}

.nav-chevron.rotated {
    transform: rotate(90deg);
}

.sidebar-subnav {
    position: relative;
    display: grid;
    gap: 2px;
    margin: 1px 0 5px 20px;
    padding: 2px 0 2px 10px;
}

.sidebar-subnav::before {
    content: '';
    position: absolute;
    left: 1px;
    top: 5px;
    bottom: 5px;
    width: 1px;
    background: rgba(255, 255, 255, 0.09);
}

.nav-subitem {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 34px;
    padding: 0 9px;
    border-radius: 7px;
    color: #8fa0b2;
    font-size: 11px;
    font-weight: 550;
    transition:
        background-color 0.18s ease,
        color 0.18s ease,
        transform 0.18s ease;
}

.nav-subitem:hover {
    background: rgba(255, 255, 255, 0.045);
    color: #eaf3f9;
}

.nav-subitem.active {
    color: #ffffff;
    background: linear-gradient(
        90deg,
        rgba(9, 169, 110, 0.22),
        rgba(9, 169, 110, 0.08)
    );
    box-shadow: inset 2px 0 0 #09a96e;
}

.nav-subline {
    width: 4px;
    height: 4px;
    flex: 0 0 4px;
    border-radius: 50%;
    background: currentColor;
    opacity: 0.6;
}

.sidebar-subnav-enter-active,
.sidebar-subnav-leave-active {
    overflow: hidden;
    transition:
        opacity 0.18s ease,
        transform 0.18s ease,
        max-height 0.22s ease;
    max-height: 160px;
}

.sidebar-subnav-enter-from,
.sidebar-subnav-leave-to {
    max-height: 0;
    opacity: 0;
    transform: translateY(-4px);
}

@media (prefers-reduced-motion: reduce) {
    .nav-chevron,
    .nav-subitem,
    .sidebar-subnav-enter-active,
    .sidebar-subnav-leave-active {
        transition: none;
    }
}
</style>
