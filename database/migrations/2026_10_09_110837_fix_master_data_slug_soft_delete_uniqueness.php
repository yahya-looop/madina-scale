
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'This migration requires PostgreSQL.'
            );
        }

        $tables = [
            'product_categories',
            'brands',
            'units',
        ];

        foreach ($tables as $table) {
            DB::statement(
                "ALTER TABLE {$table}
                 DROP CONSTRAINT IF EXISTS {$table}_slug_unique"
            );

            DB::statement(
                "CREATE UNIQUE INDEX {$table}_slug_active_unique
                 ON {$table} (slug)
                 WHERE deleted_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        $tables = [
            'product_categories',
            'brands',
            'units',
        ];

        foreach ($tables as $table) {
            DB::statement(
                "DROP INDEX IF EXISTS {$table}_slug_active_unique"
            );

            DB::statement(
                "ALTER TABLE {$table}
                 ADD CONSTRAINT {$table}_slug_unique UNIQUE (slug)"
            );
        }
    }
};
