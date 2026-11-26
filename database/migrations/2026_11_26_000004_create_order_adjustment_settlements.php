<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_adjustment_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_adjustment_id')->constrained('sales_order_adjustments')->restrictOnDelete();
            $table->string('settlement_type', 24); // collection, refund, customer_credit
            $table->decimal('amount', 14, 2);
            $table->foreignId('financial_transaction_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $table->string('reference', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['sales_order_adjustment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_adjustment_settlements');
    }
};
