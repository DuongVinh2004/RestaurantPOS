SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `sp_kitchen_branch_routing_scope`;
DELIMITER $$
CREATE PROCEDURE `sp_kitchen_branch_routing_scope`()
BEGIN
    DECLARE v_default_branch_id int unsigned DEFAULT NULL;

    SELECT COALESCE(
               MIN(CASE WHEN b.`is_default` = 1 THEN b.`branch_id` END),
               MIN(CASE WHEN b.`branch_code` = 'MAIN' THEN b.`branch_id` END),
               MIN(CASE WHEN b.`is_active` = 1 THEN b.`branch_id` END),
               MIN(b.`branch_id`)
           )
      INTO v_default_branch_id
      FROM `branches` b;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'kitchen_stations'
           AND COLUMN_NAME = 'branch_id'
    ) THEN
        ALTER TABLE `kitchen_stations`
            ADD COLUMN `branch_id` int unsigned NULL AFTER `station_id`;
    END IF;

    IF v_default_branch_id IS NOT NULL THEN
        UPDATE `kitchen_stations`
           SET `branch_id` = v_default_branch_id
         WHERE `branch_id` IS NULL;
    END IF;

    IF EXISTS (
        SELECT 1
          FROM `kitchen_stations`
         WHERE `branch_id` IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot backfill kitchen_stations.branch_id because no default branch exists.';
    END IF;

    ALTER TABLE `kitchen_stations`
        MODIFY COLUMN `branch_id` int unsigned NOT NULL;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'kitchen_stations'
           AND INDEX_NAME = 'idx_kitchen_stations__branch_id__is_active__name'
    ) THEN
        ALTER TABLE `kitchen_stations`
            ADD KEY `idx_kitchen_stations__branch_id__is_active__name` (`branch_id`, `is_active`, `name`);
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = 'kitchen_stations'
           AND CONSTRAINT_NAME = 'fk_kitchen_stations__branch_id__branches'
           AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE `kitchen_stations`
            ADD CONSTRAINT `fk_kitchen_stations__branch_id__branches`
            FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`)
            ON DELETE RESTRICT ON UPDATE RESTRICT;
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'kitchen_station_category_routes'
           AND COLUMN_NAME = 'branch_id'
    ) THEN
        ALTER TABLE `kitchen_station_category_routes`
            ADD COLUMN `branch_id` int unsigned NULL AFTER `station_id`;
    END IF;

    UPDATE `kitchen_station_category_routes` r
    INNER JOIN `kitchen_stations` s ON s.`station_id` = r.`station_id`
       SET r.`branch_id` = s.`branch_id`
    WHERE r.`branch_id` IS NULL
        OR r.`branch_id` <> s.`branch_id`;

    IF EXISTS (
        SELECT 1
          FROM `kitchen_station_category_routes`
         WHERE `branch_id` IS NULL
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot backfill kitchen_station_category_routes.branch_id because matching kitchen station is missing.';
    END IF;

    ALTER TABLE `kitchen_station_category_routes`
        MODIFY COLUMN `branch_id` int unsigned NOT NULL;

    IF EXISTS (
        SELECT 1
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'kitchen_station_category_routes'
           AND INDEX_NAME = 'uq_kitchen_station_category_routes__category_id'
    ) THEN
        ALTER TABLE `kitchen_station_category_routes`
            DROP INDEX `uq_kitchen_station_category_routes__category_id`;
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'kitchen_station_category_routes'
           AND INDEX_NAME = 'uq_kitchen_station_category_routes__branch_id__category_id'
    ) THEN
        ALTER TABLE `kitchen_station_category_routes`
            ADD UNIQUE KEY `uq_kitchen_station_category_routes__branch_id__category_id` (`branch_id`, `category_id`);
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'kitchen_station_category_routes'
           AND INDEX_NAME = 'idx_kitchen_station_category_routes__branch_id__category_active'
    ) THEN
        ALTER TABLE `kitchen_station_category_routes`
            ADD KEY `idx_kitchen_station_category_routes__branch_id__category_active` (`branch_id`, `category_id`, `is_active`);
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME = 'kitchen_station_category_routes'
           AND CONSTRAINT_NAME = 'fk_kitchen_station_category_routes__branch_id__branches'
           AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE `kitchen_station_category_routes`
            ADD CONSTRAINT `fk_kitchen_station_category_routes__branch_id__branches`
            FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`)
            ON DELETE RESTRICT ON UPDATE RESTRICT;
    END IF;
END $$
DELIMITER ;

CALL `sp_kitchen_branch_routing_scope`();
DROP PROCEDURE IF EXISTS `sp_kitchen_branch_routing_scope`;
