---
name: Backend + Staff-Web Batch
about: Plan one narrow, reviewable roadmap batch or sub-batch
title: "[Batch ?] "
labels: ["roadmap", "batch", "needs-triage"]
assignees: []
---

## Batch

- [ ] Batch 0 - Enablement Lane
- [ ] Batch 1 - Staff Bootstrap + Auth/RBAC Completion
- [ ] Batch 2 - FOH + Waiting + Check-in Operator Loop
- [ ] Batch 3 - Order Lifecycle + Service Session Completion
- [ ] Batch 4 - Kitchen/KDS Web Surface
- [ ] Batch 5 - Checkout + Refund + Cashier Go-Live Hardening
- [ ] Batch 6 - Inventory + Reporting + Admin Operations Surface
- [ ] Batch 7 - Release Loop, Runbooks, and Final Integration

## Intent

Describe the concrete user or operator outcome this batch must unlock.

## Priority Alignment

- [ ] This batch matches the current priority order from `AGENTS.md`
- [ ] The scope is narrow enough for one reviewable PR or a small PR stack
- [ ] This batch extends existing foundations instead of rewriting working areas
- [ ] Speculative cleanup is explicitly out of scope

## In Scope

- [ ]

## Out of Scope

- [ ]

## Likely Changed Files

- [ ]

Shared seams, if touched:

- [ ] `routes/api.php`
- [ ] `config/booking.php`
- [ ] `config/staff_capabilities.php`
- [ ] `database/schema/mysql-schema.sql`

## Verification

- [ ] Targeted backend tests:
- [ ] Contract or harness checks:
- [ ] `npm run test`
- [ ] `npm run build`
- [ ] `npm run smoke:live`
- [ ] Runtime or release gates, if applicable:

## Plugin Workflow

- [ ] `@github`: issue ownership, linked PR, and CI status
- [ ] `@build-web-apps`: required for UX or page-flow review
- [ ] `@vercel`: preview, build log, or runtime-log evidence
- [ ] `@sentry`: regression watch or release-scoped errors
- [ ] `@hugging-face`: explicitly out of scope for core completion

## Done When

- [ ]

## Added or Updated Tests

- [ ]

## Remaining Risks

- [ ]

## Linked Evidence

- PR:
- Preview:
- Sentry:
- Docs or runbooks:
