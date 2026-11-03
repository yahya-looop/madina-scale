<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('return_number', 40)->unique();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('status', 20)->default('completed');
            $table->string('refund_method', 20);
            $table->foreignId('cash_account_id')->nullable()->constrained('financial_accounts')->restrictOnDelete();
            $table->decimal('refund_amount', 18, 2);
            $table->date('return_date');
            $table->string('reason', 500)->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sales_order_id', 'status']);
            $table->index(['location_id', 'return_date']);
        });

        Schema::create('sales_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained('sales_order_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_refund', 18, 2);
            $table->timestamps();
            $table->unique(['sales_return_id', 'sales_order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
