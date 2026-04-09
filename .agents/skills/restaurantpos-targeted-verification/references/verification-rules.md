# Verification Rules

Use this file when the script output needs manual adjustment.

## Base ladder

1. Targeted `php artisan test ...`
2. `vendor/bin/pint --test`
3. `vendor/bin/phpstan analyse`
4. `php artisan test`
5. `php artisan booking:doctor --json`
6. `php artisan booking:deploy-check --mode=preflight`
7. `php artisan booking:release-manifest --json`

## Escalate when any of these are true

- `database/schema/mysql-schema.sql`, `database/patches/*`, `db_all.sql`, or `tools/mysql/*` changed
- `routes/api.php`, `app/Http/Requests/*`, or `app/Http/Resources/*` changed
- runtime services changed: booking doctor, deploy safety, outbox, ops heartbeat, reporting snapshots, payment webhooks
- privacy export, anonymization, retention, conversation inbox, notification platform, branch scheduling, or multi-branch reporting services changed
- feature flags, audit logging, metrics, realtime feeds, or hot-path read services changed
- shared files changed

## Runtime-sensitive reminders

- `phpunit.xml` uses SQLite in-memory by default
- Booking runtime checks need MySQL-like bootstrap, Redis, and scheduler heartbeat for real confidence
- Use `restaurantpos-runtime-smoke` when live runtime proof matters
