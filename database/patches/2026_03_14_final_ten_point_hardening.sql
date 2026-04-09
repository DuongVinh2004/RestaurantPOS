SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_cleanup_expired_holds`;
DROP PROCEDURE IF EXISTS `sp_apply_table_holds_time_range_constraint`;
DROP TRIGGER IF EXISTS `trg_table_holds__bi_defaults`;

UPDATE `table_holds`
SET `duration_minutes` = CASE
        WHEN `duration_minutes` IS NULL OR `duration_minutes` <= 0 THEN
            CASE
                WHEN `end_time` IS NOT NULL AND `end_time` > `start_time`
                    THEN TIMESTAMPDIFF(MINUTE, `start_time`, `end_time`)
                WHEN `expire_at` IS NOT NULL AND `expire_at` > `start_time`
                    THEN GREATEST(1, TIMESTAMPDIFF(MINUTE, `start_time`, `expire_at`))
                ELSE 120
            END
        ELSE `duration_minutes`
    END,
    `end_time` = CASE
        WHEN `end_time` IS NULL OR `end_time` <= `start_time`
            THEN DATE_ADD(`start_time`, INTERVAL CASE
                WHEN `duration_minutes` IS NULL OR `duration_minutes` <= 0 THEN
                    CASE
                        WHEN `expire_at` IS NOT NULL AND `expire_at` > `start_time`
                            THEN GREATEST(1, TIMESTAMPDIFF(MINUTE, `start_time`, `expire_at`))
                        ELSE 120
                    END
                ELSE `duration_minutes`
            END MINUTE)
        ELSE `end_time`
    END;

ALTER TABLE `table_holds`
  MODIFY COLUMN `end_time` datetime(6) NOT NULL;

DELIMITER $$
CREATE PROCEDURE `sp_apply_table_holds_time_range_constraint`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'table_holds'
      AND constraint_name = 'chk_table_holds__time_range'
      AND constraint_type = 'CHECK'
  ) THEN
    ALTER TABLE `table_holds`
      ADD CONSTRAINT `chk_table_holds__time_range`
      CHECK ((`start_time` < `end_time`));
  END IF;
END $$

CALL `sp_apply_table_holds_time_range_constraint`() $$
DROP PROCEDURE IF EXISTS `sp_apply_table_holds_time_range_constraint` $$

CREATE TRIGGER `trg_table_holds__bi_defaults`
BEFORE INSERT ON `table_holds`
FOR EACH ROW
BEGIN
  IF NEW.`hold_id` IS NULL OR NEW.`hold_id` = '' THEN
    SET NEW.`hold_id` = UUID();
  END IF;

  IF NEW.`created_at` IS NULL THEN
    SET NEW.`created_at` = CURRENT_TIMESTAMP(6);
  END IF;

  IF NEW.`duration_minutes` IS NULL OR NEW.`duration_minutes` <= 0 THEN
    IF NEW.`end_time` IS NOT NULL AND NEW.`start_time` IS NOT NULL AND NEW.`end_time` > NEW.`start_time` THEN
      SET NEW.`duration_minutes` = GREATEST(1, TIMESTAMPDIFF(MINUTE, NEW.`start_time`, NEW.`end_time`));
    ELSEIF NEW.`expire_at` IS NOT NULL AND NEW.`start_time` IS NOT NULL AND NEW.`expire_at` > NEW.`start_time` THEN
      SET NEW.`duration_minutes` = GREATEST(1, TIMESTAMPDIFF(MINUTE, NEW.`start_time`, NEW.`expire_at`));
    ELSE
      SET NEW.`duration_minutes` = 120;
    END IF;
  END IF;

  IF NEW.`end_time` IS NULL AND NEW.`start_time` IS NOT NULL THEN
    SET NEW.`end_time` = DATE_ADD(NEW.`start_time`, INTERVAL NEW.`duration_minutes` MINUTE);
  END IF;

  IF NEW.`end_time` IS NOT NULL AND NEW.`start_time` IS NOT NULL AND NEW.`end_time` <= NEW.`start_time` THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'table_holds end_time must be after start_time';
  END IF;

  IF NEW.`expire_at` IS NULL THEN
    SET NEW.`expire_at` = DATE_ADD(NEW.`created_at`, INTERVAL 5 MINUTE);
  END IF;
END $$

CREATE PROCEDURE `sp_cleanup_expired_holds`()
BEGIN
  UPDATE `table_holds`
  SET `hold_status` = 'Expired'
  WHERE `hold_status` IN ('Holding','Pending')
    AND `expire_at` <= UTC_TIMESTAMP(6);
END $$
DELIMITER ;
