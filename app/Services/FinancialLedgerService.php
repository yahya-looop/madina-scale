<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\JournalEntry;
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
     * @param array<string, mixed> $data
     */
    public function createDraft(array $data, User $actor): JournalEntry
    {
        return DB::transaction(function () use ($data, $actor): JournalEntry {
            $entry = JournalEntry::query()->create([
                'entry_number' => $this->number('JE'),
                'entry_date' => $data['entry_date'],
                'description' => trim($data['description']),
                'status' => 'draft',
                'source' => 'manual',
                'reference' => $data['reference'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($data['lines'] as $line) {
                $account = FinancialAccount::query()->where('is_active', true)->find($line['account_id']);
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
                    'location_id' => $data['location_id'],
                    'customer_id' => $line['customer_id'] ?? null,
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
            if ($entry->status !== 'draft') {
                throw ValidationException::withMessages(['journal' => 'Only draft journals can be posted.']);
            }

            $lines = $entry->lines()->with('account')->lockForUpdate()->get();
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
     * Record cash/bank income, expense or an unallocated customer advance.
     * Customer receipts credit Customer Advances until invoice allocation is
     * introduced with the sales receivables workflow.
     *
     * @param array<string, mixed> $data
     */
    public function recordTransaction(array $data, User $actor): FinancialTransaction
    {
        return DB::transaction(function () use ($data, $actor): FinancialTransaction {
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
                ->lockForUpdate()->find($data['cash_account_id']);
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
                $category = FinancialAccount::query()->where('is_active', true)->find($data['category_account_id'] ?? null);
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
                'location_id' => $data['location_id'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create($line + [
                    'location_id' => $data['location_id'] ?? null,
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
                'location_id' => $data['location_id'] ?? null,
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

    private function money(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d{1,14}(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid non-negative amount with up to two decimal places.']);
        }

        return bcadd($value, '0', 2);
    }

    private function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
    }
}
