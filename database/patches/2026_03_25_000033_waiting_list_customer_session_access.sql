SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_waiting_list_customer_session_access`;
DELIMITER $$
CREATE PROCEDURE `sp_waiting_list_customer_session_access`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'customer_session_id'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD COLUMN `customer_session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND index_name = 'idx_waiting_list__customer_session_id__requested_at'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD KEY `idx_waiting_list__customer_session_id__requested_at` (`customer_session_id`, `requested_at`);
  END IF;
END $$
DELIMITER ;

CALL `sp_waiting_list_customer_session_access`();
DROP PROCEDURE IF EXISTS `sp_waiting_list_customer_session_access`;
