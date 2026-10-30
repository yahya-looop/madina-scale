<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_identity_locks', function (Blueprint $table): void {
            $table->string('identity_key', 80)->primary();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_identity_locks');
    }
};
