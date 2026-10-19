<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table): void {
            $table->string('purpose_label', 80)->nullable()->after('purpose');
        });

        // Restore legacy archived purposes to active choices: this feature no longer archives.
        DB::table('inventory_reservation_purposes')->where('is_active', false)->update(['is_active' => true, 'updated_at' => now()]);

        // Snapshot current labels before purposes can be physically deleted.
        DB::statement('UPDATE inventory_reservations AS reservations SET purpose_label = purposes.label FROM inventory_reservation_purposes AS purposes WHERE reservations.purpose = purposes.key AND reservations.purpose_label IS NULL');
    }

    public function down(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table): void {
            $table->dropColumn('purpose_label');
        });
    }
};
