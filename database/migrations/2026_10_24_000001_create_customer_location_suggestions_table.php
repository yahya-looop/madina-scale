<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_location_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('name', 120);
            $table->string('normalized_name', 120);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['type', 'normalized_name']);
            $table->index(['type', 'name']);
        });
    }
    public function down(): void { Schema::dropIfExists('customer_location_suggestions'); }
};
