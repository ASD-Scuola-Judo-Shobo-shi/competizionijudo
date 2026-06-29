-- Add the application-required column without rewriting historical migrations.
-- Existing rows intentionally default to an empty category; legacy values are
-- copied only when the old categoria_peso column is present.

SET @weight_category_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'athletes'
      AND COLUMN_NAME = 'weight_category'
);

SET @add_weight_category_sql = IF(
    @weight_category_exists = 0,
    CONCAT(
        'ALTER TABLE athletes ADD COLUMN weight_category VARCHAR(50) NOT NULL DEFAULT ',
        QUOTE(''),
        ' AFTER program'
    ),
    'DO 1'
);

PREPARE add_weight_category_statement FROM @add_weight_category_sql;
EXECUTE add_weight_category_statement;
DEALLOCATE PREPARE add_weight_category_statement;

SET @legacy_weight_category_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'athletes'
      AND COLUMN_NAME = 'categoria_peso'
);

SET @backfill_weight_category_sql = IF(
    @legacy_weight_category_exists > 0,
    CONCAT(
        'UPDATE athletes SET weight_category = categoria_peso ',
        'WHERE weight_category = ',
        QUOTE(''),
        ' AND categoria_peso IS NOT NULL AND categoria_peso <> ',
        QUOTE('')
    ),
    'DO 1'
);

PREPARE backfill_weight_category_statement FROM @backfill_weight_category_sql;
EXECUTE backfill_weight_category_statement;
DEALLOCATE PREPARE backfill_weight_category_statement;
