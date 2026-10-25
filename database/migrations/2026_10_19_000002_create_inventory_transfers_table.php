<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('transfer_uuid')->unique();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('source_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('quantity_sent', 18, 4);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->string('status', 24)->default('in_transit');
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('recipient_note')->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['destination_location_id', 'status', 'dispatched_at']);
            $table->index(['source_location_id', 'status', 'dispatched_at']);
        });

        DB::statement('ALTER TABLE inventory_transfers ADD CONSTRAINT inventory_transfers_owner_check CHECK ((product_id IS NOT NULL AND product_variant_id IS NULL) OR (product_id IS NULL AND product_variant_id IS NOT NULL))');
        DB::statement('ALTER TABLE inventory_transfers ADD CONSTRAINT inventory_transfers_locations_check CHECK (source_location_id <> destination_location_id)');
        DB::statement('ALTER TABLE inventory_transfers ADD CONSTRAINT inventory_transfers_quantity_check CHECK (quantity_sent > 0 AND quantity_received >= 0 AND quantity_received <= quantity_sent)');
        DB::statement("ALTER TABLE inventory_transfers ADD CONSTRAINT inventory_transfers_status_check CHECK (status IN ('in_transit', 'partially_received', 'received', 'rejected', 'cancelled', 'discrepancy'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
