<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('account_code', 32)->unique();
            $table->string('company_name', 180);
            $table->string('legal_name', 180)->nullable();
            $table->string('business_type', 40)->default('retailer');
            $table->string('tier', 30)->default('standard');
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('customer_id')->nullable()->unique()->constrained('customers')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('contact_name', 160)->nullable();
            $table->string('email', 190)->nullable()->index();
            $table->string('phone', 40)->nullable()->index();
            $table->string('tax_id', 100)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('payment_terms', 30)->default('prepaid');
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->unsignedSmallInteger('default_discount_percent')->default(0);
            $table->string('billing_address', 500)->nullable();
            $table->string('billing_area', 120)->nullable();
            $table->string('billing_district', 120)->nullable();
            $table->string('billing_division', 120)->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('shipping_address', 500)->nullable();
            $table->string('shipping_area', 120)->nullable();
            $table->string('shipping_district', 120)->nullable();
            $table->string('shipping_division', 120)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['status', 'tier']);
        });

        Schema::create('b2b_quotes', function (Blueprint $table): void {
            $table->id();
            $table->string('quote_number', 40)->unique();
            $table->foreignId('b2b_account_id')->constrained('b2b_accounts')->restrictOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->string('currency', 3)->default('BDT');
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['b2b_account_id', 'created_at']);
        });

        Schema::create('b2b_quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_quote_id')->constrained('b2b_quotes')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('sku', 100)->nullable();
            $table->string('description', 240);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_quote_items');
        Schema::dropIfExists('b2b_quotes');
        Schema::dropIfExists('b2b_accounts');
    }
};
