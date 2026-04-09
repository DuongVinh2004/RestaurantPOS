SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_menu_price_and_active_voucher_integrity`;
DELIMITER $$
CREATE PROCEDURE `sp_menu_price_and_active_voucher_integrity`()
BEGIN
  DECLARE overlapping_price_exists TINYINT(1) DEFAULT 0;
  DECLARE active_voucher_conflict_exists TINYINT(1) DEFAULT 0;

  SELECT EXISTS (
    SELECT 1
    FROM `menu_item_prices` `left_price`
    INNER JOIN `menu_item_prices` `right_price`
      ON `left_price`.`item_id` = `right_price`.`item_id`
     AND `left_price`.`price_id` < `right_price`.`price_id`
     AND `left_price`.`effective_from` < COALESCE(`right_price`.`effective_to`, '9999-12-31 23:59:59.999999')
     AND COALESCE(`left_price`.`effective_to`, '9999-12-31 23:59:59.999999') > `right_price`.`effective_from`
    LIMIT 1
  ) INTO overlapping_price_exists;

  IF overlapping_price_exists = 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'menu_item_prices contains overlapping ranges; clean data before applying round 2 integrity patch';
  END IF;

  SELECT EXISTS (
    SELECT 1
    FROM `reservations`
    WHERE `applied_user_voucher_id` IS NOT NULL
      AND `status` IN ('Confirmed', 'Reserved')
    GROUP BY `applied_user_voucher_id`
    HAVING COUNT(*) > 1
    LIMIT 1
  ) INTO active_voucher_conflict_exists;

  IF active_voucher_conflict_exists = 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'reservations contains multiple active applications of the same user voucher; clean data before applying round 2 integrity patch';
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
            WHEN `status` IN ('Confirmed', 'Reserved') THEN `applied_user_voucher_id`
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
    ALTER TABLE `reservations`
      ADD UNIQUE KEY `uq_reservations__active_applied_user_voucher_id` (`active_applied_user_voucher_id`);
  END IF;
END $$
DELIMITER ;

CALL `sp_menu_price_and_active_voucher_integrity`();
DROP PROCEDURE IF EXISTS `sp_menu_price_and_active_voucher_integrity`;

DROP TRIGGER IF EXISTS `trg_menu_item_prices__bi_overlap_guard`;
DROP TRIGGER IF EXISTS `trg_menu_item_prices__bu_overlap_guard`;
DELIMITER $$
CREATE TRIGGER `trg_menu_item_prices__bi_overlap_guard`
BEFORE INSERT ON `menu_item_prices`
FOR EACH ROW
BEGIN
  DECLARE overlap_exists TINYINT(1) DEFAULT 0;

  SELECT EXISTS (
    SELECT 1
    FROM `menu_item_prices`
    WHERE `item_id` = NEW.`item_id`
      AND `effective_from` < COALESCE(NEW.`effective_to`, '9999-12-31 23:59:59.999999')
      AND COALESCE(`effective_to`, '9999-12-31 23:59:59.999999') > NEW.`effective_from`
    LIMIT 1
  ) INTO overlap_exists;

  IF overlap_exists = 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'menu_item_prices overlap conflict for same item';
  END IF;
END $$

CREATE TRIGGER `trg_menu_item_prices__bu_overlap_guard`
BEFORE UPDATE ON `menu_item_prices`
FOR EACH ROW
BEGIN
  DECLARE overlap_exists TINYINT(1) DEFAULT 0;

  SELECT EXISTS (
    SELECT 1
    FROM `menu_item_prices`
    WHERE `item_id` = NEW.`item_id`
      AND `price_id` <> OLD.`price_id`
      AND `effective_from` < COALESCE(NEW.`effective_to`, '9999-12-31 23:59:59.999999')
      AND COALESCE(`effective_to`, '9999-12-31 23:59:59.999999') > NEW.`effective_from`
    LIMIT 1
  ) INTO overlap_exists;

  IF overlap_exists = 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'menu_item_prices overlap conflict for same item';
  END IF;
END $$
DELIMITER ;
