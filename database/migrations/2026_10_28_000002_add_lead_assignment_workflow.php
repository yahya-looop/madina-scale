<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->after('assigned_to')->constrained()->nullOnDelete();
            $table->index(['location_id', 'assigned_to']);
        });

        Schema::create('lead_assignment_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestampsTz();
            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_assignment_history');
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['location_id', 'assigned_to']);
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
