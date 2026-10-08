<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attribute_id')
                ->constrained('attributes')
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->string('slug', 180);

            $table->string('value', 255);
            $table->string('color_code', 20)->nullable();

            $table->string('status', 30)->default('active');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['attribute_id', 'status']);
            $table->index('sort_order');
            $table->index('created_at');
        });

        DB::statement(
            'CREATE UNIQUE INDEX attribute_values_attribute_id_slug_unique_active
             ON attribute_values (attribute_id, slug)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS attribute_values_attribute_id_slug_unique_active'
        );

        Schema::dropIfExists('attribute_values');
    }
};
