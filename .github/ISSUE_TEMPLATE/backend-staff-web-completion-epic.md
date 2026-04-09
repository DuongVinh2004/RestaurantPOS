---
name: Backend + Staff-Web Completion Epic
about: Track the full backend + staff-web completion roadmap across all batches
title: "[Roadmap] Backend + Staff-Web Completion"
labels: ["roadmap", "epic"]
assignees: []
---

## Goal

Use this issue as the single tracking thread for backend + `staff-web` completion. Keep the roadmap aligned with `AGENTS.md` priority order and only move to lower-priority batches after the higher-priority outcome is truly stable.

## Priority Guardrails

- [ ] Batch order follows `Auth / Identity / RBAC -> FOH / service session -> Order lifecycle -> Kitchen / KDS -> Checkout / cashier -> Inventory -> Reporting / ops`
- [ ] Work is split into narrow, reviewable issues instead of mega-PRs
- [ ] Shared seams stay explicit when touched:
  - `routes/api.php`
  - `config/booking.php`
  - `config/staff_capabilities.php`
  - `database/schema/mysql-schema.sql`
- [ ] Generated FE artifacts are regenerated from backend source of truth, not edited manually
- [ ] Runtime-sensitive changes include live/runtime verification, not only sqlite-backed tests

## Batch Checklist

- [ ] Batch 0 - Enablement Lane
- [ ] Batch 1 - Staff Bootstrap + Auth/RBAC Completion
- [ ] Batch 2 - FOH + Waiting + Check-in Operator Loop
- [ ] Batch 3 - Order Lifecycle + Service Session Completion
- [ ] Batch 4 - Kitchen/KDS Web Surface
- [ ] Batch 5 - Checkout + Refund + Cashier Go-Live Hardening
- [ ] Batch 6 - Inventory + Reporting + Admin Operations Surface
- [ ] Batch 7 - Release Loop, Runbooks, and Final Integration

## Current Focus

- [ ] Batch 0 running in parallel with Batch 1
- [ ] Batch 1 is the current primary delivery lane
- [ ] Lower-priority batches are blocked until the active batch has explicit done criteria and verification evidence

## Standard Release Evidence

- [ ] `php artisan booking:harness:web-auth --json`
- [ ] `php artisan booking:harness:golden-flows --json --manifest-path=storage/app/uat/scenario-pack.json`
- [ ] `npm run test`
- [ ] `npm run build`
- [ ] `npm run smoke:live`
- [ ] `php artisan booking:doctor --json`
- [ ] `php artisan booking:deploy-check --mode=preflight --strict`

## Plugin Operating Model

- [ ] `@github` is used as the source of truth for issue, PR, and CI ownership
- [ ] `@build-web-apps` is used on frontend/operator workflow batches
- [ ] `@vercel` is used for preview deploy, build logs, and runtime review once preview exists
- [ ] `@sentry` is used for staging or release regression triage once environments are wired
- [ ] `@hugging-face` stays deferred until all core completion batches are finished

## Linked Work

- [ ] One issue exists per batch or sub-batch
- [ ] Each PR links back to the owning issue
- [ ] Preview, smoke, and release evidence are attached in the relevant issue or PR
- [ ] Runbook or contract docs are updated when operator behavior changes

## Risks / Blockers

- Current workspace is not backed by a real `.git` worktree, so `@github` workflow value stays limited until the repo is pushed from a real clone
- Shared seams can still collide late if issues drift outside their original batch boundary
- Preview-only success is not enough; browser UX and runtime smoke must stay part of the gate
