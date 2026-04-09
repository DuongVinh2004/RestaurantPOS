# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/runbooks/staff-conversation-inbox.md`

## Code hotspots

- `app/Http/Controllers/Api/Staff/StaffConversationInboxController.php`
- `app/Services/Staff/StaffConversationInboxService.php`
- `app/Services/Staff/StaffConversationWorkflowService.php`

## Test surface

- `tests/Feature/Staff/StaffConversationInboxFlowTest.php`

## Questions to answer before patching

- Is the change about triage reads, assignment ownership, link consistency, or internal-note writes?
- Which route must require `conversation.manage` and an idempotency key?
- Can the branch or linked entity drift after the mutation?
- Does the feature flag or rollout story change for a single branch?
