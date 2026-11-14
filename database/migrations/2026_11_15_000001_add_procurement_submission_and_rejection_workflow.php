<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table): void {
            $table->foreignId('submitted_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('submitted_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('revision_no')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['submitted_at', 'rejected_at', 'rejection_reason', 'revision_no']);
        });
        Schema::table('purchase_requisitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['submitted_at', 'rejected_at', 'rejection_reason']);
        });
    }
};
