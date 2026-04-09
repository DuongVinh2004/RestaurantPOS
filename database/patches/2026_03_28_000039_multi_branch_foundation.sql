CREATE TABLE IF NOT EXISTS `branches` (
  `branch_id` int unsigned NOT NULL AUTO_INCREMENT,
  `branch_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `is_default` tinyint unsigned NOT NULL DEFAULT '0',
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`branch_id`),
  UNIQUE KEY `uq_branches__branch_code` (`branch_code`),
  KEY `idx_branches__is_active__is_default__branch_name` (`is_active`,`is_default`,`branch_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `branches` (`branch_id`, `branch_code`, `branch_name`, `description`, `timezone`, `currency`, `is_active`, `is_default`, `row_version`, `created_at`, `updated_at`)
SELECT 1, 'MAIN', 'Main Branch', 'Single-site compatibility default branch.', 'UTC', 'VND', 1, 1, 1, CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6)
WHERE NOT EXISTS (
  SELECT 1 FROM `branches` WHERE `branch_code` = 'MAIN'
);

DROP PROCEDURE IF EXISTS `sp_multi_branch_foundation`;
DELIMITER $$
CREATE PROCEDURE `sp_multi_branch_foundation`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'ingredient_stock_movements'
      AND column_name = 'branch_id'
  ) THEN
    ALTER TABLE `ingredient_stock_movements`
      ADD COLUMN `branch_id` int unsigned NOT NULL DEFAULT '1' AFTER `ingredient_id`,
      ADD KEY `idx_ingredient_stock_movements__branch_id__ingredient_i_55caca95` (`branch_id`,`ingredient_id`,`created_at`),
      ADD CONSTRAINT `fk_ingredient_stock_movements__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'purchase_orders'
      AND column_name = 'branch_id'
  ) THEN
    ALTER TABLE `purchase_orders`
      ADD COLUMN `branch_id` int unsigned NOT NULL DEFAULT '1' AFTER `supplier_id`,
      ADD KEY `idx_purchase_orders__branch_id__status__created_at` (`branch_id`,`purchase_order_status`,`created_at`),
      ADD CONSTRAINT `fk_purchase_orders__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'purchase_receipts'
      AND column_name = 'branch_id'
  ) THEN
    ALTER TABLE `purchase_receipts`
      ADD COLUMN `branch_id` int unsigned NOT NULL DEFAULT '1' AFTER `purchase_order_id`,
      ADD KEY `idx_purchase_receipts__branch_id__received_at` (`branch_id`,`received_at`),
      ADD CONSTRAINT `fk_purchase_receipts__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND column_name = 'branch_id'
  ) THEN
    ALTER TABLE `payments`
      ADD COLUMN `branch_id` int unsigned NOT NULL DEFAULT '1' AFTER `reservation_id`,
      ADD KEY `idx_payments__branch_id__reservation_id__payment_type__status` (`branch_id`,`reservation_id`,`payment_type`,`status`),
      ADD CONSTRAINT `fk_payments__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'branch_id'
  ) THEN
    ALTER TABLE `reservations`
      ADD COLUMN `branch_id` int unsigned NOT NULL DEFAULT '1' AFTER `user_id`,
      ADD KEY `idx_reservations__branch_id__status__start_time__end_time` (`branch_id`,`status`,`start_time`,`end_time`),
      ADD CONSTRAINT `fk_reservations__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'restaurant_tables'
      AND column_name = 'branch_id'
  ) THEN
    ALTER TABLE `restaurant_tables`
      ADD COLUMN `branch_id` int unsigned NOT NULL DEFAULT '1' AFTER `table_code`,
      ADD KEY `idx_restaurant_tables__branch_id__status__zone` (`branch_id`,`status`,`zone`),
      ADD CONSTRAINT `fk_restaurant_tables__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'table_holds'
      AND column_name = 'branch_id'
  ) THEN
    ALTER TABLE `table_holds`
      ADD COLUMN `branch_id` int unsigned NOT NULL DEFAULT '1' AFTER `user_id`,
      ADD KEY `idx_table_holds__branch_id__status__expire_at__start_time` (`branch_id`,`hold_status`,`expire_at`,`start_time`),
      ADD CONSTRAINT `fk_table_holds__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'cashier_shifts'
      AND column_name = 'branch_id'
  ) THEN
    ALTER TABLE `cashier_shifts`
      ADD COLUMN `branch_id` int unsigned NOT NULL DEFAULT '1' AFTER `cashier_user_id`,
      ADD KEY `idx_cashier_shifts__branch_id__status__opened_at` (`branch_id`,`status`,`opened_at`),
      ADD CONSTRAINT `fk_cashier_shifts__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'branch_id'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD COLUMN `branch_id` int unsigned NOT NULL DEFAULT '1' AFTER `user_id`,
      ADD KEY `idx_waiting_list__branch_id__status__priority__requested_at` (`branch_id`,`status`,`priority`,`requested_at`),
      ADD CONSTRAINT `fk_waiting_list__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;
END $$
DELIMITER ;

CALL `sp_multi_branch_foundation`();
DROP PROCEDURE IF EXISTS `sp_multi_branch_foundation`;
