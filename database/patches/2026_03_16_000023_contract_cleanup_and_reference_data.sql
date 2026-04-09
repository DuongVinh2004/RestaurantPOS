SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_contract_cleanup_and_reference_data`;
DELIMITER $$
CREATE PROCEDURE `sp_contract_cleanup_and_reference_data`()
BEGIN
  INSERT INTO `roles` (`role_id`, `role_name`)
  VALUES
    (1, 'Admin'),
    (2, 'Staff'),
    (3, 'Customer')
  ON DUPLICATE KEY UPDATE `role_name` = VALUES(`role_name`);

  UPDATE `notification_outbox`
  SET `channel` = 'Email'
  WHERE `channel` IS NULL
     OR TRIM(`channel`) = ''
     OR `channel` NOT IN ('SMS', 'Email', 'Push', 'Webhook');

  UPDATE `table_holds`
  SET `end_time` = DATE_ADD(`start_time`, INTERVAL GREATEST(COALESCE(`duration_minutes`, 0), 1) MINUTE)
  WHERE `end_time` IS NULL OR `end_time` <= `start_time`;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'active_applied_user_voucher_id'
  ) THEN
    ALTER TABLE `reservations`
      ADD COLUMN `active_applied_user_voucher_id` int unsigned
        GENERATED ALWAYS AS (
          CASE WHEN `status` IN ('Confirmed', 'Reserved') THEN `applied_user_voucher_id` ELSE NULL END
        ) STORED AFTER `applied_user_voucher_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND index_name = 'uq_reservations__active_applied_user_voucher_id'
  ) THEN
    ALTER TABLE `reservations`
      ADD UNIQUE KEY `uq_reservations__active_applied_user_voucher_id` (`active_applied_user_voucher_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND index_name = 'uq_payments__payment_provider__transaction_code'
  ) THEN
    ALTER TABLE `payments`
      ADD UNIQUE KEY `uq_payments__payment_provider__transaction_code` (`payment_provider`, `transaction_code`);
  END IF;

  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'table_holds'
      AND column_name = 'end_time'
      AND is_nullable = 'YES'
  ) THEN
    ALTER TABLE `table_holds`
      MODIFY COLUMN `end_time` datetime(6) NOT NULL;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'table_holds'
      AND constraint_name = 'chk_table_holds__time_range'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `table_holds`
      ADD CONSTRAINT `chk_table_holds__time_range` CHECK (`start_time` < `end_time`);
  END IF;

  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'notification_outbox'
      AND column_name = 'channel'
      AND column_type NOT LIKE 'enum(%'
  ) THEN
    ALTER TABLE `notification_outbox`
      MODIFY COLUMN `channel` enum('SMS','Email','Push','Webhook') NOT NULL;
  END IF;

  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'role_id'
      AND column_default IS NOT NULL
  ) THEN
    ALTER TABLE `users`
      MODIFY COLUMN `role_id` int unsigned NOT NULL;
  END IF;
END $$
DELIMITER ;

CALL `sp_contract_cleanup_and_reference_data`();
DROP PROCEDURE IF EXISTS `sp_contract_cleanup_and_reference_data`;
