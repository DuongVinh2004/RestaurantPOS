SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_staff_conversation_workflow_hardening`;
DELIMITER $$
CREATE PROCEDURE `sp_staff_conversation_workflow_hardening`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND column_name = 'workflow_state'
  ) THEN
    ALTER TABLE `conversations`
      ADD COLUMN `workflow_state` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Open' AFTER `status`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND column_name = 'workflow_state_reason'
  ) THEN
    ALTER TABLE `conversations`
      ADD COLUMN `workflow_state_reason` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `workflow_state`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND column_name = 'workflow_state_changed_at'
  ) THEN
    ALTER TABLE `conversations`
      ADD COLUMN `workflow_state_changed_at` datetime(6) DEFAULT NULL AFTER `created_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND column_name = 'first_triaged_at'
  ) THEN
    ALTER TABLE `conversations`
      ADD COLUMN `first_triaged_at` datetime(6) DEFAULT NULL AFTER `workflow_state_changed_at`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND column_name = 'resolved_at'
  ) THEN
    ALTER TABLE `conversations`
      ADD COLUMN `resolved_at` datetime(6) DEFAULT NULL AFTER `first_triaged_at`;
  END IF;

  UPDATE `conversations`
  SET `workflow_state` = CASE `status`
      WHEN 'Pending' THEN 'PendingCustomer'
      WHEN 'Closed' THEN 'Closed'
      ELSE 'Open'
    END
  WHERE `workflow_state` IS NULL OR `workflow_state` = '';

  UPDATE `conversations`
  SET `workflow_state_reason` = CASE `workflow_state`
      WHEN 'PendingCustomer' THEN 'waiting_for_customer'
      WHEN 'Closed' THEN 'closed'
      ELSE 'open'
    END
  WHERE `workflow_state_reason` IS NULL OR `workflow_state_reason` = '';

  UPDATE `conversations`
  SET `workflow_state_changed_at` = COALESCE(`workflow_state_changed_at`, `created_at`, UTC_TIMESTAMP(6))
  WHERE `workflow_state_changed_at` IS NULL;

  UPDATE `conversations`
  SET `resolved_at` = COALESCE(`resolved_at`, `closed_at`)
  WHERE `workflow_state` = 'Resolved'
    AND `resolved_at` IS NULL;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'conversations'
      AND index_name = 'idx_conversations__branch_id__workflow_state__created_at'
  ) THEN
    ALTER TABLE `conversations`
      ADD KEY `idx_conversations__branch_id__workflow_state__created_at` (`branch_id`,`workflow_state`,`created_at`);
  END IF;
END $$
DELIMITER ;

CALL `sp_staff_conversation_workflow_hardening`();
DROP PROCEDURE IF EXISTS `sp_staff_conversation_workflow_hardening`;
