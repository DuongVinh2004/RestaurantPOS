SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_inventory_master_data_row_version`;
DELIMITER $$
CREATE PROCEDURE `sp_inventory_master_data_row_version`()
BEGIN
    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'ingredients'
           AND COLUMN_NAME = 'row_version'
    ) THEN
        ALTER TABLE `ingredients`
            ADD COLUMN `row_version` bigint unsigned NOT NULL DEFAULT 1 AFTER `is_active`;
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'menu_item_recipes'
           AND COLUMN_NAME = 'row_version'
    ) THEN
        ALTER TABLE `menu_item_recipes`
            ADD COLUMN `row_version` bigint unsigned NOT NULL DEFAULT 1 AFTER `notes`;
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'suppliers'
           AND COLUMN_NAME = 'row_version'
    ) THEN
        ALTER TABLE `suppliers`
            ADD COLUMN `row_version` bigint unsigned NOT NULL DEFAULT 1 AFTER `is_active`;
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'purchase_orders'
           AND COLUMN_NAME = 'row_version'
    ) THEN
        ALTER TABLE `purchase_orders`
            ADD COLUMN `row_version` bigint unsigned NOT NULL DEFAULT 1 AFTER `updated_by`;
    END IF;
END $$
DELIMITER ;

CALL `sp_inventory_master_data_row_version`();
DROP PROCEDURE IF EXISTS `sp_inventory_master_data_row_version`;

DROP TRIGGER IF EXISTS `trg_ingredients__bi_row_version`;
DROP TRIGGER IF EXISTS `trg_ingredients__bu_row_version`;
DROP TRIGGER IF EXISTS `trg_menu_item_recipes__bi_row_version`;
DROP TRIGGER IF EXISTS `trg_menu_item_recipes__bu_row_version`;
DROP TRIGGER IF EXISTS `trg_suppliers__bi_row_version`;
DROP TRIGGER IF EXISTS `trg_suppliers__bu_row_version`;
DROP TRIGGER IF EXISTS `trg_purchase_orders__bi_row_version`;
DROP TRIGGER IF EXISTS `trg_purchase_orders__bu_row_version`;

DELIMITER $$
CREATE TRIGGER `trg_ingredients__bi_row_version`
BEFORE INSERT ON `ingredients`
FOR EACH ROW
BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END $$

CREATE TRIGGER `trg_ingredients__bu_row_version`
BEFORE UPDATE ON `ingredients`
FOR EACH ROW
BEGIN
    SET NEW.`row_version` = GREATEST(OLD.`row_version` + 1, COALESCE(NEW.`row_version`, 0));
END $$

CREATE TRIGGER `trg_menu_item_recipes__bi_row_version`
BEFORE INSERT ON `menu_item_recipes`
FOR EACH ROW
BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END $$

CREATE TRIGGER `trg_menu_item_recipes__bu_row_version`
BEFORE UPDATE ON `menu_item_recipes`
FOR EACH ROW
BEGIN
    SET NEW.`row_version` = GREATEST(OLD.`row_version` + 1, COALESCE(NEW.`row_version`, 0));
END $$

CREATE TRIGGER `trg_suppliers__bi_row_version`
BEFORE INSERT ON `suppliers`
FOR EACH ROW
BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END $$

CREATE TRIGGER `trg_suppliers__bu_row_version`
BEFORE UPDATE ON `suppliers`
FOR EACH ROW
BEGIN
    SET NEW.`row_version` = GREATEST(OLD.`row_version` + 1, COALESCE(NEW.`row_version`, 0));
END $$

CREATE TRIGGER `trg_purchase_orders__bi_row_version`
BEFORE INSERT ON `purchase_orders`
FOR EACH ROW
BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END $$

CREATE TRIGGER `trg_purchase_orders__bu_row_version`
BEFORE UPDATE ON `purchase_orders`
FOR EACH ROW
BEGIN
    SET NEW.`row_version` = GREATEST(OLD.`row_version` + 1, COALESCE(NEW.`row_version`, 0));
END $$
DELIMITER ;
