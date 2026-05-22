-- Wave 2 Batch 1: Thêm cột qr_payment_token vào bảng restaurant_tables
-- Điều này cho phép mỗi bàn in một mã QR cố định dùng để quét và lấy hoá đơn hiện tại.

SET NAMES utf8mb4;
SET @patch_db := DATABASE();

DROP PROCEDURE IF EXISTS __patch_exec;
DROP PROCEDURE IF EXISTS __patch_exec_if_column_missing;
DROP PROCEDURE IF EXISTS __patch_exec_if_index_missing;

DELIMITER $$
CREATE PROCEDURE __patch_exec(IN p_sql LONGTEXT)
BEGIN
  SET @__patch_sql := p_sql;
  PREPARE __patch_stmt FROM @__patch_sql;
  EXECUTE __patch_stmt;
  DEALLOCATE PREPARE __patch_stmt;
END $$

CREATE PROCEDURE __patch_exec_if_column_missing(
  IN p_schema VARCHAR(128),
  IN p_table VARCHAR(128),
  IN p_column VARCHAR(128),
  IN p_sql LONGTEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = p_schema
      AND table_name = p_table
      AND column_name = p_column
  ) THEN
    CALL __patch_exec(p_sql);
  END IF;
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

CALL __patch_exec_if_column_missing(
  @patch_db,
  'restaurant_tables',
  'qr_payment_token',
  'ALTER TABLE `restaurant_tables` ADD COLUMN `qr_payment_token` char(64) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL AFTER `price`'
);

CALL __patch_exec_if_index_missing(
  @patch_db,
  'restaurant_tables',
  'uq_restaurant_tables__qr_payment_token',
  'ALTER TABLE `restaurant_tables` ADD UNIQUE KEY `uq_restaurant_tables__qr_payment_token` (`qr_payment_token`)'
);

DROP PROCEDURE IF EXISTS __patch_exec_if_index_missing;
DROP PROCEDURE IF EXISTS __patch_exec_if_column_missing;
DROP PROCEDURE IF EXISTS __patch_exec;
