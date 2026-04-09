SET SQL_SAFE_UPDATES = 0;
SET NAMES utf8mb4;
SET @patch_db := DATABASE();

DROP PROCEDURE IF EXISTS __patch_exec;
DROP PROCEDURE IF EXISTS __patch_exec_if_check_missing;
DROP PROCEDURE IF EXISTS __patch_exec_if_column_exists;
DROP PROCEDURE IF EXISTS __patch_assert_zero;

DELIMITER $$
CREATE PROCEDURE __patch_exec(IN p_sql LONGTEXT)
BEGIN
  SET @__patch_sql := p_sql;
  PREPARE __patch_stmt FROM @__patch_sql;
  EXECUTE __patch_stmt;
  DEALLOCATE PREPARE __patch_stmt;
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
      AND constraint_name = p_constraint
      AND constraint_type = 'CHECK'
  ) THEN
    CALL __patch_exec(p_sql);
  END IF;
END $$

CREATE PROCEDURE __patch_exec_if_column_exists(
  IN p_schema VARCHAR(128),
  IN p_table VARCHAR(128),
  IN p_column VARCHAR(128),
  IN p_sql LONGTEXT
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = p_schema
      AND table_name = p_table
      AND column_name = p_column
  ) THEN
    CALL __patch_exec(p_sql);
  END IF;
END $$

CREATE PROCEDURE __patch_assert_zero(IN p_count BIGINT, IN p_message VARCHAR(255))
BEGIN
  IF p_count <> 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = p_message;
  END IF;
END $$
DELIMITER ;

UPDATE `reservation_order_items`
SET `line_total` = ROUND(`unit_price` * `quantity`, 2)
WHERE `line_total` <> ROUND(`unit_price` * `quantity`, 2);

SET @invalid_reservation_money := (
  SELECT COUNT(*)
  FROM `reservations`
  WHERE `deposit_required_amount` < 0
     OR `deposit_paid_amount` < 0
     OR `discount_amount` < 0
     OR (`final_bill_amount` IS NOT NULL AND `final_bill_amount` < 0)
);
CALL __patch_assert_zero(@invalid_reservation_money, 'Cannot converge DB contract: reservations contains negative financial values');

SET @invalid_order_item_money := (
  SELECT COUNT(*)
  FROM `reservation_order_items`
  WHERE `unit_price` < 0
     OR `line_total` < 0
);
CALL __patch_assert_zero(@invalid_order_item_money, 'Cannot converge DB contract: reservation_order_items contains negative money values');

SET @invalid_user_voucher_money := (
  SELECT COUNT(*)
  FROM `user_vouchers`
  WHERE `used_amount` IS NOT NULL
    AND `used_amount` < 0
);
CALL __patch_assert_zero(@invalid_user_voucher_money, 'Cannot converge DB contract: user_vouchers contains negative used_amount values');

SET @invalid_refund_payments := (
  SELECT COUNT(*)
  FROM `payments`
  WHERE `payment_type` = 'Refund'
    AND `status` <> 'Refunded'
);
CALL __patch_assert_zero(@invalid_refund_payments, 'Cannot converge DB contract: refund payments exist with a non-Refunded status');

UPDATE `reservations`
SET `deposit_status` = CASE
    WHEN `deposit_status` IN ('NotRequired', 'Pending', 'Paid', 'Refunded', 'PartiallyRefunded', 'Forfeited') THEN `deposit_status`
    WHEN COALESCE(`deposit_required_amount`, 0) <= 0.0001 AND COALESCE(`deposit_paid_amount`, 0) <= 0.0001 THEN 'NotRequired'
    WHEN COALESCE(`deposit_paid_amount`, 0) <= 0.0001 THEN 'Pending'
    WHEN COALESCE(`deposit_paid_amount`, 0) + 0.0001 >= COALESCE(`deposit_required_amount`, 0) THEN 'Paid'
    ELSE 'Pending'
END;

CALL __patch_exec_if_column_exists(
  @patch_db,
  'reservations',
  'deposit_status',
  "ALTER TABLE `reservations` MODIFY COLUMN `deposit_status` VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NotRequired'"
);

CALL __patch_exec_if_check_missing(@patch_db, 'reservations', 'chk_reservations__deposit_status', "ALTER TABLE `reservations` ADD CONSTRAINT `chk_reservations__deposit_status` CHECK (`deposit_status` IN ('NotRequired','Pending','Paid','Refunded','PartiallyRefunded','Forfeited'))");
CALL __patch_exec_if_check_missing(@patch_db, 'reservations', 'chk_reservations__deposit_required_nonneg', "ALTER TABLE `reservations` ADD CONSTRAINT `chk_reservations__deposit_required_nonneg` CHECK (`deposit_required_amount` >= 0)");
CALL __patch_exec_if_check_missing(@patch_db, 'reservations', 'chk_reservations__deposit_paid_nonneg', "ALTER TABLE `reservations` ADD CONSTRAINT `chk_reservations__deposit_paid_nonneg` CHECK (`deposit_paid_amount` >= 0)");
CALL __patch_exec_if_check_missing(@patch_db, 'reservations', 'chk_reservations__discount_nonneg', "ALTER TABLE `reservations` ADD CONSTRAINT `chk_reservations__discount_nonneg` CHECK (`discount_amount` >= 0)");
CALL __patch_exec_if_check_missing(@patch_db, 'reservations', 'chk_reservations__final_bill_nonneg', "ALTER TABLE `reservations` ADD CONSTRAINT `chk_reservations__final_bill_nonneg` CHECK (`final_bill_amount` IS NULL OR `final_bill_amount` >= 0)");
CALL __patch_exec_if_check_missing(@patch_db, 'reservation_order_items', 'chk_reservation_order_items__unit_price_nonneg', "ALTER TABLE `reservation_order_items` ADD CONSTRAINT `chk_reservation_order_items__unit_price_nonneg` CHECK (`unit_price` >= 0)");
CALL __patch_exec_if_check_missing(@patch_db, 'reservation_order_items', 'chk_reservation_order_items__line_total_nonneg', "ALTER TABLE `reservation_order_items` ADD CONSTRAINT `chk_reservation_order_items__line_total_nonneg` CHECK (`line_total` >= 0)");
CALL __patch_exec_if_check_missing(@patch_db, 'reservation_order_items', 'chk_reservation_order_items__line_total_matches', "ALTER TABLE `reservation_order_items` ADD CONSTRAINT `chk_reservation_order_items__line_total_matches` CHECK (`line_total` = ROUND(`unit_price` * `quantity`, 2))");
CALL __patch_exec_if_check_missing(@patch_db, 'user_vouchers', 'chk_user_vouchers__used_amount_nonneg', "ALTER TABLE `user_vouchers` ADD CONSTRAINT `chk_user_vouchers__used_amount_nonneg` CHECK (`used_amount` IS NULL OR `used_amount` >= 0)");
CALL __patch_exec_if_check_missing(@patch_db, 'payments', 'chk_payments__type', "ALTER TABLE `payments` ADD CONSTRAINT `chk_payments__type` CHECK (`payment_type` IN ('Deposit','Final','Refund'))");
CALL __patch_exec_if_check_missing(@patch_db, 'payments', 'chk_payments__status', "ALTER TABLE `payments` ADD CONSTRAINT `chk_payments__status` CHECK (`status` IN ('Pending','Partial','Success','Failed','Refunded'))");
CALL __patch_exec_if_check_missing(@patch_db, 'payments', 'chk_payments__refund_status', "ALTER TABLE `payments` ADD CONSTRAINT `chk_payments__refund_status` CHECK (`payment_type` <> 'Refund' OR `status` = 'Refunded')");

UPDATE `migrations`
SET `migration` = '2026_03_14_000005_financial_contract_hardening'
WHERE `migration` = '2026_03_14_000005_final_ten_point_hardening'
  AND NOT EXISTS (
    SELECT 1 FROM (
      SELECT `migration` FROM `migrations`
    ) AS `m`
    WHERE `migration` = '2026_03_14_000005_financial_contract_hardening'
  );

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_03_14_000006_financial_integrity_hardening', 3
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_03_14_000006_financial_integrity_hardening');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_03_14_000007_financial_integrity_contract_round', 3
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_03_14_000007_financial_integrity_contract_round');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_03_14_000008_reservation_lifecycle_consistency_round', 3
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_03_14_000008_reservation_lifecycle_consistency_round');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_03_14_000009_voucher_loyalty_consistency_round', 3
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_03_14_000009_voucher_loyalty_consistency_round');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_03_14_000010_menu_preorder_alignment', 3
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_03_14_000010_menu_preorder_alignment');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_03_14_000011_release_contract_canonicalization', 4
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_03_14_000011_release_contract_canonicalization');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_03_14_000012_database_contract_convergence_round', 5
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_03_14_000012_database_contract_convergence_round');

DROP PROCEDURE IF EXISTS __patch_assert_zero;
DROP PROCEDURE IF EXISTS __patch_exec_if_column_exists;
DROP PROCEDURE IF EXISTS __patch_exec_if_check_missing;
DROP PROCEDURE IF EXISTS __patch_exec;
