<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('adjustment_number', 40)->unique();
            $table->string('reason', 1000);
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->decimal('previous_total', 14, 2);
            $table->decimal('new_total', 14, 2);
            $table->decimal('financial_delta', 14, 2);
            $table->string('settlement_status', 32)->default('not_required');
            $table->string('courier_sync_status', 32)->default('not_required');
            $table->foreignId('financial_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['sales_order_id', 'created_at']);
            $table->index(['settlement_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_adjustments');
    }
};
