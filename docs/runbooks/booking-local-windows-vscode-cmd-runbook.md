# Booking Local Windows VSCode CMD Runbook

Runbook nay danh cho may Windows, dung `cmd.exe` trong terminal cua VSCode, va bam sat contract thuc te cua repo nay.

Muc tieu:

- chay local backend dung cach
- biet khi nao can bootstrap lai
- biet khi nao chi can mo runtime hang ngay
- biet cach bootstrap staff key, customer token, reporting
- tranh nham `php artisan migrate` la duong setup chinh

## 1. Quy uoc

- Tat ca lenh ben duoi dung cho `cmd.exe`, khong phai PowerShell.
- Mo VSCode tai dung thu muc repo:
  - `C:\Users\Duong Vinh\RestaurantPOS-Laravel`
- Neu terminal cua VSCode da mo tai repo root thi khong can `cd`.
- Repo nay la `SQL-first`.
- Duong bootstrap chuan la:
  - `composer bootstrap:booking`
- Khong dung `php artisan migrate` lam luong setup local/staging/release chinh.

## 2. Dieu kien can co tren may

Can co cac thanh phan sau:

- PHP 8.2
- Composer 2
- Node.js + npm
- MySQL 8 compatible
- `mysql.exe` trong `PATH`
- Redis

Neu service MySQL cua may khong tien dung hoac user hien tai khong co quyen `Start-Service`, repo nay co script chay MySQL local bang datadir trong `storage\mysql-local\data`:

```cmd
powershell -ExecutionPolicy Bypass -File scripts\ops\start-local-mysql.ps1 -Restart
```

Kiem tra nhanh:

```cmd
php -v
composer -V
node -v
npm -v
mysql --version
redis-server --version
redis-cli --version
```

Neu Redis chua co tren may, mo `cmd` bang `Run as administrator` va cai:

```cmd
choco install redis -y
```

## 3. Chay lan dau tren may moi

### 3.1. Tao file `.env`

```cmd
copy .env.example .env
```

Sua `.env` theo DB/Redis thuc te cua may ban.

Toi thieu can dung:

```env
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=restaurantdb
DB_USERNAME=root
DB_PASSWORD=123456

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Neu `mysql.exe` khong nam trong `PATH`, them vao `.env`:

```env
MYSQL_BIN=C:\duong-dan-den-mysql\mysql.exe
```

### 3.2. Cai dependency

```cmd
composer install
npm install
```

### 3.3. Tao app key

```cmd
php artisan key:generate --ansi --force
```

### 3.4. Bootstrap DB + seed + site + reporting

```cmd
composer bootstrap:booking
```

Lenh nay se:

- import `database/schema/mysql-schema.sql`
- apply `database/patches/*.sql`
- seed `SystemReferenceDataSeeder`
- clear cache Laravel
- chay `booking:bootstrap-site`
- rebuild reporting snapshots
- normalize release artifacts
- refresh release manifest

Neu lenh tren pass, ban khong can chay lai moi ngay.

## 4. Chay project moi ngay

Moi lan mo project de dev/test local, chi can 1 lenh bat runtime backend va 1 tien trinh tuy chon cho frontend.

### 4.0. One-liner dev

Neu chi can backend Laravel nhu luong ban vua chay thu cong:

```cmd
npm run dev:be
```

Lenh nay giu lai du lieu local neu booking schema da ton tai. Neu day la lan dau tren DB trong, script se bootstrap day du. Neu muon co tinh reset DB ve release schema va seed/demo data moi, chay:

```cmd
npm run dev:be:reset
```

Neu may khong co MySQL Server 8 hoac Redis binary nhung Docker Desktop dang chay, `npm run dev:be` va `npm run dev:all` co the fallback sang `docker-compose.testing.yml` de bat service `mysql`/`redis` local-only theo `.env` `DB_PORT` va `REDIS_PORT`.

Lenh nay cung refresh demo login pack de hai tai khoan `uat.customer.primary` va `uat.staff` dung password `UatDemo!123` khop voi DB hien tai. Neu chi muon bootstrap backend ma khong seed demo login pack, chay:

```cmd
npm run dev:be -- -SkipUatPack
```

Neu ban da tu quan ly Redis rieng va khong muon script bat repo-local Redis, chay:

```cmd
npm run dev:be -- -SkipRedis
```

Neu muon chay backend va ca 2 frontend trong cung mot terminal:

```cmd
npm run dev:all
```

Neu muon chay ca 3 process va reset DB truoc khi serve:

```cmd
npm run dev:all:reset
```

Mac dinh:

- backend: `http://127.0.0.1:8000`
- customer-web: `http://127.0.0.1:3000`
- staff-web: `http://127.0.0.1:5173`

Dung `Ctrl+C` de dung ca nhom process.

Ngay sau khi `npm run dev:all` len xong, prove lane bang:

```cmd
npm run dev:smoke
```

`npm run dev:smoke` chi pass khi:

- manifest `storage\app\uat\scenario-pack.json` ton tai
- backend `127.0.0.1:8000` dang nghe
- customer-web `127.0.0.1:3000` dang nghe
- staff-web `127.0.0.1:5173` dang nghe
- customer va staff demo login trong UAT pack dang dung

Neu `dev:smoke` fail vi port chua len, doi startup xong roi chay lai. Neu fail vi manifest, refresh pack bang `npm run dev:all` hoac script UAT ben duoi.

Luu y: `npm run dev:all` + `npm run dev:smoke` la lane dev/UI va live-E2E. Neu ban can `booking:doctor`, scheduler heartbeat, hoac outbox readiness thi van phai dung lane `npm run runtime:up` + `npm run runtime:preflight` o muc ben duoi.

### 4.1. One-liner runtime

```cmd
npm run runtime:up
```

Lenh nay se:

- ensure repo-local MySQL tren `127.0.0.1:3306`
- ensure repo-local Redis tren `127.0.0.1:6379`
- start `php artisan serve`
- start `php artisan schedule:work`
- touch 1 lan scheduler heartbeat de `booking:doctor` khong fail ngay luc vua len runtime

Lenh npm nay chay duoc tu `cmd.exe` va PowerShell. Tren Windows, runtime se uu tien helper PowerShell cua repo cho MySQL/Redis. Neu may khong co MySQL Server 8 hoac Redis binary nhung Docker Desktop dang chay, lane co the fallback sang `docker-compose.testing.yml` de start MySQL/Redis local-only theo `.env` `DB_PORT` va `REDIS_PORT`.

Khi can ha runtime:

```cmd
npm run runtime:down
```

PowerShell debug truc tiep van co san neu can xem loi process Windows rieng:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\ops\local-runtime.ps1 -Action up
powershell -ExecutionPolicy Bypass -File scripts\ops\local-runtime.ps1 -Action down
```

Linux/macOS hoac CI-like shell dung cung contract npm:

```bash
npm run runtime:up
composer bootstrap:booking
npm run runtime:preflight
npm run runtime:down
```

Neu CI khong cap san MySQL/Redis service, bat Docker truoc de Compose fallback local-only co the expose MySQL/Redis qua `.env` `DB_PORT` va `REDIS_PORT`.

Neu can debug tung process rieng le, dung fallback thu cong ben duoi.

### 4.2. Manual fallback theo tung process

Chocolatey package Redis tren may Windows nay khong tu tao service. Khong chay truc tiep config mac dinh trong `C:\ProgramData\chocolatey\lib\redis\tools` vi thu muc do co the chi doc voi user thuong, lam Redis tu bao `MISCONF` sau khi RDB snapshot fail.

#### MySQL local, neu can

Neu may ban da co MySQL service rieng dang chay tren `127.0.0.1:3306`, giu nguyen cach van hanh do.

Neu khong, dung script repo-local:

```cmd
powershell -ExecutionPolicy Bypass -File scripts\ops\start-local-mysql.ps1 -Restart
```

#### Redis

Cach chay on dinh cho repo nay la dung script local de Redis ghi snapshot vao `storage\redis`:

```cmd
powershell -ExecutionPolicy Bypass -File scripts\ops\start-local-redis.ps1 -Restart
```

Kiem tra Redis:

```cmd
redis-cli ping
```

Ket qua dung:

```text
PONG
```

#### Laravel app

```cmd
php artisan serve
```

#### Scheduler

```cmd
php artisan schedule:work
```

Scheduler la bat buoc cho local neu ban muon business flow dong:

- reservation expiry
- waiting-list expiry
- hold expiry
- reminders
- outbox processing
- reporting freshness

#### Frontend assets, neu can

Neu ban co dung giao dien frontend:

```cmd
npm run dev
```

Neu chi test API thi co the bo qua buoc nay.

## 5. Health check sau khi app da len

Sau khi `npm run runtime:up` xong, chay:

```cmd
npm run runtime:preflight
php artisan booking:doctor --json
php artisan notifications:outbox-health --json
php artisan booking:release-manifest --json
php artisan booking:deploy-check --mode=preflight --strict --json
```

Trang thai local dung la:

- `db.ok = true`
- `redis.ok = true`
- `scheduler.ok = true`
- `outbox.ok = true`
- neu `scheduler` bao bi block boi `runtime.redis`, sua Redis truoc roi moi debug heartbeat
- neu `outbox` bao bi block boi `runtime.db`, sua MySQL truoc roi moi doc outbox health/backlog

Bootstrap SQL-first hien tai co prime 1 lan scheduler heartbeat de runtime verify khong fail ngay sau bootstrap. Tuy nhien de `scheduler.ok` tiep tuc xanh, van phai de `php artisan schedule:work` chay lien tuc.

## 6. Nen test the nao sau khi project da chay

Nen test theo 3 lop, theo dung thu tu nay:

1. runtime health
2. automated tests
3. smoke / UAT thu cong

### 6.1. Lop 1: runtime health

Day la lop can chay moi khi ban vua mo project, vua doi `.env`, vua bootstrap lai, hoac vua pull code.

```cmd
npm run runtime:preflight
php artisan booking:doctor --json
php artisan notifications:outbox-health --json
php artisan booking:release-manifest --json
php artisan booking:deploy-check --mode=preflight --strict --json
```

Neu lop nay chua xanh, khong nen nhay sang UAT flow.

### 6.2. Lop 2: automated tests

Luu y quan trong:

- `php artisan test` / `phpunit` dung `APP_ENV=testing`
- theo [phpunit.xml](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/phpunit.xml), test mac dinh dung:
  - `DB_CONNECTION=sqlite`
  - `DB_DATABASE=:memory:`
- nghia la automated tests khong can `php artisan serve` dang chay moi thuc hien duoc
- nhung runtime smoke va API manual thi van can app + redis + scheduler

#### 6.2.1. Quick local test sau moi lan sua code

```cmd
php artisan test tests/Feature/Console
```

Day la cum test nhanh va rat huu ich de chan drift operational:

- [tests/Feature/Console/BookingDoctorCommandTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Console/BookingDoctorCommandTest.php)
- [tests/Feature/Console/BookingDeployCheckCommandTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Console/BookingDeployCheckCommandTest.php)
- [tests/Feature/Console/BookingReleaseManifestCommandTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Console/BookingReleaseManifestCommandTest.php)
- [tests/Feature/Console/SiteBootstrapCommandTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Console/SiteBootstrapCommandTest.php)
- [tests/Feature/Console/ReportingSnapshotCommandsTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Console/ReportingSnapshotCommandsTest.php)
- [tests/Feature/Console/CustomerAccessSessionBootstrapCommandsTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Console/CustomerAccessSessionBootstrapCommandsTest.php)
- [tests/Feature/Console/StaffApiKeyBootstrapCommandsTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Console/StaffApiKeyBootstrapCommandsTest.php)

#### 6.2.2. Test theo scope go-live can uu tien

Chay cum nay khi ban muon test lai nhung flow sat voi day-1 rollout:

```cmd
php artisan test tests/Feature/Staff/StaffTableBoardHttpFlowTest.php tests/Feature/Staff/StaffWaitingListLifecycleTest.php tests/Feature/Staff/StaffCheckInFlowTest.php tests/Feature/Staff/StaffCheckoutHttpFlowTest.php tests/Feature/Staff/StaffCashierShiftHttpFlowTest.php tests/Feature/Staff/StaffFinancialReconciliationHttpFlowTest.php tests/Feature/Staff/StaffReportingReadModelsHttpFlowTest.php tests/Feature/Reservation/ReservationHttpFlowTest.php tests/Feature/Reservation/CustomerReservationSelfServiceHttpFlowTest.php tests/Feature/Reservation/CustomerReservationDepositSelfServiceFlowTest.php tests/Feature/Reservation/CustomerReservationOrderBillSelfPaymentFlowTest.php tests/Feature/WaitingList/CustomerWaitingListSelfServiceHttpFlowTest.php tests/Feature/WaitingList/CustomerWaitingListOwnerContractHttpFlowTest.php
```

Cum file tren phu hop voi 5 UAT chinh:

- reservation core
- waiting list
- dine-in settlement
- customer payment
- cashier / reconciliation / reporting

#### 6.2.3. Chay full automated test

Dung khi ban muon check tong the truoc khi commit hoac truoc khi merge:

```cmd
php artisan test
```

Neu ban co Git Bash hoac WSL, co the chay gate script cua repo:

```bash
bash scripts/ci/booking-smoke-gate.sh
bash scripts/ci/booking-full-gate.sh
```

Script nay ton tai that trong repo:

- [scripts/ci/booking-smoke-gate.sh](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/scripts/ci/booking-smoke-gate.sh)
- [scripts/ci/booking-full-gate.sh](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/scripts/ci/booking-full-gate.sh)
- [scripts/ci/booking-ops-smoke.sh](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/scripts/ci/booking-ops-smoke.sh)
- [scripts/ci/booking-reliability-smoke.sh](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/scripts/ci/booking-reliability-smoke.sh)

### 6.3. Lop 3: smoke / UAT thu cong

Lop nay moi dung app dang chay that, redis that, scheduler that.

#### 6.3.0. Canonical UAT pack cho customer-web live proof

Manifest UAT chuan cho local lane nam tai:

```text
storage\app\uat\scenario-pack.json
```

Refresh pack thu cong:

```cmd
powershell -ExecutionPolicy Bypass -File scripts\uat\Bootstrap-UatPack.ps1 -BaseUrl http://127.0.0.1:8000
```

Reset pack va seeded UAT data khi can lam sach:

```cmd
powershell -ExecutionPolicy Bypass -File scripts\uat\Reset-UatPack.ps1
```

Sau khi reset, phai bootstrap pack lai truoc khi chay:

- `npm run dev:smoke`
- `cd customer-web && npm run verify:release:live`

#### 6.3.1. UAT-0: xac nhan runtime

```cmd
php artisan booking:doctor --json
curl.exe http://127.0.0.1:8000/api/v1/staff/tables/board -H "X-Staff-Key: <PLAINTEXT_STAFF_KEY>"
curl.exe http://127.0.0.1:8000/api/v1/staff/reservations -H "X-Staff-Key: <PLAINTEXT_STAFF_KEY>"
```

#### 6.3.2. UAT-1: reservation core

Muc tieu:

- tao reservation
- staff thay trong inbox/board
- assign table
- check-in

Can tham khao payload chuan trong test:

- [tests/Feature/Reservation/ReservationHttpFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Reservation/ReservationHttpFlowTest.php)
- [tests/Feature/Staff/StaffReservationInboxFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffReservationInboxFlowTest.php)
- [tests/Feature/Staff/StaffReservationBoardAssignmentFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffReservationBoardAssignmentFlowTest.php)
- [tests/Feature/Staff/StaffCheckInFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffCheckInFlowTest.php)

#### 6.3.3. UAT-2: waiting list

Muc tieu:

- vao waiting-list
- notify / invite
- customer accept hoac confirm-arrival
- staff seat

Can tham khao:

- [tests/Feature/Staff/StaffWaitingListLifecycleTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffWaitingListLifecycleTest.php)
- [tests/Feature/Staff/StaffWaitingListSemiAutomationFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffWaitingListSemiAutomationFlowTest.php)
- [tests/Feature/WaitingList/CustomerWaitingListSelfServiceHttpFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/WaitingList/CustomerWaitingListSelfServiceHttpFlowTest.php)
- [tests/Feature/WaitingList/CustomerWaitingListOwnerContractHttpFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/WaitingList/CustomerWaitingListOwnerContractHttpFlowTest.php)

#### 6.3.4. UAT-3: dine-in settlement

Muc tieu:

- check-in
- open order
- add items
- serve
- checkout
- invoice
- reconciliation

Can tham khao:

- [tests/Feature/Staff/StaffTableOrderIdempotencyReplayServiceTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffTableOrderIdempotencyReplayServiceTest.php)
- [tests/Feature/Staff/StaffOrderItemLifecycleFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffOrderItemLifecycleFlowTest.php)
- [tests/Feature/Staff/StaffCheckoutHttpFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffCheckoutHttpFlowTest.php)
- [tests/Feature/Staff/StaffFinanceInvoiceAndAccountingExportHttpFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffFinanceInvoiceAndAccountingExportHttpFlowTest.php)
- [tests/Feature/Staff/StaffFinancialReconciliationHttpFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffFinancialReconciliationHttpFlowTest.php)

#### 6.3.5. UAT-4: customer payment

Muc tieu:

- deposit hoac bill payment session
- callback / webhook
- terminal state update
- summary cap nhat dung

Can tham khao:

- [tests/Feature/Payments/PaymentProviderWebhookFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Payments/PaymentProviderWebhookFlowTest.php)
- [tests/Feature/Reservation/CustomerReservationDepositPaymentSessionFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Reservation/CustomerReservationDepositPaymentSessionFlowTest.php)
- [tests/Feature/Reservation/CustomerReservationOrderBillSelfPaymentFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Reservation/CustomerReservationOrderBillSelfPaymentFlowTest.php)

Neu local `.env` dang de:

- `PAYMENT_CUSTOMER_SELF_PAY_ENABLED=true`
- default provider `simulated`

thi ban co the smoke test self-pay local.

#### 6.3.6. UAT-5: daily operations

Muc tieu:

- open shift
- run service day
- close shift
- reporting usable
- outbox health sach

Can tham khao:

- [tests/Feature/Staff/StaffCashierShiftHttpFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffCashierShiftHttpFlowTest.php)
- [tests/Feature/Staff/StaffReportingReadModelsHttpFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Staff/StaffReportingReadModelsHttpFlowTest.php)
- [tests/Feature/Console/ReportingSnapshotCommandsTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Console/ReportingSnapshotCommandsTest.php)

### 6.4. Thu tu toi khuyen dung de test local

Neu ban muon test nhanh sau khi code:

```cmd
php artisan booking:doctor --json
php artisan test tests/Feature/Console
php artisan test tests/Feature/Staff/StaffTableBoardHttpFlowTest.php tests/Feature/Staff/StaffWaitingListLifecycleTest.php tests/Feature/Staff/StaffCheckoutHttpFlowTest.php
```

Neu ban muon test truoc khi commit:

```cmd
php artisan booking:doctor --strict
php artisan test
```

Neu ban muon test giong gate cua repo:

```cmd
php artisan booking:doctor --strict
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:release-manifest --verify-frozen --json
php artisan test
```

Neu ban muon prove customer-web theo lane release:

```cmd
cd customer-web
npm run verify:release
```

Lenh nay la CI-safe. No prove lint, typecheck, Vitest, production build, va Playwright smoke mock-backed.

Neu ban muon prove live dung runtime that:

```cmd
cd customer-web
set NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000
set CUSTOMER_WEB_LIVE_APP_HOST=127.0.0.1
set CUSTOMER_WEB_LIVE_APP_PORT=3000
set CUSTOMER_WEB_LIVE_IDENTIFIER=uat.customer.primary
set CUSTOMER_WEB_LIVE_PASSWORD=UatDemo!123
npm run verify:release:live
```

Lenh nay khong CI-safe. No se fail ro neu:

- thieu env live
- thieu hoac vo `storage\app\uat\scenario-pack.json`
- backend `127.0.0.1:8000` khong len
- customer-web `127.0.0.1:3000` khong len
- `NEXT_PUBLIC_ENABLE_DEV_MOCKS=true`

Dung `npm run verify:release` khi ban can lane release an toan cho CI. Chi dung `npm run verify:release:live` khi muon prove live runtime that.

## 7. Viec can lam sau khi pull code moi

Khong phai luc nao cung can bootstrap lai. Dung quy tac nay:

### 7.1. Luon nen chay

```cmd
composer install
npm install
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 7.2. Chi chay lai bootstrap khi co 1 trong cac truong hop sau

- DB local vua bi xoa
- doi may moi
- pull code moi co thay doi trong:
  - `database/schema/mysql-schema.sql`
  - `database/patches/`
  - `db_all.sql`
- local data dang bi vo va ban muon reset sach

Lenh:

```cmd
composer bootstrap:booking
```

### 7.3. Chay smoke check sau khi pull

```cmd
php artisan booking:doctor --json
php artisan test
```

## 8. Khi sua `.env` hoac config

Moi khi sua `.env` hoac file trong `config/`, chay:

```cmd
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

Neu app dang chay, nen restart lai:

- `php artisan serve`
- `php artisan schedule:work`

## 9. Khi can reset local data hoan toan

Chi lam khi ban that su muon reset sach local DB.

### 9.1. Dung app va scheduler

Dong cac terminal dang chay:

- `php artisan serve`
- `php artisan schedule:work`

### 9.2. Drop va tao lai database

Can sua lai user/password/db name theo `.env` cua ban.

Vi du:

```cmd
mysql -u root -p123456 -e "DROP DATABASE IF EXISTS restaurantdb; CREATE DATABASE restaurantdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 9.3. Bootstrap lai

```cmd
composer bootstrap:booking
```

## 10. Staff bootstrap credential

### 10.1. Liet ke staff API keys hien co

```cmd
php artisan staff-auth:api-keys:list --json
```

### 10.2. Rotate bootstrap staff key

Dung khi ban mat key cu hoac muon cap key moi.

```cmd
php artisan booking:bootstrap-site --rotate-staff-key --json
```

Output se tra ve `plaintext_key` moi. Luu lai gia tri do.

### 10.3. Issue them 1 key staff/admin cu the

```cmd
php artisan staff-auth:api-keys:issue 2 "Local Dev Key" --json
```

### 10.4. Header staff khi goi API

```text
X-Staff-Key: <PLAINTEXT_STAFF_KEY>
```

## 11. Customer self-service bootstrap

Repo bootstrap site khong tao san customer test. Neu ban can test self-service, tao 1 customer local truoc.

### 11.1. Tao customer test local

```cmd
php artisan tinker --execute="\App\Models\User::query()->firstOrCreate(['username' => 'customer-local-01'], ['full_name' => 'Customer Local 01', 'email' => 'customer.local.01@example.test', 'phone' => '0900000010', 'role_id' => 3, 'current_tier_id' => null, 'language_pref' => 'vn', 'is_deleted' => false]);"
```

Lay `user_id` cua customer:

```cmd
php artisan tinker --execute="echo \App\Models\User::query()->where('username', 'customer-local-01')->value('user_id');"
```

### 11.2. Issue customer access session

Thay `<CUSTOMER_USER_ID>` bang gia tri vua lay duoc:

```cmd
php artisan customer-auth:access-sessions:issue <CUSTOMER_USER_ID> --json
```

### 11.3. Liet ke / inspect / revoke customer access session

```cmd
php artisan customer-auth:access-sessions:list --json
php artisan customer-auth:access-sessions:show <ACCESS_SESSION_ID> --json
php artisan customer-auth:access-sessions:revoke <ACCESS_SESSION_ID> --json
```

### 11.4. Header customer khi goi API

```text
X-Customer-Token: <PLAIN_TEXT_TOKEN>
```

## 12. Reporting va operational commands can nho

### 12.1. Rebuild reporting snapshots thu cong

```cmd
php artisan booking:reporting-snapshots:rebuild --json
```

Chi dung khi:

- vua bootstrap lai DB
- vua import/chinh data truc tiep
- muon ep reporting refresh ngay

### 12.2. Kiem tra outbox

```cmd
php artisan notifications:outbox-health --json
```

### 12.3. Release / deploy checks local

```cmd
php artisan booking:release-manifest --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:doctor --strict
```

## 13. Vi du goi API staff sau khi app da len

### 13.1. Xem table board

```cmd
curl.exe http://127.0.0.1:8000/api/v1/staff/tables/board -H "X-Staff-Key: <PLAINTEXT_STAFF_KEY>"
```

### 13.2. Xem reservation inbox

```cmd
curl.exe http://127.0.0.1:8000/api/v1/staff/reservations -H "X-Staff-Key: <PLAINTEXT_STAFF_KEY>"
```

### 13.3. Xem reporting daily sales

```cmd
curl.exe http://127.0.0.1:8000/api/v1/staff/reporting/daily-sales -H "X-Staff-Key: <PLAINTEXT_STAFF_KEY>"
```

## 14. Payment local va luu y rollout

Local cua ban co the dang dung:

- `PAYMENT_CUSTOMER_SELF_PAY_ENABLED=true`
- provider mac dinh `simulated`

Dieu nay chap nhan duoc cho local dev.

Neu ban muon local bam sat hardening default cua repo hon, sua `.env`:

```env
PAYMENT_CUSTOMER_SELF_PAY_ENABLED=false
PAYMENT_PROVIDER_DEFAULT=generic_http_hmac
CUSTOMER_DEPOSIT_PAYMENT_DEFAULT_PROVIDER=generic_http_hmac
CUSTOMER_BILL_PAYMENT_DEFAULT_PROVIDER=generic_http_hmac
```

Sau do:

```cmd
php artisan config:clear
```

## 15. Loi thuong gap va cach xu ly

### 15.1. `mysql` khong tim thay hoac MySQL connection refused

Nguyen nhan:

- `mysql.exe` khong nam trong `PATH`
- MySQL chua chay tren `DB_HOST:DB_PORT`
- `.env` dang tro sai `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, hoac `DB_PASSWORD`
- `mysqld.exe` trong `PATH` la MariaDB/XAMPP, khong phai MySQL Server 8 repo-local bootstrap

Cach xu ly:

- them MySQL vao `PATH`
- hoac set `MYSQL_BIN=` trong `.env`
- neu dung script repo-local, co the set them `MYSQLD_BIN=` trong `.env`
- neu chi co XAMPP/MariaDB, hay start database do nhu external service tren `DB_HOST:DB_PORT`; dung repo-local script can MySQL Server 8 `mysqld.exe`

Neu MySQL service cua may khong start duoc bang user hien tai, chay MySQL local cua repo:

```cmd
powershell -ExecutionPolicy Bypass -File scripts\ops\start-local-mysql.ps1 -Restart
```

Kiem tra TCP/credential bang dung port trong `.env`:

```cmd
mysql --protocol=tcp -h 127.0.0.1 -P 3306 -u root -p restaurantdb -e "SELECT 1;"
```

Neu dung Docker Compose testing fallback:

```cmd
docker compose -f docker-compose.testing.yml up -d mysql redis
```

Compose fallback doc `.env` va expose dung port theo `DB_PORT` / `REDIS_PORT`. Neu ban co chu y override port, sua `.env` truoc khi start:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Chay lai SQL-first bootstrap:

```cmd
composer bootstrap:booking
```

### 15.2. `Redis connection refused`

Nguyen nhan:

- Redis chua chay
- `.env` dang tro sai `REDIS_HOST` hoac `REDIS_PORT`

Cach xu ly:

```cmd
npm run runtime:up
```

Neu ban muon debug rieng Redis:

```cmd
powershell -ExecutionPolicy Bypass -File scripts\ops\start-local-redis.ps1 -Restart
redis-cli ping
```

Ket qua phai la:

```text
PONG
```

### 15.3. `scheduler` fail trong `booking:doctor`

Nguyen nhan:

- chua chay `php artisan schedule:work`
- vua moi mo scheduler, heartbeat chua kip cap nhat
- Redis dang down, nen heartbeat store khong doc duoc

Cach xu ly:

```cmd
npm run runtime:up
```

Neu van fail, chay lai gate:

```cmd
npm run runtime:preflight
php artisan booking:doctor --json
```

Neu thong diep noi ro `Blocked by runtime.redis failure`, do la blocker Redis/runtime chu khong phai drift rieng cua scheduler.

Neu can debug heartbeat rieng:

```cmd
php artisan schedule:work
php artisan booking:ops-heartbeat:touch scheduler --json
```

### 15.4. SQL bootstrap not applied

Trieu chung thuong gap:

- `notifications:outbox-health --json` bao `notification_outbox table is missing`
- `booking:deploy-check --mode=preflight` skip cac data/ops guard vi database runtime chua dung
- API health co DB reachable nhung business table khong ton tai

Cach xu ly dung contract cua repo:

```cmd
composer bootstrap:booking
php artisan booking:release-manifest --json
php artisan booking:doctor --json
php artisan booking:deploy-check --mode=preflight --strict --json
```

Khong dung `php artisan migrate` lam duong setup chinh cho local/staging/release. Bootstrap phai import `database/schema/mysql-schema.sql`, apply `database/patches/*.sql`, va chay `tools/mysql/verify_release_contract.sql`.

### 15.5. Vua sua `.env` nhung app khong nhan

```cmd
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 15.6. App len duoc nhung flow business bi ho

Thuong la do thieu 1 trong 2 process nay:

- `redis-server`
- `php artisan schedule:work`
- hoac MySQL dang down nen `notifications:outbox-health`/`runtime.outbox` chi bao duoc dependency blocker

Cach nhanh nhat de dua local runtime ve dung lane:

```cmd
npm run runtime:up
```

## 16. Checklist nhanh de mo project moi ngay

Mo 1 terminal trong VSCode:

```cmd
npm run runtime:up
```

Neu can dong runtime:

```cmd
npm run runtime:down
```

Kiem tra:

```cmd
npm run runtime:preflight
php artisan booking:doctor --json
```

Neu can frontend:

```cmd
npm run dev
```

## 17. Checklist nhanh de setup lai tu dau

```cmd
copy .env.example .env
composer install
npm install
php artisan key:generate --ansi --force
composer bootstrap:booking
npm run runtime:up
npm run runtime:preflight
php artisan booking:doctor --json
```
