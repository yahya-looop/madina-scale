<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->decimal('low_stock_threshold', 18, 4)
                ->default(0)
                ->after('reserved_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->dropColumn('low_stock_threshold');
        });
    }
};

