<script setup lang="ts">
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Sidebar from '@/components/layout/Sidebar.vue';
import Topbar from '@/components/layout/Topbar.vue';
import { useTheme } from '@/composables/useTheme';

interface ErpLayoutProps {
    userName: string;
    userEmail: string;
}

defineProps<ErpLayoutProps>();

const collapsed = ref(false);
const mobileOpen = ref(false);

const { mode, setTheme } = useTheme();
const page = usePage();

function toggleSidebar(): void {
    collapsed.value = !collapsed.value;
}

function openMobileSidebar(): void {
    mobileOpen.value = true;
}

function closeMobileSidebar(): void {
    mobileOpen.value = false;
}
</script>

<template>
    <div class="erp-shell">
        <Sidebar
            :collapsed="collapsed"
            :mobile-open="mobileOpen"
            @toggle="toggleSidebar"
            @close-mobile="closeMobileSidebar"
        />

        <div class="erp-main" :class="{ 'sidebar-collapsed': collapsed }">
            <Topbar
                :user-name="userName"
                :user-email="userEmail"
                :theme="mode"
                @toggle-sidebar="openMobileSidebar"
                @set-theme="setTheme"
            />

            <main id="main-content" class="erp-content" tabindex="-1">
                <Transition name="erp-page" mode="out-in">
                    <div :key="page.url" class="erp-page-transition">
                        <slot />
                    </div>
                </Transition>
            </main>
        </div>
    </div>
</template>

<style scoped>
.erp-page-enter-active,
.erp-page-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
    will-change: opacity, transform;
}
.erp-page-enter-from { opacity: 0; transform: translateY(8px); }
.erp-page-leave-to { opacity: 0; transform: translateY(-4px); }
.erp-page-transition { min-width: 0; }
@media (prefers-reduced-motion: reduce) {
    .erp-page-enter-active, .erp-page-leave-active { transition: none; }
    .erp-page-enter-from, .erp-page-leave-to { opacity: 1; transform: none; }
}
</style>
