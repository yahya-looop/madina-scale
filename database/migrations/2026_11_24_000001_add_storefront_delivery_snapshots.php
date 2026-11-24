<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('shipping_weight_grams')->nullable()->after('product_type');
        });
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedInteger('shipping_weight_override_grams')->nullable()->after('selling_price');
        });
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->decimal('delivery_charge', 14, 2)->default(0)->after('tax_total');
            $table->unsignedInteger('parcel_weight_grams')->default(0)->after('delivery_charge');
            $table->string('delivery_zone', 40)->nullable();
            $table->string('delivery_selection_mode', 30)->nullable();
            $table->string('customer_preferred_courier_code', 40)->nullable();
            $table->string('system_recommended_courier_code', 40)->nullable();
            $table->string('assigned_courier_code', 40)->nullable();
            $table->jsonb('delivery_quote_snapshot')->nullable();
            $table->index(['assigned_courier_code', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropIndex(['assigned_courier_code', 'status']);
            $table->dropColumn(['delivery_charge', 'parcel_weight_grams', 'delivery_zone', 'delivery_selection_mode', 'customer_preferred_courier_code', 'system_recommended_courier_code', 'assigned_courier_code', 'delivery_quote_snapshot']);
        });
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('shipping_weight_override_grams'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('shipping_weight_grams'));
    }
};
