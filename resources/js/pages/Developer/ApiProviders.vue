<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

interface Provider {
    id: number; name: string; slug: string; category: string; base_url: string;
    auth_type: string; environment: string; is_active: boolean; notes: string | null;
    has_credentials: boolean; credential_keys: string[]; config: Record<string, string>;
    updated_at: string;
}
interface Props { userName?: string; userEmail?: string }
const props = withDefaults(defineProps<Props>(), { userName: '', userEmail: '' });
const providers = ref<Provider[]>([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const showForm = ref(false);
const editingId = ref<number | null>(null);
const search = ref('');
const form = ref({ name: '', slug: '', category: 'payment', base_url: '', auth_type: 'api_key', environment: 'sandbox', is_active: false, notes: '', credentials: { api_key: '', client_id: '', client_secret: '', token: '' } as Record<string, string> });
const filtered = computed(() => providers.value.filter(p => `${p.name} ${p.category} ${p.base_url}`.toLowerCase().includes(search.value.toLowerCase())));
const categories = [{ value: 'payment', label: 'Payment Gateway' }, { value: 'courier', label: 'Courier / Shipping' }, { value: 'sms', label: 'SMS Gateway' }, { value: 'email', label: 'Email Provider' }, { value: 'head_office', label: 'Head Office ERP' }, { value: 'analytics', label: 'Analytics' }, { value: 'other', label: 'Other API' }];
async function request(url: string, options: RequestInit = {}) {
    const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) }, credentials: 'same-origin' });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || 'Request failed.');
    return body;
}
async function load() { loading.value = true; error.value = ''; try { providers.value = (await request('/api/api-providers')).data || []; } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to load providers.'; } finally { loading.value = false; } }
function reset() { form.value = { name: '', slug: '', category: 'payment', base_url: '', auth_type: 'api_key', environment: 'sandbox', is_active: false, notes: '', credentials: { api_key: '', client_id: '', client_secret: '', token: '' } }; editingId.value = null; }
function create() { reset(); showForm.value = true; error.value = ''; success.value = ''; }
function edit(provider: Provider) { reset(); editingId.value = provider.id; form.value = { name: provider.name, slug: provider.slug, category: provider.category, base_url: provider.base_url, auth_type: provider.auth_type, environment: provider.environment, is_active: provider.is_active, notes: provider.notes || '', credentials: { api_key: '', client_id: '', client_secret: '', token: '' } }; showForm.value = true; error.value = ''; }
async function save() { saving.value = true; error.value = ''; success.value = ''; try { const payload = { ...form.value, credentials: Object.fromEntries(Object.entries(form.value.credentials).filter(([,v]) => v.trim() !== '')) }; const body = await request(editingId.value ? `/api/api-providers/${editingId.value}` : '/api/api-providers', { method: editingId.value ? 'PUT' : 'POST', body: JSON.stringify(payload) }); success.value = editingId.value ? 'Provider updated.' : 'Provider created.'; showForm.value = false; await load(); } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to save provider.'; } finally { saving.value = false; } }
async function remove(provider: Provider) { if (!window.confirm(`Delete ${provider.name}? This cannot be undone.`)) return; error.value = ''; try { await request(`/api/api-providers/${provider.id}`, { method: 'DELETE' }); success.value = 'Provider deleted.'; await load(); } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to delete provider.'; } }
onMounted(load);
</script>

<template>
    <Head title="API & Providers" />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <div class="api-providers-page space-y-5">
            <div class="erp-page-header flex flex-wrap items-end justify-between gap-4">
                <div class="erp-page-heading"><nav class="erp-page-breadcrumb" aria-label="Breadcrumb"><span>Workspace</span><span aria-hidden="true">›</span><span>Developer</span><span aria-hidden="true">›</span><strong>API & Providers</strong></nav><div class="erp-page-title-line"><span class="erp-page-title-icon"><Icon name="tool" :size="20" /></span><div><p class="text-xs font-semibold tracking-widest text-indigo-600">DEVELOPER / INTEGRATIONS</p><h1>API & Providers</h1><p class="text-sm text-slate-500">Manage payment, courier, SMS, email, Head Office and other external API configurations from one place.</p></div></div></div>
                <button class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700" @click="create">+ Add Provider</button>
            </div>
            <div v-if="success" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ success }}</div>
            <div v-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Total providers</p><p class="mt-1 text-2xl font-bold">{{ providers.length }}</p></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Enabled</p><p class="mt-1 text-2xl font-bold text-emerald-600">{{ providers.filter(p => p.is_active).length }}</p></div>
                <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Sandbox configurations</p><p class="mt-1 text-2xl font-bold">{{ providers.filter(p => p.environment === 'sandbox').length }}</p></div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4"><h2 class="font-semibold text-slate-900">Configured providers</h2><input v-model="search" type="search" placeholder="Search providers..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:max-w-xs" /></div>
                <div v-if="loading" class="p-8 text-center text-sm text-slate-500">Loading providers...</div>
                <div v-else-if="filtered.length === 0" class="p-10 text-center"><p class="font-medium text-slate-700">No API providers configured</p><p class="mt-1 text-sm text-slate-500">Add your first provider to centralize its endpoint and credentials.</p></div>
                <div v-else class="divide-y divide-slate-100">
                    <div v-for="provider in filtered" :key="provider.id" class="flex flex-wrap items-center justify-between gap-4 p-4">
                        <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-slate-900">{{ provider.name }}</h3><span class="rounded-full px-2 py-0.5 text-xs" :class="provider.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ provider.is_active ? 'Enabled' : 'Disabled' }}</span><span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">{{ provider.environment }}</span></div><p class="mt-1 truncate text-sm text-slate-500">{{ provider.base_url }}</p><p class="mt-1 text-xs text-slate-400">{{ categories.find(c => c.value === provider.category)?.label || provider.category }} · {{ provider.auth_type }} · {{ provider.has_credentials ? 'Credentials stored securely' : 'No credentials stored' }}</p></div>
                        <div class="flex gap-2"><button class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50" @click="edit(provider)">Edit</button><button class="rounded-lg border border-rose-200 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50" @click="remove(provider)">Delete</button></div>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Security:</strong> Credentials are encrypted at rest and never returned by the API. Leave credential fields blank when editing to keep the existing values. This menu stores configuration only; it does not claim that a provider connection is live or tested.</div>
        </div>
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4" @click.self="showForm = false">
            <div class="my-8 w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl"><div class="mb-5 flex items-center justify-between"><h2 class="text-lg font-bold">{{ editingId ? 'Edit API provider' : 'Add API provider' }}</h2><button class="text-slate-500" @click="showForm = false">✕</button></div>
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                    <label class="text-sm font-medium">Provider name<input v-model="form.name" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
                    <label class="text-sm font-medium">Slug<input v-model="form.slug" maxlength="140" placeholder="auto-generated if blank" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
                    <label class="text-sm font-medium">Category<select v-model="form.category" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option></select></label>
                    <label class="text-sm font-medium">Authentication<select v-model="form.auth_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="none">None</option><option value="api_key">API Key</option><option value="bearer">Bearer Token</option><option value="basic">Basic Auth</option><option value="oauth2">OAuth 2.0</option><option value="custom">Custom</option></select></label>
                    <label class="sm:col-span-2 text-sm font-medium">Base URL<input v-model="form.base_url" required type="url" placeholder="https://api.provider.com" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
                    <label class="text-sm font-medium">Environment<select v-model="form.environment" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="sandbox">Sandbox</option><option value="production">Production</option></select></label>
                    <label class="flex items-center gap-2 pt-7 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded" /> Enable this configuration</label>
                    <div class="sm:col-span-2 rounded-lg border border-slate-200 p-3"><p class="mb-3 text-sm font-semibold">Credentials <span class="font-normal text-slate-500">(encrypted at rest)</span></p><p v-if="editingId" class="mb-3 text-xs text-slate-500">Leave blank to retain the current secret. Existing secrets are never displayed.</p><div class="grid gap-3 sm:grid-cols-2"><label v-for="key in ['api_key','client_id','client_secret','token']" :key="key" class="text-sm">{{ key.replace('_',' ').replace('_',' ') }}<input v-model="form.credentials[key]" :type="key.includes('secret') || key === 'token' || key === 'api_key' ? 'password' : 'text'" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label></div></div>
                    <label class="sm:col-span-2 text-sm font-medium">Notes<textarea v-model="form.notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea></label>
                    <div class="sm:col-span-2 flex justify-end gap-2"><button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="showForm = false">Cancel</button><button type="submit" :disabled="saving" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ saving ? 'Saving...' : 'Save provider' }}</button></div>
                </form>
            </div>
        </div>
    </ErpLayout>
</template>
