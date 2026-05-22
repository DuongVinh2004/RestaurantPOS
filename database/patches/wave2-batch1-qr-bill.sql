-- Wave 2 Batch 1: Thêm cột qr_payment_token vào bảng restaurant_tables
-- Điều này cho phép mỗi bàn in một mã QR cố định dùng để quét và lấy hoá đơn hiện tại.

ALTER TABLE `restaurant_tables` 
ADD COLUMN `qr_payment_token` char(64) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL AFTER `price`;

ALTER TABLE `restaurant_tables`
ADD UNIQUE KEY `uq_restaurant_tables__qr_payment_token` (`qr_payment_token`);
