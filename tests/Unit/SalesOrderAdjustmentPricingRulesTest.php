<?php

namespace Tests\Unit;

use App\Services\SalesOrderAdjustmentPricingRules;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesOrderAdjustmentPricingRulesTest extends TestCase
{
    public function test_retail_adjustment_allocates_all_discounts_into_final_line_total(): void
    {
        $result = (new SalesOrderAdjustmentPricingRules())->reconcile([
            ['id' => 1, 'quantity' => '2.0000', 'unit_price' => '90.00', 'line_discount' => '10.00'],
        ], [
            'price_basis' => 'catalogue_and_commercial_rules',
            'promotion_discount' => '10.00',
            'manual_discount' => '5.00',
        ], '15.00', '20.00');

        $this->assertSame('180.00', $result['subtotal']);
        $this->assertSame('20.00', $result['discount_total']);
        $this->assertSame('170.00', $result['net_line_subtotal']);
        $this->assertSame('160.00', $result['final_line_subtotal']);
        $this->assertSame('10.00', $result['lines'][0]['line_discount']);
        $this->assertSame('10.00', $result['lines'][0]['allocated_order_discount']);
        $this->assertSame('160.00', $result['lines'][0]['line_total']);
        $this->assertSame('160.00', bcsub($result['subtotal'], $result['discount_total'], 2));
        $this->assertSame('10.00', $result['snapshot']['promotion_discount']);
        $this->assertSame('10.00', $result['snapshot']['manual_discount']);
    }

    public function test_b2b_quote_adjustment_allocates_quote_discount_and_reconciles_line_total(): void
    {
        $result = (new SalesOrderAdjustmentPricingRules())->reconcile([
            ['id' => 7, 'quantity' => '2.0000', 'unit_price' => '120.00', 'line_discount' => '20.00'],
        ], [
            'price_basis' => 'b2b_quote',
            'accepted_discount' => '10.00',
            'accepted_tax' => '0.00',
            'accepted_total' => '190.00',
        ], '30.00', null);

        $this->assertSame('240.00', $result['subtotal']);
        $this->assertSame('30.00', $result['discount_total']);
        $this->assertSame('220.00', $result['net_line_subtotal']);
        $this->assertSame('210.00', $result['final_line_subtotal']);
        $this->assertSame('10.00', $result['snapshot']['accepted_discount']);
        $this->assertSame('220.00', $result['snapshot']['accepted_line_subtotal']);
        $this->assertSame('10.00', $result['lines'][0]['allocated_order_discount']);
        $this->assertSame('210.00', $result['lines'][0]['line_total']);
    }

    public function test_order_discount_is_allocated_proportionally_and_rounding_reconciles_exactly(): void
    {
        $result = (new SalesOrderAdjustmentPricingRules())->reconcile([
            ['id' => 1, 'quantity' => '1.0000', 'unit_price' => '10.00', 'line_discount' => '0.00'],
            ['id' => 2, 'quantity' => '1.0000', 'unit_price' => '20.00', 'line_discount' => '0.00'],
            ['id' => 3, 'quantity' => '1.0000', 'unit_price' => '30.00', 'line_discount' => '0.00'],
        ], [
            'price_basis' => 'catalogue_and_commercial_rules',
            'promotion_discount' => '0.00',
            'manual_discount' => '0.01',
        ], '0.01', null);

        $this->assertSame('60.00', $result['subtotal']);
        $this->assertSame('59.99', $result['final_line_subtotal']);
        $this->assertSame('0.01', bcadd(bcadd($result['lines'][0]['allocated_order_discount'], $result['lines'][1]['allocated_order_discount'], 2), $result['lines'][2]['allocated_order_discount'], 2));
        $this->assertSame('59.99', bcadd(bcadd($result['lines'][0]['line_total'], $result['lines'][1]['line_total'], 2), $result['lines'][2]['line_total'], 2));
    }

    public function test_adjusted_price_cannot_fall_below_existing_line_discount(): void
    {
        $this->expectException(ValidationException::class);
        (new SalesOrderAdjustmentPricingRules())->reconcile([
            ['id' => 1, 'quantity' => '1.0000', 'unit_price' => '5.00', 'line_discount' => '10.00'],
        ], ['price_basis' => 'catalogue_and_commercial_rules', 'promotion_discount' => '10.00', 'manual_discount' => '0.00'], '10.00', null);
    }

    public function test_requested_discount_cannot_remove_applied_promotion_discount(): void
    {
        $this->expectException(ValidationException::class);
        (new SalesOrderAdjustmentPricingRules())->reconcile([
            ['id' => 1, 'quantity' => '1.0000', 'unit_price' => '100.00', 'line_discount' => '10.00'],
        ], ['price_basis' => 'catalogue_and_commercial_rules', 'promotion_discount' => '10.00', 'manual_discount' => '0.00'], '10.00', '5.00');
    }

    public function test_total_discount_cannot_be_less_than_existing_line_discount_allocations(): void
    {
        $this->expectException(ValidationException::class);
        (new SalesOrderAdjustmentPricingRules())->reconcile([
            ['id' => 1, 'quantity' => '1.0000', 'unit_price' => '100.00', 'line_discount' => '15.00'],
        ], ['price_basis' => 'catalogue_and_commercial_rules', 'promotion_discount' => '10.00', 'manual_discount' => '0.00'], '10.00', null);
    }
}
