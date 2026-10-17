<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('purpose', 24);
            $table->string('status', 16)->default('active');
            $table->string('reference_type', 80)->nullable();
            $table->string('reference_id', 120)->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'expires_at']);
            $table->index(['product_id', 'location_id', 'status']);
            $table->index(['product_variant_id', 'location_id', 'status']);
        });

        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT inventory_reservations_owner_check CHECK ((product_id IS NOT NULL AND product_variant_id IS NULL) OR (product_id IS NULL AND product_variant_id IS NOT NULL))");
        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT inventory_reservations_quantity_check CHECK (quantity > 0)");
        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT inventory_reservations_purpose_check CHECK (purpose IN ('customer_order', 'internal_use'))");
        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT inventory_reservations_status_check CHECK (status IN ('active', 'released', 'cancelled', 'fulfilled', 'expired'))");
    }

    public function down(): void { Schema::dropIfExists('inventory_reservations'); }
};
