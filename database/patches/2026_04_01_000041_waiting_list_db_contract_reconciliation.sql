SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_waiting_list_db_contract_reconciliation`;
DELIMITER $$
CREATE PROCEDURE `sp_waiting_list_db_contract_reconciliation`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'customer_session_id'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD COLUMN `customer_session_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'customer_response_status'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD COLUMN `customer_response_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `notify_expires_at`;
  ELSEIF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'customer_response_status'
      AND (data_type <> 'varchar' OR character_maximum_length <> 30 OR is_nullable <> 'YES')
  ) THEN
    ALTER TABLE `waiting_list`
      MODIFY COLUMN `customer_response_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `notify_expires_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'customer_responded_at'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD COLUMN `customer_responded_at` datetime(6) DEFAULT NULL AFTER `customer_response_status`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'customer_confirmed_arrival_at'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD COLUMN `customer_confirmed_arrival_at` datetime(6) DEFAULT NULL AFTER `customer_responded_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND index_name = 'idx_waiting_list__customer_session_id__requested_at'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD KEY `idx_waiting_list__customer_session_id__requested_at` (`customer_session_id`,`requested_at`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND constraint_name = 'chk_waiting_list__customer_response_requires_timestamp'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD CONSTRAINT `chk_waiting_list__customer_response_requires_timestamp`
      CHECK ((`customer_response_status` IS NULL) OR (`customer_responded_at` IS NOT NULL));
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND constraint_name = 'chk_waiting_list__customer_arrival_requires_accept'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD CONSTRAINT `chk_waiting_list__customer_arrival_requires_accept`
      CHECK ((`customer_confirmed_arrival_at` IS NULL) OR (`customer_response_status` = 'Accepted'));
  END IF;
END $$
DELIMITER ;

CALL `sp_waiting_list_db_contract_reconciliation`();
DROP PROCEDURE IF EXISTS `sp_waiting_list_db_contract_reconciliation`;
