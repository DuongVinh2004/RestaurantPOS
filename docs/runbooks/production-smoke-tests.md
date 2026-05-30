# Production Smoke Tests

Đây là các bài kiểm tra siêu nhẹ (Smoke Tests) dùng để xác nhận hệ thống hoạt động ổn định ngay sau khi Deploy lên Production mà **không gây hại** (destructive) tới dữ liệu sản xuất thật.

> [!WARNING]
> Tuyệt đối không chạy UAT Scenario Pack hay tạo dữ liệu giả diện rộng vào Production.

## 1. Backend Smoke
- **Health Endpoints**:
  - Truy cập `GET /up` hoặc `GET /api/v1/health`.
  - Kết quả mong đợi: HTTP 200 OK, kết nối DB/Redis thành công.
- **Diagnostics Tools (Artisan)**:
  ```bash
  php artisan booking:doctor
  php artisan notifications:outbox-health
  ```
  Kết quả mong đợi: Trạng thái OK trên mọi modules, outbox queue không bị stuck.
- **Login Probe (Staff)**:
  - Dùng POSTMAN hoặc lệnh curl gọi API đăng nhập của một tài khoản Staff **đã được cấp quyền hợp lệ**.
  - Kết quả mong đợi: HTTP 200 OK cùng access token. Đảm bảo API trả về đúng cấu trúc.
- **Customer Session Probe**:
  - Truy cập endpoint danh sách nhà hàng hoặc available tables `/api/v1/tables/available`.
  - Kết quả mong đợi: Danh sách trả về không bị lỗi 500, response body chuẩn.

## 2. Frontend Smoke
- **Staff-Web Loads**:
  - Mở URL Staff-Web.
  - Kết quả mong đợi: Giao diện load thành công, CSS không vỡ, không có lỗi JS trên console.
  - Đăng nhập thử với tài khoản nhân viên.
- **Customer-Web Loads**:
  - Mở URL Customer-Web.
  - Kết quả mong đợi: Giao diện load thành công, không có lỗi JS trên console.
- **Reservation Page Reachable**:
  - Truy cập màn hình đặt bàn `/reservations/new`.
  - Kết quả mong đợi: Load được form đặt bàn, chọn giờ không bị crash.
- **Checkout Page (Only if specific test branch exists)**:
  - TẠM THỜI KHÔNG THỰC HIỆN thanh toán VNPay/MoMo thật nếu không có thẻ thật / thông tin thật để thử. Chỉ kiểm tra xem giao diện Checkout có render được không.

## 3. Monitor Logs 
Sau khi chạy Smoke Tests, mở server và xem log:
```bash
tail -n 100 storage/logs/laravel.log
```
Kết quả mong đợi: Không có log `[ERROR]` hoặc exception nào bắn ra do các hoạt động trên.
