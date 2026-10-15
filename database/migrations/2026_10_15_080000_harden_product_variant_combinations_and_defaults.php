<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->text('attribute_signature')->nullable();
        });

        // Backfill a stable, order-independent signature for existing variants.
        DB::statement(<<<'SQL'
            UPDATE product_variants AS variant
            SET attribute_signature = selected.signature
            FROM (
                SELECT
                    product_variant_id,
                    string_agg(
                        attribute_id::text || ':' || attribute_value_id::text,
                        '|' ORDER BY attribute_id
                    ) AS signature
                FROM product_variant_attribute_values
                GROUP BY product_variant_id
            ) AS selected
            WHERE variant.id = selected.product_variant_id
        SQL);

        // An inactive variant must never remain the product's default.
        DB::statement(<<<'SQL'
            UPDATE product_variants
            SET is_default = FALSE
            WHERE deleted_at IS NULL
              AND is_default = TRUE
              AND status <> 'active'
        SQL);

        // If active variants exist but no active default exists, select a deterministic default.
        DB::statement(<<<'SQL'
            WITH candidates AS (
                SELECT
                    variant.id,
                    ROW_NUMBER() OVER (
                        PARTITION BY variant.product_id
                        ORDER BY variant.sort_order, variant.id
                    ) AS row_number
                FROM product_variants AS variant
                WHERE variant.deleted_at IS NULL
                  AND variant.status = 'active'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM product_variants AS current_default
                      WHERE current_default.product_id = variant.product_id
                        AND current_default.deleted_at IS NULL
                        AND current_default.status = 'active'
                        AND current_default.is_default = TRUE
                  )
            )
            UPDATE product_variants AS variant
            SET is_default = TRUE
            FROM candidates
            WHERE variant.id = candidates.id
              AND candidates.row_number = 1
        SQL);

        $duplicates = DB::select(<<<'SQL'
            SELECT
                product_id,
                attribute_signature,
                COUNT(*) AS duplicate_count,
                array_agg(id ORDER BY id) AS variant_ids
            FROM product_variants
            WHERE deleted_at IS NULL
              AND attribute_signature IS NOT NULL
            GROUP BY product_id, attribute_signature
            HAVING COUNT(*) > 1
            ORDER BY product_id
            LIMIT 10
        SQL);

        if ($duplicates !== []) {
            $details = collect($duplicates)
                ->map(fn (object $row): string => sprintf(
                    'product_id=%s variant_ids=%s',
                    $row->product_id,
                    $row->variant_ids
                ))
                ->implode('; ');

            throw new RuntimeException(
                'Cannot enforce unique product variant attribute combinations because duplicate combinations already exist. '
                . 'Resolve these variants, then rerun the migration. Examples: '
                . $details
            );
        }

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX product_variants_product_attribute_signature_unique
            ON product_variants (product_id, attribute_signature)
            WHERE deleted_at IS NULL AND attribute_signature IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            DROP INDEX IF EXISTS product_variants_product_attribute_signature_unique
        SQL);

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn('attribute_signature');
        });
    }
};
