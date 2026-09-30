<script setup lang="ts">
import { ref } from 'vue';
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
                <slot />
            </main>
        </div>
    </div>
</template>
