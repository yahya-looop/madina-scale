<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

import Icon from '@/components/dashboard/Icon.vue';
import { useAuthorization } from '@/composables/useAuthorization';
import {
    primaryNavigation,
    type NavigationItem,
} from '@/config/navigation';

defineProps<{
    collapsed: boolean;
    mobileOpen: boolean;
}>();

const emit = defineEmits<{
    closeMobile: [];
    toggle: [];
}>();

const page = usePage();
const { isDeveloper, can } = useAuthorization();

const manualOpenGroups = ref<string[]>([]);

/**
 * Navigation permission mapping.
 *
 * This keeps the existing navigation.ts backward compatible while
 * allowing Sidebar to enforce the permissions that already exist
 * in the RBAC system.
 *
 * As more permissions/modules are implemented, add them here or
 * move them directly into navigation.ts later.
 */
const navigationPermissions: Record<string, string> = {
    '/users': 'system.users.view',
    '/settings/roles-permissions': 'access.roles.view',
};

/**
 * Optional module-level permission mapping for top-level navigation.
 *
 * These are intentionally only applied where the corresponding
 * permission is already known. Unknown/future modules remain visible
 * until their RBAC permissions are defined.
 */
const navigationGroupPermissions: Record<string, string> = {
    'Team & Users': 'system.users.view',
    Settings: 'access.roles.view',
};

/**
 * Current Inertia URL.
 *
 * Unlike window.location.pathname, page.url updates correctly after
 * Inertia navigation without requiring a full page reload.
 */
const currentPath = computed(() => {
    const url = page.url || '/';

    try {
        return new URL(url, window.location.origin).pathname;
    } catch {
        return url.split('?')[0] || '/';
    }
});

/**
 * Resolve the permission required by a navigation item.
 */
function getPermission(item: NavigationItem): string | null {
    if (item.href && navigationPermissions[item.href]) {
        return navigationPermissions[item.href];
    }

    if (navigationGroupPermissions[item.label]) {
        return navigationGroupPermissions[item.label];
    }

    return null;
}

/**
 * Check whether the current user can see a navigation item.
 *
 * Developer is intentionally allowed to see everything.
 * This matches the backend AuthorizationService behavior.
 */
function canView(item: NavigationItem): boolean {
    if (isDeveloper.value) {
        return true;
    }

    const permission = getPermission(item);

    if (!permission) {
        return true;
    }

    return can(permission);
}

/**
 * Check whether a navigation item or any of its children is visible.
 */
function hasVisibleChildren(item: NavigationItem): boolean {
    return Boolean(
        item.children?.some((child) => canView(child)),
    );
}

/**
 * Navigation visibility.
 *
 * A group is shown when:
 * - the current user can access the group itself, or
 * - at least one child is visible.
 */
const items = computed<NavigationItem[]>(() => {
    return primaryNavigation.filter((item) => {
        if (isDeveloper.value) {
            return true;
        }

        if (canView(item)) {
            return true;
        }

        return hasVisibleChildren(item);
    });
});

/**
 * Determine whether an item points to the current route.
 */
function isPathActive(item: NavigationItem): boolean {
    if (!item.href) {
        return false;
    }

    const path = currentPath.value;

    return (
        path === item.href ||
        path.startsWith(`${item.href}/`)
    );
}

/**
 * Determine whether any visible child is currently active.
 */
function hasActiveChild(item: NavigationItem): boolean {
    return Boolean(
        item.children?.some(
            (child) =>
                canView(child) &&
                isPathActive(child),
        ),
    );
}

/**
 * Determine whether a group is currently expanded.
 */
function isGroupOpen(item: NavigationItem): boolean {
    return (
        hasActiveChild(item) ||
        manualOpenGroups.value.includes(item.label)
    );
}

/**
 * Toggle a navigation group.
 */
function toggleGroup(item: NavigationItem): void {
    if (!item.expandable) {
        return;
    }

    const currentlyOpen = isGroupOpen(item);

    manualOpenGroups.value = currentlyOpen
        ? manualOpenGroups.value.filter(
              (label) => label !== item.label,
          )
        : [
              ...manualOpenGroups.value,
              item.label,
          ];
}

/**
 * Handle mobile navigation close.
 *
 * Inertia Link handles the actual navigation.
 */
function handleNavigation(): void {
    emit('closeMobile');
}

/**
 * Determine whether a group actually has children.
 *
 * Some navigation entries are marked expandable for future modules.
 * Until children are defined, they are rendered as a normal item
 * instead of displaying a dead expandable control.
 */
function hasChildren(item: NavigationItem): boolean {
    return Boolean(
        item.children?.some((child) => canView(child)),
    );
}

/**
 * Determine whether an item should behave as a group.
 */
function isExpandableGroup(item: NavigationItem): boolean {
    return Boolean(
        item.expandable &&
            hasChildren(item),
    );
}

/**
 * Build a safe route for items that do not yet have a destination.
 */
function itemHref(item: NavigationItem): string {
    return item.href || '#';
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
    <!-- Brand / Collapse -->
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

    <!-- Main Navigation -->
    <nav class="sidebar-nav" aria-label="Main navigation" tabindex="0">
      <template v-for="item in items" :key="item.label">
        <!-- Expandable Group -->
        <button
          v-if="isExpandableGroup(item)"
          class="nav-item nav-group-trigger"
          :class="{
            active: hasActiveChild(item),
            'is-open': isGroupOpen(item),
          }"
          type="button"
          :aria-expanded="isGroupOpen(item)"
          :aria-controls="`sidebar-group-${item.label
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')}`"
          @click="toggleGroup(item)"
        >
          <Icon :name="item.icon" :size="18" />

          <span class="nav-label">
            {{ item.label }}
          </span>

          <span v-if="item.badge" class="nav-badge">
            {{ item.badge }}
          </span>

          <Icon
            class="nav-chevron"
            :class="{
              rotated: isGroupOpen(item),
            }"
            name="chevron-right"
            :size="13"
          />
        </button>

        <!-- Normal Navigation Item -->
        <Link
          v-else
          class="nav-item"
          :class="{
            active: isPathActive(item),
            'is-disabled': !item.href,
          }"
          :href="itemHref(item)"
          :aria-current="isPathActive(item) ? 'page' : undefined"
          :aria-disabled="!item.href ? 'true' : undefined"
          @click="item.href ? handleNavigation() : undefined"
        >
          <Icon :name="item.icon" :size="18" />

          <span class="nav-label">
            {{ item.label }}
          </span>

          <span v-if="item.badge" class="nav-badge">
            {{ item.badge }}
          </span>
        </Link>

        <!-- Sub Navigation -->
        <Transition name="sidebar-subnav">
          <div
            v-if="hasChildren(item) && isGroupOpen(item) && !collapsed"
            :id="`sidebar-group-${item.label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`"
            class="sidebar-subnav"
          >
            <template v-for="child in item.children" :key="child.label">
              <Link
                v-if="canView(child)"
                class="nav-subitem"
                :class="{
                  active: isPathActive(child),
                }"
                :href="itemHref(child)"
                :aria-current="isPathActive(child) ? 'page' : undefined"
                @click="handleNavigation()"
              >
                <span class="nav-subline" aria-hidden="true"></span>

                <Icon :name="child.icon" :size="15" />

                <span>
                  {{ child.label }}
                </span>

                <span v-if="child.badge" class="nav-badge nav-sub-badge">
                  {{ child.badge }}
                </span>
              </Link>
            </template>
          </div>
        </Transition>
      </template>
    </nav>

    <!-- System Status -->
    <div class="sidebar-status">
      <span class="status-dot" aria-hidden="true"></span>

      <div>
        <strong>System Status</strong>
        <span>All Systems Operational</span>
      </div>
    </div>

    <!-- Footer -->
    <div class="sidebar-footer">
      <div class="footer-mark">M</div>

      <div class="footer-copy">
        <strong>Madina Scale</strong>
        <span>Enterprise Dashboard</span>
      </div>

      <small>v1.0.0</small>
    </div>
  </aside>

  <!-- Mobile Overlay -->
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
  flex: 0 0 auto;
  margin-left: auto;
  transition: transform 0.2s ease, opacity 0.18s ease;
}

.nav-chevron.rotated {
  transform: rotate(90deg);
}

.nav-item.is-disabled {
  cursor: default;
  opacity: 0.72;
}

.nav-item.is-disabled:hover {
  background: transparent;
  color: inherit;
}

/* ----------------------------------------
   Sub Navigation
---------------------------------------- */

.sidebar-subnav {
  position: relative;
  display: grid;
  gap: 2px;
  margin: 1px 0 5px 20px;
  padding: 2px 0 2px 10px;
}

.sidebar-subnav::before {
  content: "";
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
  transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
}

.nav-subitem:hover {
  background: rgba(255, 255, 255, 0.045);
  color: #eaf3f9;
}

.nav-subitem.active {
  color: #ffffff;
  background: linear-gradient(90deg, rgba(9, 169, 110, 0.22), rgba(9, 169, 110, 0.08));
  box-shadow: inset 2px 0 0 #09a96e;
}

.nav-subitem .nav-badge {
  margin-left: auto;
}

.nav-subline {
  width: 4px;
  height: 4px;
  flex: 0 0 4px;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.6;
}

/* ----------------------------------------
   Sub Navigation Animation
---------------------------------------- */

.sidebar-subnav-enter-active,
.sidebar-subnav-leave-active {
  overflow: hidden;
  max-height: 160px;
  transition: opacity 0.18s ease, transform 0.18s ease, max-height 0.22s ease;
}

.sidebar-subnav-enter-from,
.sidebar-subnav-leave-to {
  max-height: 0;
  opacity: 0;
  transform: translateY(-4px);
}

/* ----------------------------------------
   Accessibility / Reduced Motion
---------------------------------------- */

@media (prefers-reduced-motion: reduce) {
  .nav-chevron,
  .nav-subitem,
  .sidebar-subnav-enter-active,
  .sidebar-subnav-leave-active {
    transition: none;
  }
}
</style>
