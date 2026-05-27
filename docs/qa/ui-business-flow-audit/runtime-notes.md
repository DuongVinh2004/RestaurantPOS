# Runtime Notes

## Startup Commands
- `npm run runtime:up` (Starts MySQL, Redis, Backend, Scheduler)
- `composer bootstrap:booking` (Bootstraps database)
- `npm run dev:all` (Starts frontend clients)

## Bootstrap Result
- UAT scenario pack loaded. Database seeded with customer/staff/admin credentials and test tables.

## Health Checks
- `php artisan booking:doctor --json`: TBD
- `php artisan notifications:outbox-health --json`: TBD

## Known Limitations
- The QA audit is performed via automated Playwright tests constructed statically due to lack of manual visual interaction in this environment.
- Test coverage ensures primary routing, DOM presence, and API interaction are healthy, but layout/visual bugs (e.g. CSS overlaps) cannot be caught.
