# Release Readiness Summary

**Date:** YYYY-MM-DD
**Release Branch / Commit:** main / <commit-hash>
**Environment:** Local / Staging / Production

This document serves as a repeatable template for capturing release verification evidence. **Do not write PASS unless the command was actually run and passed.**

## Final Readiness Status
**Status:** [ READY | NOT READY ]
**Decision Notes:** [ Add reasoning here ]

## Backend Verification
| Command | Purpose | Result | Evidence Path | Notes |
|---------|---------|--------|---------------|-------|
| `composer verify:select -- --base=origin/main` | Smart test selection | NOT RUN | | |
| `vendor\bin\pint --test` | Code style check | NOT RUN | | |
| `vendor\bin\phpstan analyse` | Static analysis | NOT RUN | | |
| `php artisan test` | Full test suite | NOT RUN | | |
| `composer test:critical` | Critical flow tests | NOT RUN | | |

## Frontend Verification
| Command | Purpose | Result | Evidence Path | Notes |
|---------|---------|--------|---------------|-------|
| `npm run test` (staff-web) | Unit/Component tests | NOT RUN | | |
| `npm run build` (staff-web) | Production build | NOT RUN | | |
| `npm run lint` (customer-web) | Linting | NOT RUN | | |
| `npm run typecheck` (customer-web) | Type checking | NOT RUN | | |
| `npm run test` (customer-web) | Unit tests | NOT RUN | | |
| `npm run build` (customer-web) | Production build | NOT RUN | | |
| `npm run test:e2e:smoke` | E2E smoke tests | NOT RUN | | |

## Runtime Verification
| Command | Purpose | Result | Evidence Path | Notes |
|---------|---------|--------|---------------|-------|
| `php artisan booking:doctor --json` | System health check | NOT RUN | | |
| `php artisan booking:deploy-check` | Deployment preflight | NOT RUN | | |

## API Contract Verification
| Command | Purpose | Result | Evidence Path | Notes |
|---------|---------|--------|---------------|-------|
| `php artisan booking:release-manifest` | Drift detection | NOT RUN | | |

## Security & Dependency Checks
| Command | Purpose | Result | Evidence Path | Notes |
|---------|---------|--------|---------------|-------|
| `composer audit` | PHP dependencies | NOT RUN | | |
| `npm audit` | JS dependencies | NOT RUN | | |

## Known Failed / Not-Run Checks
*   *List any checks that failed or were skipped, and provide justification if the release is still considered ready.*
