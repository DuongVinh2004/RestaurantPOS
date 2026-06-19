-- Chèn dữ liệu Roles chuẩn hóa (Canonical Roles)
-- Được chuyển đổi từ ReferenceDataSeeder sang SQL-first để hệ thống bootstrap mà không phụ thuộc db:seed

INSERT IGNORE INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'Admin'),
(2, 'Staff'),
(3, 'Customer'),
(4, 'Server'),
(5, 'Waiter'),
(6, 'Cashier'),
(7, 'Kitchen'),
(8, 'Manager');
