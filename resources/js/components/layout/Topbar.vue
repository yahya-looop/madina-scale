<script setup lang="ts">
import { ref } from 'vue';
import Icon from '@/components/dashboard/Icon.vue';
import type { ThemeMode } from '@/composables/useTheme';

defineProps<{ userName: string; userEmail: string; theme: ThemeMode }>();
const emit = defineEmits<{ toggleSidebar: []; setTheme: [value: ThemeMode] }>();
const themeOpen = ref(false);
const profileOpen = ref(false);
</script>

<template>
    <header class="topbar">
        <div class="topbar-search">
            <Icon name="search" :size="19" /><input
                aria-label="Global search"
                placeholder="Search anything... (orders, products, customers, invoices, tickets, etc.)"
            /><kbd>Ctrl + K</kbd>
        </div>
        <div class="topbar-actions">
            <button
                class="icon-btn primary-action"
                type="button"
                aria-label="Create"
            >
                <Icon name="plus" :size="22" />
            </button>
            <button
                class="icon-btn notification-btn"
                type="button"
                aria-label="Notifications"
            >
                <Icon name="bell" :size="20" /><span>5</span>
            </button>
            <button class="icon-btn" type="button" aria-label="Chat">
                <Icon name="chat" :size="19" />
            </button>
            <button class="icon-btn" type="button" aria-label="Calendar">
                <Icon name="calendar" :size="19" />
            </button>
            <div class="theme-wrap">
                <button
                    class="theme-trigger"
                    type="button"
                    aria-label="Theme"
                    @click="themeOpen = !themeOpen"
                >
                    <Icon
                        :name="theme === 'dark' ? 'moon' : 'sun'"
                        :size="18"
                    /><span class="theme-pill"></span>
                </button>
                <div v-if="themeOpen" class="theme-menu">
                    <button
                        :class="{ selected: theme === 'light' }"
                        @click="
                            emit('setTheme', 'light');
                            themeOpen = false;
                        "
                    >
                        <Icon name="sun" :size="16" /> Light
                    </button>
                    <button
                        :class="{ selected: theme === 'dark' }"
                        @click="
                            emit('setTheme', 'dark');
                            themeOpen = false;
                        "
                    >
                        <Icon name="moon" :size="16" /> Dark
                    </button>
                    <button
                        :class="{ selected: theme === 'system' }"
                        @click="
                            emit('setTheme', 'system');
                            themeOpen = false;
                        "
                    >
                        <Icon name="settings" :size="16" /> System
                    </button>
                </div>
            </div>
            <button class="language" type="button">
                🇺🇸 English <Icon name="chevron-down" :size="13" />
            </button>
            <button class="icon-btn" type="button" aria-label="Fullscreen">
                <Icon name="expand" :size="18" />
            </button>
            <div class="profile-wrap">
                <button
                    class="profile"
                    type="button"
                    @click="profileOpen = !profileOpen"
                >
                    <span class="avatar">{{
                        userName.slice(0, 1).toUpperCase()
                    }}</span
                    ><span class="profile-copy"
                        ><strong>{{ userName }}</strong
                        ><small><i></i> Online</small></span
                    ><Icon name="chevron-down" :size="14" />
                </button>
                <div v-if="profileOpen" class="profile-menu">
                    <strong>{{ userName }}</strong
                    ><span>{{ userEmail }}</span
                    ><button>Profile</button><button>Logout</button>
                </div>
            </div>
        </div>
        <button
            class="mobile-menu"
            type="button"
            aria-label="Open navigation"
            @click="emit('toggleSidebar')"
        >
            <span></span><span></span><span></span>
        </button>
    </header>
</template>
