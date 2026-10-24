<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_shipment_id')->constrained('sales_order_shipments')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->char('event_hash', 64);
            $table->string('event_id', 160)->nullable();
            $table->string('previous_status', 40)->nullable();
            $table->string('received_status', 40);
            $table->string('outcome', 32);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('received_at')->useCurrent();
            $table->timestampsTz();
            $table->unique(['provider', 'event_hash']);
            $table->index(['sales_order_shipment_id', 'received_at']);
            $table->index(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_shipment_events');
    }
};
