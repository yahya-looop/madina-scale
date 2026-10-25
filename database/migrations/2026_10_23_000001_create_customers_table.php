<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_code', 32)->unique();
            $table->string('name', 160);
            $table->string('phone', 40)->nullable()->index();
            $table->string('email', 190)->nullable()->index();
            $table->string('company_name', 160)->nullable();
            $table->string('address_line', 500)->nullable();
            $table->string('area', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('division', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestampsTz();
            $table->index(['name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
