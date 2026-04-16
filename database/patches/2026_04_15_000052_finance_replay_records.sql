CREATE TABLE IF NOT EXISTS `finance_replay_records` (
  `finance_replay_record_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scope` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aggregate_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aggregate_id` bigint unsigned NOT NULL,
  `idempotency_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_fingerprint` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result_id` bigint unsigned DEFAULT NULL,
  `context_json` json DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`finance_replay_record_id`),
  UNIQUE KEY `uq_finance_replay_records__scope_aggregate_key` (`scope`,`aggregate_type`,`aggregate_id`,`idempotency_key`),
  KEY `idx_finance_replay_records__idempotency_key` (`idempotency_key`),
  KEY `idx_finance_replay_records__result` (`result_type`,`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
