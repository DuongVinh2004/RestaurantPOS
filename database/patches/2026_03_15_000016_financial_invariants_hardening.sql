SET @OLD_SQL_MODE = @@SQL_MODE;
SET SQL_MODE = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

DROP TRIGGER IF EXISTS `trg_payments__bi_refund_cap`;
DROP TRIGGER IF EXISTS `trg_payments__bu_refund_cap`;

DELIMITER $$
CREATE TRIGGER `trg_payments__bi_refund_cap`
BEFORE INSERT ON `payments`
FOR EACH ROW
BEGIN
    DECLARE v_source_amount DECIMAL(14,2);
    DECLARE v_refunded_amount DECIMAL(14,2);

    IF NEW.`payment_type` = 'Refund' AND NEW.`status` = 'Refunded' AND NEW.`refund_of_payment_id` IS NOT NULL THEN
        SELECT `amount`
        INTO v_source_amount
        FROM `payments`
        WHERE `payment_id` = NEW.`refund_of_payment_id`
        FOR UPDATE;

        IF v_source_amount IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Refund source payment does not exist.';
        END IF;

        SELECT COALESCE(SUM(`amount`), 0)
        INTO v_refunded_amount
        FROM `payments`
        WHERE `refund_of_payment_id` = NEW.`refund_of_payment_id`
          AND `payment_type` = 'Refund'
          AND `status` = 'Refunded'
        FOR UPDATE;

        IF ROUND(v_refunded_amount + NEW.`amount`, 2) > ROUND(v_source_amount, 2) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Refund amount exceeds source payment amount.';
        END IF;
    END IF;
END$$

CREATE TRIGGER `trg_payments__bu_refund_cap`
BEFORE UPDATE ON `payments`
FOR EACH ROW
BEGIN
    DECLARE v_source_amount DECIMAL(14,2);
    DECLARE v_refunded_amount DECIMAL(14,2);

    IF NEW.`payment_type` = 'Refund' AND NEW.`status` = 'Refunded' AND NEW.`refund_of_payment_id` IS NOT NULL THEN
        SELECT `amount`
        INTO v_source_amount
        FROM `payments`
        WHERE `payment_id` = NEW.`refund_of_payment_id`
        FOR UPDATE;

        IF v_source_amount IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Refund source payment does not exist.';
        END IF;

        SELECT COALESCE(SUM(`amount`), 0)
        INTO v_refunded_amount
        FROM `payments`
        WHERE `refund_of_payment_id` = NEW.`refund_of_payment_id`
          AND `payment_type` = 'Refund'
          AND `status` = 'Refunded'
          AND `payment_id` <> OLD.`payment_id`
        FOR UPDATE;

        IF ROUND(v_refunded_amount + NEW.`amount`, 2) > ROUND(v_source_amount, 2) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Refund amount exceeds source payment amount.';
        END IF;
    END IF;
END$$
DELIMITER ;

SET SQL_MODE = @OLD_SQL_MODE;
