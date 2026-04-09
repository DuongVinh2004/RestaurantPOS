DROP PROCEDURE IF EXISTS `sp_branch_scheduling_policy_foundation`;
DELIMITER $$
CREATE PROCEDURE `sp_branch_scheduling_policy_foundation`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'branches'
      AND column_name = 'business_hours'
  ) THEN
    ALTER TABLE `branches`
      ADD COLUMN `business_hours` JSON NULL AFTER `currency`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'branches'
      AND column_name = 'closure_windows'
  ) THEN
    ALTER TABLE `branches`
      ADD COLUMN `closure_windows` JSON NULL AFTER `business_hours`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'branches'
      AND column_name = 'booking_policy'
  ) THEN
    ALTER TABLE `branches`
      ADD COLUMN `booking_policy` JSON NULL AFTER `closure_windows`;
  END IF;
END $$
DELIMITER ;

CALL `sp_branch_scheduling_policy_foundation`();
DROP PROCEDURE IF EXISTS `sp_branch_scheduling_policy_foundation`;
