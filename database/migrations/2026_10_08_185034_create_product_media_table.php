<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_media', function (Blueprint $table) {
            $table->id();

            /*
             * Product ownership.
             *
             * Deleting a product permanently removes its
             * media records as well.
             */
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            /*
             * Storage configuration.
             *
             * Example:
             * public
             * s3
             * r2
             */
            $table->string('disk', 50)
                ->default('public');

            /*
             * Relative storage path.
             *
             * Example:
             * products/1/images/product-main.webp
             */
            $table->string('path', 500);

            /*
             * Media collection.
             *
             * Keeping this flexible allows future collections
             * such as images, documents or manuals.
             */
            $table->string('collection', 50)
                ->default('images');

            /*
             * Original uploaded filename.
             */
            $table->string('original_name', 255)
                ->nullable();

            /*
             * File metadata.
             */
            $table->string('mime_type', 100)
                ->nullable();

            $table->unsignedBigInteger('size')
                ->nullable();

            /*
             * Image dimensions.
             *
             * Nullable because the media architecture may
             * support non-image files in the future.
             */
            $table->unsignedInteger('width')
                ->nullable();

            $table->unsignedInteger('height')
                ->nullable();

            /*
             * Storefront / accessibility text.
             */
            $table->string('alt_text', 255)
                ->nullable();

            /*
             * Manual gallery ordering.
             */
            $table->unsignedInteger('sort_order')
                ->default(0);

            /*
             * One primary media item per product/collection.
             */
            $table->boolean('is_primary')
                ->default(false);

            $table->timestamps();
            $table->softDeletes();

            /*
             * Common product media queries.
             */
            $table->index([
                'product_id',
                'collection',
            ]);

            $table->index([
                'product_id',
                'sort_order',
            ]);

            $table->index([
                'product_id',
                'is_primary',
            ]);

            $table->index('created_at');
        });

        /*
         * PostgreSQL partial unique index:
         *
         * A product can have only one active primary
         * media item inside a collection.
         */
        DB::statement(
            'CREATE UNIQUE INDEX product_media_primary_unique_active
             ON product_media (product_id, collection)
             WHERE is_primary = TRUE AND deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS product_media_primary_unique_active'
        );

        Schema::dropIfExists('product_media');
    }
};
