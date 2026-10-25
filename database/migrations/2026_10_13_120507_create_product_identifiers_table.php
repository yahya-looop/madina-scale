<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_identifiers', function (Blueprint $table) {
            $table->id();

            // Exactly one owner must be set: product OR variant.
            $table->foreignId('owner_product_id')
                ->nullable()
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('owner_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->cascadeOnDelete();

            // Supported identifier types: sku, barcode.
            $table->string('identifier_type', 20);

            // Preserve the display value and store its normalized form.
            $table->string('identifier_value', 100);
            $table->string('normalized_value', 100);

            // Inactive identifiers remain available for history.
            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['owner_product_id', 'identifier_type'],
                'product_identifiers_product_owner_index'
            );

            $table->index(
                ['owner_variant_id', 'identifier_type'],
                'product_identifiers_variant_owner_index'
            );
        });

        // Exactly one owner must be present.
        DB::statement('
            ALTER TABLE product_identifiers
            ADD CONSTRAINT product_identifiers_exactly_one_owner_check
            CHECK (
                (owner_product_id IS NOT NULL AND owner_variant_id IS NULL)
                OR
                (owner_product_id IS NULL AND owner_variant_id IS NOT NULL)
            )
        ');

        DB::statement("
            ALTER TABLE product_identifiers
            ADD CONSTRAINT product_identifiers_type_check
            CHECK (identifier_type IN ('sku', 'barcode'))
        ");

        DB::statement("
            ALTER TABLE product_identifiers
            ADD CONSTRAINT product_identifiers_value_check
            CHECK (
                BTRIM(identifier_value) <> ''
                AND normalized_value = LOWER(BTRIM(identifier_value))
                AND normalized_value <> ''
            )
        ");

        // One active owner can have at most one identifier of each type.
        DB::statement('
            CREATE UNIQUE INDEX product_identifiers_product_type_unique
            ON product_identifiers (owner_product_id, identifier_type)
            WHERE owner_product_id IS NOT NULL AND deleted_at IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX product_identifiers_variant_type_unique
            ON product_identifiers (owner_variant_id, identifier_type)
            WHERE owner_variant_id IS NOT NULL AND deleted_at IS NULL
        ');

        // Global uniqueness across products and variants, case-insensitively.
        DB::statement('
            CREATE UNIQUE INDEX product_identifiers_value_unique_active
            ON product_identifiers (identifier_type, normalized_value)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_identifiers');
    }
};
