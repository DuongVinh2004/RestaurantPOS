SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_data_lifecycle_foundation`;
DELIMITER $$
CREATE PROCEDURE `sp_data_lifecycle_foundation`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'privacy_anonymized_at'
  ) THEN
    ALTER TABLE `users`
      ADD COLUMN `privacy_anonymized_at` datetime(6) DEFAULT NULL AFTER `is_deleted`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'customer_access_sessions'
      AND column_name = 'session_id'
  ) THEN
    ALTER TABLE `customer_access_sessions`
      ADD COLUMN `session_id` varchar(100) DEFAULT NULL AFTER `user_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'customer_access_sessions'
      AND column_name = 'guest_name'
  ) THEN
    ALTER TABLE `customer_access_sessions`
      ADD COLUMN `guest_name` varchar(200) DEFAULT NULL AFTER `session_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'customer_access_sessions'
      AND column_name = 'phone'
  ) THEN
    ALTER TABLE `customer_access_sessions`
      ADD COLUMN `phone` varchar(30) DEFAULT NULL AFTER `guest_name`;
  END IF;

  CREATE TABLE IF NOT EXISTS `customer_privacy_requests` (
    `customer_privacy_request_id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int unsigned NOT NULL,
    `request_type` varchar(30) NOT NULL,
    `status` varchar(30) NOT NULL,
    `requested_by_actor_type` varchar(40) DEFAULT NULL,
    `requested_by_user_id` int unsigned DEFAULT NULL,
    `requested_via` varchar(30) DEFAULT NULL,
    `reason` varchar(500) DEFAULT NULL,
    `reviewed_by` int unsigned DEFAULT NULL,
    `reviewed_at` datetime(6) DEFAULT NULL,
    `processed_at` datetime(6) DEFAULT NULL,
    `resolution_notes` varchar(500) DEFAULT NULL,
    `result_summary_json` json DEFAULT NULL,
    `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`customer_privacy_request_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'customer_privacy_requests'
      AND index_name = 'idx_customer_privacy_requests__user_id__status__created_at'
  ) THEN
    ALTER TABLE `customer_privacy_requests`
      ADD KEY `idx_customer_privacy_requests__user_id__status__created_at` (`user_id`,`status`,`created_at`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'customer_privacy_requests'
      AND index_name = 'idx_customer_privacy_requests__status__created_at'
  ) THEN
    ALTER TABLE `customer_privacy_requests`
      ADD KEY `idx_customer_privacy_requests__status__created_at` (`status`,`created_at`);
  END IF;
END $$
DELIMITER ;

CALL `sp_data_lifecycle_foundation`();
DROP PROCEDURE IF EXISTS `sp_data_lifecycle_foundation`;
