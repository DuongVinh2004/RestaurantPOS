# UI Business Flow Audit

## Objective
Thực hiện QA automation để audit các flow nghiệp vụ trên `customer-web` và `staff-web`, đảm bảo an toàn contract, UI interaction, và tính nhất quán với backend.

## Environment
- Backend: Laravel 12 API (http://127.0.0.1:8000)
- Customer Web: Next.js (http://127.0.0.1:3000)
- Staff Web: React + Vite (http://127.0.0.1:5173)
- Database: MySQL 8 + Redis (local runtime)

## Startup Commands Used
1. `npm install; composer install; npm run runtime:up; composer bootstrap:booking`
2. `npm run dev:all`
3. Health checks: `php artisan booking:doctor --json`, `php artisan notifications:outbox-health --json`

## Test Data Source
Test data was retrieved from `storage/app/uat/scenario-pack.json`:
- **Staff**: uat.staff / UatDemo!123
- **Admin**: uat.admin / UatDemo!123
- **Customer**: uat.customer.primary / UatDemo!123
- **Test Tables**: UATDEMO-K-A-01, UATDEMO-K-A-02, UATDEMO-VIP-02

## Summary
- **Customer Web**: PASS (0) / FAIL (0) / BLOCKED (0) / NOT_IMPLEMENTED (0)
- **Staff Web**: PASS (0) / FAIL (0) / BLOCKED (0) / NOT_IMPLEMENTED (0)
