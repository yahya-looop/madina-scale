<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';

interface Summary { modules: number; disabled_modules: number; flags: number; enabled_flags: number; menus: number; hidden_menus: number }
const summary = ref<Summary>({ modules: 0, disabled_modules: 0, flags: 0, enabled_flags: 0, menus: 0, hidden_menus: 0 });
const loading = ref(true);
const error = ref('');

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const [modules, flags, menus] = await Promise.all([
            fetch('/api/v1/backend/modules', { headers, credentials: 'same-origin' }),
            fetch('/api/v1/backend/feature-flags', { headers, credentials: 'same-origin' }),
            fetch('/api/v1/backend/menu-registry', { headers, credentials: 'same-origin' }),
        ]);
        if (!modules.ok || !flags.ok || !menus.ok) throw new Error('Unable to load the Developer Console summary.');
        const [m, f, n] = await Promise.all([modules.json(), flags.json(), menus.json()]);
        const moduleRows = Array.isArray(m.data) ? m.data : [];
        const flagRows = Array.isArray(f.data) ? f.data : [];
        const menuRows = Array.isArray(n.data) ? n.data : [];
        summary.value = {
            modules: moduleRows.length,
            disabled_modules: moduleRows.filter((item: { state: string }) => item.state === 'disabled').length,
            flags: flagRows.length,
            enabled_flags: flagRows.filter((item: { is_enabled: boolean }) => item.is_enabled).length,
            menus: menuRows.length,
            hidden_menus: menuRows.filter((item: { is_visible: boolean }) => !item.is_visible).length,
        };
    } catch (cause) {
        error.value = cause instanceof Error ? cause.message : 'Unable to load system controls.';
    } finally { loading.value = false; }
}
onMounted(() => { void load(); });
</script>

<template>
    <Head title="Developer Console" />
    <ErpLayout>
        <div class="space-y-5 p-4 md:p-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">System / Developer</p><h1 class="text-2xl font-semibold">Developer Console</h1><p class="mt-1 text-sm text-muted-foreground">Central control plane for modules, feature rollout, navigation and system configuration.</p></div>
                <button class="rounded-lg border px-3 py-2 text-sm" type="button" :disabled="loading" @click="load">Refresh</button>
            </header>
            <p v-if="error" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Link href="/backend/developer/modules" class="rounded-xl border bg-card p-4 transition hover:border-primary"><p class="text-sm text-muted-foreground">Module Registry</p><div class="mt-2 flex items-end justify-between"><strong class="text-3xl">{{ loading ? '—' : summary.modules }}</strong><span class="text-xs text-muted-foreground">{{ summary.disabled_modules }} disabled</span></div><p class="mt-3 text-sm">Manage module lifecycle and availability →</p></Link>
                <Link href="/backend/developer/feature-flags" class="rounded-xl border bg-card p-4 transition hover:border-primary"><p class="text-sm text-muted-foreground">Feature Flags</p><div class="mt-2 flex items-end justify-between"><strong class="text-3xl">{{ loading ? '—' : summary.flags }}</strong><span class="text-xs text-muted-foreground">{{ summary.enabled_flags }} enabled</span></div><p class="mt-3 text-sm">Control rollout and environment targeting →</p></Link>
                <Link href="/backend/developer/menu-registry" class="rounded-xl border bg-card p-4 transition hover:border-primary"><p class="text-sm text-muted-foreground">Menu Registry</p><div class="mt-2 flex items-end justify-between"><strong class="text-3xl">{{ loading ? '—' : summary.menus }}</strong><span class="text-xs text-muted-foreground">{{ summary.hidden_menus }} hidden</span></div><p class="mt-3 text-sm">Manage sidebar structure and visibility →</p></Link>
                <Link href="/backend/settings/system" class="rounded-xl border bg-card p-4 transition hover:border-primary sm:col-span-2 xl:col-span-1"><p class="text-sm text-muted-foreground">System Settings</p><strong class="mt-2 block text-xl">Configuration</strong><p class="mt-3 text-sm">Business, localization, currency and security settings →</p></Link>
                <Link href="/backend/developer/api-providers" class="rounded-xl border bg-card p-4 transition hover:border-primary"><p class="text-sm text-muted-foreground">Integrations</p><strong class="mt-2 block text-xl">API & Providers</strong><p class="mt-3 text-sm">Manage configured external providers →</p></Link>
                <Link href="/backend/settings/activity-logs" class="rounded-xl border bg-card p-4 transition hover:border-primary"><p class="text-sm text-muted-foreground">Audit Trail</p><strong class="mt-2 block text-xl">Activity Logs</strong><p class="mt-3 text-sm">Review administrative and system changes →</p></Link>
            </div>
            <section class="rounded-xl border bg-card p-4"><h2 class="font-semibold">Control-plane principles</h2><ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground"><li>Module state never replaces user permissions.</li><li>Hidden navigation does not grant or revoke backend access.</li><li>Feature flags control rollout, not authorization.</li><li>Administrative changes are recorded in the activity log.</li></ul></section>
        </div>
    </ErpLayout>
</template>
