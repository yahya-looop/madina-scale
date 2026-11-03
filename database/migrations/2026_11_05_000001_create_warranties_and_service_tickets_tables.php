<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_warranties', function (Blueprint $table): void {
            $table->id();
            $table->string('warranty_number', 40)->unique();
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('serial_number', 120)->nullable()->unique();
            $table->date('warranty_start');
            $table->date('warranty_end');
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['sales_order_item_id', 'created_at']);
            $table->index(['customer_id', 'warranty_end']);
            $table->index(['location_id', 'status']);
        });

        Schema::create('service_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number', 40)->unique();
            $table->foreignId('warranty_id')->nullable()->constrained('product_warranties')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->text('issue');
            $table->text('diagnosis')->nullable();
            $table->text('resolution')->nullable();
            $table->timestampTz('received_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['location_id', 'status', 'priority']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('service_ticket_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_ticket_id')->constrained('service_tickets')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('quantity', 16, 4);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['service_ticket_id', 'created_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('service_ticket_parts');
        Schema::dropIfExists('service_tickets');
        Schema::dropIfExists('product_warranties');
    }
};
