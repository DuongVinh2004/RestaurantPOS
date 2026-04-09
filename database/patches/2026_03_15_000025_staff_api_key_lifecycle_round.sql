SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_staff_api_key_lifecycle_round`;
DELIMITER $$
CREATE PROCEDURE `sp_staff_api_key_lifecycle_round`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_api_keys'
  ) THEN
    CREATE TABLE `staff_api_keys` (
      `staff_api_key_id` bigint unsigned NOT NULL AUTO_INCREMENT,
      `user_id` int unsigned NOT NULL,
      `label` varchar(100) NOT NULL,
      `key_hash` char(64) NOT NULL,
      `last_used_at` datetime(6) DEFAULT NULL,
      `expires_at` datetime(6) DEFAULT NULL,
      `revoked_at` datetime(6) DEFAULT NULL,
      `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
      `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
      PRIMARY KEY (`staff_api_key_id`),
      UNIQUE KEY `uq_staff_api_keys__key_hash` (`key_hash`),
      KEY `idx_staff_api_keys__user_id__revoked_at__expires_at` (`user_id`, `revoked_at`, `expires_at`),
      CONSTRAINT `fk_staff_api_keys__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
      CONSTRAINT `chk_staff_api_keys__label_nonempty` CHECK (char_length(trim(`label`)) > 0)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_api_keys'
      AND column_name = 'label'
  ) THEN
    ALTER TABLE `staff_api_keys`
      ADD COLUMN `label` varchar(100) NOT NULL AFTER `user_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_api_keys'
      AND column_name = 'key_hash'
  ) THEN
    ALTER TABLE `staff_api_keys`
      ADD COLUMN `key_hash` char(64) NOT NULL AFTER `label`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_api_keys'
      AND column_name = 'last_used_at'
  ) THEN
    ALTER TABLE `staff_api_keys`
      ADD COLUMN `last_used_at` datetime(6) NULL AFTER `key_hash`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_api_keys'
      AND column_name = 'expires_at'
  ) THEN
    ALTER TABLE `staff_api_keys`
      ADD COLUMN `expires_at` datetime(6) NULL AFTER `last_used_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_api_keys'
      AND column_name = 'revoked_at'
  ) THEN
    ALTER TABLE `staff_api_keys`
      ADD COLUMN `revoked_at` datetime(6) NULL AFTER `expires_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_api_keys'
      AND column_name = 'created_at'
  ) THEN
    ALTER TABLE `staff_api_keys`
      ADD COLUMN `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) AFTER `revoked_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_api_keys'
      AND column_name = 'updated_at'
  ) THEN
    ALTER TABLE `staff_api_keys`
      ADD COLUMN `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) AFTER `created_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_api_keys'
      AND index_name = 'uq_staff_api_keys__key_hash'
  ) THEN
    ALTER TABLE `staff_api_keys`
      ADD UNIQUE KEY `uq_staff_api_keys__key_hash` (`key_hash`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_api_keys'
      AND index_name = 'idx_staff_api_keys__user_id__revoked_at__expires_at'
  ) THEN
    ALTER TABLE `staff_api_keys`
      ADD KEY `idx_staff_api_keys__user_id__revoked_at__expires_at` (`user_id`, `revoked_at`, `expires_at`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'staff_api_keys'
      AND constraint_name = 'chk_staff_api_keys__label_nonempty'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `staff_api_keys`
      ADD CONSTRAINT `chk_staff_api_keys__label_nonempty`
      CHECK (char_length(trim(`label`)) > 0);
  END IF;
END $$
DELIMITER ;

CALL `sp_staff_api_key_lifecycle_round`();
DROP PROCEDURE IF EXISTS `sp_staff_api_key_lifecycle_round`;
