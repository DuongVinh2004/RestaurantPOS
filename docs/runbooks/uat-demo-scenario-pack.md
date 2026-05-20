# RestaurantPOS UAT Demo Scenario Pack

## Intent

Pack này tạo một bộ seed + scripts + checklist thống nhất để demo/UAT backend RestaurantPOS mà không phải tự ghép dữ liệu thủ công.

## Environment Requirements

- PHP CLI có thể chạy `php artisan`.
- API đang chạy và reachable qua `APP_URL` hoặc `-BaseUrl`, mặc định `http://127.0.0.1:8000`.
- Database đã có booking schema đầy đủ.
- Redis nên chạy nếu môi trường đang bật `booking.require_redis_for_booking_api=true`.
  - Nếu demo local không có Redis, tắt guard này trước khi chạy UAT pack.
- Feature flags cho demo branch sẽ được bootstrap command bật theo environment hiện tại:
  - `customer.bill_self_payment`
  - `waiting_list.advanced_automation`
  - `inventory.uplift`
  - `staff.conversation_inbox`
  - `staff.kitchen_dispatch`

Note: `staff.conversation_ai_assist` stays registered but is not enabled by the UAT pack until inbox and assist rollout evidence promotes it.

## Quick Start

```powershell
pwsh -File .\scripts\uat\Bootstrap-UatPack.ps1 -BaseUrl http://127.0.0.1:8000
pwsh -File .\scripts\uat\Invoke-UatScenario.ps1 -Scenario all
pwsh -File .\scripts\uat\Reset-UatPack.ps1
```

Bootstrap sẽ tạo manifest tại `storage/app/uat/scenario-pack.json` trừ khi override bằng `-ManifestPath`.

## Smoke Evidence Status

- `storage/app/uat/scenario-pack.json` is the current scenario manifest and source of seeded IDs, auth material, and branch/table/order inputs for smoke.
- Current smoke truth lives under `storage/app/booking_release/staff_web_smoke/latest-<target>.*` plus the exact timestamped smoke file cited by the release ticket.
- Timestamped smoke files that are not cited by the release ticket are historical only. Keep them for audit context, but do not treat old failures or old passes as current release evidence.
- If a historical smoke file is reused for investigation, re-bootstrap the scenario pack and re-run smoke before using the result for release decisions.

## Launch-Critical Replay Evidence

Limited-production readiness requires UAT replay evidence for the day-1 golden flows below. Record pass/fail by scenario key in `uat_scenario_pack_replay.scenario_results`:

| Evidence key | Golden flow |
| --- | --- |
| `customer_reservation_hold_access_session` | Customer reservation, table hold, and access session. |
| `staff_auth_branch_scope` | Staff authentication and branch-scope enforcement. |
| `walk_in_table_service_session` | Walk-in/table service session. |
| `order_item_lifecycle` | Order item create/update/cancel lifecycle. |
| `kds_dispatch_update` | KDS dispatch/update visibility. |
| `checkout_cashier_shift` | Checkout and cashier shift path. |
| `refund_cancel_after_payment` | Refund and cancel-after-payment path. |
| `inventory_consumption_adjustment` | Inventory consumption plus adjustment visibility. |
| `notification_outbox_visibility` | Notification/outbox visibility. |

The launch-readiness evidence file must set `production_artifact_contains_demo_credentials=false`. Do not paste UAT passwords, API keys, customer tokens, staff keys, bearer tokens, or provider credentials into release evidence.

## Canonical Data

Branch canonical:

- `branch_code`: `UATDEMO`
- `timezone`: `Asia/Ho_Chi_Minh`
- `currency`: `VND`
- `business_hours`: `00:00-23:59` tất cả các ngày để demo không bị lệch giờ

Canonical actors được ghi trong manifest:

- `auth.admin`
- `auth.staff`
- `auth.customer_primary`
- `auth.customer_secondary`

Actor ownership mặc định:

- `auth.customer_primary` chạy các scripted customer mutation flows, gồm `waiting-list-lifecycle`
- `auth.customer_secondary` được giữ riêng cho seeded waiting-list + conversation linkage để replay `conversation-inbox` không đụng active waiting entry của scripted lane

Manifest chứa:

- username/password cho login flows
- staff/admin API key plaintext cho staff routes
- branch/tables/menu/voucher/loyalty ids
- seeded reservation ids và scenario parameter ids

Because the manifest contains demo auth material, keep it in the UAT artifact store and do not attach it directly as production launch evidence.

## Scripts

### Bootstrap

```powershell
pwsh -File .\scripts\uat\Bootstrap-UatPack.ps1 -BaseUrl http://127.0.0.1:8000
```

Tác dụng:

- reset dữ liệu UAT cũ của branch `UATDEMO`
- seed canonical users, tables, menu, voucher, loyalty, reservations, waiting-list, conversation
- ghi manifest JSON để scripts khác dùng lại

### Reset

```powershell
pwsh -File .\scripts\uat\Reset-UatPack.ps1
```

Tác dụng:

- xoá dữ liệu canonical UAT pack
- xoá manifest mặc định, trừ khi truyền `-KeepManifest`

### Scenario Runner

```powershell
pwsh -File .\scripts\uat\Invoke-UatScenario.ps1 -Scenario availability-hold-reservation
pwsh -File .\scripts\uat\Invoke-UatScenario.ps1 -Scenario all
```

Supported scenario names:

- `availability-hold-reservation`
- `deposit-self-pay`
- `dine-in-checkout`
- `refund-partial`
- `refund-cancel`
- `waiting-list-lifecycle`
- `benefits`
- `admin-master-data`
- `conversation-inbox`

`all` chạy toàn bộ các scenario trên theo thứ tự phù hợp cho demo.

## Scripted Scenarios

### `availability-hold-reservation`

Flow:

- `GET /api/v1/tables/available`
- `POST /api/v1/table-holds`
- `POST /api/v1/reservations`

Expected outcomes:

- availability trả về bàn trống cho slot seeded
- hold được tạo với `hold_status=Holding`
- reservation mới được tạo và linked hold chuyển sang confirmed state

### `deposit-self-pay`

Flow:

- `GET /api/v1/reservations/{id}/deposit-preview`
- `POST /deposit/acknowledge`
- `POST /deposit/intent`
- `POST /deposit/payment-sessions`
- `POST /deposit/payment-sessions/{id}/confirm`

Expected outcomes:

- deposit requirement được acknowledge
- intent chuyển sang `Submitted`
- payment session `Succeeded` và `Applied`
- `deposit_paid_amount=100000.00`

### `dine-in-checkout`

Flow:

- staff check-in seeded reservation
- staff tạo on-spot order và add items
- customer xem `active-order` và `bill-preview`
- staff lock bill và finalize settlement

Expected outcomes:

- reservation chuyển `Confirmed -> Reserved -> Completed`
- active order có total đúng theo menu seeded
- final payment được tạo
- checkout đóng nợ về `0.00`

Lưu ý:

- Scenario này time-sensitive theo bootstrap time. Hãy chạy ngay sau `Bootstrap-UatPack.ps1`.

### `refund-partial`

Flow:

- `GET /api/v1/staff/reservations/{id}/refund-preview`
- `POST /api/v1/staff/reservations/{id}/refund`

Expected outcomes:

- refund scope là `deposit`
- tạo thêm một payment `Refund`
- reservation `deposit_status=PartiallyRefunded`
- `deposit_paid_amount` giảm còn `60000.00`

### `refund-cancel`

Flow:

- `GET /api/v1/staff/reservations/{id}/refund-preview`
- `POST /api/v1/staff/reservations/{id}/refund-cancel`

Expected outcomes:

- reservation bị cancel
- deposit/final payment đều được reverse qua refund payments
- reservation `deposit_status=Refunded`

### `waiting-list-lifecycle`

Flow:

- customer create waiting-list entry
- staff notify với table hold
- customer accept
- customer confirm-arrival
- staff seat

Expected outcomes:

- waiting-list đi qua `Waiting -> Notified -> Seated`
- notify hold được gắn với `waiting-list:{waiting_id}`
- seating tạo reservation phục vụ mới

### `benefits`

Flow:

- `GET /benefits-preview`
- apply voucher
- remove voucher
- redeem loyalty
- release loyalty

Expected outcomes:

- voucher lock/unlock theo reservation
- reservation loyalty snapshot tăng/giảm đúng
- transaction log cho redeem/release được tạo

### `admin-master-data`

Flow:

- list table templates
- create restaurant table
- create menu category
- create menu item
- create menu price

Expected outcomes:

- table/master data mới được tạo thành công bằng admin API key seeded
- generated ids được in ra bởi script

### `conversation-inbox`

Flow:

- list open conversations theo branch demo
- đọc seeded conversation detail

Expected outcomes:

- inbox trả về seeded conversation
- detail trả messages, entities, events, analyses, assignment history

## Manual Extensions

Các phần sau chưa được script runner tự động mutate trong phase này:

- conversation take-over / unassign / internal-note mutation
- admin update/archive/guard edge cases sau khi đã tạo master data
- lặp lại cùng một scenario đã mutate mà không reset/bootstrap lại pack

Các flow này vẫn có seed/support data để operator chạy manual bằng cURL hoặc API client.

## UAT Checklist

Sau khi chạy từng scenario, verify:

- response không có `error_code`
- ids quan trọng được in ra bởi script tồn tại trong DB hoặc API follow-up
- hold/reservation/waiting-list/payment status khớp expected outcome phía trên
- branch của dữ liệu mới phát sinh là `UATDEMO`
- customer/staff auth đang resolve đúng actor từ manifest
- feature-gated flows như bill self-payment và conversation inbox đang mở trên demo branch

Checklist cuối buổi demo:

- chạy `Reset-UatPack.ps1` để xoá dữ liệu demo
- nếu giữ manifest để debug, dùng `-KeepManifest`

## Operator Notes

- Pack này ưu tiên deterministic demo seed, không seed random.
- Nếu cần re-run full demo, luôn reset/bootstrap lại để row_version và timing không drift.
- Manifest là source of truth cho ids/tokens/passwords của pack, không nên hard-code lại trong script ngoài.
