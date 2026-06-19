# Hướng Dẫn Bootstrap Hệ Thống Với Dữ Liệu Thật (Real Data Only)

Tài liệu này hướng dẫn các thao tác chuẩn bị và kích hoạt hệ thống RestaurantPOS hoàn toàn sạch, sử dụng 100% dữ liệu từ cửa hàng thực tế, không có bất kỳ dummy hay demo data nào.

## 1. Yêu cầu Trước Khi Chạy
Hệ thống **không còn tự động sinh Admin/Staff mẫu**. Bạn bắt buộc phải cung cấp thông tin tài khoản qua biến môi trường để `booking:bootstrap-site` có thể tạo các user quản trị viên đầu tiên.

Hãy đảm bảo bạn đã cấu hình các giá trị sau trong file `.env`:
```env
BOOTSTRAP_ADMIN_USERNAME=admin_cuahang
BOOTSTRAP_STAFF_USERNAME=nv_quanly
```

> [!WARNING]
> Lệnh sẽ thất bại với `ValidationException` nếu thiếu các biến môi trường này hoặc nếu bạn không truyền trực tiếp qua tham số của lệnh.

## 2. Các Bước Cài Đặt Ban Đầu

### Khởi tạo Cơ sở Dữ liệu và Roles Hệ Thống
Hệ thống sử dụng cơ chế SQL-first patch thay cho việc seed bằng PHP. Bạn chỉ cần chạy script setup (hoặc migrate nếu dùng tính năng gốc). Các role mặc định như `Admin`, `Staff`, `Customer`... sẽ được insert sẵn qua patch SQL.

### Khởi tạo Site Mới
Chạy lệnh console để tạo cấu trúc Chi nhánh (Branch), Khu vực Bàn cơ bản (Starter Layout) và khởi tạo danh mục rỗng:
```bash
php artisan booking:bootstrap-site --show-secret-once
```

Nếu không sử dụng file `.env`, bạn có thể pass trực tiếp qua arguments:
```bash
php artisan booking:bootstrap-site \
  --admin-username="admin_cuahang" \
  --admin-name="Nguyễn Văn A" \
  --staff-username="nv_quanly" \
  --staff-name="Trần Thị B" \
  --show-secret-once
```

> [!TIP]
> Lưu ý lưu lại Staff API Key được tạo ra trên màn hình console ở bước này, vì hệ thống chỉ hiển thị một lần duy nhất.

## 3. Quy Trình Vận Hành Môi Trường Giả Lập (UAT/Demo)
Nếu bạn cần một hệ thống có đầy đủ dữ liệu giả (như menu ảo, đơn hàng test, người dùng demo) để thực hiện UAT (User Acceptance Testing), hệ thống đã cô lập hoàn toàn quy trình này.

Lệnh **chỉ có thể** chạy trên môi trường có biến môi trường `APP_ENV=uat/testing/local` VÀ `BOOKING_ALLOW_DEMO_DATA=true`.

```bash
php artisan booking:uat-pack:bootstrap
```
Nếu bạn cố tình chạy lệnh trên ở môi trường Production, hệ thống sẽ từ chối và bắn ra ngoại lệ an toàn.
