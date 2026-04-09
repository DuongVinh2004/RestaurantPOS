SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS `trg_agent_assignments__single_active_bi`;
DROP TRIGGER IF EXISTS `trg_agent_assignments__single_active_bu`;
DROP TRIGGER IF EXISTS `trg_bank_accounts__single_default_bi`;
DROP TRIGGER IF EXISTS `trg_bank_accounts__single_default_bu`;

DROP PROCEDURE IF EXISTS `sp_staff_auth_and_integrity_hardening_hotfix`;
DELIMITER $$

CREATE PROCEDURE `sp_staff_auth_and_integrity_hardening_hotfix`()
BEGIN
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

CREATE TRIGGER `trg_agent_assignments__single_active_bi`
BEFORE INSERT ON `agent_assignments`
FOR EACH ROW
BEGIN
  IF NEW.`is_active` = 1 AND EXISTS (
    SELECT 1
    FROM `agent_assignments` a
    WHERE a.`conversation_id` = NEW.`conversation_id`
      AND a.`is_active` = 1
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Only one active agent assignment is allowed per conversation.';
  END IF;
END $$

CREATE TRIGGER `trg_agent_assignments__single_active_bu`
BEFORE UPDATE ON `agent_assignments`
FOR EACH ROW
BEGIN
  IF NEW.`is_active` = 1 AND EXISTS (
    SELECT 1
    FROM `agent_assignments` a
    WHERE a.`conversation_id` = NEW.`conversation_id`
      AND a.`is_active` = 1
      AND a.`assignment_id` <> OLD.`assignment_id`
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Only one active agent assignment is allowed per conversation.';
  END IF;
END $$

CREATE TRIGGER `trg_bank_accounts__single_default_bi`
BEFORE INSERT ON `bank_accounts`
FOR EACH ROW
BEGIN
  IF NEW.`is_default` = 1 AND EXISTS (
    SELECT 1
    FROM `bank_accounts` b
    WHERE b.`user_id` = NEW.`user_id`
      AND b.`is_default` = 1
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Only one default bank account is allowed per user.';
  END IF;
END $$

CREATE TRIGGER `trg_bank_accounts__single_default_bu`
BEFORE UPDATE ON `bank_accounts`
FOR EACH ROW
BEGIN
  IF NEW.`is_default` = 1 AND EXISTS (
    SELECT 1
    FROM `bank_accounts` b
    WHERE b.`user_id` = NEW.`user_id`
      AND b.`is_default` = 1
      AND b.`bank_account_id` <> OLD.`bank_account_id`
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Only one default bank account is allowed per user.';
  END IF;
END $$

DELIMITER ;

CALL `sp_staff_auth_and_integrity_hardening_hotfix`();
DROP PROCEDURE IF EXISTS `sp_staff_auth_and_integrity_hardening_hotfix`;
