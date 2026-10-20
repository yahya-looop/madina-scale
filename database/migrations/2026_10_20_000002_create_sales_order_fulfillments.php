<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_fulfillments', function (Blueprint $table): void {
            $table->id();
            $table->string('fulfillment_number', 40)->unique();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->restrictOnDelete();
            $table->string('status', 24)->default('completed');
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('fulfilled_at')->useCurrent();
            $table->timestampsTz();
            $table->index(['sales_order_id', 'fulfilled_at']);
        });

        Schema::create('sales_order_fulfillment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_fulfillment_id')->constrained('sales_order_fulfillments')->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained('sales_order_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestampsTz();
            $table->unique(['sales_order_fulfillment_id', 'sales_order_item_id'], 'fulfillment_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_fulfillment_items');
        Schema::dropIfExists('sales_order_fulfillments');
    }
};
