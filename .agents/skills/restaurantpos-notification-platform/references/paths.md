# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/runbooks/notification-platform-v2.md`
- `docs/audit-trail.md`

## Code hotspots

- `app/Services/NotificationOutboxService.php`
- `app/Services/NotificationOutboxHealthService.php`
- `app/Services/Notifications/NotificationChannelManager.php`
- `app/Services/Notifications/NotificationPreferenceService.php`
- `app/Services/Notifications/Drivers/EmailNotificationChannelDriver.php`
- `app/Services/Notifications/Drivers/SmsStubNotificationChannelDriver.php`
- `app/Services/Notifications/Drivers/ZaloStubNotificationChannelDriver.php`

## Test surface

- `tests/Feature/Notifications/NotificationOutboxServiceSmokeTest.php`
- `tests/Feature/Services/NotificationOutboxServiceRetryTest.php`
- `tests/Feature/Console/NotificationOpsCommandsTest.php`
- `tests/Unit/Services/NotificationOutboxHealthServiceTest.php`
- `tests/Unit/Services/NotificationOutboxServiceIdempotencyKeyTest.php`

## Questions to answer before patching

- Is the change about enqueue, delivery, preference suppression, or ops visibility?
- Does the channel remain real, stub, or disabled after the change?
- Which command or health snapshot should prove the behavior?
- Does audit or outbox evidence need to change with the delivery flow?
