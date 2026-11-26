<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_quotes', function (Blueprint $table): void {
            $table->foreignId('revision_of_id')->nullable()->after('b2b_rfq_id')->constrained('b2b_quotes')->nullOnDelete();
            $table->unsignedSmallInteger('revision_number')->default(1)->after('revision_of_id');
            $table->index(['revision_of_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::table('b2b_quotes', function (Blueprint $table): void {
            $table->dropIndex(['revision_of_id', 'revision_number']);
            $table->dropConstrainedForeignId('revision_of_id');
            $table->dropColumn('revision_number');
        });
    }
};
