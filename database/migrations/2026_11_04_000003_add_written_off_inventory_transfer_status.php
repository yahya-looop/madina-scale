<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE inventory_transfers DROP CONSTRAINT IF EXISTS inventory_transfers_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_transfers
            ADD CONSTRAINT inventory_transfers_status_check
            CHECK (status IN ('in_transit', 'partially_received', 'received', 'rejected', 'cancelled', 'discrepancy', 'written_off'))
        SQL);
    }

    public function down(): void
    {
        DB::statement("UPDATE inventory_transfers SET status = 'rejected' WHERE status = 'written_off'");
        DB::statement('ALTER TABLE inventory_transfers DROP CONSTRAINT IF EXISTS inventory_transfers_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_transfers
            ADD CONSTRAINT inventory_transfers_status_check
            CHECK (status IN ('in_transit', 'partially_received', 'received', 'rejected', 'cancelled', 'discrepancy'))
        SQL);
    }
};
