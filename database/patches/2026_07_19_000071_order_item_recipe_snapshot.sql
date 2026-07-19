SET NAMES utf8mb4;

-- Freeze the recipe identity carried by every order item. Existing rows are
-- backfilled from the recipe visible at upgrade time because no earlier
-- committed recipe version exists for them.

DROP PROCEDURE IF EXISTS `sp_order_item_recipe_snapshot`;

DELIMITER $$

CREATE PROCEDURE `sp_order_item_recipe_snapshot`()
BEGIN
  DECLARE v_missing_snapshot_count BIGINT DEFAULT 0;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'reservation_order_items'
       AND column_name = 'recipe_snapshot'
  ) THEN
    ALTER TABLE `reservation_order_items`
      ADD COLUMN `recipe_snapshot` json NULL AFTER `item_name_snapshot`;
  END IF;

  UPDATE `reservation_order_items` AS `order_item`
  LEFT JOIN (
    SELECT
      `ordered_recipe`.`item_id`,
      JSON_ARRAYAGG(JSON_OBJECT(
        'recipe_line_id', `ordered_recipe`.`recipe_line_id`,
        'ingredient_id', `ordered_recipe`.`ingredient_id`,
        'quantity', CAST(`ordered_recipe`.`quantity` AS CHAR),
        'unit_code', `ordered_recipe`.`unit_code`,
        'sort_order', `ordered_recipe`.`sort_order`,
        'recipe_row_version', `ordered_recipe`.`row_version`
      )) AS `snapshot`
    FROM (
      SELECT
        `recipe_line_id`,
        `item_id`,
        `ingredient_id`,
        `quantity`,
        `unit_code`,
        `sort_order`,
        `row_version`
      FROM `menu_item_recipes`
      ORDER BY `item_id`, `sort_order`, `recipe_line_id`
    ) AS `ordered_recipe`
    GROUP BY `ordered_recipe`.`item_id`
  ) AS `recipe` ON `recipe`.`item_id` = `order_item`.`item_id`
  SET `order_item`.`recipe_snapshot` = COALESCE(`recipe`.`snapshot`, JSON_ARRAY())
  WHERE `order_item`.`recipe_snapshot` IS NULL;

  SELECT COUNT(*) INTO v_missing_snapshot_count
    FROM `reservation_order_items`
   WHERE `recipe_snapshot` IS NULL;

  IF v_missing_snapshot_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot enforce order-item recipe snapshots: null snapshots remain after backfill';
  END IF;

  ALTER TABLE `reservation_order_items`
    MODIFY COLUMN `recipe_snapshot` json NOT NULL AFTER `item_name_snapshot`;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'reservation_order_items'
       AND constraint_name = 'chk_reservation_order_items__recipe_snapshot_array'
       AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `reservation_order_items`
      ADD CONSTRAINT `chk_reservation_order_items__recipe_snapshot_array`
      CHECK (JSON_TYPE(`recipe_snapshot`) = 'ARRAY');
  END IF;
END $$

DELIMITER ;

CALL `sp_order_item_recipe_snapshot`();
DROP PROCEDURE IF EXISTS `sp_order_item_recipe_snapshot`;

SELECT 'order_item_recipe_snapshot:ok' AS checkpoint;
