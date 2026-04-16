# Giant Service Decomposition Plan

This plan is intentionally incremental. Do not split these services in one
batch. Use it when a future change already touches the relevant behavior.

## StaffCheckoutService

Current clusters:
- settlement preview, bill lock, and finalize paths
- refund preview, allocation, and execution
- cashier shift state and settlement reconciliation
- payment integrity, idempotency, and row-version guards
- invoice and financial sync side effects

Target extraction points:
- `Application/Actions/*Settlement*Action`
- `Application/Actions/*Refund*Action`
- `Application/Policies/*Payment*Policy`
- `Application/Queries/*Settlement*Query`
- `Domain/ValueObjects` for totals, allocation plans, and payment summaries

## StaffConversationWorkflowService

Current clusters:
- assignment, takeover, close, and workflow state transitions
- outbound reply preparation and notification handoff
- reservation or waiting-list linking
- branch and actor capability checks
- conversation event/audit recording

Target extraction points:
- `Application/Actions/*Assignment*Action`
- `Application/Actions/*Reply*Action`
- `Domain/State/*WorkflowStateMachine`
- `Domain/Policies/*Conversation*Policy`
- `Application/Queries/*ConversationContext*Query`

## StaffTableBoardService

Current clusters:
- branch-scoped board reads
- table occupancy and service-session projection
- reservation and waiting-list context joins
- availability, hold, and conflict state
- stale-write and row-version guard helpers

Target extraction points:
- `Application/Queries/*TableBoard*Query`
- `Application/Queries/*ServiceSession*Query`
- `Domain/Policies/*TableBoard*Policy`
- `Application/Support/*BoardProjectionBuilder`
- `Domain/ValueObjects/*BoardSnapshot`

## NotificationOutboxService

Current clusters:
- enqueue and dedupe decisions
- quiet-hour and preference suppression
- channel driver selection
- delivery attempt recording and retry state
- health and dead-letter reporting

Target extraction points:
- `Application/Actions/*Enqueue*Action`
- `Application/Actions/*DeliveryAttempt*Action`
- `Domain/Policies/*NotificationPreference*Policy`
- `Infrastructure/*Channel*Resolver`
- `Application/Queries/*OutboxHealth*Query`

## Rule For Future Touches

Each future extraction should be small enough to review in isolation, keep public
method behavior stable, and preserve existing idempotency, transaction, locking,
authorization, and audit guarantees.
