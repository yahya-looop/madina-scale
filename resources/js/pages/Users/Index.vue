<script setup lang="ts">
import { computed, defineComponent, h, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
interface Role {
  id: number;
  name: string;
  slug: string;
  isSystem: boolean;
  isActive: boolean;
}
interface User {
  id: number;
  name: string;
  email: string;
  status: 'active' | 'inactive';
  roles?: Role[];
  last_login_at?: string | null;
  last_login_ip?: string | null;
  failed_login_attempts?: number;
  locked_until?: string | null;
  password_changed_at?: string | null;
  email_verified_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}
interface Capabilities {
  canCreate: boolean;
  canUpdate: boolean;
  canView: boolean;
  isDeveloper?: boolean;
}
interface Props {
  users?: User[];
  roles?: Role[];
  capabilities?: Capabilities;
  userName?: string;
  userEmail?: string;
}
/* Page-local icons avoid dependencies on shared icon names. */
type IconName =
  | 'users'
  | 'refresh'
  | 'plus'
  | 'check'
  | 'lock'
  | 'search'
  | 'close'
  | 'eye'
  | 'edit'
  | 'chevron-right'
  | 'developer';
const iconPaths: Record<IconName, ReturnType<typeof h>[]> = {
  users: [
    h('path', {
      d: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2',
    }),
    h('circle', {
      cx: '9',
      cy: '7',
      r: '4',
    }),
    h('path', {
      d: 'M22 21v-2a4 4 0 0 0-3-3.87',
    }),
    h('path', {
      d: 'M16 3.13a4 4 0 0 1 0 7.75',
    }),
  ],
  refresh: [
    h('path', {
      d: 'M20 11a8.1 8.1 0 0 0-15.5-2',
    }),
    h('path', {
      d: 'M4 4v5h5',
    }),
    h('path', {
      d: 'M4 13a8.1 8.1 0 0 0 15.5 2',
    }),
    h('path', {
      d: 'M20 20v-5h-5',
    }),
  ],
  plus: [
    h('path', {
      d: 'M12 5v14',
    }),
    h('path', {
      d: 'M5 12h14',
    }),
  ],
  check: [
    h('path', {
      d: 'm5 12 4 4L19 6',
    }),
  ],
  lock: [
    h('rect', {
      x: '4',
      y: '10',
      width: '16',
      height: '11',
      rx: '2',
    }),
    h('path', {
      d: 'M8 10V7a4 4 0 0 1 8 0v3',
    }),
  ],
  search: [
    h('circle', {
      cx: '11',
      cy: '11',
      r: '7',
    }),
    h('path', {
      d: 'm20 20-4-4',
    }),
  ],
  close: [
    h('path', {
      d: 'm6 6 12 12',
    }),
    h('path', {
      d: 'm18 6-12 12',
    }),
  ],
  eye: [
    h('path', {
      d: 'M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z',
    }),
    h('circle', {
      cx: '12',
      cy: '12',
      r: '2.5',
    }),
  ],
  edit: [
    h('path', {
      d: 'M12 20h9',
    }),
    h('path', {
      d: 'M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z',
    }),
  ],
  'chevron-right': [
    h('path', {
      d: 'm9 18 6-6-6-6',
    }),
  ],
  developer: [
    h('path', {
      d: 'M8 3h8l3 3v15H5V3h3Z',
    }),
    h('path', {
      d: 'M8 3v5h8V3',
    }),
    h('path', {
      d: 'M9 13h6',
    }),
    h('path', {
      d: 'M9 16h4',
    }),
  ],
};
const AppIcon = defineComponent({
  name: 'UsersPageIcon',
  props: {
    name: {
      type: String as () => IconName,
      required: true,
    },
    size: {
      type: Number,
      default: 16,
    },
    strokeWidth: {
      type: Number,
      default: 1.8,
    },
  },
  setup(iconProps) {
    return () =>
      h(
        'svg',
        {
          width: iconProps.size,
          height: iconProps.size,
          viewBox: '0 0 24 24',
          fill: 'none',
          stroke: 'currentColor',
          'stroke-width': iconProps.strokeWidth,
          'stroke-linecap': 'round',
          'stroke-linejoin': 'round',
          'aria-hidden': 'true',
          focusable: 'false',
        },
        iconPaths[iconProps.name],
      );
  },
});
const props = withDefaults(defineProps<Props>(), {
  users: () => [],
  roles: () => [],
  capabilities: () => ({
    canCreate: false,
    canUpdate: false,
    canView: false,
    isDeveloper: false,
  }),
  userName: '',
  userEmail: '',
});
const search = ref('');
const statusFilter = ref<'all' | 'active' | 'inactive'>('all');
const isLoading = ref(false);
const operationMessage = ref('');
const operationMessageType = ref<'success' | 'error'>('success');
const localUsers = ref<User[]>([...props.users]);
const filteredUsers = computed(() => {
  const query = search.value.trim().toLowerCase();
  return localUsers.value.filter((user) => {
    const searchable = [
      user.name,
      user.email,
      ...(user.roles ?? []).map((role) => role.name),
    ]
      .join(' ')
      .toLowerCase();
    const matchesSearch = query.length === 0 || searchable.includes(query);
    const matchesStatus =
      statusFilter.value === 'all' || user.status === statusFilter.value;
    return matchesSearch && matchesStatus;
  });
});
const totalUsers = computed(() => localUsers.value.length);
const activeUsers = computed(
  () => localUsers.value.filter((user) => user.status === 'active').length,
);
const inactiveUsers = computed(
  () => localUsers.value.filter((user) => user.status === 'inactive').length,
);
function initials(name: string): string {
  return (
    name
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join('') || '?'
  );
}
function formatDate(value?: string | null): string {
  if (!value) {
    return '—';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  }).format(date);
}
function formatLastLogin(value?: string | null): string {
  if (!value) {
    return 'Never';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return 'Never';
  }
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}
function formatLastLoginIp(value?: string | null): string {
  return value ?? '';
}
/* Refresh current user data from the API. */
async function refreshUsers(): Promise<void> {
  isLoading.value = true;
  operationMessage.value = '';
  try {
    const response = await fetch('/api/v1/backend/users', {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      cache: 'no-store',
    });
    const payload = (await response.json()) as {
      data?: User[];
      message?: string;
    };
    if (!response.ok) {
      throw new Error(payload.message ?? 'Unable to load users.');
    }
    localUsers.value = Array.isArray(payload.data) ? payload.data : [];
    operationMessageType.value = 'success';
  } catch (error) {
    operationMessageType.value = 'error';
    operationMessage.value =
      error instanceof Error ? error.message : 'Unable to load users.';
  } finally {
    isLoading.value = false;
  }
}
function openCreate(): void {
  if (!props.capabilities.canCreate) {
    return;
  }
  router.visit('/backend/users/create');
}
function openUser(user: User): void {
  if (!props.capabilities.canView) {
    return;
  }
  router.visit(`/backend/users/${user.id}`);
}
function editUser(user: User): void {
  if (!props.capabilities.canUpdate) {
    return;
  }
  router.visit(`/backend/users/${user.id}/edit`);
}
function clearFilters(): void {
  search.value = '';
  statusFilter.value = 'all';
}
/* Refresh persisted data on mount so login metadata stays current. */
onMounted(() => {
  void refreshUsers();
});
</script>
<template>
  <Head title="Users" />
  <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
    <div class="users-page">
      <!-- =========================================================
        PAGE HEADER
        ========================================================= -->
      <header class="page-header">
        <div class="page-header-copy">
          <nav class="page-breadcrumb" aria-label="Breadcrumb">
            <span>Identity &amp; Access</span>
            <AppIcon
              name="chevron-right"
              :size="12"
              class="breadcrumb-icon"
            />
            <span class="is-current"> Users </span>
          </nav>
          <div class="page-title-row">
            <div class="page-title-icon" aria-hidden="true">
              <AppIcon name="users" :size="21" />
            </div>
            <div>
              <h1>User Management</h1>
              <p>
                Manage platform users, account status and
                assigned roles from one workspace.
              </p>
            </div>
          </div>
        </div>
        <div class="header-actions">
          <button
            class="btn btn-secondary"
            type="button"
            :disabled="isLoading"
            @click="refreshUsers"
          >
            <AppIcon name="refresh" :size="15" />
            <span>
              {{ isLoading ? 'Refreshing...' : 'Refresh' }}
            </span>
          </button>
          <button
            v-if="props.capabilities.canCreate"
            class="btn btn-primary"
            type="button"
            @click="openCreate"
          >
            <AppIcon name="plus" :size="15" />
            <span>Add user</span>
          </button>
        </div>
      </header>
      <!-- =========================================================
        OPERATION MESSAGE
        ========================================================= -->
      <div
        v-if="operationMessage"
        class="operation-message"
        :class="operationMessageType"
        role="status"
      >
        <AppIcon
          :name="operationMessageType === 'error' ? 'close' : 'check'"
          :size="15"
        />
        <span>{{ operationMessage }}</span>
      </div>
      <!-- =========================================================
        STATISTICS
        ========================================================= -->
      <section class="stat-grid" aria-label="User statistics">
        <article class="stat-card">
          <div class="stat-icon">
            <AppIcon name="users" :size="17" />
          </div>
          <div>
            <span>Total users</span>
            <strong>{{ totalUsers }}</strong>
          </div>
        </article>
        <article class="stat-card">
          <div class="stat-icon active">
            <AppIcon name="check" :size="17" />
          </div>
          <div>
            <span>Active</span>
            <strong>{{ activeUsers }}</strong>
          </div>
        </article>
        <article class="stat-card">
          <div class="stat-icon inactive">
            <AppIcon name="lock" :size="17" />
          </div>
          <div>
            <span>Inactive</span>
            <strong>{{ inactiveUsers }}</strong>
          </div>
        </article>
      </section>
      <!-- =========================================================
        USERS WORKSPACE
        ========================================================= -->
      <section class="workspace-card" aria-labelledby="users-table-title">
        <h2 id="users-table-title" class="sr-only">User list</h2>
        <!-- Toolbar -->
        <div class="toolbar">
          <label class="search-field">
            <AppIcon name="search" :size="16" />
            <input
              v-model="search"
              type="search"
              placeholder="Search by name, email or role..."
              autocomplete="off"
            />
          </label>
          <div class="toolbar-actions">
            <label class="sr-only" for="user-status-filter">
              Filter users by status
            </label>
            <select id="user-status-filter" v-model="statusFilter">
              <option value="all">All statuses</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <button
              v-if="search || statusFilter !== 'all'"
              class="btn btn-ghost"
              type="button"
              @click="clearFilters"
            >
              <AppIcon name="close" :size="14" />
              <span>Clear</span>
            </button>
          </div>
        </div>
        <!-- Table -->
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>User</th>
                <th>Status</th>
                <th>Roles</th>
                <th>Last Login</th>
                <th>Created</th>
                <th class="actions-column">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="user in filteredUsers"
                :key="user.id"
                :class="{ 'is-clickable': props.capabilities.canView }"
                :tabindex="props.capabilities.canView ? 0 : undefined"
                :aria-label="props.capabilities.canView ? `View details for ${user.name}` : undefined"
                @click="openUser(user)"
                @keydown.enter.prevent="openUser(user)"
                @keydown.space.prevent="openUser(user)"
              >
                <!-- User -->
                <td>
                  <button
                    class="user-cell"
                    type="button"
                    :disabled="!props.capabilities.canView"
                    @click.stop="openUser(user)"
                  >
                    <span class="avatar">
                      {{ initials(user.name) }}
                    </span>
                    <span class="user-copy">
                      <strong>
                        {{ user.name }}
                      </strong>
                      <small>
                        {{ user.email }}
                      </small>
                    </span>
                  </button>
                </td>
                <!-- Status -->
                <td>
                  <span
                    class="status-badge"
                    :class="user.status"
                  >
                    <i></i>
                    {{ user.status }}
                  </span>
                </td>
                <!-- Roles -->
                <td>
                  <div class="roles-cell">
                    <span
                      v-for="role in (
                        user.roles ?? []
                      ).slice(0, 2)"
                      :key="role.id"
                      class="role-chip"
                      :class="{
                        system: role.isSystem,
                      }"
                    >
                      {{ role.name }}
                    </span>
                    <span
                      v-if="(user.roles ?? []).length > 2"
                      class="role-more"
                    >
                      +{{ (user.roles ?? []).length - 2 }}
                    </span>
                    <span
                      v-if="
                        (user.roles ?? []).length === 0
                      "
                      class="muted"
                    >
                      No role
                    </span>
                  </div>
                </td>
                <!-- Last Login -->
                <td class="last-login-cell">
                  <div class="last-login">
                    <span
                      class="last-login-time"
                      :class="{
                        never: !user.last_login_at,
                      }"
                    >
                      {{
                        formatLastLogin(
                          user.last_login_at,
                        )
                      }}
                    </span>
                    <small
                      v-if="user.last_login_ip"
                      class="last-login-ip"
                    >
                      {{
                        formatLastLoginIp(
                          user.last_login_ip,
                        )
                      }}
                    </small>
                  </div>
                </td>
                <!-- Created -->
                <td class="date-cell">
                  {{ formatDate(user.created_at) }}
                </td>
                <!-- Actions -->
                <td class="actions-column">
                  <div class="row-actions">
                    <button
                      v-if="props.capabilities.canView"
                      class="icon-btn"
                      type="button"
                      title="View user"
                      aria-label="View user"
                      @click.stop="openUser(user)"
                    >
                      <AppIcon name="eye" :size="15" />
                    </button>
                    <button
                      v-if="props.capabilities.canUpdate"
                      class="icon-btn"
                      type="button"
                      title="Edit user"
                      aria-label="Edit user"
                      @click.stop="editUser(user)"
                    >
                      <AppIcon name="edit" :size="15" />
                    </button>
                  </div>
                </td>
              </tr>
              <!-- Empty state -->
              <tr v-if="filteredUsers.length === 0">
                <td colspan="6">
                  <div class="empty-state">
                    <div class="empty-icon">
                      <AppIcon name="users" :size="20" />
                    </div>
                    <strong> No users found </strong>
                    <p>
                      Try another search or clear the
                      current filters.
                    </p>
                    <button
                      v-if="
                        search || statusFilter !== 'all'
                      "
                      class="btn btn-ghost empty-clear"
                      type="button"
                      @click="clearFilters"
                    >
                      Clear filters
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Footer -->
        <footer class="table-footer">
          <span>
            Showing
            <strong>
              {{ filteredUsers.length }}
            </strong>
            of
            <strong>
              {{ totalUsers }}
            </strong>
            users
          </span>
          <span
            v-if="props.capabilities.isDeveloper"
            class="developer-note"
          >
            <AppIcon name="developer" :size="13" />
            Developer access
          </span>
        </footer>
      </section>
    </div>
  </ErpLayout>
</template>
<style scoped>
/* ================================================================
 PAGE
 ================================================================ */
.users-page {
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  color: var(--text-primary, #17202a);
  padding: 0;
  animation: users-page-enter 500ms cubic-bezier(0.22, 1, 0.36, 1) both;
}
/* ================================================================
 ACCESSIBILITY
 ================================================================ */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
/* ================================================================
 PAGE HEADER
 ================================================================ */
.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 18px;
  animation: users-header-enter 480ms cubic-bezier(0.22, 1, 0.36, 1) 20ms both;
}
.page-header-copy {
  min-width: 0;
}
.page-breadcrumb {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-bottom: 9px;
  color: var(--text-tertiary, #7b8794);
  font-size: 11px;
  font-weight: 600;
  line-height: 1.2;
}
.page-breadcrumb .is-current {
  color: var(--text-secondary, #596573);
}
.breadcrumb-icon {
  flex: 0 0 auto;
  color: var(--border-strong, #cdd4dd);
}
.page-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}
.page-title-icon {
  display: inline-flex;
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  align-items: center;
  justify-content: center;
  border: 1px solid
    color-mix(in srgb, var(--primary, #1f8f68) 22%, var(--border, #e5e9ee));
  border-radius: 12px;
  color: var(--primary, #1f8f68);
  background: color-mix(
    in srgb,
    var(--primary, #1f8f68) 10%,
    var(--surface, #ffffff)
  );
}
.page-title-row h1 {
  margin: 0;
  color: var(--text-primary, #17202a);
  font-size: 23px;
  font-weight: 750;
  line-height: 1.15;
  letter-spacing: -0.025em;
}
.page-title-row p {
  max-width: 620px;
  margin: 5px 0 0;
  color: var(--text-secondary, #596573);
  font-size: 12px;
  line-height: 1.5;
}
/* ================================================================
 HEADER ACTIONS
 ================================================================ */
.header-actions,
.toolbar-actions,
.row-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 38px;
  padding: 0 13px;
  border: 1px solid transparent;
  border-radius: 9px;
  font: inherit;
  font-size: 12px;
  font-weight: 650;
  cursor: pointer;
  transition:
    transform 140ms ease,
    border-color 140ms ease,
    background 140ms ease,
    color 140ms ease,
    opacity 140ms ease,
    box-shadow 140ms ease;
}
.btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}
.btn:not(:disabled):hover {
  transform: translateY(-1px);
}
.btn-primary {
  color: #ffffff;
  background: var(--primary, #1f8f68);
  box-shadow: 0 5px 14px
    color-mix(in srgb, var(--primary, #1f8f68) 18%, transparent);
}
.btn-secondary,
.btn-ghost {
  color: var(--text-primary, #17202a);
  border-color: var(--border, #e5e9ee);
  background: var(--surface, #ffffff);
}
.btn-ghost {
  background: transparent;
}
.btn-secondary:hover,
.btn-ghost:hover {
  border-color: var(--border-strong, #d4dbe3);
  background: var(--surface-muted, #f8fafb);
}
/* ================================================================
 OPERATION MESSAGE
 ================================================================ */
.operation-message {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 18px;
  padding: 11px 14px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 10px;
  font-size: 13px;
}
.operation-message.error {
  border-color: #f1b8b8;
  color: #a52d2d;
  background: #fff6f6;
}
/* ================================================================
 STATISTICS
 ================================================================ */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 14px;
}
.stat-card {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 70px;
  padding: 12px 14px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 12px;
  background: var(--surface, #ffffff);
  box-shadow: 0 5px 18px rgb(15 23 42 / 3%);
}
.stat-icon {
  display: grid;
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  place-items: center;
  border-radius: 10px;
  color: var(--text-secondary, #596573);
  background: var(--surface-muted, #f3f5f7);
}
.stat-icon.active {
  color: #19734f;
  background: #eaf7f1;
}
.stat-icon.inactive {
  color: #9a6518;
  background: #fff5e4;
}
.stat-card span {
  display: block;
  color: var(--text-tertiary, #7b8794);
  font-size: 12px;
}
.stat-card strong {
  display: block;
  margin-top: 3px;
  color: var(--text-primary, #17202a);
  font-size: 21px;
  font-weight: 750;
  line-height: 1.1;
}
/* ================================================================
 WORKSPACE
 ================================================================ */
.workspace-card {
  overflow: hidden;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 14px;
  background: var(--surface, #ffffff);
  box-shadow: var(--shadow, 0 8px 24px rgb(15 23 42 / 4%));
  animation: workspace-enter 520ms cubic-bezier(0.22, 1, 0.36, 1) 80ms both;
}
/* ================================================================
 TOOLBAR
 ================================================================ */
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 15px;
  border-bottom: 1px solid var(--border, #e5e9ee);
}
.search-field {
  display: flex;
  align-items: center;
  gap: 9px;
  width: min(470px, 100%);
  height: 38px;
  padding: 0 10px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 8px;
  color: var(--text-tertiary, #7b8794);
  background: var(--surface-muted, #f8fafb);
  transition:
    border-color 140ms ease,
    box-shadow 140ms ease,
    background 140ms ease;
}
.search-field:focus-within {
  border-color: color-mix(
    in srgb,
    var(--primary, #1f8f68) 45%,
    var(--border, #e5e9ee)
  );
  background: var(--surface, #ffffff);
  box-shadow: 0 0 0 3px
    color-mix(in srgb, var(--primary, #1f8f68) 10%, transparent);
}
.search-field input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  color: inherit;
  background: transparent;
  font: inherit;
  font-size: 12px;
}
.search-field input::placeholder {
  color: var(--text-tertiary, #9aa4b2);
}
.toolbar select {
  height: 38px;
  padding: 0 34px 0 10px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 8px;
  outline: 0;
  color: var(--text-primary, #17202a);
  background: var(--surface, #ffffff);
  font: inherit;
  font-size: 12px;
  cursor: pointer;
}
/* ================================================================
 TABLE
 ================================================================ */
.table-wrap {
  overflow-x: auto;
}
table {
  width: 100%;
  border-collapse: collapse;
  border-spacing: 0;
}
th {
  padding: 12px 16px;
  color: var(--text-tertiary, #7b8794);
  background: var(--surface-muted, #fafbfc);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-align: left;
  text-transform: uppercase;
  white-space: nowrap;
}
td {
  padding: 13px 16px;
  border-top: 1px solid var(--border, #eef1f4);
  vertical-align: middle;
  color: var(--text-primary, #17202a);
  font-size: 13px;
}
tbody tr {
  transition: background 140ms ease;
}
tbody tr:hover {
  background: var(--surface-muted, #fafbfc);
}

tbody tr.is-clickable {
  cursor: pointer;
}

tbody tr.is-clickable:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--primary, #1f8f68) 65%, transparent);
  outline-offset: -2px;
}
/* ================================================================
 USER CELL
 ================================================================ */
.user-cell {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 0;
  border: 0;
  color: inherit;
  background: transparent;
  text-align: left;
  cursor: pointer;
}
.user-cell:disabled {
  cursor: default;
}
.avatar {
  display: grid;
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  place-items: center;
  border-radius: 11px;
  color: var(--primary, #1f8f68);
  background: var(--primary-soft, #eaf7f1);
  font-size: 12px;
  font-weight: 800;
}
.user-copy {
  display: grid;
  gap: 3px;
  min-width: 0;
}
.user-copy strong {
  overflow: hidden;
  max-width: 280px;
  color: var(--text-primary, #17202a);
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.user-copy small {
  overflow: hidden;
  max-width: 280px;
  color: var(--text-tertiary, #7b8794);
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
/* ================================================================
 STATUS
 ================================================================ */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  text-transform: capitalize;
}
.status-badge i {
  width: 6px;
  height: 6px;
  flex: 0 0 6px;
  border-radius: 50%;
  background: currentColor;
}
.status-badge.active {
  color: #17714e;
  background: #eaf7f1;
}
.status-badge.inactive {
  color: #9a6518;
  background: #fff5e4;
}
/* ================================================================
 ROLES
 ================================================================ */
.roles-cell {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  min-width: 190px;
}
.role-chip,
.role-more {
  display: inline-flex;
  align-items: center;
  min-height: 25px;
  padding: 0 8px;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 7px;
  color: var(--text-secondary, #596573);
  background: var(--surface-muted, #f8fafb);
  font-size: 11px;
  font-weight: 700;
}
.role-chip.system {
  color: #6c4b9e;
  border-color: #dfd3f0;
  background: #f7f2fc;
}
.role-more {
  color: var(--text-tertiary, #7b8794);
}
.muted {
  color: var(--text-tertiary, #7b8794);
}
/* ================================================================
 LAST LOGIN
 ================================================================ */
.last-login-cell {
  min-width: 155px;
}
.last-login {
  display: grid;
  gap: 3px;
}
.last-login-time {
  color: var(--text-secondary, #596573);
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}
.last-login-time.never {
  color: var(--text-tertiary, #9aa4b2);
  font-weight: 500;
}
.last-login-ip {
  color: var(--text-tertiary, #8a94a5);
  font-size: 10px;
  line-height: 1.2;
  white-space: nowrap;
}
/* ================================================================
 CREATED
 ================================================================ */
.date-cell {
  color: var(--text-secondary, #596573);
  white-space: nowrap;
}
/* ================================================================
 ACTIONS
 ================================================================ */
.actions-column {
  width: 110px;
  text-align: right;
}
.icon-btn {
  display: grid;
  width: 32px;
  height: 32px;
  place-items: center;
  border: 1px solid var(--border, #e5e9ee);
  border-radius: 8px;
  color: var(--text-secondary, #596573);
  background: var(--surface, #ffffff);
  cursor: pointer;
  transition:
    color 140ms ease,
    border-color 140ms ease,
    background 140ms ease,
    transform 140ms ease;
}
.icon-btn:hover {
  color: var(--primary, #1f8f68);
  border-color: color-mix(
    in srgb,
    var(--primary, #1f8f68) 50%,
    var(--border, #e5e9ee)
  );
  background: var(--surface-muted, #fafbfc);
  transform: translateY(-1px);
}
.icon-btn:focus-visible,
.btn:focus-visible,
.user-cell:focus-visible,
.search-field:focus-within,
.toolbar select:focus-visible {
  outline: 2px solid
    color-mix(in srgb, var(--primary, #1f8f68) 65%, transparent);
  outline-offset: 2px;
}
/* ================================================================
 EMPTY STATE
 ================================================================ */
.empty-state {
  display: grid;
  min-height: 250px;
  place-items: center;
  align-content: center;
  padding: 40px 20px;
  text-align: center;
}
.empty-icon {
  display: grid;
  width: 44px;
  height: 44px;
  margin-bottom: 10px;
  place-items: center;
  border-radius: 12px;
  color: var(--text-tertiary, #7b8794);
  background: var(--surface-muted, #f3f5f7);
}
.empty-state strong {
  color: var(--text-primary, #394457);
  font-size: 14px;
}
.empty-state p {
  margin: 6px 0 0;
  color: var(--text-tertiary, #7b8794);
  font-size: 12px;
}
.empty-clear {
  margin-top: 14px;
}
/* ================================================================
 FOOTER
 ================================================================ */
.table-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 12px 16px;
  border-top: 1px solid var(--border, #e5e9ee);
  color: var(--text-tertiary, #7b8794);
  font-size: 12px;
}
.table-footer strong {
  color: var(--text-secondary, #596573);
}
.developer-note {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #6c4b9e;
  font-weight: 700;
}
/* ================================================================
 PAGE ANIMATION
 ================================================================ */
@keyframes users-page-enter {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
@keyframes users-header-enter {
  from {
    opacity: 0;
    transform: translateY(7px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
@keyframes workspace-enter {
  from {
    opacity: 0;
    transform: translateY(9px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
/* ================================================================
 RESPONSIVE
 ================================================================ */
@media (max-width: 1100px) {
  .last-login-cell {
    min-width: 125px;
  }
  .last-login-ip {
    display: none;
  }
}
@media (max-width: 900px) {
  .page-header {
    align-items: flex-start;
    flex-direction: column;
  }
  .header-actions {
    width: 100%;
  }
  .stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .toolbar {
    align-items: stretch;
    flex-direction: column;
  }
  .search-field {
    width: 100%;
  }
  .toolbar-actions {
    justify-content: space-between;
  }
}
@media (max-width: 720px) {
  .page-title-row {
    align-items: flex-start;
  }
  .page-title-icon {
    width: 38px;
    height: 38px;
    flex-basis: 38px;
  }
  .page-title-row h1 {
    font-size: 20px;
  }
  .header-actions,
  .header-actions .btn {
    width: 100%;
  }
  .header-actions {
    flex-direction: column;
  }
  .toolbar-actions {
    align-items: stretch;
    flex-direction: column;
  }
  .toolbar select {
    width: 100%;
  }
  .table-footer {
    align-items: flex-start;
    flex-direction: column;
  }
}
@media (max-width: 560px) {
  .stat-grid {
    grid-template-columns: 1fr;
  }
  .page-header {
    gap: 18px;
    margin-bottom: 18px;
  }
  .page-title-row p {
    font-size: 12px;
  }
  .stat-card {
    min-height: 76px;
    padding: 14px;
  }
  th,
  td {
    padding-right: 12px;
    padding-left: 12px;
  }
}
/* ================================================================
 FINAL UI POLISH — user index workspace
 Presentation-only overrides; existing data and actions are preserved.
 ================================================================ */
.users-page {
  isolation: isolate;
  --users-accent: var(--primary, #1f8f68);
}
.page-header {
  position: relative;
  padding: 4px 0 8px;
}
.page-title-icon {
  box-shadow: 0 8px 20px color-mix(in srgb, var(--primary, #1f8f68) 12%, transparent);
}
.header-actions .btn {
  min-height: 40px;
}
.stat-card {
  position: relative;
  overflow: hidden;
  transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}
.stat-card::after {
  position: absolute;
  right: -18px;
  bottom: -25px;
  width: 78px;
  height: 78px;
  border: 1px solid color-mix(in srgb, var(--primary, #1f8f68) 10%, transparent);
  border-radius: 50%;
  content: "";
  pointer-events: none;
}
.stat-card:hover {
  transform: translateY(-2px);
  border-color: color-mix(in srgb, var(--primary, #1f8f68) 28%, var(--border, #e5e9ee));
  box-shadow: 0 10px 26px rgb(15 23 42 / 6%);
}
.workspace-card {
  border-radius: 16px;
  box-shadow: 0 12px 34px rgb(15 23 42 / 5%), 0 1px 2px rgb(15 23 42 / 3%);
}
.toolbar {
  background: linear-gradient(180deg, var(--surface, #fff), var(--surface-muted, #fafbfc));
}
.search-field,
.toolbar select {
  min-height: 40px;
  border-radius: 10px;
}
table {
  min-width: 880px;
}
thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  border-bottom: 1px solid var(--border, #e5e9ee);
}
tbody tr {
  transition: background-color 150ms ease;
}
tbody tr:hover {
  background: color-mix(in srgb, var(--primary, #1f8f68) 3%, var(--surface, #fff));
}
.user-cell {
  border-radius: 9px;
}
.user-cell:not(:disabled):hover .user-copy strong {
  color: var(--primary, #1f8f68);
}
.avatar {
  border: 1px solid color-mix(in srgb, var(--primary, #1f8f68) 15%, transparent);
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 55%);
}
.status-badge {
  border: 1px solid transparent;
}
.status-badge.active {
  border-color: #d1efdf;
}
.status-badge.inactive {
  border-color: #f3e2c2;
}
.role-chip,
.role-more {
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.icon-btn {
  width: 34px;
  height: 34px;
  transition: color 150ms ease, border-color 150ms ease, background-color 150ms ease, transform 150ms ease, box-shadow 150ms ease;
}
.icon-btn:hover {
  box-shadow: 0 4px 12px rgb(15 23 42 / 7%);
}
.operation-message.success {
  color: #176b4b;
  border-color: #cdebdc;
  background: #f0faf5;
}
.operation-message.error {
  color: #a52d2d;
}
.table-footer {
  background: var(--surface-muted, #fafbfc);
}
.btn:focus-visible,
.icon-btn:focus-visible,
.user-cell:focus-visible,
.search-field:focus-within,
.toolbar select:focus-visible {
  outline-offset: 3px;
}
@media (max-width: 720px) {
  .workspace-card {
    border-radius: 12px;
  }
  .toolbar {
    padding: 12px;
  }
  .stat-grid {
    gap: 10px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .users-page,
  .page-header,
  .workspace-card {
    animation: none !important;
  }
  .stat-card,
  .btn,
  .icon-btn,
  tbody tr {
    transition: none !important;
  }
}
</style>
