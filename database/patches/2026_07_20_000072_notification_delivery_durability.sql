SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_notification_delivery_durability`;
DELIMITER $$

CREATE PROCEDURE `sp_notification_delivery_durability`()
BEGIN
  IF EXISTS (
    SELECT 1
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name = 'notification_delivery_attempts'
  ) THEN
    ALTER TABLE `notification_delivery_attempts`
      MODIFY COLUMN `status` enum('Started','Succeeded','Failed','Deferred','Suppressed','Unknown') NOT NULL;
  END IF;
END $$

DELIMITER ;
CALL `sp_notification_delivery_durability`();
DROP PROCEDURE IF EXISTS `sp_notification_delivery_durability`;

SELECT 'notification_delivery_durability:ok' AS checkpoint;
