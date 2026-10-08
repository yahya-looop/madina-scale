<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('product_categories')
                ->restrictOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->restrictOnDelete();

            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            $table->string('name', 200);
            $table->string('slug', 220);

            $table->string('sku', 100)->nullable();
            $table->string('model_number', 120)->nullable();
            $table->string('barcode', 100)->nullable();

            $table->string('product_type', 30)->default('simple');

            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            $table->string('status', 30)->default('draft');
            $table->string('visibility', 30)->default('visible');

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status']);
            $table->index(['brand_id', 'status']);
            $table->index('unit_id');
            $table->index('product_type');
            $table->index(['status', 'visibility']);
            $table->index('sort_order');
            $table->index('created_at');
        });

        /*
         * PostgreSQL partial unique indexes.
         *
         * These keep slug / SKU / barcode unique only among
         * non-deleted products.
         */

        DB::statement(
            'CREATE UNIQUE INDEX products_slug_unique_active
             ON products (slug)
             WHERE deleted_at IS NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX products_sku_unique_active
             ON products (LOWER(sku))
             WHERE deleted_at IS NULL AND sku IS NOT NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX products_barcode_unique_active
             ON products (LOWER(barcode))
             WHERE deleted_at IS NULL AND barcode IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS products_slug_unique_active'
        );

        DB::statement(
            'DROP INDEX IF EXISTS products_sku_unique_active'
        );

        DB::statement(
            'DROP INDEX IF EXISTS products_barcode_unique_active'
        );

        Schema::dropIfExists('products');
    }
};
