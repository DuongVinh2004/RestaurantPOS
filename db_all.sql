/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `agent_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_assignments` (
  `assignment_id` int unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `agent_user_id` int unsigned NOT NULL,
  `assigned_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `released_at` datetime(6) DEFAULT NULL,
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `active_conversation_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS ((case when (`is_active` = 1) then `conversation_id` else NULL end)) STORED,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`assignment_id`),
  KEY `fk_agent_assignments__agent_user_id__users` (`agent_user_id`),
  KEY `idx_agent_assignments__conversation_id__is_active` (`conversation_id`,`is_active`),
  UNIQUE KEY `uq_agent_assignments__active_conversation_id` (`active_conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `reporting_daily_sales_snapshots`;
CREATE TABLE `reporting_daily_sales_snapshots` (
  `snapshot_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int unsigned NOT NULL,
  `business_date` date NOT NULL,
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `billed_reservation_count` int unsigned NOT NULL DEFAULT '0',
  `billed_guest_count` int unsigned NOT NULL DEFAULT '0',
  `gross_bill_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `billed_total_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `invoice_issued_count` int unsigned NOT NULL DEFAULT '0',
  `invoiced_total_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `invoiced_tax_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `payment_row_count` int unsigned NOT NULL DEFAULT '0',
  `refund_row_count` int unsigned NOT NULL DEFAULT '0',
  `captured_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `refunded_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `net_paid_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `deposit_net_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `final_net_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `cashier_shift_closed_count` int unsigned NOT NULL DEFAULT '0',
  `cash_discrepancy_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `refreshed_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`snapshot_id`),
  UNIQUE KEY `uq_reporting_daily_sales_snapshots__branch_id__business_7ee7b06b` (`branch_id`,`business_date`,`currency`),
  KEY `idx_reporting_daily_sales_snapshots__business_date__branch_id` (`business_date`,`branch_id`),
  CONSTRAINT `chk_reporting_daily_sales_snapshots__money_nonneg` CHECK (((`gross_bill_amount` >= 0) and (`discount_amount` >= 0) and (`billed_total_amount` >= 0) and (`invoiced_total_amount` >= 0) and (`invoiced_tax_amount` >= 0) and (`captured_amount` >= 0) and (`refunded_amount` >= 0) and (`net_paid_amount` >= 0) and (`deposit_net_amount` >= 0) and (`final_net_amount` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reporting_daily_operation_snapshots`;
CREATE TABLE `reporting_daily_operation_snapshots` (
  `snapshot_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int unsigned NOT NULL,
  `business_date` date NOT NULL,
  `scheduled_reservation_count` int unsigned NOT NULL DEFAULT '0',
  `scheduled_guest_count` int unsigned NOT NULL DEFAULT '0',
  `scheduled_minutes_total` int unsigned NOT NULL DEFAULT '0',
  `checked_in_count` int unsigned NOT NULL DEFAULT '0',
  `completed_count` int unsigned NOT NULL DEFAULT '0',
  `cancelled_count` int unsigned NOT NULL DEFAULT '0',
  `no_show_count` int unsigned NOT NULL DEFAULT '0',
  `turn_count` int unsigned NOT NULL DEFAULT '0',
  `turn_minutes_total` int unsigned NOT NULL DEFAULT '0',
  `waiting_list_created_count` int unsigned NOT NULL DEFAULT '0',
  `waiting_list_notified_count` int unsigned NOT NULL DEFAULT '0',
  `waiting_list_seated_count` int unsigned NOT NULL DEFAULT '0',
  `waiting_list_cancelled_count` int unsigned NOT NULL DEFAULT '0',
  `waiting_list_confirmed_arrival_count` int unsigned NOT NULL DEFAULT '0',
  `refreshed_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`snapshot_id`),
  UNIQUE KEY `uq_reporting_daily_operation_snapshots__branch_id__business_date` (`branch_id`,`business_date`),
  KEY `idx_reporting_daily_operation_snapshots__business_date__80d8b18a` (`business_date`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reporting_daily_inventory_movement_snapshots`;
CREATE TABLE `reporting_daily_inventory_movement_snapshots` (
  `snapshot_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int unsigned NOT NULL,
  `business_date` date NOT NULL,
  `ingredient_id` int unsigned NOT NULL,
  `unit_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `movement_count` int unsigned NOT NULL DEFAULT '0',
  `purchase_receipt_movement_count` int unsigned NOT NULL DEFAULT '0',
  `stock_in_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `stock_out_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `adjustment_increase_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `adjustment_decrease_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `wastage_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `net_quantity_delta` decimal(14,3) NOT NULL DEFAULT '0.000',
  `last_movement_at` datetime(6) DEFAULT NULL,
  `refreshed_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`snapshot_id`),
  UNIQUE KEY `uq_reporting_daily_inventory_movement_snapshots__branch_63746fdf` (`branch_id`,`business_date`,`ingredient_id`,`unit_code`),
  KEY `idx_reporting_daily_inventory_movement_snapshots__busin_d77c75b1` (`business_date`,`branch_id`,`ingredient_id`),
  CONSTRAINT `chk_reporting_daily_inventory_movement_snapshots__quant_525e7578` CHECK (((`stock_in_quantity` >= 0) and (`stock_out_quantity` >= 0) and (`adjustment_increase_quantity` >= 0) and (`adjustment_decrease_quantity` >= 0) and (`wastage_quantity` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `audit_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` int unsigned DEFAULT NULL,
  `actor_type` varchar(40) DEFAULT NULL,
  `actor_key` varchar(120) DEFAULT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` varchar(64) NOT NULL,
  `action` varchar(50) NOT NULL,
  `before_json` json DEFAULT NULL,
  `after_json` json DEFAULT NULL,
  `summary_json` json DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `request_id` varchar(64) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`audit_id`),
  KEY `idx_audit_logs__entity_type__entity_id` (`entity_type`,`entity_id`),
  KEY `idx_audit_logs__actor_user_id__created_at` (`actor_user_id`,`created_at`),
  KEY `idx_audit_logs__actor_type__created_at` (`actor_type`,`created_at`),
  KEY `idx_audit_logs__action__created_at` (`action`,`created_at`),
  KEY `idx_audit_logs__request_id` (`request_id`),
  KEY `idx_audit_logs__created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_log_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log_subjects` (
  `audit_subject_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `audit_id` bigint unsigned NOT NULL,
  `subject_type` varchar(50) NOT NULL,
  `subject_id` varchar(64) NOT NULL,
  `subject_role` varchar(32) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`audit_subject_id`),
  KEY `idx_audit_log_subjects__subject_type__subject_id__audit_id` (`subject_type`,`subject_id`,`audit_id`),
  KEY `idx_audit_log_subjects__audit_id` (`audit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_accounts` (
  `bank_account_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `bank_account_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_holder_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint unsigned NOT NULL DEFAULT '0',
  `default_user_id` int unsigned GENERATED ALWAYS AS ((case when (`is_default` = 1) then `user_id` else NULL end)) STORED,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`bank_account_id`),
  UNIQUE KEY `uq_bank_accounts__user_id__bank_account_number` (`user_id`,`bank_account_number`),
  KEY `idx_bank_accounts__user_id` (`user_id`),
  UNIQUE KEY `uq_bank_accounts__default_user_id` (`default_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversation_aggregates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_aggregates` (
  `agg_id` int unsigned NOT NULL AUTO_INCREMENT,
  `agg_date` date NOT NULL,
  `hour` tinyint unsigned DEFAULT NULL,
  `channel` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_conversations` int unsigned NOT NULL DEFAULT '0',
  `total_messages` int unsigned NOT NULL DEFAULT '0',
  `total_spam` int unsigned NOT NULL DEFAULT '0',
  `orders_extracted` int unsigned NOT NULL DEFAULT '0',
  `top_items` json DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`agg_id`),
  UNIQUE KEY `uq_conversation_aggregates__agg_date__hour__channel` (`agg_date`,`hour`,`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversation_analyses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_analyses` (
  `analysis_id` int unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `analyzer_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_spam` tinyint unsigned NOT NULL DEFAULT '0',
  `quality_score` decimal(5,4) DEFAULT NULL,
  `extracted_info` json DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`analysis_id`),
  KEY `idx_conversation_analyses__conversation_id` (`conversation_id`),
  CONSTRAINT `chk_conversation_analyses__quality_score_range` CHECK (((`quality_score` is null) or ((`quality_score` >= 0) and (`quality_score` <= 1))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversation_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_events` (
  `event_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_by_user_id` int unsigned DEFAULT NULL,
  `event_data` json DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`event_id`),
  KEY `fk_conversation_events__event_by_user_id__users` (`event_by_user_id`),
  KEY `idx_conversation_events__conversation_id` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversation_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_files` (
  `file_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint unsigned NOT NULL,
  `file_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`file_id`),
  KEY `fk_conversation_files__message_id__conversation_messages` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversation_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_messages` (
  `message_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_id` int unsigned DEFAULT NULL,
  `message_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `is_internal_note` tinyint unsigned NOT NULL DEFAULT '0',
  `attachment_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `is_processed` tinyint unsigned NOT NULL DEFAULT '0',
  `processing_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confidence` decimal(5,4) DEFAULT NULL,
  `related_reservation_id` int unsigned DEFAULT NULL,
  `related_order_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `fk_conversation_messages__sender_id__users` (`sender_id`),
  KEY `fk_conversation_messages__related_reservation_id__reservations` (`related_reservation_id`),
  KEY `fk_conversation_messages__related_order_id__reservation_orders` (`related_order_id`),
  KEY `idx_conversation_messages__conversation_id__created_at` (`conversation_id`,`created_at`),
  KEY `idx_conv_msgs__conv_note_created_at` (`conversation_id`,`is_internal_note`,`created_at`),
  KEY `idx_conversation_messages__is_processed` (`is_processed`),
  CONSTRAINT `chk_conversation_messages__confidence_range` CHECK (((`confidence` is null) or ((`confidence` >= 0) and (`confidence` <= 1))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `conversation_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `customer_session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `channel` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WebChat',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Open',
  `intent_detected` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linked_reservation_id` int unsigned DEFAULT NULL,
  `linked_waiting_list_id` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `closed_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`conversation_id`),
  KEY `idx_conversations__user_id__status` (`user_id`,`status`),
  KEY `fk_conversations__branch_id__branches` (`branch_id`),
  KEY `fk_conversations__linked_reservation_id__reservations` (`linked_reservation_id`),
  KEY `fk_conversations__linked_waiting_list_id__waiting_list` (`linked_waiting_list_id`),
  KEY `idx_conversations__branch_id__status__created_at` (`branch_id`,`status`,`created_at`),
  KEY `idx_conversations__channel__created_at` (`channel`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_conversations__bi_uuid` BEFORE INSERT ON `conversations` FOR EACH ROW BEGIN
  IF NEW.`conversation_id` IS NULL OR NEW.`conversation_id` = '' THEN
    SET NEW.`conversation_id` = UUID();
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `loyalty_point_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_point_transactions` (
  `txn_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `reservation_id` int unsigned DEFAULT NULL,
  `txn_type` enum('Earn','Redeem','Adjust') NOT NULL,
  `points` bigint NOT NULL,
  `amount_basis` decimal(14,2) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'VND',
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `created_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`txn_id`),
  KEY `idx_lptx__user_id__created_at` (`user_id`,`created_at`),
  KEY `fk_lptx__created_by__users` (`created_by`),
  KEY `idx_lptx__reservation_id` (`reservation_id`),
  KEY `idx_lptx__txn_type__created_at` (`txn_type`,`created_at`),
  CONSTRAINT `chk_lptx__amount_basis_nonneg` CHECK (((`amount_basis` is null) or (`amount_basis` >= 0))),
  CONSTRAINT `chk_lptx__points_nonzero` CHECK ((`points` <> 0)),
  CONSTRAINT `chk_lptx__points_sign_by_type` CHECK ((((`txn_type` = _utf8mb4'Earn') and (`points` > 0)) or ((`txn_type` = _utf8mb4'Redeem') and (`points` < 0)) or ((`txn_type` = _utf8mb4'Adjust') and (`points` <> 0))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `loyalty_tiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_tiers` (
  `tier_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tier_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tier_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_points` bigint unsigned NOT NULL,
  `benefits_json` json DEFAULT NULL,
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`tier_id`),
  UNIQUE KEY `uq_loyalty_tiers__tier_code` (`tier_code`),
  KEY `idx_loyalty_tiers__is_active__min_points` (`is_active`,`min_points`),
  CONSTRAINT `chk_loyalty_tiers__min_points_nonneg` CHECK ((`min_points` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_loyalty_tiers__bi_row_version` BEFORE INSERT ON `loyalty_tiers` FOR EACH ROW BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_loyalty_tiers__bu_row_version` BEFORE UPDATE ON `loyalty_tiers` FOR EACH ROW BEGIN
    SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `menu_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_categories` (
  `category_id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_deleted` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_menu_categories__name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu_item_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_item_prices` (
  `price_id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int unsigned NOT NULL,
  `price` decimal(14,2) NOT NULL,
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `effective_from` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `effective_to` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`price_id`),
  KEY `idx_menu_item_prices__item_id__effective_from` (`item_id`,`effective_from`),
  CONSTRAINT `chk_menu_item_prices__price_nonneg` CHECK ((`price` >= 0)),
  CONSTRAINT `chk_menu_item_prices__range` CHECK (((`effective_to` is null) or (`effective_from` < `effective_to`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_items` (
  `item_id` int unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned DEFAULT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `img_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_available` tinyint unsigned NOT NULL DEFAULT '1',
  `is_preorder_enabled` tinyint unsigned NOT NULL DEFAULT '0',
  `preorder_quota_per_day` int unsigned DEFAULT NULL,
  `preorder_cutoff_minutes` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `uq_menu_items__code` (`code`),
  KEY `fk_menu_items__category_id__menu_categories` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_entities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;

DROP TABLE IF EXISTS `ingredients`;
CREATE TABLE `ingredients` (
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

DROP TABLE IF EXISTS `menu_item_recipes`;
CREATE TABLE `menu_item_recipes` (
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
  CONSTRAINT `chk_menu_item_recipes__quantity_positive` CHECK ((`quantity` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `branch_id` int unsigned NOT NULL AUTO_INCREMENT,
  `branch_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `business_hours` json DEFAULT NULL,
  `closure_windows` json DEFAULT NULL,
  `booking_policy` json DEFAULT NULL,
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `is_default` tinyint unsigned NOT NULL DEFAULT '0',
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`branch_id`),
  UNIQUE KEY `uq_branches__branch_code` (`branch_code`),
  KEY `idx_branches__is_active__is_default__branch_name` (`is_active`,`is_default`,`branch_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ingredient_stock_movements`;
CREATE TABLE `ingredient_stock_movements` (
  `movement_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ingredient_id` int unsigned NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '1',
  `movement_type` enum('StockIn','StockOut','AdjustmentIncrease','AdjustmentDecrease','Wastage') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_delta` decimal(14,3) NOT NULL,
  `unit_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`movement_id`),
  KEY `idx_ingredient_stock_movements__branch_id__ingredient_i_55caca95` (`branch_id`,`ingredient_id`,`created_at`),
  KEY `idx_ingredient_stock_movements__ingredient_id__created_at` (`ingredient_id`,`created_at`),
  KEY `idx_ingredient_stock_movements__reference` (`reference_type`,`reference_id`),
  CONSTRAINT `chk_ingredient_stock_movements__quantity_delta_nonzero` CHECK ((`quantity_delta` <> 0)),
  CONSTRAINT `chk_ingredient_stock_movements__sign_matches_type` CHECK (((`movement_type` in ('StockIn','AdjustmentIncrease')) and (`quantity_delta` > 0)) or ((`movement_type` in ('StockOut','AdjustmentDecrease','Wastage')) and (`quantity_delta` < 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `supplier_id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`supplier_id`),
  UNIQUE KEY `uq_suppliers__code` (`code`),
  KEY `idx_suppliers__is_active__name` (`is_active`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `purchase_order_id` int unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` int unsigned NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '1',
  `order_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_order_status` enum('Draft','Ordered','PartiallyReceived','Received','Cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `ordered_at` datetime(6) DEFAULT NULL,
  `expected_at` datetime(6) DEFAULT NULL,
  `received_at` datetime(6) DEFAULT NULL,
  `supplier_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`purchase_order_id`),
  UNIQUE KEY `uq_purchase_orders__order_code` (`order_code`),
  KEY `idx_purchase_orders__supplier_id__status__created_at` (`supplier_id`,`purchase_order_status`,`created_at`),
  KEY `idx_purchase_orders__branch_id__status__created_at` (`branch_id`,`purchase_order_status`,`created_at`),
  KEY `idx_purchase_orders__created_by` (`created_by`),
  KEY `idx_purchase_orders__updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_order_lines`;
CREATE TABLE `purchase_order_lines` (
  `po_line_id` int unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int unsigned NOT NULL,
  `ingredient_id` int unsigned NOT NULL,
  `ordered_quantity` decimal(14,3) NOT NULL,
  `received_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `unit_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_cost` decimal(14,3) DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`po_line_id`),
  UNIQUE KEY `uq_purchase_order_lines__purchase_order_id__ingredient_id` (`purchase_order_id`,`ingredient_id`),
  KEY `idx_purchase_order_lines__ingredient_id__purchase_order_id` (`ingredient_id`,`purchase_order_id`),
  CONSTRAINT `chk_purchase_order_lines__ordered_quantity_positive` CHECK ((`ordered_quantity` > 0)),
  CONSTRAINT `chk_purchase_order_lines__received_quantity_range` CHECK (((`received_quantity` >= 0) and (`received_quantity` <= `ordered_quantity`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_receipts`;
CREATE TABLE `purchase_receipts` (
  `receipt_id` int unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int unsigned NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '1',
  `receipt_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `receipt_status` enum('Posted','Voided') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Posted',
  `received_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `supplier_document_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`receipt_id`),
  UNIQUE KEY `uq_purchase_receipts__receipt_code` (`receipt_code`),
  KEY `idx_purchase_receipts__purchase_order_id__received_at` (`purchase_order_id`,`received_at`),
  KEY `idx_purchase_receipts__branch_id__received_at` (`branch_id`,`received_at`),
  KEY `idx_purchase_receipts__created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_receipt_lines`;
CREATE TABLE `purchase_receipt_lines` (
  `receipt_line_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `receipt_id` int unsigned NOT NULL,
  `purchase_order_line_id` int unsigned NOT NULL,
  `ingredient_id` int unsigned NOT NULL,
  `received_quantity` decimal(14,3) NOT NULL,
  `unit_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_cost` decimal(14,3) DEFAULT NULL,
  `stock_movement_id` bigint unsigned DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`receipt_line_id`),
  UNIQUE KEY `uq_purchase_receipt_lines__stock_movement_id` (`stock_movement_id`),
  KEY `idx_purchase_receipt_lines__purchase_order_line_id` (`purchase_order_line_id`),
  KEY `idx_purchase_receipt_lines__ingredient_id__receipt_id` (`ingredient_id`,`receipt_id`),
  CONSTRAINT `chk_purchase_receipt_lines__received_quantity_positive` CHECK ((`received_quantity` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `message_entities`;
CREATE TABLE `message_entities` (
  `message_entity_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint unsigned NOT NULL,
  `entity_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_text` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_normalized` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extra_json` json DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`message_entity_id`),
  KEY `fk_message_entities__message_id__conversation_messages` (`message_id`),
  KEY `idx_message_entities__entity_type` (`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_outbox` (
  `outbox_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel` enum('SMS','Email','Zalo','Push','Webhook') NOT NULL,
  `recipient` varchar(200) NOT NULL,
  `recipient_user_id` int unsigned DEFAULT NULL,
  `template_key` varchar(100) NOT NULL,
  `idempotency_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dedupe_key` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` json NOT NULL,
  `status` enum('Pending','Processing','Sent','Failed','Cancelled') NOT NULL DEFAULT 'Pending',
  `processing_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locked_until` datetime(6) DEFAULT NULL,
  `locked_by` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempt_count` int unsigned NOT NULL DEFAULT '0',
  `last_attempted_at` datetime(6) DEFAULT NULL,
  `next_retry_at` datetime(6) DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `related_reservation_id` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `sent_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`outbox_id`),
  UNIQUE KEY `uq_notification_outbox__idempotency_key` (`idempotency_key`),
  KEY `idx_notification_outbox__status__next_retry_at` (`status`,`next_retry_at`),
  KEY `idx_notification_outbox__related_reservation_id` (`related_reservation_id`),
  KEY `idx_notification_outbox__status__created_at` (`status`,`created_at`),
  KEY `idx_notification_outbox__channel__status__created_at` (`channel`,`status`,`created_at`),
  KEY `idx_notification_outbox__status__locked_until__next_retry_at` (`status`,`locked_until`,`next_retry_at`),
  KEY `idx_notification_outbox__dedupe_key__created_at` (`dedupe_key`,`created_at`),
  KEY `idx_notification_outbox__recipient_user_id__status__created_at` (`recipient_user_id`,`status`,`created_at`),
  KEY `fk_notification_outbox__recipient_user_id__users` (`recipient_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_delivery_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_delivery_attempts` (
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
  KEY `idx_notif_delivery_attempts__provider_key__attempted_at` (`provider_key`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_preferences` (
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
  CONSTRAINT `chk_notification_preferences__quiet_window` CHECK (((`quiet_hours_start_minute` is null and `quiet_hours_end_minute` is null) or (`quiet_hours_start_minute` <= 1439 and `quiet_hours_end_minute` <= 1439)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `payment_id` int unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` int unsigned NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '1',
  `refund_of_payment_id` int unsigned DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Other',
  `payment_type` enum('Deposit','Final','Refund') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Final',
  `status` enum('Pending','Partial','Success','Failed','Refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `transaction_code` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idempotency_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_response_json` json DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `uq_payments__idempotency_key` (`idempotency_key`),
  UNIQUE KEY `uq_payments__payment_provider__transaction_code` (`payment_provider`,`transaction_code`),
  KEY `fk_payments__reservation_id__reservations` (`reservation_id`),
  KEY `fk_payments__created_by__users` (`created_by`),
  KEY `idx_payments__reservation_id__payment_type__status` (`reservation_id`,`payment_type`,`status`),
  KEY `idx_payments__branch_id__reservation_id__payment_type__status` (`branch_id`,`reservation_id`,`payment_type`,`status`),
  KEY `idx_payments__refund_of_payment_id` (`refund_of_payment_id`),
  KEY `idx_payments__updated_by` (`updated_by`),
  CONSTRAINT `chk_payments__amount_nonneg` CHECK ((`amount` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `billing_invoices`;
CREATE TABLE `billing_invoices` (
  `billing_invoice_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` int unsigned NOT NULL,
  `invoice_number` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_status` enum('Issued','Voided') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Issued',
  `subtotal_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `tax_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_rate_percentage` decimal(6,3) NOT NULL DEFAULT '0.000',
  `prices_include_tax` tinyint(1) NOT NULL DEFAULT '1',
  `taxable_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `seller_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seller_tax_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seller_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_at` datetime(6) NOT NULL,
  `issued_by` int unsigned DEFAULT NULL,
  `voided_at` datetime(6) DEFAULT NULL,
  `voided_by` int unsigned DEFAULT NULL,
  `metadata_json` json DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`billing_invoice_id`),
  UNIQUE KEY `uq_billing_invoices__reservation_id` (`reservation_id`),
  UNIQUE KEY `uq_billing_invoices__invoice_number` (`invoice_number`),
  KEY `idx_billing_invoices__invoice_status__issued_at` (`invoice_status`,`issued_at`),
  KEY `idx_billing_invoices__issued_by` (`issued_by`),
  KEY `idx_billing_invoices__voided_by` (`voided_by`),
  CONSTRAINT `chk_billing_invoices__money_nonneg` CHECK (((`subtotal_amount` >= 0) and (`discount_amount` >= 0) and (`total_amount` >= 0) and (`taxable_amount` >= 0) and (`tax_amount` >= 0))),
  CONSTRAINT `chk_billing_invoices__summary_consistency` CHECK (((`subtotal_amount` >= `discount_amount`) and (`total_amount` = (`subtotal_amount` - `discount_amount`)) and ((round((`taxable_amount` + `tax_amount`),2)) = `total_amount`))),
  CONSTRAINT `chk_billing_invoices__void_state` CHECK ((((`invoice_status` <> 'Voided') or (`voided_at` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_payments__bi_row_version` BEFORE INSERT ON `payments` FOR EACH ROW BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_payments__bu_row_version` BEFORE UPDATE ON `payments` FOR EACH ROW BEGIN
    SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;


DROP TABLE IF EXISTS `reservation_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reservation_order_items` (
  `order_item_id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `item_id` int unsigned NOT NULL,
  `quantity` int unsigned NOT NULL,
  `unit_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `line_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `item_name_snapshot` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Ordered','InProgress','Served','Cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Ordered',
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`order_item_id`),
  KEY `fk_reservation_order_items__order_id__reservation_orders` (`order_id`),
  KEY `fk_reservation_order_items__item_id__menu_items` (`item_id`),
  KEY `idx_reservation_order_items__updated_by` (`updated_by`),
  CONSTRAINT `chk_reservation_order_items__qty_positive` CHECK ((`quantity` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_reservation_order_items__bi_row_version` BEFORE INSERT ON `reservation_order_items` FOR EACH ROW BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_reservation_order_items__bu_row_version` BEFORE UPDATE ON `reservation_order_items` FOR EACH ROW BEGIN
    SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `reservation_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reservation_orders` (
  `order_id` int unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` int unsigned NOT NULL,
  `order_type` enum('PreOrder','OnSpot') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PreOrder',
  `status` enum('Active','Cancelled','Completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  KEY `fk_reservation_orders__reservation_id__reservations` (`reservation_id`),
  KEY `fk_reservation_orders__created_by__users` (`created_by`),
  KEY `idx_reservation_orders__updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_reservation_orders__bi_row_version` BEFORE INSERT ON `reservation_orders` FOR EACH ROW BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_reservation_orders__bu_row_version` BEFORE UPDATE ON `reservation_orders` FOR EACH ROW BEGIN
    SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `reservation_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reservation_tables` (
  `reservation_table_id` int unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` int unsigned NOT NULL,
  `table_id` int unsigned NOT NULL,
  PRIMARY KEY (`reservation_table_id`),
  UNIQUE KEY `uq_reservation_tables__reservation_id__table_id` (`reservation_id`,`table_id`),
  KEY `idx_reservation_tables__table_id__reservation_id` (`table_id`,`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_reservation_tables__bi_prevent_overlap` BEFORE INSERT ON `reservation_tables` FOR EACH ROW BEGIN
    DECLARE v_start DATETIME(6);
    DECLARE v_end DATETIME(6);
    DECLARE v_status VARCHAR(20);
    DECLARE v_conflict_count BIGINT DEFAULT 0;

    SELECT `start_time`, `end_time`, `status`
      INTO v_start, v_end, v_status
      FROM `reservations`
     WHERE `reservation_id` = NEW.`reservation_id`
     LIMIT 1;

    IF v_status IN ('Confirmed', 'Reserved') THEN
        SELECT COUNT(*) INTO v_conflict_count
          FROM `reservation_tables` rt
          JOIN `reservations` r ON r.`reservation_id` = rt.`reservation_id`
         WHERE rt.`table_id` = NEW.`table_id`
           AND rt.`reservation_id` <> NEW.`reservation_id`
           AND r.`status` IN ('Confirmed', 'Reserved')
           AND r.`start_time` < v_end
           AND r.`end_time` > v_start;
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_tables overlap conflict with another active reservation';
        END IF;

        SELECT COUNT(*) INTO v_conflict_count
          FROM `table_hold_details` thd
          JOIN `table_holds` th ON th.`hold_id` = thd.`hold_id`
         WHERE thd.`table_id` = NEW.`table_id`
           AND th.`hold_status` IN ('Holding', 'Pending', 'Confirmed')
           AND (th.`hold_status` = 'Confirmed' OR th.`expire_at` > CURRENT_TIMESTAMP(6))
           AND th.`start_time` < v_end
           AND th.`end_time` > v_start
           AND (th.`confirmed_reservation_id` IS NULL OR th.`confirmed_reservation_id` <> NEW.`reservation_id`);
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_tables overlap conflict with active table hold';
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_reservation_tables__bu_prevent_overlap` BEFORE UPDATE ON `reservation_tables` FOR EACH ROW BEGIN
    DECLARE v_start DATETIME(6);
    DECLARE v_end DATETIME(6);
    DECLARE v_status VARCHAR(20);
    DECLARE v_conflict_count BIGINT DEFAULT 0;

    SELECT `start_time`, `end_time`, `status`
      INTO v_start, v_end, v_status
      FROM `reservations`
     WHERE `reservation_id` = NEW.`reservation_id`
     LIMIT 1;

    IF v_status IN ('Confirmed', 'Reserved') THEN
        SELECT COUNT(*) INTO v_conflict_count
          FROM `reservation_tables` rt
          JOIN `reservations` r ON r.`reservation_id` = rt.`reservation_id`
         WHERE rt.`table_id` = NEW.`table_id`
           AND rt.`reservation_id` <> NEW.`reservation_id`
           AND r.`status` IN ('Confirmed', 'Reserved')
           AND r.`start_time` < v_end
           AND r.`end_time` > v_start;
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_tables overlap conflict with another active reservation';
        END IF;

        SELECT COUNT(*) INTO v_conflict_count
          FROM `table_hold_details` thd
          JOIN `table_holds` th ON th.`hold_id` = thd.`hold_id`
         WHERE thd.`table_id` = NEW.`table_id`
           AND th.`hold_status` IN ('Holding', 'Pending', 'Confirmed')
           AND (th.`hold_status` = 'Confirmed' OR th.`expire_at` > CURRENT_TIMESTAMP(6))
           AND th.`start_time` < v_end
           AND th.`end_time` > v_start
           AND (th.`confirmed_reservation_id` IS NULL OR th.`confirmed_reservation_id` <> NEW.`reservation_id`);
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_tables overlap conflict with active table hold';
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reservations` (
  `reservation_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '1',
  `reservation_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reserved_at` datetime(6) DEFAULT NULL,
  `start_time` datetime(6) NOT NULL,
  `end_time` datetime(6) NOT NULL,
  `guest_count` int unsigned NOT NULL,
  `status` enum('Confirmed','Reserved','Cancelled','Expired','Completed','NoShow') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Confirmed',
  `source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Online',
  `checked_in_at` datetime(6) DEFAULT NULL,
  `checked_out_at` datetime(6) DEFAULT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `cancel_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_by` int unsigned DEFAULT NULL,
  `no_show_at` datetime(6) DEFAULT NULL,
  `deposit_required_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `deposit_paid_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `deposit_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NotRequired',
  `deposit_requirement_acknowledged_at` datetime(6) DEFAULT NULL,
  `deposit_intent_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'None',
  `deposit_intent_submitted_at` datetime(6) DEFAULT NULL,
  `deposit_intent_revoked_at` datetime(6) DEFAULT NULL,
  `applied_user_voucher_id` int unsigned DEFAULT NULL,
  `active_applied_user_voucher_id` int unsigned GENERATED ALWAYS AS ((case when (`status` in (_utf8mb4'Confirmed',_utf8mb4'Reserved')) then `applied_user_voucher_id` else NULL end)) STORED,
  `discount_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `final_bill_amount` decimal(14,2) DEFAULT NULL,
  `bill_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `billed_at` datetime(6) DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`reservation_id`),
  UNIQUE KEY `uq_reservations__reservation_code` (`reservation_code`),
  UNIQUE KEY `uq_reservations__active_applied_user_voucher_id` (`active_applied_user_voucher_id`),
  KEY `fk_reservations__user_id__users` (`user_id`),
  KEY `idx_reservations__start_time__end_time__status` (`start_time`,`end_time`,`status`),
  KEY `idx_reservations__status__start_time__end_time__reservation_id` (`status`,`start_time`,`end_time`,`reservation_id`),
  KEY `idx_reservations__branch_id__status__start_time__end_time` (`branch_id`,`status`,`start_time`,`end_time`),
  KEY `idx_reservations__status__checked_in_at` (`status`,`checked_in_at`),
  KEY `idx_reservations__applied_user_voucher_id` (`applied_user_voucher_id`),
  KEY `idx_reservations__created_by` (`created_by`),
  KEY `idx_reservations__updated_by` (`updated_by`),
  KEY `idx_reservations__cancelled_by` (`cancelled_by`),
  CONSTRAINT `chk_reservations__guest_count_positive` CHECK ((`guest_count` > 0)),
  CONSTRAINT `chk_reservations__terminal_timestamps` CHECK ((((`status` <> _utf8mb4'Cancelled') or (`cancelled_at` is not null)) and ((`status` <> _utf8mb4'Completed') or (`checked_out_at` is not null)) and ((`status` <> _utf8mb4'NoShow') or (`no_show_at` is not null)) and ((`billed_at` is null) or (`final_bill_amount` is not null)))),
  CONSTRAINT `chk_reservations__deposit_status` CHECK ((`deposit_status` in (_utf8mb4'NotRequired',_utf8mb4'Pending',_utf8mb4'Paid',_utf8mb4'Refunded',_utf8mb4'PartiallyRefunded',_utf8mb4'Forfeited'))),
  CONSTRAINT `chk_reservations__deposit_intent_status` CHECK ((`deposit_intent_status` in (_utf8mb4'None',_utf8mb4'Submitted',_utf8mb4'Revoked'))),
  CONSTRAINT `chk_reservations__deposit_intent_submitted_timestamp` CHECK (((`deposit_intent_status` <> _utf8mb4'Submitted') OR (`deposit_intent_submitted_at` IS NOT NULL))),
  CONSTRAINT `chk_reservations__deposit_intent_revoked_timestamp` CHECK (((`deposit_intent_status` <> _utf8mb4'Revoked') OR (`deposit_intent_submitted_at` IS NOT NULL AND `deposit_intent_revoked_at` IS NOT NULL))),
  CONSTRAINT `chk_reservations__money_nonneg` CHECK (((`deposit_required_amount` >= 0) and (`deposit_paid_amount` >= 0) and (`discount_amount` >= 0) and ((`final_bill_amount` is null) or (`final_bill_amount` >= 0)))),
  CONSTRAINT `chk_reservations__reserved_requires_checked_in_at` CHECK (((`status` <> _utf8mb4'Reserved') or (`checked_in_at` is not null))),
  CONSTRAINT `chk_reservations__time_range` CHECK ((`start_time` < `end_time`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_reservations__bi_row_version` BEFORE INSERT ON `reservations` FOR EACH ROW BEGIN
  IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
    SET NEW.`row_version` = 1;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_reservations__bu_row_version` BEFORE UPDATE ON `reservations` FOR EACH ROW BEGIN
  SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

DROP TABLE IF EXISTS `payment_provider_webhook_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_provider_webhook_receipts` (
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
  CONSTRAINT `chk_payment_provider_webhook_receipts__delivery_status` CHECK ((`delivery_status` in (_utf8mb4'Received',_utf8mb4'Applied',_utf8mb4'Ignored',_utf8mb4'Failed'))),
  CONSTRAINT `chk_payment_provider_webhook_receipts__payment_scope` CHECK (((`payment_scope` is null) or (`payment_scope` in (_utf8mb4'deposit',_utf8mb4'bill'))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reservation_deposit_payment_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reservation_deposit_payment_sessions` (
  `deposit_payment_session_id` int unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` int unsigned NOT NULL,
  `customer_user_id` int unsigned NOT NULL,
  `linked_payment_id` int unsigned DEFAULT NULL,
  `provider_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'simulated',
  `provider_session_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_payment_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `session_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Created',
  `settlement_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NotApplied',
  `failure_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_payload_json` json DEFAULT NULL,
  `idempotency_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_expires_at` datetime(6) DEFAULT NULL,
  `last_reconciled_at` datetime(6) DEFAULT NULL,
  `confirmed_at` datetime(6) DEFAULT NULL,
  `failed_at` datetime(6) DEFAULT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `expired_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`deposit_payment_session_id`),
  UNIQUE KEY `uq_reservation_deposit_payment_sessions__reservation_id_e59cee38` (`reservation_id`,`idempotency_key`),
  UNIQUE KEY `uq_reservation_deposit_payment_sessions__provider_code__5cbc608b` (`provider_code`,`provider_session_code`),
  UNIQUE KEY `uq_reservation_deposit_payment_sessions__linked_payment_id` (`linked_payment_id`),
  KEY `fk_reservation_deposit_payment_sessions__reservation_id_5da21e5a` (`reservation_id`),
  KEY `fk_reservation_deposit_payment_sessions__customer_user_id__users` (`customer_user_id`),
  KEY `idx_reservation_deposit_payment_sessions__reservation_i_5cc2e4f1` (`reservation_id`,`session_status`),
  KEY `idx_reservation_deposit_payment_sessions__customer_user_82307680` (`customer_user_id`,`created_at`),
  KEY `idx_reservation_deposit_payment_sessions__settlement_status` (`settlement_status`),
  KEY `idx_reservation_deposit_payment_sessions__updated_by` (`updated_by`),
  CONSTRAINT `chk_reservation_deposit_payment_sessions__money_nonneg` CHECK ((`amount` >= 0)),
  CONSTRAINT `chk_reservation_deposit_payment_sessions__session_status` CHECK ((`session_status` in (_utf8mb4'Created',_utf8mb4'Pending',_utf8mb4'Succeeded',_utf8mb4'Failed',_utf8mb4'Cancelled',_utf8mb4'Expired'))),
  CONSTRAINT `chk_reservation_deposit_payment_sessions__settlement_status` CHECK ((`settlement_status` in (_utf8mb4'NotApplied',_utf8mb4'Applied',_utf8mb4'Skipped')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

DROP TABLE IF EXISTS `reservation_bill_payment_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reservation_bill_payment_sessions` (
  `bill_payment_session_id` int unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` int unsigned NOT NULL,
  `order_id` int unsigned DEFAULT NULL,
  `customer_user_id` int unsigned NOT NULL,
  `linked_payment_id` int unsigned DEFAULT NULL,
  `provider_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'simulated',
  `provider_session_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_payment_code` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `session_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Created',
  `settlement_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NotApplied',
  `failure_code` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_payload_json` json DEFAULT NULL,
  `idempotency_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_expires_at` datetime(6) DEFAULT NULL,
  `last_reconciled_at` datetime(6) DEFAULT NULL,
  `confirmed_at` datetime(6) DEFAULT NULL,
  `failed_at` datetime(6) DEFAULT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `expired_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`bill_payment_session_id`),
  UNIQUE KEY `uq_reservation_bill_payment_sessions__reservation_id__i_2839be4a` (`reservation_id`,`idempotency_key`),
  UNIQUE KEY `uq_reservation_bill_payment_sessions__provider_code__pr_5c790f76` (`provider_code`,`provider_session_code`),
  UNIQUE KEY `uq_reservation_bill_payment_sessions__linked_payment_id` (`linked_payment_id`),
  KEY `fk_reservation_bill_payment_sessions__reservation_id__r_a8d6b53e` (`reservation_id`),
  KEY `fk_reservation_bill_payment_sessions__order_id__reserva_ce14f420` (`order_id`),
  KEY `fk_reservation_bill_payment_sessions__customer_user_id__users` (`customer_user_id`),
  KEY `idx_reservation_bill_payment_sessions__reservation_id___b47ad219` (`reservation_id`,`session_status`),
  KEY `idx_reservation_bill_payment_sessions__customer_user_id_69f85b0a` (`customer_user_id`,`created_at`),
  KEY `idx_reservation_bill_payment_sessions__settlement_status` (`settlement_status`),
  KEY `idx_reservation_bill_payment_sessions__updated_by` (`updated_by`),
  CONSTRAINT `chk_reservation_bill_payment_sessions__money_nonneg` CHECK ((`amount` >= 0)),
  CONSTRAINT `chk_reservation_bill_payment_sessions__session_status` CHECK ((`session_status` in (_utf8mb4'Created',_utf8mb4'Pending',_utf8mb4'Succeeded',_utf8mb4'Failed',_utf8mb4'Cancelled',_utf8mb4'Expired'))),
  CONSTRAINT `chk_reservation_bill_payment_sessions__settlement_status` CHECK ((`settlement_status` in (_utf8mb4'NotApplied',_utf8mb4'Applied',_utf8mb4'Skipped')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `restaurant_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurant_tables` (
  `table_id` int unsigned NOT NULL AUTO_INCREMENT,
  `table_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '1',
  `template_id` int unsigned DEFAULT NULL,
  `zone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pos_x` int DEFAULT NULL,
  `pos_y` int DEFAULT NULL,
  `status` enum('Available','Reserved','Occupied','Blocked','Maintenance') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Available',
  `description` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `is_deleted` tinyint unsigned NOT NULL DEFAULT '0',
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  `price` decimal(14,2) DEFAULT NULL,
  PRIMARY KEY (`table_id`),
  UNIQUE KEY `uq_restaurant_tables__table_code` (`table_code`),
  KEY `idx_restaurant_tables__branch_id__status__zone` (`branch_id`,`status`,`zone`),
  KEY `fk_restaurant_tables__template_id__table_templates` (`template_id`),
  CONSTRAINT `chk_restaurant_tables__price_nonneg` CHECK (((`price` is null) or (`price` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_restaurant_tables__bi_row_version` BEFORE INSERT ON `restaurant_tables` FOR EACH ROW BEGIN
  IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
    SET NEW.`row_version` = 1;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_restaurant_tables__bu_row_version` BEFORE UPDATE ON `restaurant_tables` FOR EACH ROW BEGIN
  SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `role_id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_roles__role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `value_json` json NOT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`setting_key`),
  KEY `fk_settings__updated_by__users` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feature_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `feature_flags` (
  `feature_flag_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `feature_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `environment` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '*',
  `branch_id` int unsigned NOT NULL DEFAULT '0',
  `enabled` tinyint unsigned NOT NULL DEFAULT '0',
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`feature_flag_id`),
  UNIQUE KEY `uq_feature_flags__feature_key__environment__branch_id` (`feature_key`,`environment`,`branch_id`),
  KEY `idx_feature_flags__environment__branch_id` (`environment`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `table_hold_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `table_hold_details` (
  `hold_detail_id` int unsigned NOT NULL AUTO_INCREMENT,
  `hold_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_id` int unsigned NOT NULL,
  PRIMARY KEY (`hold_detail_id`),
  UNIQUE KEY `uq_table_hold_details__hold_id__table_id` (`hold_id`,`table_id`),
  KEY `fk_table_hold_details__table_id__restaurant_tables` (`table_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_table_hold_details__bi_prevent_overlap` BEFORE INSERT ON `table_hold_details` FOR EACH ROW BEGIN
    DECLARE v_start DATETIME(6);
    DECLARE v_end DATETIME(6);
    DECLARE v_status VARCHAR(20);
    DECLARE v_expire_at DATETIME(6);
    DECLARE v_confirmed_reservation_id INT UNSIGNED;
    DECLARE v_conflict_count BIGINT DEFAULT 0;

    SELECT `start_time`, `end_time`, `hold_status`, `expire_at`, `confirmed_reservation_id`
      INTO v_start, v_end, v_status, v_expire_at, v_confirmed_reservation_id
      FROM `table_holds`
     WHERE `hold_id` = NEW.`hold_id`
     LIMIT 1;

    IF v_status IN ('Holding', 'Pending', 'Confirmed') AND (v_status = 'Confirmed' OR v_expire_at > CURRENT_TIMESTAMP(6)) THEN
        SELECT COUNT(*) INTO v_conflict_count
          FROM `reservation_tables` rt
          JOIN `reservations` r ON r.`reservation_id` = rt.`reservation_id`
         WHERE rt.`table_id` = NEW.`table_id`
           AND r.`status` IN ('Confirmed', 'Reserved')
           AND r.`start_time` < v_end
           AND r.`end_time` > v_start
           AND (v_confirmed_reservation_id IS NULL OR rt.`reservation_id` <> v_confirmed_reservation_id);
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'table_hold_details overlap conflict with active reservation';
        END IF;

        SELECT COUNT(*) INTO v_conflict_count
          FROM `table_hold_details` thd
          JOIN `table_holds` th ON th.`hold_id` = thd.`hold_id`
         WHERE thd.`table_id` = NEW.`table_id`
           AND thd.`hold_id` <> NEW.`hold_id`
           AND th.`hold_status` IN ('Holding', 'Pending', 'Confirmed')
           AND (th.`hold_status` = 'Confirmed' OR th.`expire_at` > CURRENT_TIMESTAMP(6))
           AND th.`start_time` < v_end
           AND th.`end_time` > v_start;
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'table_hold_details overlap conflict with another active hold';
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_table_hold_details__bu_prevent_overlap` BEFORE UPDATE ON `table_hold_details` FOR EACH ROW BEGIN
    DECLARE v_start DATETIME(6);
    DECLARE v_end DATETIME(6);
    DECLARE v_status VARCHAR(20);
    DECLARE v_expire_at DATETIME(6);
    DECLARE v_confirmed_reservation_id INT UNSIGNED;
    DECLARE v_conflict_count BIGINT DEFAULT 0;

    SELECT `start_time`, `end_time`, `hold_status`, `expire_at`, `confirmed_reservation_id`
      INTO v_start, v_end, v_status, v_expire_at, v_confirmed_reservation_id
      FROM `table_holds`
     WHERE `hold_id` = NEW.`hold_id`
     LIMIT 1;

    IF v_status IN ('Holding', 'Pending', 'Confirmed') AND (v_status = 'Confirmed' OR v_expire_at > CURRENT_TIMESTAMP(6)) THEN
        SELECT COUNT(*) INTO v_conflict_count
          FROM `reservation_tables` rt
          JOIN `reservations` r ON r.`reservation_id` = rt.`reservation_id`
         WHERE rt.`table_id` = NEW.`table_id`
           AND r.`status` IN ('Confirmed', 'Reserved')
           AND r.`start_time` < v_end
           AND r.`end_time` > v_start
           AND (v_confirmed_reservation_id IS NULL OR rt.`reservation_id` <> v_confirmed_reservation_id);
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'table_hold_details overlap conflict with active reservation';
        END IF;

        SELECT COUNT(*) INTO v_conflict_count
          FROM `table_hold_details` thd
          JOIN `table_holds` th ON th.`hold_id` = thd.`hold_id`
         WHERE thd.`table_id` = NEW.`table_id`
           AND thd.`hold_id` <> NEW.`hold_id`
           AND th.`hold_status` IN ('Holding', 'Pending', 'Confirmed')
           AND (th.`hold_status` = 'Confirmed' OR th.`expire_at` > CURRENT_TIMESTAMP(6))
           AND th.`start_time` < v_end
           AND th.`end_time` > v_start;
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'table_hold_details overlap conflict with another active hold';
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `table_holds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `table_holds` (
  `hold_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '1',
  `confirmed_reservation_id` int unsigned DEFAULT NULL,
  `start_time` datetime(6) NOT NULL,
  `end_time` datetime(6) NOT NULL,
  `duration_minutes` int unsigned NOT NULL DEFAULT '120',
  `hold_status` enum('Holding','Pending','Confirmed','Expired','Cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Holding',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `expire_at` datetime(6) NOT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  `active_session_hold_key` varchar(100) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS ((case when (`hold_status` in (_utf8mb4'Holding',_utf8mb4'Pending')) then `session_id` else NULL end)) STORED,
  PRIMARY KEY (`hold_id`),
  UNIQUE KEY `uq_table_holds__active_session_hold_key` (`active_session_hold_key`),
  KEY `fk_table_holds__user_id__users` (`user_id`),
  KEY `idx_table_holds__status__expire_at__start_time` (`hold_status`,`expire_at`,`start_time`),
  KEY `idx_table_holds__branch_id__status__expire_at__start_time` (`branch_id`,`hold_status`,`expire_at`,`start_time`),
  KEY `idx_table_holds__session_id__start_time__created_at` (`session_id`,`start_time`,`created_at`),
  KEY `idx_table_holds__session_id__confirmed_reservation_id` (`session_id`,`confirmed_reservation_id`),
  KEY `idx_table_holds__confirmed_reservation_id` (`confirmed_reservation_id`),
  KEY `idx_table_holds__updated_by` (`updated_by`),
  CONSTRAINT `chk_table_holds__time_range` CHECK ((`start_time` < `end_time`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_table_holds__bi_row_version` BEFORE INSERT ON `table_holds` FOR EACH ROW BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_table_holds__bi_defaults` BEFORE INSERT ON `table_holds` FOR EACH ROW BEGIN
  IF NEW.`hold_id` IS NULL OR NEW.`hold_id` = '' THEN
    SET NEW.`hold_id` = UUID();
  END IF;

  IF NEW.`created_at` IS NULL THEN
    SET NEW.`created_at` = CURRENT_TIMESTAMP(6);
  END IF;

  IF NEW.`duration_minutes` IS NULL OR NEW.`duration_minutes` <= 0 THEN
    IF NEW.`end_time` IS NOT NULL AND NEW.`start_time` IS NOT NULL AND NEW.`end_time` > NEW.`start_time` THEN
      SET NEW.`duration_minutes` = GREATEST(1, TIMESTAMPDIFF(MINUTE, NEW.`start_time`, NEW.`end_time`));
    ELSEIF NEW.`expire_at` IS NOT NULL AND NEW.`start_time` IS NOT NULL AND NEW.`expire_at` > NEW.`start_time` THEN
      SET NEW.`duration_minutes` = GREATEST(1, TIMESTAMPDIFF(MINUTE, NEW.`start_time`, NEW.`expire_at`));
    ELSE
      SET NEW.`duration_minutes` = 120;
    END IF;
  END IF;

  IF NEW.`end_time` IS NULL AND NEW.`start_time` IS NOT NULL THEN
    SET NEW.`end_time` = DATE_ADD(NEW.`start_time`, INTERVAL NEW.`duration_minutes` MINUTE);
  END IF;

  IF NEW.`end_time` IS NOT NULL AND NEW.`start_time` IS NOT NULL AND NEW.`end_time` <= NEW.`start_time` THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'table_holds end_time must be after start_time';
  END IF;

  IF NEW.`expire_at` IS NULL THEN
    SET NEW.`expire_at` = DATE_ADD(NEW.`created_at`, INTERVAL 5 MINUTE);
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_table_holds__bu_row_version` BEFORE UPDATE ON `table_holds` FOR EACH ROW BEGIN
    SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `table_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `table_templates` (
  `template_id` int unsigned NOT NULL AUTO_INCREMENT,
  `template_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seats` int unsigned NOT NULL DEFAULT '6',
  `description` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`template_id`),
  UNIQUE KEY `uq_table_templates__template_code` (`template_code`),
  CONSTRAINT `chk_table_templates__seats_positive` CHECK ((`seats` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_access_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_access_sessions` (
  `access_session_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  CONSTRAINT `chk_customer_access_sessions__expires_future` CHECK ((`expires_at` > `created_at`)),
  CONSTRAINT `chk_customer_access_sessions__revoked_after_created` CHECK ((`revoked_at` is null) or (`revoked_at` >= `created_at`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_privacy_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_privacy_requests` (
  `customer_privacy_request_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `request_type` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL,
  `requested_by_actor_type` varchar(40) DEFAULT NULL,
  `requested_by_user_id` int unsigned DEFAULT NULL,
  `requested_via` varchar(30) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` datetime(6) DEFAULT NULL,
  `processed_at` datetime(6) DEFAULT NULL,
  `resolution_notes` varchar(500) DEFAULT NULL,
  `result_summary_json` json DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`customer_privacy_request_id`),
  KEY `idx_customer_privacy_requests__user_id__status__created_at` (`user_id`,`status`,`created_at`),
  KEY `idx_customer_privacy_requests__status__created_at` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_auth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
DROP TABLE IF EXISTS `cashier_shifts`;
CREATE TABLE `cashier_shifts` (
  `cashier_shift_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shift_code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cashier_user_id` int unsigned NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '1',
  `active_cashier_user_id` int unsigned GENERATED ALWAYS AS ((case when (`status` = _utf8mb4'Open') then `cashier_user_id` else NULL end)) STORED,
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
  KEY `idx_cashier_shifts__branch_id__status__opened_at` (`branch_id`,`status`,`opened_at`),
  KEY `idx_cashier_shifts__status__opened_at` (`status`,`opened_at`),
  CONSTRAINT `chk_cashier_shifts__status` CHECK ((`status` in (_utf8mb4'Open',_utf8mb4'Closed'))),
  CONSTRAINT `chk_cashier_shifts__money_nonneg` CHECK (((`opening_float_amount` >= 0) and ((`expected_cash_amount` is null) or (`expected_cash_amount` >= 0)) and ((`actual_cash_amount` is null) or (`actual_cash_amount` >= 0)))),
  CONSTRAINT `chk_cashier_shifts__open_close_state` CHECK ((((`status` <> _utf8mb4'Open') or (`closed_at` is null and `closed_by` is null and `expected_cash_amount` is null and `actual_cash_amount` is null and `cash_discrepancy_amount` is null)) and ((`status` <> _utf8mb4'Closed') or (`closed_at` is not null and `closed_by` is not null and `expected_cash_amount` is not null and `actual_cash_amount` is not null and `cash_discrepancy_amount` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_auth_tokens`;
CREATE TABLE `user_auth_tokens` (
  `token_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `purpose` enum('PasswordReset','VerifyEmail','VerifyPhone') COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` enum('Email','SMS') COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `otp_hash` char(64) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `attempt_count` int unsigned NOT NULL DEFAULT '0',
  `max_attempts` int unsigned NOT NULL DEFAULT '5',
  `expires_at` datetime(6) NOT NULL,
  `used_at` datetime(6) DEFAULT NULL,
  `created_ip` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `uq_user_auth_tokens__token_hash` (`token_hash`),
  KEY `idx_user_auth_tokens__user_id__purpose__expires_at` (`user_id`,`purpose`,`expires_at`),
  KEY `idx_user_auth_tokens__purpose__recipient__expires_at` (`purpose`,`recipient`,`expires_at`),
  KEY `idx_user_auth_tokens__expires_at__used_at` (`expires_at`,`used_at`),
  CONSTRAINT `chk_user_auth_tokens__attempts_le_max` CHECK ((`attempt_count` <= `max_attempts`)),
  CONSTRAINT `chk_user_auth_tokens__expires_future` CHECK ((`expires_at` > `created_at`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_points` (
  `user_id` int unsigned NOT NULL,
  `total_points` bigint unsigned NOT NULL DEFAULT '0',
  `last_updated` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `updated_by` int unsigned DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`user_id`),
  KEY `idx_user_points__updated_by` (`updated_by`),
  CONSTRAINT `chk_user_points__total_points_nonneg` CHECK ((`total_points` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_user_points__bi_row_version` BEFORE INSERT ON `user_points` FOR EACH ROW BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_user_points__bu_row_version` BEFORE UPDATE ON `user_points` FOR EACH ROW BEGIN
    SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `user_tier_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_tier_history` (
  `history_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `from_tier_id` int unsigned DEFAULT NULL,
  `to_tier_id` int unsigned NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`history_id`),
  KEY `idx_user_tier_history__user_id__effective_at` (`user_id`,`effective_at`),
  KEY `idx_user_tier_history__to_tier_id__effective_at` (`to_tier_id`,`effective_at`),
  KEY `idx_user_tier_history__created_by` (`created_by`),
  KEY `fk_user_tier_history__from_tier_id__loyalty_tiers` (`from_tier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_vouchers` (
  `user_voucher_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `voucher_id` int unsigned NOT NULL,
  `assigned_date` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `is_used` tinyint unsigned NOT NULL DEFAULT '0',
  `used_date` datetime(6) DEFAULT NULL,
  `used_reservation_id` int unsigned DEFAULT NULL,
  `used_amount` decimal(14,2) DEFAULT NULL,
  `lock_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locked_until` datetime(6) DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`user_voucher_id`),
  UNIQUE KEY `uq_user_vouchers__user_id__voucher_id` (`user_id`,`voucher_id`),
  KEY `fk_user_vouchers__voucher_id__vouchers` (`voucher_id`),
  KEY `idx_user_vouchers__voucher_id__is_used__user_id` (`voucher_id`,`is_used`,`user_id`),
  KEY `idx_user_vouchers__user_id__is_used` (`user_id`,`is_used`),
  KEY `idx_user_vouchers__lock_token__locked_until` (`lock_token`,`locked_until`),
  KEY `idx_user_vouchers__used_reservation_id` (`used_reservation_id`),
  KEY `idx_user_vouchers__updated_by` (`updated_by`),
  KEY `idx_user_vouchers__created_by` (`created_by`),
  CONSTRAINT `chk_user_vouchers__lock_pair` CHECK ((((`lock_token` is null) and (`locked_until` is null)) or ((`lock_token` is not null) and (`locked_until` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_user_vouchers__bi_validate_state` BEFORE INSERT ON `user_vouchers` FOR EACH ROW BEGIN
    IF NOT (
        (NEW.`is_used` = 0 AND NEW.`used_date` IS NULL AND NEW.`used_reservation_id` IS NULL AND NEW.`used_amount` IS NULL)
        OR
        (NEW.`is_used` = 1 AND NEW.`used_date` IS NOT NULL AND NEW.`used_reservation_id` IS NOT NULL AND NEW.`used_amount` IS NOT NULL)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_vouchers used-state invariant violated';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_user_vouchers__bi_row_version` BEFORE INSERT ON `user_vouchers` FOR EACH ROW BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_user_vouchers__bu_validate_state` BEFORE UPDATE ON `user_vouchers` FOR EACH ROW BEGIN
    IF NOT (
        (NEW.`is_used` = 0 AND NEW.`used_date` IS NULL AND NEW.`used_reservation_id` IS NULL AND NEW.`used_amount` IS NULL)
        OR
        (NEW.`is_used` = 1 AND NEW.`used_date` IS NOT NULL AND NEW.`used_reservation_id` IS NOT NULL AND NEW.`used_amount` IS NOT NULL)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_vouchers used-state invariant violated';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_user_vouchers__bu_row_version` BEFORE UPDATE ON `user_vouchers` FOR EACH ROW BEGIN
    SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` int unsigned NOT NULL,
  `current_tier_id` int unsigned DEFAULT NULL,
  `language_pref` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vn',
  `is_deleted` tinyint unsigned NOT NULL DEFAULT '0',
  `privacy_anonymized_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users__username` (`username`),
  UNIQUE KEY `uq_users__email` (`email`),
  UNIQUE KEY `uq_users__phone` (`phone`),
  KEY `fk_users__role_id__roles` (`role_id`),
  KEY `idx_users__current_tier_id` (`current_tier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_users__bi_row_version` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
  IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
    SET NEW.`row_version` = 1;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_users__bu_row_version` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
  SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

DROP TABLE IF EXISTS `staff_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_api_keys` (
  `staff_api_key_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `label` varchar(100) NOT NULL,
  `key_hash` char(64) NOT NULL,
  `last_used_at` datetime(6) DEFAULT NULL,
  `expires_at` datetime(6) DEFAULT NULL,
  `revoked_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`staff_api_key_id`),
  UNIQUE KEY `uq_staff_api_keys__key_hash` (`key_hash`),
  KEY `idx_staff_api_keys__user_id__revoked_at__expires_at` (`user_id`,`revoked_at`,`expires_at`),
  CONSTRAINT `chk_staff_api_keys__label_nonempty` CHECK ((char_length(trim(`label`)) > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vouchers` (
  `voucher_id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_type` enum('Fixed','Percent','FreeItem') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` decimal(14,2) DEFAULT NULL,
  `free_item_id` int unsigned DEFAULT NULL,
  `free_item_qty` int unsigned DEFAULT NULL,
  `max_usage` int unsigned DEFAULT NULL,
  `max_usage_per_user` int unsigned DEFAULT NULL,
  `min_spend` decimal(14,2) NOT NULL DEFAULT '0.00',
  `start_date` datetime(6) DEFAULT NULL,
  `expiry_date` datetime(6) DEFAULT NULL,
  `is_active` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`voucher_id`),
  UNIQUE KEY `uq_vouchers__code` (`code`),
  KEY `idx_vouchers__free_item_id` (`free_item_id`),
  KEY `idx_vouchers__created_by` (`created_by`),
  KEY `idx_vouchers__updated_by` (`updated_by`),
  CONSTRAINT `chk_vouchers__money_nonneg` CHECK ((((`discount_value` is null) or (`discount_value` >= 0)) and (`min_spend` >= 0) and ((`max_usage` is null) or (`max_usage` >= 0)) and ((`max_usage_per_user` is null) or (`max_usage_per_user` >= 0)))),
  CONSTRAINT `chk_vouchers__percent_range` CHECK (((`discount_type` <> _utf8mb4'Percent') or ((`discount_value` is not null) and (`discount_value` between 0 and 100))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_vouchers__bi_row_version` BEFORE INSERT ON `vouchers` FOR EACH ROW BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_vouchers__bu_row_version` BEFORE UPDATE ON `vouchers` FOR EACH ROW BEGIN
    SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `waiting_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `waiting_list` (
  `waiting_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `customer_session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT '1',
  `guest_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_count` int unsigned NOT NULL,
  `requested_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `status` enum('Waiting','Notified','Seated','Cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Waiting',
  `priority` int NOT NULL DEFAULT '0',
  `notified_at` datetime(6) DEFAULT NULL,
  `notify_expires_at` datetime(6) DEFAULT NULL,
  `customer_response_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_responded_at` datetime(6) DEFAULT NULL,
  `customer_confirmed_arrival_at` datetime(6) DEFAULT NULL,
  `notified_by` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `seated_at` datetime(6) DEFAULT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `cancel_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `row_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`waiting_id`),
  KEY `fk_waiting_list__user_id__users` (`user_id`),
  KEY `idx_waiting_list__customer_session_id__requested_at` (`customer_session_id`,`requested_at`),
  KEY `idx_waiting_list__status__created_at` (`status`,`created_at`),
  KEY `idx_waiting_list__status__priority__requested_at` (`status`,`priority`,`requested_at`),
  KEY `idx_waiting_list__branch_id__status__priority__requested_at` (`branch_id`,`status`,`priority`,`requested_at`),
  KEY `idx_waiting_list__notify_expires_at` (`notify_expires_at`),
  KEY `idx_waiting_list__notified_by` (`notified_by`),
  KEY `idx_waiting_list__updated_by` (`updated_by`),
  CONSTRAINT `chk_waiting_list__guest_count_positive` CHECK ((`guest_count` > 0)),
  CONSTRAINT `chk_waiting_list__status_notified_requires_window` CHECK (((`status` <> 'Notified') OR (`notified_at` IS NOT NULL AND `notify_expires_at` IS NOT NULL))),
  CONSTRAINT `chk_waiting_list__status_seated_requires_timestamp` CHECK (((`status` <> 'Seated') OR (`seated_at` IS NOT NULL))),
  CONSTRAINT `chk_waiting_list__status_cancelled_requires_timestamp` CHECK (((`status` <> 'Cancelled') OR (`cancelled_at` IS NOT NULL))),
  CONSTRAINT `chk_waiting_list__customer_response_requires_timestamp` CHECK (((`customer_response_status` IS NULL) OR (`customer_responded_at` IS NOT NULL))),
  CONSTRAINT `chk_waiting_list__customer_arrival_requires_accept` CHECK (((`customer_confirmed_arrival_at` IS NULL) OR (`customer_response_status` = 'Accepted')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_waiting_list__bi_row_version` BEFORE INSERT ON `waiting_list` FOR EACH ROW BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_waiting_list__bu_row_version` BEFORE UPDATE ON `waiting_list` FOR EACH ROW BEGIN
    SET NEW.`row_version` = OLD.`row_version` + 1;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `kitchen_stations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kitchen_stations` (
  `station_id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_mode` enum('KDS','Printer','Both') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'KDS',
  `printer_target` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`station_id`),
  UNIQUE KEY `uq_kitchen_stations__code` (`code`),
  KEY `idx_kitchen_stations__is_active__name` (`is_active`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kitchen_station_category_routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kitchen_station_category_routes` (
  `route_id` int unsigned NOT NULL AUTO_INCREMENT,
  `station_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`route_id`),
  UNIQUE KEY `uq_kitchen_station_category_routes__category_id` (`category_id`),
  KEY `idx_kitchen_station_category_routes__station_id__is_act_baf6944e` (`station_id`,`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kitchen_order_item_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kitchen_order_item_tickets` (
  `ticket_id` int unsigned NOT NULL AUTO_INCREMENT,
  `station_id` int unsigned NOT NULL,
  `order_id` int unsigned NOT NULL,
  `reservation_id` int unsigned NOT NULL,
  `order_item_id` int unsigned NOT NULL,
  `item_id` int unsigned NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `route_id` int unsigned DEFAULT NULL,
  `route_source` enum('Category','Manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Category',
  `output_mode` enum('KDS','Printer','Both') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'KDS',
  `printer_target` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_status` enum('Queued','Fired','Ready','Completed','Cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Queued',
  `first_dispatched_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `fired_at` datetime(6) DEFAULT NULL,
  `ready_at` datetime(6) DEFAULT NULL,
  `completed_at` datetime(6) DEFAULT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `last_recalled_at` datetime(6) DEFAULT NULL,
  `dispatch_count` int unsigned NOT NULL DEFAULT '1',
  `recall_count` int unsigned NOT NULL DEFAULT '0',
  `ticket_notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`ticket_id`),
  UNIQUE KEY `uq_kitchen_order_item_tickets__order_item_id` (`order_item_id`),
  KEY `idx_kitchen_order_item_tickets__station_id__ticket_stat_45b5b743` (`station_id`,`ticket_status`,`ticket_id`),
  KEY `idx_kitchen_order_item_tickets__order_id__ticket_status` (`order_id`,`ticket_status`),
  KEY `idx_kitchen_order_item_tickets__reservation_id__ticket_status` (`reservation_id`,`ticket_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 DROP PROCEDURE IF EXISTS `sp_cleanup_expired_holds` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE PROCEDURE `sp_cleanup_expired_holds`()
BEGIN
  UPDATE `table_holds`
  SET `hold_status` = 'Expired'
  WHERE `hold_status` IN ('Holding','Pending')
    AND `expire_at` <= UTC_TIMESTAMP(6);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
-- Deferred foreign key creation to make schema import-safe for fresh MySQL test databases.
-- The following tables contain STORED generated columns; MySQL rejects adding foreign keys on these tables during schema import in this snapshot.
-- Omitted deferred foreign keys:
--   agent_assignments.fk_agent_assignments__agent_user_id__users
--   agent_assignments.fk_agent_assignments__conversation_id__conversations
--   bank_accounts.fk_bank_accounts__user_id__users
--   reservations.fk_reservations__applied_user_voucher_id__user_vouchers
--   reservations.fk_reservations__branch_id__branches
--   reservations.fk_reservations__cancelled_by__users
--   reservations.fk_reservations__created_by__users
--   reservations.fk_reservations__updated_by__users
--   reservations.fk_reservations__user_id__users
--   table_holds.fk_table_holds__branch_id__branches
--   table_holds.fk_table_holds__confirmed_reservation_id__reservations
--   table_holds.fk_table_holds__updated_by__users
--   table_holds.fk_table_holds__user_id__users
--   cashier_shifts.fk_cashier_shifts__branch_id__branches
--   cashier_shifts.fk_cashier_shifts__cashier_user_id__users
--   cashier_shifts.fk_cashier_shifts__opened_by__users
--   cashier_shifts.fk_cashier_shifts__closed_by__users

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2026_03_13_000000_booking_schema_baseline_guard',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2026_03_13_000001_booking_hardening_round',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2026_03_13_000002_booking_overlap_guards',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_03_14_000003_booking_schema_alignment',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_03_14_000004_drop_patch_helper_procedures',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_03_14_000005_final_ten_point_hardening',3);

-- Deferred foreign keys (import-safe order)
ALTER TABLE `reporting_daily_sales_snapshots` ADD CONSTRAINT `fk_reporting_daily_sales_snapshots__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `reporting_daily_operation_snapshots` ADD CONSTRAINT `fk_reporting_daily_operation_snapshots__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `reporting_daily_inventory_movement_snapshots` ADD CONSTRAINT `fk_reporting_daily_inventory_movement_snapshots__branch_88f21726` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `reporting_daily_inventory_movement_snapshots` ADD CONSTRAINT `fk_reporting_daily_inventory_movement_snapshots__ingred_20ec5e09` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`ingredient_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `audit_logs` ADD CONSTRAINT `fk_audit_logs__actor_user_id__users` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `conversation_analyses` ADD CONSTRAINT `fk_conversation_analyses__conversation_id__conversations` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `conversation_events` ADD CONSTRAINT `fk_conversation_events__conversation_id__conversations` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `conversation_events` ADD CONSTRAINT `fk_conversation_events__event_by_user_id__users` FOREIGN KEY (`event_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `conversation_files` ADD CONSTRAINT `fk_conversation_files__message_id__conversation_messages` FOREIGN KEY (`message_id`) REFERENCES `conversation_messages` (`message_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `conversation_messages` ADD CONSTRAINT `fk_conversation_messages__conversation_id__conversations` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `conversation_messages` ADD CONSTRAINT `fk_conversation_messages__related_order_id__reservation_orders` FOREIGN KEY (`related_order_id`) REFERENCES `reservation_orders` (`order_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `conversation_messages` ADD CONSTRAINT `fk_conversation_messages__related_reservation_id__reservations` FOREIGN KEY (`related_reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `conversation_messages` ADD CONSTRAINT `fk_conversation_messages__sender_id__users` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `conversations` ADD CONSTRAINT `fk_conversations__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `conversations` ADD CONSTRAINT `fk_conversations__linked_reservation_id__reservations` FOREIGN KEY (`linked_reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `conversations` ADD CONSTRAINT `fk_conversations__linked_waiting_list_id__waiting_list` FOREIGN KEY (`linked_waiting_list_id`) REFERENCES `waiting_list` (`waiting_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `conversations` ADD CONSTRAINT `fk_conversations__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `loyalty_point_transactions` ADD CONSTRAINT `fk_lptx__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `loyalty_point_transactions` ADD CONSTRAINT `fk_lptx__reservation_id__reservations` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `loyalty_point_transactions` ADD CONSTRAINT `fk_lptx__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `menu_item_prices` ADD CONSTRAINT `fk_menu_item_prices__item_id__menu_items` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `menu_items` ADD CONSTRAINT `fk_menu_items__category_id__menu_categories` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`category_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `menu_item_recipes` ADD CONSTRAINT `fk_menu_item_recipes__ingredient_id__ingredients` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`ingredient_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `menu_item_recipes` ADD CONSTRAINT `fk_menu_item_recipes__item_id__menu_items` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `ingredient_stock_movements` ADD CONSTRAINT `fk_ingredient_stock_movements__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `ingredient_stock_movements` ADD CONSTRAINT `fk_ingredient_stock_movements__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `ingredient_stock_movements` ADD CONSTRAINT `fk_ingredient_stock_movements__ingredient_id__ingredients` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`ingredient_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `purchase_orders` ADD CONSTRAINT `fk_purchase_orders__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `purchase_orders` ADD CONSTRAINT `fk_purchase_orders__supplier_id__suppliers` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `purchase_orders` ADD CONSTRAINT `fk_purchase_orders__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `purchase_orders` ADD CONSTRAINT `fk_purchase_orders__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `purchase_order_lines` ADD CONSTRAINT `fk_purchase_order_lines__purchase_order_id__purchase_orders` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`purchase_order_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `purchase_order_lines` ADD CONSTRAINT `fk_purchase_order_lines__ingredient_id__ingredients` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`ingredient_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `purchase_receipts` ADD CONSTRAINT `fk_purchase_receipts__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `purchase_receipts` ADD CONSTRAINT `fk_purchase_receipts__purchase_order_id__purchase_orders` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`purchase_order_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `purchase_receipts` ADD CONSTRAINT `fk_purchase_receipts__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `purchase_receipt_lines` ADD CONSTRAINT `fk_purchase_receipt_lines__receipt_id__purchase_receipts` FOREIGN KEY (`receipt_id`) REFERENCES `purchase_receipts` (`receipt_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `purchase_receipt_lines` ADD CONSTRAINT `fk_purchase_receipt_lines__purchase_order_line_id__purc_8b776a16` FOREIGN KEY (`purchase_order_line_id`) REFERENCES `purchase_order_lines` (`po_line_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `purchase_receipt_lines` ADD CONSTRAINT `fk_purchase_receipt_lines__ingredient_id__ingredients` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`ingredient_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `purchase_receipt_lines` ADD CONSTRAINT `fk_purchase_receipt_lines__stock_movement_id__ingredien_999b3bca` FOREIGN KEY (`stock_movement_id`) REFERENCES `ingredient_stock_movements` (`movement_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `message_entities` ADD CONSTRAINT `fk_message_entities__message_id__conversation_messages` FOREIGN KEY (`message_id`) REFERENCES `conversation_messages` (`message_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `notification_delivery_attempts` ADD CONSTRAINT `fk_notif_delivery_attempts__outbox_id__outbox` FOREIGN KEY (`outbox_id`) REFERENCES `notification_outbox` (`outbox_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `notification_preferences` ADD CONSTRAINT `fk_notification_preferences__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `notification_outbox` ADD CONSTRAINT `fk_notification_outbox__related_reservation_id__reservations` FOREIGN KEY (`related_reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `notification_outbox` ADD CONSTRAINT `fk_notification_outbox__recipient_user_id__users` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `payments` ADD CONSTRAINT `fk_payments__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `payments` ADD CONSTRAINT `fk_payments__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `payments` ADD CONSTRAINT `fk_payments__refund_of_payment_id__payments` FOREIGN KEY (`refund_of_payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `payments` ADD CONSTRAINT `fk_payments__reservation_id__reservations` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `payments` ADD CONSTRAINT `fk_payments__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `billing_invoices` ADD CONSTRAINT `fk_billing_invoices__reservation_id__reservations` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `billing_invoices` ADD CONSTRAINT `fk_billing_invoices__issued_by__users` FOREIGN KEY (`issued_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `billing_invoices` ADD CONSTRAINT `fk_billing_invoices__voided_by__users` FOREIGN KEY (`voided_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `reservation_order_items` ADD CONSTRAINT `fk_reservation_order_items__item_id__menu_items` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `reservation_order_items` ADD CONSTRAINT `fk_reservation_order_items__order_id__reservation_orders` FOREIGN KEY (`order_id`) REFERENCES `reservation_orders` (`order_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `reservation_order_items` ADD CONSTRAINT `fk_reservation_order_items__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `reservation_orders` ADD CONSTRAINT `fk_reservation_orders__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `reservation_orders` ADD CONSTRAINT `fk_reservation_orders__reservation_id__reservations` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `reservation_orders` ADD CONSTRAINT `fk_reservation_orders__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `reservation_tables` ADD CONSTRAINT `fk_reservation_tables__reservation_id__reservations` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `reservation_tables` ADD CONSTRAINT `fk_reservation_tables__table_id__restaurant_tables` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`table_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `reservation_deposit_payment_sessions` ADD CONSTRAINT `fk_reservation_deposit_payment_sessions__reservation_id_5da21e5a` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `reservation_deposit_payment_sessions` ADD CONSTRAINT `fk_reservation_deposit_payment_sessions__customer_user_id__users` FOREIGN KEY (`customer_user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `reservation_deposit_payment_sessions` ADD CONSTRAINT `fk_reservation_deposit_payment_sessions__linked_payment_140895f5` FOREIGN KEY (`linked_payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `reservation_deposit_payment_sessions` ADD CONSTRAINT `fk_reservation_deposit_payment_sessions__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `reservation_deposit_payment_sessions` ADD CONSTRAINT `fk_reservation_deposit_payment_sessions__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `reservation_bill_payment_sessions` ADD CONSTRAINT `fk_reservation_bill_payment_sessions__reservation_id__r_a8d6b53e` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `reservation_bill_payment_sessions` ADD CONSTRAINT `fk_reservation_bill_payment_sessions__order_id__reserva_ce14f420` FOREIGN KEY (`order_id`) REFERENCES `reservation_orders` (`order_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `reservation_bill_payment_sessions` ADD CONSTRAINT `fk_reservation_bill_payment_sessions__customer_user_id__users` FOREIGN KEY (`customer_user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `reservation_bill_payment_sessions` ADD CONSTRAINT `fk_reservation_bill_payment_sessions__linked_payment_id_29c6b2c1` FOREIGN KEY (`linked_payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `reservation_bill_payment_sessions` ADD CONSTRAINT `fk_reservation_bill_payment_sessions__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `reservation_bill_payment_sessions` ADD CONSTRAINT `fk_reservation_bill_payment_sessions__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `restaurant_tables` ADD CONSTRAINT `fk_restaurant_tables__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `restaurant_tables` ADD CONSTRAINT `fk_restaurant_tables__template_id__table_templates` FOREIGN KEY (`template_id`) REFERENCES `table_templates` (`template_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `settings` ADD CONSTRAINT `fk_settings__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `table_hold_details` ADD CONSTRAINT `fk_table_hold_details__hold_id__table_holds` FOREIGN KEY (`hold_id`) REFERENCES `table_holds` (`hold_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `table_hold_details` ADD CONSTRAINT `fk_table_hold_details__table_id__restaurant_tables` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`table_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `customer_access_sessions` ADD CONSTRAINT `fk_customer_access_sessions__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `user_auth_tokens` ADD CONSTRAINT `fk_user_auth_tokens__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `user_points` ADD CONSTRAINT `fk_user_points__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `user_points` ADD CONSTRAINT `fk_user_points__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `user_tier_history` ADD CONSTRAINT `fk_user_tier_history__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `user_tier_history` ADD CONSTRAINT `fk_user_tier_history__from_tier_id__loyalty_tiers` FOREIGN KEY (`from_tier_id`) REFERENCES `loyalty_tiers` (`tier_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `user_tier_history` ADD CONSTRAINT `fk_user_tier_history__to_tier_id__loyalty_tiers` FOREIGN KEY (`to_tier_id`) REFERENCES `loyalty_tiers` (`tier_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `user_tier_history` ADD CONSTRAINT `fk_user_tier_history__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `user_vouchers` ADD CONSTRAINT `fk_user_vouchers__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `user_vouchers` ADD CONSTRAINT `fk_user_vouchers__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `user_vouchers` ADD CONSTRAINT `fk_user_vouchers__used_reservation_id__reservations` FOREIGN KEY (`used_reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `user_vouchers` ADD CONSTRAINT `fk_user_vouchers__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `user_vouchers` ADD CONSTRAINT `fk_user_vouchers__voucher_id__vouchers` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`voucher_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `users` ADD CONSTRAINT `fk_users__current_tier_id__loyalty_tiers` FOREIGN KEY (`current_tier_id`) REFERENCES `loyalty_tiers` (`tier_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `users` ADD CONSTRAINT `fk_users__role_id__roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `staff_api_keys` ADD CONSTRAINT `fk_staff_api_keys__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `vouchers` ADD CONSTRAINT `fk_vouchers__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `vouchers` ADD CONSTRAINT `fk_vouchers__free_item_id__menu_items` FOREIGN KEY (`free_item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `vouchers` ADD CONSTRAINT `fk_vouchers__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `waiting_list` ADD CONSTRAINT `fk_waiting_list__branch_id__branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `waiting_list` ADD CONSTRAINT `fk_waiting_list__notified_by__users` FOREIGN KEY (`notified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `waiting_list` ADD CONSTRAINT `fk_waiting_list__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `waiting_list` ADD CONSTRAINT `fk_waiting_list__user_id__users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `kitchen_station_category_routes` ADD CONSTRAINT `fk_kitchen_station_category_routes__category_id__menu_categories` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`category_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `kitchen_station_category_routes` ADD CONSTRAINT `fk_kitchen_station_category_routes__station_id__kitchen_stations` FOREIGN KEY (`station_id`) REFERENCES `kitchen_stations` (`station_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `kitchen_order_item_tickets` ADD CONSTRAINT `fk_kitchen_order_item_tickets__category_id__menu_categories` FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`category_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `kitchen_order_item_tickets` ADD CONSTRAINT `fk_kitchen_order_item_tickets__created_by__users` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `kitchen_order_item_tickets` ADD CONSTRAINT `fk_kitchen_order_item_tickets__item_id__menu_items` FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `kitchen_order_item_tickets` ADD CONSTRAINT `fk_kitchen_order_item_tickets__order_id__reservation_orders` FOREIGN KEY (`order_id`) REFERENCES `reservation_orders` (`order_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `kitchen_order_item_tickets` ADD CONSTRAINT `fk_kitchen_order_item_tickets__order_item_id__reservati_39c3a948` FOREIGN KEY (`order_item_id`) REFERENCES `reservation_order_items` (`order_item_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `kitchen_order_item_tickets` ADD CONSTRAINT `fk_kitchen_order_item_tickets__reservation_id__reservations` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
ALTER TABLE `kitchen_order_item_tickets` ADD CONSTRAINT `fk_kitchen_order_item_tickets__route_id__kitchen_statio_37e6acbc` FOREIGN KEY (`route_id`) REFERENCES `kitchen_station_category_routes` (`route_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
ALTER TABLE `kitchen_order_item_tickets` ADD CONSTRAINT `fk_kitchen_order_item_tickets__station_id__kitchen_stations` FOREIGN KEY (`station_id`) REFERENCES `kitchen_stations` (`station_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `kitchen_order_item_tickets` ADD CONSTRAINT `fk_kitchen_order_item_tickets__updated_by__users` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT;
