SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_staff_auth_and_integrity_hardening`;
DELIMITER $$
CREATE PROCEDURE `sp_staff_auth_and_integrity_hardening`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'agent_assignments'
      AND column_name = 'active_conversation_id'
  ) THEN
    ALTER TABLE `agent_assignments`
      ADD COLUMN `active_conversation_id` char(36)
        GENERATED ALWAYS AS (
          CASE WHEN `is_active` = 1 THEN `conversation_id` ELSE NULL END
        ) STORED AFTER `is_active`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'agent_assignments'
      AND index_name = 'uq_agent_assignments__active_conversation_id'
  ) THEN
    ALTER TABLE `agent_assignments`
      ADD UNIQUE KEY `uq_agent_assignments__active_conversation_id` (`active_conversation_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'bank_accounts'
      AND column_name = 'default_user_id'
  ) THEN
    ALTER TABLE `bank_accounts`
      ADD COLUMN `default_user_id` int unsigned
        GENERATED ALWAYS AS (
          CASE WHEN `is_default` = 1 THEN `user_id` ELSE NULL END
        ) STORED AFTER `is_default`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'bank_accounts'
      AND index_name = 'uq_bank_accounts__default_user_id'
  ) THEN
    ALTER TABLE `bank_accounts`
      ADD UNIQUE KEY `uq_bank_accounts__default_user_id` (`default_user_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND constraint_name = 'chk_reservations__money_nonneg'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `reservations`
      ADD CONSTRAINT `chk_reservations__money_nonneg`
      CHECK (
        `deposit_required_amount` >= 0
        AND `deposit_paid_amount` >= 0
        AND `discount_amount` >= 0
        AND (`final_bill_amount` IS NULL OR `final_bill_amount` >= 0)
      );
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND constraint_name = 'chk_reservations__reserved_requires_checked_in_at'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `reservations`
      ADD CONSTRAINT `chk_reservations__reserved_requires_checked_in_at`
      CHECK (`status` <> 'Reserved' OR `checked_in_at` IS NOT NULL);
  END IF;
END $$
DELIMITER ;

CALL `sp_staff_auth_and_integrity_hardening`();
DROP PROCEDURE IF EXISTS `sp_staff_auth_and_integrity_hardening`;
