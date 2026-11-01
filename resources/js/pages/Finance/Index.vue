<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';
import { useAuthorization } from '@/composables/useAuthorization';

interface Props { userName?: string; userEmail?: string }
interface LocationOption { id: number; name: string; code?: string | null; access_level: string }
interface Account { id: number; code: string; name: string; type: string; normal_balance: string; is_system: boolean; is_cash_bank: boolean; is_active: boolean; ledger_balance?: string }
interface Transaction {
    id: number; transaction_number: string; type: string; transaction_date: string; amount: string;
    payment_method: string; description: string; reference?: string | null;
    cash_account?: Account; category_account?: Account; customer?: { name: string } | null;
}
interface CustomerOption { id: number; customer_code: string; name: string; phone?: string | null }
interface Journal { id: number; entry_number: string; entry_date: string; description: string; status: string; lines: Array<{ account?: Account; debit: string; credit: string }> }
interface Overview {
    month_income: string; month_expenses: string; customer_receipts: string;
    cash_bank_balances: Array<{ id: number; code: string; name: string; balance: string }>;
    recent_transactions: Transaction[]; locations: LocationOption[];
}
const props = withDefaults(defineProps<Props>(), { userName: '', userEmail: '' });
const { can } = useAuthorization();
const tab = ref<'overview' | 'transactions' | 'accounts' | 'journals'>('overview');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const overview = ref<Overview | null>(null);
const accounts = ref<Account[]>([]);
const transactions = ref<Transaction[]>([]);
const journals = ref<Journal[]>([]);
const customers = ref<CustomerOption[]>([]);
const selectedLocation = ref('');
const transactionModal = ref(false);
const accountModal = ref(false);
const journalModal = ref(false);
const transactionForm = reactive({
    idempotency_key: '', type: 'income', transaction_date: new Date().toISOString().slice(0, 10), amount: '',
    payment_method: 'cash', cash_account_id: '', category_account_id: '', customer_id: '',
    description: '', reference: '', location_id: '',
});
const accountForm = reactive({ code: '', name: '', type: 'expense', normal_balance: 'debit', is_cash_bank: false, description: '' });
const journalForm = reactive({ entry_date: new Date().toISOString().slice(0, 10), description: '', reference: '', location_id: '', lines: [
    { account_id: '', debit: '', credit: '' }, { account_id: '', debit: '', credit: '' },
] });
const manageableLocations = computed(() => (overview.value?.locations ?? []).filter((l) => l.access_level === 'manage'));
const cashAccounts = computed(() => accounts.value.filter((a) => a.is_cash_bank && a.is_active));
const categoryAccounts = computed(() => accounts.value.filter((a) => a.is_active && !a.is_cash_bank && a.type === (transactionForm.type === 'income' ? 'income' : 'expense')));
const money = (v?: string | number) => new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT', maximumFractionDigits: 2 }).format(Number(v ?? 0));
const dateLabel = (v?: string) => v ? new Intl.DateTimeFormat('en-BD', { dateStyle: 'medium' }).format(new Date(v)) : '—';
const csrf = () => decodeURIComponent(document.cookie.split('; ').find((v) => v.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=') ?? '');
async function api<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest',
            ...(options.body ? { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf() } : {}),
            ...(options.headers ?? {}),
        },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || Object.values(payload.errors ?? {}).flat().join(' ') || `Request failed (${response.status})`);
    return payload as T;
}
async function load(): Promise<void> {
    loading.value = true; error.value = '';
    try {
        const suffix = selectedLocation.value ? `?location_id=${selectedLocation.value}` : '';
        const [o, a, t, j, c] = await Promise.all([
            api<{ data: Overview }>(`/api/finance/overview${suffix}`),
            api<{ data: Account[] }>(`/api/finance/accounts${suffix}`),
            can('system.finance.transactions.view')
                ? api<{ data: Transaction[] }>(`/api/finance/transactions${suffix || '?'}${suffix ? '&' : ''}per_page=12`)
                : Promise.resolve({ data: [] as Transaction[] }),
            can('system.finance.journals.view')
                ? api<{ data: Journal[] }>(`/api/finance/journals${suffix || '?'}${suffix ? '&' : ''}per_page=10`)
                : Promise.resolve({ data: [] as Journal[] }),
            can('system.finance.transactions.create')
                ? api<{ data: CustomerOption[] }>('/api/finance/customer-options')
                : Promise.resolve({ data: [] as CustomerOption[] }),
        ]);
        overview.value = o.data;
        accounts.value = a.data;
        transactions.value = t.data;
        journals.value = j.data;
        customers.value = c.data;
        if (!transactionForm.location_id && o.data.locations.some((l) => l.access_level === 'manage')) {
            const preferred = o.data.locations.find((l) => l.access_level === 'manage')!;
            transactionForm.location_id = String(preferred.id);
            journalForm.location_id = String(preferred.id);
        }
        if (!selectedLocation.value && o.data.locations.length === 1) selectedLocation.value = String(o.data.locations[0].id);
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to load finance data.'; }
    finally { loading.value = false; }
}
function notify(message: string): void { success.value = message; window.setTimeout(() => { success.value = ''; }, 3500); }
async function saveTransaction(): Promise<void> {
    saving.value = true; error.value = '';
    try {
        if (!transactionForm.idempotency_key) transactionForm.idempotency_key = `${Date.now()}-${Math.random().toString(36).slice(2, 14)}`;
        await api('/api/finance/transactions', { method: 'POST', body: JSON.stringify({
            ...transactionForm, amount: transactionForm.amount, cash_account_id: Number(transactionForm.cash_account_id),
            category_account_id: transactionForm.type === 'customer_receipt' ? null : Number(transactionForm.category_account_id),
            customer_id: transactionForm.customer_id ? Number(transactionForm.customer_id) : null,
            location_id: Number(transactionForm.location_id),
        }) });
        transactionModal.value = false; transactionForm.amount = ''; transactionForm.description = ''; transactionForm.reference = ''; transactionForm.idempotency_key = '';
        notify('Financial transaction posted to the general ledger.'); await load();
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to save transaction.'; }
    finally { saving.value = false; }
}
async function saveAccount(): Promise<void> {
    saving.value = true; error.value = '';
    try {
        await api('/api/finance/accounts', { method: 'POST', body: JSON.stringify(accountForm) });
        accountModal.value = false; Object.assign(accountForm, { code: '', name: '', type: 'expense', normal_balance: 'debit', is_cash_bank: false, description: '' });
        notify('Chart-of-account entry created.'); await load();
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to create account.'; }
    finally { saving.value = false; }
}
async function saveJournal(): Promise<void> {
    const debitTotal = journalForm.lines.reduce((sum, line) => sum + Number(line.debit || 0), 0);
    const creditTotal = journalForm.lines.reduce((sum, line) => sum + Number(line.credit || 0), 0);
    if (debitTotal <= 0 || Math.abs(debitTotal - creditTotal) > 0.001) {
        error.value = 'Journal must have equal debit and credit totals greater than zero.';
        return;
    }
    saving.value = true; error.value = '';
    try {
        const created = await api<{ data: Journal }>('/api/finance/journals', { method: 'POST', body: JSON.stringify({
            ...journalForm, location_id: Number(journalForm.location_id),
            lines: journalForm.lines.map((l) => ({ ...l, account_id: Number(l.account_id), debit: l.debit || '0', credit: l.credit || '0' })),
        }) });
        await api(`/api/finance/journals/${created.data.id}/post`, { method: 'POST', body: JSON.stringify({}) });
        journalModal.value = false; journalForm.description = ''; journalForm.reference = '';
        journalForm.lines = [{ account_id: '', debit: '', credit: '' }, { account_id: '', debit: '', credit: '' }];
        notify('Balanced journal posted successfully.'); await load();
    } catch (e) { error.value = e instanceof Error ? e.message : 'Unable to post journal.'; }
    finally { saving.value = false; }
}
function addJournalLine(): void { journalForm.lines.push({ account_id: '', debit: '', credit: '' }); }
function formatType(type: string): string { return ({ income: 'Income', expense: 'Expense', customer_receipt: 'Customer receipt' } as Record<string, string>)[type] ?? type; }
onMounted(load);
</script>

<template>
    <Head title="Accounts & Finance" />
    <ErpLayout :user-name="props.userName" :user-email="props.userEmail">
        <div class="finance-page">
            <header class="page-heading erp-page-header">
                <div class="erp-page-heading"><nav class="erp-page-breadcrumb" aria-label="Breadcrumb"><span>Workspace</span><span aria-hidden="true">›</span><span>Accounts & Finance</span></nav><div class="erp-page-title-line"><span class="erp-page-title-icon"><Icon name="wallet" :size="20" /></span><div><p class="eyebrow">FINANCE MANAGEMENT</p><h1>Accounts & Finance</h1><p class="subtitle">Double-entry ledger, cash and bank, income, expenses and customer receipts.</p></div></div></div>
                <div class="heading-actions">
                    <button v-if="can('system.finance.transactions.create') && manageableLocations.length" class="btn btn-primary" @click="transactionModal = true">＋ Record transaction</button>
                    <button v-if="can('system.finance.journals.create') && can('system.finance.journals.post')" class="btn btn-secondary" @click="journalModal = true">＋ Journal entry</button>
                </div>
            </header>
            <div v-if="error" class="notice notice-error">{{ error }} <button @click="error = ''">×</button></div>
            <div v-if="success" class="notice notice-success">{{ success }}</div>
            <div class="toolbar">
                <div class="tabs">
                    <button v-for="item in [{id:'overview',label:'Overview'},{id:'transactions',label:'Transactions'},{id:'accounts',label:'Chart of Accounts'},{id:'journals',label:'Journal Entries'}]" :key="item.id" :class="{active:tab===item.id}" @click="tab = item.id as typeof tab">{{ item.label }}</button>
                </div>
                <select v-model="selectedLocation" @change="load"><option value="">All accessible locations</option><option v-for="loc in overview?.locations ?? []" :key="loc.id" :value="String(loc.id)">{{ loc.name }}</option></select>
            </div>
            <div v-if="loading" class="empty">Loading finance records…</div>
            <template v-else>
                <section v-if="tab === 'overview'">
                    <div class="kpi-grid">
                        <article class="kpi"><span>Income this month</span><strong>{{ money(overview?.month_income) }}</strong><small>Posted income transactions</small></article>
                        <article class="kpi"><span>Expenses this month</span><strong>{{ money(overview?.month_expenses) }}</strong><small>Posted expense transactions</small></article>
                        <article class="kpi"><span>Customer receipts</span><strong>{{ money(overview?.customer_receipts) }}</strong><small>Unallocated customer advances</small></article>
                    </div>
                    <section class="panel">
                        <div class="panel-heading"><div><h2>Cash & bank balances</h2><p>Calculated from posted journal lines only</p></div><button class="text-button" @click="tab='accounts'">View chart of accounts →</button></div>
                        <div class="balance-grid"><div v-for="account in overview?.cash_bank_balances ?? []" :key="account.id" class="balance-item"><span>{{ account.code }} · {{ account.name }}</span><strong>{{ money(account.balance) }}</strong></div><div v-if="!overview?.cash_bank_balances.length" class="empty">No posted cash or bank activity yet.</div></div>
                    </section>
                    <section class="panel"><div class="panel-heading"><div><h2>Recent transactions</h2><p>Latest posted financial activity</p></div><button class="text-button" @click="tab='transactions'">All transactions →</button></div>
                        <div class="table-wrap"><table><thead><tr><th>Reference</th><th>Date</th><th>Type</th><th>Description</th><th>Amount</th></tr></thead><tbody><tr v-for="row in overview?.recent_transactions ?? []" :key="row.id"><td>{{ row.transaction_number }}</td><td>{{ dateLabel(row.transaction_date) }}</td><td>{{ formatType(row.type) }}</td><td>{{ row.description }}</td><td class="amount">{{ money(row.amount) }}</td></tr><tr v-if="!overview?.recent_transactions.length"><td colspan="5" class="empty">No financial transactions recorded yet.</td></tr></tbody></table></div>
                    </section>
                </section>
                <section v-else-if="tab === 'transactions'" class="panel">
                    <div class="panel-heading"><div><h2>Financial transactions</h2><p>Income, operating expenses and customer receipts</p></div><button v-if="can('system.finance.transactions.create') && manageableLocations.length" class="btn btn-primary" @click="transactionModal=true">Record transaction</button></div>
                    <div class="table-wrap"><table><thead><tr><th>Transaction</th><th>Date</th><th>Type</th><th>Description</th><th>Cash / Bank</th><th>Amount</th></tr></thead><tbody><tr v-for="row in transactions" :key="row.id"><td>{{ row.transaction_number }}</td><td>{{ dateLabel(row.transaction_date) }}</td><td>{{ formatType(row.type) }}</td><td>{{ row.description }}</td><td>{{ row.cash_account?.name ?? '—' }}</td><td class="amount">{{ money(row.amount) }}</td></tr><tr v-if="!transactions.length"><td colspan="6" class="empty">No transactions found.</td></tr></tbody></table></div>
                </section>
                <section v-else-if="tab === 'accounts'" class="panel">
                    <div class="panel-heading"><div><h2>Chart of Accounts</h2><p>System accounts are protected; custom accounts can be added as needed.</p></div><button v-if="can('system.finance.accounts.manage')" class="btn btn-primary" @click="accountModal=true">＋ Add account</button></div>
                    <div class="table-wrap"><table><thead><tr><th>Code</th><th>Account name</th><th>Type</th><th>Normal balance</th><th>Ledger balance</th><th>Status</th></tr></thead><tbody><tr v-for="account in accounts" :key="account.id"><td>{{ account.code }}</td><td>{{ account.name }} <span v-if="account.is_system" class="system-tag">System</span></td><td>{{ account.type }}</td><td>{{ account.normal_balance }}</td><td class="amount">{{ money(account.ledger_balance) }}</td><td>{{ account.is_active ? 'Active' : 'Inactive' }}</td></tr></tbody></table></div>
                </section>
                <section v-else class="panel">
                    <div class="panel-heading"><div><h2>Journal entries</h2><p>Only posted, balanced journals affect ledger balances.</p></div><button v-if="can('system.finance.journals.create') && can('system.finance.journals.post')" class="btn btn-primary" @click="journalModal=true">＋ Journal entry</button></div>
                    <div class="table-wrap"><table><thead><tr><th>Entry</th><th>Date</th><th>Description</th><th>Lines</th><th>Status</th></tr></thead><tbody><tr v-for="entry in journals" :key="entry.id"><td>{{ entry.entry_number }}</td><td>{{ dateLabel(entry.entry_date) }}</td><td>{{ entry.description }}</td><td>{{ entry.lines?.length ?? 0 }}</td><td><span class="status" :class="entry.status">{{ entry.status }}</span></td></tr><tr v-if="!journals.length"><td colspan="5" class="empty">No journal entries yet.</td></tr></tbody></table></div>
                </section>
            </template>

            <div v-if="transactionModal" class="modal-backdrop" @click.self="transactionModal=false"><form class="modal" @submit.prevent="saveTransaction"><div class="modal-title"><div><h2>Record financial transaction</h2><p>Each transaction creates and posts a balanced journal.</p></div><button type="button" class="close" @click="transactionModal=false">×</button></div>
                <label>Transaction type<select v-model="transactionForm.type"><option value="income">Income</option><option value="expense">Expense</option><option value="customer_receipt">Customer receipt / advance</option></select></label>
                <div class="form-grid"><label>Date<input v-model="transactionForm.transaction_date" type="date" required></label><label>Amount (BDT)<input v-model="transactionForm.amount" type="number" min="0.01" step="0.01" required></label></div>
                <label>Cash / Bank account<select v-model="transactionForm.cash_account_id" required><option value="">Select account</option><option v-for="a in cashAccounts" :key="a.id" :value="String(a.id)">{{ a.code }} · {{ a.name }}</option></select></label>
                <label v-if="transactionForm.type !== 'customer_receipt'">{{ transactionForm.type === 'income' ? 'Income category' : 'Expense category' }}<select v-model="transactionForm.category_account_id" required><option value="">Select category</option><option v-for="a in categoryAccounts" :key="a.id" :value="String(a.id)">{{ a.code }} · {{ a.name }}</option></select></label>
                <label v-if="transactionForm.type === 'customer_receipt'">Customer<select v-model="transactionForm.customer_id" required><option value="">Select customer</option><option v-for="customer in customers" :key="customer.id" :value="String(customer.id)">{{ customer.customer_code }} · {{ customer.name }}</option></select></label>
                <div class="form-grid"><label>Payment method<select v-model="transactionForm.payment_method"><option value="cash">Cash</option><option value="bank">Bank</option><option value="mobile_banking">Mobile banking</option><option value="card">Card</option><option value="other">Other</option></select></label><label>Location<select v-model="transactionForm.location_id" required><option value="">Select location</option><option v-for="l in manageableLocations" :key="l.id" :value="String(l.id)">{{ l.name }}</option></select></label></div>
                <label>Description<input v-model="transactionForm.description" required maxlength="500"></label><label>Reference (optional)<input v-model="transactionForm.reference" maxlength="120"></label>
                <div class="modal-actions"><button type="button" class="btn btn-secondary" @click="transactionModal=false">Cancel</button><button class="btn btn-primary" :disabled="saving">{{ saving ? 'Posting…' : 'Save & post' }}</button></div></form></div>

            <div v-if="accountModal" class="modal-backdrop" @click.self="accountModal=false"><form class="modal" @submit.prevent="saveAccount"><div class="modal-title"><div><h2>Add chart-of-account entry</h2><p>Use a unique code and the correct account class.</p></div><button type="button" class="close" @click="accountModal=false">×</button></div>
                <div class="form-grid"><label>Code<input v-model="accountForm.code" required maxlength="24"></label><label>Name<input v-model="accountForm.name" required maxlength="160"></label></div>
                <label>Account type<select v-model="accountForm.type"><option value="asset">Asset</option><option value="liability">Liability</option><option value="equity">Equity</option><option value="income">Income</option><option value="expense">Expense</option></select></label>
                <label class="check"><input v-model="accountForm.is_cash_bank" type="checkbox"> This is a cash / bank account</label>
                <label>Description<input v-model="accountForm.description" maxlength="2000"></label>
                <div class="modal-actions"><button type="button" class="btn btn-secondary" @click="accountModal=false">Cancel</button><button class="btn btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Create account' }}</button></div></form></div>

            <div v-if="journalModal" class="modal-backdrop" @click.self="journalModal=false"><form class="modal wide" @submit.prevent="saveJournal"><div class="modal-title"><div><h2>Manual journal entry</h2><p>Debit and credit totals must match before posting.</p></div><button type="button" class="close" @click="journalModal=false">×</button></div>
                <div class="form-grid"><label>Date<input v-model="journalForm.entry_date" type="date" required></label><label>Location<select v-model="journalForm.location_id" required><option value="">Select location</option><option v-for="l in manageableLocations" :key="l.id" :value="String(l.id)">{{ l.name }}</option></select></label></div>
                <label>Description<input v-model="journalForm.description" required maxlength="500"></label><label>Reference<input v-model="journalForm.reference" maxlength="120"></label>
                <div v-for="(line,index) in journalForm.lines" :key="index" class="journal-line"><select v-model="line.account_id" required><option value="">Account</option><option v-for="a in accounts.filter(x=>x.is_active)" :key="a.id" :value="String(a.id)">{{ a.code }} · {{ a.name }}</option></select><input v-model="line.debit" type="number" min="0" step="0.01" placeholder="Debit"><input v-model="line.credit" type="number" min="0" step="0.01" placeholder="Credit"><button type="button" class="text-button danger" @click="journalForm.lines.splice(index,1)" :disabled="journalForm.lines.length<=2">Remove</button></div>
                <button type="button" class="text-button" @click="addJournalLine">＋ Add line</button>
                <div class="modal-actions"><button type="button" class="btn btn-secondary" @click="journalModal=false">Cancel</button><button class="btn btn-primary" :disabled="saving">{{ saving ? 'Posting…' : 'Create & post journal' }}</button></div></form></div>
        </div>
    </ErpLayout>
</template>

<style scoped>
.finance-page{display:flex;flex-direction:column;gap:20px;color:var(--text-primary,#172033);min-width:0}.page-heading,.panel-heading,.modal-title{display:flex;align-items:center;justify-content:space-between;gap:16px}.page-heading h1{font-size:26px;font-weight:750;margin:2px 0}.eyebrow{font-size:11px;letter-spacing:.12em;font-weight:700;color:#64748b;margin:0}.subtitle,.panel-heading p,.modal-title p{font-size:13px;color:#64748b;margin:4px 0 0}.heading-actions{display:flex;gap:8px;flex-wrap:wrap}.btn{border:1px solid #d7deea;border-radius:9px;padding:9px 13px;font-size:13px;font-weight:650;cursor:pointer}.btn:disabled{opacity:.55;cursor:not-allowed}.btn-primary{background:#174ea6;border-color:#174ea6;color:white}.btn-secondary{background:white;color:#243247}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.toolbar select,.finance-page input,.finance-page select{border:1px solid #d7deea;border-radius:8px;padding:9px 10px;background:white;color:#182338;min-width:0}.tabs{display:flex;gap:4px;flex-wrap:wrap;border-bottom:1px solid #e5eaf1}.tabs button{padding:10px 13px;border:0;background:transparent;color:#64748b;border-bottom:2px solid transparent;cursor:pointer;font-size:13px}.tabs button.active{color:#174ea6;border-color:#174ea6;font-weight:700}.kpi-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.kpi,.panel{background:white;border:1px solid #e4e9f0;border-radius:13px;padding:17px;box-shadow:0 2px 8px #14233b08}.kpi span,.kpi small{display:block;color:#64748b;font-size:12px}.kpi strong{display:block;font-size:24px;margin:9px 0 4px;font-weight:750}.panel{margin-top:16px}.panel-heading{margin-bottom:14px}.panel-heading h2,.modal h2{font-size:16px;font-weight:750;margin:0}.text-button{border:0;background:transparent;color:#1755aa;font-size:12px;font-weight:650;cursor:pointer}.balance-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.balance-item{padding:13px;border:1px solid #edf0f5;border-radius:9px}.balance-item span{display:block;color:#64748b;font-size:12px}.balance-item strong{display:block;margin-top:7px;font-size:18px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;white-space:nowrap;font-size:13px}th{text-align:left;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.04em;background:#f8fafc}td,th{padding:11px 10px;border-bottom:1px solid #edf0f5}.amount{text-align:right;font-weight:700}.system-tag{font-size:10px;color:#64748b;background:#eef2f7;padding:3px 6px;border-radius:5px;margin-left:5px}.status{font-size:11px;padding:4px 7px;border-radius:6px;background:#eef2f7}.status.posted{background:#e8f7ee;color:#16723c}.empty{text-align:center;color:#64748b;padding:28px;font-size:13px}.notice{padding:11px 14px;border-radius:9px;font-size:13px}.notice-error{background:#fff0f0;color:#a32828}.notice-success{background:#eaf8ef;color:#176d3a}.notice button{float:right;border:0;background:none;font-size:18px}.modal-backdrop{position:fixed;inset:0;background:#10182880;z-index:1000;display:grid;place-items:center;padding:20px}.modal{width:min(540px,100%);max-height:90vh;overflow:auto;background:white;border-radius:15px;padding:22px;display:flex;flex-direction:column;gap:13px;box-shadow:0 20px 60px #0003}.modal.wide{width:min(850px,100%)}.modal-title{margin-bottom:5px}.close{border:0;background:#f1f4f8;border-radius:7px;width:30px;height:30px;font-size:20px;cursor:pointer}.modal label{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:650;color:#344054}.modal label.check{flex-direction:row;align-items:center}.modal input,.modal select{width:100%}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:8px}.journal-line{display:grid;grid-template-columns:minmax(180px,2fr) 1fr 1fr auto;gap:8px}.danger{color:#b42318}@media(max-width:760px){.page-heading{align-items:flex-start;flex-direction:column}.kpi-grid,.balance-grid{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}.journal-line{grid-template-columns:1fr 1fr}.journal-line select{grid-column:1/-1}.heading-actions{width:100%}}
</style>
