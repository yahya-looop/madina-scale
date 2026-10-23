<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

import Icon from '@/components/dashboard/Icon.vue';
import { useAuthorization } from '@/composables/useAuthorization';
import { primaryNavigation, type NavigationItem } from '@/config/navigation';

defineProps<{ collapsed: boolean; mobileOpen: boolean }>();

const emit = defineEmits<{ closeMobile: []; toggle: [] }>();
const page = usePage();
const { isDeveloper, can } = useAuthorization();
const manualOpenGroups = ref<string[]>([]);

const appVersion = computed<string>(() => {
    const version = import.meta.env.VITE_APP_VERSION;
    if (typeof version === 'string' && version.trim()) {
        return version.trim().startsWith('v') ? version.trim() : `v${version.trim()}`;
    }
    return 'v1.0.0';
});

const currentPath = computed<string>(() => {
    const url = page.url || '/';
    try {
        return new URL(url, window.location.origin).pathname.replace(/\/+$/, '') || '/';
    } catch {
        return (url.split('?')[0] || '/').replace(/\/+$/, '') || '/';
    }
});

const legacyNavigationPermissions: Record<string, string> = {
    '/users': 'system.users.view',
    '/settings/roles-permissions': 'system.roles.view',
};

function getPermission(item: NavigationItem): string | null {
    if (item.permission) return item.permission;
    if (item.href && legacyNavigationPermissions[item.href]) return legacyNavigationPermissions[item.href];
    return null;
}

function canView(item: NavigationItem): boolean {
    if (item.developerOnly && !isDeveloper.value) return false;
    if (isDeveloper.value) return true;
    const permission = getPermission(item);
    return permission ? can(permission) : true;
}

function hasVisibleChildren(item: NavigationItem): boolean {
    return Boolean(item.children?.some((child) => canView(child)));
}

const items = computed<NavigationItem[]>(() =>
    primaryNavigation.filter((item) => {
        if (item.developerOnly) return isDeveloper.value;
        return canView(item) || hasVisibleChildren(item);
    }),
);

/**
 * Only the most-specific matching route is active.
 * This keeps /inventory from also activating on /inventory/low-stock
 * or /inventory/movements, while preserving parent activity for
 * nested detail routes that have no more-specific navigation item.
 */
const visibleRouteItems = computed<NavigationItem[]>(() =>
    items.value.flatMap((item) => [
        ...(item.href ? [item] : []),
        ...(item.children?.filter((child) => canView(child) && Boolean(child.href)) ?? []),
    ]),
);

function matchesPath(href: string, path: string): boolean {
    const normalizedHref = href.replace(/\/+$/, '') || '/';
    return path === normalizedHref || path.startsWith(`${normalizedHref}/`);
}

function isPathActive(item: NavigationItem): boolean {
    if (!item.href) return false;
    const href = item.href.replace(/\/+$/, '') || '/';
    if (!matchesPath(href, currentPath.value)) return false;

    const moreSpecificMatch = visibleRouteItems.value.some((candidate) => {
        if (candidate.label === item.label || !candidate.href) return false;
        const candidateHref = candidate.href.replace(/\/+$/, '') || '/';
        return candidateHref.length > href.length && matchesPath(candidateHref, currentPath.value);
    });

    return !moreSpecificMatch;
}

function hasActiveChild(item: NavigationItem): boolean {
    return Boolean(item.children?.some((child) => canView(child) && isPathActive(child)));
}

function isGroupOpen(item: NavigationItem): boolean {
    return hasActiveChild(item) || manualOpenGroups.value.includes(item.label);
}

function toggleGroup(item: NavigationItem): void {
    if (!item.expandable || hasActiveChild(item)) return;
    manualOpenGroups.value = isGroupOpen(item)
        ? manualOpenGroups.value.filter((label) => label !== item.label)
        : [...manualOpenGroups.value, item.label];
}

function handleNavigation(): void {
    emit('closeMobile');
}

function hasChildren(item: NavigationItem): boolean {
    return Boolean(item.children?.some((child) => canView(child)));
}

function isExpandableGroup(item: NavigationItem): boolean {
    return Boolean(item.expandable && hasChildren(item));
}

function itemHref(item: NavigationItem): string {
    return item.href || '#';
}
</script>

<template>
    <aside class="erp-sidebar" :class="{ 'is-collapsed': collapsed, 'is-mobile-open': mobileOpen }">
        <button class="sidebar-brand" type="button"
            :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            :aria-expanded="!collapsed" @click="emit('toggle')">
            <span class="brand-mark" aria-hidden="true">
                <img src="/images/madina-scale-icon.png" alt="" class="brand-logo" />
            </span>
            <span class="brand-copy">
                <strong>MADINA SCALE</strong>
                <small> WEIGHING A BETTER TOMORROW </small>
            </span>
        </button>

        <nav class="sidebar-nav" aria-label="Main navigation" tabindex="0">
            <template v-for="item in items" :key="item.label">
                <button v-if="isExpandableGroup(item)" class="nav-item nav-group-trigger"
                    :class="{ 'is-open': isGroupOpen(item), 'has-active-child': hasActiveChild(item) }"
                    type="button" :aria-expanded="isGroupOpen(item)"
                    :aria-controls="`sidebar-group-${item.label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`"
                    @click="toggleGroup(item)">
                    <Icon :name="item.icon" :size="18" />
                    <span class="nav-label">{{ item.label }}</span>
                    <span v-if="item.badge" class="nav-badge">{{ item.badge }}</span>
                    <Icon class="nav-chevron" :class="{ rotated: isGroupOpen(item) }"
                        name="chevron-right" :size="13" />
                </button>

                <Link v-else class="nav-item"
                    :class="{ active: isPathActive(item), 'is-disabled': !item.href }"
                    :href="itemHref(item)" :aria-current="isPathActive(item) ? 'page' : undefined"
                    :aria-disabled="!item.href ? 'true' : undefined"
                    @click="item.href ? handleNavigation() : undefined">
                    <Icon :name="item.icon" :size="18" />
                    <span class="nav-label">{{ item.label }}</span>
                    <span v-if="item.badge" class="nav-badge">{{ item.badge }}</span>
                </Link>

                <Transition name="sidebar-subnav">
                    <div v-if="hasChildren(item) && isGroupOpen(item) && !collapsed"
                        :id="`sidebar-group-${item.label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`"
                        class="sidebar-subnav">
                        <template v-for="child in item.children" :key="child.label">
                            <Link v-if="canView(child)" class="nav-subitem"
                                :class="{ active: isPathActive(child) }" :href="itemHref(child)"
                                :aria-current="isPathActive(child) ? 'page' : undefined"
                                @click="handleNavigation()">
                                <Icon :name="child.icon" :size="16" />
                                <span class="nav-subitem-label">{{ child.label }}</span>
                                <span v-if="child.badge" class="nav-badge nav-sub-badge">{{ child.badge }}</span>
                            </Link>
                        </template>
                    </div>
                </Transition>
            </template>
        </nav>

        <div class="sidebar-footer">
            <div style="width: 27px; height: 27px; border-radius: 50%" aria-hidden="true">
                <img style="width: 27px; height: 27px; border-radius: 50%"
                    src="/images/madina-scale-icon.png" alt="" class="brand-logo" />
            </div>
            <div class="footer-copy">
                <strong>Madina Scale &amp; Co.</strong>
                <span>Version &nbsp; - &nbsp;
                    <small style="color: #00a652; font-weight: bold">{{ appVersion }}</small>
                </span>
            </div>
        </div>
    </aside>

    <button v-if="mobileOpen" class="sidebar-scrim" type="button"
        aria-label="Close menu" @click="emit('closeMobile')"></button>
</template>

<style scoped>
.nav-group-trigger,.nav-item{font-family:inherit;font-size:inherit;font-weight:inherit;letter-spacing:inherit;line-height:inherit}
.nav-group-trigger{width:100%;border:0;background:transparent;color:inherit;font:inherit;text-align:left;cursor:pointer;appearance:none}
.nav-group-trigger.is-open:not(.has-active-child){background:rgba(255,255,255,.025)}
.nav-group-trigger.has-active-child{color:#eaf3f9;background:transparent}
.nav-chevron{flex:0 0 auto;margin-left:auto;opacity:.78;transition:transform .2s ease,opacity .18s ease}
.nav-chevron.rotated{transform:rotate(90deg)}
.nav-item.is-disabled{cursor:default;opacity:.72}
.nav-item.is-disabled:hover{background:transparent;color:inherit}
.sidebar-subnav{position:relative;display:grid;gap:4px;margin:3px 0 7px 20px;padding:2px 0 2px 10px}
.sidebar-subnav::before{content:'';position:absolute;left:1px;top:5px;bottom:5px;width:1px;background:rgba(255,255,255,.08)}
.nav-subitem{position:relative;display:flex;align-items:center;min-height:40px;gap:10px;padding:0 12px;border-radius:9px;color:#8fa0b2;font-size:12px;font-weight:600;line-height:1;text-decoration:none;transition:background-color .18s ease,color .18s ease,box-shadow .18s ease,transform .18s ease}
.nav-subitem:hover{background:rgba(255,255,255,.045);color:#eef7f5}
.nav-subitem.active{color:#fff;background:linear-gradient(90deg,#09a96e 0%,#08a66b 100%);box-shadow:0 5px 14px rgba(9,169,110,.18);font-weight:650}
.nav-subitem.active::before{content:'';position:absolute;left:0;top:7px;bottom:7px;width:3px;border-radius:0 3px 3px 0;background:#fff}
.nav-subitem-label{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nav-subitem .nav-badge{margin-left:auto}
.sidebar-subnav-enter-active,.sidebar-subnav-leave-active{overflow:hidden;max-height:160px;transition:opacity .18s ease,transform .18s ease,max-height .22s ease}
.sidebar-subnav-enter-from,.sidebar-subnav-leave-to{max-height:0;opacity:0;transform:translateY(-4px)}
.brand-mark{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;flex:0 0 42px;overflow:hidden;border-radius:11px;background:#08a96d}
.brand-logo{display:block;width:100%;height:100%;object-fit:cover}
.erp-sidebar.is-collapsed .brand-mark{width:38px;height:38px;flex-basis:38px}
.sidebar-footer{display:flex;align-items:center;gap:10px;margin:10px 10px 14px;padding:10px 9px;border-radius:10px;border:1px solid rgba(255,255,255,.06);background:rgba(255,255,255,.025)}
.footer-copy{min-width:0;display:flex;flex-direction:column;gap:2px}
.footer-copy strong{overflow:hidden;color:#f4f8fb;font-size:12px;font-weight:650;line-height:1.25;text-overflow:ellipsis;white-space:nowrap}
.footer-copy span{color:#7e90a3;font-size:10px;font-weight:500;line-height:1.2}
@media(prefers-reduced-motion:reduce){.nav-chevron,.nav-subitem,.sidebar-subnav-enter-active,.sidebar-subnav-leave-active{transition:none}}
</style>
