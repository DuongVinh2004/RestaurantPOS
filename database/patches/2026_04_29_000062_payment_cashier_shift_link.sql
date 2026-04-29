SET NAMES utf8mb4;

-- Link payment/refund rows to the cashier shift that authorized the money mutation.
-- Existing rows remain nullable so legacy reconciliation can fall back to the old time-window model.

DROP PROCEDURE IF EXISTS `sp_payment_cashier_shift_link`;

DELIMITER $$

CREATE PROCEDURE `sp_payment_cashier_shift_link`()
BEGIN
  DECLARE v_orphan_count BIGINT DEFAULT 0;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'payments'
       AND column_name = 'cashier_shift_id'
  ) THEN
    ALTER TABLE `payments`
      ADD COLUMN `cashier_shift_id` bigint unsigned DEFAULT NULL AFTER `branch_id`;
  END IF;

  SELECT COUNT(*) INTO v_orphan_count
    FROM `payments` p
    LEFT JOIN `cashier_shifts` cs ON cs.`cashier_shift_id` = p.`cashier_shift_id`
   WHERE p.`cashier_shift_id` IS NOT NULL
     AND cs.`cashier_shift_id` IS NULL;
  IF v_orphan_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot add payments.cashier_shift_id FK: orphan cashier shift rows exist';
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'payments'
       AND index_name = 'idx_payments__cashier_shift_id'
  ) THEN
    ALTER TABLE `payments`
      ADD KEY `idx_payments__cashier_shift_id` (`cashier_shift_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'payments'
       AND constraint_name = 'fk_payments__cashier_shift_id__cashier_shifts'
       AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `payments`
      ADD CONSTRAINT `fk_payments__cashier_shift_id__cashier_shifts`
      FOREIGN KEY (`cashier_shift_id`) REFERENCES `cashier_shifts` (`cashier_shift_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
  END IF;
END $$

DELIMITER ;

CALL `sp_payment_cashier_shift_link`();
DROP PROCEDURE IF EXISTS `sp_payment_cashier_shift_link`;
