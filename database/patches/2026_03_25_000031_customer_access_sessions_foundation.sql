START TRANSACTION;

CREATE TABLE IF NOT EXISTS `customer_access_sessions` (
  `access_session_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `token_last_eight` char(8) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `session_meta_json` json DEFAULT NULL,
  `expires_at` datetime(6) NOT NULL,
  `last_used_at` datetime(6) DEFAULT NULL,
  `revoked_at` datetime(6) DEFAULT NULL,
  `created_ip` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`access_session_id`),
  UNIQUE KEY `uq_customer_access_sessions__token_hash` (`token_hash`),
  KEY `idx_customer_access_sessions__user_id__expires_at` (`user_id`,`expires_at`),
  KEY `idx_customer_access_sessions__expires_at__revoked_at` (`expires_at`,`revoked_at`),
  CONSTRAINT `fk_customer_access_sessions__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `chk_customer_access_sessions__expires_future` CHECK ((`expires_at` > `created_at`)),
  CONSTRAINT `chk_customer_access_sessions__revoked_after_created` CHECK ((`revoked_at` is null) or (`revoked_at` >= `created_at`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
