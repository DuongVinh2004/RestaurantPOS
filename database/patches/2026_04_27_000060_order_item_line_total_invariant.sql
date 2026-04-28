SET NAMES utf8mb4;

-- Enforce the order item money invariant without rewriting existing financial data.
-- Existing drift must be fixed deliberately before this patch can add the CHECK.

DROP PROCEDURE IF EXISTS `sp_order_item_line_total_invariant`;

DELIMITER $$

CREATE PROCEDURE `sp_order_item_line_total_invariant`()
BEGIN
  DECLARE v_drift_count BIGINT DEFAULT 0;

  SELECT COUNT(*) INTO v_drift_count
    FROM `reservation_order_items`
   WHERE `line_total` <> ROUND(`unit_price` * `quantity`, 2);

  IF v_drift_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot add reservation_order_items line_total CHECK: drifted line totals exist';
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'reservation_order_items'
       AND constraint_name = 'chk_reservation_order_items__line_total_matches'
       AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `reservation_order_items`
      ADD CONSTRAINT `chk_reservation_order_items__line_total_matches`
      CHECK (`line_total` = ROUND(`unit_price` * `quantity`, 2));
  END IF;
END $$

DELIMITER ;

CALL `sp_order_item_line_total_invariant`();
DROP PROCEDURE IF EXISTS `sp_order_item_line_total_invariant`;
