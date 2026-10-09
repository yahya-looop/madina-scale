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

        DB::statement(
            'ALTER TABLE attributes
             DROP CONSTRAINT IF EXISTS attributes_slug_unique'
        );

        DB::statement(
            'CREATE UNIQUE INDEX attributes_slug_active_unique
             ON attributes (slug)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS attributes_slug_active_unique'
        );

        DB::statement(
            'ALTER TABLE attributes
             ADD CONSTRAINT attributes_slug_unique UNIQUE (slug)'
        );
    }
};
