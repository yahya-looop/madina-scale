<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->char('integrity_hash', 64)->nullable()->after('metadata');
            $table->index(['created_at', 'module', 'action'], 'activity_logs_audit_filter_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropIndex('activity_logs_audit_filter_index');
            $table->dropColumn('integrity_hash');
        });
    }
};
