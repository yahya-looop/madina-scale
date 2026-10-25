<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->string('name', 200)->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 100)->nullable();

            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('wholesale_price', 14, 2)->nullable();
            $table->decimal('promotional_price', 14, 2)->nullable();

            $table->timestamp('promotion_starts_at')->nullable();
            $table->timestamp('promotion_ends_at')->nullable();

            $table->boolean('is_default')->default(false);
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['product_id', 'status'],
                'product_variants_product_status_index'
            );

            $table->index(
                ['product_id', 'is_default'],
                'product_variants_product_default_index'
            );

            $table->index('sort_order');
        });

        // SKU must be unique among active variants, ignoring letter case.
        DB::statement('
            CREATE UNIQUE INDEX product_variants_sku_unique_active
            ON product_variants (LOWER(sku))
            WHERE deleted_at IS NULL AND sku IS NOT NULL
        ');

        // Barcode must be unique among active variants, ignoring letter case.
        DB::statement('
            CREATE UNIQUE INDEX product_variants_barcode_unique_active
            ON product_variants (LOWER(barcode))
            WHERE deleted_at IS NULL AND barcode IS NOT NULL
        ');

        // A product can have at most one default variant.
        DB::statement('
            CREATE UNIQUE INDEX product_variants_one_default_active
            ON product_variants (product_id)
            WHERE deleted_at IS NULL AND is_default = TRUE
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
