-- PATCH 16 — KDS / Printer Routing Foundation
-- additive-only: kitchen station/category routing + per-order-item kitchen tickets

CREATE TABLE IF NOT EXISTS `kitchen_stations` (
  `station_id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `output_mode` enum('KDS','Printer','Both') NOT NULL DEFAULT 'KDS',
  `printer_target` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`station_id`),
  UNIQUE KEY `uq_kitchen_stations__code` (`code`),
  KEY `idx_kitchen_stations__is_active__name` (`is_active`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kitchen_station_category_routes` (
  `route_id` int unsigned NOT NULL AUTO_INCREMENT,
  `station_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`route_id`),
  UNIQUE KEY `uq_kitchen_station_category_routes__category_id` (`category_id`),
  KEY `idx_kitchen_station_category_routes__station_id__is_act_baf6944e` (`station_id`,`is_active`,`sort_order`),
  CONSTRAINT `fk_kitchen_station_category_routes__category_id__menu_categories` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`category_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_kitchen_station_category_routes__station_id__kitchen_stations` FOREIGN KEY (`station_id`) REFERENCES `kitchen_stations` (`station_id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kitchen_order_item_tickets` (
  `ticket_id` int unsigned NOT NULL AUTO_INCREMENT,
  `station_id` int unsigned NOT NULL,
  `order_id` int unsigned NOT NULL,
  `reservation_id` int unsigned NOT NULL,
  `order_item_id` int unsigned NOT NULL,
  `item_id` int unsigned NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `route_id` int unsigned DEFAULT NULL,
  `route_source` enum('Category','Manual') NOT NULL DEFAULT 'Category',
  `output_mode` enum('KDS','Printer','Both') NOT NULL DEFAULT 'KDS',
  `printer_target` varchar(120) DEFAULT NULL,
  `ticket_status` enum('Queued','Fired','Ready','Completed','Cancelled') NOT NULL DEFAULT 'Queued',
  `first_dispatched_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `fired_at` datetime(6) DEFAULT NULL,
  `ready_at` datetime(6) DEFAULT NULL,
  `completed_at` datetime(6) DEFAULT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `last_recalled_at` datetime(6) DEFAULT NULL,
  `dispatch_count` int unsigned NOT NULL DEFAULT '1',
  `recall_count` int unsigned NOT NULL DEFAULT '0',
  `ticket_notes` varchar(500) DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`ticket_id`),
  UNIQUE KEY `uq_kitchen_order_item_tickets__order_item_id` (`order_item_id`),
  KEY `idx_kitchen_order_item_tickets__station_id__ticket_stat_45b5b743` (`station_id`,`ticket_status`,`ticket_id`),
  KEY `idx_kitchen_order_item_tickets__order_id__ticket_status` (`order_id`,`ticket_status`),
  KEY `idx_kitchen_order_item_tickets__reservation_id__ticket_status` (`reservation_id`,`ticket_status`),
  CONSTRAINT `fk_kitchen_order_item_tickets__category_id__menu_categories` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`category_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_kitchen_order_item_tickets__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_kitchen_order_item_tickets__item_id__menu_items` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_kitchen_order_item_tickets__order_id__reservation_orders` FOREIGN KEY (`order_id`) REFERENCES `reservation_orders` (`order_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_kitchen_order_item_tickets__order_item_id__reservati_39c3a948` FOREIGN KEY (`order_item_id`) REFERENCES `reservation_order_items` (`order_item_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_kitchen_order_item_tickets__reservation_id__reservations` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_kitchen_order_item_tickets__route_id__kitchen_statio_37e6acbc` FOREIGN KEY (`route_id`) REFERENCES `kitchen_station_category_routes` (`route_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_kitchen_order_item_tickets__station_id__kitchen_stations` FOREIGN KEY (`station_id`) REFERENCES `kitchen_stations` (`station_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_kitchen_order_item_tickets__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
