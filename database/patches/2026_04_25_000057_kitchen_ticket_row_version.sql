SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_kitchen_ticket_row_version`;
DELIMITER $$
CREATE PROCEDURE `sp_kitchen_ticket_row_version`()
BEGIN
    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'kitchen_order_item_tickets'
           AND COLUMN_NAME = 'row_version'
    ) THEN
        ALTER TABLE `kitchen_order_item_tickets`
            ADD COLUMN `row_version` bigint unsigned NOT NULL DEFAULT 1 AFTER `updated_by`;
    END IF;
END $$
DELIMITER ;

CALL `sp_kitchen_ticket_row_version`();
DROP PROCEDURE IF EXISTS `sp_kitchen_ticket_row_version`;

DROP TRIGGER IF EXISTS `trg_kitchen_order_item_tickets__bi_row_version`;
DROP TRIGGER IF EXISTS `trg_kitchen_order_item_tickets__bu_row_version`;

DELIMITER $$
CREATE TRIGGER `trg_kitchen_order_item_tickets__bi_row_version`
BEFORE INSERT ON `kitchen_order_item_tickets`
FOR EACH ROW
BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END $$

CREATE TRIGGER `trg_kitchen_order_item_tickets__bu_row_version`
BEFORE UPDATE ON `kitchen_order_item_tickets`
FOR EACH ROW
BEGIN
    SET NEW.`row_version` = OLD.`row_version` + 1;
END $$
DELIMITER ;
