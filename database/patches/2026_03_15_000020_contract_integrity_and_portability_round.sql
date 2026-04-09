SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_contract_integrity_and_portability_round`;
DELIMITER $$
CREATE PROCEDURE `sp_contract_integrity_and_portability_round`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'menu_items'
      AND column_name = 'is_preorder_enabled'
  ) THEN
    ALTER TABLE `menu_items`
      ADD COLUMN `is_preorder_enabled` tinyint unsigned NOT NULL DEFAULT 0 AFTER `is_available`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'menu_items'
      AND column_name = 'preorder_quota_per_day'
  ) THEN
    ALTER TABLE `menu_items`
      ADD COLUMN `preorder_quota_per_day` int unsigned NULL AFTER `is_preorder_enabled`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'menu_items'
      AND column_name = 'preorder_cutoff_minutes'
  ) THEN
    ALTER TABLE `menu_items`
      ADD COLUMN `preorder_cutoff_minutes` int unsigned NOT NULL DEFAULT 0 AFTER `preorder_quota_per_day`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'table_holds'
      AND index_name = 'idx_table_holds__session_id__confirmed_reservation_id'
  ) THEN
    ALTER TABLE `table_holds`
      ADD INDEX `idx_table_holds__session_id__confirmed_reservation_id` (`session_id`, `confirmed_reservation_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'user_vouchers'
      AND index_name = 'idx_user_vouchers__voucher_id__is_used__user_id'
  ) THEN
    ALTER TABLE `user_vouchers`
      ADD INDEX `idx_user_vouchers__voucher_id__is_used__user_id` (`voucher_id`, `is_used`, `user_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND constraint_name = 'chk_reservations__deposit_status'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `reservations`
      ADD CONSTRAINT `chk_reservations__deposit_status`
      CHECK (`deposit_status` IN ('NotRequired','Pending','Paid','Refunded','PartiallyRefunded','Forfeited'));
  END IF;
END $$
DELIMITER ;

CALL `sp_contract_integrity_and_portability_round`();
DROP PROCEDURE IF EXISTS `sp_contract_integrity_and_portability_round`;

DROP TRIGGER IF EXISTS `trg_payments__bi_refund_lineage_guard`;
DROP TRIGGER IF EXISTS `trg_payments__bu_refund_lineage_guard`;

DELIMITER $$
CREATE TRIGGER `trg_payments__bi_refund_lineage_guard`
BEFORE INSERT ON `payments`
FOR EACH ROW
BEGIN
  DECLARE src_reservation_id INT UNSIGNED DEFAULT NULL;
  DECLARE src_payment_type VARCHAR(20) DEFAULT NULL;
  DECLARE src_amount DECIMAL(14,2) DEFAULT NULL;
  DECLARE src_currency VARCHAR(10) DEFAULT NULL;
  DECLARE refunded_total DECIMAL(14,2) DEFAULT 0.00;

  IF NEW.`refund_of_payment_id` IS NULL THEN
    IF NEW.`payment_type` = 'Refund' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments refund rows must reference source payment';
    END IF;
  ELSE
    IF NEW.`payment_type` <> 'Refund' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments only refund rows may reference refund_of_payment_id';
    END IF;

    SELECT `reservation_id`, `payment_type`, `amount`, `currency`
      INTO src_reservation_id, src_payment_type, src_amount, src_currency
    FROM `payments`
    WHERE `payment_id` = NEW.`refund_of_payment_id`
    LIMIT 1;

    IF src_payment_type IS NULL OR src_payment_type = 'Refund' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments refund lineage must target a non-refund payment';
    END IF;

    IF NEW.`reservation_id` <> src_reservation_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments refund lineage must stay inside reservation';
    END IF;

    IF COALESCE(NEW.`currency`, '') <> COALESCE(src_currency, '') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments refund currency must match source payment';
    END IF;

    SELECT COALESCE(SUM(`amount`), 0.00)
      INTO refunded_total
    FROM `payments`
    WHERE `refund_of_payment_id` = NEW.`refund_of_payment_id`
      AND `payment_type` = 'Refund'
      AND `status` = 'Refunded';

    IF refunded_total + COALESCE(NEW.`amount`, 0.00) > COALESCE(src_amount, 0.00) + 0.0001 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments refund exceeds source payment amount';
    END IF;
  END IF;
END $$

CREATE TRIGGER `trg_payments__bu_refund_lineage_guard`
BEFORE UPDATE ON `payments`
FOR EACH ROW
BEGIN
  DECLARE src_reservation_id INT UNSIGNED DEFAULT NULL;
  DECLARE src_payment_type VARCHAR(20) DEFAULT NULL;
  DECLARE src_amount DECIMAL(14,2) DEFAULT NULL;
  DECLARE src_currency VARCHAR(10) DEFAULT NULL;
  DECLARE refunded_total DECIMAL(14,2) DEFAULT 0.00;

  IF NEW.`refund_of_payment_id` IS NULL THEN
    IF NEW.`payment_type` = 'Refund' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments refund rows must reference source payment';
    END IF;
  ELSE
    IF NEW.`payment_type` <> 'Refund' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments only refund rows may reference refund_of_payment_id';
    END IF;

    SELECT `reservation_id`, `payment_type`, `amount`, `currency`
      INTO src_reservation_id, src_payment_type, src_amount, src_currency
    FROM `payments`
    WHERE `payment_id` = NEW.`refund_of_payment_id`
    LIMIT 1;

    IF src_payment_type IS NULL OR src_payment_type = 'Refund' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments refund lineage must target a non-refund payment';
    END IF;

    IF NEW.`reservation_id` <> src_reservation_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments refund lineage must stay inside reservation';
    END IF;

    IF COALESCE(NEW.`currency`, '') <> COALESCE(src_currency, '') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments refund currency must match source payment';
    END IF;

    SELECT COALESCE(SUM(`amount`), 0.00)
      INTO refunded_total
    FROM `payments`
    WHERE `refund_of_payment_id` = NEW.`refund_of_payment_id`
      AND `payment_type` = 'Refund'
      AND `status` = 'Refunded'
      AND `payment_id` <> NEW.`payment_id`;

    IF refunded_total + COALESCE(NEW.`amount`, 0.00) > COALESCE(src_amount, 0.00) + 0.0001 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'payments refund exceeds source payment amount';
    END IF;
  END IF;
END $$
DELIMITER ;
