# Booking smoke suite

Tối thiểu trước khi chạy:

1. Bật environment `testing`.
2. Migrate booking schema vào test DB.
3. Đảm bảo cache store `redis` trong test có thể map sang driver `array` hoặc Redis test riêng.

Chạy nhanh:

```bash
bash scripts/ci/booking-smoke.sh
```

Nhóm smoke này cover:
- idempotency middleware replay/conflict/in-progress
- pay/refund service idempotency replay
- on-spot order / add-items idempotency replay
- refund-cancel guard
- voucher / loyalty post-payment guard
- payment summary / voucher discount helper
