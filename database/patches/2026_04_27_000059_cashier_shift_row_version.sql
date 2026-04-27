SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_cashier_shift_row_version`;
DELIMITER $$
CREATE PROCEDURE `sp_cashier_shift_row_version`()
BEGIN
    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'cashier_shifts'
           AND COLUMN_NAME = 'row_version'
    ) THEN
        ALTER TABLE `cashier_shifts`
            ADD COLUMN `row_version` bigint unsigned NOT NULL DEFAULT 1 AFTER `updated_at`;
    END IF;
END $$
DELIMITER ;

CALL `sp_cashier_shift_row_version`();
DROP PROCEDURE IF EXISTS `sp_cashier_shift_row_version`;

DROP TRIGGER IF EXISTS `trg_cashier_shifts__bi_row_version`;
DROP TRIGGER IF EXISTS `trg_cashier_shifts__bu_row_version`;

DELIMITER $$
CREATE TRIGGER `trg_cashier_shifts__bi_row_version`
BEFORE INSERT ON `cashier_shifts`
FOR EACH ROW
BEGIN
    IF NEW.`row_version` IS NULL OR NEW.`row_version` = 0 THEN
        SET NEW.`row_version` = 1;
    END IF;
END $$

CREATE TRIGGER `trg_cashier_shifts__bu_row_version`
BEFORE UPDATE ON `cashier_shifts`
FOR EACH ROW
BEGIN
    SET NEW.`row_version` = OLD.`row_version` + 1;
END $$
DELIMITER ;
