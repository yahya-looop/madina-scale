<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfer_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_transfer_id')->constrained('inventory_transfers')->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->char('payload_hash', 64);
            $table->decimal('quantity_received', 18, 4);
            $table->text('recipient_note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('resulting_quantity_received', 18, 4);
            $table->string('resulting_status', 30);
            $table->timestamps();
            $table->index(['inventory_transfer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_receipts');
    }
};
