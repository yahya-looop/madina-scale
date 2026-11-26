<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->unsignedInteger('product_weight_grams')->default(0)->after('parcel_weight_grams');
            $table->unsignedInteger('packaging_weight_grams')->default(0)->after('product_weight_grams');
            $table->json('parcel_weight_snapshot')->nullable()->after('delivery_quote_snapshot');
        });
        Schema::table('sales_order_items', function (Blueprint $table): void {
            $table->unsignedInteger('shipping_weight_grams')->nullable()->after('sku');
        });
        Schema::create('b2b_rfqs', function (Blueprint $table): void {
            $table->id();
            $table->string('rfq_number', 40)->unique();
            $table->foreignId('b2b_account_id')->nullable()->constrained('b2b_accounts')->nullOnDelete();
            $table->string('company_name', 180);
            $table->string('contact_name', 160);
            $table->string('email', 190)->nullable();
            $table->string('phone', 40);
            $table->string('status', 24)->default('submitted')->index();
            $table->date('required_by')->nullable();
            $table->string('delivery_location', 500)->nullable();
            $table->text('requirements')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
        });
        Schema::create('b2b_rfq_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_rfq_id')->constrained('b2b_rfqs')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('description', 240);
            $table->string('sku', 100)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 40)->nullable();
            $table->text('specifications')->nullable();
            $table->timestampsTz();
        });
        Schema::table('b2b_quotes', function (Blueprint $table): void {
            $table->foreignId('b2b_rfq_id')->nullable()->after('b2b_account_id')->constrained('b2b_rfqs')->nullOnDelete();
        });
        DB::table('settings')->updateOrInsert(['key' => 'shipping.packaging_weight_grams'], [
            'group' => 'shipping', 'value' => '0', 'type' => 'integer',
            'description' => 'Fixed packaging weight added once to each storefront parcel (grams).',
            'is_active' => true, 'sort_order' => 50, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('b2b_quotes', fn (Blueprint $table) => $table->dropConstrainedForeignId('b2b_rfq_id'));
        Schema::dropIfExists('b2b_rfq_items');
        Schema::dropIfExists('b2b_rfqs');
        Schema::table('sales_order_items', fn (Blueprint $table) => $table->dropColumn('shipping_weight_grams'));
        Schema::table('sales_orders', fn (Blueprint $table) => $table->dropColumn(['product_weight_grams', 'packaging_weight_grams', 'parcel_weight_snapshot']));
        DB::table('settings')->where('key', 'shipping.packaging_weight_grams')->delete();
    }
};
