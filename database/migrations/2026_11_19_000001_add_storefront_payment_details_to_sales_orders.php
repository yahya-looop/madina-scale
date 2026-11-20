<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->string('payment_method', 32)->default('cash_on_delivery')->after('grand_total');
            $table->string('payment_status', 32)->default('unpaid')->after('payment_method');
            $table->json('payment_details')->nullable()->after('payment_status');
            $table->index(['payment_method', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropIndex(['payment_method', 'payment_status']);
            $table->dropColumn(['payment_method', 'payment_status', 'payment_details']);
        });
    }
};
