SET NAMES utf8mb4;
SET @patch_db := DATABASE();

DROP PROCEDURE IF EXISTS __patch_exec;
DROP PROCEDURE IF EXISTS __patch_exec_if_check_missing;
DELIMITER $$
CREATE PROCEDURE __patch_exec(IN p_sql LONGTEXT)
BEGIN
  SET @__patch_sql := p_sql;
  PREPARE __patch_stmt FROM @__patch_sql;
  EXECUTE __patch_stmt;
  DEALLOCATE PREPARE __patch_stmt;
END $$

CREATE PROCEDURE __patch_exec_if_check_missing(
  IN p_schema VARCHAR(128),
  IN p_table VARCHAR(128),
  IN p_constraint VARCHAR(128),
  IN p_sql LONGTEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE constraint_schema = p_schema
      AND table_name = p_table
      AND constraint_name = p_constraint
      AND constraint_type = 'CHECK'
  ) THEN
    CALL __patch_exec(p_sql);
  END IF;
END $$
DELIMITER ;

ALTER TABLE `notification_outbox`
  MODIFY COLUMN `channel` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

CALL __patch_exec_if_check_missing(
  @patch_db,
  'notification_outbox',
  'chk_notification_outbox__channel_nonempty',
  "ALTER TABLE `notification_outbox` ADD CONSTRAINT `chk_notification_outbox__channel_nonempty` CHECK (CHAR_LENGTH(TRIM(`channel`)) BETWEEN 1 AND 20)"
);

DROP PROCEDURE IF EXISTS `sp_cleanup_expired_holds`;
DELIMITER $$
CREATE PROCEDURE `sp_cleanup_expired_holds`()
BEGIN
  UPDATE `table_holds`
  SET `hold_status` = 'Expired'
  WHERE `hold_status` IN ('Holding','Pending')
    AND `expire_at` IS NOT NULL
    AND `expire_at` <= CURRENT_TIMESTAMP(6);
END $$
DELIMITER ;

DROP PROCEDURE IF EXISTS __patch_exec_if_check_missing;
DROP PROCEDURE IF EXISTS __patch_exec;
