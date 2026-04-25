# Feature Flags Rollout Guide

## Intent

This foundation adds lightweight rollout control for sensitive flows without introducing a heavy third-party flag framework.

- Resolution scope: environment + optional branch override
- Safe default: missing DB override falls back to config defaults
- Kill switch intent: disable new or sensitive flow entry points quickly
- Auditability: every console set or clear writes an audit log entry

## Registered Features

| Feature key | Default in `testing` / `local` | Default in `*` | Primary intent |
| --- | --- | --- | --- |
| `customer.bill_self_payment` | enabled | disabled | Customer bill self-payment session creation; contract-visible but off for day-1 launch |
| `waiting_list.advanced_automation` | enabled | disabled | Semi-automated queue advance after decline / expiry |
| `staff.kitchen_dispatch` | enabled | disabled | Kitchen dispatch and ticket mutation flows |
| `inventory.uplift` | enabled | disabled | Inventory + purchasing advanced admin surface |
| `staff.conversation_inbox` | enabled | disabled | Staff conversation inbox read/workflow surface |
| `staff.conversation_ai_assist` | enabled | disabled | Optional conversation summary and follow-up hints inside inbox detail |

Unknown feature keys always resolve disabled.

## Day-1 Launch Posture

For staging and limited production, the launch-readiness gate uses the wildcard `*` default as the production-like source of truth. Day-1 starts with all registered rollout flags below disabled unless the release ticket records an audited override, target branch, rollback owner, and matching evidence.

| Feature key | Day-1 state | Launch note |
| --- | --- | --- |
| `customer.bill_self_payment` | off | Staff settlement is the day-1 payment path. |
| `waiting_list.advanced_automation` | off | Canonical notify and seat flows remain the operator path. |
| `staff.kitchen_dispatch` | off | Kitchen/KDS mutations are outside day-1 limited-production scope. |
| `inventory.uplift` | off | Advanced inventory and purchasing workflows are outside day-1 limited-production scope. |
| `staff.conversation_inbox` | off | Keep behind controlled rollout evidence. |
| `staff.conversation_ai_assist` | off | Optional assist stays off by default even if inbox is later enabled. |

`booking:launch-readiness` fails the `day1_feature_flag_posture` check if one of these flags loses its kill switch, lacks an explicit wildcard default, or has a wildcard default that does not match the day-1 state.

These backend flags are only part of the launch truth. Customer-web still keeps waiting-list, benefits, privacy, data export, and preorder env-gated by default, and customer payment-session wording must stay contract-visible only until real provider evidence promotes it.

## Resolution Order

For a known feature, effective state resolves in this order:

1. exact environment + exact branch
2. exact environment + global scope
3. wildcard environment + exact branch
4. wildcard environment + global scope
5. config default from `config/feature_flags.php`

Global scope is represented in storage with `branch_id = 0` and `environment = '*'`.

## Storage Model

Overrides are stored in `feature_flags`.

- `feature_key`
- `environment`
- `branch_id`
- `enabled`
- `reason`
- `updated_by`
- `row_version`
- timestamps

The table has a unique key on `feature_key + environment + branch_id`.

## Audit Model

Console mutations record audit events:

- `feature_flag.updated`
- `feature_flag.cleared`

Primary audit entity:

- `entity_type = feature_flag`
- `entity_id = <feature_key>|<environment>|<branch_id or global>`

When the scope is branch-local, the audit record also includes a `branch` subject.

## Console Operations

List effective state:

```bash
php artisan booking:feature-flags:list --json
php artisan booking:feature-flags:list --feature=customer.bill_self_payment --branch-id=3 --environment=production --json
```

Set an override:

```bash
php artisan booking:feature-flags:set customer.bill_self_payment off --branch-id=3 --environment=production --reason="canary paused" --actor-user-id=12 --json
```

Clear an override:

```bash
php artisan booking:feature-flags:clear customer.bill_self_payment --branch-id=3 --environment=production --actor-user-id=12 --json
```

## Integrated Flows

### `customer.bill_self_payment`

- gated in bill preview capability output
- gated in new customer bill payment session creation
- existing session show / refresh / confirm are intentionally not blocked in phase 1 so in-flight sessions can still settle
- this flag staying off is the day-1 contract; do not market bill self-pay as live launch scope just because the contract routes remain visible

### `waiting_list.advanced_automation`

- gated in orchestration context and action hints
- gated in queue advance mutation
- canonical notify and seat flows remain available
- customer waiting-list still stays off by default; day-1 waiting-list work is manual staff operation only

### `staff.kitchen_dispatch`

- gated in dispatch order
- gated in ticket fire / bump / recall mutations
- read-only station and change feeds are not gated in phase 1
- visible kitchen routes do not change the day-1 posture; dispatch and ticket mutation rollout is still held back

### `inventory.uplift`

- gated at admin inventory surface
- gated at admin purchasing surface
- branch-aware gating applies when branch scope is present on the request or resolved from purchase orders
- receiving lifecycle and reconciliation notes live in [`docs/runbooks/inventory-purchasing-lifecycle.md`](./inventory-purchasing-lifecycle.md)

### `staff.conversation_inbox`

- gated in inbox list resolution
- gated in conversation detail resolution
- gated in assignment, take-over, unlink, link, and internal-note mutations
- contract visibility is not launch permission; keep the inbox out of the day-1 operator promise until explicit evidence promotes it

### `staff.conversation_ai_assist`

- gated only in the optional `data.ai_assist` envelope on conversation detail
- never blocks inbox list, detail read, take-over, notes, or outbound reply
- disabled state must still return conversation detail with a stable fallback payload

## Safe Default Behavior

- no DB row: use config default
- missing table: use config default
- unknown feature key: disabled

This keeps production-like environments safe by default and avoids all-or-nothing deploy coupling.

## Phase 1 Limitations

- no admin HTTP write surface yet; console is the operator path
- no percentage rollout, actor targeting, or cohort segmentation
- no branch-aware conversation list fallback when `branch_id` is omitted; list resolution falls back to global scope in that case
- kitchen read-only endpoints remain visible even when dispatch mutation flags are off
- no scheduled rollout windows or automatic expiry for overrides
