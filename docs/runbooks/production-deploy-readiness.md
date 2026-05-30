# Production Deployment Readiness

Tài liệu này cung cấp checklist kiểm định khoảng trống (Gap Audit Checklist) bắt buộc trước khi đưa hệ thống RestaurantPOS lên môi trường Production. KHÔNG bypass bất kỳ hạng mục nào nếu không có sự phê duyệt từ Technical Lead.

## 1. Environment & Config

- [ ] `APP_ENV=production` được thiết lập chính xác.
- [ ] `APP_DEBUG=false` tuyệt đối không bật ở production.
- [ ] `APP_KEY` đã được tạo và lưu trữ an toàn qua secret manager.
- [ ] `APP_URL` là URL chính thức (HTTPS).
- [ ] Các biến `DB_*` (Host, Database, Username) chính xác. Mật khẩu (`DB_PASSWORD`) phải nằm trong Secret Manager.
- [ ] Các biến `REDIS_*` cấu hình đúng cluster/instance production.
- [ ] `QUEUE_CONNECTION=redis` (hoặc driver production tương đương, KHÔNG dùng `sync` hay `database` nếu có tải cao).
- [ ] `CACHE_STORE=redis` và `SESSION_DRIVER=redis`.
- [ ] `LOG_CHANNEL` phù hợp (ví dụ: `stderr` để ship log, hoặc `daily` với retention policy rõ ràng).
- [ ] `MAIL_*` cấu hình đúng thông tin SMTP production.
- [ ] Payment Provider Config (MoMo, VNPay) nếu dùng thật thì phải có thông tin credentials thực tế (chỉ enable khi đã ký hợp đồng và có public/private key).
- [ ] Storage config (`FILESYSTEM_DISK`) cấu hình đúng S3 hoặc local an toàn.
- [ ] CORS/Frontend URLs (`CORS_ALLOWED_ORIGINS`, v.v.) chỉ allow đúng domains của staff-web và customer-web.
- [ ] Trusted Proxies được cấu hình nếu hệ thống chạy sau Nginx/Load Balancer/Cloudflare.
- [ ] Rate limit/Security headers được cấu hình đầy đủ trên reverse proxy hoặc middleware.

*(Tuyệt đối không commit các biến secret vào repository. Xem `production-env-checklist.md` để biết thêm chi tiết).*

## 2. Database (SQL-First Contract)

- [ ] KHÔNG dùng `php artisan migrate` như lệnh bootstrap/deploy chính ở Production.
- [ ] Tồn tại kế hoạch áp dụng các SQL patches (`database/patches/*.sql`) với thứ tự áp dụng rõ ràng.
- [ ] Xác nhận CÓ thực hiện backup DB toàn vẹn trước mỗi lần chạy lệnh thay đổi database.
- [ ] Scripts verify schema drift và data validation chạy thành công sau khi patch.
- [ ] Chiến lược Rollback (hoặc Forward-fix) đã được document và thống nhất.
- [ ] Các thay đổi destructive (Xóa bảng, xóa cột dữ liệu) có quy trình cảnh báo/review 2 lớp.
- [ ] Logic giao dịch (locking/transaction/idempotency) cho checkout, payment, voucher, loyalty đã được unit test/E2E test cover ở môi trường Staging.

## 3. Backend Runtime

- [ ] PHP version đúng yêu cầu của file `composer.json`.
- [ ] Composer cài đặt mode production: `composer install --no-dev --optimize-autoloader`.
- [ ] Route cache (`php artisan route:cache`) được chạy nếu không có closure routes.
- [ ] Config cache (`php artisan config:cache`) được chạy.
- [ ] View cache (`php artisan view:cache`) được chạy.
- [ ] Queue worker daemon (Supervisor/Systemd) cấu hình đúng và đang chạy.
- [ ] Cron / Scheduler (`php artisan schedule:run`) được config vào system crontab chạy mỗi phút.
- [ ] Notification Outbox worker/process khỏe mạnh.
- [ ] Storage symlink (`php artisan storage:link`) đã được tạo với quyền truy cập phù hợp.
- [ ] Quyền đọc/ghi thư mục `storage` và `bootstrap/cache` được cấp đúng cho PHP-FPM user (e.g., `www-data`).
- [ ] Health endpoints trả về 200 OK.

## 4. Frontend (Staff & Customer Web)

- [ ] **Staff-Web**: Build chế độ production (`npm run build`) không có warning nghiêm trọng; URL backend trỏ đúng API production.
- [ ] **Customer-Web**: Build chế độ production; cấu hình `NEXT_PUBLIC_API_BASE_URL` trỏ đúng API production.
- [ ] Static assets phục vụ tốt từ Nginx hoặc CDN.
- [ ] Next.js (Customer-Web) chạy ổn định bằng PM2/Systemd hoặc container.
- [ ] CORS/session/cookie domain (`SESSION_DOMAIN`) khớp với domain triển khai thực tế.
- [ ] CSRF và security headers (X-Frame-Options, HSTS) bật ở mức cao nhất.

## 5. Security & Isolation

- [ ] Tuyệt đối không dùng thông tin Demo/UAT Credentials ở production (VD: Admin: `admin@example.com`/`password123` phải bị xóa hoặc không được tạo ở script bootstrap).
- [ ] `APP_DEBUG=false` xác nhận tắt hoàn toàn, không có exception stack trace phơi bày ra user.
- [ ] Không có `.env` file bị expose qua public Nginx/Apache.
- [ ] Auth/session cookie flags: `Secure=true`, `HttpOnly=true`, `SameSite=Lax/Strict`.
- [ ] Rate limiting (cho API đăng nhập, gửi SMS/Email, Checkout) được cấu hình và hoạt động.
- [ ] Các API thay đổi dữ liệu được bảo vệ chống IDOR (Insecure Direct Object Reference) dựa trên report bảo mật trước deploy.
- [ ] Payment webhook endpoints kiểm tra kỹ signature của nhà cung cấp, loại bỏ các fake callback payload.

## 6. Observability & Monitoring

- [ ] Command `php artisan booking:doctor` chạy thành công.
- [ ] Command `php artisan notifications:outbox-health` báo cáo 0 pending/stuck quá hạn.
- [ ] Các command deploy-check/preflight, launch-readiness pass xanh.
- [ ] Logs được gom về một nơi tập trung (CloudWatch, ELK, Datadog...) hoặc ít nhất có công cụ theo dõi log errors liên tục (Sentry, Bugsnag).
- [ ] DB heartbeat và Redis health tracking hoạt động tốt (để tránh downtime im lặng).
- [ ] Có hệ thống cảnh báo (Alert) qua Slack/Email/Telegram khi hệ thống xuất hiện lỗi 500 spike, lỗi Queue chết, lỗi Outbox bị nghẽn.
