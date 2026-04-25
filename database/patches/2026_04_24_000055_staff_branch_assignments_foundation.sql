SET NAMES utf8mb4;

INSERT INTO `roles` (`role_name`)
SELECT 'Server'
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `role_name` = 'Server');

INSERT INTO `roles` (`role_name`)
SELECT 'Waiter'
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `role_name` = 'Waiter');

INSERT INTO `roles` (`role_name`)
SELECT 'Cashier'
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `role_name` = 'Cashier');

INSERT INTO `roles` (`role_name`)
SELECT 'Kitchen'
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `role_name` = 'Kitchen');

INSERT INTO `roles` (`role_name`)
SELECT 'Manager'
WHERE NOT EXISTS (SELECT 1 FROM `roles` WHERE `role_name` = 'Manager');

CREATE TABLE IF NOT EXISTS `staff_branch_assignments` (
  `staff_branch_assignment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `branch_id` int unsigned NOT NULL,
  `is_primary` tinyint unsigned NOT NULL DEFAULT 0,
  `assigned_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `revoked_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`staff_branch_assignment_id`),
  UNIQUE KEY `uq_staff_branch_assignments__user_id__branch_id` (`user_id`, `branch_id`),
  KEY `idx_staff_branch_assignments__branch_id__revoked_at` (`branch_id`, `revoked_at`),
  KEY `idx_staff_branch_assignments__user_id__revoked_at__primary` (`user_id`, `revoked_at`, `is_primary`),
  CONSTRAINT `fk_staff_branch_assignments__branch_id__branches`
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_staff_branch_assignments__user_id__users`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS `sp_staff_branch_assignments_foundation`;

DELIMITER $$

CREATE PROCEDURE `sp_staff_branch_assignments_foundation`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'staff_branch_assignments'
       AND index_name = 'uq_staff_branch_assignments__user_id__branch_id'
  ) THEN
    ALTER TABLE `staff_branch_assignments`
      ADD UNIQUE KEY `uq_staff_branch_assignments__user_id__branch_id` (`user_id`, `branch_id`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'staff_branch_assignments'
       AND index_name = 'idx_staff_branch_assignments__branch_id__revoked_at'
  ) THEN
    ALTER TABLE `staff_branch_assignments`
      ADD KEY `idx_staff_branch_assignments__branch_id__revoked_at` (`branch_id`, `revoked_at`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'staff_branch_assignments'
       AND index_name = 'idx_staff_branch_assignments__user_id__revoked_at__primary'
  ) THEN
    ALTER TABLE `staff_branch_assignments`
      ADD KEY `idx_staff_branch_assignments__user_id__revoked_at__primary` (`user_id`, `revoked_at`, `is_primary`);
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'staff_branch_assignments'
       AND constraint_name = 'fk_staff_branch_assignments__branch_id__branches'
       AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `staff_branch_assignments`
      ADD CONSTRAINT `fk_staff_branch_assignments__branch_id__branches`
      FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
  END IF;

  IF NOT EXISTS (
    SELECT 1
      FROM information_schema.table_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'staff_branch_assignments'
       AND constraint_name = 'fk_staff_branch_assignments__user_id__users'
       AND constraint_type = 'FOREIGN KEY'
  ) THEN
    ALTER TABLE `staff_branch_assignments`
      ADD CONSTRAINT `fk_staff_branch_assignments__user_id__users`
      FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT;
  END IF;
END $$

DELIMITER ;

CALL `sp_staff_branch_assignments_foundation`();
DROP PROCEDURE IF EXISTS `sp_staff_branch_assignments_foundation`;
