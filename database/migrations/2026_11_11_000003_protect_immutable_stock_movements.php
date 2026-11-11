<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_stock_movement_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION
                    'Stock movement history is immutable; record a compensating movement instead.'
                    USING ERRCODE = '55000';
            END;
            $$
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER stock_movements_immutable_update
            BEFORE UPDATE ON stock_movements
            FOR EACH ROW
            EXECUTE FUNCTION prevent_stock_movement_mutation()
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER stock_movements_immutable_delete
            BEFORE DELETE ON stock_movements
            FOR EACH ROW
            EXECUTE FUNCTION prevent_stock_movement_mutation()
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_immutable_update ON stock_movements');
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_immutable_delete ON stock_movements');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_stock_movement_mutation()');
    }
};
