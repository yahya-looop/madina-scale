<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('location_id')->constrained('customers')->nullOnDelete();
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropIndex(['customer_id', 'created_at']);
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
