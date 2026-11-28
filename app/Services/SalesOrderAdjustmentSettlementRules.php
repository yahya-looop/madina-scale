<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class SalesOrderAdjustmentSettlementRules
{
    /**
     * @throws ValidationException
     */
    public function assertWithinRemainingBalance(string $amount, string $available, string $field, string $message): void
    {
        if (bccomp($amount, '0.00', 2) <= 0 || bccomp($amount, $available, 2) > 0) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertRefundWithinPaidBalance(string $amount, string $paid, string $previousRefunds): void
    {
        $remaining = bcsub($paid, $previousRefunds, 2);
        $this->assertWithinRemainingBalance(
            $amount,
            $remaining,
            'amount',
            'Refund exceeds the order payment balance available for refund: '.$remaining.'. Use customer credit for any remaining customer benefit.',
        );
    }

    /**
     * Refunds and customer-credit notes represent the same customer benefit.
     * They must share one cap so an order cannot receive both for the same paid amount.
     *
     * @throws ValidationException
     */
    public function assertCustomerBenefitWithinPaidBalance(
        string $amount,
        string $paid,
        string $previousRefunds,
        string $previousCustomerCredits,
    ): void {
        $previousBenefits = bcadd($previousRefunds, $previousCustomerCredits, 2);
        $remaining = bcsub($paid, $previousBenefits, 2);

        $this->assertWithinRemainingBalance(
            $amount,
            $remaining,
            'amount',
            'Refunds and customer-credit notes cannot exceed the order payment balance available for customer benefit: '.$remaining.'.',
        );
    }
}
