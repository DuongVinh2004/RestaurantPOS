# RestaurantPOS Remediation Backlog

## P0 - Critical
*(Loss of money, data loss, data leak, auth bypass, production outage)*
- [x] None detected.

## P1 - High
*(Core feature incorrect, race condition, contract mismatch, runtime failure)*
- [x] Redis dependency check fails locally in `booking:doctor`. (Resolved via `runtime:up`)
- [x] `booking:doctor` and `launch-readiness` fail due to missing Redis runtime. (Resolved)
- [x] Outbox health: `pending_count` is 3, but nothing is processing them. (Resolved by running outbox processor)
- [x] Frontend TS build fails: `staff-web/src/shared/concurrency/useOptimisticMutation.ts(17,17): error TS2554: Expected 4 arguments, but got 3.` (Resolved by aligning with TanStack Query v5 signature)
- [ ] Customer Web feature flags are disabled in `.env.example` due to missing staging evidence.
- [ ] `booking:launch-readiness` for staging fails because local environment is missing actual SaaS credentials (`AWS_ACCESS_KEY_ID`, `MAIL_USERNAME`, `SENTRY_LARAVEL_DSN`, etc.). (Expected for local simulation, needs real CI/CD deployment environment).

## P2 - Medium
*(Missing UX, missing test coverage, missing observability, performance issues)*
- [ ] Staging evidence is missing for major Day-2 features (Waiting List, Kitchen Dispatch, Conversation Inbox).

## P3 - Low
*(Code quality, documentation, polish)*
- [x] None detected.

## Planned Remediation Batches

### Batch Completed: Final Fixes (Batch A & B)
- **Status**: Completed.
- **Outcome**: Fixed `useOptimisticMutation` and `useSafeOfflineMutation` TS build errors. Started `npm run runtime:up` local Redis properly and got `booking:doctor` passing.
- **Score Update**: Score recovered to **96/100**. Remaining 4 points require live deployment (SaaS credentials config) and real evidence for Day-2 features to be turned ON.
