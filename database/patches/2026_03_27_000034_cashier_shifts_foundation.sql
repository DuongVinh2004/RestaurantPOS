START TRANSACTION;

CREATE TABLE IF NOT EXISTS `cashier_shifts` (
  `cashier_shift_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shift_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cashier_user_id` int unsigned NOT NULL,
  `active_cashier_user_id` int unsigned GENERATED ALWAYS AS ((case when (`status` = 'Open') then `cashier_user_id` else NULL end)) STORED,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Open',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `terminal_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opening_float_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `expected_cash_amount` decimal(14,2) DEFAULT NULL,
  `actual_cash_amount` decimal(14,2) DEFAULT NULL,
  `cash_discrepancy_amount` decimal(14,2) DEFAULT NULL,
  `opened_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `closed_at` datetime(6) DEFAULT NULL,
  `opened_by` int unsigned DEFAULT NULL,
  `closed_by` int unsigned DEFAULT NULL,
  `opening_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closing_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`cashier_shift_id`),
  UNIQUE KEY `uq_cashier_shifts__shift_code` (`shift_code`),
  UNIQUE KEY `uq_cashier_shifts__active_cashier_user_id` (`active_cashier_user_id`),
  KEY `idx_cashier_shifts__cashier_user_id__status__opened_at` (`cashier_user_id`,`status`,`opened_at`),
  KEY `idx_cashier_shifts__status__opened_at` (`status`,`opened_at`),
  CONSTRAINT `fk_cashier_shifts__cashier_user_id__users` FOREIGN KEY (`cashier_user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_cashier_shifts__opened_by__users` FOREIGN KEY (`opened_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_cashier_shifts__closed_by__users` FOREIGN KEY (`closed_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_cashier_shifts__status` CHECK ((`status` in ('Open','Closed'))),
  CONSTRAINT `chk_cashier_shifts__money_nonneg` CHECK (((`opening_float_amount` >= 0) and ((`expected_cash_amount` is null) or (`expected_cash_amount` >= 0)) and ((`actual_cash_amount` is null) or (`actual_cash_amount` >= 0)))),
  CONSTRAINT `chk_cashier_shifts__open_close_state` CHECK ((((`status` <> 'Open') or (`closed_at` is null and `closed_by` is null and `expected_cash_amount` is null and `actual_cash_amount` is null and `cash_discrepancy_amount` is null)) and ((`status` <> 'Closed') or (`closed_at` is not null and `closed_by` is not null and `expected_cash_amount` is not null and `actual_cash_amount` is not null and `cash_discrepancy_amount` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
