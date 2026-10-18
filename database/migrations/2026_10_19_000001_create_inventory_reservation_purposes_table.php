<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reservation_purposes', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 24)->unique();
            $table->string('label', 80);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        DB::table('inventory_reservation_purposes')->insert([
            ['key' => 'customer_order', 'label' => 'Customer order', 'is_active' => true, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'internal_use', 'label' => 'Internal use', 'is_active' => true, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Existing reservations retain their purpose keys; new keys are now table-driven.
        DB::statement('ALTER TABLE inventory_reservations DROP CONSTRAINT IF EXISTS inventory_reservations_purpose_check');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT inventory_reservations_purpose_check CHECK (purpose IN ('customer_order', 'internal_use'))");
        Schema::dropIfExists('inventory_reservation_purposes');
    }
};
