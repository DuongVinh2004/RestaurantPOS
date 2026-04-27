SET NAMES utf8mb4;

-- Restore finance-critical cashier shift user foreign keys for existing SQL-first deployments.
-- This patch fails closed on orphaned user references instead of rewriting production data.

DROP PROCEDURE IF EXISTS `sp_cashier_shift_user_fk_contract`;

DELIMITER $$

CREATE PROCEDURE `sp_cashier_shift_user_fk_contract`()
BEGIN
  DECLARE v_orphan_count BIGINT DEFAULT 0;

  SELECT COUNT(*) INTO v_orphan_count
    FROM `cashier_shifts` cs
    LEFT JOIN `users` u ON u.`user_id` = cs.`cashier_user_id`
   WHERE u.`user_id` IS NULL;
  IF v_orphan_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot add cashier_shifts.cashier_user_id FK: orphan user rows exist';
  END IF;

  SELECT COUNT(*) INTO v_orphan_count
    FROM `cashier_shifts` cs
    LEFT JOIN `users` u ON u.`user_id` = cs.`opened_by`
   WHERE cs.`opened_by` IS NOT NULL
     AND u.`user_id` IS NULL;
  IF v_orphan_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot add cashier_shifts.opened_by FK: orphan user rows exist';
  END IF;

  SELECT COUNT(*) INTO v_orphan_count
    FROM `cashier_shifts` cs
    LEFT JOIN `users` u ON u.`user_id` = cs.`closed_by`
   WHERE cs.`closed_by` IS NOT NULL
     AND u.`user_id` IS NULL;
  IF v_orphan_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot add cashier_shifts.closed_by FK: orphan user rows exist';
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'cashier_shifts'
       AND column_name = 'cashier_user_id'
       AND seq_in_index = 1
  ) THEN
    ALTER TABLE `cashier_shifts`
      ADD KEY `idx_cashier_shifts__cashier_user_id` (`cashier_user_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'cashier_shifts'
       AND column_name = 'opened_by'
       AND seq_in_index = 1
  ) THEN
    ALTER TABLE `cashier_shifts`
      ADD KEY `idx_cashier_shifts__opened_by` (`opened_by`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'cashier_shifts'
       AND column_name = 'closed_by'
       AND seq_in_index = 1
  ) THEN
    ALTER TABLE `cashier_shifts`
      ADD KEY `idx_cashier_shifts__closed_by` (`closed_by`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'cashier_shifts'
       AND constraint_name = 'fk_cashier_shifts__cashier_user_id__users'
       AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `cashier_shifts`
      ADD CONSTRAINT `fk_cashier_shifts__cashier_user_id__users`
      FOREIGN KEY (`cashier_user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'cashier_shifts'
       AND constraint_name = 'fk_cashier_shifts__opened_by__users'
       AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `cashier_shifts`
      ADD CONSTRAINT `fk_cashier_shifts__opened_by__users`
      FOREIGN KEY (`opened_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'cashier_shifts'
       AND constraint_name = 'fk_cashier_shifts__closed_by__users'
       AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `cashier_shifts`
      ADD CONSTRAINT `fk_cashier_shifts__closed_by__users`
      FOREIGN KEY (`closed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
  END IF;
END $$

DELIMITER ;

CALL `sp_cashier_shift_user_fk_contract`();
DROP PROCEDURE IF EXISTS `sp_cashier_shift_user_fk_contract`;
