<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\JournalEntry;
use App\Services\FinancialLedgerService;
use App\Services\LocationInventoryAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
        private readonly LocationInventoryAccessService $locationAccess,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $locationId = $request->integer('location_id') ?: null;
        if ($locationId) {
            $this->locationAccess->assertView($request->user(), $locationId);
        }

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $transactions = FinancialTransaction::query()
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when(! $locationId, fn ($q) => $this->locationAccess->scopeViewable($q, $request->user()))
            ->whereBetween('transaction_date', [$monthStart, $monthEnd]);

        $incomeQuery = DB::table('journal_entry_lines as lines')
            ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->join('financial_accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->where('entries.status', 'posted')
            ->where('accounts.type', 'income')
            ->whereBetween('entries.entry_date', [$monthStart, $monthEnd])
            ->when($locationId, fn ($q) => $q->where('lines.location_id', $locationId))
            ->when(! $locationId, fn ($q) => $this->locationAccess->scopeViewable($q, $request->user(), 'lines.location_id'));
        $monthIncome = $incomeQuery->selectRaw('COALESCE(SUM(lines.credit - lines.debit), 0) as total')->value('total') ?? '0.00';

        $expenseQuery = DB::table('journal_entry_lines as lines')
            ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->join('financial_accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->where('entries.status', 'posted')
            ->where('accounts.type', 'expense')
            ->whereBetween('entries.entry_date', [$monthStart, $monthEnd])
            ->when($locationId, fn ($q) => $q->where('lines.location_id', $locationId))
            ->when(! $locationId, fn ($q) => $this->locationAccess->scopeViewable($q, $request->user(), 'lines.location_id'));
        $monthExpenses = $expenseQuery->selectRaw('COALESCE(SUM(lines.debit - lines.credit), 0) as total')->value('total') ?? '0.00';

        $cashBalances = DB::table('journal_entry_lines as lines')
            ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->join('financial_accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->where('entries.status', 'posted')
            ->where('accounts.is_cash_bank', true)
            ->when($locationId, fn ($q) => $q->where('lines.location_id', $locationId))
            ->when(! $locationId, fn ($q) => $this->locationAccess->scopeViewable($q, $request->user(), 'lines.location_id'))
            ->select('accounts.id', 'accounts.code', 'accounts.name')
            ->selectRaw('COALESCE(SUM(lines.debit - lines.credit), 0) as balance')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->get();

        return response()->json([
            'data' => [
                'month_income' => $monthIncome,
                'month_expenses' => $monthExpenses,
                'customer_receipts' => (clone $transactions)->where('type', 'customer_receipt')->sum('amount'),
                'cash_bank_balances' => $cashBalances,
                'locations' => $this->locationAccess->viewableLocations($request->user())->values(),
                'recent_transactions' => $request->user()->can('system.finance.transactions.view')
                    ? FinancialTransaction::query()
                        ->with(['cashAccount:id,code,name', 'categoryAccount:id,code,name', 'customer:id,name', 'location:id,name'])
                        ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
                        ->when(! $locationId, fn ($q) => $this->locationAccess->scopeViewable($q, $request->user()))
                        ->latest('transaction_date')->latest('id')->limit(8)->get()
                    : [],
            ],
        ]);
    }

    public function customerOptions(): JsonResponse
    {
        return response()->json([
            'data' => Customer::query()->where('status', 'active')->orderBy('name')
                ->limit(500)->get(['id', 'customer_code', 'name', 'phone']),
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $locationId = $request->integer('location_id') ?: null;
        if ($locationId) {
            $this->locationAccess->assertView($request->user(), $locationId);
        }

        $accounts = FinancialAccount::query()
            ->with('parent:id,code,name')
            ->orderBy('code')->get();

        $balances = DB::table('journal_entry_lines as lines')
            ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->where('entries.status', 'posted')
            ->when($locationId, fn ($q) => $q->where('lines.location_id', $locationId))
            ->when(! $locationId, fn ($q) => $this->locationAccess->scopeViewable($q, $request->user(), 'lines.location_id'))
            ->select('lines.account_id')
            ->selectRaw('COALESCE(SUM(lines.debit - lines.credit), 0) as balance')
            ->groupBy('lines.account_id')
            ->pluck('balance', 'account_id');

        $accounts->each(function (FinancialAccount $account) use ($balances): void {
            $raw = (string) ($balances[$account->id] ?? '0.00');
            $account->setAttribute('ledger_balance', $account->normal_balance === 'credit' ? bcmul($raw, '-1', 2) : $raw);
        });

        return response()->json(['data' => $accounts]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:financial_accounts,code'],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
            'parent_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'is_cash_bank' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $expectedNormalBalance = in_array($data['type'], ['asset', 'expense'], true) ? 'debit' : 'credit';
        if ($data['normal_balance'] !== $expectedNormalBalance) {
            return response()->json(['message' => 'Normal balance must follow the account type: '.$expectedNormalBalance.'.'], 422);
        }
        if (($data['is_cash_bank'] ?? false) && ($data['type'] !== 'asset' || $data['normal_balance'] !== 'debit')) {
            return response()->json(['message' => 'Cash and bank accounts must be debit-normal asset accounts.'], 422);
        }

        if (! empty($data['parent_id'])) {
            $parent = FinancialAccount::query()->findOrFail($data['parent_id']);
            if ($parent->type !== $data['type']) {
                return response()->json(['message' => 'A chart-of-account child must use the same account type as its parent.'], 422);
            }
        }

        $account = FinancialAccount::query()->create($data + ['is_system' => false, 'is_active' => true]);

        return response()->json(['message' => 'Financial account created.', 'data' => $account], 201);
    }

    public function updateAccount(Request $request, FinancialAccount $account): JsonResponse
    {
        if ($account->is_system) {
            return response()->json(['message' => 'System chart-of-account records cannot be structurally changed.'], 422);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $account->update($data);

        return response()->json(['message' => 'Financial account updated.', 'data' => $account->fresh()]);
    }

    public function journals(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $journals = JournalEntry::query()
            ->with(['lines.account:id,code,name', 'location:id,name', 'creator:id,name', 'poster:id,name'])
            ->when(! $request->filled('location_id'), fn ($q) => $this->locationAccess->scopeViewable($q, $request->user()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->date('to')))
            ->when($request->filled('location_id'), function ($q) use ($request): void {
                $locationId = $request->integer('location_id');
                $this->locationAccess->assertView($request->user(), $locationId);
                $q->where('location_id', $locationId);
            })
            ->latest('entry_date')->latest('id')->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $journals->items(),
            'meta' => ['current_page' => $journals->currentPage(), 'last_page' => $journals->lastPage(), 'per_page' => $journals->perPage(), 'total' => $journals->total()],
        ]);
    }

    public function storeJournal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:120'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.credit' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        if (! empty($data['location_id'])) {
            $this->locationAccess->assertManage($request->user(), (int) $data['location_id']);
        }

        $entry = $this->ledger->createDraft($data, $request->user());

        return response()->json(['message' => 'Draft journal created.', 'data' => $entry], 201);
    }

    public function postJournal(Request $request, JournalEntry $journal): JsonResponse
    {
        if ($journal->location_id) {
            $this->locationAccess->assertManage($request->user(), (int) $journal->location_id);
        }
        $entry = $this->ledger->post($journal, $request->user());

        return response()->json(['message' => 'Journal posted.', 'data' => $entry]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $items = FinancialTransaction::query()
            ->with(['cashAccount:id,code,name', 'categoryAccount:id,code,name', 'customer:id,name,customer_code', 'location:id,name', 'journalEntry:id,entry_number,status'])
            ->when(! $request->filled('location_id'), fn ($q) => $this->locationAccess->scopeViewable($q, $request->user()))
            ->when($request->filled('type'), fn ($q) => $q->where('type', (string) $request->input('type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->date('to')))
            ->when($request->filled('location_id'), function ($q) use ($request): void {
                $locationId = $request->integer('location_id');
                $this->locationAccess->assertView($request->user(), $locationId);
                $q->where('location_id', $locationId);
            })
            ->latest('transaction_date')->latest('id')->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $items->items(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage(), 'total' => $items->total()],
        ]);
    }

    public function storeTransaction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense', 'customer_receipt'])],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'mobile_banking', 'card', 'other'])],
            'cash_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'category_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'customer_id' => ['required_if:type,customer_receipt', 'nullable', 'integer', 'exists:customers,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:500'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
        ]);

        if (! empty($data['location_id'])) {
            $this->locationAccess->assertManage($request->user(), (int) $data['location_id']);
        }
        $transaction = $this->ledger->recordTransaction($data, $request->user());

        return response()->json(['message' => 'Financial transaction posted.', 'data' => $transaction], $transaction->wasRecentlyCreated ? 201 : 200);
    }
}
