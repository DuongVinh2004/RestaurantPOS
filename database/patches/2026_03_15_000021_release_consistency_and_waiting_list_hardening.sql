SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_release_consistency_and_waiting_list_hardening`;
DELIMITER $$
CREATE PROCEDURE `sp_release_consistency_and_waiting_list_hardening`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND constraint_name = 'chk_waiting_list__status_notified_requires_window'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD CONSTRAINT `chk_waiting_list__status_notified_requires_window`
      CHECK ((`status` <> 'Notified') OR (`notified_at` IS NOT NULL AND `notify_expires_at` IS NOT NULL));
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND constraint_name = 'chk_waiting_list__status_seated_requires_timestamp'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD CONSTRAINT `chk_waiting_list__status_seated_requires_timestamp`
      CHECK ((`status` <> 'Seated') OR (`seated_at` IS NOT NULL));
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND constraint_name = 'chk_waiting_list__status_cancelled_requires_timestamp'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD CONSTRAINT `chk_waiting_list__status_cancelled_requires_timestamp`
      CHECK ((`status` <> 'Cancelled') OR (`cancelled_at` IS NOT NULL));
  END IF;
END $$
DELIMITER ;

CALL `sp_release_consistency_and_waiting_list_hardening`();
DROP PROCEDURE IF EXISTS `sp_release_consistency_and_waiting_list_hardening`;
