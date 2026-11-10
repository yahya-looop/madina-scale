<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controlled_accounting_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->string('workflow_type', 32); // opening_balance, company_adjustment
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('status', 16)->default('submitted'); // submitted, approved, rejected, posted
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_note')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampsTz();
            $table->index(['workflow_type', 'status']);
            $table->index(['location_id', 'workflow_type', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controlled_accounting_entries');
    }
};
