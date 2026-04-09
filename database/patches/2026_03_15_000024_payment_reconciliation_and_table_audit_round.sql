SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_payment_reconciliation_round`;
DELIMITER $$
CREATE PROCEDURE `sp_payment_reconciliation_round`()
BEGIN
  DECLARE duplicate_provider_transaction_exists TINYINT(1) DEFAULT 0;

  SELECT EXISTS (
    SELECT 1
    FROM `payments`
    WHERE `transaction_code` IS NOT NULL
      AND TRIM(`transaction_code`) <> ''
    GROUP BY `payment_provider`, `transaction_code`
    HAVING COUNT(*) > 1
    LIMIT 1
  ) INTO duplicate_provider_transaction_exists;

  IF duplicate_provider_transaction_exists = 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'payments contains duplicate provider transaction references; clean data before applying round 3 reconciliation patch';
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
END $$
DELIMITER ;

CALL `sp_payment_reconciliation_round`();
DROP PROCEDURE IF EXISTS `sp_payment_reconciliation_round`;
