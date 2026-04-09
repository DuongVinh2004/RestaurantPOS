SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_staff_conversation_inbox_foundation`;
DELIMITER $$
CREATE PROCEDURE `sp_staff_conversation_inbox_foundation`()
BEGIN
  DECLARE v_default_branch_id INT UNSIGNED DEFAULT NULL;

  IF EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'branches'
  ) THEN
    SELECT `branch_id`
      INTO v_default_branch_id
    FROM `branches`
    WHERE `is_default` = 1
    ORDER BY `branch_id`
    LIMIT 1;

    IF v_default_branch_id IS NULL THEN
      SELECT `branch_id`
        INTO v_default_branch_id
      FROM `branches`
      ORDER BY `branch_id`
      LIMIT 1;
    END IF;
  END IF;

  IF v_default_branch_id IS NULL THEN
    SET v_default_branch_id = 1;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND column_name = 'branch_id'
  ) THEN
    ALTER TABLE `conversations`
      ADD COLUMN `branch_id` int unsigned NULL AFTER `conversation_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND column_name = 'linked_reservation_id'
  ) THEN
    ALTER TABLE `conversations`
      ADD COLUMN `linked_reservation_id` int unsigned DEFAULT NULL AFTER `intent_detected`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND column_name = 'linked_waiting_list_id'
  ) THEN
    ALTER TABLE `conversations`
      ADD COLUMN `linked_waiting_list_id` int unsigned DEFAULT NULL AFTER `linked_reservation_id`;
  END IF;

  UPDATE `conversations`
  SET `branch_id` = v_default_branch_id
  WHERE `branch_id` IS NULL;

  ALTER TABLE `conversations`
    MODIFY COLUMN `branch_id` int unsigned NOT NULL;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND index_name = 'fk_conversations__branch_id__branches'
  ) THEN
    ALTER TABLE `conversations`
      ADD KEY `fk_conversations__branch_id__branches` (`branch_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND index_name = 'fk_conversations__linked_reservation_id__reservations'
  ) THEN
    ALTER TABLE `conversations`
      ADD KEY `fk_conversations__linked_reservation_id__reservations` (`linked_reservation_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND index_name = 'fk_conversations__linked_waiting_list_id__waiting_list'
  ) THEN
    ALTER TABLE `conversations`
      ADD KEY `fk_conversations__linked_waiting_list_id__waiting_list` (`linked_waiting_list_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND index_name = 'idx_conversations__branch_id__status__created_at'
  ) THEN
    ALTER TABLE `conversations`
      ADD KEY `idx_conversations__branch_id__status__created_at` (`branch_id`,`status`,`created_at`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND index_name = 'idx_conversations__channel__created_at'
  ) THEN
    ALTER TABLE `conversations`
      ADD KEY `idx_conversations__channel__created_at` (`channel`,`created_at`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'conversation_messages'
      AND column_name = 'is_internal_note'
  ) THEN
    ALTER TABLE `conversation_messages`
      ADD COLUMN `is_internal_note` tinyint unsigned NOT NULL DEFAULT '0' AFTER `message_type`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'conversation_messages'
      AND index_name = 'idx_conv_msgs__conv_note_created_at'
  ) THEN
    ALTER TABLE `conversation_messages`
      ADD KEY `idx_conv_msgs__conv_note_created_at` (`conversation_id`,`is_internal_note`,`created_at`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND constraint_name = 'fk_conversations__branch_id__branches'
      AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `conversations`
      ADD CONSTRAINT `fk_conversations__branch_id__branches`
      FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`)
      ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND constraint_name = 'fk_conversations__linked_reservation_id__reservations'
      AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `conversations`
      ADD CONSTRAINT `fk_conversations__linked_reservation_id__reservations`
      FOREIGN KEY (`linked_reservation_id`) REFERENCES `reservations` (`reservation_id`)
      ON DELETE SET NULL ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND constraint_name = 'fk_conversations__linked_waiting_list_id__waiting_list'
      AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `conversations`
      ADD CONSTRAINT `fk_conversations__linked_waiting_list_id__waiting_list`
      FOREIGN KEY (`linked_waiting_list_id`) REFERENCES `waiting_list` (`waiting_id`)
      ON DELETE SET NULL ON UPDATE RESTRICT;
  END IF;
END $$
DELIMITER ;

CALL `sp_staff_conversation_inbox_foundation`();
DROP PROCEDURE IF EXISTS `sp_staff_conversation_inbox_foundation`;
