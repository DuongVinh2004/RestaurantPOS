# Production Deploy Runbook

Tài liệu này cung cấp các bước chính xác để triển khai (Deploy) RestaurantPOS lên môi trường Production. 
TUYỆT ĐỐI không bỏ qua các bước Backup và Staging Dry-run Gate.

## 1. Scope & Preconditions

- **Scope**: Deploy cập nhật Backend Laravel, Database schema (SQL-First), Staff-Web (React) và Customer-Web (Next.js).
- **Preconditions**: 
  - CI Branch `main` màu xanh.
  - Đã pass chuỗi Staging Dry-Run.
  - Technical Lead / Operator đã duyệt kế hoạch Rollback.

## 2. Required Infrastructure & Secrets
- DB Production (MySQL 8), Redis Server (Cluster/Standalone).
- Web Server (Nginx) phục vụ PHP-FPM và Node/Static files.
- Đã điền đầy đủ thông tin môi trường Production (tham khảo `production-env-checklist.md`). Không ghi/cung cấp hardcode secrets trong text/chat.

---

## 3. SQL-First Database Deployment Plan

> [!WARNING]
> Không dùng `php artisan migrate` để deploy. Bám sát luồng `database/schema/mysql-schema.sql` và patches.

### Trường hợp 3.1: First Production Deployment (Dự án Mới Toanh)
1. Tạo một DB MySQL 8 trống trên Production.
2. Nạp cấu trúc database và dữ liệu cơ sở:
   ```bash
   mysql -u <user> -p <database> < database/schema/mysql-schema.sql
   ```
3. Khởi tạo dữ liệu Bootstrap cần thiết bằng script an toàn:
   ```bash
   # Nếu có lệnh đặc thù để khởi tạo Admin đầu tiên
   php artisan staff-auth:api-keys:issue --admin --no-demo
   ```
4. **Tuyệt đối không chạy** lệnh UAT/Demo seed (`booking:uat-pack:bootstrap`) trên Production.

### Trường hợp 3.2: Existing Production Upgrade (Bảo trì/Cập nhật tính năng)
1. **BẮT BUỘC BACKUP DATABASE** trước khi làm gì khác (thông qua MySQL dump hoặc Snapshot dịch vụ đám mây).
2. Tải mã nguồn mới về production server.
3. Apply tuần tự các patch file `.sql` mới chưa được apply (nằm trong thư mục `database/patches/`):
   ```bash
   mysql -u <user> -p <database> < database/patches/v2_01_add_feature_tables.sql
   ```
4. Chạy tool kiểm tra tính nguyên vẹn:
   ```bash
   php artisan booking:doctor
   ```

---

## 4. Deployment Steps

### 4.1 Backend Deployment Steps
1. Put hệ thống vào chế độ Maintenance (nếu hệ thống cho phép downtime):
   ```bash
   php artisan down --secret="your-bypass-secret"
   ```
2. Pull mã nguồn / checkout phiên bản release.
3. Install dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader --no-interaction
   ```
4. Cập nhật cache:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. Khởi động lại PHP-FPM:
   ```bash
   sudo systemctl restart php8.4-fpm
   ```

### 4.2 Queue Worker & Scheduler Setup
1. Khởi động / Restart Queue Worker (Supervisor):
   ```bash
   sudo supervisorctl restart all
   # Hoặc lệnh tương đương của dịch vụ queue
   php artisan queue:restart
   ```
2. Kiểm tra cronjob (đã chạy `php artisan schedule:run`).

### 4.3 Frontend Deployment
**Staff-Web**:
1. Cài đặt npm và build static files:
   ```bash
   cd staff-web
   npm ci
   npm run build
   ```
2. Cập nhật thư mục public/Nginx trỏ tới thư mục `dist` vừa build.

**Customer-Web**:
1. Cài đặt npm và build app Next.js:
   ```bash
   cd customer-web
   npm ci
   npm run build
   ```
2. Restart dịch vụ Node/PM2:
   ```bash
   pm2 restart customer-web
   ```

### 4.4 Health Checks & Smoke Tests
1. Vô hiệu hóa chế độ bảo trì:
   ```bash
   php artisan up
   ```
2. Chạy Health Check tự động:
   ```bash
   php artisan booking:doctor
   php artisan notifications:outbox-health
   ```
3. Thực hiện Smoke Test thủ công theo tài liệu `production-smoke-tests.md`.

---

## 5. Backup & Rollback Plan

### Backup Plan
- **Database**: Sử dụng mysqldump hoặc cloud backup.
  ```bash
  mysqldump -u [user] -p [db_name] > backup_pre_deploy_$(date +%F_%H%M).sql
  ```
- **Storage/Files**: Sao lưu thư mục `storage/app/public` (nếu chứa file user-generated quan trọng).

### Rollback Plan
- **Rollback App Code**: `git checkout <previous_stable_tag>` và `composer install`, `npm run build` lại như trên.
- **Rollback Database**: 
  - Ưu tiên phương pháp **Forward-Fix** (viết thêm mã hoặc query sửa lỗi) nếu hệ thống ĐÃ sinh ra dữ liệu mới trong thời gian live vừa qua.
  - Nếu buộc phải rollback DB từ backup (lỗi nghiêm trọng / mất trắng dữ liệu), chấp nhận việc sẽ mất một số dữ liệu Booking sinh ra trong khoảng thời gian bị lỗi. Chạy lệnh: `mysql -u [user] -p [db_name] < backup_pre_deploy_...sql`.

---

## 6. Post-deploy Monitoring & Payment Policy
- Xem log tại `storage/logs/laravel.log`, theo dõi tỉ lệ HTTP 500.
- Check hàng đợi queue có bị failed không (`php artisan queue:failed`).
- **Payment Callback Policy**: Tích hợp MoMo/VNPay trên Production chỉ bật sau khi config Webhook URL bên Dashboard phía đối tác đã chỉ định sang tên miền Production, và credentials (secret key) là chuẩn.
- Ghi nhận Production Cutover Success vào ticket báo cáo.
