<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfer_notification_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_transfer_id')->constrained('inventory_transfers')->restrictOnDelete();
            $table->string('event', 40);
            $table->json('recipient_ids');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'available_at', 'updated_at'], 'inventory_transfer_outbox_dispatch_idx');
            $table->index(['inventory_transfer_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_notification_outbox');
    }
};
