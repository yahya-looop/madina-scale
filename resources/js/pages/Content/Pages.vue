<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';

type CmsPage = {
    id: number; title: string; slug: string; excerpt: string | null;
    content: string | null; sections?: Array<{key:string;name:string;type:string;content:Record<string, unknown>}>; featured_image: string | null; status: string;
    published_at: string | null; meta_title: string | null;
    meta_description: string | null; meta_keywords: string | null;
    canonical_url: string | null; robots_index: boolean; robots_follow: boolean;
};
const pages = ref<CmsPage[]>([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const search = ref('');
const statusFilter = ref('');
const editingId = ref<number | null>(null);
const showEditor = ref(false);
const revisionPage = ref<CmsPage | null>(null);
const sectionTemplates = ref<Array<{id:number;key:string;name:string;type:string;content:Record<string, unknown>}>>([]);
const selectedSectionKey = ref('');
const revisions = ref<Array<{id:number;version:number;change_note:string|null;created_at:string}>>([]);
const form = reactive({
    title: '', slug: '', excerpt: '', content: '', sections: [] as Array<{key:string;name:string;type:string;content:Record<string, unknown>}>, featured_image: '', status: 'draft',
    published_at: '', meta_title: '', meta_description: '', meta_keywords: '',
    canonical_url: '', robots_index: true, robots_follow: true,
});
const publishedCount = computed(() => pages.value.filter((p) => p.status === 'published').length);
const draftCount = computed(() => pages.value.filter((p) => p.status === 'draft').length);
function csrf(): string { return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''; }
async function api(url: string, method = 'GET', body?: unknown): Promise<any> {
    const response = await fetch(url, { method, credentials: 'same-origin', headers: {
        Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(),
        ...(body ? { 'Content-Type': 'application/json' } : {}),
    }, ...(body ? { body: JSON.stringify(body) } : {}) });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || 'The request could not be completed.');
    return data;
}
async function load(): Promise<void> {
    loading.value = true; error.value = '';
    try {
        const params = new URLSearchParams();
        if (search.value.trim()) params.set('search', search.value.trim());
        if (statusFilter.value) params.set('status', statusFilter.value);
        params.set('per_page', '100');
        const result = await api(`/api/cms/pages?${params.toString()}`);
        pages.value = result.data ?? [];
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to load pages.'; }
    finally { loading.value = false; }
}
function resetForm(): void {
    Object.assign(form, { title: '', slug: '', excerpt: '', content: '', sections: [] as Array<{key:string;name:string;type:string;content:Record<string, unknown>}>, featured_image: '', status: 'draft',
        published_at: '', meta_title: '', meta_description: '', meta_keywords: '', canonical_url: '', robots_index: true, robots_follow: true });
    editingId.value = null;
}
async function loadSectionTemplates(): Promise<void> { try { const result = await api('/api/cms/sections'); sectionTemplates.value = (result.data ?? []).filter((item: {is_active:boolean}) => item.is_active); } catch { sectionTemplates.value = []; } }
function addSection(): void { const item = sectionTemplates.value.find((section) => section.key === selectedSectionKey.value); if (!item) return; form.sections.push({ key:item.key, name:item.name, type:item.type, content:item.content }); selectedSectionKey.value = ''; }
function removeSection(index: number): void { form.sections.splice(index, 1); }
async function createPage(): Promise<void> { resetForm(); await loadSectionTemplates(); showEditor.value = true; }
function editPage(page: CmsPage): void {
    resetForm(); void loadSectionTemplates(); editingId.value = page.id;
    Object.assign(form, { ...page, excerpt: page.excerpt ?? '', content: page.content ?? '', featured_image: page.featured_image ?? '',
        sections: page.sections ?? [], published_at: page.published_at ? page.published_at.slice(0, 16) : '', meta_title: page.meta_title ?? '',
        meta_description: page.meta_description ?? '', meta_keywords: page.meta_keywords ?? '', canonical_url: page.canonical_url ?? '' });
    showEditor.value = true;
}
async function save(): Promise<void> {
    saving.value = true; error.value = '';
    try {
        const payload = { ...form, published_at: form.published_at || null };
        await api(editingId.value ? `/api/cms/pages/${editingId.value}` : '/api/cms/pages', editingId.value ? 'PUT' : 'POST', payload);
        showEditor.value = false; await load();
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to save page.'; }
    finally { saving.value = false; }
}
async function removePage(page: CmsPage): Promise<void> {
    if (!window.confirm(`Delete “${page.title}”?`)) return;
    try { await api(`/api/cms/pages/${page.id}`, 'DELETE'); await load(); }
    catch (e) { error.value = e instanceof Error ? e.message : 'Unable to delete page.'; }
}

async function openRevisions(page: CmsPage): Promise<void> {
    revisionPage.value = page; revisions.value = [];
    try { const result = await api(`/api/cms/pages/${page.id}/revisions`); revisions.value = result.data ?? []; }
    catch (e) { error.value = e instanceof Error ? e.message : 'Unable to load revisions.'; }
}
async function restoreRevision(revisionId: number): Promise<void> {
    if (!revisionPage.value || !window.confirm('Restore this revision? The current page will be saved as a new revision first.')) return;
    try { await api(`/api/cms/pages/${revisionPage.value.id}/revisions/${revisionId}/restore`, 'POST', {}); revisionPage.value = null; await load(); }
    catch (e) { error.value = e instanceof Error ? e.message : 'Unable to restore revision.'; }
}

async function uploadImage(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0]; if (!file) return;
    const data = new FormData(); data.append('file', file);
    try {
        const response = await fetch('/api/cms/media-library', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: data });
        const result = await response.json(); if (!response.ok) throw new Error(result.message || 'Upload failed.');
        form.featured_image = result.data.url;
    } catch (e) { error.value = e instanceof Error ? e.message : 'Image upload failed.'; }
}
onMounted(load);
</script>

<template>
    <ErpLayout><div class="cms-page">
        <header class="page-head erp-page-header"><div class="erp-page-heading"><nav class="erp-page-breadcrumb" aria-label="Breadcrumb"><span>Workspace</span><span aria-hidden="true">›</span><span>Website Management</span><span aria-hidden="true">›</span><strong>Content Pages</strong></nav><div class="erp-page-title-line"><span class="erp-page-title-icon"><Icon name="cms" :size="20" /></span><div><p class="eyebrow">WEBSITE MANAGEMENT</p><h1>Content Management</h1><p class="muted">Create, edit and publish pages for the public website.</p></div></div></div><button class="primary" @click="createPage">＋ Create page</button></header>
        <div class="stats"><article><span>Total pages</span><strong>{{ pages.length }}</strong></article><article><span>Published</span><strong>{{ publishedCount }}</strong></article><article><span>Drafts</span><strong>{{ draftCount }}</strong></article></div>
        <div class="toolbar"><input v-model="search" placeholder="Search pages by title or slug" @keyup.enter="load"><select v-model="statusFilter" @change="load"><option value="">All statuses</option><option value="draft">Draft</option><option value="published">Published</option><option value="scheduled">Scheduled</option><option value="archived">Archived</option></select><button @click="load">Search</button></div>
        <p v-if="error" class="error">{{ error }}</p>
        <div class="table-wrap"><table><thead><tr><th>Page</th><th>URL</th><th>Status</th><th>Published</th><th>Actions</th></tr></thead><tbody>
            <tr v-for="page in pages" :key="page.id"><td><strong>{{ page.title }}</strong><small>{{ page.meta_title || 'No SEO title set' }}</small></td><td><code>/pages/{{ page.slug }}</code></td><td><span class="status" :class="page.status">{{ page.status }}</span></td><td>{{ page.published_at ? new Date(page.published_at).toLocaleDateString() : '—' }}</td><td class="actions"><button @click="editPage(page)">Edit</button><button @click="openRevisions(page)">History</button><a v-if="page.status === 'published'" :href="`/pages/${page.slug}`" target="_blank" rel="noreferrer">View</a><button class="danger" :disabled="page.status === 'published'" @click="removePage(page)">Delete</button></td></tr>
            <tr v-if="!loading && pages.length === 0"><td colspan="5" class="empty">No pages found. Create your first website page.</td></tr>
            <tr v-if="loading"><td colspan="5" class="empty">Loading pages…</td></tr>
        </tbody></table></div>
        <div v-if="revisionPage" class="overlay" @click.self="revisionPage = null"><section class="editor"><header><div><p class="eyebrow">VERSION HISTORY</p><h2>{{ revisionPage.title }}</h2></div><button class="close" @click="revisionPage = null">×</button></header><p v-if="!revisions.length" class="empty">No previous revisions have been recorded yet. Revisions are created automatically when a page is updated.</p><div v-for="revision in revisions" :key="revision.id" class="revision"><div><strong>Version {{ revision.version }}</strong><small>{{ revision.change_note || 'Saved revision' }} · {{ new Date(revision.created_at).toLocaleString() }}</small></div><button class="primary" @click="restoreRevision(revision.id)">Restore</button></div></section></div>
        <div v-if="showEditor" class="overlay" @click.self="showEditor = false"><section class="editor"><header><div><p class="eyebrow">PAGE EDITOR</p><h2>{{ editingId ? 'Edit page' : 'Create page' }}</h2></div><button class="close" @click="showEditor = false">×</button></header>
            <form @submit.prevent="save"><div class="field"><label>Page title *</label><input v-model="form.title" required maxlength="180"></div>
                <div class="field"><label>URL slug</label><input v-model="form.slug" placeholder="auto-generated-from-title"></div>
                <div class="field"><label>Short description</label><textarea v-model="form.excerpt" rows="2" maxlength="500"></textarea></div>
                <div class="field"><label>Page content</label><textarea v-model="form.content" rows="12" placeholder="Write plain text here. Use reusable sections for structured content; HTML is displayed as text."></textarea></div>
                <h3>Reusable content sections</h3><div class="field"><label>Add a saved section</label><div class="section-picker"><select v-model="selectedSectionKey"><option value="">Choose a reusable section</option><option v-for="section in sectionTemplates" :key="section.id" :value="section.key">{{ section.name }} ({{ section.type }})</option></select><button type="button" @click="addSection" :disabled="!selectedSectionKey">Add section</button></div></div><div v-for="(section,index) in form.sections" :key="`${section.key}-${index}`" class="revision"><div><strong>{{ section.name }}</strong><small>{{ section.type }} · {{ section.key }}</small></div><button type="button" @click="removeSection(index)">Remove</button></div>
                <div class="field"><label>Featured image</label><input v-model="form.featured_image" placeholder="Image URL"><input type="file" accept="image/jpeg,image/png,image/webp,image/avif" @change="uploadImage"></div>
                <div class="field"><label>Publishing status</label><select v-model="form.status"><option value="draft">Draft</option><option value="published">Published</option><option value="scheduled">Scheduled</option><option value="archived">Archived</option></select></div>
                <div class="field" v-if="form.status === 'scheduled'"><label>Publish at</label><input v-model="form.published_at" type="datetime-local"></div>
                <h3>Search engine metadata</h3><div class="field"><label>Meta title</label><input v-model="form.meta_title" maxlength="180"></div>
                <div class="field"><label>Meta description</label><textarea v-model="form.meta_description" rows="2" maxlength="320"></textarea></div>
                <div class="field"><label>Meta keywords</label><input v-model="form.meta_keywords" maxlength="500"></div>
                <div class="field"><label>Canonical URL</label><input v-model="form.canonical_url" type="url" placeholder="https://example.com/page"></div>
                <div class="checks"><label><input v-model="form.robots_index" type="checkbox"> Allow search indexing</label><label><input v-model="form.robots_follow" type="checkbox"> Allow link following</label></div>
                <p v-if="error" class="error">{{ error }}</p><footer><button type="button" @click="showEditor = false">Cancel</button><button class="primary" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save page' }}</button></footer>
            </form></section></div>
    </div></ErpLayout>
</template>

<style scoped>
.cms-page{padding:0;color:var(--text-primary,#172033)}.page-head{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:22px}.eyebrow{font-size:11px;letter-spacing:.12em;color:#64748b;font-weight:700;margin:0 0 6px}.page-head h1{font-size:25px;margin:0}.muted,small{color:#64748b}.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}.stats article,.table-wrap{background:var(--surface,#fff);border:1px solid var(--border,#e2e8f0);border-radius:12px}.stats article{padding:16px;display:grid;gap:8px}.stats span{font-size:13px;color:#64748b}.stats strong{font-size:24px}.toolbar{display:flex;gap:10px;margin-bottom:14px}.toolbar input{flex:1}.toolbar input,.toolbar select,.field input,.field textarea,.field select{border:1px solid #cbd5e1;border-radius:8px;padding:10px 12px;background:white;color:#172033;min-width:0}.toolbar button,.actions button,.actions a,footer button,.primary{border:1px solid #cbd5e1;border-radius:8px;padding:9px 13px;background:white;color:#334155;text-decoration:none;cursor:pointer}.primary{background:#174c3c;color:white;border-color:#174c3c}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;text-align:left}th,td{padding:13px 15px;border-bottom:1px solid #edf0f4;font-size:13px}th{color:#64748b;font-weight:600;background:#f8fafc}td small{display:block;margin-top:4px}.status{padding:4px 8px;border-radius:20px;background:#f1f5f9;text-transform:capitalize}.status.published{background:#dcfce7;color:#166534}.status.draft{background:#fef3c7;color:#92400e}.status.archived{background:#e2e8f0}.actions{white-space:nowrap;display:flex;gap:6px}.actions button,.actions a{padding:6px 9px}.danger{color:#b91c1c!important}.empty{text-align:center;color:#64748b;padding:32px}.overlay{position:fixed;inset:0;background:#0f172a88;z-index:1000;display:flex;justify-content:flex-end}.editor{width:min(680px,100%);height:100%;overflow:auto;background:white;padding:24px;box-shadow:-12px 0 40px #0002}.editor header,footer{display:flex;align-items:center;justify-content:space-between;gap:12px}.editor h2{margin:0 0 20px}.close{font-size:26px;border:0;background:transparent;cursor:pointer}.field{display:grid;gap:6px;margin:14px 0}.field label{font-size:13px;font-weight:650;color:#334155}.field textarea{resize:vertical}.editor h3{margin:24px 0 8px;font-size:15px}.checks{display:flex;gap:20px;flex-wrap:wrap;font-size:13px;margin:18px 0}.error{color:#b91c1c;background:#fef2f2;padding:10px;border-radius:8px}.editor footer{justify-content:flex-end;border-top:1px solid #e2e8f0;padding-top:16px;margin-top:20px}@media(max-width:640px){.cms-page{padding:0}.page-head{align-items:flex-start;flex-direction:column}.stats{grid-template-columns:1fr}.toolbar{flex-wrap:wrap}.toolbar input{flex-basis:100%}}
</style>
