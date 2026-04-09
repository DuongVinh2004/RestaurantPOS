SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_customer_reservation_deposit_self_service_intent`;
DELIMITER $$
CREATE PROCEDURE `sp_customer_reservation_deposit_self_service_intent`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'deposit_requirement_acknowledged_at'
  ) THEN
    ALTER TABLE `reservations`
      ADD COLUMN `deposit_requirement_acknowledged_at` datetime(6) DEFAULT NULL AFTER `deposit_status`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'deposit_intent_status'
  ) THEN
    ALTER TABLE `reservations`
      ADD COLUMN `deposit_intent_status` enum('None','Submitted','Revoked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'None' AFTER `deposit_requirement_acknowledged_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'deposit_intent_submitted_at'
  ) THEN
    ALTER TABLE `reservations`
      ADD COLUMN `deposit_intent_submitted_at` datetime(6) DEFAULT NULL AFTER `deposit_intent_status`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'deposit_intent_revoked_at'
  ) THEN
    ALTER TABLE `reservations`
      ADD COLUMN `deposit_intent_revoked_at` datetime(6) DEFAULT NULL AFTER `deposit_intent_submitted_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND constraint_name = 'chk_reservations__deposit_intent_status'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `reservations`
      ADD CONSTRAINT `chk_reservations__deposit_intent_status`
      CHECK ((`deposit_intent_status` in (_utf8mb4'None',_utf8mb4'Submitted',_utf8mb4'Revoked')));
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND constraint_name = 'chk_reservations__deposit_intent_submitted_timestamp'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `reservations`
      ADD CONSTRAINT `chk_reservations__deposit_intent_submitted_timestamp`
      CHECK (((`deposit_intent_status` <> _utf8mb4'Submitted') OR (`deposit_intent_submitted_at` IS NOT NULL)));
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND constraint_name = 'chk_reservations__deposit_intent_revoked_timestamp'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `reservations`
      ADD CONSTRAINT `chk_reservations__deposit_intent_revoked_timestamp`
      CHECK (((`deposit_intent_status` <> _utf8mb4'Revoked') OR (`deposit_intent_submitted_at` IS NOT NULL AND `deposit_intent_revoked_at` IS NOT NULL)));
  END IF;
END $$
DELIMITER ;

CALL `sp_customer_reservation_deposit_self_service_intent`();
DROP PROCEDURE IF EXISTS `sp_customer_reservation_deposit_self_service_intent`;
