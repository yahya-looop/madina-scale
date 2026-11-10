<?php

namespace App\Services;

use App\Models\ControlledAccountingEntry;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ControlledAccountingService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly LocationInventoryAccessService $locationAccess,
        private readonly FinancialLedgerService $ledger,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $workflowType, array $data, User $actor): ControlledAccountingEntry
    {
        if (! in_array($workflowType, ['opening_balance', 'company_adjustment'], true)) {
            throw ValidationException::withMessages([
                'workflow_type' => 'Unsupported controlled accounting workflow.',
            ]);
        }

        $permission = $workflowType === 'opening_balance'
            ? 'system.finance.opening_balances.create'
            : 'system.finance.company_adjustments.create';

        $this->assertPermission($actor, $permission);

        $locationId = null;
        $fiscalYear = null;

        if ($workflowType === 'opening_balance') {
            $locationId = (int) ($data['location_id'] ?? 0);
            $fiscalYear = (int) ($data['fiscal_year'] ?? 0);

            $location = Location::query()
                ->whereKey($locationId)
                ->where('is_active', true)
                ->first();

            if (! $location || $locationId < 1) {
                throw ValidationException::withMessages([
                    'location_id' => 'Select an active location.',
                ]);
            }

            $entryDateTimestamp = strtotime((string) ($data['entry_date'] ?? ''));

            if (
                $fiscalYear < 2000
                || $fiscalYear > 2100
                || $entryDateTimestamp === false
                || (int) date('Y', $entryDateTimestamp) !== $fiscalYear
            ) {
                throw ValidationException::withMessages([
                    'fiscal_year' => 'Fiscal year must match the opening journal entry date.',
                ]);
            }

            $this->locationAccess->assertManage($actor, $locationId);
        } elseif (
            array_key_exists('location_id', $data)
            && $data['location_id'] !== null
            && $data['location_id'] !== ''
        ) {
            throw ValidationException::withMessages([
                'location_id' => 'Company-wide adjustments cannot be assigned to a location.',
            ]);
        }

        return DB::transaction(function () use (
            $workflowType,
            $data,
            $actor,
            $locationId,
            $fiscalYear,
        ): ControlledAccountingEntry {
            if ($workflowType === 'opening_balance') {
                // Lock the location row to serialize concurrent submissions for the same fiscal year.
                Location::query()
                    ->whereKey($locationId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $alreadyExists = ControlledAccountingEntry::query()
                    ->where('workflow_type', 'opening_balance')
                    ->where('location_id', $locationId)
                    ->where('fiscal_year', $fiscalYear)
                    ->whereIn('status', ['submitted', 'approved', 'posted'])
                    ->exists();

                if ($alreadyExists) {
                    throw ValidationException::withMessages([
                        'fiscal_year' => 'An opening balance already exists for this location and fiscal year.',
                    ]);
                }
            }

            $this->assertBalancedLines($data['lines']);

            $journal = JournalEntry::query()->create([
                'entry_number' => 'JE-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)),
                'entry_date' => $data['entry_date'],
                'description' => trim((string) $data['description']),
                'status' => 'draft',
                'source' => $workflowType === 'opening_balance'
                    ? 'controlled_opening_balance'
                    : 'controlled_company_adjustment',
                'reference' => $data['reference'] ?? null,
                'location_id' => $locationId,
                'created_by' => $actor->id,
            ]);

            foreach ($data['lines'] as $line) {
                $account = FinancialAccount::query()
                    ->whereKey($line['account_id'])
                    ->where('is_active', true)
                    ->first();

                if (! $account) {
                    throw ValidationException::withMessages([
                        'lines' => 'Every journal line must use an active financial account.',
                    ]);
                }

                $debit = $this->money($line['debit'] ?? '0');
                $credit = $this->money($line['credit'] ?? '0');

                if ((bccomp($debit, '0.00', 2) > 0) === (bccomp($credit, '0.00', 2) > 0)) {
                    throw ValidationException::withMessages([
                        'lines' => 'Each line must contain either a debit or a credit amount, not both.',
                    ]);
                }

                $journal->lines()->create([
                    'account_id' => $account->id,
                    'location_id' => $locationId,
                    'description' => $line['description'] ?? null,
                    'debit' => $debit,
                    'credit' => $credit,
                    'customer_id' => $line['customer_id'] ?? null,
                    'supplier_id' => $line['supplier_id'] ?? null,
                ]);
            }

            $entry = ControlledAccountingEntry::query()->create([
                'journal_entry_id' => $journal->id,
                'workflow_type' => $workflowType,
                'fiscal_year' => $fiscalYear,
                'location_id' => $locationId,
                'status' => 'submitted',
                'created_by' => $actor->id,
                'submitted_at' => now(),
            ]);

            $this->activityLog->recordForUser(
                $actor,
                'submitted',
                'finance',
                'Submitted '.$workflowType.' '.$journal->entry_number.' for approval',
                $entry,
                null,
                [
                    'status' => 'submitted',
                    'workflow_type' => $workflowType,
                    'journal_entry_id' => $journal->id,
                ],
            );

            return $entry->load(['journal.lines.account', 'location', 'creator']);
        }, 3);
    }

    public function approve(
        ControlledAccountingEntry $entry,
        User $actor,
        ?string $note = null,
    ): ControlledAccountingEntry {
        return DB::transaction(function () use ($entry, $actor, $note): ControlledAccountingEntry {
            $workflow = ControlledAccountingEntry::query()
                ->lockForUpdate()
                ->findOrFail($entry->id);

            $this->assertActionPermission($workflow, $actor, 'approve');

            if ($workflow->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'entry' => 'Only submitted entries can be approved.',
                ]);
            }

            if ((int) $workflow->created_by === (int) $actor->id) {
                abort(403, 'The creator cannot approve their own accounting entry.');
            }

            $workflow->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approval_note' => $note,
            ]);

            $this->activityLog->recordForUser(
                $actor,
                'approved',
                'finance',
                'Approved controlled accounting entry #'.$workflow->id,
                $workflow,
                ['status' => 'submitted'],
                ['status' => 'approved', 'created_by' => $workflow->created_by],
            );

            return $workflow->fresh(['journal.lines.account', 'location', 'creator', 'approver']);
        }, 3);
    }

    public function reject(
        ControlledAccountingEntry $entry,
        User $actor,
        string $note,
    ): ControlledAccountingEntry {
        return DB::transaction(function () use ($entry, $actor, $note): ControlledAccountingEntry {
            $workflow = ControlledAccountingEntry::query()
                ->lockForUpdate()
                ->findOrFail($entry->id);

            $this->assertActionPermission($workflow, $actor, 'approve');

            if ($workflow->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'entry' => 'Only submitted entries can be rejected.',
                ]);
            }

            if ((int) $workflow->created_by === (int) $actor->id) {
                abort(403, 'The creator cannot reject their own accounting entry.');
            }

            $rejectionNote = trim($note);

            $workflow->update([
                'status' => 'rejected',
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'approval_note' => $rejectionNote,
            ]);

            $this->activityLog->recordForUser(
                $actor,
                'rejected',
                'finance',
                'Rejected controlled accounting entry #'.$workflow->id,
                $workflow,
                ['status' => 'submitted'],
                ['status' => 'rejected', 'reason' => $rejectionNote],
            );

            return $workflow->fresh(['journal.lines.account', 'location', 'creator']);
        }, 3);
    }

    public function post(ControlledAccountingEntry $entry, User $actor): ControlledAccountingEntry
    {
        return DB::transaction(function () use ($entry, $actor): ControlledAccountingEntry {
            $workflow = ControlledAccountingEntry::query()
                ->lockForUpdate()
                ->findOrFail($entry->id);

            $this->assertActionPermission($workflow, $actor, 'post');

            if ($workflow->status !== 'approved') {
                throw ValidationException::withMessages([
                    'entry' => 'Only approved controlled accounting entries can be posted.',
                ]);
            }

            if ((int) $workflow->created_by === (int) $actor->id) {
                abort(403, 'The creator cannot post their own accounting entry.');
            }

            $journal = JournalEntry::query()->findOrFail($workflow->journal_entry_id);

            $this->ledger->postControlled($journal, $actor, $workflow->workflow_type);

            $workflow->update([
                'status' => 'posted',
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            $this->activityLog->recordForUser(
                $actor,
                'posted',
                'finance',
                'Posted controlled accounting entry #'.$workflow->id,
                $workflow,
                ['status' => 'approved'],
                ['status' => 'posted', 'journal_entry_id' => $journal->id],
            );

            return $workflow->fresh(['journal.lines.account', 'location', 'creator', 'approver', 'poster']);
        }, 3);
    }

    private function assertActionPermission(
        ControlledAccountingEntry $entry,
        User $actor,
        string $action,
    ): void {
        $prefix = $entry->workflow_type === 'opening_balance'
            ? 'system.finance.opening_balances.'
            : 'system.finance.company_adjustments.';

        $this->assertPermission($actor, $prefix.$action);

        if ($entry->workflow_type === 'opening_balance') {
            if (! $entry->location_id) {
                throw ValidationException::withMessages([
                    'entry' => 'Opening balances must belong to a location.',
                ]);
            }

            $this->locationAccess->assertManage($actor, (int) $entry->location_id);
        } elseif ($entry->workflow_type !== 'company_adjustment' || $entry->location_id !== null) {
            throw ValidationException::withMessages([
                'entry' => 'Invalid company-wide accounting scope.',
            ]);
        }
    }

    private function assertPermission(User $actor, string $permission): void
    {
        abort_unless(
            $this->authorization->can($actor, $permission),
            403,
            'You are not authorized to perform this accounting action.',
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function assertBalancedLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'A journal requires at least two lines.',
            ]);
        }

        $debitTotal = '0.00';
        $creditTotal = '0.00';

        foreach ($lines as $line) {
            $debit = $this->money($line['debit'] ?? '0');
            $credit = $this->money($line['credit'] ?? '0');

            if ((bccomp($debit, '0.00', 2) > 0) === (bccomp($credit, '0.00', 2) > 0)) {
                throw ValidationException::withMessages([
                    'lines' => 'Each line must contain either a debit or a credit amount, not both.',
                ]);
            }

            $debitTotal = bcadd($debitTotal, $debit, 2);
            $creditTotal = bcadd($creditTotal, $credit, 2);
        }

        if (bccomp($debitTotal, '0.00', 2) <= 0 || bccomp($debitTotal, $creditTotal, 2) !== 0) {
            throw ValidationException::withMessages([
                'lines' => 'Total debit and credit must be equal and greater than zero.',
            ]);
        }
    }

    /**
     * Normalize and validate an accounting amount before BCMath operations.
     *
     * @return numeric-string
     */
    private function money(mixed $value): string
    {
        $value = trim((string) $value);

        if (! $this->isValidMoney($value)) {
            throw ValidationException::withMessages([
                'lines' => 'Enter valid non-negative amounts with up to two decimal places.',
            ]);
        }

        return bcadd($value, '0.00', 2);
    }

    /**
     * Check the exact accepted decimal format for accounting amounts.
     *
     * @phpstan-assert-if-true numeric-string $value
     */
    private function isValidMoney(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A\d{1,14}(?:\.\d{1,2})?\z/', $value) === 1;
    }
}
