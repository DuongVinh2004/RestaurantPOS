SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_waiting_list_user_fk_generated_column_hotfix`;
DELIMITER $$
CREATE PROCEDURE `sp_waiting_list_user_fk_generated_column_hotfix`()
proc: BEGIN
  DECLARE waiting_list_exists TINYINT(1) DEFAULT 0;
  DECLARE users_exists TINYINT(1) DEFAULT 0;
  DECLARE delete_rule_value VARCHAR(30) DEFAULT NULL;

  SELECT EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
  ) INTO waiting_list_exists;

  SELECT EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
  ) INTO users_exists;

  IF waiting_list_exists = 0 OR users_exists = 0 THEN
    LEAVE proc;
  END IF;

  SELECT rc.DELETE_RULE
  INTO delete_rule_value
  FROM information_schema.referential_constraints rc
  WHERE rc.constraint_schema = DATABASE()
    AND rc.constraint_name = 'fk_waiting_list__user_id__users'
    AND rc.table_name = 'waiting_list'
  LIMIT 1;

  IF delete_rule_value IS NOT NULL AND delete_rule_value <> 'RESTRICT' THEN
    ALTER TABLE `waiting_list` DROP FOREIGN KEY `fk_waiting_list__user_id__users`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'waiting_list'
      AND index_name = 'fk_waiting_list__user_id__users'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD KEY `fk_waiting_list__user_id__users` (`user_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND constraint_name = 'fk_waiting_list__user_id__users'
      AND table_name = 'waiting_list'
  ) THEN
    ALTER TABLE `waiting_list`
      ADD CONSTRAINT `fk_waiting_list__user_id__users`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT;
  END IF;
END proc $$
DELIMITER ;

CALL `sp_waiting_list_user_fk_generated_column_hotfix`();
DROP PROCEDURE IF EXISTS `sp_waiting_list_user_fk_generated_column_hotfix`;
