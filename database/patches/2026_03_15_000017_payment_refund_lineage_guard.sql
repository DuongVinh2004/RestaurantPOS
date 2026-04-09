SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS `trg_payments__bi_refund_lineage_guard`;
DROP TRIGGER IF EXISTS `trg_payments__bu_refund_lineage_guard`;

DELIMITER $$

CREATE TRIGGER `trg_payments__bi_refund_lineage_guard`
BEFORE INSERT ON `payments`
FOR EACH ROW
BEGIN
    DECLARE v_source_reservation_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_source_amount DECIMAL(14,2) DEFAULT 0.00;
    DECLARE v_source_type VARCHAR(20) DEFAULT NULL;
    DECLARE v_source_status VARCHAR(20) DEFAULT NULL;
    DECLARE v_existing_refunded DECIMAL(14,2) DEFAULT 0.00;

    IF NEW.`payment_type` = 'Refund' THEN
        IF NEW.`refund_of_payment_id` IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Refund payments must reference a source payment';
        END IF;

        SELECT `reservation_id`, `amount`, `payment_type`, `status`
          INTO v_source_reservation_id, v_source_amount, v_source_type, v_source_status
          FROM `payments`
         WHERE `payment_id` = NEW.`refund_of_payment_id`
         LIMIT 1;

        IF v_source_reservation_id IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Refund source payment not found';
        END IF;

        IF v_source_reservation_id <> NEW.`reservation_id` THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Refund source payment must belong to the same reservation';
        END IF;

        IF v_source_type NOT IN ('Deposit', 'Final') OR v_source_status NOT IN ('Success', 'Partial') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Refund source payment must target a captured Deposit or Final payment';
        END IF;

        SELECT COALESCE(SUM(`amount`), 0.00)
          INTO v_existing_refunded
          FROM `payments`
         WHERE `refund_of_payment_id` = NEW.`refund_of_payment_id`
           AND `payment_type` = 'Refund'
           AND `status` = 'Refunded';

        IF ROUND(v_existing_refunded + NEW.`amount`, 2) - ROUND(v_source_amount, 2) > 0.0001 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Refund amount exceeds refundable source payment amount';
        END IF;
    END IF;
END$$

CREATE TRIGGER `trg_payments__bu_refund_lineage_guard`
BEFORE UPDATE ON `payments`
FOR EACH ROW
BEGIN
    DECLARE v_source_reservation_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_source_amount DECIMAL(14,2) DEFAULT 0.00;
    DECLARE v_source_type VARCHAR(20) DEFAULT NULL;
    DECLARE v_source_status VARCHAR(20) DEFAULT NULL;
    DECLARE v_existing_refunded DECIMAL(14,2) DEFAULT 0.00;

    IF NEW.`payment_type` = 'Refund' THEN
        IF NEW.`refund_of_payment_id` IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Refund payments must reference a source payment';
        END IF;

        SELECT `reservation_id`, `amount`, `payment_type`, `status`
          INTO v_source_reservation_id, v_source_amount, v_source_type, v_source_status
          FROM `payments`
         WHERE `payment_id` = NEW.`refund_of_payment_id`
         LIMIT 1;

        IF v_source_reservation_id IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Refund source payment not found';
        END IF;

        IF v_source_reservation_id <> NEW.`reservation_id` THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Refund source payment must belong to the same reservation';
        END IF;

        IF v_source_type NOT IN ('Deposit', 'Final') OR v_source_status NOT IN ('Success', 'Partial') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Refund source payment must target a captured Deposit or Final payment';
        END IF;

        SELECT COALESCE(SUM(`amount`), 0.00)
          INTO v_existing_refunded
          FROM `payments`
         WHERE `refund_of_payment_id` = NEW.`refund_of_payment_id`
           AND `payment_type` = 'Refund'
           AND `status` = 'Refunded'
           AND `payment_id` <> OLD.`payment_id`;

        IF ROUND(v_existing_refunded + NEW.`amount`, 2) - ROUND(v_source_amount, 2) > 0.0001 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Refund amount exceeds refundable source payment amount';
        END IF;
    END IF;
END$$

DELIMITER ;
