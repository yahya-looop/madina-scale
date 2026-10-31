<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('financial_accounts')->updateOrInsert(
            ['code' => '1200'],
            [
                'name' => 'Inventory Asset',
                'type' => 'asset',
                'normal_balance' => 'debit',
                'is_system' => true,
                'is_cash_bank' => false,
                'is_active' => true,
                'description' => 'Inventory value relieved when stock is issued for service and other non-sale purposes.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('financial_accounts')->updateOrInsert(
            ['code' => '6200'],
            [
                'name' => 'Service Parts Expense',
                'type' => 'expense',
                'normal_balance' => 'debit',
                'is_system' => true,
                'is_cash_bank' => false,
                'is_active' => true,
                'description' => 'Cost of spare parts consumed in warranty and service work.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        Schema::table('service_ticket_parts', function (Blueprint $table): void {
            $table->decimal('unit_cost', 16, 2)->nullable()->after('quantity');
            $table->decimal('total_cost', 16, 2)->nullable()->after('unit_cost');
            $table->foreignId('journal_entry_id')->nullable()->after('stock_movement_id')
                ->constrained('journal_entries')->restrictOnDelete();
            $table->unique('stock_movement_id', 'service_ticket_parts_stock_movement_unique');
        });
    }

    public function down(): void
    {
        Schema::table('service_ticket_parts', function (Blueprint $table): void {
            $table->dropUnique('service_ticket_parts_stock_movement_unique');
            $table->dropConstrainedForeignId('journal_entry_id');
            $table->dropColumn(['unit_cost', 'total_cost']);
        });

        // System accounts are intentionally retained: journals may reference them.
    }
};
