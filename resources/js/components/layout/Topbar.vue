<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from '@/components/dashboard/Icon.vue';
import type { ThemeMode } from '@/composables/useTheme';

const emit = defineEmits<{
    toggleSidebar: [];
    setTheme: [value: ThemeMode];
}>();

const props = defineProps<{
    userName: string;
    userEmail: string;
    theme: ThemeMode;
}>();

const themeOpen = ref(false);
const profileOpen = ref(false);

const themeLabel = computed(() => {
    switch (props.theme) {
        case 'dark':
            return 'Dark';
        case 'light':
            return 'Light';
        default:
            return 'System';
    }
});

const themeIcon = computed(() => {
    switch (props.theme) {
        case 'dark':
            return 'moon';
        case 'light':
            return 'sun';
        default:
            return 'settings';
    }
});

function selectTheme(value: ThemeMode): void {
    emit('setTheme', value);
    themeOpen.value = false;
}

function toggleThemeMenu(): void {
    themeOpen.value = !themeOpen.value;
    profileOpen.value = false;
}

function toggleProfileMenu(): void {
    profileOpen.value = !profileOpen.value;
    themeOpen.value = false;
}

function closeMenus(): void {
    themeOpen.value = false;
    profileOpen.value = false;
}

function handleDocumentClick(event: MouseEvent): void {
    const target = event.target;

    if (!(target instanceof Node)) {
        return;
    }

    const targetElement = target as HTMLElement;

    if (
        !targetElement.closest('.dashboard-theme-wrap') &&
        !targetElement.closest('.profile-wrap')
    ) {
        closeMenus();
    }
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeMenus();
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <header class="topbar">
        <!-- Global Search -->
        <div class="topbar-search">
            <Icon name="search" :size="19" />

            <input
                aria-label="Global search"
                type="search"
                autocomplete="off"
                placeholder="Search anything... (orders, products, customers, invoices, tickets, etc.)"
            />

            <kbd>Ctrl + K</kbd>
        </div>

        <!-- Topbar Actions -->
        <div class="topbar-actions">
            <!-- Create -->
            <button
                class="icon-btn primary-action"
                type="button"
                aria-label="Create"
            >
                <Icon name="plus" :size="22" />
            </button>

            <!-- Notifications -->
            <button
                class="icon-btn notification-btn"
                type="button"
                aria-label="Notifications"
            >
                <Icon name="bell" :size="20" />
                <span>5</span>
            </button>

            <!-- Calendar -->
            <button
                class="icon-btn"
                type="button"
                aria-label="Calendar"
            >
                <Icon name="calendar" :size="19" />
            </button>

            <!-- =========================================================
                 THEME CONTROL
                 ========================================================= -->
            <div class="dashboard-theme-wrap">
                <button
                    class="dashboard-theme-trigger"
                    type="button"
                    :aria-expanded="themeOpen"
                    aria-haspopup="menu"
                    aria-label="Change theme"
                    @click.stop="toggleThemeMenu"
                >
                    <span class="dashboard-theme-icon" aria-hidden="true">
                        <Icon :name="themeIcon" :size="17" />
                    </span>

                    <span class="dashboard-theme-label">
                        {{ themeLabel }}
                    </span>

                    <span class="dashboard-theme-chevron" aria-hidden="true">
                        <Icon
                            name="chevron-down"
                            :size="13"
                        />
                    </span>
                </button>

                <!-- Theme dropdown -->
                <div
                    v-if="themeOpen"
                    class="dashboard-theme-menu"
                    role="menu"
                    aria-label="Theme options"
                    @click.stop
                >
                    <!-- Light -->
                    <button
                        type="button"
                        role="menuitem"
                        :class="{
                            selected: props.theme === 'light',
                        }"
                        @click="selectTheme('light')"
                    >
                        <span
                            class="dashboard-theme-option-icon theme-light-icon"
                            aria-hidden="true"
                        >
                            <Icon name="sun" :size="17" />
                        </span>

                        <span class="dashboard-theme-option-label">
                            Light
                        </span>

                        <span
                            v-if="props.theme === 'light'"
                            class="dashboard-theme-check"
                            aria-hidden="true"
                        >
                            <Icon name="check" :size="15" />
                        </span>
                    </button>

                    <!-- Dark -->
                    <button
                        type="button"
                        role="menuitem"
                        :class="{
                            selected: props.theme === 'dark',
                        }"
                        @click="selectTheme('dark')"
                    >
                        <span
                            class="dashboard-theme-option-icon theme-dark-icon"
                            aria-hidden="true"
                        >
                            <Icon name="moon" :size="17" />
                        </span>

                        <span class="dashboard-theme-option-label">
                            Dark
                        </span>

                        <span
                            v-if="props.theme === 'dark'"
                            class="dashboard-theme-check"
                            aria-hidden="true"
                        >
                            <Icon name="check" :size="15" />
                        </span>
                    </button>

                    <!-- System -->
                    <button
                        type="button"
                        role="menuitem"
                        :class="{
                            selected: props.theme === 'system',
                        }"
                        @click="selectTheme('system')"
                    >
                        <span
                            class="dashboard-theme-option-icon theme-system-icon"
                            aria-hidden="true"
                        >
                            <Icon name="settings" :size="17" />
                        </span>

                        <span class="dashboard-theme-option-label">
                            System
                        </span>

                        <span
                            v-if="props.theme === 'system'"
                            class="dashboard-theme-check"
                            aria-hidden="true"
                        >
                            <Icon name="check" :size="15" />
                        </span>
                    </button>
                </div>
            </div>

            <!-- Fullscreen -->
            <button
                class="icon-btn"
                type="button"
                aria-label="Fullscreen"
            >
                <Icon name="expand" :size="18" />
            </button>

            <!-- Profile -->
            <div class="profile-wrap">
                <button
                    class="profile"
                    type="button"
                    :aria-expanded="profileOpen"
                    aria-haspopup="menu"
                    @click.stop="toggleProfileMenu"
                >
                    <span class="avatar">
                        {{ userName.slice(0, 1).toUpperCase() }}
                    </span>

                    <span class="profile-copy">
                        <strong>{{ userName }}</strong>

                        <small>
                            <i></i>
                            Online
                        </small>
                    </span>

                    <Icon
                        name="chevron-down"
                        :size="14"
                    />
                </button>

                <div
                    v-if="profileOpen"
                    class="profile-menu"
                    role="menu"
                    @click.stop
                >
                    <strong>{{ userName }}</strong>
                    <span>{{ userEmail }}</span>

                    <button
                        type="button"
                        role="menuitem"
                    >
                        Profile
                    </button>

                    <button
                        type="button"
                        role="menuitem"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile navigation -->
        <button
            class="mobile-menu"
            type="button"
            aria-label="Open navigation"
            @click="emit('toggleSidebar')"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>
    </header>
</template>

<style scoped>
/* ============================================================
   TOPBAR THEME CONTROL
   ============================================================ */

.dashboard-theme-wrap {
    position: relative;
    display: flex;
    align-items: center;
    flex: 0 0 auto;
}

/* Main theme trigger */
.dashboard-theme-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;

    height: 42px;
    min-width: 42px;

    padding: 0 10px;

    border: 1px solid var(--border, #dce3ec);
    border-radius: 11px;

    background: var(--surface, #ffffff);
    color: var(--text-secondary, #526173);

    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    line-height: 1;

    cursor: pointer;

    transition:
        border-color 0.18s ease,
        background-color 0.18s ease,
        color 0.18s ease,
        box-shadow 0.18s ease,
        transform 0.12s ease;
}

.dashboard-theme-trigger:hover {
    border-color: rgba(19, 164, 108, 0.45);

    background: rgba(19, 164, 108, 0.07);

    color: var(--text-primary, #142033);

    box-shadow:
        0 4px 14px rgba(15, 23, 42, 0.08);
}

.dashboard-theme-trigger:active {
    transform: translateY(1px);
}

.dashboard-theme-trigger[aria-expanded='true'] {
    border-color: rgba(19, 164, 108, 0.55);

    background: rgba(19, 164, 108, 0.08);

    color: var(--text-primary, #142033);

    box-shadow:
        0 5px 16px rgba(15, 23, 42, 0.09);
}

/* Theme icon container */
.dashboard-theme-icon {
    display: grid;

    width: 24px;
    height: 24px;

    place-items: center;

    flex: 0 0 24px;

    border-radius: 7px;

    background: rgba(19, 164, 108, 0.10);
    color: var(--primary, #13a46c);
}

/* Theme text */
.dashboard-theme-label {
    display: inline-flex;
    align-items: center;

    min-width: 30px;

    font-size: 12px;
    font-weight: 650;
    line-height: 1;

    white-space: nowrap;
}

/* Chevron */
.dashboard-theme-chevron {
    display: grid;

    width: 16px;
    height: 16px;

    place-items: center;

    flex: 0 0 16px;

    color: currentColor;

    opacity: 0.7;

    transition:
        transform 0.18s ease,
        opacity 0.18s ease;
}

.dashboard-theme-trigger[aria-expanded='true']
    .dashboard-theme-chevron {
    transform: rotate(180deg);
    opacity: 1;
}

/* ============================================================
   THEME MENU
   ============================================================ */

.dashboard-theme-menu {
    position: absolute;

    top: calc(100% + 9px);
    right: 0;

    z-index: 1000;

    width: 170px;

    padding: 6px;

    border: 1px solid var(--border, #dce3ec);
    border-radius: 13px;

    background: var(
        --surface-elevated,
        var(--surface, #ffffff)
    );

    color: var(
        --text-primary,
        #142033
    );

    box-shadow:
        0 18px 40px rgba(15, 23, 42, 0.16),
        0 3px 10px rgba(15, 23, 42, 0.06);

    animation:
        dashboard-theme-menu-in
        0.14s
        ease-out;
}

@keyframes dashboard-theme-menu-in {
    from {
        opacity: 0;
        transform: translateY(-4px) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Every option */
.dashboard-theme-menu button {
    display: flex;

    width: 100%;
    min-height: 38px;

    align-items: center;

    gap: 9px;

    padding: 7px 9px;

    border: 0;
    border-radius: 9px;

    background: transparent;

    /*
     * Important:
     * Explicit text color is used here so dark-mode hover
     * never turns the label white-on-white.
     */
    color: var(
        --text-secondary,
        #526173
    );

    font-family: inherit;
    font-size: 12px;
    font-weight: 550;

    text-align: left;

    cursor: pointer;

    transition:
        background-color 0.15s ease,
        color 0.15s ease,
        transform 0.12s ease;
}

/* Hover */
.dashboard-theme-menu button:hover {
    background: rgba(19, 164, 108, 0.10);

    color: var(
        --text-primary,
        #142033
    );
}

/* Keyboard focus */
.dashboard-theme-menu button:focus-visible {
    outline: 2px solid rgba(19, 164, 108, 0.45);
    outline-offset: -2px;

    background: rgba(19, 164, 108, 0.08);

    color: var(
        --text-primary,
        #142033
    );
}

/* Selected */
.dashboard-theme-menu button.selected {
    background: rgba(19, 164, 108, 0.12);

    color: var(
        --text-primary,
        #142033
    );
}

/* Selected + hover */
.dashboard-theme-menu button.selected:hover {
    background: rgba(19, 164, 108, 0.16);

    color: var(
        --text-primary,
        #142033
    );
}

/* Prevent text disappearing in dark theme */
.dashboard-theme-menu button > span {
    color: inherit;
}

.dashboard-theme-option-label {
    flex: 1;

    color: inherit;

    white-space: nowrap;
}

/* Option icons */
.dashboard-theme-option-icon {
    display: grid;

    width: 23px;
    height: 23px;

    place-items: center;

    flex: 0 0 23px;

    border-radius: 7px;

    color: currentColor;
}

.theme-light-icon {
    color: #d99a00;
    background: rgba(245, 158, 11, 0.10);
}

.theme-dark-icon {
    color: #6d7cff;
    background: rgba(99, 102, 241, 0.11);
}

.theme-system-icon {
    color: #64748b;
    background: rgba(100, 116, 139, 0.10);
}

/* Check mark */
.dashboard-theme-check {
    display: grid;

    width: 18px;
    height: 18px;

    place-items: center;

    margin-left: auto;

    flex: 0 0 18px;

    color: var(
        --primary,
        #13a46c
    );
}

/* ============================================================
   DARK MODE
   Explicit selectors make the dropdown readable even if the
   global CSS variables are imperfect.
   ============================================================ */

:global(.dark) .dashboard-theme-trigger,
:global([data-theme='dark']) .dashboard-theme-trigger {
    border-color: #2a394b;

    background: #111c2b;

    color: #b8c5d5;
}

:global(.dark) .dashboard-theme-trigger:hover,
:global(.dark) .dashboard-theme-trigger[aria-expanded='true'],
:global([data-theme='dark']) .dashboard-theme-trigger:hover,
:global([data-theme='dark'])
    .dashboard-theme-trigger[aria-expanded='true'] {
    border-color: rgba(19, 164, 108, 0.55);

    background: rgba(19, 164, 108, 0.10);

    color: #f5f8fc;
}

:global(.dark) .dashboard-theme-menu,
:global([data-theme='dark']) .dashboard-theme-menu {
    border-color: #2a394b;

    background: #111c2b;

    color: #e8eef6;

    box-shadow:
        0 20px 44px rgba(0, 0, 0, 0.38),
        0 5px 14px rgba(0, 0, 0, 0.22);
}

:global(.dark) .dashboard-theme-menu button,
:global([data-theme='dark']) .dashboard-theme-menu button {
    color: #aebdce;
}

:global(.dark) .dashboard-theme-menu button:hover,
:global([data-theme='dark']) .dashboard-theme-menu button:hover {
    background: rgba(19, 164, 108, 0.14);

    color: #ffffff;
}

:global(.dark) .dashboard-theme-menu button.selected,
:global([data-theme='dark']) .dashboard-theme-menu button.selected {
    background: rgba(19, 164, 108, 0.17);

    color: #ffffff;
}

:global(.dark)
    .dashboard-theme-menu
    button.selected:hover,
:global([data-theme='dark'])
    .dashboard-theme-menu
    button.selected:hover {
    background: rgba(19, 164, 108, 0.22);

    color: #ffffff;
}

:global(.dark) .dashboard-theme-option-icon,
:global([data-theme='dark']) .dashboard-theme-option-icon {
    color: inherit;
}

:global(.dark) .theme-light-icon,
:global([data-theme='dark']) .theme-light-icon {
    color: #fbbf24;

    background: rgba(251, 191, 36, 0.12);
}

:global(.dark) .theme-dark-icon,
:global([data-theme='dark']) .theme-dark-icon {
    color: #a5b4fc;

    background: rgba(129, 140, 248, 0.14);
}

:global(.dark) .theme-system-icon,
:global([data-theme='dark']) .theme-system-icon {
    color: #cbd5e1;

    background: rgba(203, 213, 225, 0.10);
}

/* ============================================================
   PROFILE MENU
   ============================================================ */

.profile-wrap {
    position: relative;
}

.profile-menu {
    position: absolute;

    top: calc(100% + 9px);
    right: 0;

    z-index: 1000;

    display: flex;
    min-width: 210px;

    flex-direction: column;

    padding: 12px;

    border: 1px solid var(--border, #dce3ec);
    border-radius: 13px;

    background: var(
        --surface-elevated,
        var(--surface, #ffffff)
    );

    color: var(
        --text-primary,
        #142033
    );

    box-shadow:
        0 18px 40px rgba(15, 23, 42, 0.16);
}

.profile-menu strong {
    display: block;

    margin-bottom: 3px;

    color: inherit;

    font-size: 13px;
    font-weight: 700;
}

.profile-menu > span {
    display: block;

    margin-bottom: 10px;

    color: var(
        --text-secondary,
        #64748b
    );

    font-size: 11px;
}

.profile-menu button {
    width: 100%;

    padding: 8px 9px;

    border: 0;
    border-radius: 8px;

    background: transparent;

    color: var(
        --text-secondary,
        #526173
    );

    font-family: inherit;
    font-size: 12px;

    text-align: left;

    cursor: pointer;
}

.profile-menu button:hover {
    background: rgba(19, 164, 108, 0.09);

    color: var(
        --text-primary,
        #142033
    );
}

/* ============================================================
   MOBILE
   ============================================================ */

@media (max-width: 760px) {
    .dashboard-theme-label,
    .dashboard-theme-chevron {
        display: none;
    }

    .dashboard-theme-trigger {
        width: 42px;

        padding: 0;
    }

    .dashboard-theme-menu {
        right: -4px;
    }
}
</style>
