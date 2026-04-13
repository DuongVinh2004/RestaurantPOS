SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_reservation_guest_snapshot_support`;
DELIMITER $$
CREATE PROCEDURE `sp_reservation_guest_snapshot_support`()
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'user_id'
      AND is_nullable <> 'YES'
  ) THEN
    ALTER TABLE `reservations`
      MODIFY COLUMN `user_id` int unsigned DEFAULT NULL;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'guest_name'
  ) THEN
    ALTER TABLE `reservations`
      ADD COLUMN `guest_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_id`;
  ELSEIF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'guest_name'
      AND (data_type <> 'varchar' OR character_maximum_length <> 200 OR is_nullable <> 'YES')
  ) THEN
    ALTER TABLE `reservations`
      MODIFY COLUMN `guest_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'guest_phone'
  ) THEN
    ALTER TABLE `reservations`
      ADD COLUMN `guest_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `guest_name`;
  ELSEIF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'guest_phone'
      AND (data_type <> 'varchar' OR character_maximum_length <> 50 OR is_nullable <> 'YES')
  ) THEN
    ALTER TABLE `reservations`
      MODIFY COLUMN `guest_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `guest_name`;
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'guest_email'
  ) THEN
    ALTER TABLE `reservations`
      ADD COLUMN `guest_email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `guest_phone`;
  ELSEIF EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'reservations'
      AND column_name = 'guest_email'
      AND (data_type <> 'varchar' OR character_maximum_length <> 200 OR is_nullable <> 'YES')
  ) THEN
    ALTER TABLE `reservations`
      MODIFY COLUMN `guest_email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `guest_phone`;
  END IF;
END $$
DELIMITER ;

CALL `sp_reservation_guest_snapshot_support`();
DROP PROCEDURE IF EXISTS `sp_reservation_guest_snapshot_support`;
