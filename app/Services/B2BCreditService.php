<?php

namespace App\Services;

use App\Models\B2BAccount;
use App\Models\SalesOrder;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class B2BCreditService
{
    /** Read-only credit position for account lists and detail screens. */
    public function summary(B2BAccount $account): array
    {
        $query = SalesOrder::query()
            ->where('b2b_account_id', $account->id)
            ->whereIn('status', ['pending', 'confirmed', 'processing', 'partially_fulfilled', 'fulfilled'])
            ->where(function ($query): void {
                $query->whereNull('credit_snapshot')
                    ->orWhere('credit_snapshot->credit_used', true);
            });
        $outstanding = bcadd((string) ($query->sum('grand_total') ?? '0'), '0', 2);
        $limit = bcadd((string) $account->credit_limit, '0', 2);
        $available = bccomp($limit, $outstanding, 2) > 0 ? bcsub($limit, $outstanding, 2) : '0.00';

        return [
            'payment_terms' => $account->payment_terms,
            'credit_limit' => $limit,
            'outstanding' => $outstanding,
            'available_credit' => $available,
            'utilization_percent' => bccomp($limit, '0.00', 2) > 0
                ? (bccomp($outstanding, $limit, 2) >= 0
                    ? '100.00'
                    : bcdiv(bcmul($outstanding, '100', 4), $limit, 2))
                : (bccomp($outstanding, '0.00', 2) > 0 ? '100.00' : '0.00'),
        ];
    }

    /**
     * The caller must run this inside the order transaction. Locking the account
     * serializes all credit-consuming order creation/conversion for that account.
     *
     * Fulfilled orders remain exposed until a payment/settlement workflow exists;
     * only cancelled orders release credit automatically.
     *
     * @return array<string, mixed>
     */
    public function assess(B2BAccount $account, string $orderAmount, ?int $excludeOrderId = null): array
    {
        $locked = B2BAccount::query()->lockForUpdate()->findOrFail($account->id);
        $amount = bcadd($orderAmount, '0', 2);
        if (bccomp($amount, '0.00', 2) < 0) {
            throw ValidationException::withMessages(['credit_limit' => 'Order amount cannot be negative.']);
        }

        $outstandingQuery = SalesOrder::query()
            ->where('b2b_account_id', $locked->id)
            ->whereIn('status', ['pending', 'confirmed', 'processing', 'partially_fulfilled', 'fulfilled'])
            ->where(function ($query): void {
                // Legacy orders without a credit snapshot are conservatively counted.
                // New prepaid orders explicitly record credit_used=false.
                $query->whereNull('credit_snapshot')
                    ->orWhere('credit_snapshot->credit_used', true);
            });
        if ($excludeOrderId) {
            $outstandingQuery->where('id', '!=', $excludeOrderId);
        }
        $outstanding = bcadd((string) ($outstandingQuery->sum('grand_total') ?? '0'), '0', 2);
        $limit = bcadd((string) $locked->credit_limit, '0', 2);
        $available = bccomp($limit, $outstanding, 2) > 0 ? bcsub($limit, $outstanding, 2) : '0.00';
        $usesCredit = $locked->payment_terms !== 'prepaid';

        if ($usesCredit && bccomp($amount, $available, 2) > 0) {
            throw ValidationException::withMessages([
                'credit_limit' => 'The order exceeds this business account’s available credit. Reduce the order amount or increase the approved credit limit.',
            ]);
        }

        $dueDays = match ($locked->payment_terms) {
            'net_7' => 7,
            'net_15' => 15,
            'net_30' => 30,
            'net_60' => 60,
            default => 0,
        };
        $dueAt = $usesCredit
            ? CarbonImmutable::now()->addDays($dueDays)->toIso8601String()
            : null;

        return [
            'payment_terms' => $locked->payment_terms,
            'credit_limit' => $limit,
            'outstanding_before' => $outstanding,
            'available_before' => $available,
            'order_amount' => $amount,
            'available_after' => $usesCredit ? bcsub($available, $amount, 2) : $available,
            'credit_used' => $usesCredit,
            'assessed_at' => now()->toIso8601String(),
            'payment_due_at' => $dueAt,
        ];
    }
}
