# 🚀 Bảng tổng hợp các bước Post-Deployment (Hoàn thiện 100% Production)

Chào mừng bạn đến với chặng cuối! Sau khi bạn gõ lệnh `docker-compose -f docker-compose.prod.yml up -d --build` trên VPS và các container đã chạy, mã nguồn đã sẵn sàng nhưng hệ thống vẫn cần những "chìa khóa vật lý" để thức tỉnh hoàn toàn. 

Dưới đây là danh sách những việc bạn (hoặc quản trị viên) phải tự tay làm để biến dự án thành một cỗ máy kiếm tiền thực sự.

---

## 🛡️ 1. Tên Miền, SSL & Tường lửa (Bắt buộc)
Mã nguồn đã có sẵn Nginx chia route, nhưng bạn cần cấp chứng chỉ bảo mật cho nó.

- [ ] **Trỏ Domain**: Vào trang quản lý Tên miền (Godaddy, Namecheap...), trỏ bản ghi `A` về địa chỉ IP của VPS.
- [ ] **Bật Cloudflare WAF**: Đăng ký tên miền qua Cloudflare, bật chế độ "Proxy (Đám mây màu cam)" để giấu IP thật của máy chủ, kích hoạt lớp chống DDoS tự động.
- [ ] **Kích hoạt SSL (Let's Encrypt)**: 
  - Đăng nhập SSH vào VPS.
  - Cấp quyền thực thi: `chmod +x scripts/production/init-letsencrypt.sh`
  - Chạy file: `./scripts/production/init-letsencrypt.sh` để lấy chứng chỉ bảo mật xanh (HTTPS).

## 🔑 2. Cung cấp API Keys & Mật khẩu (.env)
Tuyệt đối không dùng file `.env` mặc định. Hãy sao chép `.env.production.example` thành `.env` và điền đủ các thông tin sau:

- [ ] **Bảo mật App**: Chạy lệnh `docker exec -it restaurant_app php artisan key:generate --force` để tạo khóa mật mã ứng dụng.
- [ ] **Thông vị Database**: Đổi `DB_PASSWORD` thành một chuỗi ngẫu nhiên cực mạnh.
- [ ] **Sentry DSN (Giám sát lỗi)**: Vào `sentry.io` tạo dự án, copy mã DSN dán vào `SENTRY_LARAVEL_DSN=...`
- [ ] **Gmail SMTP (Thông báo)**: Bật bảo mật 2 lớp cho Gmail, tạo Mật khẩu ứng dụng (App Password) và dán vào `MAIL_PASSWORD=...`.
- [ ] **AWS S3 / Cloudflare R2 (Backup)**: Tạo một Bucket, lấy 2 đoạn mã Access Key/Secret Key dán vào cấu hình `AWS_ACCESS_KEY_ID`.
- [ ] **Payment Gateway (Tùy chọn)**: Nếu bạn muốn bật tính năng tự thanh toán (Day-2), điền API Key thực tế của nhà cung cấp thanh toán (VNPay / Stripe) vào file cấu hình.

## 🗄️ 3. Tối ưu hóa & Khởi tạo Dữ liệu Laravel
Mã nguồn cần được "nén" lại để chạy với tốc độ ánh sáng trên Production.

- [ ] **Chạy Migration**: Cập nhật cấu trúc Database mới nhất.
  `docker exec -it restaurant_app php artisan migrate --force`
- [ ] **Cache toàn bộ hệ thống**: Laravel sẽ gộp tất cả file config/route thành 1 file siêu tốc.
  `docker exec -it restaurant_app php artisan optimize`
- [ ] **Khởi động Frontend**: Đối với file Next.js, nếu bạn chạy Node trên VPS, chạy `npm run build` và `npm run start` (Nếu chạy bằng PM2).

## 🎛️ 4. Quyết định Mở/Khóa tính năng (Feature Flags)
Dự án được thiết kế với cơ chế bật/tắt tính năng rất an toàn. Mở file `.env` của frontend (Next.js) và quyết định:

- [ ] **Tính năng Thanh toán Online (Self-pay)**: Chuyển đổi từ môi trường giả lập (Simulated) sang Live thật sự?
- [ ] **Tính năng Đặt món trước (Pre-order)**: `NEXT_PUBLIC_FEATURE_PREORDER=true/false`?
- [ ] **Tính năng Danh sách chờ (Waiting List)**: `NEXT_PUBLIC_FEATURE_WAITING_LIST=true/false`?
- [ ] **Tính năng Điểm thành viên (Loyalty)**: `NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS=true/false`?
> *Lưu ý: Bạn hoàn toàn có thể giữ giá trị `false` cho tháng khai trương đầu tiên để nhân viên quen việc, sau đó đổi thành `true` mà không cần viết lại code.*

## 🏢 5. Khởi tạo Business Logic (Setup thực tế)
Hệ thống đã chạy, nhưng nó cần biết nhà hàng của bạn trông như thế nào.

- [ ] Đăng nhập vào trang Admin bằng tài khoản root.
- [ ] Thiết lập **Sơ đồ bàn (Table Board)** cho chi nhánh đầu tiên.
- [ ] Thiết lập **Giờ mở cửa/Đóng cửa** để hệ thống Đặt bàn chặn khách nếu chọn sai giờ.
- [ ] Tạo **Tài khoản nhân viên (Staff Accounts)** và gắn Quyền (RBAC) tương ứng: Thu ngân (Cashier), Phục vụ (Waiter), Bếp (Kitchen).
- [ ] Nạp **Thực đơn (Menu & Combos)** và gán giá tiền.
