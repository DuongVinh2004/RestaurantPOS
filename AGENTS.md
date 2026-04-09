# AGENTS.md

## Project intent
This repository is being hardened into a lean full RestaurantPOS production-grade backend.
Do not overbuild low-priority features before core flows are complete.

## Priority order
1. Auth / Identity / RBAC
2. Walk-in + service session + dine-in POS flow
3. Order lifecycle
4. Kitchen / KDS
5. Checkout / payment / refund / cashier shift
6. Inventory basic but useful
7. Reporting / ops / go-live hardening

## Engineering rules
- Read existing code before changing anything.
- Prefer extending current foundations over large rewrites.
- Keep controllers thin.
- Put business logic in services/domain logic.
- Add or update tests for every meaningful change.
- Protect production safety: transactions, idempotency, locking, authorization, audit.
- Avoid cosmetic or speculative changes.
- Work in small reviewable batches.

## Output discipline
For every batch:
- explain the intent
- list changed files
- list added/updated tests
- state remaining risks
