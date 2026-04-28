# Order Settlement Workflow Containment

## Current responsibility boundary

`OrderSettlementWorkflow` is the production orchestrator for staff checkout and refund flows. It coordinates bill preview, bill locking, payment capture, settlement finalization, refund allocation, cancel-after-payment handling, voucher/loyalty side effects, table release, notifications, audit, branch authorization, row-version checks, idempotency replay, and realtime events.

Because it owns cross-module money and service-state invariants, this workflow must remain behaviorally stable through P0/P1 and go-live hardening. Extraction is post-go-live unless the product owner explicitly approves a higher-risk refactor.

## Safe future extraction seams

- Bill snapshot and discount calculation can be isolated behind the existing reservation financial sync and settlement amount calculator contracts.
- Payment capture idempotency can be isolated around `PaymentCaptureService` once replay mismatch and double-submit tests cover both `checkout` and `payOrder`.
- Refund planning/execution can remain behind `RefundExecutionService`, with cancel-after-payment as a separate orchestration adapter only after refund allocation and over-refund tests are stable.
- Settlement finalization can stay in `SettlementFinalizerService`, with table release and loyalty/voucher settlement as explicit collaborators.
- Realtime and notification publishing can be moved behind event publishers after the persistence transaction side effects are characterized.

## Required characterization before extraction

Before changing production workflow structure, keep or expand tests covering:

- Preview totals without bill locking.
- Bill locking and reservation bill snapshot persistence.
- Payment capture, final payment idempotency, and finalization.
- Reservation completion, order completion, checked-out timestamp, and table release.
- Refund preview, refund execution, refund currency, over-refund denial, and refund idempotency.
- Cancel-after-payment, cancellation fields, active order cancellation behavior, and final table state.
- Branch authorization, open cashier shift enforcement, and stale row-version failures.

## Current posture

The workflow is intentionally contained rather than decomposed during this hardening pass. New tests should characterize behavior first. Production refactors should only follow after the above coverage is green in the release gate and after product-owner approval for post-P0/P1 or post-go-live extraction work.
