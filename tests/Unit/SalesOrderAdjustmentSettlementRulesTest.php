<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\SalesOrderAdjustmentSettlementRules;
use Illuminate\Validation\ValidationException;

class SalesOrderAdjustmentSettlementRulesTest extends TestCase
{
    public function test_refund_cannot_exceed_paid_order_balance_after_previous_refunds(): void
    {
        $rules = new SalesOrderAdjustmentSettlementRules();

        $this->expectException(ValidationException::class);
        $rules->assertRefundWithinPaidBalance('60.00', '100.00', '50.00');
    }

    public function test_refund_can_equal_remaining_paid_order_balance(): void
    {
        $rules = new SalesOrderAdjustmentSettlementRules();

        $this->assertNull($rules->assertRefundWithinPaidBalance('50.00', '100.00', '50.00'));
    }

    public function test_refund_transaction_cannot_be_reused_beyond_its_unspent_amount(): void
    {
        $rules = new SalesOrderAdjustmentSettlementRules();

        $this->expectException(ValidationException::class);
        $rules->assertWithinRemainingBalance(
            '40.00',
            '30.00',
            'financial_transaction_id',
            'Refund transaction balance exceeded.',
        );
    }

    public function test_partial_refund_transaction_use_is_allowed_within_remaining_amount(): void
    {
        $rules = new SalesOrderAdjustmentSettlementRules();

        $this->assertNull($rules->assertWithinRemainingBalance(
            '30.00',
            '30.00',
            'financial_transaction_id',
            'Refund transaction balance exceeded.',
        ));
    }


    public function test_refund_and_customer_credit_share_the_same_paid_balance_limit(): void
    {
        $rules = new SalesOrderAdjustmentSettlementRules();

        $this->expectException(ValidationException::class);
        $rules->assertCustomerBenefitWithinPaidBalance('25.00', '100.00', '50.00', '30.00');
    }

    public function test_customer_credit_can_use_only_the_remaining_paid_balance_after_refunds_and_credits(): void
    {
        $rules = new SalesOrderAdjustmentSettlementRules();

        $this->assertNull($rules->assertCustomerBenefitWithinPaidBalance('20.00', '100.00', '50.00', '30.00'));
    }

    public function test_zero_or_negative_settlement_is_rejected_by_balance_rule(): void
    {
        $rules = new SalesOrderAdjustmentSettlementRules();

        $this->expectException(ValidationException::class);
        $rules->assertWithinRemainingBalance(
            '0.00',
            '100.00',
            'amount',
            'Settlement must be positive.',
        );
    }
}
