<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 60)->index();
            $table->string('base_url', 2048);
            $table->string('auth_type', 40)->default('none');
            $table->longText('credentials')->nullable();
            $table->longText('config')->nullable();
            $table->string('environment', 20)->default('sandbox');
            $table->boolean('is_active')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_providers');
    }
};
