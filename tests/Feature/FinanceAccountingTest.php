<?php

use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FinancialLedgerService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @param  list<string>  $permissionSlugs
 */
function createFinanceApiUser(array $permissionSlugs): User
{
    $user = User::factory()->create();
    $module = Module::query()->firstOrCreate(['slug' => 'finance'], [
        'name' => 'Accounts & Finance',
        'description' => 'Finance authorization test module.',
        'sort_order' => 60,
        'is_system' => true,
        'is_active' => true,
    ]);

    if (! $module->is_active) {
        $module->update(['is_active' => true]);
    }

    $role = Role::query()->create([
        'name' => 'Finance API Test Role',
        'slug' => 'finance-api-test-'.strtolower(Str::random(10)),
        'description' => 'Temporary role for finance authorization tests.',
        'is_system' => false,
        'is_active' => true,
    ]);

    foreach ($permissionSlugs as $slug) {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug], [
            'module_id' => $module->id,
            'name' => ucfirst(str_replace(['system.finance.', '.', '_'], ['', ' ', ' '], $slug)),
            'description' => 'Finance authorization test permission.',
        ]);
        $role->permissions()->attach($permission->id);
    }

    $user->roles()->attach($role->id);

    return $user;
}

function financeLocation(string $suffix): Location
{
    return Location::query()->create([
        'name' => 'Finance '.$suffix,
        'code' => 'FIN-'.strtoupper(Str::random(8)),
        'type' => 'branch',
        'is_active' => true,
    ]);
}

beforeEach(function (): void {
    $this->actor = User::factory()->create();
    $this->location = Location::query()->create([
        'name' => 'Finance Test Branch',
        'code' => 'FIN-'.strtoupper(Str::random(6)),
        'type' => 'branch',
        'is_active' => true,
    ]);
});

it('posts an income transaction as a balanced double-entry journal', function (): void {
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $income = FinancialAccount::query()->where('code', '4100')->firstOrFail();

    $transaction = app(FinancialLedgerService::class)->recordTransaction([
        'type' => 'income',
        'idempotency_key' => (string) Str::uuid(),
        'transaction_date' => now()->toDateString(),
        'amount' => '1250.50',
        'payment_method' => 'cash',
        'cash_account_id' => $cash->id,
        'category_account_id' => $income->id,
        'description' => 'Test service income',
        'location_id' => $this->location->id,
    ], $this->actor);

    expect($transaction->journalEntry->status)->toBe('posted')
        ->and($transaction->journalEntry->lines)->toHaveCount(2)
        ->and(bcadd((string) $transaction->journalEntry->lines->first()->debit, (string) $transaction->journalEntry->lines->last()->debit, 2))->toBe('1250.50')
        ->and(bcadd((string) $transaction->journalEntry->lines->first()->credit, (string) $transaction->journalEntry->lines->last()->credit, 2))->toBe('1250.50');
});

it('does not post an unbalanced manual journal', function (): void {
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $expense = FinancialAccount::query()->where('code', '6000')->firstOrFail();

    $journal = app(FinancialLedgerService::class)->createDraft([
        'entry_date' => now()->toDateString(),
        'description' => 'Unbalanced test journal',
        'location_id' => $this->location->id,
        'lines' => [
            ['account_id' => $expense->id, 'debit' => '100.00', 'credit' => '0'],
            ['account_id' => $cash->id, 'debit' => '0', 'credit' => '90.00'],
        ],
    ], $this->actor);

    expect(fn () => app(FinancialLedgerService::class)->post($journal, $this->actor))
        ->toThrow(ValidationException::class);

    expect($journal->fresh()->status)->toBe('draft');
});

it('records customer receipts as customer advances rather than premature sales revenue', function (): void {
    $customer = Customer::query()->create([
        'customer_code' => 'FINCUS-'.strtoupper(Str::random(6)),
        'name' => 'Finance Test Customer',
        'status' => 'active',
    ]);
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();

    $transaction = app(FinancialLedgerService::class)->recordTransaction([
        'type' => 'customer_receipt',
        'idempotency_key' => (string) Str::uuid(),
        'transaction_date' => now()->toDateString(),
        'amount' => '500.00',
        'payment_method' => 'cash',
        'cash_account_id' => $cash->id,
        'customer_id' => $customer->id,
        'description' => 'Customer advance',
        'location_id' => $this->location->id,
    ], $this->actor);

    expect($transaction->journalEntry->status)->toBe('posted')
        ->and($transaction->journalEntry->lines->where('customer_id', $customer->id))->toHaveCount(1)
        ->and($transaction->journalEntry->lines->where('credit', '500.00'))->toHaveCount(1);
});

it('replays the same financial transaction idempotently and rejects key reuse with changed data', function (): void {
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $income = FinancialAccount::query()->where('code', '4100')->firstOrFail();
    $payload = [
        'type' => 'income',
        'idempotency_key' => (string) Str::uuid(),
        'transaction_date' => now()->toDateString(),
        'amount' => '250.00',
        'payment_method' => 'cash',
        'cash_account_id' => $cash->id,
        'category_account_id' => $income->id,
        'description' => 'Idempotent test income',
        'location_id' => $this->location->id,
    ];

    $service = app(FinancialLedgerService::class);
    $first = $service->recordTransaction($payload, $this->actor);
    $replay = $service->recordTransaction($payload, $this->actor);

    expect($replay->id)->toBe($first->id)
        ->and(FinancialTransaction::query()->where('idempotency_key', $payload['idempotency_key'])->count())->toBe(1);

    $changed = $payload;
    $changed['amount'] = '251.00';
    expect(fn () => $service->recordTransaction($changed, $this->actor))
        ->toThrow(ValidationException::class);
});

it('rejects operational draft journals without an active location', function (): void {
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $expense = FinancialAccount::query()->where('code', '6000')->firstOrFail();

    expect(fn () => app(FinancialLedgerService::class)->createDraft([
        'entry_date' => now()->toDateString(),
        'description' => 'Location-less operational journal',
        'lines' => [
            ['account_id' => $expense->id, 'debit' => '100.00', 'credit' => '0'],
            ['account_id' => $cash->id, 'debit' => '0', 'credit' => '100.00'],
        ],
    ], $this->actor))->toThrow(ValidationException::class);
});

it('rejects posting legacy location-less journals through the operational ledger service', function (): void {
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $expense = FinancialAccount::query()->where('code', '6000')->firstOrFail();
    $journal = JournalEntry::query()->create([
        'entry_number' => 'JE-LEGACY-'.strtoupper(Str::random(8)),
        'entry_date' => now()->toDateString(),
        'description' => 'Legacy location-less draft',
        'status' => 'draft',
        'source' => 'manual',
        'created_by' => $this->actor->id,
    ]);
    $journal->lines()->createMany([
        ['account_id' => $expense->id, 'debit' => '100.00', 'credit' => '0.00'],
        ['account_id' => $cash->id, 'debit' => '0.00', 'credit' => '100.00'],
    ]);

    expect(fn () => app(FinancialLedgerService::class)->post($journal, $this->actor))
        ->toThrow(ValidationException::class);
    expect($journal->fresh()->status)->toBe('draft');
});

it('rejects journal lines assigned to a different location from their parent journal', function (): void {
    $otherLocation = Location::query()->create([
        'name' => 'Other Finance Branch',
        'code' => 'FIN-'.strtoupper(Str::random(6)),
        'type' => 'branch',
        'is_active' => true,
    ]);
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $expense = FinancialAccount::query()->where('code', '6000')->firstOrFail();
    $journal = app(FinancialLedgerService::class)->createDraft([
        'entry_date' => now()->toDateString(),
        'description' => 'Mismatched location lines',
        'location_id' => $this->location->id,
        'lines' => [
            ['account_id' => $expense->id, 'debit' => '100.00', 'credit' => '0'],
            ['account_id' => $cash->id, 'debit' => '0', 'credit' => '100.00'],
        ],
    ], $this->actor);
    $journal->lines()->firstOrFail()->update(['location_id' => $otherLocation->id]);

    expect(fn () => app(FinancialLedgerService::class)->post($journal, $this->actor))
        ->toThrow(ValidationException::class);
    expect($journal->fresh()->status)->toBe('draft');
});

it('rejects operational financial transactions without a location', function (): void {
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $income = FinancialAccount::query()->where('code', '4100')->firstOrFail();

    expect(fn () => app(FinancialLedgerService::class)->recordTransaction([
        'type' => 'income',
        'idempotency_key' => (string) Str::uuid(),
        'transaction_date' => now()->toDateString(),
        'amount' => '100.00',
        'payment_method' => 'cash',
        'cash_account_id' => $cash->id,
        'category_account_id' => $income->id,
        'description' => 'Location-less income',
    ], $this->actor))->toThrow(ValidationException::class);
});

it('scopes finance journal and transaction lists to assigned locations', function (): void {
    $otherLocation = financeLocation('Other Branch');
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $income = FinancialAccount::query()->where('code', '4100')->firstOrFail();
    $service = app(FinancialLedgerService::class);

    $visibleJournal = $service->createDraft([
        'entry_date' => now()->toDateString(),
        'description' => 'Visible location journal',
        'location_id' => $this->location->id,
        'lines' => [
            ['account_id' => $income->id, 'debit' => '100.00', 'credit' => '0.00'],
            ['account_id' => $cash->id, 'debit' => '0.00', 'credit' => '100.00'],
        ],
    ], $this->actor);
    $hiddenJournal = $service->createDraft([
        'entry_date' => now()->toDateString(),
        'description' => 'Other location journal',
        'location_id' => $otherLocation->id,
        'lines' => [
            ['account_id' => $income->id, 'debit' => '200.00', 'credit' => '0.00'],
            ['account_id' => $cash->id, 'debit' => '0.00', 'credit' => '200.00'],
        ],
    ], $this->actor);

    $visibleTransaction = $service->recordTransaction([
        'type' => 'income',
        'idempotency_key' => (string) Str::uuid(),
        'transaction_date' => now()->toDateString(),
        'amount' => '100.00',
        'payment_method' => 'cash',
        'cash_account_id' => $cash->id,
        'category_account_id' => $income->id,
        'description' => 'Visible location income',
        'location_id' => $this->location->id,
    ], $this->actor);
    $hiddenTransaction = $service->recordTransaction([
        'type' => 'income',
        'idempotency_key' => (string) Str::uuid(),
        'transaction_date' => now()->toDateString(),
        'amount' => '200.00',
        'payment_method' => 'cash',
        'cash_account_id' => $cash->id,
        'category_account_id' => $income->id,
        'description' => 'Other location income',
        'location_id' => $otherLocation->id,
    ], $this->actor);

    $reader = createFinanceApiUser([
        'system.finance.journals.view',
        'system.finance.transactions.view',
    ]);
    $reader->locations()->attach($this->location->id, ['access_level' => 'view']);

    $journalResponse = $this->actingAs($reader)->getJson('/api/v1/backend/finance/journals')->assertOk();
    $journalIds = collect($journalResponse->json('data'))->pluck('id')->map(fn ($id): int => (int) $id);
    expect($journalIds)->toContain($visibleJournal->id)->not->toContain($hiddenJournal->id);

    $transactionResponse = $this->getJson('/api/v1/backend/finance/transactions')->assertOk();
    $transactionIds = collect($transactionResponse->json('data'))->pluck('id')->map(fn ($id): int => (int) $id);
    expect($transactionIds)->toContain($visibleTransaction->id)->not->toContain($hiddenTransaction->id);
});

it('scopes finance overview ledger totals to assigned locations', function (): void {
    $otherLocation = financeLocation('Other Totals Branch');
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $income = FinancialAccount::query()->where('code', '4100')->firstOrFail();
    $service = app(FinancialLedgerService::class);

    $service->recordTransaction([
        'type' => 'income',
        'idempotency_key' => (string) Str::uuid(),
        'transaction_date' => now()->toDateString(),
        'amount' => '125.00',
        'payment_method' => 'cash',
        'cash_account_id' => $cash->id,
        'category_account_id' => $income->id,
        'description' => 'Visible branch income',
        'location_id' => $this->location->id,
    ], $this->actor);

    $service->recordTransaction([
        'type' => 'income',
        'idempotency_key' => (string) Str::uuid(),
        'transaction_date' => now()->toDateString(),
        'amount' => '875.00',
        'payment_method' => 'cash',
        'cash_account_id' => $cash->id,
        'category_account_id' => $income->id,
        'description' => 'Hidden branch income',
        'location_id' => $otherLocation->id,
    ], $this->actor);

    $reader = createFinanceApiUser([
        'system.finance.view',
        'system.finance.transactions.view',
    ]);
    $reader->locations()->attach($this->location->id, ['access_level' => 'view']);

    $response = $this->actingAs($reader)->getJson('/api/v1/backend/finance/overview')->assertOk();

    expect(bccomp((string) $response->json('data.month_income'), '125.00', 2))->toBe(0)
        ->and(collect($response->json('data.recent_transactions'))->pluck('description'))
        ->toContain('Visible branch income')
        ->not->toContain('Hidden branch income');
});

it('blocks cross-location journal creation, posting and transaction creation without mutations', function (): void {
    $otherLocation = financeLocation('Unauthorized Branch');
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $expense = FinancialAccount::query()->where('code', '6000')->firstOrFail();
    $income = FinancialAccount::query()->where('code', '4100')->firstOrFail();

    $otherJournal = app(FinancialLedgerService::class)->createDraft([
        'entry_date' => now()->toDateString(),
        'description' => 'Other location draft',
        'location_id' => $otherLocation->id,
        'lines' => [
            ['account_id' => $expense->id, 'debit' => '100.00', 'credit' => '0.00'],
            ['account_id' => $cash->id, 'debit' => '0.00', 'credit' => '100.00'],
        ],
    ], $this->actor);

    $writer = createFinanceApiUser([
        'system.finance.journals.create',
        'system.finance.journals.post',
        'system.finance.transactions.create',
    ]);
    $writer->locations()->attach($this->location->id, ['access_level' => 'manage']);

    $this->actingAs($writer);

    $this->postJson('/api/v1/backend/finance/journals', [
        'entry_date' => now()->toDateString(),
        'description' => 'Unauthorized cross-location journal',
        'location_id' => $otherLocation->id,
        'lines' => [
            ['account_id' => $expense->id, 'debit' => '100.00', 'credit' => '0.00'],
            ['account_id' => $cash->id, 'debit' => '0.00', 'credit' => '100.00'],
        ],
    ])->assertForbidden();

    $this->postJson('/api/v1/backend/finance/journals/'.$otherJournal->id.'/post')->assertForbidden();

    $this->postJson('/api/v1/backend/finance/transactions', [
        'type' => 'income',
        'idempotency_key' => (string) Str::uuid(),
        'transaction_date' => now()->toDateString(),
        'amount' => '100.00',
        'payment_method' => 'cash',
        'cash_account_id' => $cash->id,
        'category_account_id' => $income->id,
        'description' => 'Unauthorized cross-location income',
        'location_id' => $otherLocation->id,
    ])->assertForbidden();

    expect($otherJournal->fresh()->status)->toBe('draft')
        ->and(FinancialTransaction::query()->count())->toBe(0);
});

it('does not allow view-only location access to create or post finance records', function (): void {
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $expense = FinancialAccount::query()->where('code', '6000')->firstOrFail();
    $income = FinancialAccount::query()->where('code', '4100')->firstOrFail();

    $draft = app(FinancialLedgerService::class)->createDraft([
        'entry_date' => now()->toDateString(),
        'description' => 'View-only user posting guard',
        'location_id' => $this->location->id,
        'lines' => [
            ['account_id' => $expense->id, 'debit' => '50.00', 'credit' => '0.00'],
            ['account_id' => $cash->id, 'debit' => '0.00', 'credit' => '50.00'],
        ],
    ], $this->actor);

    $reader = createFinanceApiUser([
        'system.finance.journals.create',
        'system.finance.journals.post',
        'system.finance.transactions.create',
    ]);
    $reader->locations()->attach($this->location->id, ['access_level' => 'view']);

    $this->actingAs($reader);

    $this->postJson('/api/v1/backend/finance/journals', [
        'entry_date' => now()->toDateString(),
        'description' => 'View-only journal creation',
        'location_id' => $this->location->id,
        'lines' => [
            ['account_id' => $expense->id, 'debit' => '50.00', 'credit' => '0.00'],
            ['account_id' => $cash->id, 'debit' => '0.00', 'credit' => '50.00'],
        ],
    ])->assertForbidden();

    $this->postJson('/api/v1/backend/finance/journals/'.$draft->id.'/post')->assertForbidden();

    $this->postJson('/api/v1/backend/finance/transactions', [
        'type' => 'income',
        'idempotency_key' => (string) Str::uuid(),
        'transaction_date' => now()->toDateString(),
        'amount' => '50.00',
        'payment_method' => 'cash',
        'cash_account_id' => $cash->id,
        'category_account_id' => $income->id,
        'description' => 'View-only transaction creation',
        'location_id' => $this->location->id,
    ])->assertForbidden();

    expect($draft->fresh()->status)->toBe('draft')
        ->and(FinancialTransaction::query()->count())->toBe(0);
});

it('requires finance permissions for customer options and chart of accounts mutations', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/backend/finance/customer-options')
        ->assertForbidden();

    $this->postJson('/api/v1/backend/finance/accounts', [
        'code' => 'TEST-'.strtoupper(Str::random(6)),
        'name' => 'Unauthorized Account',
        'type' => 'expense',
        'normal_balance' => 'debit',
    ])->assertForbidden();
});
