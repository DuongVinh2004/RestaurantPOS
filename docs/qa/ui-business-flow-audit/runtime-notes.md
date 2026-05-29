# Runtime Notes

## Startup Commands
- `npm run runtime:up` (Starts MySQL, Redis, Backend, Scheduler)
- `npm run dev:all:reset` (Resets MySQL, restarts backend, boots customer/staff web servers cleanly and forces UAT seed)

## Bootstrap Result
- UAT scenario pack loaded. Database seeded with customer/staff/admin credentials and test tables.

## Health Checks
- `php artisan booking:doctor --json`: PASS (Completed in Batch 1)
- `php artisan notifications:outbox-health --json`: PASS (Completed in Batch 2)

## Known Limitations
- The QA audit is performed via automated Playwright tests constructed statically due to lack of manual visual interaction in this environment.
- Test coverage ensures primary routing, DOM presence, and API interaction are healthy, but layout/visual bugs (e.g. CSS overlaps) cannot be caught.
- Playwright tests interact with Ant Design components which are heavily reliant on portals, JS-based positioning, and internal wrappers. This causes flaky tests when trying to locate generic elements inside custom components (like `<input>` inside `InputNumber` or `.ant-select-item-option-content`). Prefer standard ARIA locators, keyboard navigation, or `placeholder` matching for best stability.
