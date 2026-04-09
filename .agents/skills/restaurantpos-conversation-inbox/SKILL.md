---
name: restaurantpos-conversation-inbox
description: Harden the RestaurantPOS staff conversation inbox, including triage list and detail reads, assignment and take-over safety, reservation or waiting-list linking, internal notes, capability gates, and rollout flags. Use when Codex touches staff conversation routes, conversation workflow services, inbox docs, or tests that protect idempotent write paths and branch-consistent links.
---

# RestaurantPOS Conversation Inbox

Read `AGENTS.md`, `.codex/AGENTS.md`, `docs/runbooks/staff-conversation-inbox.md`, and `references/paths.md` before patching.

## Workflow

1. Classify the change as list or detail read, assignment flow, link or unlink flow, or internal-note write path.
2. Keep inbox orchestration in `StaffConversationInboxService` and `StaffConversationWorkflowService`, not in the controller.
3. Preserve the active-assignment uniqueness invariant and take-over semantics.
4. Enforce `conversation.manage`, feature flag checks, and idempotency on every write route.
5. If the change alters operator behavior or rollout boundaries, update the runbook in the same batch.

## Guardrails

- This module is an internal operational inbox, not a customer chat product.
- Do not add outbound reply behavior implicitly while working on internal notes or assignment flows.
- Keep reservation and waiting-list links branch-consistent with the conversation.
- Internal notes must remain agent-authored and clearly marked as internal-only.

## Verify

- `php artisan test tests/Feature/Staff/StaffConversationInboxFlowTest.php`
- Add `restaurantpos-feature-flag-rollout` when branch-level rollout or kill-switch behavior moves
