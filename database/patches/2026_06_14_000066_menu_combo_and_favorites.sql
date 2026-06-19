-- 2026_06_14_023746_add_combo_and_best_seller_to_menu_items_table
-- 2026_06_14_024836_add_combo_details_to_menu_items_table
ALTER TABLE `menu_items` 
ADD COLUMN `is_combo` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_available`,
ADD COLUMN `is_best_seller` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_combo`,
ADD COLUMN `compare_at_price_amount` DECIMAL(12, 2) NULL AFTER `is_best_seller`,
ADD COLUMN `serving_size` INT NULL AFTER `is_combo`,
ADD COLUMN `combo_items_json` JSON NULL AFTER `serving_size`;

-- 2026_06_14_031129_add_parent_order_item_id_to_reservation_order_items_table
ALTER TABLE `reservation_order_items` 
ADD COLUMN `parent_order_item_id` INT UNSIGNED NULL AFTER `order_id`;

ALTER TABLE `reservation_order_items` 
ADD CONSTRAINT `reservation_order_items_parent_order_item_id_foreign` FOREIGN KEY (`parent_order_item_id`) REFERENCES `reservation_order_items` (`order_item_id`) ON DELETE CASCADE;

-- 2026_06_14_022710_create_user_favorite_menu_items_table
CREATE TABLE IF NOT EXISTS `user_favorite_menu_items` (
    `favorite_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `menu_item_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `row_version` INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`favorite_id`),
    UNIQUE KEY `user_favorite_menu_items_user_id_menu_item_id_unique` (`user_id`, `menu_item_id`),
    CONSTRAINT `user_favorite_menu_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `user_favorite_menu_items_menu_item_id_foreign` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2026_06_14_031117_create_menu_item_combo_components_table
CREATE TABLE IF NOT EXISTS `menu_item_combo_components` (
    `combo_component_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `combo_item_id` INT UNSIGNED NOT NULL,
    `component_item_id` INT UNSIGNED NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`combo_component_id`),
    CONSTRAINT `menu_item_combo_components_combo_item_id_foreign` FOREIGN KEY (`combo_item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE,
    CONSTRAINT `menu_item_combo_components_component_item_id_foreign` FOREIGN KEY (`component_item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
