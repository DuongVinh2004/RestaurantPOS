# RestaurantPOS Audit Program

This directory contains historical audits, the current adversarial audit, and the live remediation program.

## Current source of truth

Read these documents in this order:

1. [`restaurantpos-adversarial-audit-remediation-tracker.md`](restaurantpos-adversarial-audit-remediation-tracker.md) — current cursor, batch status, finding status, verification and evidence.
2. [`restaurantpos-adversarial-audit-remediation-roadmap.md`](restaurantpos-adversarial-audit-remediation-roadmap.md) — execution order, dependencies, owned paths and exit gates.
3. [`restaurantpos-exhaustive-adversarial-audit-2026-07-14.md`](restaurantpos-exhaustive-adversarial-audit-2026-07-14.md) — immutable source audit and finding evidence.
4. [`restaurantpos-adversarial-audit-worktree-inventory-2026-07-15.md`](restaurantpos-adversarial-audit-worktree-inventory-2026-07-15.md) — B00 path ownership, quarantine decisions and baseline verification.

The audit report must remain unchanged. Progress, corrections and closure evidence belong in the tracker, not in the source report.

## Program status

- Production decision: `NO-GO`.
- Program state: `ACTIVE`.
- Current execution cursor: `B12 — immutable recipe consumption and kitchen wastage (READY)`. B07 remains externally blocked; do not start B08.
- Findings: 30 total; 7 closed, 2 code-fixed, 3 partial and 18 open. `PARTIAL` and `CODE_FIXED` are not release-safe closure.
- Last status review: `2026-07-18`.

## Historical documents

The following documents predate the 2026-07-14 audit. They remain useful as historical context or command references, but they are not production-readiness authority:

- [`strict-production-readiness-audit.md`](strict-production-readiness-audit.md)
- [`project-quality-scorecard.md`](project-quality-scorecard.md)
- [`project-quality-backlog.md`](project-quality-backlog.md)
- [`project-elevation-roadmap-2026-05-23.md`](project-elevation-roadmap-2026-05-23.md)
- [`controlled-staging-rehearsal-report.md`](controlled-staging-rehearsal-report.md)
- [`../codex-accelerated-execution-roadmap.md`](../codex-accelerated-execution-roadmap.md)
- [`../codex-execution-pack.md`](../codex-execution-pack.md)
- [`../codex-parallel-agent-prompts.md`](../codex-parallel-agent-prompts.md)

If any historical statement conflicts with the current audit, roadmap or tracker, the current tracker wins.
