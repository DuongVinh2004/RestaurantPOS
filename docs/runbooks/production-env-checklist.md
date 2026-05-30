# Production Environment Checklist

Tài liệu này là checklist tham chiếu cho các biến môi trường cấu hình tại file `.env` khi chạy trên môi trường Production.
> [!IMPORTANT]
> TUYỆT ĐỐI không được cung cấp, lưu trữ, hoặc commit mật khẩu, khóa API, thông tin thanh toán thật vào trong repository. Bạn phải điền trực tiếp vào server qua Secret Manager hoặc SSH config bằng tay.

## Bắt buộc (Required)

```ini
APP_NAME="RestaurantPOS"
APP_ENV=production
APP_KEY="<phải_generate_key_bằng_lệnh_hoặc_copy_từ_secret>"
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

LOG_CHANNEL=daily
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=restaurantpos_prod
DB_USERNAME=pos_prod_user
DB_PASSWORD="<your_db_password_from_secret_manager>"

BROADCAST_DRIVER=log
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_DOMAIN=".yourdomain.com"
SESSION_SECURE_COOKIE=true

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD="<your_redis_password_or_null>"
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME="<smtp_user>"
MAIL_PASSWORD="<smtp_password>"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"

# Cấu hình Frontend
NEXT_PUBLIC_API_BASE_URL="https://api.yourdomain.com"
CORS_ALLOWED_ORIGINS="https://staff.yourdomain.com,https://booking.yourdomain.com"
```

## Tùy chọn (Optional / External Providers)

Các biến này chỉ dùng cho thanh toán hoặc SMS, không dùng bản Mock/Sandbox trên Production.
```ini
# MoMo (Real Production Keys)
MOMO_PARTNER_CODE="<momo_prod_partner_code>"
MOMO_ACCESS_KEY="<momo_prod_access_key>"
MOMO_SECRET_KEY="<momo_prod_secret_key>"
MOMO_ENDPOINT="https://payment.momo.vn/v2/gateway/api/create"

# VNPay (Real Production Keys)
VNPAY_TMN_CODE="<vnpay_prod_code>"
VNPAY_HASH_SECRET="<vnpay_prod_secret>"
VNPAY_URL="https://pay.vnpay.vn/vpcpay.html"

# AWS / S3 Storage
AWS_ACCESS_KEY_ID="<aws_access_key>"
AWS_SECRET_ACCESS_KEY="<aws_secret_key>"
AWS_DEFAULT_REGION="ap-southeast-1"
AWS_BUCKET="<your_s3_bucket>"
AWS_USE_PATH_STYLE_ENDPOINT=false
```

## Tuyệt đối KHÔNG sử dụng trên Production

- Biến liên quan đến UAT Data (không có biến cụ thể nhưng nghiêm cấm config seed data mode cho `APP_ENV`).
- Demo credentials hardcoded trong code.
