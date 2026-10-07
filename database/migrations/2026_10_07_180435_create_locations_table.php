<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();

            // Identity
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->string('type', 30)->default('branch');

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);

            // Address
            $table->string('country_code', 2)->default('BD');
            $table->string('division', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('address')->nullable();

            // Contact
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();

            // Operational configuration
            $table->json('business_hours')->nullable();

            // Future extensibility
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
            $table->index('is_primary');
            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};