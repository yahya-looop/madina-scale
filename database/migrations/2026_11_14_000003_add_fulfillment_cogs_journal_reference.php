<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_fulfillments', function (Blueprint $table): void {
            $table->decimal('cogs_amount', 16, 2)->default('0.00')->after('status');
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->unique()
                ->after('idempotency_key')
                ->constrained('journal_entries')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_fulfillments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('journal_entry_id');
            $table->dropColumn('cogs_amount');
        });
    }
};
