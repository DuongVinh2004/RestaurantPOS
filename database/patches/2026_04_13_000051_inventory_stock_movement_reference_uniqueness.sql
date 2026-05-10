SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_inventory_stock_movement_reference_uniqueness`;

DELIMITER $$

CREATE PROCEDURE `sp_inventory_stock_movement_reference_uniqueness`()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM `ingredient_stock_movements`
        WHERE `reference_type` IS NOT NULL
          AND `reference_id` IS NOT NULL
        GROUP BY `reference_type`, `reference_id`
        HAVING COUNT(*) > 1
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Duplicate ingredient_stock_movements lineage exists for reference_type/reference_id. Clean duplicate rows before adding uniqueness guard.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'ingredient_stock_movements'
          AND index_name = 'idx_ingredient_stock_movements__reference'
    ) THEN
        ALTER TABLE `ingredient_stock_movements`
            DROP INDEX `idx_ingredient_stock_movements__reference`;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'ingredient_stock_movements'
          AND index_name = 'uq_ingredient_stock_movements__reference'
    ) THEN
        ALTER TABLE `ingredient_stock_movements`
            ADD UNIQUE KEY `uq_ingredient_stock_movements__reference` (`reference_type`, `reference_id`);
    END IF;
END $$

DELIMITER ;

CALL `sp_inventory_stock_movement_reference_uniqueness`();

DROP PROCEDURE IF EXISTS `sp_inventory_stock_movement_reference_uniqueness`;
