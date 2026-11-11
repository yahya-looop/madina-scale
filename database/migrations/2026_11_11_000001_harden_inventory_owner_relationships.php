<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Fail closed when the existing database contains an owner mismatch.
         * This migration does not rewrite stock, reservations, transfers or ledger rows.
         */
        // During `migrate --pretend`, SELECT statements are recorded but not executed,
        // so selectOne() returns null. Run the preflight only during a real migration.
        if (! DB::connection()->pretending()) {
            foreach ([
                'inventory_stocks',
                'stock_movements',
                'inventory_reservations',
                'inventory_transfers',
            ] as $table) {
                $invalid = DB::selectOne("
                SELECT COUNT(*) AS aggregate
                FROM {$table} AS owner_row
                LEFT JOIN products AS direct_product
                    ON direct_product.id = owner_row.product_id
                LEFT JOIN product_variants AS variant
                    ON variant.id = owner_row.product_variant_id
                LEFT JOIN products AS variant_product
                    ON variant_product.id = variant.product_id
                WHERE (
                    owner_row.product_id IS NOT NULL
                    AND (direct_product.id IS NULL OR direct_product.product_type <> 'simple')
                ) OR (
                    owner_row.product_variant_id IS NOT NULL
                    AND (variant.id IS NULL OR variant_product.id IS NULL OR variant_product.product_type <> 'variable')
                )
            ");

                if ((int) $invalid->aggregate > 0) {
                    throw new RuntimeException(
                        "Cannot install inventory ownership guards: {$table} contains {$invalid->aggregate} invalid product/variant relationship(s). Reconcile those records before retrying."
                    );
                }
            }
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION assert_inventory_owner_relationship()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_product_type TEXT;
                v_product_deleted_at TIMESTAMPTZ;
                v_variant_deleted_at TIMESTAMPTZ;
                v_parent_id BIGINT;
            BEGIN
                IF NEW.product_id IS NOT NULL THEN
                    SELECT product_type, deleted_at
                    INTO v_product_type, v_product_deleted_at
                    FROM products
                    WHERE id = NEW.product_id
                    FOR UPDATE;

                    IF NOT FOUND
                       OR v_product_type <> 'simple'
                       OR v_product_deleted_at IS NOT NULL
                    THEN
                        RAISE EXCEPTION
                            'Inventory records can reference product_id only for active simple products.'
                            USING ERRCODE = '23514';
                    END IF;
                ELSE
                    SELECT product_id
                    INTO v_parent_id
                    FROM product_variants
                    WHERE id = NEW.product_variant_id;

                    IF NOT FOUND THEN
                        RAISE EXCEPTION
                            'The selected inventory variant does not exist.'
                            USING ERRCODE = '23503';
                    END IF;

                    -- Match the application lock order: parent product, then variant.
                    PERFORM 1
                    FROM products
                    WHERE id = v_parent_id
                    FOR UPDATE;

                    SELECT product_type, deleted_at
                    INTO v_product_type, v_product_deleted_at
                    FROM products
                    WHERE id = v_parent_id;

                    PERFORM 1
                    FROM product_variants
                    WHERE id = NEW.product_variant_id
                      AND product_id = v_parent_id
                    FOR UPDATE;

                    SELECT deleted_at
                    INTO v_variant_deleted_at
                    FROM product_variants
                    WHERE id = NEW.product_variant_id
                      AND product_id = v_parent_id;

                    IF NOT FOUND
                       OR v_product_type <> 'variable'
                       OR v_product_deleted_at IS NOT NULL
                       OR v_variant_deleted_at IS NOT NULL
                    THEN
                        RAISE EXCEPTION
                            'Inventory records can reference only active variants of active variable products.'
                            USING ERRCODE = '23514';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        foreach ([
            'inventory_stocks',
            'stock_movements',
            'inventory_reservations',
            'inventory_transfers',
        ] as $table) {
            DB::unprepared("
                CREATE TRIGGER {$table}_owner_relationship_insert_guard
                BEFORE INSERT ON {$table}
                FOR EACH ROW
                EXECUTE FUNCTION assert_inventory_owner_relationship()
            ");

            DB::unprepared("
                CREATE TRIGGER {$table}_owner_relationship_update_guard
                BEFORE UPDATE OF product_id, product_variant_id ON {$table}
                FOR EACH ROW
                EXECUTE FUNCTION assert_inventory_owner_relationship()
            ");
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_product_inventory_type_change()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF OLD.product_type IS DISTINCT FROM NEW.product_type THEN
                    IF NEW.product_type = 'variable' AND (
                        EXISTS (SELECT 1 FROM inventory_stocks WHERE product_id = NEW.id)
                        OR EXISTS (SELECT 1 FROM stock_movements WHERE product_id = NEW.id)
                        OR EXISTS (SELECT 1 FROM inventory_reservations WHERE product_id = NEW.id)
                        OR EXISTS (SELECT 1 FROM inventory_transfers WHERE product_id = NEW.id)
                    ) THEN
                        RAISE EXCEPTION
                            'A simple product with inventory records cannot be converted to variable.'
                            USING ERRCODE = '23514';
                    END IF;

                    IF NEW.product_type = 'simple' AND EXISTS (
                        SELECT 1
                        FROM product_variants AS variant
                        WHERE variant.product_id = NEW.id
                          AND (
                            EXISTS (SELECT 1 FROM inventory_stocks WHERE product_variant_id = variant.id)
                            OR EXISTS (SELECT 1 FROM stock_movements WHERE product_variant_id = variant.id)
                            OR EXISTS (SELECT 1 FROM inventory_reservations WHERE product_variant_id = variant.id)
                            OR EXISTS (SELECT 1 FROM inventory_transfers WHERE product_variant_id = variant.id)
                          )
                    ) THEN
                        RAISE EXCEPTION
                            'A variable product with inventory records cannot be converted to simple.'
                            USING ERRCODE = '23514';
                    END IF;
                END IF;

                IF OLD.deleted_at IS NULL
                   AND NEW.deleted_at IS NOT NULL
                   AND (
                        EXISTS (
                            SELECT 1 FROM inventory_stocks
                            WHERE product_id = NEW.id
                              AND (quantity > 0 OR reserved_quantity > 0)
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM product_variants AS variant
                            INNER JOIN inventory_stocks AS stock
                                ON stock.product_variant_id = variant.id
                            WHERE variant.product_id = NEW.id
                              AND (stock.quantity > 0 OR stock.reserved_quantity > 0)
                        )
                        OR EXISTS (SELECT 1 FROM inventory_reservations WHERE product_id = NEW.id AND status = 'active')
                        OR EXISTS (
                            SELECT 1
                            FROM product_variants AS variant
                            INNER JOIN inventory_reservations AS reservation
                                ON reservation.product_variant_id = variant.id
                            WHERE variant.product_id = NEW.id
                              AND reservation.status = 'active'
                        )
                        OR EXISTS (
                            SELECT 1 FROM inventory_transfers
                            WHERE product_id = NEW.id
                              AND status IN ('in_transit', 'partially_received', 'discrepancy')
                              AND quantity_received < quantity_sent
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM product_variants AS variant
                            INNER JOIN inventory_transfers AS transfer
                                ON transfer.product_variant_id = variant.id
                            WHERE variant.product_id = NEW.id
                              AND transfer.status IN ('in_transit', 'partially_received', 'discrepancy')
                              AND transfer.quantity_received < transfer.quantity_sent
                        )
                   )
                THEN
                    RAISE EXCEPTION
                        'A product with on-hand stock, active reservations or outstanding transfers cannot be deleted.'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER products_inventory_type_change_guard
            BEFORE UPDATE OF product_type, deleted_at ON products
            FOR EACH ROW
            EXECUTE FUNCTION guard_product_inventory_type_change()
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_product_variant_inventory_relationship()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'UPDATE'
                   AND OLD.product_id IS DISTINCT FROM NEW.product_id
                   AND (
                        EXISTS (SELECT 1 FROM inventory_stocks WHERE product_variant_id = OLD.id)
                        OR EXISTS (SELECT 1 FROM stock_movements WHERE product_variant_id = OLD.id)
                        OR EXISTS (SELECT 1 FROM inventory_reservations WHERE product_variant_id = OLD.id)
                        OR EXISTS (SELECT 1 FROM inventory_transfers WHERE product_variant_id = OLD.id)
                   )
                THEN
                    RAISE EXCEPTION
                        'A variant with inventory history cannot be reassigned to another product.'
                        USING ERRCODE = '23514';
                END IF;

                IF TG_OP = 'UPDATE'
                   AND OLD.deleted_at IS NULL
                   AND NEW.deleted_at IS NOT NULL
                   AND (
                        EXISTS (
                            SELECT 1 FROM inventory_stocks
                            WHERE product_variant_id = OLD.id
                              AND (quantity > 0 OR reserved_quantity > 0)
                        )
                        OR EXISTS (
                            SELECT 1 FROM inventory_reservations
                            WHERE product_variant_id = OLD.id
                              AND status = 'active'
                        )
                        OR EXISTS (
                            SELECT 1 FROM inventory_transfers
                            WHERE product_variant_id = OLD.id
                              AND status IN ('in_transit', 'partially_received', 'discrepancy')
                              AND quantity_received < quantity_sent
                        )
                   )
                THEN
                    RAISE EXCEPTION
                        'A variant with on-hand stock, active reservations or outstanding transfers cannot be deleted.'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER product_variants_inventory_relationship_guard
            BEFORE UPDATE OF product_id, deleted_at ON product_variants
            FOR EACH ROW
            EXECUTE FUNCTION guard_product_variant_inventory_relationship()
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS product_variants_inventory_relationship_guard ON product_variants');
        DB::unprepared('DROP TRIGGER IF EXISTS products_inventory_type_change_guard ON products');

        foreach ([
            'inventory_stocks',
            'stock_movements',
            'inventory_reservations',
            'inventory_transfers',
        ] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_owner_relationship_insert_guard ON {$table}");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_owner_relationship_update_guard ON {$table}");
        }

        DB::unprepared('DROP FUNCTION IF EXISTS guard_product_variant_inventory_relationship()');
        DB::unprepared('DROP FUNCTION IF EXISTS guard_product_inventory_type_change()');
        DB::unprepared('DROP FUNCTION IF EXISTS assert_inventory_owner_relationship()');
    }
};
