<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->foreignId('location_id')
                ->constrained('locations')
                ->restrictOnDelete();

            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0);

            $table->timestamps();

            $table->index(['location_id', 'product_id']);
            $table->index(['location_id', 'product_variant_id']);
        });

        DB::statement("
            ALTER TABLE inventory_stocks
            ADD CONSTRAINT inventory_stocks_owner_check
            CHECK (
                (product_id IS NOT NULL AND product_variant_id IS NULL)
                OR
                (product_id IS NULL AND product_variant_id IS NOT NULL)
            )
        ");

        DB::statement("
            ALTER TABLE inventory_stocks
            ADD CONSTRAINT inventory_stocks_quantity_check
            CHECK (
                quantity >= 0
                AND reserved_quantity >= 0
                AND reserved_quantity <= quantity
            )
        ");

        DB::statement("
            CREATE UNIQUE INDEX inventory_stocks_product_location_unique
            ON inventory_stocks (product_id, location_id)
            WHERE product_id IS NOT NULL
        ");

        DB::statement("
            CREATE UNIQUE INDEX inventory_stocks_variant_location_unique
            ON inventory_stocks (product_variant_id, location_id)
            WHERE product_variant_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};
