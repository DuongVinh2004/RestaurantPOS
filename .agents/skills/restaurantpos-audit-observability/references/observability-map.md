# Observability Map

## Docs

- `docs/audit-trail.md`
- `docs/runbooks/notification-platform-v2.md`

## Code hotspots

- `app/Http/Middleware/AuditRequestMiddleware.php`
- `app/Http/Controllers/Api/Staff/StaffAuditTrailController.php`
- `app/Http/Controllers/Api/Staff/StaffOperationalRealtimeController.php`
- `app/Services/AuditTrailQueryService.php`
- `app/Services/NotificationOutboxService.php`
- `app/Services/NotificationOutboxHealthService.php`
- `app/Services/MetricsService.php`
- `app/Services/OperationalAlertService.php`
- `app/Services/OperationalInsightsService.php`
- `app/Services/Staff/StaffOperationalRealtimeService.php`
- `app/Support/AuditTrail/*`

## Test surface

- `tests/Feature/Notifications/NotificationOutboxServiceSmokeTest.php`
- `tests/Feature/Services/NotificationOutboxServiceRetryTest.php`
- `tests/Feature/Staff/StaffAuditTrailHttpFlowTest.php`
- `tests/Feature/Staff/StaffOperationalRealtimeFlowTest.php`
- `tests/Unit/Services/NotificationOutboxHealthServiceTest.php`
- `tests/Unit/Services/OperationalAlertServiceTest.php`
- `tests/Unit/Services/OperationalInsightsServiceTest.php`
- `tests/Unit/Support/OperationalHealthEvaluatorTest.php`
