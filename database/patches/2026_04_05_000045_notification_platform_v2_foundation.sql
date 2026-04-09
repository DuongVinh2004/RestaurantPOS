SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_notification_platform_v2_foundation`;
DELIMITER $$
CREATE PROCEDURE `sp_notification_platform_v2_foundation`()
BEGIN
  ALTER TABLE `notification_outbox`
    MODIFY COLUMN `channel` enum('SMS','Email','Zalo','Push','Webhook') NOT NULL;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'notification_outbox'
      AND column_name = 'recipient_user_id'
  ) THEN
    ALTER TABLE `notification_outbox`
      ADD COLUMN `recipient_user_id` int unsigned DEFAULT NULL AFTER `recipient`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'notification_outbox'
      AND column_name = 'dedupe_key'
  ) THEN
    ALTER TABLE `notification_outbox`
      ADD COLUMN `dedupe_key` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `idempotency_key`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'notification_outbox'
      AND column_name = 'last_attempted_at'
  ) THEN
    ALTER TABLE `notification_outbox`
      ADD COLUMN `last_attempted_at` datetime(6) DEFAULT NULL AFTER `attempt_count`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'notification_outbox'
      AND index_name = 'idx_notification_outbox__dedupe_key__created_at'
  ) THEN
    ALTER TABLE `notification_outbox`
      ADD KEY `idx_notification_outbox__dedupe_key__created_at` (`dedupe_key`,`created_at`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'notification_outbox'
      AND index_name = 'idx_notification_outbox__recipient_user_id__status__created_at'
  ) THEN
    ALTER TABLE `notification_outbox`
      ADD KEY `idx_notification_outbox__recipient_user_id__status__created_at` (`recipient_user_id`,`status`,`created_at`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'notification_outbox'
      AND index_name = 'fk_notification_outbox__recipient_user_id__users'
  ) THEN
    ALTER TABLE `notification_outbox`
      ADD KEY `fk_notification_outbox__recipient_user_id__users` (`recipient_user_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'notification_outbox'
      AND constraint_name = 'fk_notification_outbox__recipient_user_id__users'
      AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `notification_outbox`
      ADD CONSTRAINT `fk_notification_outbox__recipient_user_id__users`
      FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`user_id`)
      ON DELETE SET NULL ON UPDATE RESTRICT;
  END IF;

  CREATE TABLE IF NOT EXISTS `notification_delivery_attempts` (
    `attempt_id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `outbox_id` bigint unsigned NOT NULL,
    `channel` enum('SMS','Email','Zalo','Push','Webhook') NOT NULL,
    `provider_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `attempt_number` int unsigned NOT NULL,
    `status` enum('Succeeded','Failed','Suppressed') NOT NULL,
    `recipient` varchar(200) NOT NULL,
    `provider_message_id` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `provider_status` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `error_code` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `error_message` varchar(500) DEFAULT NULL,
    `request_payload_json` json DEFAULT NULL,
    `response_payload_json` json DEFAULT NULL,
    `attempted_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `completed_at` datetime(6) DEFAULT NULL,
    `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`attempt_id`),
    KEY `fk_notif_delivery_attempts__outbox_id__outbox` (`outbox_id`),
    KEY `idx_notif_delivery_attempts__status__attempted_at` (`status`,`attempted_at`),
    KEY `idx_notif_delivery_attempts__channel__status__attempted_at` (`channel`,`status`,`attempted_at`),
    KEY `idx_notif_delivery_attempts__provider_key__attempted_at` (`provider_key`,`attempted_at`),
    CONSTRAINT `fk_notif_delivery_attempts__outbox_id__outbox`
      FOREIGN KEY (`outbox_id`) REFERENCES `notification_outbox` (`outbox_id`)
      ON DELETE CASCADE ON UPDATE RESTRICT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

  CREATE TABLE IF NOT EXISTS `notification_preferences` (
    `notification_preference_id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int unsigned NOT NULL,
    `channel` enum('SMS','Email','Zalo','Push','Webhook') NOT NULL,
    `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
    `quiet_hours_start_minute` smallint unsigned DEFAULT NULL,
    `quiet_hours_end_minute` smallint unsigned DEFAULT NULL,
    `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`notification_preference_id`),
    UNIQUE KEY `uq_notification_preferences__user_id__channel` (`user_id`,`channel`),
    KEY `idx_notification_preferences__channel__is_enabled` (`channel`,`is_enabled`),
    CONSTRAINT `fk_notification_preferences__user_id__users`
      FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
      ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT `chk_notification_preferences__quiet_window`
      CHECK (((`quiet_hours_start_minute` IS NULL AND `quiet_hours_end_minute` IS NULL) OR (`quiet_hours_start_minute` <= 1439 AND `quiet_hours_end_minute` <= 1439)))
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
END $$
DELIMITER ;

CALL `sp_notification_platform_v2_foundation`();
DROP PROCEDURE IF EXISTS `sp_notification_platform_v2_foundation`;
