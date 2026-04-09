# Round 5 — consistency and legacy fallback cleanup

This round is code-only: no schema migration is required.

## What changed

- Clarified the reservation status semantic trap where persisted DB value `Reserved` really means the guest has already checked in.
- Added helper methods on `ReservationStatus` so new code can say `checkedIn()` / `checkedInDbValue()` / `activeDbValues()` instead of scattering raw `Reserved` checks.
- Tightened staff auth fallback behavior so production no longer silently falls back to env keys just because the DB-backed store is unavailable. Any such fallback is now explicit opt-in.
- Strengthened environment validation warnings around role-name fallback and env-based staff auth fallbacks.
- Upgraded customer-auth legacy validator severity to an error in production when `user_auth_tokens` auth is still enabled.

## Rollout impact

- No database change is needed for this round.
- If production still depends on implicit env fallback when `staff_api_keys` is unavailable, set `STAFF_AUTH_ALLOW_ENV_FALLBACK=true` temporarily during rollout, then remove it after DB-backed keys are verified.
- New code should prefer the `ReservationStatus` helper methods over direct `Reserved` comparisons.
