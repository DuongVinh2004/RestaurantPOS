# Project Completion Audit Prompt

Copy-paste prompt nay khi can giao cho Codex mot dot ra soat toan bo repo theo huong production hardening, fix loi thuc te, va dong cac flow con mo ma khong rewrite lon.

```text
Ban dang lam viec trong repo: C:\Users\Duong Vinh\RestaurantPOS-Laravel

Bat buoc doc truoc khi sua:
- AGENTS.md cua repo
- README.md
- cac config/changelog/runbook lien quan den khu vuc sap sua

Muc tieu:
- Audit toan bo project de tim:
  - bug thuc te
  - flow chua hoan chinh
  - assumption mong quanh auth, booking, order, kitchen, checkout, inventory, reporting, ops
  - contract drift giua code, tests, schema SQL-first, artifacts, docs, va web consumers
- Sua cac diem lech co tac dong that bang patch nho, reviewable, an toan cho production.
- Giu du an tien gan hon trang thai production-ready, khong overbuild feature moi.

Thu tu uu tien domain:
1. Auth / Identity / RBAC
2. Walk-in + service session + dine-in POS flow
3. Order lifecycle
4. Kitchen / KDS
5. Checkout / payment / refund / cashier shift
6. Inventory basic but useful
7. Reporting / ops / go-live hardening

Nguyen tac ky thuat:
- Doc code hien co truoc khi thay doi bat ky file nao.
- Khong rewrite lon, khong cosmetic refactor, khong doi kien truc neu khong bi blocker.
- Keep controllers thin.
- Dat business logic trong services/domain logic.
- Bao ve production safety: transaction, idempotency, locking, authorization, audit, branch scope.
- Khong doi contract backend machine-facing neu khong that su can:
  - khong tu y doi enum/API response keys/schema tai chinh
  - khong xoa alias/legacy compatibility dang duoc giu cho rollout an toan
  - khong thay doi luu tru UTC thanh local time toan cuc neu he thong dang dung UTC
- Neu thay doi co y nghia, bat buoc them hoac cap nhat test.
- Chia thanh cac batch nho, reviewable.

Khu vuc can audit toi thieu:
- config/**
- app/Http/**
- app/Services/**
- app/Support/**
- routes/**
- database/schema/**
- database/patches/**
- build/api-consumer/**
- storage/app/booking_release/**
- staff-web/**
- customer-web/** neu co
- tests/**

Checklist audit theo nhom:

1. Auth / RBAC / scope isolation
- Route nao thieu capability guard?
- Co bleed giua admin/staff/customer scope khong?
- Co mutation nao thieu authorization regression test khong?

2. FOH / reservation / table / check-in
- Availability -> hold -> assign -> check-in -> move -> release co guard day du chua?
- Co stale row_version, branch drift, overlap, conflict, hoac idempotency gap nao khong?

3. Order / kitchen
- State transition co hop ly va nhat quan giua order item, station routing, kitchen ticket khong?
- Feature flag tat/mo co lam lo route hoac path loi khong?

4. Checkout / finance
- Bill lock, capture, finalize, refund, webhook replay, cashier shift, invoice, reconciliation co giu finance invariants khong?
- Co duplicate/retry/race path nao chua co guard hoac test khong?

5. Inventory / purchasing
- Branch scope, unit consistency, stock movement, PO receiving, over-receive protection co chac chua?

6. Reporting / ops / release
- Health, doctor, outbox, metrics, realtime, retention, bootstrap, release artifacts, SQL-first schema co drift khong?
- API artifacts/OpenAPI/Postman/frontend consumer docs co con stale khong?

7. User-facing assumptions
- Timezone, locale, date/time, currency, seed/demo data, placeholder, docs operator-facing, va examples co dung ngu canh san pham khong?
- Neu runtime/db dang dung UTC, chi sua default van hanh, input/output/display/docs/demo data; khong rewrite storage model.

Quy trinh thuc thi:
1. Audit va liet ke findings theo muc do uu tien.
2. Chi sua nhung diem co tac dong that, uu tien loi production-sensitive va flow core chua dong.
3. Sau moi batch, chay verification hep theo changed files:
  - PHPUnit/Pest tests lien quan
  - frontend tests neu co TS/React thay doi
  - artifact regeneration neu contract/docs thay doi
  - artisan/config/runtime gates phu hop neu cham vao release/ops/schema
4. Khong chay full-repo rewrite/auto-format neu no tao churn lon khong lien quan.
5. Neu gap blocker moi truong (MySQL/Redis/scheduler/provider ngoai), phan biet ro:
  - code regression
  - environment/runtime blocker
6. Neu commit/push:
  - tao branch `codex/<ten-ngan-gon>`
  - stage co chu dich
  - commit message ngan, ro
  - push len origin neu auth san sang
  - neu khong push duoc, noi ro ly do ky thuat

Output discipline cho moi batch:
1. Intent
2. Findings addressed
3. Changed files
4. Added/updated tests
5. Verification run
6. Remaining risks

Yeu cau cuoi:
- Khong dung o phan tich; neu khong bi blocker thi phai thuc su code, them test, va chay verification hep.
- Bao cao findings truoc, tom tat sau.
- Neu co diem can xac nhan nghiep vu/phap ly, khong doan; ghi ro la can confirm.
```

## Suggested operator note

Dung prompt nay cho cac dot "integrator pass" hoac "final production hardening pass" sau khi nhieu batch da duoc merge. Neu task co pham vi hep hon mot domain, nen dung prompt nho hon theo workstream de tranh quet qua rong.
