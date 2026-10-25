<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('idempotency_key', 100)->nullable();
            $table->string('provider_shipment_id', 120)->nullable();
            $table->string('tracking_code', 160)->nullable();
            $table->string('status', 40)->default('queued');
            $table->string('recipient_name', 160);
            $table->string('recipient_phone', 40);
            $table->string('recipient_address', 500);
            $table->decimal('cod_amount', 14, 2)->default(0);
            $table->jsonb('provider_response')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('booked_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['sales_order_id', 'status']);
            $table->index(['provider', 'tracking_code']);
            $table->unique(['provider', 'provider_shipment_id']);
            $table->unique(['provider', 'idempotency_key']);
        });

        Schema::create('sales_order_risk_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('score')->default(0);
            $table->string('level', 20)->default('low');
            $table->string('decision', 30)->default('allow');
            $table->jsonb('signals')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->index(['level', 'decision']);
        });

        Schema::create('sales_order_sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->string('recipient_phone', 40);
            $table->string('event', 60);
            $table->text('message');
            $table->string('provider', 40)->default('configured_gateway');
            $table->string('status', 20)->default('queued');
            $table->string('provider_message_id', 160)->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('provider_response')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
            $table->index(['sales_order_id', 'event']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('sales_order_phone_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->string('phone', 40);
            $table->string('code_hash', 255);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
            $table->index(['phone', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_phone_verifications');
        Schema::dropIfExists('sales_order_sms_logs');
        Schema::dropIfExists('sales_order_risk_assessments');
        Schema::dropIfExists('sales_order_shipments');
    }
};
