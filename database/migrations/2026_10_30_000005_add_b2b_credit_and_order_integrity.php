<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->json('credit_snapshot')->nullable()->after('pricing_snapshot');
            $table->timestampTz('payment_due_at')->nullable()->after('credit_snapshot');
            $table->index(['b2b_account_id', 'status', 'created_at'], 'sales_orders_b2b_credit_exposure_idx');
            $table->unique(['source_type', 'source_id'], 'sales_orders_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropIndex('sales_orders_b2b_credit_exposure_idx');
            $table->dropUnique('sales_orders_source_unique');
            $table->dropColumn(['credit_snapshot', 'payment_due_at']);
        });
    }
};
