SET NAMES utf8mb4;

-- Critical branch-owned tables must fail closed before branch foreign keys are restored.
-- Do not rewrite orphan data here; release operators must repair branch ownership explicitly.

DROP PROCEDURE IF EXISTS `sp_branch_fk_integrity_guard`;

DELIMITER $$

CREATE PROCEDURE `sp_branch_fk_integrity_guard`()
BEGIN
  DECLARE v_orphan_count BIGINT DEFAULT 0;

  SELECT COUNT(*) INTO v_orphan_count
    FROM `reservations` r
    LEFT JOIN `branches` b ON b.`branch_id` = r.`branch_id`
   WHERE b.`branch_id` IS NULL;
  IF v_orphan_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot add reservations.branch_id FK: orphan branch rows exist';
  END IF;

  SELECT COUNT(*) INTO v_orphan_count
    FROM `table_holds` th
    LEFT JOIN `branches` b ON b.`branch_id` = th.`branch_id`
   WHERE b.`branch_id` IS NULL;
  IF v_orphan_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot add table_holds.branch_id FK: orphan branch rows exist';
  END IF;

  SELECT COUNT(*) INTO v_orphan_count
    FROM `cashier_shifts` cs
    LEFT JOIN `branches` b ON b.`branch_id` = cs.`branch_id`
   WHERE b.`branch_id` IS NULL;
  IF v_orphan_count > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot add cashier_shifts.branch_id FK: orphan branch rows exist';
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'reservations'
       AND index_name = 'idx_reservations__branch_id__status__start_time__end_time'
  ) THEN
    ALTER TABLE `reservations`
      ADD KEY `idx_reservations__branch_id__status__start_time__end_time` (`branch_id`, `status`, `start_time`, `end_time`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'table_holds'
       AND index_name = 'idx_table_holds__branch_id__status__expire_at__start_time'
  ) THEN
    ALTER TABLE `table_holds`
      ADD KEY `idx_table_holds__branch_id__status__expire_at__start_time` (`branch_id`, `hold_status`, `expire_at`, `start_time`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'cashier_shifts'
       AND index_name = 'idx_cashier_shifts__branch_id__status__opened_at'
  ) THEN
    ALTER TABLE `cashier_shifts`
      ADD KEY `idx_cashier_shifts__branch_id__status__opened_at` (`branch_id`, `status`, `opened_at`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'reservations'
       AND constraint_name = 'fk_reservations__branch_id__branches'
       AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `reservations`
      ADD CONSTRAINT `fk_reservations__branch_id__branches`
      FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'table_holds'
       AND constraint_name = 'fk_table_holds__branch_id__branches'
       AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `table_holds`
      ADD CONSTRAINT `fk_table_holds__branch_id__branches`
      FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'cashier_shifts'
       AND constraint_name = 'fk_cashier_shifts__branch_id__branches'
       AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `cashier_shifts`
      ADD CONSTRAINT `fk_cashier_shifts__branch_id__branches`
      FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;
END $$

DELIMITER ;

CALL `sp_branch_fk_integrity_guard`();
DROP PROCEDURE IF EXISTS `sp_branch_fk_integrity_guard`;
