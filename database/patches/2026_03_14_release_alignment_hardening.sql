SET NAMES utf8mb4;
SET SQL_SAFE_UPDATES = 0;
SET @patch_db := DATABASE();

DROP PROCEDURE IF EXISTS __patch_exec;
DROP PROCEDURE IF EXISTS __patch_exec_if_column_missing;
DROP PROCEDURE IF EXISTS __patch_exec_if_index_missing;
DROP PROCEDURE IF EXISTS __patch_exec_if_check_missing;
DROP PROCEDURE IF EXISTS __patch_exec_if_constraint_exists;

DELIMITER $$
CREATE PROCEDURE __patch_exec(IN p_sql LONGTEXT)
BEGIN
  SET @__patch_sql := p_sql;
  PREPARE __patch_stmt FROM @__patch_sql;
  EXECUTE __patch_stmt;
  DEALLOCATE PREPARE __patch_stmt;
END $$

CREATE PROCEDURE __patch_exec_if_column_missing(
  IN p_schema VARCHAR(128),
  IN p_table VARCHAR(128),
  IN p_column VARCHAR(128),
  IN p_sql LONGTEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = p_schema
      AND table_name = p_table
      AND column_name = p_column
  ) THEN
    CALL __patch_exec(p_sql);
  END IF;
END $$

CREATE PROCEDURE __patch_exec_if_index_missing(
  IN p_schema VARCHAR(128),
  IN p_table VARCHAR(128),
  IN p_index VARCHAR(128),
  IN p_sql LONGTEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = p_schema
      AND table_name = p_table
      AND index_name = p_index
  ) THEN
    CALL __patch_exec(p_sql);
  END IF;
END $$

CREATE PROCEDURE __patch_exec_if_check_missing(
  IN p_schema VARCHAR(128),
  IN p_table VARCHAR(128),
  IN p_constraint VARCHAR(128),
  IN p_sql LONGTEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE constraint_schema = p_schema
      AND table_name = p_table
      AND constraint_type = 'CHECK'
      AND constraint_name = p_constraint
  ) THEN
    CALL __patch_exec(p_sql);
  END IF;
END $$

CREATE PROCEDURE __patch_exec_if_constraint_exists(
  IN p_schema VARCHAR(128),
  IN p_table VARCHAR(128),
  IN p_constraint VARCHAR(128),
  IN p_sql LONGTEXT
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE constraint_schema = p_schema
      AND table_name = p_table
      AND constraint_name = p_constraint
  ) THEN
    CALL __patch_exec(p_sql);
  END IF;
END $$
DELIMITER ;

CALL __patch_exec_if_column_missing(
  @patch_db,
  'menu_items',
  'is_preorder_enabled',
  'ALTER TABLE `menu_items` ADD COLUMN `is_preorder_enabled` tinyint unsigned NOT NULL DEFAULT 0 AFTER `is_available`'
);

CALL __patch_exec_if_column_missing(
  @patch_db,
  'menu_items',
  'preorder_quota_per_day',
  'ALTER TABLE `menu_items` ADD COLUMN `preorder_quota_per_day` int unsigned DEFAULT NULL AFTER `is_preorder_enabled`'
);

CALL __patch_exec_if_column_missing(
  @patch_db,
  'menu_items',
  'preorder_cutoff_minutes',
  'ALTER TABLE `menu_items` ADD COLUMN `preorder_cutoff_minutes` int unsigned NOT NULL DEFAULT 0 AFTER `preorder_quota_per_day`'
);

UPDATE `menu_items`
SET `is_preorder_enabled` = CASE WHEN `is_preorder_enabled` = 1 THEN 1 ELSE 0 END,
    `preorder_quota_per_day` = CASE WHEN `preorder_quota_per_day` IS NOT NULL AND `preorder_quota_per_day` < 0 THEN 0 ELSE `preorder_quota_per_day` END,
    `preorder_cutoff_minutes` = CASE WHEN `preorder_cutoff_minutes` IS NULL OR `preorder_cutoff_minutes` < 0 THEN 0 ELSE `preorder_cutoff_minutes` END;

ALTER TABLE `menu_items`
  MODIFY COLUMN `is_preorder_enabled` tinyint unsigned NOT NULL DEFAULT 0,
  MODIFY COLUMN `preorder_cutoff_minutes` int unsigned NOT NULL DEFAULT 0;

CALL __patch_exec_if_index_missing(
  @patch_db,
  'menu_items',
  'idx_menu_items__available__preorder_enabled',
  'ALTER TABLE `menu_items` ADD INDEX `idx_menu_items__available__preorder_enabled` (`is_available`, `is_preorder_enabled`)'
);

CALL __patch_exec_if_constraint_exists(
  @patch_db,
  'menu_items',
  'chk_menu_items__preorder_quota_nonneg',
  'ALTER TABLE `menu_items` DROP CHECK `chk_menu_items__preorder_quota_nonneg`'
);

CALL __patch_exec_if_constraint_exists(
  @patch_db,
  'menu_items',
  'chk_menu_items__preorder_cutoff_nonneg',
  'ALTER TABLE `menu_items` DROP CHECK `chk_menu_items__preorder_cutoff_nonneg`'
);

CALL __patch_exec_if_check_missing(
  @patch_db,
  'menu_items',
  'chk_menu_items__preorder_quota_nonnegative',
  'ALTER TABLE `menu_items` ADD CONSTRAINT `chk_menu_items__preorder_quota_nonnegative` CHECK (`preorder_quota_per_day` IS NULL OR `preorder_quota_per_day` >= 0)'
);

CALL __patch_exec_if_check_missing(
  @patch_db,
  'menu_items',
  'chk_menu_items__preorder_cutoff_nonnegative',
  'ALTER TABLE `menu_items` ADD CONSTRAINT `chk_menu_items__preorder_cutoff_nonnegative` CHECK (`preorder_cutoff_minutes` >= 0)'
);

UPDATE `reservations`
SET `deposit_status` = CASE
    WHEN `deposit_status` IN ('NotRequired', 'Pending', 'Paid', 'Refunded', 'PartiallyRefunded', 'Forfeited') THEN `deposit_status`
    WHEN COALESCE(`deposit_required_amount`, 0) <= 0.0001 AND COALESCE(`deposit_paid_amount`, 0) <= 0.0001 THEN 'NotRequired'
    WHEN COALESCE(`deposit_paid_amount`, 0) <= 0.0001 THEN 'Pending'
    WHEN COALESCE(`deposit_paid_amount`, 0) + 0.0001 >= COALESCE(`deposit_required_amount`, 0) THEN 'Paid'
    ELSE 'Pending'
END;

CALL __patch_exec_if_constraint_exists(@patch_db, 'reservations', 'chk_reservations__deposit_status_allowed', 'ALTER TABLE `reservations` DROP CHECK `chk_reservations__deposit_status_allowed`');
CALL __patch_exec_if_constraint_exists(@patch_db, 'reservations', 'chk_reservations__deposit_required_amount_nonneg', 'ALTER TABLE `reservations` DROP CHECK `chk_reservations__deposit_required_amount_nonneg`');
CALL __patch_exec_if_constraint_exists(@patch_db, 'reservations', 'chk_reservations__deposit_paid_amount_nonneg', 'ALTER TABLE `reservations` DROP CHECK `chk_reservations__deposit_paid_amount_nonneg`');
CALL __patch_exec_if_constraint_exists(@patch_db, 'reservations', 'chk_reservations__discount_amount_nonneg', 'ALTER TABLE `reservations` DROP CHECK `chk_reservations__discount_amount_nonneg`');
CALL __patch_exec_if_constraint_exists(@patch_db, 'reservations', 'chk_reservations__final_bill_amount_nonneg', 'ALTER TABLE `reservations` DROP CHECK `chk_reservations__final_bill_amount_nonneg`');
CALL __patch_exec_if_constraint_exists(@patch_db, 'reservation_order_items', 'chk_reservation_order_items__line_total_consistent', 'ALTER TABLE `reservation_order_items` DROP CHECK `chk_reservation_order_items__line_total_consistent`');
CALL __patch_exec_if_constraint_exists(@patch_db, 'reservations', 'chk_reservations__reserved_requires_checkin', 'ALTER TABLE `reservations` DROP CHECK `chk_reservations__reserved_requires_checkin`');

CALL __patch_exec_if_check_missing(@patch_db, 'reservations', 'chk_reservations__deposit_status', 'ALTER TABLE `reservations` ADD CONSTRAINT `chk_reservations__deposit_status` CHECK (`deposit_status` IN (''NotRequired'', ''Pending'', ''Paid'', ''Refunded'', ''PartiallyRefunded'', ''Forfeited''))');
CALL __patch_exec_if_check_missing(@patch_db, 'reservations', 'chk_reservations__deposit_required_nonneg', 'ALTER TABLE `reservations` ADD CONSTRAINT `chk_reservations__deposit_required_nonneg` CHECK (`deposit_required_amount` >= 0)');
CALL __patch_exec_if_check_missing(@patch_db, 'reservations', 'chk_reservations__deposit_paid_nonneg', 'ALTER TABLE `reservations` ADD CONSTRAINT `chk_reservations__deposit_paid_nonneg` CHECK (`deposit_paid_amount` >= 0)');
CALL __patch_exec_if_check_missing(@patch_db, 'reservations', 'chk_reservations__discount_nonneg', 'ALTER TABLE `reservations` ADD CONSTRAINT `chk_reservations__discount_nonneg` CHECK (`discount_amount` >= 0)');
CALL __patch_exec_if_check_missing(@patch_db, 'reservations', 'chk_reservations__final_bill_nonneg', 'ALTER TABLE `reservations` ADD CONSTRAINT `chk_reservations__final_bill_nonneg` CHECK (`final_bill_amount` IS NULL OR `final_bill_amount` >= 0)');
CALL __patch_exec_if_check_missing(@patch_db, 'reservation_order_items', 'chk_reservation_order_items__line_total_matches', 'ALTER TABLE `reservation_order_items` ADD CONSTRAINT `chk_reservation_order_items__line_total_matches` CHECK (`line_total` = ROUND(`unit_price` * `quantity`, 2))');
CALL __patch_exec_if_check_missing(@patch_db, 'reservations', 'chk_reservations__reserved_requires_checked_in_at', 'ALTER TABLE `reservations` ADD CONSTRAINT `chk_reservations__reserved_requires_checked_in_at` CHECK (`status` <> ''Reserved'' OR `checked_in_at` IS NOT NULL)');

DROP PROCEDURE IF EXISTS __patch_exec_if_constraint_exists;
DROP PROCEDURE IF EXISTS __patch_exec_if_check_missing;
DROP PROCEDURE IF EXISTS __patch_exec_if_index_missing;
DROP PROCEDURE IF EXISTS __patch_exec_if_column_missing;
DROP PROCEDURE IF EXISTS __patch_exec;
