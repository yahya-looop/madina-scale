<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Serialize product and variant writes that can affect identifiers.
         * The transaction-level lock is shared by all application instances
         * connected to this PostgreSQL database.
         */
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION lock_product_identifier_writes()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            BEGIN
                PERFORM pg_advisory_xact_lock(73142, 1);
                RETURN NULL;
            END;
            $$;
        SQL);

        /*
         * Synchronize identifiers owned by products.
         */
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION sync_product_identifiers()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_identifier_type TEXT;
                v_identifier_value TEXT;
                v_existing_normalized TEXT;
                v_has_active BOOLEAN;
            BEGIN
                IF NEW.product_type NOT IN ('simple', 'variable') THEN
                    RAISE EXCEPTION
                        'Product type must be simple or variable.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.product_type = 'variable'
                   AND (
                        NULLIF(BTRIM(NEW.sku), '') IS NOT NULL
                        OR NULLIF(BTRIM(NEW.barcode), '') IS NOT NULL
                   )
                THEN
                    RAISE EXCEPTION
                        'Variable products cannot have a parent SKU or barcode.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.product_type = 'simple'
                   AND NEW.deleted_at IS NULL
                   AND NULLIF(BTRIM(NEW.sku), '') IS NULL
                THEN
                    RAISE EXCEPTION
                        'Active simple products must have a SKU.'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.product_type = 'simple'
                   AND EXISTS (
                       SELECT 1
                       FROM product_variants pv
                       WHERE pv.product_id = NEW.id
                         AND pv.deleted_at IS NULL
                   )
                THEN
                    RAISE EXCEPTION
                        'A product with active variants cannot be changed to simple.'
                        USING ERRCODE = '23514';
                END IF;

                FOREACH v_identifier_type IN ARRAY
                    ARRAY['sku', 'barcode']
                LOOP
                    v_identifier_value :=
                        CASE v_identifier_type
                            WHEN 'sku' THEN
                                CASE
                                    WHEN NEW.deleted_at IS NULL
                                         AND NEW.product_type = 'simple'
                                    THEN NULLIF(BTRIM(NEW.sku), '')
                                    ELSE NULL
                                END
                            WHEN 'barcode' THEN
                                CASE
                                    WHEN NEW.deleted_at IS NULL
                                         AND NEW.product_type = 'simple'
                                    THEN NULLIF(BTRIM(NEW.barcode), '')
                                    ELSE NULL
                                END
                        END;

                    SELECT normalized_value
                    INTO v_existing_normalized
                    FROM product_identifiers
                    WHERE owner_product_id = NEW.id
                      AND identifier_type = v_identifier_type
                      AND deleted_at IS NULL
                    FOR UPDATE;

                    v_has_active := FOUND;

                    IF v_identifier_value IS NULL THEN
                        IF v_has_active THEN
                            UPDATE product_identifiers
                            SET deleted_at = COALESCE(
                                    NEW.deleted_at,
                                    CURRENT_TIMESTAMP
                                ),
                                updated_at = CURRENT_TIMESTAMP
                            WHERE owner_product_id = NEW.id
                              AND identifier_type = v_identifier_type
                              AND deleted_at IS NULL;
                        END IF;

                    ELSIF v_has_active
                          AND v_existing_normalized =
                              LOWER(BTRIM(v_identifier_value))
                    THEN
                        UPDATE product_identifiers
                        SET identifier_value = v_identifier_value,
                            normalized_value = LOWER(BTRIM(v_identifier_value)),
                            updated_at = CURRENT_TIMESTAMP
                        WHERE owner_product_id = NEW.id
                          AND identifier_type = v_identifier_type
                          AND deleted_at IS NULL;

                    ELSE
                        IF v_has_active THEN
                            UPDATE product_identifiers
                            SET deleted_at = CURRENT_TIMESTAMP,
                                updated_at = CURRENT_TIMESTAMP
                            WHERE owner_product_id = NEW.id
                              AND identifier_type = v_identifier_type
                              AND deleted_at IS NULL;
                        END IF;

                        INSERT INTO product_identifiers (
                            owner_product_id,
                            owner_variant_id,
                            identifier_type,
                            identifier_value,
                            normalized_value,
                            created_at,
                            updated_at
                        )
                        VALUES (
                            NEW.id,
                            NULL,
                            v_identifier_type,
                            v_identifier_value,
                            LOWER(BTRIM(v_identifier_value)),
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        );
                    END IF;
                END LOOP;

                /*
                 * Release child-variant identifiers when the parent is
                 * soft-deleted. Variant records themselves are retained.
                 */
                IF NEW.deleted_at IS NOT NULL THEN
                    UPDATE product_identifiers pi
                    SET deleted_at = NEW.deleted_at,
                        updated_at = CURRENT_TIMESTAMP
                    FROM product_variants pv
                    WHERE pi.owner_variant_id = pv.id
                      AND pv.product_id = NEW.id
                      AND pi.deleted_at IS NULL;

                /*
                 * Restore identifiers from the variants' current values.
                 * The global unique index rejects identifiers reused by
                 * another active product or variant.
                 */
                ELSIF TG_OP = 'UPDATE'
                      AND OLD.deleted_at IS NOT NULL
                      AND NEW.deleted_at IS NULL
                      AND NEW.product_type = 'variable'
                THEN
                    UPDATE product_variants
                    SET sku = sku
                    WHERE product_id = NEW.id
                      AND deleted_at IS NULL;
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        /*
         * Synchronize identifiers owned by variants.
         */
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION sync_variant_identifiers()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_identifier_type TEXT;
                v_identifier_value TEXT;
                v_existing_normalized TEXT;
                v_parent_deleted_at TIMESTAMP;
                v_parent_type TEXT;
                v_has_active BOOLEAN;
            BEGIN
                SELECT deleted_at, product_type
                INTO v_parent_deleted_at, v_parent_type
                FROM products
                WHERE id = NEW.product_id
                FOR UPDATE;

                IF NOT FOUND THEN
                    RAISE EXCEPTION
                        'Variant parent product does not exist.'
                        USING ERRCODE = '23503';
                END IF;

                IF v_parent_type <> 'variable' THEN
                    RAISE EXCEPTION
                        'Variants can only belong to variable products.'
                        USING ERRCODE = '23514';
                END IF;

                FOREACH v_identifier_type IN ARRAY
                    ARRAY['sku', 'barcode']
                LOOP
                    v_identifier_value :=
                        CASE v_identifier_type
                            WHEN 'sku' THEN
                                CASE
                                    WHEN NEW.deleted_at IS NULL
                                         AND v_parent_deleted_at IS NULL
                                    THEN NULLIF(BTRIM(NEW.sku), '')
                                    ELSE NULL
                                END
                            WHEN 'barcode' THEN
                                CASE
                                    WHEN NEW.deleted_at IS NULL
                                         AND v_parent_deleted_at IS NULL
                                    THEN NULLIF(BTRIM(NEW.barcode), '')
                                    ELSE NULL
                                END
                        END;

                    SELECT normalized_value
                    INTO v_existing_normalized
                    FROM product_identifiers
                    WHERE owner_variant_id = NEW.id
                      AND identifier_type = v_identifier_type
                      AND deleted_at IS NULL
                    FOR UPDATE;

                    v_has_active := FOUND;

                    IF v_identifier_value IS NULL THEN
                        IF v_has_active THEN
                            UPDATE product_identifiers
                            SET deleted_at = COALESCE(
                                    NEW.deleted_at,
                                    v_parent_deleted_at,
                                    CURRENT_TIMESTAMP
                                ),
                                updated_at = CURRENT_TIMESTAMP
                            WHERE owner_variant_id = NEW.id
                              AND identifier_type = v_identifier_type
                              AND deleted_at IS NULL;
                        END IF;

                    ELSIF v_has_active
                          AND v_existing_normalized =
                              LOWER(BTRIM(v_identifier_value))
                    THEN
                        UPDATE product_identifiers
                        SET identifier_value = v_identifier_value,
                            normalized_value = LOWER(BTRIM(v_identifier_value)),
                            updated_at = CURRENT_TIMESTAMP
                        WHERE owner_variant_id = NEW.id
                          AND identifier_type = v_identifier_type
                          AND deleted_at IS NULL;

                    ELSE
                        IF v_has_active THEN
                            UPDATE product_identifiers
                            SET deleted_at = CURRENT_TIMESTAMP,
                                updated_at = CURRENT_TIMESTAMP
                            WHERE owner_variant_id = NEW.id
                              AND identifier_type = v_identifier_type
                              AND deleted_at IS NULL;
                        END IF;

                        INSERT INTO product_identifiers (
                            owner_product_id,
                            owner_variant_id,
                            identifier_type,
                            identifier_value,
                            normalized_value,
                            created_at,
                            updated_at
                        )
                        VALUES (
                            NULL,
                            NEW.id,
                            v_identifier_type,
                            v_identifier_value,
                            LOWER(BTRIM(v_identifier_value)),
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        );
                    END IF;
                END LOOP;

                RETURN NEW;
            END;
            $$;
        SQL);

        /*
         * Statement-level locks are acquired before row-level work.
         */
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER products_identifier_write_lock_insert
            BEFORE INSERT ON products
            FOR EACH STATEMENT
            EXECUTE FUNCTION lock_product_identifier_writes();
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER products_identifier_write_lock_update
            BEFORE UPDATE OF sku, barcode, product_type, deleted_at
            ON products
            FOR EACH STATEMENT
            EXECUTE FUNCTION lock_product_identifier_writes();
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER product_variants_identifier_write_lock_insert
            BEFORE INSERT ON product_variants
            FOR EACH STATEMENT
            EXECUTE FUNCTION lock_product_identifier_writes();
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER product_variants_identifier_write_lock_update
            BEFORE UPDATE OF sku, barcode, product_id, deleted_at
            ON product_variants
            FOR EACH STATEMENT
            EXECUTE FUNCTION lock_product_identifier_writes();
        SQL);

        /*
         * Row-level synchronization triggers.
         */
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER products_sync_identifiers_insert_trigger
            AFTER INSERT ON products
            FOR EACH ROW
            EXECUTE FUNCTION sync_product_identifiers();
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER products_sync_identifiers_update_trigger
            AFTER UPDATE OF sku, barcode, product_type, deleted_at
            ON products
            FOR EACH ROW
            EXECUTE FUNCTION sync_product_identifiers();
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER product_variants_sync_identifiers_insert_trigger
            AFTER INSERT ON product_variants
            FOR EACH ROW
            EXECUTE FUNCTION sync_variant_identifiers();
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER product_variants_sync_identifiers_update_trigger
            AFTER UPDATE OF sku, barcode, product_id, deleted_at
            ON product_variants
            FOR EACH ROW
            EXECUTE FUNCTION sync_variant_identifiers();
        SQL);

        /*
         * Backfill through the same trigger logic. Any duplicate active
         * identifiers or invalid product/variant state aborts the migration.
         */
        DB::unprepared(<<<'SQL'
            UPDATE products
            SET sku = sku;
        SQL);

        DB::unprepared(<<<'SQL'
            UPDATE product_variants
            SET sku = sku;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(
            'DROP TRIGGER IF EXISTS products_identifier_write_lock_insert ON products'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS products_identifier_write_lock_update ON products'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS product_variants_identifier_write_lock_insert ON product_variants'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS product_variants_identifier_write_lock_update ON product_variants'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS products_sync_identifiers_insert_trigger ON products'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS products_sync_identifiers_update_trigger ON products'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS product_variants_sync_identifiers_insert_trigger ON product_variants'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS product_variants_sync_identifiers_update_trigger ON product_variants'
        );

        DB::unprepared(
            'DROP FUNCTION IF EXISTS sync_product_identifiers()'
        );

        DB::unprepared(
            'DROP FUNCTION IF EXISTS sync_variant_identifiers()'
        );

        DB::unprepared(
            'DROP FUNCTION IF EXISTS lock_product_identifier_writes()'
        );
    }
};
