# Kitchen KDS Lifecycle

This runbook documents the canonical kitchen ticket lifecycle for the production-lean KDS slice.

## Lifecycle Contract

- `Queued`
  - Created by `POST /api/v1/staff/orders/{order_id}/kitchen/dispatch`
  - Allowed action: `fire`
  - State reason: `awaiting_fire`
- `Fired`
  - Entered by `POST /api/v1/staff/kitchen/tickets/{ticket_id}/fire`
  - Allowed action: `bump`
  - State reason: `in_preparation`
- `Ready`
  - Entered by `POST /api/v1/staff/kitchen/tickets/{ticket_id}/bump`
  - Allowed action: `recall`
  - State reason: `awaiting_service_completion`
- `Completed`
  - Entered when the linked order item becomes `Served`
  - Terminal state
  - State reason: `order_item_served`
- `Cancelled`
  - Entered when the linked order item becomes `Cancelled`
  - Terminal state
  - State reason: `order_item_cancelled`

Invalid transitions are rejected. Important guards:

- queued tickets cannot be bumped or recalled
- fired tickets cannot be fired again or recalled directly
- ready tickets cannot be fired again
- terminal tickets cannot be recalled
- ticket actions are blocked when the linked order item is already in a terminal state that no longer matches the requested KDS action

## Redispatch Rules

- Dispatch remains idempotent per `order_item_id`
- Repeat dispatch reuses the existing non-terminal ticket instead of creating duplicates
- Repeat dispatch is blocked when the active ticket has drifted routing or order-item state
- Route removal or route deactivation is still blocked while active tickets exist through the admin routing flow

## Ticket Read Contract

`KitchenOrderItemTicketResource` now exposes two operational sections:

- `lifecycle`
  - `status`
  - `state_reason`
  - `is_terminal`
  - `allowed_actions`
- `reconciliation`
  - `sync_status`
  - `routing_status`
  - `order_item_expected_status`
  - `order_item_matches_ticket`
  - `station_active`
  - `drift_reasons`
  - `next_actions`

These fields are additive and are intended for KDS clients, QA, and drift diagnosis. Existing top-level ticket fields remain unchanged.

## Reconciliation Path

Use the reconciliation service when kitchen state looks inconsistent:

```php
app(App\Services\Kitchen\KitchenTicketReconciliationService::class)->scan([
    'include_terminal' => true,
]);
```

The report returns:

- `checked_count`
- `drift_count`
- `status_drift_count`
- `routing_drift_count`
- `tickets[]` with `sync_status`, `routing_status`, `drift_reasons`, and `next_actions`

Common corrective actions:

- `reload_order_item_state`
- `review_station_routes`
- `run_kitchen_reconciliation`

## Operational Proof

Use the shared ops snapshot and deploy preflight when kitchen readiness matters for staging or operator handoff:

```powershell
php artisan booking:ops-snapshot --json
php artisan booking:deploy-check --mode=preflight --strict
```

Kitchen/KDS health now surfaces:

- `kitchen_kds.active_ticket_count`
- `kitchen_kds.drift_count`
- `kitchen_kds.status_drift_count`
- `kitchen_kds.routing_drift_count`
- `kitchen_kds.oldest_fired_age_seconds`
- `kitchen_kds.oldest_ready_age_seconds`
- `kitchen_kds.drift_examples[]`
- `kitchen_kds.backlog_examples[]`

Interpretation:

- `status=fail`
  - ticket or routing drift already exists; do not trust the board until reconciliation completes
- `status=degraded`
  - live backlog is aging past the configured warning window; staffing, dispatch flow, or service cadence needs review

Expected operator action:

- open `booking:ops-snapshot --json` first to see whether the signal is drift or stale backlog
- if drift exists, run the reconciliation service output above and correct order-item state or station routing before release evidence is collected
- if only backlog is stale, confirm kitchen staffing/throughput and capture the reason before rollout proceeds

## Verification

Run the kitchen-focused regression slice after lifecycle or routing changes:

```powershell
php artisan test tests/Feature/Staff/StaffKitchenDispatchFoundationFlowTest.php tests/Feature/Admin/AdminKitchenRoutingFoundationHttpFlowTest.php
```
