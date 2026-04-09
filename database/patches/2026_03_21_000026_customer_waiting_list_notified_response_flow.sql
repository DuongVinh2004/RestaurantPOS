SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_customer_waiting_list_notified_response_flow`;
DELIMITER $$
CREATE PROCEDURE `sp_customer_waiting_list_notified_response_flow`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'customer_response_status'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD COLUMN `customer_response_status` enum('Accepted','Declined') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `notify_expires_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'customer_responded_at'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD COLUMN `customer_responded_at` datetime(6) DEFAULT NULL AFTER `customer_response_status`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'customer_confirmed_arrival_at'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD COLUMN `customer_confirmed_arrival_at` datetime(6) DEFAULT NULL AFTER `customer_responded_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND constraint_name = 'chk_waiting_list__customer_response_requires_timestamp'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD CONSTRAINT `chk_waiting_list__customer_response_requires_timestamp`
      CHECK ((`customer_response_status` IS NULL) OR (`customer_responded_at` IS NOT NULL));
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND constraint_name = 'chk_waiting_list__customer_arrival_requires_accept'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD CONSTRAINT `chk_waiting_list__customer_arrival_requires_accept`
      CHECK ((`customer_confirmed_arrival_at` IS NULL) OR (`customer_response_status` = 'Accepted'));
  END IF;
END $$
DELIMITER ;

CALL `sp_customer_waiting_list_notified_response_flow`();
DROP PROCEDURE IF EXISTS `sp_customer_waiting_list_notified_response_flow`;
