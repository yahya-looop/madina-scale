<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->decimal('fulfilled_cost_quantity', 18, 4)->default('0.0000');
            $table->decimal('fulfilled_cost_value', 18, 2)->default('0.00');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->dropColumn(['fulfilled_cost_quantity', 'fulfilled_cost_value']);
        });
    }
};
