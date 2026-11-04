<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_return_items', function (Blueprint $table): void {
            $table->string('disposition', 20)->default('restock')->after('line_refund');
            $table->string('disposition_note', 500)->nullable()->after('disposition');
            $table->index(['disposition', 'created_at'], 'sales_return_items_disposition_created_index');
        });

        DB::statement("ALTER TABLE sales_return_items ADD CONSTRAINT sales_return_items_disposition_check CHECK (disposition IN ('restock', 'damaged', 'quarantine', 'rejected'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sales_return_items DROP CONSTRAINT IF EXISTS sales_return_items_disposition_check');

        Schema::table('sales_return_items', function (Blueprint $table): void {
            $table->dropIndex('sales_return_items_disposition_created_index');
            $table->dropColumn(['disposition', 'disposition_note']);
        });
    }
};
