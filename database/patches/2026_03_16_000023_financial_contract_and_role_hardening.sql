SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_financial_contract_and_role_hardening`;
DELIMITER $$

CREATE PROCEDURE `sp_financial_contract_and_role_hardening`()
BEGIN
  INSERT INTO `roles` (`role_name`)
  SELECT 'Admin'
  WHERE NOT EXISTS (
    SELECT 1 FROM `roles` WHERE `role_name` = 'Admin'
  );

  INSERT INTO `roles` (`role_name`)
  SELECT 'Staff'
  WHERE NOT EXISTS (
    SELECT 1 FROM `roles` WHERE `role_name` = 'Staff'
  );

  INSERT INTO `roles` (`role_name`)
  SELECT 'Customer'
  WHERE NOT EXISTS (
    SELECT 1 FROM `roles` WHERE `role_name` = 'Customer'
  );

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

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND index_name = 'uq_payments__payment_provider__transaction_code'
  ) THEN
    IF EXISTS (
      SELECT 1
      FROM `payments`
      WHERE `transaction_code` IS NOT NULL
        AND CHAR_LENGTH(TRIM(`transaction_code`)) > 0
      GROUP BY `payment_provider`, `transaction_code`
      HAVING COUNT(*) > 1
      LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot add uq_payments__payment_provider__transaction_code because duplicate provider transaction codes already exist.';
    END IF;

    ALTER TABLE `payments`
      ADD UNIQUE KEY `uq_payments__payment_provider__transaction_code` (`payment_provider`, `transaction_code`);
  END IF;

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
          CASE
            WHEN `applied_user_voucher_id` IS NOT NULL AND `status` IN ('Confirmed', 'Reserved')
              THEN `applied_user_voucher_id`
            ELSE NULL
          END
        ) STORED AFTER `applied_user_voucher_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND index_name = 'uq_reservations__active_applied_user_voucher_id'
  ) THEN
    IF EXISTS (
      SELECT 1
      FROM `reservations`
      WHERE `applied_user_voucher_id` IS NOT NULL
        AND `status` IN ('Confirmed', 'Reserved')
      GROUP BY `applied_user_voucher_id`
      HAVING COUNT(*) > 1
      LIMIT 1
    ) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot add uq_reservations__active_applied_user_voucher_id because multiple active reservations already reference the same user voucher.';
    END IF;

    ALTER TABLE `reservations`
      ADD UNIQUE KEY `uq_reservations__active_applied_user_voucher_id` (`active_applied_user_voucher_id`);
  END IF;

  IF EXISTS (
    SELECT 1
    FROM `table_holds`
    WHERE `end_time` IS NULL
    LIMIT 1
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Cannot harden table_holds.end_time because rows with NULL end_time still exist.';
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
      ADD CONSTRAINT `chk_table_holds__time_range`
      CHECK (`start_time` < `end_time`);
  END IF;

  IF EXISTS (
    SELECT 1
    FROM `notification_outbox`
    WHERE TRIM(COALESCE(`channel`, '')) NOT IN ('SMS', 'Email', 'Push', 'Webhook')
    LIMIT 1
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Cannot harden notification_outbox.channel because unsupported channel values already exist.';
  END IF;

  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'notification_outbox'
      AND column_name = 'channel'
      AND data_type <> 'enum'
  ) THEN
    ALTER TABLE `notification_outbox`
      MODIFY COLUMN `channel` enum('SMS','Email','Push','Webhook') NOT NULL;
  END IF;
END $$

DELIMITER ;

CALL `sp_financial_contract_and_role_hardening`();
DROP PROCEDURE IF EXISTS `sp_financial_contract_and_role_hardening`;
