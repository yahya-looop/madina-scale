<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();

            $table->uuid('transfer_group_id')->nullable();

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

            $table->string('movement_type', 30);
            $table->decimal('quantity_delta', 18, 4);
            $table->decimal('balance_before', 18, 4);
            $table->decimal('balance_after', 18, 4);

            $table->string('reason', 255)->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->uuid('idempotency_key')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'created_at']);
            $table->index(['product_id', 'created_at']);
            $table->index(['product_variant_id', 'created_at']);
            $table->index('transfer_group_id');
        });

        DB::statement("
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_owner_check
            CHECK (
                (product_id IS NOT NULL AND product_variant_id IS NULL)
                OR
                (product_id IS NULL AND product_variant_id IS NOT NULL)
            )
        ");

        DB::statement("
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_type_check
            CHECK (
                movement_type IN (
                    'opening',
                    'receipt',
                    'issue',
                    'adjustment',
                    'transfer_in',
                    'transfer_out',
                    'reversal'
                )
            )
        ");

        DB::statement("
            ALTER TABLE stock_movements
            ADD CONSTRAINT stock_movements_quantity_check
            CHECK (
                quantity_delta <> 0
                AND balance_before >= 0
                AND balance_after >= 0
                AND balance_after = balance_before + quantity_delta
            )
        ");

        DB::statement("
            CREATE UNIQUE INDEX stock_movements_idempotency_unique
            ON stock_movements (idempotency_key)
            WHERE idempotency_key IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
