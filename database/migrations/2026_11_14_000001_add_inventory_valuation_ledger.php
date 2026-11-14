<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->decimal('average_unit_cost', 18, 6)->nullable()->default('0.000000')->after('reserved_quantity');
            $table->decimal('inventory_value', 18, 2)->nullable()->default('0.00')->after('average_unit_cost');
            $table->string('valuation_status', 24)->default('valued')->after('inventory_value');
        });

        // Existing positive stock is intentionally not assigned an invented cost.
        DB::table('inventory_stocks')->where('quantity', '>', 0)->update([
            'average_unit_cost' => null,
            'inventory_value' => null,
            'valuation_status' => 'pending_opening',
        ]);

        DB::table('inventory_stocks')->where('quantity', '<=', 0)->update([
            'average_unit_cost' => '0.000000',
            'inventory_value' => '0.00',
            'valuation_status' => 'valued',
        ]);

        Schema::create('inventory_valuation_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_stock_id')->constrained('inventory_stocks')->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->string('movement_type', 32);
            $table->decimal('quantity_delta', 18, 4);
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('value_delta', 18, 2);
            $table->decimal('quantity_before', 18, 4);
            $table->decimal('quantity_after', 18, 4);
            $table->decimal('value_before', 18, 2);
            $table->decimal('value_after', 18, 2);
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['inventory_stock_id', 'id']);
            $table->index(['reference_type', 'reference_id']);
        });

        DB::statement("ALTER TABLE inventory_valuation_movements ADD CONSTRAINT inventory_valuation_movements_quantity_check CHECK (quantity_after = quantity_before + quantity_delta)");
        DB::statement("ALTER TABLE inventory_valuation_movements ADD CONSTRAINT inventory_valuation_movements_value_check CHECK (value_after = value_before + value_delta AND value_after >= 0)");
        DB::statement("ALTER TABLE inventory_stocks ADD CONSTRAINT inventory_stocks_valuation_status_check CHECK (valuation_status IN ('pending_opening', 'valued'))");
        DB::statement("ALTER TABLE inventory_stocks ADD CONSTRAINT inventory_stocks_valuation_nonnegative_check CHECK ((inventory_value IS NULL OR inventory_value >= 0) AND (average_unit_cost IS NULL OR average_unit_cost >= 0))");

        DB::table('financial_accounts')->updateOrInsert(
            ['code' => '5100'],
            [
                'name' => 'Purchase Price Variance',
                'type' => 'expense',
                'normal_balance' => 'debit',
                'is_system' => true,
                'is_cash_bank' => false,
                'is_active' => true,
                'description' => 'Difference between supplier return credit and inventory carrying value.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_inventory_valuation_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Inventory valuation ledger entries are immutable';
            END;
            $$ LANGUAGE plpgsql;
        
            CREATE TRIGGER inventory_valuation_movements_immutable
            BEFORE UPDATE OR DELETE ON inventory_valuation_movements
            FOR EACH ROW EXECUTE FUNCTION prevent_inventory_valuation_mutation();
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS inventory_valuation_movements_immutable ON inventory_valuation_movements');
        DB::statement('DROP FUNCTION IF EXISTS prevent_inventory_valuation_mutation()');
        Schema::dropIfExists('inventory_valuation_movements');
        DB::statement('ALTER TABLE inventory_stocks DROP CONSTRAINT IF EXISTS inventory_stocks_valuation_status_check');
        DB::statement('ALTER TABLE inventory_stocks DROP CONSTRAINT IF EXISTS inventory_stocks_valuation_nonnegative_check');
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->dropColumn(['average_unit_cost', 'inventory_value', 'valuation_status']);
        });
    }
};
