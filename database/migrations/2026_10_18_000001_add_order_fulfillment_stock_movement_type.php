<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_type_check
            CHECK (
                movement_type IN (
                    'opening',
                    'receipt',
                    'issue',
                    'order_fulfillment',
                    'adjustment',
                    'transfer_in',
                    'transfer_out',
                    'reversal'
                )
            )
        SQL);
    }

    public function down(): void
    {
        // Keep rollback safe even if order fulfillment movements have already been recorded.
        DB::statement("UPDATE stock_movements SET movement_type = 'issue' WHERE movement_type = 'order_fulfillment'");
        DB::statement('ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_type_check
            CHECK (
                movement_type IN (
                    'opening',
                    'receipt',
                    'issue',
                    'adjustment',
                    'transfer_in',
                    'transfer_out',
                    'reversal'
                )
            )
        SQL);
    }
};
