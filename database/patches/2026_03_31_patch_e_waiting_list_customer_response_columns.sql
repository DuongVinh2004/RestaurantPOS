SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_patch_e_waiting_list_customer_response_columns`;
DELIMITER $$
CREATE PROCEDURE `sp_patch_e_waiting_list_customer_response_columns`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'customer_response_status'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD COLUMN `customer_response_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `notify_expires_at`;
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
END $$
DELIMITER ;

CALL `sp_patch_e_waiting_list_customer_response_columns`();
DROP PROCEDURE IF EXISTS `sp_patch_e_waiting_list_customer_response_columns`;
