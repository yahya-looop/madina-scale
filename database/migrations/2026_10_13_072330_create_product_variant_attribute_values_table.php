<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Required by the composite foreign key below.
        DB::statement('
            ALTER TABLE attribute_values
            ADD CONSTRAINT attribute_values_id_attribute_id_unique
            UNIQUE (id, attribute_id)
        ');

        Schema::create(
            'product_variant_attribute_values',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('product_variant_id')
                    ->constrained('product_variants')
                    ->restrictOnDelete();

                $table->foreignId('attribute_id')
                    ->constrained('attributes')
                    ->restrictOnDelete();

                $table->unsignedBigInteger('attribute_value_id');

                // Ensure the selected value belongs to this attribute.
                $table->foreign(
                    ['attribute_value_id', 'attribute_id'],
                    'pvav_value_attribute_fk'
                )
                    ->references(['id', 'attribute_id'])
                    ->on('attribute_values')
                    ->restrictOnDelete();

                $table->timestamps();

                // A variant can have only one value per attribute.
                $table->unique(
                    ['product_variant_id', 'attribute_id'],
                    'pvav_variant_attribute_unique'
                );

                $table->index('attribute_value_id');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'product_variant_attribute_values'
        );

        DB::statement('
            ALTER TABLE attribute_values
            DROP CONSTRAINT IF EXISTS
            attribute_values_id_attribute_id_unique
        ');
    }
};
