<?php

use App\Models\FinancialAccount;
use App\Models\Location;
use App\Models\User;
use App\Services\FinancialLedgerService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

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
    $customer = \App\Models\Customer::query()->create([
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
        ->and(\App\Models\FinancialTransaction::query()->where('idempotency_key', $payload['idempotency_key'])->count())->toBe(1);

    $changed = $payload;
    $changed['amount'] = '251.00';
    expect(fn () => $service->recordTransaction($changed, $this->actor))
        ->toThrow(ValidationException::class);
});
