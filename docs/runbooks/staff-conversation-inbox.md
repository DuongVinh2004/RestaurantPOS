# Staff Conversation Inbox

## Scope

The current module is an internal operational inbox foundation for restaurant staff. It is designed for triage, assignment, linkage to reservation and waiting-list records, internal follow-up notes, timeline review, and a minimal queue-backed outbound reply path when runtime delivery support is truly available.

It is not a customer chat product and it does not provide omnichannel live delivery.

## Reused schema

The inbox reuses the existing conversation domain tables:

- `conversations`
- `conversation_messages`
- `conversation_files`
- `conversation_events`
- `conversation_analyses`
- `agent_assignments`
- `message_entities`
- `conversation_aggregates`

Important existing fields now used by staff flow:

- `agent_assignments.is_active` and the unique active assignment invariant on `active_conversation_id`
- `agent_assignments.notes`
- `conversation_messages.related_reservation_id`
- `conversation_files.file_url`
- `message_entities.entity_type`, `entity_normalized`
- `conversation_events.event_type`, `event_data`
- `conversation_analyses.analyzer_name`, `quality_score`, `extracted_info`

## Added schema usage

This batch adds and uses a small amount of schema so the existing domain becomes operationally usable:

- `conversations.branch_id`
- `conversations.linked_reservation_id`
- `conversations.linked_waiting_list_id`
- `conversations.workflow_state`
- `conversations.workflow_state_reason`
- `conversations.workflow_state_changed_at`
- `conversations.first_triaged_at`
- `conversations.resolved_at`
- `conversation_messages.is_internal_note`

## Staff API

All routes are under `/api/v1/staff/conversations` and require `staff.capability:conversation.manage`.

- `GET /api/v1/staff/conversations`
- `GET /api/v1/staff/conversations/{conversation_id}`
- `POST /api/v1/staff/conversations/{conversation_id}/assign`
- `POST /api/v1/staff/conversations/{conversation_id}/take-over`
- `POST /api/v1/staff/conversations/{conversation_id}/unassign`
- `POST /api/v1/staff/conversations/{conversation_id}/workflow-state`
- `POST /api/v1/staff/conversations/{conversation_id}/links`
- `DELETE /api/v1/staff/conversations/{conversation_id}/links/reservation`
- `DELETE /api/v1/staff/conversations/{conversation_id}/links/waiting-list`
- `POST /api/v1/staff/conversations/{conversation_id}/internal-notes`
- `POST /api/v1/staff/conversations/{conversation_id}/outbound-replies`

Runtime note for staff-web and other consumers:

- Conversation detail responses expose `data.capabilities` and nested `data.capabilities.outbound_reply`.
- Consumers must use that detail envelope to decide whether outbound reply is operationally enabled.
- Session capability alone is not enough to infer reply availability because assignment state, runtime delivery support, and recipient readiness can still lock the action.
- Conversation detail now also exposes `data.ai_assist` as an optional, bounded assist envelope for summary + follow-up hints.
- The current `staff-web` shell opens linked queue items through `/waiting-list?focus={waiting_id}` so the operator lands on the intended waiting-list row instead of the default first result.
- `data.ai_assist.status` can be `ready`, `disabled`, or `unavailable`.
- `data.ai_assist` must never block the canonical thread timeline. When the assist lane is off or lacks stable context, consumers still render detail normally and use the fallback message.

Supported list filters:

- `status`
- `workflow_state`
- `inbox_view`
- `channel`
- `assigned_agent_user_id`
- `assignment_state`
- `branch_id`
- `reservation_id`
- `waiting_list_id`
- `user_id`
- `created_from`
- `created_to`
- `q`

Supported inbox views:

- `unassigned`
- `overdue`
- `waiting_on_customer`
- `resolved_today`

## Workflow contract

Staff inbox workflow is now explicit and separate from the coarse conversation status field:

- `Open`
- `Triaged`
- `Assigned`
- `PendingCustomer`
- `Resolved`
- `Closed`

Coarse status compatibility remains:

- `PendingCustomer` maps the coarse `status` to `Pending`
- `Closed` maps the coarse `status` to `Closed`
- other workflow states keep the coarse `status` at `Open`

Current guarded transition rules:

- `assign` and `take-over` move the workflow to `Assigned`
- `unassign` moves the workflow back to `Triaged`
- `workflow-state` route supports `Triaged`, `PendingCustomer`, `Resolved`, and `Closed`
- `Assigned` is not accepted through `workflow-state`; operators must use `assign` or `take-over`
- `Resolved` and `Closed` conversations must be reopened to `Triaged` before reassignment
- closing a conversation blocks further link edits and internal notes

Transition metadata exposed in summary/detail envelopes:

- `data.conversation.workflow.state`
- `data.conversation.workflow.state_reason`
- `data.conversation.workflow.state_changed_at`
- `data.conversation.workflow.first_triaged_at`
- `data.conversation.workflow.resolved_at`
- `data.conversation.workflow.closed_at`
- `data.conversation.workflow.allowed_actions`
- `data.conversation.operational.is_overdue`
- `data.conversation.operational.queue_bucket`

`workflow-state` request payload:

```json
{
  "workflow_state": "Resolved",
  "expected_workflow_state": "Assigned",
  "reason": "Customer confirmed the issue is resolved."
}
```

Operational notes:

- `expected_workflow_state` is optional but recommended for stale-action safety from staff-web or operator tooling.
- `Resolved` and `Closed` transitions release any active assignment so closed-loop queue ownership stays explicit.
- `workflow.state_changed` events provide the canonical timeline record for resolve, reopen, close, and waiting-on-customer moves.

## Operational Proof

The inbox now participates in the shared operational snapshot and deploy preflight:

```bash
php artisan booking:ops-snapshot --json
php artisan booking:deploy-check --mode=preflight --strict
```

Conversation inbox health exposes:

- `conversation_inbox.active_conversation_count`
- `conversation_inbox.unassigned_count`
- `conversation_inbox.overdue_count`
- `conversation_inbox.waiting_on_customer_count`
- `conversation_inbox.resolved_today_count`
- `conversation_inbox.terminal_with_active_assignment_count`
- `conversation_inbox.oldest_overdue_age_seconds`
- `conversation_inbox.workflow_state_counts`
- `conversation_inbox.overdue_examples[]`

Interpretation:

- `status=fail`
  - a terminal conversation still has an active assignment, which means assignment release drift already exists
- `status=degraded`
  - unassigned or overdue backlog crossed the configured warning threshold, but workflow invariants still hold

Expected operator action:

- if `terminal_with_active_assignment_count > 0`, reopen and normalize ownership before using inbox metrics as reliable support evidence
- if the queue is only degraded, review `overdue_examples[]`, rebalance staffing or triage, and record whether the backlog is expected
- use the existing inbox views (`unassigned`, `overdue`, `waiting_on_customer`, `resolved_today`) to resolve the flagged rows, then re-run the ops snapshot

## Demo seed

Use the dev seed to get usable sample data:

```bash
php artisan db:seed --class=Database\\Seeders\\DevConversationSeeder
```

The seed creates:

- one reservation-linked open conversation
- one waiting-list-linked pending conversation
- one closed conversation
- messages, an internal note, files, entities, analyses, events, and an active assignment

## Contract artifact

The OpenAPI artifact is exported to:

- `storage/app/booking_release/openapi-v1.json`

Regenerate it with:

```bash
php artisan booking:api-contract --write
php artisan booking:api-artifacts:generate
```

## Optional AI assist

This batch adds a strictly optional read-assist layer for conversation detail:

- feature flag: `staff.conversation_ai_assist`
- provider: `local_heuristic`
- model identifier: `conversation-summary-v1`
- output shape: `data.ai_assist.summary`, `suggested_actions`, `risk_flags`, fallback reason, and source counts

Current operational constraints:

- no external model call and no extra queue dependency in this phase
- zero incremental model cost (`cost_tier=zero`)
- synchronous latency budget target: `150ms`
- canonical timeline remains the source of truth for decisions

Fallback contract:

- `status=disabled`
  - rollout is off for the current environment/branch
- `status=unavailable`
  - the thread does not yet have enough stable visible context for the assist lane
- both fallback states still return the normal detail payload and must not block take-over, notes, or outbound reply

Runtime smoke note:

- `staff-web/scripts/live-smoke.mjs` now records a non-blocking `conversation ai assist` step after conversation detail read
- disabled or unavailable assist is acceptable evidence as long as the detail contract remains readable

## Current limits

- Outbound reply is currently limited to queueing real email delivery when the linked customer record has an email address and the runtime email channel is enabled in `real` mode.
- Outbound reply quiet-hour checks now prefer the linked conversation branch timezone when that branch is available.
- Assignment/state conflicts can still return `409 conflict`, while missing idempotency key or validation failures return `422`.
- Resolved/closed workflow is explicit, but inbox SLA thresholds remain intentionally lightweight. `overdue` currently means no non-terminal activity for 15 minutes in UTC terms.
- Channel-native web chat, SMS, Zalo, delivery receipts, and customer-thread synchronization are still out of scope.
- Full staff-branch membership is still not modeled.
- Inbox-adjacent branch reads currently use an operational scope only: default branch plus branches where the authenticated staff actor has an open cashier shift.
- Conversation data and link consistency still enforce branch coherence inside the conversation graph, but they are not a replacement for a future explicit staff-branch membership model.
- Internal notes remain supported. External staff replies are limited to the guarded outbound email foundation above.
- Real-time inbox sync is out of scope for this batch.
- AI assist currently stays heuristic and recommendation-only. It does not perform autonomous mutations.
