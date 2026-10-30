<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_promotions', function (Blueprint $table): void {
            $table->unsignedInteger('priority')->default(100)->after('usage_count');
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->json('pricing_snapshot')->nullable()->after('grand_total');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropColumn('pricing_snapshot');
        });

        Schema::table('pricing_promotions', function (Blueprint $table): void {
            $table->dropColumn('priority');
        });
    }
};
