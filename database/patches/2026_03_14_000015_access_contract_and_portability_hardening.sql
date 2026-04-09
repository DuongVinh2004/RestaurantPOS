SET NAMES utf8mb4;
SET @patch_db := DATABASE();

DROP PROCEDURE IF EXISTS __patch_exec;
DROP PROCEDURE IF EXISTS __patch_exec_if_index_missing;

DELIMITER $$
CREATE PROCEDURE __patch_exec(IN p_sql LONGTEXT)
BEGIN
  SET @__patch_sql := p_sql;
  PREPARE __patch_stmt FROM @__patch_sql;
  EXECUTE __patch_stmt;
  DEALLOCATE PREPARE __patch_stmt;
END $$

CREATE PROCEDURE __patch_exec_if_index_missing(
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
    CALL __patch_exec(p_sql);
  END IF;
END $$
DELIMITER ;

CALL __patch_exec_if_index_missing(
  @patch_db,
  'table_holds',
  'idx_table_holds__session_id__confirmed_reservation_id__user_id',
  'CREATE INDEX `idx_table_holds__session_id__confirmed_reservation_id__user_id` ON `table_holds` (`session_id`, `confirmed_reservation_id`, `user_id`)'
);

DROP PROCEDURE IF EXISTS __patch_exec_if_index_missing;
DROP PROCEDURE IF EXISTS __patch_exec;
