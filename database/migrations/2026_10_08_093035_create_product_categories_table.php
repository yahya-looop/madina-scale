<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();

            /*
             * ---------------------------------------------------------
             * Category Hierarchy
             * ---------------------------------------------------------
             *
             * NULL parent_id = root category.
             *
             * Example:
             *
             * Electronics
             * ├── Mobile
             * ├── Laptop
             * └── Accessories
             *
             */
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('product_categories')
                ->restrictOnDelete();

            /*
             * ---------------------------------------------------------
             * Category Identity
             * ---------------------------------------------------------
             */
            $table->string('name', 150);

            $table->string('slug', 180)
                ->unique();

            $table->text('description')
                ->nullable();

            /*
             * ---------------------------------------------------------
             * Category Media
             * ---------------------------------------------------------
             *
             * Store the file path only.
             * Actual media storage will be handled separately.
             *
             */
            $table->string('image_path', 500)
                ->nullable();

            /*
             * ---------------------------------------------------------
             * Lifecycle
             * ---------------------------------------------------------
             *
             * active
             * inactive
             * archived
             */
            $table->string('status', 30)
                ->default('active');

            /*
             * ---------------------------------------------------------
             * Visibility
             * ---------------------------------------------------------
             *
             * visible
             * hidden
             */
            $table->string('visibility', 30)
                ->default('visible');

            /*
             * ---------------------------------------------------------
             * Ordering
             * ---------------------------------------------------------
             */
            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            /*
             * Categories should be recoverable rather than
             * permanently destroyed during normal operations.
             */
            $table->softDeletes();

            /*
             * ---------------------------------------------------------
             * Query Indexes
             * ---------------------------------------------------------
             */
            $table->index([
                'parent_id',
                'status',
            ]);

            $table->index([
                'status',
                'visibility',
            ]);

            $table->index('sort_order');

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
