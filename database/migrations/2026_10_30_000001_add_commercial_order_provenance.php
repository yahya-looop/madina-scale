<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->foreignId('b2b_account_id')->nullable()->after('customer_id')->constrained('b2b_accounts')->nullOnDelete();
            $table->foreignId('b2b_quote_id')->nullable()->after('b2b_account_id')->constrained('b2b_quotes')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->after('b2b_quote_id')->constrained('leads')->nullOnDelete();
            $table->string('source_type', 32)->nullable()->after('lead_id')->index();
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type')->index();
            $table->foreignId('promotion_id')->nullable()->after('source_id')->constrained('pricing_promotions')->nullOnDelete();
            $table->string('promotion_code', 60)->nullable()->after('promotion_id');
            $table->unique('b2b_quote_id');
        });

        Schema::table('b2b_quotes', function (Blueprint $table): void {
            $table->foreignId('converted_order_id')->nullable()->after('created_by')->unique()->constrained('sales_orders')->nullOnDelete();
            $table->timestampTz('accepted_at')->nullable()->after('converted_order_id');
            $table->timestampTz('converted_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('b2b_quotes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('converted_order_id');
            $table->dropColumn(['accepted_at', 'converted_at']);
        });
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropUnique(['b2b_quote_id']);
            $table->dropConstrainedForeignId('b2b_account_id');
            $table->dropConstrainedForeignId('b2b_quote_id');
            $table->dropConstrainedForeignId('lead_id');
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['source_type', 'source_id', 'promotion_code']);
        });
    }
};
