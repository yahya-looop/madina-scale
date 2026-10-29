<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('code', 60)->nullable()->unique();
            $table->string('description', 1000)->nullable();
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 12, 2);
            $table->string('scope', 24)->default('order');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->decimal('minimum_order_amount', 14, 2)->default(0);
            $table->decimal('minimum_quantity', 12, 3)->default(1);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('stackable')->default(false);
            $table->boolean('publicly_visible')->default(true);
            $table->string('status', 20)->default('draft')->index();
            $table->timestampTz('starts_at')->nullable()->index();
            $table->timestampTz('ends_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('flash_sales', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->string('tagline', 240)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('banner_image')->nullable();
            $table->string('accent_color', 7)->default('#059669');
            $table->timestampTz('starts_at')->index();
            $table->timestampTz('ends_at')->index();
            $table->boolean('show_on_storefront')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['status', 'show_on_storefront', 'starts_at', 'ends_at']);
        });

        Schema::create('flash_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('regular_price', 14, 2);
            $table->decimal('sale_price', 14, 2);
            $table->unsignedInteger('quantity_limit')->nullable();
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['flash_sale_id', 'product_variant_id']);
            $table->index(['flash_sale_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('flash_sales');
        Schema::dropIfExists('pricing_promotions');
    }
};
