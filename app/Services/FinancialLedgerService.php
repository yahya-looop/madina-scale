<?php

namespace App\Services;

use App\Models\ControlledAccountingEntry;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialLedgerService
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    /**
     * Create a draft journal. Drafts never affect account balances.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, User $actor): JournalEntry
    {
        $locationId = $this->assertOperationalLocation($data['location_id'] ?? null);

        return DB::transaction(function () use ($data, $actor, $locationId): JournalEntry {
            $entry = JournalEntry::query()->create([
                'entry_number' => $this->number('JE'),
                'entry_date' => $data['entry_date'],
                'description' => trim($data['description']),
                'status' => 'draft',
                'source' => $data['source'] ?? 'manual',
                'reference' => $data['reference'] ?? null,
                'location_id' => $locationId,
                'created_by' => $actor->id,
            ]);

            foreach ($data['lines'] as $line) {
                $account = FinancialAccount::query()->where('is_active', true)->whereKey($line['account_id'])->first();
                if (! $account) {
                    throw ValidationException::withMessages(['lines' => 'Every journal line must use an active account.']);
                }

                $debit = $this->money($line['debit'] ?? '0');
                $credit = $this->money($line['credit'] ?? '0');
                if ((bccomp($debit, '0.00', 2) > 0) === (bccomp($credit, '0.00', 2) > 0)) {
                    throw ValidationException::withMessages(['lines' => 'Each line must contain either a debit or a credit amount, not both.']);
                }

                $entry->lines()->create([
                    'account_id' => $account->id,
                    'location_id' => $locationId,
                    'customer_id' => $line['customer_id'] ?? null,
                    'supplier_id' => $line['supplier_id'] ?? null,
                    'description' => $line['description'] ?? null,
                    'debit' => $debit,
                    'credit' => $credit,
                ]);
            }

            $this->activityLog->recordForUser(
                $actor, 'created', 'finance', 'Created draft journal '.$entry->entry_number,
                $entry, null, ['entry_number' => $entry->entry_number, 'line_count' => $entry->lines()->count()],
            );

            return $entry->load('lines.account');
        });
    }

    /**
     * Post a balanced journal. Posted journals are immutable; corrections must
     * be made through a separate reversing journal in a later controlled flow.
     */
    public function post(JournalEntry $journal, User $actor): JournalEntry
    {
        return DB::transaction(function () use ($journal, $actor): JournalEntry {
            $entry = JournalEntry::query()->lockForUpdate()->findOrFail($journal->id);
            if (str_starts_with($entry->source, 'controlled_')) {
                throw ValidationException::withMessages(['journal' => 'Controlled accounting entries must be posted through their approval workflow.']);
            }
            $locationId = $this->assertOperationalLocation($entry->location_id);
            if ($entry->status !== 'draft') {
                throw ValidationException::withMessages(['journal' => 'Only draft journals can be posted.']);
            }

            $lines = $entry->lines()->with('account')->lockForUpdate()->get();
            if ($lines->contains(fn ($line): bool => (int) $line->location_id !== $locationId)) {
                throw ValidationException::withMessages(['journal' => 'Every journal line must belong to the same location as its journal.']);
            }

            if ($lines->count() < 2) {
                throw ValidationException::withMessages(['journal' => 'A journal needs at least two lines.']);
            }

            $debit = '0.00';
            $credit = '0.00';
            foreach ($lines as $line) {
                if (! $line->account || ! $line->account->is_active) {
                    throw ValidationException::withMessages(['journal' => 'A journal contains an inactive or missing account.']);
                }
                $debit = bcadd($debit, (string) $line->debit, 2);
                $credit = bcadd($credit, (string) $line->credit, 2);
            }

            if (bccomp($debit, '0.00', 2) <= 0 || bccomp($debit, $credit, 2) !== 0) {
                throw ValidationException::withMessages(['journal' => 'Journal is not balanced. Total debit and credit must be equal and greater than zero.']);
            }

            $entry->update([
                'status' => 'posted',
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            $this->activityLog->recordForUser(
                $actor, 'posted', 'finance', 'Posted journal '.$entry->entry_number,
                $entry, ['status' => 'draft'], ['status' => 'posted', 'total_debit' => $debit, 'total_credit' => $credit],
            );

            return $entry->fresh(['lines.account', 'poster']);
        }, 3);
    }

    /**
     * Post a controlled accounting journal after its maker-checker workflow
     * has approved it. This is intentionally separate from operational post().
     */
    public function postControlled(JournalEntry $journal, User $actor, string $workflowType): JournalEntry
    {
        return DB::transaction(function () use ($journal, $actor, $workflowType): JournalEntry {
            $entry = JournalEntry::query()->lockForUpdate()->findOrFail($journal->id);
            $expectedSource = $workflowType === 'opening_balance'
                ? 'controlled_opening_balance'
                : 'controlled_company_adjustment';

            if (! in_array($workflowType, ['opening_balance', 'company_adjustment'], true) || $entry->source !== $expectedSource) {
                throw ValidationException::withMessages(['journal' => 'Journal source does not match the controlled accounting workflow.']);
            }
            if ($entry->status !== 'draft') {
                throw ValidationException::withMessages(['journal' => 'Only draft controlled journals can be posted.']);
            }

            $workflow = ControlledAccountingEntry::query()
                ->where('journal_entry_id', $entry->id)
                ->where('workflow_type', $workflowType)
                ->lockForUpdate()
                ->first();

            if (
                ! $workflow
                || $workflow->status !== 'approved'
                || ! $workflow->approved_by
                || (int) $workflow->approved_by === (int) $workflow->created_by
                || (int) $workflow->created_by === (int) $actor->id
                || (int) $workflow->location_id !== (int) $entry->location_id
            ) {
                throw ValidationException::withMessages(['journal' => 'A controlled accounting journal requires an approved independent workflow before posting.']);
            }

            if ($workflowType === 'opening_balance') {
                if (
                    ! $entry->location_id
                    || ! $workflow->fiscal_year
                    || date('Y', strtotime((string) $entry->entry_date) ?: 0) !== (string) $workflow->fiscal_year
                    || ! Location::query()->whereKey($entry->location_id)->where('is_active', true)->exists()
                ) {
                    throw ValidationException::withMessages(['journal' => 'Opening balance must belong to an active location.']);
                }
            } elseif ($entry->location_id !== null || $workflow->fiscal_year !== null) {
                throw ValidationException::withMessages(['journal' => 'Company-wide adjustment must not be assigned to a location or opening fiscal year.']);
            }

            $lines = $entry->lines()->with('account')->lockForUpdate()->get();
            if ($lines->count() < 2 || $lines->contains(
                fn ($line): bool => $workflowType === 'opening_balance'
                    ? (int) $line->location_id !== (int) $entry->location_id
                    : $line->location_id !== null,
            )) {
                throw ValidationException::withMessages(['journal' => 'Every controlled journal line must match the journal scope.']);
            }

            $debit = '0.00';
            $credit = '0.00';
            foreach ($lines as $line) {
                if (! $line->account || ! $line->account->is_active) {
                    throw ValidationException::withMessages(['journal' => 'A journal contains an inactive or missing account.']);
                }
                $debit = bcadd($debit, (string) $line->debit, 2);
                $credit = bcadd($credit, (string) $line->credit, 2);
            }
            if (bccomp($debit, '0.00', 2) <= 0 || bccomp($debit, $credit, 2) !== 0) {
                throw ValidationException::withMessages(['journal' => 'Journal is not balanced.']);
            }

            $entry->update([
                'status' => 'posted',
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            $this->activityLog->recordForUser(
                $actor,
                'posted',
                'finance',
                'Posted controlled journal '.$entry->entry_number,
                $entry,
                ['status' => 'draft'],
                ['status' => 'posted', 'workflow_type' => $workflowType, 'total_debit' => $debit, 'total_credit' => $credit],
            );

            return $entry->fresh(['lines.account', 'poster']);
        }, 3);
    }

    /**
     * Record cash/bank income, expense or an unallocated customer advance.
     * Customer receipts credit Customer Advances until invoice allocation is
     * introduced with the sales receivables workflow.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordTransaction(array $data, User $actor): FinancialTransaction
    {
        $locationId = $this->assertOperationalLocation($data['location_id'] ?? null);

        return DB::transaction(function () use ($data, $actor, $locationId): FinancialTransaction {
            $idempotencyKey = trim((string) $data['idempotency_key']);
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['madina-scale:finance-transaction:'.$idempotencyKey]);
            }
            $payloadHash = hash('sha256', json_encode(collect($data)->except('idempotency_key')->sortKeys()->all(), JSON_THROW_ON_ERROR));
            $existing = FinancialTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different financial transaction.']);
                }

                return $existing->load(['cashAccount', 'categoryAccount', 'customer', 'journalEntry']);
            }

            $amount = $this->money($data['amount']);
            if (bccomp($amount, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
            }

            $cash = FinancialAccount::query()->where('is_active', true)->where('is_cash_bank', true)
                ->lockForUpdate()->whereKey($data['cash_account_id'])->first();
            if (! $cash) {
                throw ValidationException::withMessages(['cash_account_id' => 'Select an active cash, bank or mobile banking account.']);
            }

            $type = $data['type'];
            $lines = [];
            $category = null;
            $customerId = $data['customer_id'] ?? null;

            if ($type === 'customer_receipt') {
                $customerAdvances = FinancialAccount::query()->where('code', '2100')->where('is_active', true)->firstOrFail();
                if (! $customerId) {
                    throw ValidationException::withMessages(['customer_id' => 'Select the customer for this receipt.']);
                }
                $lines = [
                    ['account_id' => $cash->id, 'debit' => $amount, 'credit' => '0.00'],
                    ['account_id' => $customerAdvances->id, 'debit' => '0.00', 'credit' => $amount, 'customer_id' => $customerId],
                ];
            } else {
                $category = FinancialAccount::query()->where('is_active', true)->whereKey($data['category_account_id'] ?? null)->first();
                $expectedType = $type === 'income' ? 'income' : 'expense';
                if (! $category || $category->type !== $expectedType || $category->is_cash_bank) {
                    throw ValidationException::withMessages(['category_account_id' => 'Choose an active account from the matching '.$expectedType.' category.']);
                }
                $lines = $type === 'income'
                    ? [
                        ['account_id' => $cash->id, 'debit' => $amount, 'credit' => '0.00'],
                        ['account_id' => $category->id, 'debit' => '0.00', 'credit' => $amount],
                    ]
                    : [
                        ['account_id' => $category->id, 'debit' => $amount, 'credit' => '0.00'],
                        ['account_id' => $cash->id, 'debit' => '0.00', 'credit' => $amount],
                    ];
            }

            $entry = JournalEntry::query()->create([
                'entry_number' => $this->number('JE'),
                'entry_date' => $data['transaction_date'],
                'description' => trim($data['description']),
                'status' => 'draft',
                'source' => 'financial_transaction',
                'reference' => $data['reference'] ?? null,
                'location_id' => $locationId,
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create($line + [
                    'location_id' => $locationId,
                    'description' => trim($data['description']),
                ]);
            }

            $this->post($entry, $actor);

            $transaction = FinancialTransaction::query()->create([
                'transaction_number' => $this->number('FT'),
                'type' => $type,
                'transaction_date' => $data['transaction_date'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'cash_account_id' => $cash->id,
                'category_account_id' => $category?->id,
                'customer_id' => $customerId,
                'journal_entry_id' => $entry->id,
                'reference' => $data['reference'] ?? null,
                'description' => trim($data['description']),
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'location_id' => $locationId,
                'created_by' => $actor->id,
            ]);

            $this->activityLog->recordForUser(
                $actor, 'recorded', 'finance', 'Recorded '.$type.' '.$transaction->transaction_number,
                $transaction, null, [
                    'amount' => $amount, 'type' => $type,
                    'journal_entry_id' => $entry->id,
                    'cash_account_id' => $cash->id,
                    'category_account_id' => $category?->id,
                ],
            );

            return $transaction->load(['cashAccount', 'categoryAccount', 'customer', 'journalEntry']);
        }, 3);
    }

    /**
     * Operational ledger entries must always belong to an active location.
     * Company-wide opening balances and adjustments require a separate controlled workflow.
     */
    private function assertOperationalLocation(mixed $value): int
    {
        if (! is_int($value) && (! is_string($value) || ! ctype_digit($value))) {
            throw ValidationException::withMessages(['location_id' => 'A location is required for every operational journal.']);
        }

        $locationId = (int) $value;
        if ($locationId < 1 || ! Location::query()->whereKey($locationId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['location_id' => 'Select an active location for this operational journal.']);
        }

        return $locationId;
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        $value = trim((string) $value);
        $this->assertValidMoneyString($value);

        return bcadd($value, '0', 2);
    }

    /**
     * @phpstan-assert numeric-string $value
     */
    private function assertValidMoneyString(string $value): void
    {
        if (! preg_match('/^\d{1,14}(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid non-negative amount with up to two decimal places.']);
        }
    }

    private function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
    }
}
