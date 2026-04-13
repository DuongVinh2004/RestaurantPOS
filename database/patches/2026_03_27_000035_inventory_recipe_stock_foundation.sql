CREATE TABLE IF NOT EXISTS `ingredients` (
  `ingredient_id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unit',
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`ingredient_id`),
  UNIQUE KEY `uq_ingredients__code` (`code`),
  KEY `idx_ingredients__is_active__name` (`is_active`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `menu_item_recipes` (
  `recipe_line_id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int unsigned NOT NULL,
  `ingredient_id` int unsigned NOT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `unit_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`recipe_line_id`),
  UNIQUE KEY `uq_menu_item_recipes__item_id__ingredient_id` (`item_id`,`ingredient_id`),
  KEY `idx_menu_item_recipes__ingredient_id__item_id` (`ingredient_id`,`item_id`),
  CONSTRAINT `fk_menu_item_recipes__ingredient_id__ingredients` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`ingredient_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_menu_item_recipes__item_id__menu_items` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `chk_menu_item_recipes__quantity_positive` CHECK ((`quantity` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ingredient_stock_movements` (
  `movement_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ingredient_id` int unsigned NOT NULL,
  `movement_type` enum('StockIn','StockOut','AdjustmentIncrease','AdjustmentDecrease','Wastage') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_delta` decimal(14,3) NOT NULL,
  `unit_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`movement_id`),
  KEY `idx_ingredient_stock_movements__ingredient_id__created_at` (`ingredient_id`,`created_at`),
  UNIQUE KEY `uq_ingredient_stock_movements__reference` (`reference_type`,`reference_id`),
  CONSTRAINT `fk_ingredient_stock_movements__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_ingredient_stock_movements__ingredient_id__ingredients` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`ingredient_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_ingredient_stock_movements__quantity_delta_nonzero` CHECK ((`quantity_delta` <> 0)),
  CONSTRAINT `chk_ingredient_stock_movements__sign_matches_type` CHECK (((`movement_type` in ('StockIn','AdjustmentIncrease')) and (`quantity_delta` > 0)) or ((`movement_type` in ('StockOut','AdjustmentDecrease','Wastage')) and (`quantity_delta` < 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
