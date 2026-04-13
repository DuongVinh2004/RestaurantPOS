# Notification reliability smoke

This round adds smoke coverage for background reliability flows:

- `notifications:process-outbox`
- `notifications:enqueue-reminders`
- `notifications:outbox-health`
- `notifications:outbox-dead-letter`
- `waiting-list:expire-notified`
- `NotificationOutboxService` send / retry / dedupe / preference behavior
- non-retryable channel failures cancel immediately instead of looping through retry backlog
- delivery-time preference and quiet-hour rechecks record explicit suppression/defer evidence
- `NotificationOutboxHealthService` degraded-state detection

Suggested local run order:

1. `php artisan booking:doctor --strict`
2. `php artisan notifications:outbox-health --json`
3. `php artisan notifications:outbox-dead-letter --json`
4. `vendor/bin/phpunit --group booking-smoke --filter NotificationOutboxServiceSmokeTest`
5. `vendor/bin/phpunit --group booking-ops --filter NotificationOutboxHealthServiceTest`
6. `vendor/bin/phpunit --group booking-ops --filter NotificationOpsCommandsTest`
