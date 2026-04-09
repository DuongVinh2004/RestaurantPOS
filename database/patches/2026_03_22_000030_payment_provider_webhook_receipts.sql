START TRANSACTION;

CREATE TABLE IF NOT EXISTS `payment_provider_webhook_receipts` (
  `payment_provider_webhook_receipt_id` int unsigned NOT NULL AUTO_INCREMENT,
  `provider_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_event_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_session_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_scope` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Received',
  `request_signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_headers_json` json DEFAULT NULL,
  `request_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `provider_payload_json` json DEFAULT NULL,
  `processed_at` datetime(6) DEFAULT NULL,
  `failure_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`payment_provider_webhook_receipt_id`),
  UNIQUE KEY `uq_payment_provider_webhook_receipts__provider_code__pr_122db085` (`provider_code`,`provider_event_code`),
  KEY `idx_payment_provider_webhook_receipts__provider_code__p_6117b764` (`provider_code`,`provider_session_code`),
  KEY `idx_payment_provider_webhook_receipts__delivery_status__878336e7` (`delivery_status`,`processed_at`),
  CONSTRAINT `chk_payment_provider_webhook_receipts__delivery_status` CHECK (`delivery_status` in ('Received','Applied','Ignored','Failed')),
  CONSTRAINT `chk_payment_provider_webhook_receipts__payment_scope` CHECK ((`payment_scope` is null) or (`payment_scope` in ('deposit','bill')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
