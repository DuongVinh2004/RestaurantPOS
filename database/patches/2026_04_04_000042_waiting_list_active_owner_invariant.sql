SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_waiting_list_active_owner_invariant`;
DELIMITER $$
CREATE PROCEDURE `sp_waiting_list_active_owner_invariant`()
BEGIN
  DECLARE duplicate_active_owner_exists TINYINT(1) DEFAULT 0;

  SELECT EXISTS (
    SELECT 1
    FROM `waiting_list`
    WHERE `user_id` IS NOT NULL
      AND `status` IN ('Waiting', 'Notified')
    GROUP BY `user_id`
    HAVING COUNT(*) > 1
    LIMIT 1
  ) INTO duplicate_active_owner_exists;

  IF duplicate_active_owner_exists = 1 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'waiting_list contains duplicate active owner entries; clean data before applying active owner invariant patch';
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND column_name = 'active_owner_waiting_key'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD COLUMN `active_owner_waiting_key` int unsigned
      GENERATED ALWAYS AS (
        (case when ((`user_id` is not null) and (`status` in (_utf8mb4'Waiting',_utf8mb4'Notified')))
          then `user_id`
          else NULL
        end)
      ) STORED
      AFTER `row_version`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND index_name = 'uq_waiting_list__active_owner_waiting_key'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD UNIQUE KEY `uq_waiting_list__active_owner_waiting_key` (`active_owner_waiting_key`);
  END IF;
END $$
DELIMITER ;

CALL `sp_waiting_list_active_owner_invariant`();
DROP PROCEDURE IF EXISTS `sp_waiting_list_active_owner_invariant`;
