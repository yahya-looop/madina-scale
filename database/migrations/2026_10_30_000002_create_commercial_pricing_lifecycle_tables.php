<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->json('pricing_snapshot')->nullable()->after('line_total');
        });

        Schema::create('pricing_promotion_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pricing_promotion_id')->constrained('pricing_promotions')->restrictOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('promotion_code', 60)->nullable();
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('applied')->index();
            $table->timestampTz('redeemed_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();
            $table->unique(['pricing_promotion_id', 'sales_order_id'], 'promotion_redemption_order_unique');
            $table->index(['pricing_promotion_id', 'customer_id', 'status'], 'promotion_redemption_customer_idx');
        });

        Schema::create('sales_order_flash_sale_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flash_sale_item_id')->constrained('flash_sale_items')->restrictOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained('sales_order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('regular_unit_price', 14, 2);
            $table->decimal('sale_unit_price', 14, 2);
            $table->string('status', 20)->default('reserved')->index();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();
            $table->unique('sales_order_item_id', 'flash_sale_allocation_order_item_unique');
            $table->index(['flash_sale_item_id', 'status'], 'flash_sale_allocation_item_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_flash_sale_allocations');
        Schema::dropIfExists('pricing_promotion_redemptions');
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->dropColumn('pricing_snapshot');
        });
    }
};
