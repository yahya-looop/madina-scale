<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status', 20)->default('submitted');
            $table->text('reason');
            $table->json('proposed_data');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampsTz();
            $table->unique(['purchase_order_id', 'revision_number']);
            $table->index(['purchase_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_revisions');
    }
};
