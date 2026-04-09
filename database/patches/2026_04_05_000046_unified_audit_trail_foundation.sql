SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_unified_audit_trail_foundation`;
DELIMITER $$
CREATE PROCEDURE `sp_unified_audit_trail_foundation`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'audit_logs'
      AND column_name = 'actor_type'
  ) THEN
    ALTER TABLE `audit_logs`
      ADD COLUMN `actor_type` varchar(40) DEFAULT NULL AFTER `actor_user_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'audit_logs'
      AND column_name = 'actor_key'
  ) THEN
    ALTER TABLE `audit_logs`
      ADD COLUMN `actor_key` varchar(120) DEFAULT NULL AFTER `actor_type`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'audit_logs'
      AND column_name = 'summary_json'
  ) THEN
    ALTER TABLE `audit_logs`
      ADD COLUMN `summary_json` json DEFAULT NULL AFTER `after_json`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'audit_logs'
      AND column_name = 'meta_json'
  ) THEN
    ALTER TABLE `audit_logs`
      ADD COLUMN `meta_json` json DEFAULT NULL AFTER `summary_json`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'audit_logs'
      AND column_name = 'request_id'
  ) THEN
    ALTER TABLE `audit_logs`
      ADD COLUMN `request_id` varchar(64) DEFAULT NULL AFTER `meta_json`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'audit_logs'
      AND index_name = 'idx_audit_logs__actor_type__created_at'
  ) THEN
    ALTER TABLE `audit_logs`
      ADD KEY `idx_audit_logs__actor_type__created_at` (`actor_type`,`created_at`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'audit_logs'
      AND index_name = 'idx_audit_logs__request_id'
  ) THEN
    ALTER TABLE `audit_logs`
      ADD KEY `idx_audit_logs__request_id` (`request_id`);
  END IF;

  CREATE TABLE IF NOT EXISTS `audit_log_subjects` (
    `audit_subject_id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `audit_id` bigint unsigned NOT NULL,
    `subject_type` varchar(50) NOT NULL,
    `subject_id` varchar(64) NOT NULL,
    `subject_role` varchar(32) DEFAULT NULL,
    `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`audit_subject_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'audit_log_subjects'
      AND index_name = 'idx_audit_log_subjects__subject_type__subject_id__audit_id'
  ) THEN
    ALTER TABLE `audit_log_subjects`
      ADD KEY `idx_audit_log_subjects__subject_type__subject_id__audit_id` (`subject_type`,`subject_id`,`audit_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'audit_log_subjects'
      AND index_name = 'idx_audit_log_subjects__audit_id'
  ) THEN
    ALTER TABLE `audit_log_subjects`
      ADD KEY `idx_audit_log_subjects__audit_id` (`audit_id`);
  END IF;
END $$
DELIMITER ;

CALL `sp_unified_audit_trail_foundation`();
DROP PROCEDURE IF EXISTS `sp_unified_audit_trail_foundation`;
