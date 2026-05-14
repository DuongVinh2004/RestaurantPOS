SET NAMES utf8mb4;

INSERT INTO `roles` (`role_id`, `role_name`)
VALUES
  (1, 'Admin'),
  (2, 'Staff'),
  (3, 'Customer'),
  (4, 'Server'),
  (5, 'Waiter'),
  (6, 'Cashier'),
  (7, 'Kitchen'),
  (8, 'Manager')
ON DUPLICATE KEY UPDATE
  `role_name` = VALUES(`role_name`);

INSERT INTO `branches` (
  `branch_code`,
  `branch_name`,
  `description`,
  `timezone`,
  `currency`,
  `business_hours`,
  `closure_windows`,
  `booking_policy`,
  `is_active`,
  `is_default`,
  `row_version`
)
VALUES
  (
    'MS-HK',
    'Mộc Sen Bistro - Hoàn Kiếm',
    '24 Tràng Tiền, Hoàn Kiếm, Hà Nội. Chi nhánh trung tâm, cao điểm tối.',
    'Asia/Ho_Chi_Minh',
    'VND',
    CAST('[{"day_of_week":0,"periods":[{"start_time":"10:00","end_time":"22:00"}]},{"day_of_week":1,"periods":[{"start_time":"10:00","end_time":"22:00"}]},{"day_of_week":2,"periods":[{"start_time":"10:00","end_time":"22:00"}]},{"day_of_week":3,"periods":[{"start_time":"10:00","end_time":"22:00"}]},{"day_of_week":4,"periods":[{"start_time":"10:00","end_time":"22:30"}]},{"day_of_week":5,"periods":[{"start_time":"09:30","end_time":"22:30"}]},{"day_of_week":6,"periods":[{"start_time":"09:30","end_time":"22:00"}]}]' AS JSON),
    CAST('[]' AS JSON),
    CAST('{"reservation":{"min_lead_time_minutes":30,"max_advance_time_minutes":259200,"service_buffer_minutes":15,"default_duration_minutes":90},"deposit":{"required_from_guest_count":8,"default_amount":"200000.00"}}' AS JSON),
    1,
    1,
    1
  ),
  (
    'MS-CG',
    'Mộc Sen Bistro - Cầu Giấy',
    '88 Duy Tân, Cầu Giấy, Hà Nội. Chi nhánh văn phòng, cao điểm trưa.',
    'Asia/Ho_Chi_Minh',
    'VND',
    CAST('[{"day_of_week":0,"periods":[{"start_time":"09:30","end_time":"21:30"}]},{"day_of_week":1,"periods":[{"start_time":"09:30","end_time":"21:30"}]},{"day_of_week":2,"periods":[{"start_time":"09:30","end_time":"21:30"}]},{"day_of_week":3,"periods":[{"start_time":"09:30","end_time":"21:30"}]},{"day_of_week":4,"periods":[{"start_time":"09:30","end_time":"22:00"}]},{"day_of_week":5,"periods":[{"start_time":"10:00","end_time":"22:00"}]},{"day_of_week":6,"periods":[{"start_time":"10:00","end_time":"21:30"}]}]' AS JSON),
    CAST('[]' AS JSON),
    CAST('{"reservation":{"min_lead_time_minutes":20,"max_advance_time_minutes":259200,"service_buffer_minutes":10,"default_duration_minutes":75},"deposit":{"required_from_guest_count":10,"default_amount":"200000.00"}}' AS JSON),
    1,
    0,
    1
  ),
  (
    'MS-TD',
    'Mộc Sen Bistro - Thảo Điền',
    '16 Xuân Thủy, Thảo Điền, TP.HCM. Chi nhánh gia đình và nhóm bạn.',
    'Asia/Ho_Chi_Minh',
    'VND',
    CAST('[{"day_of_week":0,"periods":[{"start_time":"10:00","end_time":"22:00"}]},{"day_of_week":1,"periods":[{"start_time":"10:00","end_time":"22:00"}]},{"day_of_week":2,"periods":[{"start_time":"10:00","end_time":"22:00"}]},{"day_of_week":3,"periods":[{"start_time":"10:00","end_time":"22:00"}]},{"day_of_week":4,"periods":[{"start_time":"10:00","end_time":"22:30"}]},{"day_of_week":5,"periods":[{"start_time":"09:30","end_time":"22:30"}]},{"day_of_week":6,"periods":[{"start_time":"09:30","end_time":"22:00"}]}]' AS JSON),
    CAST('[]' AS JSON),
    CAST('{"reservation":{"min_lead_time_minutes":30,"max_advance_time_minutes":259200,"service_buffer_minutes":15,"default_duration_minutes":105},"deposit":{"required_from_guest_count":8,"default_amount":"300000.00"}}' AS JSON),
    1,
    0,
    1
  )
ON DUPLICATE KEY UPDATE
  `branch_name` = VALUES(`branch_name`),
  `description` = VALUES(`description`),
  `timezone` = VALUES(`timezone`),
  `currency` = VALUES(`currency`),
  `business_hours` = VALUES(`business_hours`),
  `closure_windows` = VALUES(`closure_windows`),
  `booking_policy` = VALUES(`booking_policy`),
  `is_active` = VALUES(`is_active`),
  `is_default` = VALUES(`is_default`);

UPDATE `branches`
   SET `is_default` = CASE WHEN `branch_code` = 'MS-HK' THEN 1 ELSE 0 END
 WHERE `branch_code` <> 'MS-HK' OR `is_default` <> 1;

INSERT INTO `table_templates` (`template_code`, `seats`, `description`)
VALUES
  ('MS-2P', 2, 'Bàn 2 khách tiêu chuẩn Mộc Sen'),
  ('MS-4P', 4, 'Bàn 4 khách tiêu chuẩn Mộc Sen'),
  ('MS-6P', 6, 'Bàn 6 khách tiêu chuẩn Mộc Sen'),
  ('MS-8P', 8, 'Bàn 8 khách phòng riêng'),
  ('MS-12P', 12, 'Bàn 12 khách phòng riêng')
ON DUPLICATE KEY UPDATE
  `seats` = VALUES(`seats`),
  `description` = VALUES(`description`);

INSERT INTO `restaurant_tables` (
  `table_code`,
  `branch_id`,
  `template_id`,
  `zone`,
  `pos_x`,
  `pos_y`,
  `status`,
  `description`,
  `is_deleted`,
  `price`
)
SELECT
  CONCAT(b.`branch_code`, '-', z.`zone_code`, '-', LPAD(n.`table_no`, 2, '0')) AS `table_code`,
  b.`branch_id`,
  tt.`template_id`,
  z.`zone_name`,
  n.`table_no`,
  z.`zone_row`,
  'Available',
  CONCAT('Bàn ', tt.`seats`, ' khách tại ', z.`zone_name`, '.'),
  0,
  NULL
FROM `branches` b
JOIN (
  SELECT 'Main Hall' AS `zone_name`, 'MAIN' AS `zone_code`, 1 AS `zone_row`
  UNION ALL SELECT 'Window Zone', 'WINDOW', 2
  UNION ALL SELECT 'Garden Corner', 'GARDEN', 3
  UNION ALL SELECT 'Private Room', 'PRIVATE', 4
) z
JOIN (
  SELECT 1 AS `table_no`
  UNION ALL SELECT 2
  UNION ALL SELECT 3
  UNION ALL SELECT 4
) n
JOIN `table_templates` tt
  ON tt.`template_code` = CASE
    WHEN z.`zone_code` IN ('MAIN', 'WINDOW') AND n.`table_no` = 1 THEN 'MS-2P'
    WHEN z.`zone_code` IN ('MAIN', 'WINDOW') THEN 'MS-4P'
    WHEN z.`zone_code` = 'GARDEN' AND n.`table_no` <= 2 THEN 'MS-4P'
    WHEN z.`zone_code` = 'GARDEN' THEN 'MS-6P'
    WHEN z.`zone_code` = 'PRIVATE' AND n.`table_no` <= 2 THEN 'MS-6P'
    WHEN z.`zone_code` = 'PRIVATE' AND n.`table_no` = 3 THEN 'MS-8P'
    ELSE 'MS-12P'
  END
WHERE b.`branch_code` IN ('MS-HK', 'MS-CG', 'MS-TD')
ON DUPLICATE KEY UPDATE
  `branch_id` = VALUES(`branch_id`),
  `template_id` = VALUES(`template_id`),
  `zone` = VALUES(`zone`),
  `pos_x` = VALUES(`pos_x`),
  `pos_y` = VALUES(`pos_y`),
  `status` = VALUES(`status`),
  `description` = VALUES(`description`),
  `is_deleted` = VALUES(`is_deleted`);

CREATE TEMPORARY TABLE `tmp_moc_sen_categories` (
  `name` varchar(150) NOT NULL,
  `description` varchar(400) NOT NULL,
  `sort_order` int NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=Memory DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tmp_moc_sen_categories` (`name`, `description`, `sort_order`)
VALUES
  ('Khai vị', 'Món mở đầu nhẹ, dễ chia sẻ tại Mộc Sen.', 10),
  ('Món chính', 'Các món Việt đậm vị cho bữa chính.', 20),
  ('Cơm & bún/phở', 'Các phần ăn quen thuộc cho trưa văn phòng và bữa tối nhanh.', 30),
  ('Rau & chay', 'Món rau, nấm và lựa chọn chay nhẹ.', 40),
  ('Tráng miệng', 'Món ngọt nhẹ sau bữa ăn.', 50),
  ('Đồ uống', 'Đồ uống thanh mát đi cùng bữa Việt.', 60),
  ('Combo', 'Set món giúp khách chọn nhanh theo dịp.', 70);

INSERT INTO `menu_categories` (`name`, `description`, `sort_order`, `is_deleted`)
SELECT `name`, `description`, `sort_order`, 0
FROM `tmp_moc_sen_categories`
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `sort_order` = VALUES(`sort_order`),
  `is_deleted` = VALUES(`is_deleted`);

CREATE TEMPORARY TABLE `tmp_moc_sen_menu_items` (
  `code` varchar(50) NOT NULL,
  `category_name` varchar(150) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` varchar(1000) NOT NULL,
  `price` decimal(14,2) NOT NULL,
  `img_url` varchar(255) NOT NULL,
  `preorder_quota_per_day` int unsigned NOT NULL,
  `preorder_cutoff_minutes` int unsigned NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=Memory DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tmp_moc_sen_menu_items` (`code`, `category_name`, `name`, `description`, `price`, `img_url`, `preorder_quota_per_day`, `preorder_cutoff_minutes`)
VALUES
  ('MS-GOI-CUON-TOM-THIT', 'Khai vị', 'Gỏi cuốn tôm thịt', 'Tôm, thịt mềm, rau sống, bún mảnh, sốt đậu phộng.', 59000.00, '/customer-web/menu/goi-cuon-tom-thit.jpg', 90, 0),
  ('MS-NEM-SEN-GION', 'Khai vị', 'Nem sen giòn', 'Nem chiên giòn nhân thịt, nấm, miến và củ sen.', 69000.00, '/customer-web/menu/nem-sen-gion.jpg', 90, 0),
  ('MS-SALAD-XOAI-TOM', 'Khai vị', 'Salad xoài tôm', 'Xoài xanh, tôm áp chảo, rau thơm, sốt chua ngọt.', 79000.00, '/customer-web/menu/salad-xoai-tom.jpg', 70, 0),
  ('MS-CHA-MUC-MINI', 'Khai vị', 'Chả mực mini', 'Chả mực giã tay, chiên vàng, dùng kèm tương ớt Mộc Sen.', 89000.00, '/customer-web/menu/cha-muc-mini.jpg', 60, 0),
  ('MS-DAU-HU-RANG-MUOI', 'Khai vị', 'Đậu hũ rang muối', 'Đậu hũ non áo bột mỏng, rang muối sả giòn.', 55000.00, '/customer-web/menu/dau-hu-rang-muoi.jpg', 80, 0),
  ('MS-GOI-GA-BAP-CHUOI', 'Khai vị', 'Gỏi gà bắp chuối', 'Gà xé, bắp chuối, rau răm, hành phi và nước mắm chua ngọt.', 76000.00, '/customer-web/menu/goi-ga-bap-chuoi.jpg', 70, 0),
  ('MS-CHA-GIO-HAI-SAN', 'Khai vị', 'Chả giò hải sản', 'Cuốn hải sản chiên giòn, dùng kèm rau sống và sốt Mộc Sen.', 89000.00, '/customer-web/menu/cha-gio-hai-san.jpg', 60, 0),
  ('MS-COM-GA-LA-SEN', 'Món chính', 'Cơm gà lá sen', 'Gà áp chảo, cơm dẻo, sốt gừng nhẹ, rau củ theo mùa.', 89000.00, '/customer-web/menu/com-ga-la-sen.jpg', 100, 0),
  ('MS-BUN-BO-MOC-SEN', 'Món chính', 'Bún bò Mộc Sen', 'Nước dùng đậm vị, thịt bò mềm, rau thơm và sa tế nhẹ.', 95000.00, '/customer-web/menu/bun-bo-moc-sen.jpg', 90, 0),
  ('MS-CA-KHO-NIEU-DAT', 'Món chính', 'Cá kho niêu đất', 'Cá kho tiêu, nước màu truyền thống, ăn kèm cơm trắng.', 119000.00, '/customer-web/menu/ca-kho-nieu-dat.jpg', 60, 0),
  ('MS-BO-LUC-LAC-SOT-TIEU', 'Món chính', 'Bò lúc lắc sốt tiêu', 'Bò mềm áp chảo, khoai tây, salad và sốt tiêu đen.', 139000.00, '/customer-web/menu/bo-luc-lac-sot-tieu.jpg', 70, 0),
  ('MS-GA-NUONG-MAT-ONG', 'Món chính', 'Gà nướng mật ong', 'Gà nướng vàng, mật ong nhẹ, rau củ nướng.', 129000.00, '/customer-web/menu/ga-nuong-mat-ong.jpg', 70, 0),
  ('MS-TOM-SOT-ME', 'Món chính', 'Tôm sốt me', 'Tôm áp chảo, sốt me chua ngọt, hành phi.', 149000.00, '/customer-web/menu/tom-sot-me.jpg', 60, 0),
  ('MS-SUON-NON-RIM-MAM', 'Món chính', 'Sườn non rim mắm', 'Sườn non rim mắm tỏi, ăn kèm dưa leo và cơm.', 129000.00, '/customer-web/menu/suon-non-rim-mam.jpg', 70, 0),
  ('MS-VIT-AP-CHAO-SOT-ME', 'Món chính', 'Vịt áp chảo sốt me', 'Vịt áp chảo da giòn, sốt me chua ngọt và rau thơm.', 159000.00, '/customer-web/menu/vit-ap-chao-sot-me.jpg', 50, 0),
  ('MS-CA-CHIEN-MAM-XOAI', 'Món chính', 'Cá chiên mắm xoài', 'Cá chiên giòn, mắm xoài xanh và rau sống ăn kèm.', 149000.00, '/customer-web/menu/ca-chien-mam-xoai.jpg', 55, 0),
  ('MS-BO-KHO-BANH-MI', 'Món chính', 'Bò kho bánh mì', 'Bò kho mềm, cà rốt, nước sốt thơm và bánh mì nóng.', 99000.00, '/customer-web/menu/bo-kho-banh-mi.jpg', 70, 0),
  ('MS-PHO-GA-THAO-MOC', 'Cơm & bún/phở', 'Phở gà thảo mộc', 'Nước dùng thanh, gà xé, rau thơm và bánh phở mềm.', 79000.00, '/customer-web/menu/pho-ga-thao-moc.jpg', 80, 0),
  ('MS-BUN-CHA-HA-NOI', 'Cơm & bún/phở', 'Bún chả Hà Nội', 'Thịt nướng than, nước chấm chua ngọt, bún và rau sống.', 89000.00, '/customer-web/menu/bun-cha-ha-noi.jpg', 90, 0),
  ('MS-COM-SUON-MAT-ONG', 'Cơm & bún/phở', 'Cơm sườn mật ong', 'Sườn nướng mật ong, cơm trắng, trứng và đồ chua.', 99000.00, '/customer-web/menu/com-suon-mat-ong.jpg', 80, 0),
  ('MS-MI-XAO-BO-RAU-CU', 'Cơm & bún/phở', 'Mì xào bò rau củ', 'Mì xào, bò mềm, rau củ giòn và sốt hài hòa.', 92000.00, '/customer-web/menu/mi-xao-bo-rau-cu.jpg', 80, 0),
  ('MS-BUN-THIT-NUONG', 'Cơm & bún/phở', 'Bún thịt nướng', 'Thịt nướng, bún, rau sống, đồ chua và nước mắm.', 85000.00, '/customer-web/menu/bun-thit-nuong.jpg', 90, 0),
  ('MS-COM-BO-XAO-SATE', 'Cơm & bún/phở', 'Cơm bò xào sa tế', 'Bò xào sa tế cay nhẹ, cơm trắng, dưa leo và đồ chua.', 109000.00, '/customer-web/menu/com-bo-xao-sate.jpg', 70, 0),
  ('MS-MIEN-GA-NAM', 'Cơm & bún/phở', 'Miến gà nấm', 'Miến dai, gà xé, nấm hương và nước dùng thanh.', 79000.00, '/customer-web/menu/mien-ga-nam.jpg', 75, 0),
  ('MS-RAU-CU-XAO-TOI', 'Rau & chay', 'Rau củ xào tỏi', 'Rau củ theo mùa xào tỏi thơm, giữ độ giòn.', 55000.00, '/customer-web/menu/rau-cu-xao-toi.jpg', 80, 0),
  ('MS-DAU-HU-SOT-NAM', 'Rau & chay', 'Đậu hũ sốt nấm', 'Đậu hũ non, nấm đông cô, sốt thanh nhẹ.', 65000.00, '/customer-web/menu/dau-hu-sot-nam.jpg', 70, 0),
  ('MS-NAM-KHO-TIEU', 'Rau & chay', 'Nấm kho tiêu', 'Nấm kho tiêu, hành boa-rô, ăn kèm cơm nóng.', 69000.00, '/customer-web/menu/nam-kho-tieu.jpg', 70, 0),
  ('MS-CANH-RAU-CU-HAT-SEN', 'Rau & chay', 'Canh rau củ hạt sen', 'Canh rau củ, hạt sen, nước dùng rau củ nhẹ.', 59000.00, '/customer-web/menu/canh-rau-cu-hat-sen.jpg', 70, 0),
  ('MS-GOI-RAU-MAM-BO', 'Rau & chay', 'Gỏi rau mầm bò', 'Rau mầm, bò áp chảo, sốt mè rang.', 89000.00, '/customer-web/menu/goi-rau-mam-bo.jpg', 70, 0),
  ('MS-CA-TIM-NUONG-MO-HANH', 'Rau & chay', 'Cà tím nướng mỡ hành', 'Cà tím nướng mềm, mỡ hành, đậu phộng và nước mắm chay.', 59000.00, '/customer-web/menu/ca-tim-nuong-mo-hanh.jpg', 70, 0),
  ('MS-DAU-BAP-XAO-TOI', 'Rau & chay', 'Đậu bắp xào tỏi', 'Đậu bắp xào tỏi nhanh lửa, giữ độ giòn và vị ngọt tự nhiên.', 52000.00, '/customer-web/menu/dau-bap-xao-toi.jpg', 75, 0),
  ('MS-CHE-SEN-LONG-NHAN', 'Tráng miệng', 'Chè sen long nhãn', 'Hạt sen mềm, long nhãn ngọt thanh, dùng lạnh.', 45000.00, '/customer-web/menu/che-sen-long-nhan.jpg', 90, 0),
  ('MS-PANNA-COTTA-DUA', 'Tráng miệng', 'Panna cotta dừa', 'Kem dừa mềm mịn, sốt xoài chua nhẹ.', 49000.00, '/customer-web/menu/panna-cotta-dua.jpg', 80, 0),
  ('MS-BANH-FLAN-CA-PHE', 'Tráng miệng', 'Bánh flan cà phê', 'Flan mềm, caramel, cà phê đậm nhẹ.', 42000.00, '/customer-web/menu/banh-flan-ca-phe.jpg', 90, 0),
  ('MS-KEM-DUA-NON', 'Tráng miệng', 'Kem dừa non', 'Kem dừa, dừa non, đậu phộng rang.', 55000.00, '/customer-web/menu/kem-dua-non.jpg', 80, 0),
  ('MS-SUA-CHUA-NEP-CAM', 'Tráng miệng', 'Sữa chua nếp cẩm', 'Sữa chua mịn, nếp cẩm dẻo, vị ngọt dịu.', 45000.00, '/customer-web/menu/sua-chua-nep-cam.jpg', 80, 0),
  ('MS-BANH-CHUOI-NUONG', 'Tráng miệng', 'Bánh chuối nướng', 'Chuối chín nướng thơm, nước cốt dừa và mè rang.', 49000.00, '/customer-web/menu/banh-chuoi-nuong.jpg', 80, 0),
  ('MS-TAU-HU-NUOC-DUONG', 'Tráng miệng', 'Tàu hũ nước đường', 'Tàu hũ mềm, nước đường gừng và trân châu nhỏ.', 39000.00, '/customer-web/menu/tau-hu-nuoc-duong.jpg', 90, 0),
  ('MS-TRA-SEN-LANH', 'Đồ uống', 'Trà sen lạnh', 'Trà sen thơm nhẹ, vị thanh, ít ngọt.', 35000.00, '/customer-web/menu/tra-sen-lanh.jpg', 120, 0),
  ('MS-NUOC-EP-CAM-CA-ROT', 'Đồ uống', 'Nước ép cam cà rốt', 'Cam tươi và cà rốt ép lạnh.', 49000.00, '/customer-web/menu/nuoc-ep-cam-ca-rot.jpg', 90, 0),
  ('MS-CA-PHE-SUA-DA', 'Đồ uống', 'Cà phê sữa đá', 'Cà phê rang đậm, sữa đặc, đá viên.', 39000.00, '/customer-web/menu/ca-phe-sua-da.jpg', 100, 0),
  ('MS-NUOC-CHANH-SA', 'Đồ uống', 'Nước chanh sả', 'Chanh tươi, sả, mật ong nhẹ.', 39000.00, '/customer-web/menu/nuoc-chanh-sa.jpg', 100, 0),
  ('MS-SINH-TO-XOAI', 'Đồ uống', 'Sinh tố xoài', 'Xoài chín, sữa chua, đá xay.', 55000.00, '/customer-web/menu/sinh-to-xoai.jpg', 80, 0),
  ('MS-TRA-TAC-MAT-ONG', 'Đồ uống', 'Trà tắc mật ong', 'Trà tắc mát, mật ong nhẹ và lát tắc tươi.', 39000.00, '/customer-web/menu/tra-tac-mat-ong.jpg', 100, 0),
  ('MS-SET-TRUA-VAN-PHONG', 'Combo', 'Set trưa văn phòng', 'Món chính + canh nhỏ + trà sen.', 149000.00, '/customer-web/menu/set-trua-van-phong.jpg', 70, 15),
  ('MS-SET-GIA-DINH-MOC-SEN', 'Combo', 'Set gia đình Mộc Sen', '4 món chính, 1 rau, 1 tráng miệng.', 399000.00, '/customer-web/menu/set-gia-dinh-moc-sen.jpg', 40, 30),
  ('MS-SET-HEN-HO-BEN-CUA-SO', 'Combo', 'Set hẹn hò bên cửa sổ', 'Khai vị, 2 món chính, 2 đồ uống, 1 tráng miệng.', 299000.00, '/customer-web/menu/set-hen-ho-ben-cua-so.jpg', 35, 30),
  ('MS-SET-BEP-TRUONG-DE-XUAT', 'Combo', 'Set bếp trưởng đề xuất', 'Combo 5 món theo mùa cho nhóm 4 khách, cân bằng khai vị, món chính và tráng miệng.', 459000.00, '/customer-web/menu/set-bep-truong-de-xuat.jpg', 30, 30);

INSERT INTO `menu_items` (
  `category_id`,
  `code`,
  `name`,
  `description`,
  `img_url`,
  `is_available`,
  `is_preorder_enabled`,
  `preorder_quota_per_day`,
  `preorder_cutoff_minutes`
)
SELECT
  c.`category_id`,
  i.`code`,
  i.`name`,
  i.`description`,
  i.`img_url`,
  1,
  1,
  i.`preorder_quota_per_day`,
  i.`preorder_cutoff_minutes`
FROM `tmp_moc_sen_menu_items` i
JOIN `menu_categories` c ON c.`name` = i.`category_name`
ON DUPLICATE KEY UPDATE
  `category_id` = VALUES(`category_id`),
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `img_url` = VALUES(`img_url`),
  `is_available` = VALUES(`is_available`),
  `is_preorder_enabled` = VALUES(`is_preorder_enabled`),
  `preorder_quota_per_day` = VALUES(`preorder_quota_per_day`),
  `preorder_cutoff_minutes` = VALUES(`preorder_cutoff_minutes`);

INSERT INTO `menu_item_prices` (`item_id`, `price`, `currency`, `effective_from`, `effective_to`)
SELECT
  mi.`item_id`,
  i.`price`,
  'VND',
  '2026-05-13 00:00:00.000000',
  NULL
FROM `tmp_moc_sen_menu_items` i
JOIN `menu_items` mi ON mi.`code` = i.`code`
WHERE NOT EXISTS (
  SELECT 1
  FROM `menu_item_prices` p
  WHERE p.`item_id` = mi.`item_id`
    AND p.`currency` = 'VND'
    AND p.`effective_from` = '2026-05-13 00:00:00.000000'
);

INSERT INTO `loyalty_tiers` (`tier_code`, `tier_name`, `min_points`, `benefits_json`, `is_active`)
VALUES
  ('SEN_MOI', 'Sen Mới', 0, CAST('{"benefits":["Voucher khách mới","Lưu món yêu thích"]}' AS JSON), 1),
  ('SEN_BAC', 'Sen Bạc', 200, CAST('{"benefits":["Giảm 5% đồ uống","Ưu đãi lunch"]}' AS JSON), 1),
  ('SEN_VANG', 'Sen Vàng', 500, CAST('{"benefits":["Giảm 8% hóa đơn","Voucher sinh nhật"]}' AS JSON), 1),
  ('SEN_KIM_CUONG', 'Sen Kim Cương', 1000, CAST('{"benefits":["Ưu tiên đặt bàn","Quà sinh nhật","Hỗ trợ nhóm lớn"]}' AS JSON), 1)
ON DUPLICATE KEY UPDATE
  `tier_name` = VALUES(`tier_name`),
  `min_points` = VALUES(`min_points`),
  `benefits_json` = VALUES(`benefits_json`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `vouchers` (
  `code`,
  `description`,
  `discount_type`,
  `discount_value`,
  `free_item_id`,
  `free_item_qty`,
  `max_usage`,
  `max_usage_per_user`,
  `min_spend`,
  `start_date`,
  `expiry_date`,
  `is_active`
)
VALUES
  ('WELCOME30', 'Khách mới Mộc Sen, đơn từ 200.000đ, dùng 1 lần mỗi khách.', 'Fixed', 30000.00, NULL, NULL, 1000, 1, 200000.00, '2026-05-13 00:00:00.000000', '2027-05-13 23:59:59.999999', 1),
  ('SENLUNCH10', 'Bữa trưa văn phòng 10:00-14:00 từ thứ 2 đến thứ 6.', 'Percent', 10.00, NULL, NULL, 2000, 5, 0.00, '2026-05-13 00:00:00.000000', '2027-05-13 23:59:59.999999', 1),
  ('FAMILY50', 'Gia đình cuối tuần từ 4 khách, đơn từ 500.000đ.', 'Fixed', 50000.00, NULL, NULL, 1000, 3, 500000.00, '2026-05-13 00:00:00.000000', '2027-05-13 23:59:59.999999', 1),
  ('BIRTHDAY100', 'Sinh nhật thành viên Sen Vàng trở lên trong tháng sinh nhật.', 'Fixed', 100000.00, NULL, NULL, 500, 1, 0.00, '2026-05-13 00:00:00.000000', '2027-05-13 23:59:59.999999', 1),
  ('WINDOWTEA', 'Đặt bàn Window Zone trước 2 giờ, tặng 1 Trà sen lạnh khi dùng tại chỗ.', 'FreeItem', NULL, (SELECT `item_id` FROM `menu_items` WHERE `code` = 'MS-TRA-SEN-LANH' LIMIT 1), 1, 1000, 5, 0.00, '2026-05-13 00:00:00.000000', '2027-05-13 23:59:59.999999', 1)
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `discount_type` = VALUES(`discount_type`),
  `discount_value` = VALUES(`discount_value`),
  `free_item_id` = VALUES(`free_item_id`),
  `free_item_qty` = VALUES(`free_item_qty`),
  `max_usage` = VALUES(`max_usage`),
  `max_usage_per_user` = VALUES(`max_usage_per_user`),
  `min_spend` = VALUES(`min_spend`),
  `start_date` = VALUES(`start_date`),
  `expiry_date` = VALUES(`expiry_date`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `kitchen_stations` (`branch_id`, `code`, `name`, `description`, `output_mode`, `is_active`)
SELECT b.`branch_id`, CONCAT(b.`branch_code`, '-HOT'), 'Bếp nóng', 'Món chính, cơm, bún/phở và combo nóng.', 'KDS', 1
FROM `branches` b
WHERE b.`branch_code` IN ('MS-HK', 'MS-CG', 'MS-TD')
UNION ALL
SELECT b.`branch_id`, CONCAT(b.`branch_code`, '-COLD'), 'Bếp lạnh', 'Khai vị, rau/chay và tráng miệng.', 'KDS', 1
FROM `branches` b
WHERE b.`branch_code` IN ('MS-HK', 'MS-CG', 'MS-TD')
UNION ALL
SELECT b.`branch_id`, CONCAT(b.`branch_code`, '-BAR'), 'Quầy đồ uống', 'Trà sen, cà phê, nước ép và sinh tố.', 'KDS', 1
FROM `branches` b
WHERE b.`branch_code` IN ('MS-HK', 'MS-CG', 'MS-TD')
ON DUPLICATE KEY UPDATE
  `branch_id` = VALUES(`branch_id`),
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `output_mode` = VALUES(`output_mode`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `kitchen_station_category_routes` (`station_id`, `branch_id`, `category_id`, `sort_order`, `is_active`)
SELECT ks.`station_id`, b.`branch_id`, c.`category_id`, c.`sort_order`, 1
FROM `branches` b
JOIN `menu_categories` c ON c.`name` IN ('Món chính', 'Cơm & bún/phở', 'Combo')
JOIN `kitchen_stations` ks ON ks.`branch_id` = b.`branch_id` AND ks.`code` = CONCAT(b.`branch_code`, '-HOT')
WHERE b.`branch_code` IN ('MS-HK', 'MS-CG', 'MS-TD')
UNION ALL
SELECT ks.`station_id`, b.`branch_id`, c.`category_id`, c.`sort_order`, 1
FROM `branches` b
JOIN `menu_categories` c ON c.`name` IN ('Khai vị', 'Rau & chay', 'Tráng miệng')
JOIN `kitchen_stations` ks ON ks.`branch_id` = b.`branch_id` AND ks.`code` = CONCAT(b.`branch_code`, '-COLD')
WHERE b.`branch_code` IN ('MS-HK', 'MS-CG', 'MS-TD')
UNION ALL
SELECT ks.`station_id`, b.`branch_id`, c.`category_id`, c.`sort_order`, 1
FROM `branches` b
JOIN `menu_categories` c ON c.`name` = 'Đồ uống'
JOIN `kitchen_stations` ks ON ks.`branch_id` = b.`branch_id` AND ks.`code` = CONCAT(b.`branch_code`, '-BAR')
WHERE b.`branch_code` IN ('MS-HK', 'MS-CG', 'MS-TD')
ON DUPLICATE KEY UPDATE
  `station_id` = VALUES(`station_id`),
  `sort_order` = VALUES(`sort_order`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `users` (`username`, `full_name`, `email`, `phone`, `role_id`, `current_tier_id`, `language_pref`, `is_deleted`)
VALUES
  ('ms.nam', 'Nam', 'nam@mocsen.example.test', '0905100001', 5, NULL, 'vn', 0),
  ('ms.linh', 'Linh', 'linh@mocsen.example.test', '0905100002', 6, NULL, 'vn', 0),
  ('ms.quan', 'Quân', 'quan@mocsen.example.test', '0905100003', 7, NULL, 'vn', 0),
  ('ms.mai', 'Mai', 'mai@mocsen.example.test', '0905100004', 8, NULL, 'vn', 0),
  ('ms.minh-anh', 'Nguyễn Minh Anh', 'minh.anh@mocsen.example.test', '0905200001', 3, (SELECT `tier_id` FROM `loyalty_tiers` WHERE `tier_code` = 'SEN_BAC' LIMIT 1), 'vn', 0),
  ('ms.thu-huong', 'Trần Thu Hương', 'thu.huong@mocsen.example.test', '0905200002', 3, (SELECT `tier_id` FROM `loyalty_tiers` WHERE `tier_code` = 'SEN_VANG' LIMIT 1), 'vn', 0)
ON DUPLICATE KEY UPDATE
  `full_name` = VALUES(`full_name`),
  `email` = VALUES(`email`),
  `phone` = VALUES(`phone`),
  `role_id` = VALUES(`role_id`),
  `current_tier_id` = VALUES(`current_tier_id`),
  `language_pref` = VALUES(`language_pref`),
  `is_deleted` = VALUES(`is_deleted`);

CREATE TEMPORARY TABLE `tmp_moc_sen_customers` (
  `customer_no` int unsigned NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `tier_code` varchar(50) NOT NULL,
  PRIMARY KEY (`customer_no`)
) ENGINE=Memory DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tmp_moc_sen_customers` (`customer_no`, `full_name`, `tier_code`)
VALUES
  (3, 'Pham Hoai An', 'SEN_MOI'),
  (4, 'Le Bao Chau', 'SEN_BAC'),
  (5, 'Do Minh Khang', 'SEN_VANG'),
  (6, 'Vu Ngoc Linh', 'SEN_MOI'),
  (7, 'Hoang Gia Bao', 'SEN_BAC'),
  (8, 'Bui Khanh Vy', 'SEN_MOI'),
  (9, 'Dang Quoc Huy', 'SEN_VANG'),
  (10, 'Tran Nhat Minh', 'SEN_MOI'),
  (11, 'Nguyen Phuong Nhi', 'SEN_BAC'),
  (12, 'Pham Duc Anh', 'SEN_MOI'),
  (13, 'Le Gia Han', 'SEN_VANG'),
  (14, 'Do Tuan Kiet', 'SEN_BAC'),
  (15, 'Vu Mai Chi', 'SEN_MOI'),
  (16, 'Hoang Anh Thu', 'SEN_KIM_CUONG'),
  (17, 'Bui Thanh Tung', 'SEN_BAC'),
  (18, 'Dang Bao Ngoc', 'SEN_MOI'),
  (19, 'Tran Hoang Nam', 'SEN_VANG'),
  (20, 'Nguyen Lan Anh', 'SEN_BAC'),
  (21, 'Pham Minh Quan', 'SEN_MOI'),
  (22, 'Le Tuong Vy', 'SEN_BAC'),
  (23, 'Do Gia Linh', 'SEN_MOI'),
  (24, 'Vu Huu Phuc', 'SEN_VANG'),
  (25, 'Hoang Kim Oanh', 'SEN_BAC'),
  (26, 'Bui Minh Tri', 'SEN_MOI'),
  (27, 'Dang Thu Trang', 'SEN_VANG'),
  (28, 'Tran Bao Tram', 'SEN_BAC'),
  (29, 'Nguyen Duy Long', 'SEN_MOI'),
  (30, 'Pham Ngoc Diep', 'SEN_BAC'),
  (31, 'Le Hoang Yen', 'SEN_VANG'),
  (32, 'Do Quang Vinh', 'SEN_MOI'),
  (33, 'Vu Thanh Ha', 'SEN_BAC'),
  (34, 'Hoang Minh Chau', 'SEN_MOI'),
  (35, 'Bui An Nhien', 'SEN_VANG'),
  (36, 'Dang Khanh Linh', 'SEN_BAC'),
  (37, 'Tran Duc Phat', 'SEN_MOI'),
  (38, 'Nguyen Mai Phuong', 'SEN_BAC'),
  (39, 'Pham Thai Son', 'SEN_VANG'),
  (40, 'Le Ngoc Mai', 'SEN_MOI');

INSERT INTO `users` (`username`, `full_name`, `email`, `phone`, `role_id`, `current_tier_id`, `language_pref`, `is_deleted`)
SELECT
  CONCAT('ms.customer-', LPAD(c.`customer_no`, 2, '0')),
  c.`full_name`,
  CONCAT('customer', LPAD(c.`customer_no`, 2, '0'), '@mocsen.example.test'),
  CONCAT('09052', LPAD(c.`customer_no`, 5, '0')),
  3,
  lt.`tier_id`,
  'vn',
  0
FROM `tmp_moc_sen_customers` c
JOIN `loyalty_tiers` lt ON lt.`tier_code` = c.`tier_code`
ON DUPLICATE KEY UPDATE
  `full_name` = VALUES(`full_name`),
  `email` = VALUES(`email`),
  `phone` = VALUES(`phone`),
  `role_id` = VALUES(`role_id`),
  `current_tier_id` = VALUES(`current_tier_id`),
  `language_pref` = VALUES(`language_pref`),
  `is_deleted` = VALUES(`is_deleted`);

INSERT INTO `staff_branch_assignments` (`user_id`, `branch_id`, `is_primary`)
SELECT u.`user_id`, b.`branch_id`, CASE WHEN b.`branch_code` = 'MS-HK' THEN 1 ELSE 0 END
FROM `users` u
JOIN `branches` b ON b.`branch_code` IN ('MS-HK', 'MS-CG', 'MS-TD')
WHERE u.`username` IN ('ms.nam', 'ms.linh', 'ms.quan', 'ms.mai')
ON DUPLICATE KEY UPDATE
  `is_primary` = VALUES(`is_primary`),
  `revoked_at` = NULL;

INSERT INTO `user_points` (`user_id`, `total_points`, `last_updated`, `updated_by`)
SELECT
  u.`user_id`,
  CASE
    WHEN lt.`tier_code` = 'SEN_KIM_CUONG' THEN 1280
    WHEN lt.`tier_code` = 'SEN_VANG' THEN 640
    WHEN lt.`tier_code` = 'SEN_BAC' THEN 280
    ELSE 80
  END,
  CURRENT_TIMESTAMP(6),
  (SELECT `user_id` FROM `users` WHERE `username` = 'ms.mai' LIMIT 1)
FROM `users` u
JOIN `loyalty_tiers` lt ON lt.`tier_id` = u.`current_tier_id`
WHERE u.`username` IN ('ms.minh-anh', 'ms.thu-huong')
   OR u.`username` LIKE 'ms.customer-%'
ON DUPLICATE KEY UPDATE
  `total_points` = VALUES(`total_points`),
  `last_updated` = VALUES(`last_updated`),
  `updated_by` = VALUES(`updated_by`);

INSERT INTO `user_vouchers` (`user_id`, `voucher_id`, `assigned_date`, `is_used`, `created_by`, `updated_by`)
SELECT
  u.`user_id`,
  v.`voucher_id`,
  '2026-05-13 09:00:00.000000',
  0,
  (SELECT `user_id` FROM `users` WHERE `username` = 'ms.mai' LIMIT 1),
  (SELECT `user_id` FROM `users` WHERE `username` = 'ms.mai' LIMIT 1)
FROM `users` u
JOIN `vouchers` v ON v.`code` IN ('WELCOME30', 'SENLUNCH10', 'FAMILY50')
WHERE u.`username` IN ('ms.minh-anh', 'ms.thu-huong')
   OR u.`username` LIKE 'ms.customer-%'
ON DUPLICATE KEY UPDATE
  `assigned_date` = VALUES(`assigned_date`),
  `is_used` = VALUES(`is_used`),
  `used_date` = NULL,
  `used_reservation_id` = NULL,
  `used_amount` = NULL,
  `lock_token` = NULL,
  `locked_until` = NULL,
  `updated_by` = VALUES(`updated_by`);

INSERT INTO `ingredients` (`code`, `name`, `unit_code`, `description`, `is_active`)
VALUES
  ('MS-RICE-JASMINE', 'Gao thom Moc Sen', 'kg', 'Core rice ingredient for lunch and family sets.', 1),
  ('MS-LOTUS-TEA', 'Tra sen', 'g', 'Lotus tea used for signature cold drink.', 1),
  ('MS-CHICKEN', 'Ga tuoi', 'kg', 'Chicken for com ga la sen and grilled dishes.', 1),
  ('MS-BEEF', 'Bo tuoi', 'kg', 'Beef for bun bo and bo luc lac.', 1),
  ('MS-HERBS', 'Rau thom', 'kg', 'Fresh herbs for noodle, roll, and salad dishes.', 1),
  ('MS-LOTUS-SEED', 'Hat sen', 'kg', 'Lotus seed for dessert and clear soup.', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `unit_code` = VALUES(`unit_code`),
  `description` = VALUES(`description`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `suppliers` (`code`, `name`, `contact_name`, `phone`, `email`, `notes`, `is_active`)
VALUES
  ('MS-SUP-FARM', 'Moc Sen Local Farm', 'Hoa', '0905300001', 'farm@mocsen.example.test', 'Vegetables, herbs, and lotus seed supplier for Moc Sen Bistro.', 1),
  ('MS-SUP-PROTEIN', 'Moc Sen Fresh Protein', 'Tuan', '0905300002', 'protein@mocsen.example.test', 'Chicken, beef, and seafood supplier for daily operations.', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `contact_name` = VALUES(`contact_name`),
  `phone` = VALUES(`phone`),
  `email` = VALUES(`email`),
  `notes` = VALUES(`notes`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `ingredient_stock_movements` (
  `ingredient_id`,
  `branch_id`,
  `movement_type`,
  `quantity_delta`,
  `unit_code`,
  `reference_type`,
  `reference_id`,
  `notes`,
  `created_by`,
  `created_at`
)
SELECT i.`ingredient_id`, b.`branch_id`, x.`movement_type`, x.`quantity_delta`, i.`unit_code`, 'moc_sen_story_pack', CONCAT(b.`branch_code`, '-', i.`code`, '-', x.`movement_type`), x.`notes`, u.`user_id`, x.`created_at`
FROM `branches` b
JOIN `users` u ON u.`username` = 'ms.mai'
JOIN `ingredients` i ON i.`code` IN ('MS-RICE-JASMINE', 'MS-LOTUS-TEA', 'MS-CHICKEN')
JOIN (
  SELECT 'StockIn' AS `movement_type`, 45.000 AS `quantity_delta`, 'Opening stock for Moc Sen operating week.' AS `notes`, '2026-05-13 08:00:00.000000' AS `created_at`
  UNION ALL SELECT 'StockOut', -8.000, 'Demo lunch and dinner consumption.', '2026-05-13 21:30:00.000000'
) x
WHERE b.`branch_code` IN ('MS-HK', 'MS-CG', 'MS-TD')
ON DUPLICATE KEY UPDATE
  `ingredient_id` = VALUES(`ingredient_id`),
  `branch_id` = VALUES(`branch_id`),
  `movement_type` = VALUES(`movement_type`),
  `quantity_delta` = VALUES(`quantity_delta`),
  `unit_code` = VALUES(`unit_code`),
  `notes` = VALUES(`notes`),
  `created_by` = VALUES(`created_by`),
  `created_at` = VALUES(`created_at`);

INSERT INTO `cashier_shifts` (
  `shift_code`,
  `cashier_user_id`,
  `branch_id`,
  `status`,
  `currency`,
  `terminal_code`,
  `opening_float_amount`,
  `expected_cash_amount`,
  `actual_cash_amount`,
  `cash_discrepancy_amount`,
  `opened_at`,
  `closed_at`,
  `opened_by`,
  `closed_by`,
  `opening_note`,
  `closing_note`
)
SELECT
  CONCAT('MS-SHIFT-', b.`branch_code`, '-20260513-PM'),
  cashier.`user_id`,
  b.`branch_id`,
  'Closed',
  'VND',
  CONCAT(b.`branch_code`, '-POS-01'),
  2000000.00,
  CASE b.`branch_code` WHEN 'MS-HK' THEN 4860000.00 WHEN 'MS-CG' THEN 3520000.00 ELSE 4180000.00 END,
  CASE b.`branch_code` WHEN 'MS-HK' THEN 4860000.00 WHEN 'MS-CG' THEN 3520000.00 ELSE 4180000.00 END,
  0.00,
  '2026-05-13 16:00:00.000000',
  '2026-05-13 22:30:00.000000',
  cashier.`user_id`,
  cashier.`user_id`,
  'Opening float for Moc Sen dinner service.',
  'Closed cleanly after QR and cash reconciliation.'
FROM `branches` b
JOIN `users` cashier ON cashier.`username` = 'ms.linh'
WHERE b.`branch_code` IN ('MS-HK', 'MS-CG', 'MS-TD')
ON DUPLICATE KEY UPDATE
  `cashier_user_id` = VALUES(`cashier_user_id`),
  `branch_id` = VALUES(`branch_id`),
  `status` = VALUES(`status`),
  `currency` = VALUES(`currency`),
  `terminal_code` = VALUES(`terminal_code`),
  `opening_float_amount` = VALUES(`opening_float_amount`),
  `expected_cash_amount` = VALUES(`expected_cash_amount`),
  `actual_cash_amount` = VALUES(`actual_cash_amount`),
  `cash_discrepancy_amount` = VALUES(`cash_discrepancy_amount`),
  `opened_at` = VALUES(`opened_at`),
  `closed_at` = VALUES(`closed_at`),
  `opened_by` = VALUES(`opened_by`),
  `closed_by` = VALUES(`closed_by`),
  `opening_note` = VALUES(`opening_note`),
  `closing_note` = VALUES(`closing_note`);

INSERT INTO `reservations` (
  `user_id`,
  `guest_name`,
  `guest_phone`,
  `guest_email`,
  `branch_id`,
  `reservation_code`,
  `reserved_at`,
  `start_time`,
  `end_time`,
  `guest_count`,
  `status`,
  `source`,
  `checked_in_at`,
  `checked_out_at`,
  `cancelled_at`,
  `cancel_reason`,
  `cancelled_by`,
  `no_show_at`,
  `deposit_required_amount`,
  `deposit_paid_amount`,
  `deposit_status`,
  `discount_amount`,
  `final_bill_amount`,
  `bill_currency`,
  `billed_at`,
  `notes`,
  `created_by`,
  `updated_by`
)
SELECT u.`user_id`, u.`full_name`, u.`phone`, u.`email`, b.`branch_id`, 'RSV-MS-20260513-0001', '2026-05-13 10:15:00.000000', '2026-05-13 19:00:00.000000', '2026-05-13 20:30:00.000000', 4, 'Completed', 'Online', '2026-05-13 18:55:00.000000', '2026-05-13 20:35:00.000000', NULL, NULL, NULL, NULL, 0.00, 0.00, 'NotRequired', 30000.00, 516000.00, 'VND', '2026-05-13 20:20:00.000000', 'Moc Sen story: Minh Anh dinner, preorder converted after check-in.', staff.`user_id`, staff.`user_id`
FROM `users` u JOIN `branches` b ON b.`branch_code` = 'MS-HK' JOIN `users` staff ON staff.`username` = 'ms.nam'
WHERE u.`username` = 'ms.minh-anh'
UNION ALL
SELECT u.`user_id`, u.`full_name`, u.`phone`, u.`email`, b.`branch_id`, 'RSV-MS-20260513-0002', '2026-05-13 11:05:00.000000', '2026-05-13 18:30:00.000000', '2026-05-13 20:00:00.000000', 5, 'Completed', 'Online', '2026-05-13 18:25:00.000000', '2026-05-13 20:05:00.000000', NULL, NULL, NULL, NULL, 0.00, 0.00, 'NotRequired', 50000.00, 739000.00, 'VND', '2026-05-13 19:55:00.000000', 'Moc Sen story: family booking with child seat and low-spice note.', staff.`user_id`, staff.`user_id`
FROM `users` u JOIN `branches` b ON b.`branch_code` = 'MS-HK' JOIN `users` staff ON staff.`username` = 'ms.nam'
WHERE u.`username` = 'ms.thu-huong'
UNION ALL
SELECT u.`user_id`, u.`full_name`, u.`phone`, u.`email`, b.`branch_id`, 'RSV-MS-20260513-0003', '2026-05-13 09:20:00.000000', '2026-05-13 12:15:00.000000', '2026-05-13 13:30:00.000000', 2, 'NoShow', 'Online', NULL, NULL, NULL, NULL, NULL, '2026-05-13 12:35:00.000000', 0.00, 0.00, 'NotRequired', 0.00, NULL, 'VND', NULL, 'Moc Sen story: lunch no-show for ops reporting.', staff.`user_id`, staff.`user_id`
FROM `users` u JOIN `branches` b ON b.`branch_code` = 'MS-CG' JOIN `users` staff ON staff.`username` = 'ms.nam'
WHERE u.`username` = 'ms.customer-04'
UNION ALL
SELECT u.`user_id`, u.`full_name`, u.`phone`, u.`email`, b.`branch_id`, 'RSV-MS-20260514-0001', '2026-05-13 21:00:00.000000', '2026-05-14 19:00:00.000000', '2026-05-14 20:45:00.000000', 6, 'Confirmed', 'Online', NULL, NULL, NULL, NULL, NULL, NULL, 200000.00, 0.00, 'Pending', 0.00, NULL, 'VND', NULL, 'Moc Sen story: future private-room booking with deposit required.', staff.`user_id`, staff.`user_id`
FROM `users` u JOIN `branches` b ON b.`branch_code` = 'MS-TD' JOIN `users` staff ON staff.`username` = 'ms.nam'
WHERE u.`username` = 'ms.customer-16'
ON DUPLICATE KEY UPDATE
  `user_id` = VALUES(`user_id`),
  `guest_name` = VALUES(`guest_name`),
  `guest_phone` = VALUES(`guest_phone`),
  `guest_email` = VALUES(`guest_email`),
  `branch_id` = VALUES(`branch_id`),
  `reserved_at` = VALUES(`reserved_at`),
  `start_time` = VALUES(`start_time`),
  `end_time` = VALUES(`end_time`),
  `guest_count` = VALUES(`guest_count`),
  `status` = VALUES(`status`),
  `source` = VALUES(`source`),
  `checked_in_at` = VALUES(`checked_in_at`),
  `checked_out_at` = VALUES(`checked_out_at`),
  `cancelled_at` = VALUES(`cancelled_at`),
  `cancel_reason` = VALUES(`cancel_reason`),
  `cancelled_by` = VALUES(`cancelled_by`),
  `no_show_at` = VALUES(`no_show_at`),
  `deposit_required_amount` = VALUES(`deposit_required_amount`),
  `deposit_paid_amount` = VALUES(`deposit_paid_amount`),
  `deposit_status` = VALUES(`deposit_status`),
  `discount_amount` = VALUES(`discount_amount`),
  `final_bill_amount` = VALUES(`final_bill_amount`),
  `bill_currency` = VALUES(`bill_currency`),
  `billed_at` = VALUES(`billed_at`),
  `notes` = VALUES(`notes`),
  `updated_by` = VALUES(`updated_by`);

INSERT INTO `reservation_tables` (`reservation_id`, `table_id`)
SELECT r.`reservation_id`, t.`table_id`
FROM `reservations` r
JOIN `restaurant_tables` t ON t.`table_code` = CASE
  WHEN r.`reservation_code` = 'RSV-MS-20260513-0001' THEN 'MS-HK-WINDOW-01'
  WHEN r.`reservation_code` = 'RSV-MS-20260513-0002' THEN 'MS-HK-GARDEN-03'
  WHEN r.`reservation_code` = 'RSV-MS-20260513-0003' THEN 'MS-CG-MAIN-01'
  ELSE 'MS-TD-PRIVATE-02'
END
WHERE r.`reservation_code` IN ('RSV-MS-20260513-0001', 'RSV-MS-20260513-0002', 'RSV-MS-20260513-0003', 'RSV-MS-20260514-0001')
ON DUPLICATE KEY UPDATE
  `table_id` = VALUES(`table_id`);

INSERT INTO `waiting_list` (
  `user_id`,
  `customer_session_id`,
  `branch_id`,
  `guest_name`,
  `phone`,
  `guest_count`,
  `requested_at`,
  `status`,
  `priority`,
  `notified_at`,
  `notify_expires_at`,
  `customer_response_status`,
  `customer_responded_at`,
  `customer_confirmed_arrival_at`,
  `notified_by`,
  `seated_at`,
  `cancelled_at`,
  `cancel_reason`,
  `notes`,
  `updated_by`
)
SELECT u.`user_id`, 'ms-waiting-20260513-001', b.`branch_id`, u.`full_name`, u.`phone`, 3, '2026-05-13 18:10:00.000000', 'Seated', 20, '2026-05-13 18:18:00.000000', '2026-05-13 18:28:00.000000', 'Accepted', '2026-05-13 18:20:00.000000', '2026-05-13 18:23:00.000000', staff.`user_id`, '2026-05-13 18:32:00.000000', NULL, NULL, 'Moc Sen story: waitlist advanced into dinner seating.', staff.`user_id`
FROM `users` u JOIN `branches` b ON b.`branch_code` = 'MS-HK' JOIN `users` staff ON staff.`username` = 'ms.nam'
WHERE u.`username` = 'ms.customer-05'
  AND NOT EXISTS (SELECT 1 FROM `waiting_list` wl WHERE wl.`customer_session_id` = 'ms-waiting-20260513-001')
UNION ALL
SELECT u.`user_id`, 'ms-waiting-20260513-002', b.`branch_id`, u.`full_name`, u.`phone`, 2, '2026-05-13 19:35:00.000000', 'Cancelled', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-13 19:50:00.000000', 'Customer left before table opened.', 'Moc Sen story: waitlist cancellation for ops report.', staff.`user_id`
FROM `users` u JOIN `branches` b ON b.`branch_code` = 'MS-CG' JOIN `users` staff ON staff.`username` = 'ms.nam'
WHERE u.`username` = 'ms.customer-06'
  AND NOT EXISTS (SELECT 1 FROM `waiting_list` wl WHERE wl.`customer_session_id` = 'ms-waiting-20260513-002');

INSERT INTO `reservation_orders` (`reservation_id`, `order_type`, `status`, `created_by`, `updated_by`, `notes`, `created_at`, `updated_at`)
SELECT r.`reservation_id`, 'PreOrder', 'Completed', staff.`user_id`, staff.`user_id`, 'MOCSEN-STORY-ORDER-0001', '2026-05-13 18:58:00.000000', '2026-05-13 20:15:00.000000'
FROM `reservations` r JOIN `users` staff ON staff.`username` = 'ms.nam'
WHERE r.`reservation_code` = 'RSV-MS-20260513-0001'
  AND NOT EXISTS (SELECT 1 FROM `reservation_orders` ro WHERE ro.`reservation_id` = r.`reservation_id` AND ro.`notes` = 'MOCSEN-STORY-ORDER-0001')
UNION ALL
SELECT r.`reservation_id`, 'OnSpot', 'Completed', staff.`user_id`, staff.`user_id`, 'MOCSEN-STORY-ORDER-0002', '2026-05-13 18:35:00.000000', '2026-05-13 19:50:00.000000'
FROM `reservations` r JOIN `users` staff ON staff.`username` = 'ms.nam'
WHERE r.`reservation_code` = 'RSV-MS-20260513-0002'
  AND NOT EXISTS (SELECT 1 FROM `reservation_orders` ro WHERE ro.`reservation_id` = r.`reservation_id` AND ro.`notes` = 'MOCSEN-STORY-ORDER-0002');

INSERT INTO `reservation_order_items` (`order_id`, `item_id`, `quantity`, `unit_price`, `currency`, `line_total`, `item_name_snapshot`, `status`, `notes`, `updated_by`, `created_at`, `updated_at`)
SELECT ro.`order_id`, mi.`item_id`, x.`quantity`, x.`unit_price`, 'VND', ROUND(x.`unit_price` * x.`quantity`, 2), mi.`name`, x.`status`, x.`notes`, staff.`user_id`, x.`created_at`, x.`updated_at`
FROM `reservation_orders` ro
JOIN `reservations` r ON r.`reservation_id` = ro.`reservation_id`
JOIN `users` staff ON staff.`username` = 'ms.nam'
JOIN (
  SELECT 'MOCSEN-STORY-ORDER-0001' AS `order_notes`, 'MS-COM-GA-LA-SEN' AS `item_code`, 2 AS `quantity`, 89000.00 AS `unit_price`, 'Served' AS `status`, 'Serve with light ginger sauce.' AS `notes`, '2026-05-13 19:00:00.000000' AS `created_at`, '2026-05-13 19:38:00.000000' AS `updated_at`
  UNION ALL SELECT 'MOCSEN-STORY-ORDER-0001', 'MS-TRA-SEN-LANH', 2, 35000.00, 'Served', 'Less sweet.', '2026-05-13 19:00:00.000000', '2026-05-13 19:20:00.000000'
  UNION ALL SELECT 'MOCSEN-STORY-ORDER-0001', 'MS-CHE-SEN-LONG-NHAN', 2, 45000.00, 'Served', 'Dessert after main dishes.', '2026-05-13 19:05:00.000000', '2026-05-13 20:05:00.000000'
  UNION ALL SELECT 'MOCSEN-STORY-ORDER-0002', 'MS-SET-GIA-DINH-MOC-SEN', 1, 399000.00, 'Served', 'Family set, low spice.', '2026-05-13 18:36:00.000000', '2026-05-13 19:30:00.000000'
  UNION ALL SELECT 'MOCSEN-STORY-ORDER-0002', 'MS-GOI-CUON-TOM-THIT', 2, 59000.00, 'Served', 'Child-friendly dipping sauce.', '2026-05-13 18:38:00.000000', '2026-05-13 19:05:00.000000'
  UNION ALL SELECT 'MOCSEN-STORY-ORDER-0002', 'MS-NUOC-EP-CAM-CA-ROT', 2, 49000.00, 'Served', 'No added sugar.', '2026-05-13 18:40:00.000000', '2026-05-13 19:10:00.000000'
) x ON x.`order_notes` = ro.`notes`
JOIN `menu_items` mi ON mi.`code` = x.`item_code`
WHERE NOT EXISTS (
  SELECT 1
  FROM `reservation_order_items` roi
  WHERE roi.`order_id` = ro.`order_id`
    AND roi.`item_id` = mi.`item_id`
    AND roi.`notes` = x.`notes`
);

INSERT INTO `kitchen_order_item_tickets` (
  `station_id`,
  `order_id`,
  `reservation_id`,
  `order_item_id`,
  `item_id`,
  `category_id`,
  `route_id`,
  `route_source`,
  `output_mode`,
  `ticket_status`,
  `first_dispatched_at`,
  `fired_at`,
  `ready_at`,
  `completed_at`,
  `dispatch_count`,
  `ticket_notes`,
  `created_by`,
  `updated_by`,
  `created_at`,
  `updated_at`
)
SELECT
  ks.`station_id`,
  ro.`order_id`,
  r.`reservation_id`,
  roi.`order_item_id`,
  mi.`item_id`,
  mi.`category_id`,
  route.`route_id`,
  'Category',
  'KDS',
  'Completed',
  DATE_ADD(roi.`created_at`, INTERVAL 2 MINUTE),
  DATE_ADD(roi.`created_at`, INTERVAL 5 MINUTE),
  DATE_ADD(roi.`created_at`, INTERVAL 22 MINUTE),
  roi.`updated_at`,
  1,
  CONCAT('Moc Sen KDS story: ', mi.`name`),
  kitchen.`user_id`,
  kitchen.`user_id`,
  roi.`created_at`,
  roi.`updated_at`
FROM `reservation_order_items` roi
JOIN `reservation_orders` ro ON ro.`order_id` = roi.`order_id`
JOIN `reservations` r ON r.`reservation_id` = ro.`reservation_id`
JOIN `menu_items` mi ON mi.`item_id` = roi.`item_id`
JOIN `kitchen_station_category_routes` route ON route.`branch_id` = r.`branch_id` AND route.`category_id` = mi.`category_id` AND route.`is_active` = 1
JOIN `kitchen_stations` ks ON ks.`station_id` = route.`station_id`
JOIN `users` kitchen ON kitchen.`username` = 'ms.quan'
WHERE ro.`notes` IN ('MOCSEN-STORY-ORDER-0001', 'MOCSEN-STORY-ORDER-0002')
ON DUPLICATE KEY UPDATE
  `station_id` = VALUES(`station_id`),
  `order_id` = VALUES(`order_id`),
  `reservation_id` = VALUES(`reservation_id`),
  `item_id` = VALUES(`item_id`),
  `category_id` = VALUES(`category_id`),
  `route_id` = VALUES(`route_id`),
  `ticket_status` = VALUES(`ticket_status`),
  `first_dispatched_at` = VALUES(`first_dispatched_at`),
  `fired_at` = VALUES(`fired_at`),
  `ready_at` = VALUES(`ready_at`),
  `completed_at` = VALUES(`completed_at`),
  `ticket_notes` = VALUES(`ticket_notes`),
  `updated_by` = VALUES(`updated_by`);

INSERT INTO `payments` (
  `reservation_id`,
  `branch_id`,
  `cashier_shift_id`,
  `amount`,
  `currency`,
  `payment_method`,
  `payment_provider`,
  `payment_type`,
  `status`,
  `transaction_code`,
  `idempotency_key`,
  `paid_at`,
  `created_by`,
  `updated_by`,
  `notes`,
  `provider_response_json`
)
SELECT r.`reservation_id`, r.`branch_id`, cs.`cashier_shift_id`, 516000.00, 'VND', 'QR', 'simulated', 'Final', 'Success', 'PAY-MS-20260513-0001', 'mocsen-story-pay-0001', '2026-05-13 20:21:00.000000', cashier.`user_id`, cashier.`user_id`, 'Moc Sen story payment: Minh Anh QR final bill.', CAST('{"provider":"simulated","channel":"qr","story":"moc_sen_bistro"}' AS JSON)
FROM `reservations` r JOIN `cashier_shifts` cs ON cs.`branch_id` = r.`branch_id` AND cs.`shift_code` = 'MS-SHIFT-MS-HK-20260513-PM' JOIN `users` cashier ON cashier.`username` = 'ms.linh'
WHERE r.`reservation_code` = 'RSV-MS-20260513-0001'
UNION ALL
SELECT r.`reservation_id`, r.`branch_id`, cs.`cashier_shift_id`, 739000.00, 'VND', 'Cash', 'simulated', 'Final', 'Success', 'PAY-MS-20260513-0002', 'mocsen-story-pay-0002', '2026-05-13 19:57:00.000000', cashier.`user_id`, cashier.`user_id`, 'Moc Sen story payment: family cash final bill.', CAST('{"provider":"simulated","channel":"cash","story":"moc_sen_bistro"}' AS JSON)
FROM `reservations` r JOIN `cashier_shifts` cs ON cs.`branch_id` = r.`branch_id` AND cs.`shift_code` = 'MS-SHIFT-MS-HK-20260513-PM' JOIN `users` cashier ON cashier.`username` = 'ms.linh'
WHERE r.`reservation_code` = 'RSV-MS-20260513-0002'
ON DUPLICATE KEY UPDATE
  `reservation_id` = VALUES(`reservation_id`),
  `branch_id` = VALUES(`branch_id`),
  `cashier_shift_id` = VALUES(`cashier_shift_id`),
  `amount` = VALUES(`amount`),
  `currency` = VALUES(`currency`),
  `payment_method` = VALUES(`payment_method`),
  `payment_provider` = VALUES(`payment_provider`),
  `payment_type` = VALUES(`payment_type`),
  `status` = VALUES(`status`),
  `paid_at` = VALUES(`paid_at`),
  `updated_by` = VALUES(`updated_by`),
  `notes` = VALUES(`notes`),
  `provider_response_json` = VALUES(`provider_response_json`);

CREATE TEMPORARY TABLE `tmp_moc_sen_report_dates` (
  `business_date` date NOT NULL,
  `day_no` int unsigned NOT NULL,
  PRIMARY KEY (`business_date`)
) ENGINE=Memory DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tmp_moc_sen_report_dates` (`business_date`, `day_no`)
VALUES
  ('2026-05-07', 1),
  ('2026-05-08', 2),
  ('2026-05-09', 3),
  ('2026-05-10', 4),
  ('2026-05-11', 5),
  ('2026-05-12', 6),
  ('2026-05-13', 7);

-- Launch-critical reporting snapshots must match transactional source rows.
-- Keep these rows derived from the Moc Sen reservations, payments, shifts,
-- invoices, and waitlist rows above so deploy-check reconciliation stays green.

INSERT INTO `reporting_daily_sales_snapshots` (
  `branch_id`,
  `business_date`,
  `currency`,
  `billed_reservation_count`,
  `billed_guest_count`,
  `gross_bill_amount`,
  `discount_amount`,
  `billed_total_amount`,
  `invoice_issued_count`,
  `invoiced_total_amount`,
  `invoiced_tax_amount`,
  `payment_row_count`,
  `refund_row_count`,
  `captured_amount`,
  `refunded_amount`,
  `net_paid_amount`,
  `deposit_net_amount`,
  `final_net_amount`,
  `cashier_shift_closed_count`,
  `cash_discrepancy_amount`,
  `refreshed_at`
)
SELECT
  x.`branch_id`,
  x.`business_date`,
  x.`currency`,
  SUM(x.`billed_reservation_count`),
  SUM(x.`billed_guest_count`),
  ROUND(SUM(x.`gross_bill_amount`), 2),
  ROUND(SUM(x.`discount_amount`), 2),
  ROUND(SUM(x.`billed_total_amount`), 2),
  SUM(x.`invoice_issued_count`),
  ROUND(SUM(x.`invoiced_total_amount`), 2),
  ROUND(SUM(x.`invoiced_tax_amount`), 2),
  SUM(x.`payment_row_count`),
  SUM(x.`refund_row_count`),
  ROUND(SUM(x.`captured_amount`), 2),
  ROUND(SUM(x.`refunded_amount`), 2),
  ROUND(SUM(x.`net_paid_amount`), 2),
  ROUND(SUM(x.`deposit_net_amount`), 2),
  ROUND(SUM(x.`final_net_amount`), 2),
  SUM(x.`cashier_shift_closed_count`),
  ROUND(SUM(x.`cash_discrepancy_amount`), 2),
  UTC_TIMESTAMP(6)
FROM (
  SELECT
    r.`branch_id`,
    DATE(r.`billed_at`) AS `business_date`,
    COALESCE(NULLIF(r.`bill_currency`, ''), 'VND') AS `currency`,
    COUNT(*) AS `billed_reservation_count`,
    SUM(COALESCE(r.`guest_count`, 0)) AS `billed_guest_count`,
    ROUND(SUM(COALESCE(r.`final_bill_amount`, 0) + COALESCE(r.`discount_amount`, 0)), 2) AS `gross_bill_amount`,
    ROUND(SUM(COALESCE(r.`discount_amount`, 0)), 2) AS `discount_amount`,
    ROUND(SUM(COALESCE(r.`final_bill_amount`, 0)), 2) AS `billed_total_amount`,
    0 AS `invoice_issued_count`,
    0.00 AS `invoiced_total_amount`,
    0.00 AS `invoiced_tax_amount`,
    0 AS `payment_row_count`,
    0 AS `refund_row_count`,
    0.00 AS `captured_amount`,
    0.00 AS `refunded_amount`,
    0.00 AS `net_paid_amount`,
    0.00 AS `deposit_net_amount`,
    0.00 AS `final_net_amount`,
    0 AS `cashier_shift_closed_count`,
    0.00 AS `cash_discrepancy_amount`
  FROM `reservations` r
  WHERE r.`reservation_code` LIKE 'RSV-MS-%'
    AND r.`billed_at` IS NOT NULL
  GROUP BY r.`branch_id`, DATE(r.`billed_at`), COALESCE(NULLIF(r.`bill_currency`, ''), 'VND')
  UNION ALL
  SELECT
    r.`branch_id`,
    DATE(i.`issued_at`) AS `business_date`,
    COALESCE(NULLIF(i.`currency`, ''), 'VND') AS `currency`,
    0,
    0,
    0.00,
    0.00,
    0.00,
    COUNT(*),
    ROUND(SUM(COALESCE(i.`total_amount`, 0)), 2),
    ROUND(SUM(COALESCE(i.`tax_amount`, 0)), 2),
    0,
    0,
    0.00,
    0.00,
    0.00,
    0.00,
    0.00,
    0,
    0.00
  FROM `billing_invoices` i
  JOIN `reservations` r ON r.`reservation_id` = i.`reservation_id`
  WHERE r.`reservation_code` LIKE 'RSV-MS-%'
    AND i.`invoice_status` = 'Issued'
  GROUP BY r.`branch_id`, DATE(i.`issued_at`), COALESCE(NULLIF(i.`currency`, ''), 'VND')
  UNION ALL
  SELECT
    p.`branch_id`,
    DATE(COALESCE(p.`paid_at`, p.`created_at`)) AS `business_date`,
    COALESCE(NULLIF(p.`currency`, ''), 'VND') AS `currency`,
    0,
    0,
    0.00,
    0.00,
    0.00,
    0,
    0.00,
    0.00,
    COUNT(*),
    SUM(CASE WHEN p.`payment_type` = 'Refund' THEN 1 ELSE 0 END),
    ROUND(SUM(CASE WHEN p.`payment_type` IN ('Deposit', 'Final') AND p.`status` IN ('Success', 'Refunded') THEN COALESCE(p.`amount`, 0) ELSE 0 END), 2),
    ROUND(SUM(CASE WHEN p.`payment_type` = 'Refund' AND p.`status` IN ('Success', 'Refunded') THEN COALESCE(p.`amount`, 0) ELSE 0 END), 2),
    ROUND(SUM(CASE WHEN p.`payment_type` IN ('Deposit', 'Final') AND p.`status` IN ('Success', 'Refunded') THEN COALESCE(p.`amount`, 0) ELSE 0 END) - SUM(CASE WHEN p.`payment_type` = 'Refund' AND p.`status` IN ('Success', 'Refunded') THEN COALESCE(p.`amount`, 0) ELSE 0 END), 2),
    ROUND(SUM(CASE WHEN p.`payment_type` = 'Deposit' AND p.`status` IN ('Success', 'Refunded') THEN COALESCE(p.`amount`, 0) ELSE 0 END), 2),
    ROUND(SUM(CASE WHEN p.`payment_type` = 'Final' AND p.`status` IN ('Success', 'Refunded') THEN COALESCE(p.`amount`, 0) ELSE 0 END), 2),
    0,
    0.00
  FROM `payments` p
  WHERE p.`idempotency_key` LIKE 'mocsen-story-pay-%'
    AND COALESCE(p.`paid_at`, p.`created_at`) IS NOT NULL
  GROUP BY p.`branch_id`, DATE(COALESCE(p.`paid_at`, p.`created_at`)), COALESCE(NULLIF(p.`currency`, ''), 'VND')
  UNION ALL
  SELECT
    cs.`branch_id`,
    DATE(cs.`closed_at`) AS `business_date`,
    COALESCE(NULLIF(cs.`currency`, ''), 'VND') AS `currency`,
    0,
    0,
    0.00,
    0.00,
    0.00,
    0,
    0.00,
    0.00,
    0,
    0,
    0.00,
    0.00,
    0.00,
    0.00,
    0.00,
    COUNT(*),
    ROUND(SUM(COALESCE(cs.`cash_discrepancy_amount`, 0)), 2)
  FROM `cashier_shifts` cs
  WHERE cs.`shift_code` LIKE 'MS-SHIFT-%'
    AND cs.`status` = 'Closed'
    AND cs.`closed_at` IS NOT NULL
  GROUP BY cs.`branch_id`, DATE(cs.`closed_at`), COALESCE(NULLIF(cs.`currency`, ''), 'VND')
) x
GROUP BY x.`branch_id`, x.`business_date`, x.`currency`
ON DUPLICATE KEY UPDATE
  `billed_reservation_count` = VALUES(`billed_reservation_count`),
  `billed_guest_count` = VALUES(`billed_guest_count`),
  `gross_bill_amount` = VALUES(`gross_bill_amount`),
  `discount_amount` = VALUES(`discount_amount`),
  `billed_total_amount` = VALUES(`billed_total_amount`),
  `invoice_issued_count` = VALUES(`invoice_issued_count`),
  `invoiced_total_amount` = VALUES(`invoiced_total_amount`),
  `invoiced_tax_amount` = VALUES(`invoiced_tax_amount`),
  `payment_row_count` = VALUES(`payment_row_count`),
  `refund_row_count` = VALUES(`refund_row_count`),
  `captured_amount` = VALUES(`captured_amount`),
  `refunded_amount` = VALUES(`refunded_amount`),
  `net_paid_amount` = VALUES(`net_paid_amount`),
  `deposit_net_amount` = VALUES(`deposit_net_amount`),
  `final_net_amount` = VALUES(`final_net_amount`),
  `cashier_shift_closed_count` = VALUES(`cashier_shift_closed_count`),
  `cash_discrepancy_amount` = VALUES(`cash_discrepancy_amount`),
  `refreshed_at` = VALUES(`refreshed_at`);

INSERT INTO `reporting_daily_operation_snapshots` (
  `branch_id`,
  `business_date`,
  `scheduled_reservation_count`,
  `scheduled_guest_count`,
  `scheduled_minutes_total`,
  `checked_in_count`,
  `completed_count`,
  `cancelled_count`,
  `no_show_count`,
  `turn_count`,
  `turn_minutes_total`,
  `waiting_list_created_count`,
  `waiting_list_notified_count`,
  `waiting_list_seated_count`,
  `waiting_list_cancelled_count`,
  `waiting_list_confirmed_arrival_count`,
  `refreshed_at`
)
SELECT
  x.`branch_id`,
  x.`business_date`,
  SUM(x.`scheduled_reservation_count`),
  SUM(x.`scheduled_guest_count`),
  SUM(x.`scheduled_minutes_total`),
  SUM(x.`checked_in_count`),
  SUM(x.`completed_count`),
  SUM(x.`cancelled_count`),
  SUM(x.`no_show_count`),
  SUM(x.`turn_count`),
  SUM(x.`turn_minutes_total`),
  SUM(x.`waiting_list_created_count`),
  SUM(x.`waiting_list_notified_count`),
  SUM(x.`waiting_list_seated_count`),
  SUM(x.`waiting_list_cancelled_count`),
  SUM(x.`waiting_list_confirmed_arrival_count`),
  UTC_TIMESTAMP(6)
FROM (
  SELECT r.`branch_id`, DATE(r.`start_time`) AS `business_date`, COUNT(*) AS `scheduled_reservation_count`, SUM(COALESCE(r.`guest_count`, 0)) AS `scheduled_guest_count`, SUM(GREATEST(0, TIMESTAMPDIFF(MINUTE, r.`start_time`, r.`end_time`))) AS `scheduled_minutes_total`, 0 AS `checked_in_count`, 0 AS `completed_count`, 0 AS `cancelled_count`, 0 AS `no_show_count`, 0 AS `turn_count`, 0 AS `turn_minutes_total`, 0 AS `waiting_list_created_count`, 0 AS `waiting_list_notified_count`, 0 AS `waiting_list_seated_count`, 0 AS `waiting_list_cancelled_count`, 0 AS `waiting_list_confirmed_arrival_count`
  FROM `reservations` r
  WHERE r.`reservation_code` LIKE 'RSV-MS-%'
  GROUP BY r.`branch_id`, DATE(r.`start_time`)
  UNION ALL
  SELECT r.`branch_id`, DATE(r.`checked_in_at`), 0, 0, 0, COUNT(*), 0, 0, 0, 0, 0, 0, 0, 0, 0, 0
  FROM `reservations` r
  WHERE r.`reservation_code` LIKE 'RSV-MS-%'
    AND r.`checked_in_at` IS NOT NULL
  GROUP BY r.`branch_id`, DATE(r.`checked_in_at`)
  UNION ALL
  SELECT r.`branch_id`, DATE(COALESCE(r.`checked_out_at`, r.`end_time`)), 0, 0, 0, 0, COUNT(*), 0, 0, SUM(CASE WHEN r.`checked_in_at` IS NOT NULL THEN 1 ELSE 0 END), SUM(CASE WHEN r.`checked_in_at` IS NOT NULL THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, r.`checked_in_at`, COALESCE(r.`checked_out_at`, r.`end_time`))) ELSE 0 END), 0, 0, 0, 0, 0
  FROM `reservations` r
  WHERE r.`reservation_code` LIKE 'RSV-MS-%'
    AND r.`status` = 'Completed'
  GROUP BY r.`branch_id`, DATE(COALESCE(r.`checked_out_at`, r.`end_time`))
  UNION ALL
  SELECT r.`branch_id`, DATE(r.`cancelled_at`), 0, 0, 0, 0, 0, COUNT(*), 0, 0, 0, 0, 0, 0, 0, 0
  FROM `reservations` r
  WHERE r.`reservation_code` LIKE 'RSV-MS-%'
    AND r.`cancelled_at` IS NOT NULL
  GROUP BY r.`branch_id`, DATE(r.`cancelled_at`)
  UNION ALL
  SELECT r.`branch_id`, DATE(r.`no_show_at`), 0, 0, 0, 0, 0, 0, COUNT(*), 0, 0, 0, 0, 0, 0, 0
  FROM `reservations` r
  WHERE r.`reservation_code` LIKE 'RSV-MS-%'
    AND r.`no_show_at` IS NOT NULL
  GROUP BY r.`branch_id`, DATE(r.`no_show_at`)
  UNION ALL
  SELECT wl.`branch_id`, DATE(wl.`requested_at`), 0, 0, 0, 0, 0, 0, 0, 0, 0, COUNT(*), 0, 0, 0, 0
  FROM `waiting_list` wl
  WHERE wl.`customer_session_id` LIKE 'ms-waiting-%'
    AND wl.`requested_at` IS NOT NULL
  GROUP BY wl.`branch_id`, DATE(wl.`requested_at`)
  UNION ALL
  SELECT wl.`branch_id`, DATE(wl.`notified_at`), 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, COUNT(*), 0, 0, 0
  FROM `waiting_list` wl
  WHERE wl.`customer_session_id` LIKE 'ms-waiting-%'
    AND wl.`notified_at` IS NOT NULL
  GROUP BY wl.`branch_id`, DATE(wl.`notified_at`)
  UNION ALL
  SELECT wl.`branch_id`, DATE(wl.`seated_at`), 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, COUNT(*), 0, 0
  FROM `waiting_list` wl
  WHERE wl.`customer_session_id` LIKE 'ms-waiting-%'
    AND wl.`seated_at` IS NOT NULL
  GROUP BY wl.`branch_id`, DATE(wl.`seated_at`)
  UNION ALL
  SELECT wl.`branch_id`, DATE(wl.`cancelled_at`), 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, COUNT(*), 0
  FROM `waiting_list` wl
  WHERE wl.`customer_session_id` LIKE 'ms-waiting-%'
    AND wl.`cancelled_at` IS NOT NULL
  GROUP BY wl.`branch_id`, DATE(wl.`cancelled_at`)
  UNION ALL
  SELECT wl.`branch_id`, DATE(wl.`customer_confirmed_arrival_at`), 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, COUNT(*)
  FROM `waiting_list` wl
  WHERE wl.`customer_session_id` LIKE 'ms-waiting-%'
    AND wl.`customer_confirmed_arrival_at` IS NOT NULL
  GROUP BY wl.`branch_id`, DATE(wl.`customer_confirmed_arrival_at`)
) x
GROUP BY x.`branch_id`, x.`business_date`
ON DUPLICATE KEY UPDATE
  `scheduled_reservation_count` = VALUES(`scheduled_reservation_count`),
  `scheduled_guest_count` = VALUES(`scheduled_guest_count`),
  `scheduled_minutes_total` = VALUES(`scheduled_minutes_total`),
  `checked_in_count` = VALUES(`checked_in_count`),
  `completed_count` = VALUES(`completed_count`),
  `cancelled_count` = VALUES(`cancelled_count`),
  `no_show_count` = VALUES(`no_show_count`),
  `turn_count` = VALUES(`turn_count`),
  `turn_minutes_total` = VALUES(`turn_minutes_total`),
  `waiting_list_created_count` = VALUES(`waiting_list_created_count`),
  `waiting_list_notified_count` = VALUES(`waiting_list_notified_count`),
  `waiting_list_seated_count` = VALUES(`waiting_list_seated_count`),
  `waiting_list_cancelled_count` = VALUES(`waiting_list_cancelled_count`),
  `waiting_list_confirmed_arrival_count` = VALUES(`waiting_list_confirmed_arrival_count`),
  `refreshed_at` = VALUES(`refreshed_at`);

INSERT INTO `reporting_daily_inventory_movement_snapshots` (
  `branch_id`,
  `business_date`,
  `ingredient_id`,
  `unit_code`,
  `movement_count`,
  `purchase_receipt_movement_count`,
  `stock_in_quantity`,
  `stock_out_quantity`,
  `adjustment_increase_quantity`,
  `adjustment_decrease_quantity`,
  `wastage_quantity`,
  `net_quantity_delta`,
  `last_movement_at`,
  `refreshed_at`
)
SELECT
  b.`branch_id`,
  d.`business_date`,
  i.`ingredient_id`,
  i.`unit_code`,
  4 + d.`day_no`,
  1,
  CASE i.`code` WHEN 'MS-LOTUS-TEA' THEN 1200.000 ELSE 25.000 + d.`day_no` END,
  CASE i.`code` WHEN 'MS-LOTUS-TEA' THEN 350.000 + d.`day_no` * 12 ELSE 8.000 + d.`day_no` END,
  0.000,
  0.000,
  CASE i.`code` WHEN 'MS-HERBS' THEN 0.500 ELSE 0.000 END,
  CASE i.`code` WHEN 'MS-LOTUS-TEA' THEN 850.000 - d.`day_no` * 12 ELSE 17.000 END,
  TIMESTAMP(d.`business_date`, '21:30:00'),
  UTC_TIMESTAMP(6)
FROM `branches` b
JOIN `tmp_moc_sen_report_dates` d
JOIN `ingredients` i ON i.`code` IN ('MS-RICE-JASMINE', 'MS-LOTUS-TEA', 'MS-CHICKEN', 'MS-HERBS')
WHERE b.`branch_code` IN ('MS-HK', 'MS-CG', 'MS-TD')
ON DUPLICATE KEY UPDATE
  `movement_count` = VALUES(`movement_count`),
  `purchase_receipt_movement_count` = VALUES(`purchase_receipt_movement_count`),
  `stock_in_quantity` = VALUES(`stock_in_quantity`),
  `stock_out_quantity` = VALUES(`stock_out_quantity`),
  `adjustment_increase_quantity` = VALUES(`adjustment_increase_quantity`),
  `adjustment_decrease_quantity` = VALUES(`adjustment_decrease_quantity`),
  `wastage_quantity` = VALUES(`wastage_quantity`),
  `net_quantity_delta` = VALUES(`net_quantity_delta`),
  `last_movement_at` = VALUES(`last_movement_at`),
  `refreshed_at` = VALUES(`refreshed_at`);

DROP TEMPORARY TABLE IF EXISTS `tmp_moc_sen_menu_items`;
DROP TEMPORARY TABLE IF EXISTS `tmp_moc_sen_categories`;
DROP TEMPORARY TABLE IF EXISTS `tmp_moc_sen_customers`;
DROP TEMPORARY TABLE IF EXISTS `tmp_moc_sen_report_dates`;
