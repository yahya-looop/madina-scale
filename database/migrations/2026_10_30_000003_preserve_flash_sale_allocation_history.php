<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_flash_sale_allocations', function (Blueprint $table): void {
            $table->json('order_item_snapshot')->nullable()->after('sales_order_item_id');
        });

        Schema::table('sales_order_flash_sale_allocations', function (Blueprint $table): void {
            $table->dropForeign(['sales_order_item_id']);
            $table->unsignedBigInteger('sales_order_item_id')->nullable()->change();
            $table->foreign('sales_order_item_id')
                ->references('id')->on('sales_order_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('sales_order_flash_sale_allocations')->whereNull('sales_order_item_id')->delete();
        Schema::table('sales_order_flash_sale_allocations', function (Blueprint $table): void {
            $table->dropForeign(['sales_order_item_id']);
            $table->unsignedBigInteger('sales_order_item_id')->nullable(false)->change();
            $table->foreign('sales_order_item_id')
                ->references('id')->on('sales_order_items')->cascadeOnDelete();
            $table->dropColumn('order_item_snapshot');
        });
    }
};
