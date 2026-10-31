<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id(); $table->string('name',180); $table->string('channel',40)->default('onsite');
            $table->string('objective',80)->nullable(); $table->text('description')->nullable();
            $table->string('status',24)->default('draft')->index(); $table->decimal('budget',14,2)->default(0);
            $table->timestamp('starts_at')->nullable(); $table->timestamp('ends_at')->nullable();
            $table->json('audience_rules')->nullable(); $table->json('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); $table->timestamps();
            $table->index(['starts_at','ends_at']);
        });
        Schema::create('marketing_campaign_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->string('event_type',32)->index(); $table->string('source',40)->nullable();
            $table->string('visitor_key',100)->nullable()->index(); $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index(); $table->decimal('value',14,2)->default(0);
            $table->json('metadata')->nullable(); $table->timestamp('occurred_at')->useCurrent()->index(); $table->timestamps();
        });
        Schema::create('marketing_coupons', function (Blueprint $table) {
            $table->id(); $table->string('code',64)->unique(); $table->string('name',180);
            $table->string('discount_type',16); $table->decimal('discount_value',14,2);
            $table->decimal('minimum_order',14,2)->default(0); $table->decimal('maximum_discount',14,2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable(); $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0); $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable(); $table->timestamp('ends_at')->nullable();
            $table->json('conditions')->nullable(); $table->unsignedBigInteger('created_by')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('marketing_coupons'); Schema::dropIfExists('marketing_campaign_events'); Schema::dropIfExists('marketing_campaigns'); }
};
