<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('lead_code', 32)->unique();
            $table->string('name', 160);
            $table->string('phone', 40)->index();
            $table->string('alternate_phone', 40)->nullable();
            $table->string('email', 190)->nullable()->index();
            $table->string('address_line', 500)->nullable();
            $table->string('area', 120)->nullable()->index();
            $table->string('district', 120)->nullable()->index();
            $table->string('division', 120)->nullable();
            $table->string('source', 40)->default('manual')->index();
            $table->string('source_detail', 190)->nullable();
            $table->string('customer_type', 20)->default('undecided')->index();
            $table->string('interested_product', 190)->nullable();
            $table->string('status', 24)->default('new')->index();
            $table->string('last_contact_outcome', 32)->nullable();
            $table->string('interest_level', 12)->default('warm');
            $table->text('notes')->nullable();
            $table->dateTimeTz('next_follow_up_at')->nullable()->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('converted_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['status', 'next_follow_up_at']);
            $table->index(['customer_type', 'status']);
        });

        Schema::create('lead_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('activity_type', 20)->default('call');
            $table->string('outcome', 32)->nullable();
            $table->text('summary')->nullable();
            $table->dateTimeTz('occurred_at')->index();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->dateTimeTz('next_follow_up_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['lead_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
    }
};
