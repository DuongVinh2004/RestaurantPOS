# Production-Level Next Roadmap

## 1. Executive Summary
Tài liệu này xác định lộ trình đưa RestaurantPOS từ trạng thái "Feature Complete / Staging Ready" lên "Production Ready" thực sự. Trọng tâm của Roadmap là ưu tiên giảm thiểu các rủi ro (Risk-Based Prioritization) liên quan đến Infrastructure, Security, Payment, Observability và Automation Gates. Không bắt đầu viết các tính năng nghiệp vụ mới (Feature) trước khi cơ sở hạ tầng Production vững vàng.

## 2. Current State Audit
Hệ thống hiện tại đã đạt được những thành tựu nhất định nhờ các đợt audit trước, nhưng vẫn còn những điểm hổng chặn đứng việc Cutover lên Production.
- **Những phần đã mạnh**: Kiến trúc Backend chuẩn SQL-first (dùng `mysql-schema.sql`, không dùng `migrate`), Cấu trúc API chia tách rõ ràng theo domain/workflow, có hệ thống Artifact Generation (SDK, OpenAPI), và Split-Web Frontend (Vite/React & Next.js).
- **Những phần còn yếu**: Cơ sở hạ tầng (VPS/Cloud) chưa được chuẩn bị, chưa có hệ thống giám sát phân tán, Webhook thanh toán chưa verify an toàn.
- **Chỉ UAT-ready, chưa Production-ready**: Voucher/Loyalty seeded data, Customer Self-Service (chưa config domain thật).
- **Production Blockers thật sự**: Không có Domain/TLS, Không có Production Secret Management (Database, Redis, Payment Credentials), Chưa có Staging Clone để verify patch.
- **Rủi ro lớn nhất nếu deploy ngay**: Dữ liệu có thể bị rò rỉ nếu `.env` không bảo mật, mất tiền nếu Payment Webhook bị giả mạo, và không thể Rollback dữ liệu nếu Patch phá vỡ cấu trúc bảng.

## 3. Production Gap Matrix

| Nhóm | Current Status | Risk Level | Production Impact | Recommended Action | Batch Candidate |
| --- | --- | --- | --- | --- | --- |
| 1. Infrastructure | Missing | Critical | Blocker | Provision AWS/DO, Domain, TLS, Secrets. | Batch 1 |
| 2. SQL-first deploy | Scripted | Medium | Rollback risk | Setup Staging Clone dry-run. | Batch 3 |
| 3. CI/CD Release gate | Gated | Medium | Incomplete | Add auto-artifact verification & secret scan. | Batch 2 |
| 4. Backend runtime | Local-only | High | Stability | Config Opcache, Queue worker, Cron. | Batch 1 |
| 5. Queue/Outbox | OK | Low | Reliability | Systemd Supervisor setup. | Batch 1 |
| 6. Frontend deploy | Scripted | High | Reachability | Nginx/PM2 setup, CORS strict config. | Batch 1 & 8 |
| 7. Auth/RBAC/Sec | Basic | High | IDOR / Leaks | Strict CORS, Cookie Secure, Secret scanner. | Batch 8 |
| 8. Customer Service | Gated | Medium | Logic error | E2E Regression with Live Data. | Batch 9 |
| 9. Checkout/Payment | Fake Pass | Critical | Financial | Sandbox & Webhook Signature Validation. | Batch 4 |
| 10. Voucher/Loyalty | Seeded | Medium | Integrity | Ensure UI handles logic without UAT Seed. | Batch 9 |
| 11. Inventory/Purch | Gated | Low | Flow error | Smoke tests. | Batch 9 |
| 12. Reporting | Base | Low | Latency | Load test queries. | Batch 7 |
| 13. Notifications | Outbox | Medium | Drop logic | Alerting for stuck outbox. | Batch 5 |
| 14. Observability | None | Critical | Blindness | Setup ELK/Datadog or Prometheus. | Batch 5 |
| 15. Backup/Restore | None | Critical | Data Loss | Automated dump to S3 before deploy. | Batch 6 |
| 16. Performance | Untested | High | Downtime | k6/JMeter load testing. | Batch 7 |
| 17. Privacy/Audit | Base | Low | Compliance | Audit logs review. | Batch 8 |
| 18. Docs/Runbooks | Added | Low | Ops error | Follow new runbooks strictly. | - |
| 19. Smoke/UAT | Local | Medium | Silent Fail | Run non-destructive smoke on Staging. | Batch 9 |
| 20. Business Cont. | Missing | High | Downtime | DR Rehearsal & Rollback drill. | Batch 10 |

## 4. Prioritized Batch Roadmap

### Batch 1 — Production Infrastructure Specification & Environment Lockdown
- **Mục tiêu**: Chốt deployment target, chuẩn hóa domain/TLS/secrets, config Nginx/PM2.
- **Lý do**: Đây là blocker lớn nhất, không có Infra thì không thể đưa app online.
- **Files/Modules**: `docker-compose.prod.yml`, Nginx config, `.env.example`.
- **Acceptance Criteria**: Có server chạy, HTTPS TLS xanh, PHP-FPM / PM2 cấu hình chuẩn, không lộ cổng DB ra Internet.
- **Verification**: `curl -I https://api.domain.com` trả 200, DB không truy cập từ ngoài.
- **Rủi ro còn lại**: Khả năng server yếu không chịu nổi tải. Cần Load Test (Batch 7).

### Batch 2 — Production CI/CD Release Gate Hardening
- **Mục tiêu**: Thêm no-secret check, verification artifacts, không auto deploy nguy hiểm.
- **Lý do**: Ngăn code lọt lưới có chứa secret hoặc lỗi cú pháp.
- **Files/Modules**: `.github/workflows/production-readiness.yml`.
- **Acceptance Criteria**: Workflow verify 100% các Gate, không có step Push lên Prod tự động.
- **Verification**: Chạy `php artisan booking:doctor` trên CI.

### Batch 3 — SQL-first Production Patch Dry-run
- **Mục tiêu**: Áp dụng schema/patch an toàn, có backup trước đó.
- **Lý do**: Hạn chế downtime và data loss do database patch.
- **Files/Modules**: `scripts/ops/db-patch-dry-run.sh`.
- **Acceptance Criteria**: Patch script có lệnh mysqldump trước khi apply `.sql`.
- **Verification**: Check dump file sinh ra và row counts không đổi.

### Batch 4 — Payment/Webhook Production Safety
- **Mục tiêu**: Code validation Webhook Signature, Idempotency, Duplicate callbacks.
- **Lý do**: Rủi ro mất tiền lớn nhất (fake webhook).
- **Files/Modules**: `CustomerReservationBillPaymentService.php`, Payment controllers.
- **Acceptance Criteria**: API từ chối Webhook không có signature hợp lệ.
- **Verification**: Gửi fake Postman payload -> nhận HTTP 403/400.

### Batch 5 — Observability & Alerting
- **Mục tiêu**: Setup Logging/Monitoring cho 5xx, Outbox, DB Health.
- **Lý do**: Tránh "mù" khi hệ thống sập.
- **Files/Modules**: `config/logging.php`, Sentry/Datadog integrations.
- **Acceptance Criteria**: Có cảnh báo qua Slack/Telegram khi Outbox kẹt hoặc error spike.
- **Verification**: `php artisan notifications:outbox-health` cảnh báo tới kênh khi fail.

### Batch 6 — Backup/Restore/Disaster Recovery Rehearsal
- **Mục tiêu**: Script tự động backup DB và quy trình Rollback.
- **Lý do**: Đảm bảo Business Continuity.
- **Files/Modules**: Ops scripts backup.
- **Acceptance Criteria**: Script tự nén và upload DB lên S3 an toàn hàng ngày.
- **Verification**: Restore thành công DB từ S3 sang DB trắng.

### Batch 7 — Performance & Load Testing
- **Mục tiêu**: Identify bottlenecks trên Hot path.
- **Lý do**: Hệ thống F&B có spike traffic cực lớn vào giờ cao điểm.
- **Files/Modules**: K6 scripts.
- **Acceptance Criteria**: Load test 500 RPS không gây sập DB.
- **Verification**: `k6 run load_test.js`.

### Batch 8 — Security Hardening
- **Mục tiêu**: Chặn Rate limit, CORS strict, Cookie Secure.
- **Lý do**: Bảo vệ User data.
- **Files/Modules**: `config/cors.php`, `config/session.php`.
- **Acceptance Criteria**: Chỉ Staff-web/Customer-web origins được gọi API.
- **Verification**: Gọi từ localhost origin khác bị Block CORS.

### Batch 9 — Production Smoke Test Pack
- **Mục tiêu**: Non-destructive smoke test sau khi deploy.
- **Lý do**: Biết Prod đang sống không cần tạo rác.
- **Files/Modules**: Playwright production config.
- **Acceptance Criteria**: Script chạy login, check health, view dashboard không update DB.
- **Verification**: `npx playwright test --project=prod`.

### Batch 10 — Controlled Staging Cutover Rehearsal
- **Mục tiêu**: Dry-run tổng duyệt các Batch 1-9.
- **Lý do**: Make sure cutover thật sẽ mượt mà.
- **Files/Modules**: Runbooks.
- **Acceptance Criteria**: Deploy mất < 5 phút, zero downtime.
- **Verification**: Báo cáo Go/No-Go report.

## 5. Verification Strategy
Mọi Batch đều phải tuân thủ nghiêm ngặt quy tắc chạy verification trước khi Commit:
- Chạy format: `vendor/bin/pint`
- Chạy Doctor: `php artisan booking:doctor`
- Chạy Deploy Check: `php artisan booking:deploy-check`
- Backend tests & API Contract diff check.

## 6. Production Cutover Blockers
Nếu không giải quyết các Vấn đề sau, KHÔNG ĐƯỢC PHÉP CUTOVER:
- Chưa chốt Domain & Server.
- Chưa test Backup/Restore.
- Chưa có Secret Key thật của Payment Provider.

## 7. Recommended Next Batch
Batch quan trọng nhất cần thực hiện đầu tiên là:
**Batch 1 — Production Infrastructure Specification & Environment Lockdown**.
*(Xem mục 11. Next Prompt)*

## 8. Do-not-do List
- Không dùng `php artisan migrate` ở Production.
- Không test tính năng bằng UAT Seed/Demo Admin Account trên Production.
- Không cấu hình `.env` thủ công mà không qua hệ thống Provisioning / Secret Manager.
- Không bypass signature validation của Payment Gateway.

## 9. Definition of “Production Ready”
Dự án chỉ được dán nhãn **Production Ready** khi và chỉ khi:
- CI green 100%.
- Staging dry-run pass.
- SQL patch dry-run pass.
- Backup/restore rehearsal pass.
- Monitoring/alerting ready.
- Production secrets configured & injected securely.
- Domain/TLS ready.
- Payment sandbox/prod config verified.
- Queue/scheduler supervised.
- Smoke tests pass.
- Rollback approved.
- Operator approval obtained.

## 10. Final Recommendation
Đề xuất bắt đầu ngay với **Batch 1**, vì mọi tính năng code dẫu hoàn thiện đến đâu cũng vô nghĩa nếu không có chỗ để chạy (Server) và không có cách thức cấu hình môi trường an toàn.

---

## 11. Next Batch Prompt (Copy/Paste vào phiên làm việc mới)

```text
Bạn là Principal DevOps & Backend Engineer cho repo RestaurantPOS.
Tôi cần thực hiện **Batch 1 — Production Infrastructure Specification & Environment Lockdown** theo lộ trình roadmap-production-level-next.

Mục tiêu:
- Thiết lập file Docker Compose hoặc Nginx/Systemd boilerplate để triển khai Production (Web server, PHP-FPM, MySQL, Redis) không chứa secret hardcoded.
- Chuẩn hóa cấu hình `config/cors.php`, `config/session.php` để tương thích chặt chẽ với Domain thật (Staff-Web & Customer-Web).
- Bổ sung tài liệu provisioning infra (các package ubuntu cần cài đặt) vào `docs/runbooks/infra-provisioning.md`.

Yêu cầu bắt buộc:
- Repo SQL-first, không dùng migration.
- Không commit file `.env` thật, chỉ dùng template có placeholder an toàn.
- Nếu có cấu hình Nginx, phải chặn các file nhạy cảm (`.env`, `.git`).
- Kết thúc phải chạy verification commands: `php artisan booking:doctor --json`, `pint`, `booking:deploy-check`.

Hãy bắt đầu bằng việc Audit các file config hiện tại, sau đó lập Implementation Plan cho tôi duyệt trước khi viết/sửa code.
```
