SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `__create_index_if_missing`;

DELIMITER $$
CREATE PROCEDURE `__create_index_if_missing`(
    IN p_schema VARCHAR(128),
    IN p_table VARCHAR(128),
    IN p_index VARCHAR(128),
    IN p_sql LONGTEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.statistics
         WHERE table_schema = p_schema
           AND table_name = p_table
           AND index_name = p_index
    ) THEN
        SET @__create_index_sql := p_sql;
        PREPARE __create_index_stmt FROM @__create_index_sql;
        EXECUTE __create_index_stmt;
        DEALLOCATE PREPARE __create_index_stmt;
    END IF;
END$$
DELIMITER ;

CALL `__create_index_if_missing`(
    DATABASE(),
    'user_vouchers',
    'idx_user_vouchers__voucher_id__is_used__user_id',
    'CREATE INDEX `idx_user_vouchers__voucher_id__is_used__user_id` ON `user_vouchers` (`voucher_id`, `is_used`, `user_id`)'
);

CALL `__create_index_if_missing`(
    DATABASE(),
    'table_holds',
    'idx_table_holds__session_id__confirmed_reservation_id',
    'CREATE INDEX `idx_table_holds__session_id__confirmed_reservation_id` ON `table_holds` (`session_id`, `confirmed_reservation_id`)'
);

DROP PROCEDURE IF EXISTS `__create_index_if_missing`;
