-- Create preorders table
CREATE TABLE `preorders` (
  `preorder_id` int unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` int unsigned NOT NULL,
  `customer_user_id` int unsigned DEFAULT NULL,
  `status` enum('draft','submitted','confirmed','rejected','cancelled','converted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` datetime(6) DEFAULT NULL,
  `confirmed_at` datetime(6) DEFAULT NULL,
  `rejected_at` datetime(6) DEFAULT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `converted_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`preorder_id`),
  UNIQUE KEY `idx_preorders__reservation_id` (`reservation_id`),
  KEY `fk_preorders__customer_user_id__users` (`customer_user_id`),
  CONSTRAINT `fk_preorders__reservation_id__reservations` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create preorder_items table
CREATE TABLE `preorder_items` (
  `preorder_item_id` int unsigned NOT NULL AUTO_INCREMENT,
  `preorder_id` int unsigned NOT NULL,
  `menu_item_id` int unsigned NOT NULL,
  `item_name_snapshot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price_snapshot` decimal(13,2) NOT NULL DEFAULT '0.00',
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `line_total_snapshot` decimal(13,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`preorder_item_id`),
  KEY `fk_preorder_items__preorder_id__preorders` (`preorder_id`),
  KEY `fk_preorder_items__menu_item_id__menu_items` (`menu_item_id`),
  CONSTRAINT `fk_preorder_items__preorder_id__preorders` FOREIGN KEY (`preorder_id`) REFERENCES `preorders` (`preorder_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
