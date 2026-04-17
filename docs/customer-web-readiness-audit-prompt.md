# Customer-Web Readiness Audit Prompt

Copy-paste prompt nay khi can giao cho Codex mot dot audit rat chat de tra loi cau hoi:
"Backend nay da thuc su san sang de bat tay lam `customer-web` chua?"

Prompt nay co chu dich nghiem ngat hon prompt audit tong quat. Muc tieu la tim cho het contract gap, auth/session drift, runtime blocker, artifact stale, va moi diem "tuong nhu da xong nhung chua khop dau-cuoi" truoc khi frontend customer bat dau phu thuoc vao backend.

```text
Ban dang lam viec trong repo:
C:\Users\Duong Vinh\RestaurantPOS-Laravel

Nhiem vu:
Audit tinh san sang cua backend de lam `customer-web` mot cach rat nghiem ngat, chat che, ki cang, khong bo sot rui ro, drift, hoac bat ky gap nao chua khep kin.

Day la pass AUDIT READINESS, khong phai pass "sua nhanh cho xanh". Khong tu dong danh dau ready chi vi route da ton tai hoac test sqlite dang pass.

Muc tieu cuoi cung:
1. Tra loi ro rang: `READY`, `PARTIALLY READY`, hay `NOT READY` cho viec bat dau `customer-web`.
2. Liet ke tat ca blocker/gap theo muc do uu tien.
3. Chung minh tung ket luan bang bang chung tu code, contract artifact, docs, config, tests, va runtime gates.
4. Chi ra chinh xac nhung gi can dong truoc khi frontend customer duoc phep phu thuoc vao backend nay.

Nguyen tac bat buoc:
- Doc code va artifact hien co truoc khi ket luan bat ky dieu gi.
- Khong dua vao ten controller, ten route, hay comment de xem nhu da xong.
- Khong dung doc mot vai file roi ket luan cam tinh.
- Khong coi passing sqlite tests la bang chung du cho runtime MySQL/Redis/scheduler.
- Khong coi generated SDK/Postman la source of truth neu frozen OpenAPI va runtime route surface khong khop.
- Khong coi "co harness check" la du neu harness qua nhe, chi check dem, hoac co the bi qua mat.
- Phai phan biet ro:
  - route ton tai
  - route contract-grade trong frozen OpenAPI
  - route da vao curated FE SDK
  - route da co mutation contract docs
  - route da co auth/session/docs/tests/runtime evidence day du
- Phai phat hien ca nhung loi am tham:
  - duplicate associative-array keys trong PHP config lam silently override
  - route co trong code nhung thieu contract-grade artifact
  - docs/runbook noi mot dang, runtime/config mot ne
  - error envelope da chuan hoa o mot so flow nhung leak shape khac o flow khac
  - owner/session/staff override bi bleed boundary
  - CORS co allow header nhung origin env rong nen browser van bi chan
  - route pass local nhung that bai khi Redis/runtime gate duoc bat
  - text/encoding/mojibake trong FE-visible payload, docs, examples, hoac tests

Bat buoc doc truoc:
- AGENTS.md
- .codex/AGENTS.md
- docs/runbooks/api-consumer-artifacts.md
- docs/runbooks/booking-api-contract.md
- config/customer_auth.php
- config/cors.php
- config/api_artifacts.php
- routes/api/customer_self_service.php
- app/Support/ApiErrorResponse.php
- app/Support/CustomerSessionRouteContract.php
- app/Http/Middleware/ResolveCustomerAuthMiddleware.php
- app/Http/Middleware/CustomerOrStaffMiddleware.php
- app/Services/CustomerAccessSessionService.php
- app/Platform/Harness/HarnessSuiteService.php
- storage/app/booking_release/openapi-v1.json
- build/api-consumer/mutation-contracts.md
- build/api-consumer/sdk/typescript/README.md
- tests/Feature/Auth/*
- tests/Feature/Reservation/CustomerReservation*
- tests/Feature/WaitingList/CustomerWaitingList*
- tests/Feature/Customer/*
- tests/Feature/CorsContractTest.php
- tests/Feature/Console/ApiConsumerArtifactsGenerateCommandTest.php
- tests/Unit/Config/CustomerAuthConfigContractTest.php
- tests/Unit/Http/CustomerOrStaffMiddlewareSessionContractTest.php

Khong duoc bo sot cac surface customer-web toi thieu nay:
- auth customer: login / me / refresh / logout
- menu/categories, menu/items, menu item detail, menu preorder preview
- tables/available
- table-holds: store / show / refresh / cancel
- reservations:
  - store / show
  - self-service list / cancel / reschedule
  - preorder show / preview / replace / clear
  - deposit preview / acknowledge / intent / revoke
  - deposit payment sessions: store / show / refresh / confirm
  - benefits preview / voucher apply-remove / loyalty redeem-release
  - bill / active-order / bill-preview
  - bill payment sessions: store / show / refresh / confirm
- waiting-list:
  - index / store / show / accept / confirm-arrival / decline / cancel
- me/loyalty
- me/vouchers
- me/data-export
- me/privacy-requests index/store

Quy trinh audit bat buoc:

1. Lap inventory route-surface cho customer-web
- Bat dau tu `routes/api/customer_self_service.php`.
- Tao bang inventory route-by-route, khong duoc audit theo kieu "nhom chuc nang" mo ho.
- Moi route phai co cac cot toi thieu:
  - method + uri
  - controller@action
  - auth mode thuc te
  - co can `X-Customer-Token` khong
  - co can `X-Session-Id` khong
  - co can `Idempotency-Key` khong
  - co can `row_version` khong
  - owner-only, session-bound, hay customer-or-staff
  - runtime dependency (Redis / scheduler / payment provider / release artifact / MySQL-only assumption)
  - contract-grade hay fallback-only trong frozen OpenAPI
  - co trong curated TypeScript SDK khong
  - co trong mutation-contract docs khong
  - test coverage nao dang bao ve no
  - docs/runbook nao mo ta no
  - verdict: closed / partial / missing / risky

2. Audit auth + session contract rat chat
- Xac thuc rang customer-web dung header-based auth:
  - `X-Customer-Token`
  - `X-Session-Id` khi route can session propagation
- Xac thuc `supports_credentials=false` va frontend KHONG duoc dua vao cookie-session.
- So khop:
  - `config/customer_auth.php`
  - `config/cors.php`
  - `ResolveCustomerAuthMiddleware`
  - `CustomerOrStaffMiddleware`
  - `CustomerSessionRouteContract`
  - `CustomerAccessSessionService`
  - docs/runbook
  - generated SDK/mutation docs
- Khong chi doc gia tri loaded config. Phai doc text nguon `config/customer_auth.php` de bat cac associative-array key bi lap lam silently collapse. Day la loai loi co the khien config "nhin co ve du" nhung runtime chi con mot phan.
- Kiem tra ro boundary:
  - owner path
  - customer session path
  - wrong session
  - wrong customer
  - staff override cho shared route
  - staff KHONG duoc bi nham la customer owner tren customer-only flow
- Kiem tra `401` vs `403` co on dinh, machine-readable, va dung envelope hay khong.

3. Audit consumer contract readiness
- Frozen OpenAPI artifact la contract source chinh, khong phai controller.
- Kiem tra moi route customer-web dang o trang thai nao:
  - contract-grade day du
  - fallback-only
  - co route runtime nhung artifact chua endorse
  - co artifact nhung runtime route/doc da drift
- Neu route can cho customer-web ma:
  - khong co trong frozen OpenAPI, hoac
  - chi la fallback-only, hoac
  - khong co schema loi/response ro rang, hoac
  - chua vao curated SDK batch trong khi frontend du kien dung ngay,
  thi khong duoc danh dau ready.
- Kiem tra cac contract quan trong:
  - success envelope `{data: ...}` / `{data: [...], meta: ...}`
  - error envelope co `error_code`, `category_code`, `message`, `request_id`, `errors`
  - `owner_scope_denied`, `authentication_required`, `forbidden_capability`, `stale_write`, `resource_conflict`, `idempotency_conflict`
  - enum/state artifacts co du cho FE khong
  - `row_version`, `Idempotency-Key`, `X-Session-Id` co duoc ghi ro trong mutation contract khong
- Cross-check dac biet cac surface docs da tu tung thua nhan la "current limitations" de xem chung da duoc dong thuc su chua, dac biet:
  - customer menu
  - loyalty / voucher read surfaces

4. Audit data visibility va owner-contract
- Kiem tra response customer co leak field staff-only, finance-only, provider_response_json, internal metadata, hay capability context khong.
- Kiem tra list/show theo owner va theo session co redaction khac nhau dung muc khong.
- Kiem tra deposit/bill/payment preview co lo thong tin ngoai scope khong.
- Kiem tra waiting-list owner flow va reservation self-service flow co truly owner-bound/session-bound khong.

5. Audit mutation safety cho browser client
- Kiem tra route nao can `Idempotency-Key`; route nao dang mutation nhung chua co idempotency guard.
- Kiem tra stale-write va `row_version` cho cancel/reschedule/benefits/payment session cac flow co can hay khong.
- Kiem tra conflict taxonomy co nhat quan de FE branch retry/reload khong.
- Kiem tra session propagation:
  - request validator
  - middleware
  - service
  - SDK helper
  - mutation docs
  - OpenAPI headers
  phai khop nhau.

6. Audit docs, artifacts, va drift control
- Kiem tra:
  - `storage/app/booking_release/openapi-v1.json`
  - `build/api-consumer/sdk/typescript/*`
  - `build/api-consumer/mutation-contracts.md`
  - `build/api-consumer/enum-state-map.json`
  - runbooks
  - route inventory fixtures
- Neu can, capture `git status --short` truoc.
- Neu chay command co the generate artifact, bat buoc ghi nhan diff phat sinh la bang chung drift, khong xem la churn vo hai.
- Khong hand-wave bang cau "chi can regenerate la xong". Neu artifact stale thi do van la readiness gap can ghi ro.

7. Audit runtime readiness, khong chi unit/feature tests
- Nho rang repo nay SQL-first va runtime production phu thuoc MySQL 8, Redis, scheduler heartbeat, va mot so release artifacts.
- Kiem tra route customer nao nam trong `require.redis` group.
- Kiem tra local sqlite pass co che giau blocker runtime nao khong.
- Kiem tra go-live/readiness gates co du bang chung cho customer-web flows khong.

8. Audit test surface va bang chung bao ve
- Moi route/flow quan trong phai co it nhat mot trong cac loai bang chung:
  - feature auth/session test
  - owner-contract test
  - visibility/redaction test
  - OpenAPI/artifact drift test
  - CORS contract test
  - runtime smoke / golden flow / release readiness evidence
- Neu co flow quan trong nhung test chi cover happy path, khong cover wrong user / wrong session / expired session / stale row_version / idempotency replay / CORS preflight / forbidden-vs-unauthorized, phai flag la gap.

Lenh can uu tien chay de lay bang chung:
- `php artisan booking:harness:web-auth --json`
- `php artisan booking:harness:golden-flows --json`
- `php artisan booking:route-contract:reconcile --json`
- `php artisan booking:release-manifest --verify-frozen --json`
- `php artisan test tests/Feature/Auth tests/Unit/Http/Middleware tests/Unit/Config/CustomerAuthConfigContractTest.php tests/Unit/Config/StaffAuthConfigContractTest.php`
- `php artisan test tests/Unit/Http/CustomerOrStaffMiddlewareSessionContractTest.php tests/Feature/CorsContractTest.php tests/Feature/Http/ApiValidationPayloadCompatibilityTest.php`
- `php artisan test tests/Feature/Reservation/CustomerReservation* tests/Feature/WaitingList/CustomerWaitingList* tests/Feature/Customer`
- `php artisan test tests/Feature/Console/ApiConsumerArtifactsGenerateCommandTest.php tests/Feature/Infrastructure/ApiOpenApiContractCoverageTest.php`
- `php artisan booking:doctor --json`
- `php artisan booking:deploy-check --mode=preflight`

Neu moi truong cho phep va can xac minh freshness drift:
- capture `git status --short`
- sau do moi can nhac chay `composer api:artifacts`
- neu co diff phat sinh, phai report no thanh contract/artifact drift, khong duoc lam im.

Quy tac danh gia muc do nghiem trong:
- P0: blocker. Khong the bat dau customer-web an toan, hoac bat dau se rat de doi contract/vo auth/runtime.
- P1: high risk. Bat dau duoc nhung chac chan gay rework, drift, hoac bug nghiem trong som.
- P2: medium. Chua chan duong ngay, nhung se tao friction, regression, hoac khong on dinh.
- P3: low. Nen don dep, nhung khong chan readiness.

Tieu chi de duoc phep ket luan `READY`:
- Khong con P0.
- Khong con P1 chua co mitigation ro rang.
- Moi route quan trong cua customer-web co contract ro rang, auth/session ro rang, va test evidence hop ly.
- Frozen OpenAPI, generated SDK/artifacts, docs/runbook, va runtime route surface khop nhau o nhung flow customer-web can dung.
- CORS, auth headers, idempotency, row_version, va error envelope da du on dinh cho browser client.
- Runtime gates khong phat hien blocker lien quan MySQL/Redis/scheduler/release artifacts.

Neu khong dat, phai ket luan `PARTIALLY READY` hoac `NOT READY`.

Output bat buoc, findings truoc summary sau:

1. Final verdict
- READY / PARTIALLY READY / NOT READY
- Mot doan ngan noi ly do cot loi

2. Blockers va gaps theo severity
- Moi finding phai co:
  - severity
  - route/file pham vi
  - bang chung cu the
  - vi sao no chan customer-web
  - can dong cai gi de close

3. Route inventory matrix
- In bang route-by-route cho tat ca surface customer-web
- Khong gom rut gon kieu "reservation flows mostly ok"

4. Contract drift report
- Runtime route vs frozen OpenAPI
- OpenAPI vs generated SDK
- OpenAPI vs mutation-contract docs
- Docs/runbook vs config/runtime

5. Auth/session boundary report
- owner
- session
- staff override
- unauthorized / forbidden split
- CORS/browser contract

6. Test and runtime evidence report
- Da chay lenh nao
- Lenh nao bi block
- Lenh nao pass/fail
- Khoang trong bang chung con thieu

7. Closure plan
- Danh sach batch nho de dong readiness gaps
- Uu tien cao den thap
- Moi batch neu ro file likely touch, tests can them, va risk can canh

Cam ket chat luong:
- Neu chua co bang chung, noi thang la chua co bang chung.
- Neu gap nay chi duoc "che" boi docs/harness nhe, van phai flag.
- Neu co config/map co nguy co bi silently override, phai noi ro.
- Neu route can cho customer-web nhung chi la fallback-only surface, phai noi ro.
- Neu text/encoding FE-facing bi loi, phai noi ro.
- Khong duoc de sot bat ky gap nao ma frontend co the dam vao trong 2-3 sprint dau.
```

## Su dung

Prompt nay phu hop khi ban muon mot pass "gatekeeper" truoc khi bat tay lam `customer-web`, hoac truoc khi cho phep frontend team consume backend contract. Neu sau audit muon chuyen sang pass remediation, nen tach thanh cac batch nho theo domain va giu output discipline cua repo.
