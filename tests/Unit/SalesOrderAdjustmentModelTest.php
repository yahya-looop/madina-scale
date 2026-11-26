<?php

namespace Tests\Unit;

use App\Models\SalesOrderAdjustment;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class SalesOrderAdjustmentModelTest extends TestCase
{
    public function test_adjustment_exposes_its_immutable_settlement_history_relation(): void
    {
        $relation = (new SalesOrderAdjustment())->settlements();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertSame('sales_order_adjustment_id', $relation->getForeignKeyName());
    }
}
