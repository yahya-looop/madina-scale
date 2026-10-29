<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('owner_id')->nullable()->after('assigned_to')->constrained('users');
        });

        // Existing creator is the immutable owner; preserve the current assignee separately.
        DB::table('leads')->whereNull('owner_id')->update(['owner_id' => DB::raw('COALESCE(created_by, assigned_to)')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
