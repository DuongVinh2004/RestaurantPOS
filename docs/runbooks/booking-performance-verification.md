# Booking Performance Verification

## Purpose

This pack adds a canonical performance and resilience verification layer on top of the existing local query-budget guard in [tests/Feature/Performance/HotPathPerformanceBudgetTest.php](/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Performance/HotPathPerformanceBudgetTest.php).

Use it to answer a different question:

- Can the current build survive realistic staging traffic, bursts, soak, and key retry/fault windows?

The canonical source of truth is:

- [config/booking_performance_verification_matrix.json](/Users/Duong%20Vinh/RestaurantPOS-Laravel/config/booking_performance_verification_matrix.json)

The canonical command is:

```powershell
php artisan booking:performance-verify --profile=staging --run --base-url=https://staging.example.com --manifest-path=storage/app/uat/scenario-pack.json --promote-baseline
```

## Profiles

- `local`
  - Short sanity profile for developer regression checks.
  - Not sufficient as rollout evidence.
- `staging`
  - Serious staging verification profile.
  - Used for limited rollout evidence and baseline capture.

## Prerequisites

- Bootstrap or refresh the canonical UAT pack first:

```powershell
php artisan booking:uat-pack:bootstrap --base-url=https://staging.example.com
```

- `node` 18+ must be available when `--run` is used.
- `PAYMENT_PROVIDER_SIMULATED_WEBHOOK_SECRET` must be set for webhook scenarios.
- For repeatable staging evidence, re-bootstrap the UAT pack before each serious run because webhook scenarios intentionally mutate seeded payment state.

## Commands

Local sanity:

```powershell
php artisan booking:performance-verify --profile=local --run --base-url=http://127.0.0.1:8000 --manifest-path=storage/app/uat/scenario-pack.json --json
```

Serious staging run:

```powershell
php artisan booking:performance-verify --profile=staging --run --base-url=https://staging.example.com --manifest-path=storage/app/uat/scenario-pack.json --promote-baseline
```

Evaluate existing raw artifacts without rerunning traffic:

```powershell
php artisan booking:performance-verify --profile=staging --ingest-dir=storage/app/booking_release/performance_verification/raw/staging-20260405t100000z --baseline=storage/app/booking_release/performance_verification/baselines/latest-staging.json
```

Run only a subset:

```powershell
php artisan booking:performance-verify --profile=staging --run --base-url=https://staging.example.com --scenario=availability_read_load --scenario=reservation_create_race --scenario=webhook_duplicate_storm
```

## Canonical Matrix

| Scenario | Type | Automation | Blocking | Staging-only | Flow / endpoints |
| --- | --- | --- | --- | --- | --- |
| `availability_read_load` | `load` | automated | yes | no | `GET /api/v1/tables/available` |
| `reservation_show_load` | `load` | automated | yes | no | `GET /api/v1/reservations/{id}` |
| `waiting_list_queue_load` | `load` | automated | yes | no | `GET /api/v1/staff/waiting-list`, `GET /api/v1/staff/waiting-list/changes` |
| `staff_board_timeline_load` | `load` | automated | yes | no | `GET /api/v1/staff/tables/board`, `GET /api/v1/staff/reservations/timeline` |
| `checkout_preview_load` | `load` | automated | yes | no | `active-order`, `bill-preview`, `settlement-preview`, `refund-preview` |
| `reservation_create_race` | `burst` | automated | yes | no | `POST /api/v1/table-holds`, `POST /api/v1/reservations`, cleanup `PATCH /api/v1/reservations/{id}/status` |
| `payment_webhook_burst` | `burst` | automated | yes | no | `POST /api/v1/payments/providers/{provider_code}/webhooks` |
| `mixed_service_day_soak` | `soak` | automated | yes | no | mixed availability, reservation, waiting-list, board, timeline, checkout, webhook traffic |
| `webhook_duplicate_storm` | `fault` | automated | yes | no | duplicate webhook delivery storm on the canonical webhook endpoint |
| `waiting_list_advance_operator_drill` | `workflow` | operator-assisted | warn | yes | `POST /api/v1/staff/waiting-list/{id}/advance` after real decline/expiry |
| `redis_degradation_fault_probe` | `fault` | operator-assisted | warn | yes | run read probes during Redis delay/outage window |
| `mysql_lock_contention_fault_probe` | `fault` | operator-assisted | warn | yes | run reservation race / checkout probes during DB contention |
| `notification_dispatch_lag_fault_probe` | `fault` | operator-assisted | warn | yes | capture health while notification workers are slowed or paused |
| `queue_backlog_fault_probe` | `fault` | operator-assisted | warn | yes | capture soak + health while backlog accumulates and drains |

## Metrics Collected

- operation latency: `p50`, `p95`, `p99`, `mean`, `max`
- operation throughput per second
- request count
- HTTP status distribution
- unexpected error rate
- accepted response rate
- controlled conflict rate
- duplicate rate
- webhook delivery status counts: `Applied`, `Ignored`, `Failed`
- cleanup error count for mutation scenarios

## Artifacts

Root:

- `storage/app/booking_release/performance_verification`

Written on each run:

- `reports/performance-verification-<profile>-<timestamp>.json`
- `reports/performance-verification-<profile>-<timestamp>.md`
- `reports/latest-<profile>.json`
- `reports/latest-<profile>.md`

Optional baseline artifacts:

- `baselines/performance-baseline-<profile>-<timestamp>.json`
- `baselines/latest-<profile>.json`

Raw live-run artifacts:

- `raw/<profile>-<timestamp>/*.json`

For limited-production readiness, archive the candidate-specific report and record it under `performance_verification_report` in the launch-readiness manual evidence JSON. The launch-readiness gate does not treat local query-budget tests as a substitute for this candidate-level artifact.

## Exit Codes

- `0`
  - all selected automated scenarios passed and there are no major warnings.
- `2`
  - no blocking failures, but there are major warnings.
  - most common case: required operator-assisted staging probes were not captured yet.
- `1`
  - blocking threshold failure, missing automated evidence, or runner/evaluation failure.

## Reading The Report

Check these sections first:

- `groups`
  - high-level pass/warn/fail by verification area.
- `scenarios`
  - exact scenario status, thresholds, and baseline deltas.
- `blocking_failures`
  - hard rollout blockers.
- `major_warnings`
  - degraded or still-manual evidence.
- `top_bottlenecks`
  - scenarios closest to or beyond threshold.

Common interpretation rules:

- `availability_read_load` failing means customer-facing table discovery is not rollout-safe.
- `reservation_create_race` failing means lock/contention behavior is unsafe even if read paths stay green.
- `webhook_duplicate_storm` failing means provider retry storms can still break idempotent settlement behavior.
- `pass_with_warnings` on staging usually means operator-assisted fault drills still need evidence capture.

## Operator-Assisted Staging Fault Windows

These are intentionally staging-only. The command does not inject infra faults by itself.

Redis degraded window:

```powershell
php artisan booking:performance-verify --profile=staging --run --base-url=https://staging.example.com --scenario=availability_read_load --scenario=staff_board_timeline_load --scenario=waiting_list_queue_load
```

Run that while Redis latency or unavailability is injected by infra, then record the resulting report.

MySQL contention window:

```powershell
php artisan booking:performance-verify --profile=staging --run --base-url=https://staging.example.com --scenario=reservation_create_race --scenario=checkout_preview_load
```

Run that while lock contention or slow-query pressure is injected on staging DB infrastructure.

Notification lag / queue backlog window:

```powershell
php artisan booking:performance-verify --profile=staging --run --base-url=https://staging.example.com --scenario=mixed_service_day_soak
php artisan notifications:outbox-health --json
php artisan booking:alert-check --json
php artisan booking:doctor --json
```

Pause or throttle workers first, let backlog build, then capture both the performance report and the health snapshots before and after recovery.

Waiting-list advance operator drill:

- Use the canonical waiting-list lifecycle from [scripts/uat/Invoke-UatScenario.ps1](/Users/Duong%20Vinh/RestaurantPOS-Laravel/scripts/uat/Invoke-UatScenario.ps1).
- Create a real declined or expired source invite.
- Execute `POST /api/v1/staff/waiting-list/{id}/advance`.
- Store the resulting evidence next to the staging report.

## Current Manual Gaps

- `waiting_list_advance_operator_drill`
- `redis_degradation_fault_probe`
- `mysql_lock_contention_fault_probe`
- `notification_dispatch_lag_fault_probe`
- `queue_backlog_fault_probe`

These remain explicit warnings on staging until evidence is captured.
