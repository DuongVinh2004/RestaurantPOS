DROP PROCEDURE IF EXISTS `sp_feature_flags_foundation`;
DELIMITER $$
CREATE PROCEDURE `sp_feature_flags_foundation`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'feature_flags'
  ) THEN
    CREATE TABLE `feature_flags` (
      `feature_flag_id` bigint unsigned NOT NULL AUTO_INCREMENT,
      `feature_key` varchar(120) NOT NULL,
      `environment` varchar(40) NOT NULL DEFAULT '*',
      `branch_id` int unsigned NOT NULL DEFAULT 0,
      `enabled` tinyint(1) NOT NULL DEFAULT 0,
      `reason` varchar(500) DEFAULT NULL,
      `updated_by` int unsigned DEFAULT NULL,
      `row_version` bigint unsigned NOT NULL DEFAULT 1,
      `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
      `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
      PRIMARY KEY (`feature_flag_id`),
      UNIQUE KEY `uq_feature_flags__feature_key__environment__branch_id` (`feature_key`,`environment`,`branch_id`),
      KEY `idx_feature_flags__environment__branch_id` (`environment`,`branch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  END IF;
END $$
DELIMITER ;

CALL `sp_feature_flags_foundation`();
DROP PROCEDURE IF EXISTS `sp_feature_flags_foundation`;
